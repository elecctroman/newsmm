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
    $hasWalletDeduction = tableHasColumn($pdo, 'orders', 'wallet_deduction_cents');
    $hasCardCharge = tableHasColumn($pdo, 'orders', 'card_charge_cents');
    $hasPaymentChannel = tableHasColumn($pdo, 'orders', 'payment_channel');
    $hasPaymentReference = tableHasColumn($pdo, 'orders', 'payment_reference');

    $selectParts = [
        'o.id',
        'o.order_no',
        'o.status',
        'o.total_cents',
        'o.currency',
        'o.notes',
        'o.created_at',
        $selectColumn,
        $hasWalletDeduction ? 'o.wallet_deduction_cents' : '0 AS wallet_deduction_cents',
        $hasCardCharge ? 'o.card_charge_cents' : '0 AS card_charge_cents',
        $hasPaymentChannel ? 'o.payment_channel' : 'NULL AS payment_channel',
        $hasPaymentReference ? 'o.payment_reference' : 'NULL AS payment_reference',
    ];

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM orders o';
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

function getSessionCartItems(): array
{
    ensureSession();
    $items = $_SESSION['cart_items'] ?? [];
    $normalized = [];
    foreach ($items as $productId => $quantity) {
        $pid = (int) $productId;
        $qty = max(0, (int) $quantity);
        if ($pid > 0 && $qty > 0) {
            $normalized[$pid] = min(999, $qty);
        }
    }
    return $normalized;
}

function saveSessionCartItems(array $items): void
{
    ensureSession();
    $_SESSION['cart_items'] = $items;
}

function clearSessionCartItems(): void
{
    ensureSession();
    unset($_SESSION['cart_items']);
}

function getSessionCartCount(): int
{
    return array_sum(getSessionCartItems());
}

function fetchProductSnapshot(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.category_id, p.name, p.price_label, p.price_cents, p.note, '
        . 'c.title AS category_title, c.slug AS category_slug, c.emoji AS category_emoji '
        . 'FROM products p '
        . 'LEFT JOIN categories c ON c.id = p.category_id '
        . 'WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        'name' => $row['name'],
        'price_label' => $row['price_label'],
        'price_cents' => (int) $row['price_cents'],
        'note' => $row['note'],
        'category_title' => $row['category_title'],
        'category_slug' => $row['category_slug'],
        'category_emoji' => $row['category_emoji'],
    ];
}

function fetchProductSnapshots(PDO $pdo, array $productIds): array
{
    $ids = array_values(array_unique(array_map('intval', $productIds)));
    $ids = array_filter($ids, static fn (int $id): bool => $id > 0);
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $sql = 'SELECT p.id, p.category_id, p.name, p.price_label, p.price_cents, p.note, '
        . 'c.title AS category_title, c.slug AS category_slug, c.emoji AS category_emoji '
        . 'FROM products p '
        . 'LEFT JOIN categories c ON c.id = p.category_id '
        . 'WHERE p.id IN (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    foreach ($ids as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $map = [];
    foreach ($rows as $row) {
        $pid = (int) $row['id'];
        $map[$pid] = [
            'id' => $pid,
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'name' => $row['name'],
            'price_label' => $row['price_label'],
            'price_cents' => (int) $row['price_cents'],
            'note' => $row['note'],
            'category_title' => $row['category_title'],
            'category_slug' => $row['category_slug'],
            'category_emoji' => $row['category_emoji'],
        ];
    }
    return $map;
}

function addCustomerCartItem(PDO $pdo, int $customerId, int $productId, int $quantity, bool $replace = false): void
{
    $quantity = max(1, min(999, $quantity));
    $stmt = $pdo->prepare(
        'INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (:customer_id, :product_id, :quantity) '
        . 'ON DUPLICATE KEY UPDATE quantity = LEAST(999, CASE WHEN :replace_mode = 1 THEN VALUES(quantity) ELSE quantity + VALUES(quantity) END), '
        . 'updated_at = NOW()'
    );
    $stmt->execute([
        'customer_id' => $customerId,
        'product_id' => $productId,
        'quantity' => $quantity,
        'replace_mode' => $replace ? 1 : 0,
    ]);
}

function setCustomerCartItemQuantity(PDO $pdo, int $customerId, int $productId, int $quantity): void
{
    $quantity = max(0, min(999, $quantity));
    if ($quantity === 0) {
        removeCustomerCartItem($pdo, $customerId, $productId);
        return;
    }
    $stmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity, updated_at = NOW() WHERE customer_id = :customer_id AND product_id = :product_id');
    $stmt->execute([
        'quantity' => $quantity,
        'customer_id' => $customerId,
        'product_id' => $productId,
    ]);
}

function removeCustomerCartItem(PDO $pdo, int $customerId, int $productId): void
{
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE customer_id = :customer_id AND product_id = :product_id');
    $stmt->execute([
        'customer_id' => $customerId,
        'product_id' => $productId,
    ]);
}

function clearCustomerCart(PDO $pdo, int $customerId): void
{
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE customer_id = :customer_id');
    $stmt->execute(['customer_id' => $customerId]);
}

function getCustomerCartQuantities(PDO $pdo, int $customerId): array
{
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart_items WHERE customer_id = :customer_id');
    $stmt->execute(['customer_id' => $customerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $quantities = [];
    foreach ($rows as $row) {
        $pid = (int) $row['product_id'];
        $qty = max(0, (int) $row['quantity']);
        if ($pid > 0 && $qty > 0) {
            $quantities[$pid] = min(999, $qty);
        }
    }
    return $quantities;
}

function buildCartView(PDO $pdo, array $quantities): array
{
    if (empty($quantities)) {
        return [
            'items' => [],
            'subtotal_cents' => 0,
            'total_cents' => 0,
            'currency' => 'TRY',
            'count' => 0,
        ];
    }

    $products = fetchProductSnapshots($pdo, array_keys($quantities));
    $items = [];
    $subtotal = 0;
    $count = 0;

    foreach ($quantities as $productId => $quantity) {
        if (!isset($products[$productId])) {
            continue;
        }
        $product = $products[$productId];
        $qty = max(1, min(999, (int) $quantity));
        $lineTotal = (int) $product['price_cents'] * $qty;
        $items[] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'price_cents' => (int) $product['price_cents'],
            'price_label' => $product['price_label'],
            'note' => $product['note'],
            'quantity' => $qty,
            'line_total_cents' => $lineTotal,
            'category' => [
                'title' => $product['category_title'],
                'slug' => $product['category_slug'],
                'emoji' => $product['category_emoji'],
            ],
        ];
        $subtotal += $lineTotal;
        $count += $qty;
    }

    usort($items, static function (array $a, array $b): int {
        return strcasecmp((string) $a['name'], (string) $b['name']);
    });

    return [
        'items' => $items,
        'subtotal_cents' => $subtotal,
        'total_cents' => $subtotal,
        'currency' => 'TRY',
        'count' => $count,
    ];
}

function resolveCartPaymentBreakdown(int $totalCents, int $balanceCents, string $mode, int $requestedWalletCents): array
{
    $normalizedMode = in_array($mode, ['wallet', 'card', 'mixed'], true) ? $mode : 'wallet';
    $available = max(0, $balanceCents);
    $wallet = 0;

    if ($normalizedMode === 'wallet') {
        $wallet = min($available, max(0, $totalCents));
    } elseif ($normalizedMode === 'mixed') {
        $wallet = max(0, min($requestedWalletCents, $available, max(0, $totalCents)));
    }

    if ($normalizedMode === 'card') {
        $wallet = 0;
    }

    $card = max(0, $totalCents - $wallet);

    return [
        'mode' => $normalizedMode,
        'wallet_cents' => $wallet,
        'card_cents' => $card,
    ];
}

function fetchCartSnapshot(PDO $pdo, ?array $customer): array
{
    if ($customer && isset($customer['id'])) {
        $quantities = getCustomerCartQuantities($pdo, (int) $customer['id']);
    } else {
        $quantities = getSessionCartItems();
    }
    return buildCartView($pdo, $quantities);
}

function getCustomerCartCount(PDO $pdo, int $customerId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE customer_id = :customer_id');
    $stmt->execute(['customer_id' => $customerId]);
    return (int) $stmt->fetchColumn();
}

function getActiveCartCount(?PDO $pdo, ?array $customer): int
{
    if ($customer && isset($customer['id']) && $pdo instanceof PDO) {
        return getCustomerCartCount($pdo, (int) $customer['id']);
    }
    return getSessionCartCount();
}

function mergeSessionCartIntoCustomer(PDO $pdo, int $customerId): void
{
    $sessionCart = getSessionCartItems();
    if (empty($sessionCart)) {
        return;
    }
    foreach ($sessionCart as $productId => $quantity) {
        addCustomerCartItem($pdo, $customerId, (int) $productId, (int) $quantity, false);
    }
    clearSessionCartItems();
}

function syncCustomerCartToSession(PDO $pdo, int $customerId): void
{
    $quantities = getCustomerCartQuantities($pdo, $customerId);
    saveSessionCartItems($quantities);
}

function getCustomerBalance(PDO $pdo, int $customerId): int
{
    if (!ensureWalletInfrastructure($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT balance_cents FROM customer_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $customerId]);
    $balance = $stmt->fetchColumn();
    return $balance !== false ? (int) $balance : 0;
}

function adjustCustomerBalance(PDO $pdo, int $customerId, int $amountCents, string $type, ?string $note = null, ?string $referenceType = null, ?int $referenceId = null): array
{
    if (!ensureWalletInfrastructure($pdo)) {
        throw new RuntimeException('Bakiye desteği etkin değil.');
    }

    $allowedTypes = ['deposit', 'purchase', 'refund', 'adjustment', 'withdrawal'];
    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Geçersiz bakiye işlem tipi.');
    }

    $managedTransaction = !$pdo->inTransaction();
    if ($managedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT balance_cents FROM customer_users WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $customerId]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            throw new RuntimeException('Müşteri kaydı bulunamadı.');
        }

        $newBalance = (int) $current + (int) $amountCents;
        if ($newBalance < 0) {
            throw new RuntimeException('Yetersiz bakiye.');
        }

        $updateStmt = $pdo->prepare('UPDATE customer_users SET balance_cents = :balance WHERE id = :id');
        $updateStmt->execute([
            'balance' => $newBalance,
            'id' => $customerId,
        ]);

        $insertStmt = $pdo->prepare(
            'INSERT INTO wallet_transactions (customer_id, type, amount_cents, balance_after, reference_type, reference_id, note) '
            . 'VALUES (:customer_id, :type, :amount_cents, :balance_after, :reference_type, :reference_id, :note)'
        );
        $insertStmt->execute([
            'customer_id' => $customerId,
            'type' => $type,
            'amount_cents' => $amountCents,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => sanitizeNullableString($note),
        ]);

        $transactionId = (int) $pdo->lastInsertId();

        if ($managedTransaction) {
            $pdo->commit();
        }

        return [
            'transaction_id' => $transactionId,
            'balance' => $newBalance,
        ];
    } catch (Throwable $e) {
        if ($managedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function fetchWalletTransactions(PDO $pdo, int $customerId, int $limit = 25): array
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_transactions', 'id')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, type, amount_cents, balance_after, reference_type, reference_id, note, created_at FROM wallet_transactions WHERE customer_id = :customer_id ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function generateTopupReference(PDO $pdo): string
{
    if (!ensureWalletInfrastructure($pdo)) {
        return 'LS-DP-' . date('Ymd') . '-0001';
    }
    $prefix = 'LS-DP-' . date('Ymd') . '-';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM wallet_topups WHERE reference_code LIKE :prefix');
    $stmt->execute(['prefix' => $prefix . '%']);
    $count = (int) $stmt->fetchColumn();
    $next = $count + 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function createWalletTopupRequest(PDO $pdo, int $customerId, array $payload): int
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_topups', 'id')) {
        throw new RuntimeException('Bakiye yükleme desteği etkin değil.');
    }

    $amount = isset($payload['amount_cents']) ? (int) $payload['amount_cents'] : 0;
    if ($amount <= 0) {
        throw new InvalidArgumentException('Geçerli bir tutar girin.');
    }

    $reference = generateTopupReference($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO wallet_topups (customer_id, reference_code, amount_cents, status, payment_channel, proof_url, notes) '
        . 'VALUES (:customer_id, :reference_code, :amount_cents, :status, :payment_channel, :proof_url, :notes)'
    );
    $stmt->execute([
        'customer_id' => $customerId,
        'reference_code' => $reference,
        'amount_cents' => $amount,
        'status' => 'pending',
        'payment_channel' => sanitizeNullableString($payload['payment_channel'] ?? null),
        'proof_url' => sanitizeNullableString($payload['proof_url'] ?? null),
        'notes' => sanitizeNullableString($payload['notes'] ?? null),
    ]);

    return (int) $pdo->lastInsertId();
}

function fetchCustomerTopups(PDO $pdo, int $customerId, int $limit = 25): array
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_topups', 'id')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, reference_code, amount_cents, status, payment_channel, proof_url, notes, admin_notes, processed_by, processed_at, created_at, updated_at FROM wallet_topups WHERE customer_id = :customer_id ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchCustomerOrderItems(PDO $pdo, int $orderId): array
{
    if (!tableHasColumn($pdo, 'order_items', 'id')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT product_id, product_name, quantity, unit_price_cents, total_cents, created_at FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
