<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';

function findCustomerByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM customer_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => trim($email)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return $row;
}

function findCustomerById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM customer_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hydrateCustomer(array $row): array
{
    unset($row['password_hash']);
    return $row;
}

function createCustomer(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare('INSERT INTO customer_users (name, email, phone, password_hash) VALUES (:name, :email, :phone, :password_hash)');
    $stmt->execute([
        'name' => trim($payload['name'] ?? ''),
        'email' => trim(strtolower($payload['email'] ?? '')),
        'phone' => sanitizeNullableString($payload['phone'] ?? null),
        'password_hash' => password_hash((string)($payload['password'] ?? ''), PASSWORD_DEFAULT),
    ]);
    return (int) $pdo->lastInsertId();
}

function authenticateCustomer(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM customer_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => trim(strtolower($email))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if (!password_verify($password, $row['password_hash'])) {
        return null;
    }
    return hydrateCustomer($row);
}

function recordCustomerLogin(PDO $pdo, int $customerId): void
{
    $stmt = $pdo->prepare('UPDATE customer_users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $customerId]);
}

function requireCustomerAuth(string $redirectPath = '/login.php'): array
{
    $customer = getCurrentCustomer();
    if ($customer) {
        return $customer;
    }
    $target = $_SERVER['REQUEST_URI'] ?? '/account/index.php';
    $query = http_build_query(['redirect' => $target]);
    redirect($redirectPath . '?' . $query);
}

function ensureCustomerGuest(): void
{
    if (getCurrentCustomer()) {
        redirect('/account/index.php');
    }
}

function updateCustomerProfile(PDO $pdo, int $customerId, array $payload): void
{
    $fields = ['name' => trim($payload['name'] ?? '')];
    $fields['phone'] = sanitizeNullableString($payload['phone'] ?? null);
    $email = trim(strtolower($payload['email'] ?? ''));
    if ($email !== '') {
        $fields['email'] = $email;
    }

    $password = $payload['password'] ?? null;
    $set = ['name = :name', 'phone = :phone'];
    if (isset($fields['email'])) {
        $set[] = 'email = :email';
    }
    if ($password !== null && $password !== '') {
        $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $set[] = 'password_hash = :password_hash';
    }

    $fields['id'] = $customerId;

    $sql = 'UPDATE customer_users SET ' . implode(', ', $set) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($fields);
}

function fetchCustomerOrders(PDO $pdo, int $customerId, ?string $email = null): array
{
    $hasUpdated = tableHasColumn($pdo, 'orders', 'updated_at');
    $selectColumn = $hasUpdated ? 'o.updated_at' : 'o.created_at AS updated_at';

    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $sql = 'SELECT o.id, o.order_no, o.status, o.total_cents, o.currency, o.notes, o.created_at, ' . $selectColumn . ' FROM orders o';
    $params = [];

    if ($hasCustomerId) {
        $sql .= ' WHERE (o.customer_id = :customer_id';
        $params['customer_id'] = $customerId;
        if ($email !== null && $email !== '') {
            $sql .= ' OR (o.customer_id IS NULL AND LOWER(o.customer_email) = :email)';
            $params['email'] = strtolower(trim($email));
        }
        $sql .= ')';
    } else {
        if ($email === null || trim($email) === '') {
            return [];
        }
        $sql .= ' WHERE LOWER(o.customer_email) = :email';
        $params['email'] = strtolower(trim($email));
    }

    $sql .= ' ORDER BY o.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchCustomerOrderStats(PDO $pdo, int $customerId, ?string $email = null): array
{
    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $params = [];

    if ($hasCustomerId) {
        $sql = 'SELECT status, COUNT(*) AS count FROM orders WHERE (customer_id = :customer_id';
        $params['customer_id'] = $customerId;
        if ($email !== null && $email !== '') {
            $sql .= ' OR (customer_id IS NULL AND LOWER(customer_email) = :email)';
            $params['email'] = strtolower(trim($email));
        }
        $sql .= ') GROUP BY status';
    } else {
        if ($email === null || trim($email) === '') {
            return [
                'total' => 0,
                'by_status' => [],
            ];
        }
        $sql = 'SELECT status, COUNT(*) AS count FROM orders WHERE LOWER(customer_email) = :email GROUP BY status';
        $params['email'] = strtolower(trim($email));
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stats = [
        'total' => 0,
        'by_status' => [],
    ];

    foreach ($rows as $row) {
        $status = $row['status'];
        $count = (int) $row['count'];
        $stats['by_status'][$status] = $count;
        $stats['total'] += $count;
    }

    return $stats;
}

function generateTicketNumber(PDO $pdo): string
{
    $prefix = 'LS-TK-' . date('Ymd') . '-';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_tickets WHERE ticket_no LIKE :prefix');
    $stmt->execute(['prefix' => $prefix . '%']);
    $count = (int) $stmt->fetchColumn();
    $next = $count + 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function createSupportTicket(PDO $pdo, int $customerId, string $subject, string $priority, string $message): int
{
    $ticketNo = generateTicketNumber($pdo);
    $stmt = $pdo->prepare('INSERT INTO support_tickets (customer_id, ticket_no, subject, priority, status, last_reply_at) VALUES (:customer_id, :ticket_no, :subject, :priority, :status, NOW())');
    $stmt->execute([
        'customer_id' => $customerId,
        'ticket_no' => $ticketNo,
        'subject' => trim($subject),
        'priority' => $priority,
        'status' => 'open',
    ]);
    $ticketId = (int) $pdo->lastInsertId();

    addSupportMessage($pdo, $ticketId, [
        'author_type' => 'customer',
        'customer_id' => $customerId,
        'message' => $message,
    ]);

    return $ticketId;
}

function addSupportMessage(PDO $pdo, int $ticketId, array $payload): int
{
    $authorType = $payload['author_type'];
    $customerId = $payload['customer_id'] ?? null;
    $staffId = $payload['staff_id'] ?? null;
    $message = trim((string)($payload['message'] ?? ''));

    $stmt = $pdo->prepare('INSERT INTO support_messages (ticket_id, author_type, customer_id, staff_id, message) VALUES (:ticket_id, :author_type, :customer_id, :staff_id, :message)');
    $stmt->execute([
        'ticket_id' => $ticketId,
        'author_type' => $authorType,
        'customer_id' => $customerId,
        'staff_id' => $staffId,
        'message' => $message,
    ]);
    $messageId = (int) $pdo->lastInsertId();

    $nextStatus = $authorType === 'staff' ? 'staff_reply' : 'customer_reply';
    $stmt = $pdo->prepare('UPDATE support_tickets SET status = :status, last_reply_at = NOW() WHERE id = :id');
    $stmt->execute([
        'status' => $nextStatus,
        'id' => $ticketId,
    ]);

    return $messageId;
}

function fetchCustomerTickets(PDO $pdo, int $customerId): array
{
    $stmt = $pdo->prepare('SELECT t.id, t.ticket_no, t.subject, t.status, t.priority, t.created_at, t.updated_at, t.last_reply_at, (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) AS message_count FROM support_tickets t WHERE t.customer_id = :customer_id ORDER BY t.updated_at DESC');
    $stmt->execute(['customer_id' => $customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchTicketById(PDO $pdo, int $customerId, int $ticketId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :id AND customer_id = :customer_id LIMIT 1');
    $stmt->execute([
        'id' => $ticketId,
        'customer_id' => $customerId,
    ]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ticket ?: null;
}

function fetchTicketMessages(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare('SELECT m.id, m.author_type, m.customer_id, m.staff_id, m.message, m.created_at, cu.name AS customer_name, au.name AS staff_name FROM support_messages m LEFT JOIN customer_users cu ON cu.id = m.customer_id LEFT JOIN admin_users au ON au.id = m.staff_id WHERE m.ticket_id = :ticket_id ORDER BY m.created_at ASC');
    $stmt->execute(['ticket_id' => $ticketId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function closeSupportTicket(PDO $pdo, int $ticketId): void
{
    $stmt = $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        'status' => 'closed',
        'id' => $ticketId,
    ]);
}
