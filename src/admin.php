<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/customer.php';

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return $cache[$key] = (bool) $stmt->fetchColumn();
}

function logActivity(PDO $pdo, ?int $userId, string $action, ?string $entity = null, ?int $entityId = null, ?string $message = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, entity, entity_id, message) VALUES (:user_id, :action, :entity, :entity_id, :message)');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'message' => $message,
        ]);
    } catch (Throwable $e) {
        // activity logs should never break the request flow
    }
}

function getDashboardMetrics(PDO $pdo): array
{
    $metrics = [
        'category_count' => 0,
        'product_count' => 0,
        'inventory_value' => 0,
        'order_count' => 0,
        'revenue_total' => 0,
        'pending_orders' => 0,
    ];

    $metrics['category_count'] = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $metrics['product_count'] = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $metrics['inventory_value'] = (int) $pdo->query('SELECT COALESCE(SUM(price_cents), 0) FROM products')->fetchColumn();
    $metrics['order_count'] = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $metrics['revenue_total'] = (int) $pdo->query("SELECT COALESCE(SUM(total_cents), 0) FROM orders WHERE status IN ('processing', 'completed', 'refunded')")->fetchColumn();
    $metrics['pending_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'processing')")->fetchColumn();

    return $metrics;
}

function getRecentProducts(PDO $pdo, int $limit = 5): array
{
    $hasUpdated = tableHasColumn($pdo, 'products', 'updated_at');
    $orderColumn = $hasUpdated ? 'p.updated_at' : 'p.created_at';
    $select = 'SELECT p.id, p.name, p.price_label, p.price_cents, ';
    $select .= $hasUpdated ? 'p.updated_at' : 'p.created_at AS updated_at';
    $select .= ', c.title AS category_title FROM products p INNER JOIN categories c ON c.id = p.category_id ';
    $sql = $select . 'ORDER BY ' . $orderColumn . ' DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentOrders(PDO $pdo, int $limit = 5): array
{
    $hasUpdated = tableHasColumn($pdo, 'orders', 'updated_at');
    $orderColumn = $hasUpdated ? 'updated_at' : 'created_at';
    $selectColumn = $hasUpdated ? 'updated_at' : 'created_at AS updated_at';
    $stmt = $pdo->prepare("SELECT id, order_no, customer_name, status, total_cents, currency, $selectColumn FROM orders ORDER BY $orderColumn DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentActivity(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare('SELECT l.id, l.action, l.entity, l.entity_id, l.message, l.created_at, u.name AS user_name FROM activity_logs l LEFT JOIN admin_users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchAllCategories(PDO $pdo): array
{
    $hasUpdated = tableHasColumn($pdo, 'categories', 'updated_at');
    $select = 'SELECT id, slug, title, description, emoji, sort_order, created_at, ';
    $select .= $hasUpdated ? 'updated_at' : 'created_at AS updated_at';
    $select .= ' FROM categories ORDER BY sort_order ASC, title ASC';
    $stmt = $pdo->query($select);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchCategoryById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, slug, title, description, emoji, sort_order FROM categories WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    return $category ?: null;
}

function createCategory(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare('INSERT INTO categories (slug, title, description, emoji, sort_order) VALUES (:slug, :title, :description, :emoji, :sort_order)');
    $stmt->execute([
        'slug' => sanitizeSlug($payload['slug'] ?? ''),
        'title' => trim($payload['title'] ?? ''),
        'description' => trim($payload['description'] ?? ''),
        'emoji' => trim($payload['emoji'] ?? ''),
        'sort_order' => (int)($payload['sort_order'] ?? 0),
    ]);
    return (int) $pdo->lastInsertId();
}

function updateCategory(PDO $pdo, int $id, array $payload): void
{
    $stmt = $pdo->prepare('UPDATE categories SET slug = :slug, title = :title, description = :description, emoji = :emoji, sort_order = :sort_order WHERE id = :id');
    $stmt->execute([
        'slug' => sanitizeSlug($payload['slug'] ?? ''),
        'title' => trim($payload['title'] ?? ''),
        'description' => trim($payload['description'] ?? ''),
        'emoji' => trim($payload['emoji'] ?? ''),
        'sort_order' => (int)($payload['sort_order'] ?? 0),
        'id' => $id,
    ]);
}

function deleteCategory(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function fetchAllProducts(PDO $pdo): array
{
    $hasUpdated = tableHasColumn($pdo, 'products', 'updated_at');
    $select = 'SELECT p.id, p.name, p.price_label, p.price_cents, p.note, p.sort_order, p.created_at, ';
    $select .= $hasUpdated ? 'p.updated_at' : 'p.created_at AS updated_at';
    $select .= ', c.title AS category_title, c.slug AS category_slug, c.id AS category_id FROM products p INNER JOIN categories c ON c.id = p.category_id ORDER BY c.sort_order ASC, p.sort_order ASC, p.name ASC';
    $stmt = $pdo->query($select);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchProductById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT p.id, p.category_id, p.name, p.price_label, p.price_cents, p.note, p.sort_order FROM products p WHERE p.id = :id');
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    return $product ?: null;
}

function createProduct(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare('INSERT INTO products (category_id, name, price_label, price_cents, note, sort_order) VALUES (:category_id, :name, :price_label, :price_cents, :note, :sort_order)');
    $stmt->execute([
        'category_id' => (int)$payload['category_id'],
        'name' => trim($payload['name'] ?? ''),
        'price_label' => $payload['price_label'],
        'price_cents' => (int)$payload['price_cents'],
        'note' => sanitizeNullableString($payload['note'] ?? null),
        'sort_order' => (int)($payload['sort_order'] ?? 0),
    ]);
    return (int) $pdo->lastInsertId();
}

function updateProduct(PDO $pdo, int $id, array $payload): void
{
    $stmt = $pdo->prepare('UPDATE products SET category_id = :category_id, name = :name, price_label = :price_label, price_cents = :price_cents, note = :note, sort_order = :sort_order WHERE id = :id');
    $stmt->execute([
        'category_id' => (int)$payload['category_id'],
        'name' => trim($payload['name'] ?? ''),
        'price_label' => $payload['price_label'],
        'price_cents' => (int)$payload['price_cents'],
        'note' => sanitizeNullableString($payload['note'] ?? null),
        'sort_order' => (int)($payload['sort_order'] ?? 0),
        'id' => $id,
    ]);
}

function deleteProduct(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function fetchOrders(PDO $pdo): array
{
    $hasUpdated = tableHasColumn($pdo, 'orders', 'updated_at');
    $selectColumn = $hasUpdated ? 'o.updated_at' : 'o.created_at AS updated_at';
    $sql = 'SELECT o.id, o.customer_id, o.order_no, o.customer_name, o.customer_email, o.customer_phone, o.status, '
        . 'o.total_cents, o.currency, o.notes, o.created_at, ' . $selectColumn
        . ', cu.name AS account_name, cu.email AS account_email, cu.phone AS account_phone '
        . 'FROM orders o '
        . 'LEFT JOIN customer_users cu ON cu.id = o.customer_id '
        . 'ORDER BY o.created_at DESC';
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchOrderById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, customer_id, order_no, customer_name, customer_email, customer_phone, status, total_cents, currency, notes FROM orders WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    return $order ?: null;
}

function createOrder(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare('INSERT INTO orders (customer_id, order_no, customer_name, customer_email, customer_phone, status, total_cents, currency, notes) VALUES (:customer_id, :order_no, :customer_name, :customer_email, :customer_phone, :status, :total_cents, :currency, :notes)');
    $stmt->execute([
        'customer_id' => isset($payload['customer_id']) && $payload['customer_id'] !== null ? (int) $payload['customer_id'] : null,
        'order_no' => $payload['order_no'],
        'customer_name' => trim($payload['customer_name'] ?? ''),
        'customer_email' => sanitizeNullableString($payload['customer_email'] ?? null),
        'customer_phone' => sanitizeNullableString($payload['customer_phone'] ?? null),
        'status' => $payload['status'],
        'total_cents' => (int)$payload['total_cents'],
        'currency' => $payload['currency'] ?? 'TRY',
        'notes' => sanitizeNullableString($payload['notes'] ?? null),
    ]);
    return (int) $pdo->lastInsertId();
}

function updateOrder(PDO $pdo, int $id, array $payload): void
{
    $stmt = $pdo->prepare('UPDATE orders SET customer_id = :customer_id, order_no = :order_no, customer_name = :customer_name, customer_email = :customer_email, customer_phone = :customer_phone, status = :status, total_cents = :total_cents, currency = :currency, notes = :notes WHERE id = :id');
    $stmt->execute([
        'customer_id' => isset($payload['customer_id']) && $payload['customer_id'] !== null ? (int) $payload['customer_id'] : null,
        'order_no' => $payload['order_no'],
        'customer_name' => trim($payload['customer_name'] ?? ''),
        'customer_email' => sanitizeNullableString($payload['customer_email'] ?? null),
        'customer_phone' => sanitizeNullableString($payload['customer_phone'] ?? null),
        'status' => $payload['status'],
        'total_cents' => (int)$payload['total_cents'],
        'currency' => $payload['currency'] ?? 'TRY',
        'notes' => sanitizeNullableString($payload['notes'] ?? null),
        'id' => $id,
    ]);
}

function deleteOrder(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function getOrderStatuses(): array
{
    return [
        'pending' => 'Beklemede',
        'processing' => 'İşleniyor',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal Edildi',
        'refunded' => 'İade Edildi',
    ];
}

function generateOrderNumber(PDO $pdo): string
{
    $prefix = 'LS-' . date('Ymd') . '-';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_no LIKE :prefix');
    $stmt->execute(['prefix' => $prefix . '%']);
    $count = (int) $stmt->fetchColumn();
    $next = $count + 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function fetchSettings(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function updateSettings(PDO $pdo, array $updates): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($updates as $key => $value) {
        $stmt->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }
}

function getAdminUserById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function updateAdminProfile(PDO $pdo, int $id, string $name, string $email, ?string $password = null): void
{
    if ($password !== null && $password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE admin_users SET name = :name, email = :email, password_hash = :password WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hash,
            'id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE admin_users SET name = :name, email = :email WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'id' => $id,
        ]);
    }
}

function fetchAllCustomers(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT c.id, c.name, c.email, c.phone, c.created_at, c.updated_at, c.last_login_at,'
        . ' (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,'
        . ' (SELECT COUNT(*) FROM support_tickets t WHERE t.customer_id = c.id) AS ticket_count'
        . ' FROM customer_users c ORDER BY c.created_at DESC'
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchCustomerProfile(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, '
        . ' (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,'
        . ' (SELECT COALESCE(SUM(o.total_cents), 0) FROM orders o WHERE o.customer_id = c.id) AS total_spent,'
        . ' (SELECT COUNT(*) FROM support_tickets t WHERE t.customer_id = c.id) AS ticket_count'
        . ' FROM customer_users c WHERE c.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    return $customer ?: null;
}

function fetchSupportTicketsAdmin(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT t.id, t.ticket_no, t.subject, t.status, t.priority, t.last_reply_at, t.created_at, t.updated_at,'
        . ' c.name AS customer_name, c.email AS customer_email'
        . ' FROM support_tickets t INNER JOIN customer_users c ON c.id = t.customer_id ORDER BY t.updated_at DESC'
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchSupportTicketAdmin(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT t.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone'
        . ' FROM support_tickets t INNER JOIN customer_users c ON c.id = t.customer_id WHERE t.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ticket ?: null;
}

function respondSupportTicket(PDO $pdo, int $ticketId, int $staffId, string $message, ?string $status = null): void
{
    addSupportMessage($pdo, $ticketId, [
        'author_type' => 'staff',
        'staff_id' => $staffId,
        'message' => $message,
    ]);
    if ($status !== null && $status !== '') {
        $stmt = $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $ticketId,
        ]);
    }
}

function updateTicketStatus(PDO $pdo, int $ticketId, string $status): void
{
    $stmt = $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'id' => $ticketId,
    ]);
}

