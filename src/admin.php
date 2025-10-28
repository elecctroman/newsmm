<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/customer.php';

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
        'wallet_balance' => 0,
        'pending_topups' => 0,
    ];

    $metrics['category_count'] = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $metrics['product_count'] = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $metrics['inventory_value'] = (int) $pdo->query('SELECT COALESCE(SUM(price_cents), 0) FROM products')->fetchColumn();
    $metrics['order_count'] = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $metrics['revenue_total'] = (int) $pdo->query("SELECT COALESCE(SUM(total_cents), 0) FROM orders WHERE status IN ('processing', 'completed', 'refunded')")->fetchColumn();
    $metrics['pending_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'processing')")->fetchColumn();

    $walletReady = ensureWalletInfrastructure($pdo);

    if ($walletReady && tableHasColumn($pdo, 'customer_users', 'balance_cents')) {
        $metrics['wallet_balance'] = (int) $pdo->query('SELECT COALESCE(SUM(balance_cents), 0) FROM customer_users')->fetchColumn();
    }

    if ($walletReady && tableHasColumn($pdo, 'wallet_topups', 'status')) {
        $metrics['pending_topups'] = (int) $pdo->query("SELECT COUNT(*) FROM wallet_topups WHERE status = 'pending'")->fetchColumn();
    }

    return $metrics;
}

function getRecentProducts(PDO $pdo, int $limit = 5): array
{
    $hasUpdated = tableHasColumn($pdo, 'products', 'updated_at');
    $orderColumn = $hasUpdated ? 'p.updated_at' : 'p.created_at';
    $select = 'SELECT p.id, p.name, p.price_label, p.price_cents, ';
    $select .= $hasUpdated ? 'p.updated_at' : 'p.created_at AS updated_at';
    $select .= ', c.name AS category_title FROM products p INNER JOIN categories c ON c.id = p.category_id ';
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

function fetchCategoryTree(PDO $pdo): array
{
    ensureCategoryInfrastructure($pdo);

    $hasUpdated = tableHasColumn($pdo, 'categories', 'updated_at');
    $select = 'SELECT id, parent_id, name, slug, description, emoji, icon_url, sort_order, is_active, created_at, ';
    $select .= $hasUpdated ? 'updated_at' : 'created_at AS updated_at';
    $select .= ' FROM categories ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC, name ASC';

    $stmt = $pdo->query($select);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return [];
    }

    $byId = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $byId[$id] = [
            'id' => $id,
            'parent_id' => $parentId,
            'slug' => $row['slug'],
            'title' => $row['name'],
            'name' => $row['name'],
            'description' => $row['description'],
            'emoji' => $row['emoji'],
            'icon' => $row['icon_url'],
            'icon_url' => $row['icon_url'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'subcategories' => [],
        ];
    }

    foreach ($byId as $id => &$category) {
        $parentId = $category['parent_id'];
        if ($parentId !== null && isset($byId[$parentId]) && $parentId !== $id) {
            $byId[$parentId]['subcategories'][] = &$category;
        }
    }
    unset($category);

    $roots = [];
    foreach ($byId as $id => $category) {
        if ($category['parent_id'] === null || !isset($byId[$category['parent_id']])) {
            $roots[] = $category;
        }
    }

    return $roots;
}

function fetchAllCategories(PDO $pdo): array
{
    return fetchCategoryTree($pdo);
}

function fetchCategoryById(PDO $pdo, int $id): ?array
{
    ensureCategoryInfrastructure($pdo);
    $hasUpdated = tableHasColumn($pdo, 'categories', 'updated_at');
    $select = 'SELECT id, parent_id, name, slug, description, emoji, icon_url, sort_order, is_active, created_at, ';
    $select .= $hasUpdated ? 'updated_at' : 'created_at AS updated_at';
    $select .= ' FROM categories WHERE id = :id';
    $stmt = $pdo->prepare($select);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
        'slug' => $row['slug'],
        'name' => $row['name'],
        'title' => $row['name'],
        'description' => $row['description'],
        'emoji' => $row['emoji'],
        'icon' => $row['icon_url'],
        'icon_url' => $row['icon_url'],
        'sort_order' => (int) $row['sort_order'],
        'is_active' => (bool) $row['is_active'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}

function fetchFlatCategories(PDO $pdo): array
{
    ensureCategoryInfrastructure($pdo);
    $hasUpdated = tableHasColumn($pdo, 'categories', 'updated_at');
    $select = 'SELECT id, parent_id, name, slug, description, emoji, icon_url, sort_order, is_active, created_at, ';
    $select .= $hasUpdated ? 'updated_at' : 'created_at AS updated_at';
    $select .= ' FROM categories ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC, name ASC';

    $stmt = $pdo->query($select);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return [];
    }

    $treeMap = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $treeMap[$id] = [
            'id' => $id,
            'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'emoji' => $row['emoji'],
            'icon_url' => $row['icon_url'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    $depths = calculateCategoryDepths($treeMap);

    $flat = [];
    foreach ($treeMap as $id => $row) {
        $flat[] = array_merge($row, ['depth' => $depths[$id] ?? 0]);
    }

    usort($flat, function (array $a, array $b): int {
        $depthCmp = ($a['depth'] <=> $b['depth']);
        if ($depthCmp !== 0) {
            return $depthCmp;
        }
        $parentCmp = ($a['parent_id'] <=> $b['parent_id']);
        if ($parentCmp !== 0) {
            return $parentCmp;
        }
        $orderCmp = ($a['sort_order'] <=> $b['sort_order']);
        if ($orderCmp !== 0) {
            return $orderCmp;
        }
        return strcmp($a['name'], $b['name']);
    });

    return $flat;
}

function calculateCategoryDepths(array $categories): array
{
    $depths = [];
    $visited = [];

    $compute = function (int $id) use (&$categories, &$depths, &$visited, &$compute): int {
        if (isset($depths[$id])) {
            return $depths[$id];
        }

        if (isset($visited[$id])) {
            return 0;
        }

        $visited[$id] = true;
        $category = $categories[$id] ?? null;
        if (!$category) {
            return $depths[$id] = 0;
        }

        $parentId = $category['parent_id'] ?? null;
        if ($parentId === null || !isset($categories[$parentId])) {
            return $depths[$id] = 0;
        }

        $depth = 1 + $compute($parentId);
        return $depths[$id] = $depth;
    };

    foreach (array_keys($categories) as $id) {
        $compute((int) $id);
    }

    return $depths;
}

function categoryExists(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return (bool) $stmt->fetchColumn();
}

function categorySlugExists(PDO $pdo, string $slug, ?int $ignoreId = null): bool
{
    $sql = 'SELECT 1 FROM categories WHERE slug = :slug';
    $params = ['slug' => $slug];
    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function productSlugExists(PDO $pdo, string $slug, ?int $ignoreId = null): bool
{
    $sql = 'SELECT 1 FROM products WHERE slug = :slug';
    $params = ['slug' => $slug];
    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function generateUniqueCategorySlug(PDO $pdo, string $name, ?int $ignoreId = null, ?string $preferredSlug = null): string
{
    $base = $preferredSlug !== null ? sanitizeSlug($preferredSlug) : sanitizeSlug($name);
    if ($base === '') {
        $base = 'kategori';
    }

    $slug = $base;
    $counter = 2;
    while (categorySlugExists($pdo, $slug, $ignoreId)) {
        $slug = $base . '-' . $counter;
        $counter++;
    }

    return $slug;
}

function generateUniqueProductSlug(PDO $pdo, string $name, ?int $ignoreId = null, ?string $preferredSlug = null): string
{
    $base = $preferredSlug !== null ? sanitizeSlug($preferredSlug) : sanitizeSlug($name);
    if ($base === '') {
        $base = 'urun';
    }

    $slug = $base;
    $counter = 2;
    while (productSlugExists($pdo, $slug, $ignoreId)) {
        $slug = $base . '-' . $counter;
        $counter++;
    }

    return $slug;
}

function categoryWouldCreateCycle(PDO $pdo, int $categoryId, int $parentId): bool
{
    if ($categoryId === $parentId) {
        return true;
    }

    $current = $parentId;
    $stmt = $pdo->prepare('SELECT parent_id FROM categories WHERE id = :id');

    $seen = [];

    while ($current !== null) {
        if ($current === $categoryId) {
            return true;
        }
        if (isset($seen[$current])) {
            break;
        }
        $seen[$current] = true;
        $stmt->execute(['id' => $current]);
        $next = $stmt->fetchColumn();
        if ($next === false) {
            break;
        }
        $current = $next !== null ? (int) $next : null;
    }

    return false;
}

function createCategory(PDO $pdo, array $payload): int
{
    ensureCategoryInfrastructure($pdo);

    $name = trim((string) ($payload['name'] ?? $payload['title'] ?? ''));
    $description = sanitizeNullableString($payload['description'] ?? null);
    $emoji = trim((string) ($payload['emoji'] ?? '')) ?: null;
    $icon = sanitizeNullableString($payload['icon_url'] ?? $payload['icon'] ?? null);
    $sortOrder = (int) ($payload['sort_order'] ?? 0);
    $isActive = isset($payload['is_active']) ? (bool) $payload['is_active'] : true;
    $parentId = isset($payload['parent_id']) ? (int) $payload['parent_id'] : null;

    if ($parentId !== null && $parentId <= 0) {
        $parentId = null;
    }

    if ($name === '') {
        throw new InvalidArgumentException('Kategori adı gereklidir.');
    }

    if ($parentId !== null && !categoryExists($pdo, $parentId)) {
        throw new InvalidArgumentException('Seçilen üst kategori bulunamadı.');
    }

    $providedSlug = sanitizeSlug((string) ($payload['slug'] ?? ''));
    $slug = generateUniqueCategorySlug($pdo, $name, null, $providedSlug !== '' ? $providedSlug : null);

    $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name, slug, description, emoji, icon_url, sort_order, is_active) VALUES (:parent_id, :name, :slug, :description, :emoji, :icon_url, :sort_order, :is_active)');
    $stmt->execute([
        'parent_id' => $parentId,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'emoji' => $emoji,
        'icon_url' => $icon,
        'sort_order' => $sortOrder,
        'is_active' => $isActive ? 1 : 0,
    ]);

    return (int) $pdo->lastInsertId();
}

function updateCategory(PDO $pdo, int $id, array $payload): void
{
    ensureCategoryInfrastructure($pdo);

    $existing = fetchCategoryById($pdo, $id);
    if (!$existing) {
        throw new InvalidArgumentException('Kategori bulunamadı.');
    }

    $name = trim((string) ($payload['name'] ?? $payload['title'] ?? $existing['name'] ?? ''));
    $description = array_key_exists('description', $payload) ? sanitizeNullableString($payload['description']) : ($existing['description'] ?? null);
    $emoji = array_key_exists('emoji', $payload) ? (trim((string) $payload['emoji']) ?: null) : ($existing['emoji'] ?? null);
    $icon = array_key_exists('icon_url', $payload)
        ? sanitizeNullableString($payload['icon_url'])
        : (array_key_exists('icon', $payload) ? sanitizeNullableString($payload['icon']) : ($existing['icon_url'] ?? null));
    $sortOrder = (int) ($payload['sort_order'] ?? $existing['sort_order'] ?? 0);
    $isActive = isset($payload['is_active']) ? (bool) $payload['is_active'] : (bool) ($existing['is_active'] ?? true);
    $parentId = array_key_exists('parent_id', $payload) ? (int) $payload['parent_id'] : ($existing['parent_id'] ?? null);

    if ($parentId !== null && $parentId <= 0) {
        $parentId = null;
    }

    if ($name === '') {
        throw new InvalidArgumentException('Kategori adı gereklidir.');
    }

    if ($parentId !== null && !categoryExists($pdo, $parentId)) {
        throw new InvalidArgumentException('Seçilen üst kategori bulunamadı.');
    }

    if ($parentId !== null && categoryWouldCreateCycle($pdo, $id, $parentId)) {
        throw new InvalidArgumentException('Kategori kendi alt kategorisine bağlanamaz.');
    }

    $providedSlug = sanitizeSlug((string) ($payload['slug'] ?? $existing['slug'] ?? ''));
    $slug = generateUniqueCategorySlug($pdo, $name, $id, $providedSlug !== '' ? $providedSlug : null);

    $stmt = $pdo->prepare('UPDATE categories SET parent_id = :parent_id, name = :name, slug = :slug, description = :description, emoji = :emoji, icon_url = :icon_url, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
    $stmt->execute([
        'parent_id' => $parentId,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'emoji' => $emoji,
        'icon_url' => $icon,
        'sort_order' => $sortOrder,
        'is_active' => $isActive ? 1 : 0,
        'id' => $id,
    ]);
}

function deleteCategory(PDO $pdo, int $id): void
{
    ensureCategoryInfrastructure($pdo);

    $stmt = $pdo->prepare('UPDATE categories SET parent_id = NULL WHERE parent_id = :id');
    $stmt->execute(['id' => $id]);

    $stmt = $pdo->prepare('UPDATE products SET subcategory_id = NULL WHERE subcategory_id = :id');
    try {
        $stmt->execute(['id' => $id]);
    } catch (Throwable $ignored) {
        // ignore
    }

    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function fetchAllProducts(PDO $pdo): array
{
    ensureCategoryInfrastructure($pdo);

    $hasUpdated = tableHasColumn($pdo, 'products', 'updated_at');
    $hasSubcategory = tableHasColumn($pdo, 'products', 'subcategory_id');
    $hasSlug = tableHasColumn($pdo, 'products', 'slug');
    $hasShortDescription = tableHasColumn($pdo, 'products', 'short_description');
    $hasDescriptionHtml = tableHasColumn($pdo, 'products', 'description_html');
    $hasOldPrice = tableHasColumn($pdo, 'products', 'old_price_cents');
    $hasDiscount = tableHasColumn($pdo, 'products', 'discount_percent');
    $hasStockStatus = tableHasColumn($pdo, 'products', 'stock_status');
    $hasPrimaryImage = tableHasColumn($pdo, 'products', 'primary_image_url');
    $hasGallery = tableHasColumn($pdo, 'products', 'gallery_json');
    $hasBadge = tableHasColumn($pdo, 'products', 'badge_json');
    $hasStatus = tableHasColumn($pdo, 'products', 'status');
    $hasIsActive = tableHasColumn($pdo, 'products', 'is_active');
    $hasMetaTitle = tableHasColumn($pdo, 'products', 'meta_title');
    $hasMetaDescription = tableHasColumn($pdo, 'products', 'meta_description');
    $hasPublishedAt = tableHasColumn($pdo, 'products', 'published_at');
    $hasAvailableAt = tableHasColumn($pdo, 'products', 'available_at');

    $columns = [
        'p.id',
        $hasSlug ? 'p.slug' : "NULL AS slug",
        'p.name',
        'p.price_label',
        'p.price_cents',
        $hasOldPrice ? 'p.old_price_cents' : 'NULL AS old_price_cents',
        $hasDiscount ? 'p.discount_percent' : 'NULL AS discount_percent',
        $hasShortDescription ? 'p.short_description' : 'NULL AS short_description',
        $hasDescriptionHtml ? 'p.description_html' : 'NULL AS description_html',
        $hasStockStatus ? 'p.stock_status' : "'in_stock' AS stock_status",
        $hasPrimaryImage ? 'p.primary_image_url' : 'NULL AS primary_image_url',
        $hasGallery ? 'p.gallery_json' : 'NULL AS gallery_json',
        $hasBadge ? 'p.badge_json' : 'NULL AS badge_json',
        'p.note',
        'p.sort_order',
        $hasStatus ? 'p.status' : "'published' AS status",
        $hasIsActive ? 'p.is_active' : '1 AS is_active',
        $hasMetaTitle ? 'p.meta_title' : 'NULL AS meta_title',
        $hasMetaDescription ? 'p.meta_description' : 'NULL AS meta_description',
        'p.created_at',
        $hasUpdated ? 'p.updated_at' : 'p.created_at AS updated_at',
        $hasPublishedAt ? 'p.published_at' : 'NULL AS published_at',
        $hasAvailableAt ? 'p.available_at' : 'NULL AS available_at',
        'c.name AS category_title',
        'c.slug AS category_slug',
        'c.id AS category_id',
        'c.icon_url AS category_icon',
    ];

    if ($hasSubcategory) {
        $columns[] = 'p.subcategory_id';
        $columns[] = 'sc.name AS subcategory_title';
        $columns[] = 'sc.slug AS subcategory_slug';
        $columns[] = 'sc.icon_url AS subcategory_icon';
    } else {
        $columns[] = 'NULL AS subcategory_id';
        $columns[] = 'NULL AS subcategory_title';
        $columns[] = 'NULL AS subcategory_slug';
        $columns[] = 'NULL AS subcategory_icon';
    }

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM products p INNER JOIN categories c ON c.id = p.category_id';
    if ($hasSubcategory) {
        $sql .= ' LEFT JOIN categories sc ON sc.id = p.subcategory_id';
    }
    $sql .= ' ORDER BY c.sort_order ASC, c.name ASC, p.sort_order ASC, p.name ASC';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchProductById(PDO $pdo, int $id): ?array
{
    ensureCategoryInfrastructure($pdo);

    $hasSubcategory = tableHasColumn($pdo, 'products', 'subcategory_id');
    $hasSlug = tableHasColumn($pdo, 'products', 'slug');
    $hasShortDescription = tableHasColumn($pdo, 'products', 'short_description');
    $hasDescriptionHtml = tableHasColumn($pdo, 'products', 'description_html');
    $hasOldPrice = tableHasColumn($pdo, 'products', 'old_price_cents');
    $hasDiscount = tableHasColumn($pdo, 'products', 'discount_percent');
    $hasStockStatus = tableHasColumn($pdo, 'products', 'stock_status');
    $hasPrimaryImage = tableHasColumn($pdo, 'products', 'primary_image_url');
    $hasGallery = tableHasColumn($pdo, 'products', 'gallery_json');
    $hasBadge = tableHasColumn($pdo, 'products', 'badge_json');
    $hasStatus = tableHasColumn($pdo, 'products', 'status');
    $hasIsActive = tableHasColumn($pdo, 'products', 'is_active');
    $hasMetaTitle = tableHasColumn($pdo, 'products', 'meta_title');
    $hasMetaDescription = tableHasColumn($pdo, 'products', 'meta_description');
    $hasPublishedAt = tableHasColumn($pdo, 'products', 'published_at');
    $hasAvailableAt = tableHasColumn($pdo, 'products', 'available_at');

    $columns = [
        'p.id',
        'p.category_id',
        $hasSubcategory ? 'p.subcategory_id' : 'NULL AS subcategory_id',
        'p.name',
        $hasSlug ? 'p.slug' : "NULL AS slug",
        'p.price_label',
        'p.price_cents',
        $hasOldPrice ? 'p.old_price_cents' : 'NULL AS old_price_cents',
        $hasDiscount ? 'p.discount_percent' : 'NULL AS discount_percent',
        $hasShortDescription ? 'p.short_description' : 'NULL AS short_description',
        $hasDescriptionHtml ? 'p.description_html' : 'NULL AS description_html',
        $hasStockStatus ? 'p.stock_status' : "'in_stock' AS stock_status",
        $hasPrimaryImage ? 'p.primary_image_url' : 'NULL AS primary_image_url',
        $hasGallery ? 'p.gallery_json' : 'NULL AS gallery_json',
        $hasBadge ? 'p.badge_json' : 'NULL AS badge_json',
        'p.note',
        'p.sort_order',
        $hasStatus ? 'p.status' : "'published' AS status",
        $hasIsActive ? 'p.is_active' : '1 AS is_active',
        $hasMetaTitle ? 'p.meta_title' : 'NULL AS meta_title',
        $hasMetaDescription ? 'p.meta_description' : 'NULL AS meta_description',
        $hasPublishedAt ? 'p.published_at' : 'NULL AS published_at',
        $hasAvailableAt ? 'p.available_at' : 'NULL AS available_at',
        'p.created_at',
        'p.updated_at'
    ];

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM products p WHERE p.id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    return $product ?: null;
}

function createProduct(PDO $pdo, array $payload): int
{
    ensureCategoryInfrastructure($pdo);
    $subcategoryId = isset($payload['subcategory_id']) ? (int) $payload['subcategory_id'] : null;
    if ($subcategoryId <= 0) {
        $subcategoryId = null;
    }

    $fields = ['category_id', 'subcategory_id', 'name', 'price_label', 'price_cents', 'note', 'sort_order'];
    $params = [
        'category_id' => (int) $payload['category_id'],
        'subcategory_id' => $subcategoryId,
        'name' => trim((string) ($payload['name'] ?? '')),
        'price_label' => (string) $payload['price_label'],
        'price_cents' => (int) $payload['price_cents'],
        'note' => sanitizeNullableString($payload['note'] ?? null),
        'sort_order' => (int) ($payload['sort_order'] ?? 0),
    ];

    $hasSlug = tableHasColumn($pdo, 'products', 'slug');
    if ($hasSlug) {
        $fields[] = 'slug';
        $params['slug'] = generateUniqueProductSlug($pdo, $params['name'], null, $payload['slug'] ?? null);
    }

    if (tableHasColumn($pdo, 'products', 'short_description')) {
        $fields[] = 'short_description';
        $params['short_description'] = sanitizeNullableString($payload['short_description'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'description_html')) {
        $fields[] = 'description_html';
        $params['description_html'] = sanitizeNullableString($payload['description_html'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'old_price_cents')) {
        $fields[] = 'old_price_cents';
        $params['old_price_cents'] = isset($payload['old_price_cents']) ? (int) $payload['old_price_cents'] : null;
    }
    if (tableHasColumn($pdo, 'products', 'discount_percent')) {
        $fields[] = 'discount_percent';
        $params['discount_percent'] = isset($payload['discount_percent']) ? (float) $payload['discount_percent'] : null;
    }
    if (tableHasColumn($pdo, 'products', 'stock_status')) {
        $fields[] = 'stock_status';
        $allowedStock = ['in_stock', 'limited', 'preorder', 'out_of_stock'];
        $stockStatus = strtolower((string) ($payload['stock_status'] ?? 'in_stock'));
        $params['stock_status'] = in_array($stockStatus, $allowedStock, true) ? $stockStatus : 'in_stock';
    }
    if (tableHasColumn($pdo, 'products', 'primary_image_url')) {
        $fields[] = 'primary_image_url';
        $params['primary_image_url'] = sanitizeNullableString($payload['primary_image_url'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'gallery_json')) {
        $fields[] = 'gallery_json';
        $params['gallery_json'] = normalizeJsonList($payload['gallery'] ?? ($payload['gallery_json'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'badge_json')) {
        $fields[] = 'badge_json';
        $params['badge_json'] = normalizeJsonList($payload['badges'] ?? ($payload['badge_json'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'status')) {
        $fields[] = 'status';
        $allowedStatus = ['draft', 'review', 'published', 'archived'];
        $status = strtolower((string) ($payload['status'] ?? 'published'));
        $params['status'] = in_array($status, $allowedStatus, true) ? $status : 'published';
    }
    if (tableHasColumn($pdo, 'products', 'is_active')) {
        $fields[] = 'is_active';
        $params['is_active'] = isset($payload['is_active']) ? ((bool) $payload['is_active'] ? 1 : 0) : 1;
    }
    if (tableHasColumn($pdo, 'products', 'meta_title')) {
        $fields[] = 'meta_title';
        $params['meta_title'] = sanitizeNullableString($payload['meta_title'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'meta_description')) {
        $fields[] = 'meta_description';
        $params['meta_description'] = sanitizeNullableString($payload['meta_description'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'published_at')) {
        $fields[] = 'published_at';
        $params['published_at'] = (!isset($params['status']) || $params['status'] === 'published') ? date('Y-m-d H:i:s') : null;
    }
    if (tableHasColumn($pdo, 'products', 'available_at')) {
        $fields[] = 'available_at';
        $params['available_at'] = sanitizeNullableString($payload['available_at'] ?? null);
    }

    $placeholders = array_map(static function (string $field): string {
        return ':' . $field;
    }, array_keys($params));

    $sql = 'INSERT INTO products (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function updateProduct(PDO $pdo, int $id, array $payload): void
{
    ensureCategoryInfrastructure($pdo);
    $existing = fetchProductById($pdo, $id);
    if (!$existing) {
        throw new InvalidArgumentException('Ürün bulunamadı.');
    }
    $subcategoryId = isset($payload['subcategory_id']) ? (int) $payload['subcategory_id'] : null;
    if ($subcategoryId <= 0) {
        $subcategoryId = null;
    }

    $fields = [
        'category_id = :category_id',
        'subcategory_id = :subcategory_id',
        'name = :name',
        'price_label = :price_label',
        'price_cents = :price_cents',
        'note = :note',
        'sort_order = :sort_order',
    ];

    $params = [
        'category_id' => (int) $payload['category_id'],
        'subcategory_id' => $subcategoryId,
        'name' => trim((string) ($payload['name'] ?? $existing['name'] ?? '')),
        'price_label' => (string) $payload['price_label'],
        'price_cents' => (int) $payload['price_cents'],
        'note' => sanitizeNullableString($payload['note'] ?? null),
        'sort_order' => (int) ($payload['sort_order'] ?? $existing['sort_order'] ?? 0),
        'id' => $id,
    ];

    if (tableHasColumn($pdo, 'products', 'slug')) {
        $fields[] = 'slug = :slug';
        $params['slug'] = generateUniqueProductSlug($pdo, $params['name'], $id, $payload['slug'] ?? ($existing['slug'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'short_description')) {
        $fields[] = 'short_description = :short_description';
        $params['short_description'] = sanitizeNullableString($payload['short_description'] ?? ($existing['short_description'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'description_html')) {
        $fields[] = 'description_html = :description_html';
        $params['description_html'] = sanitizeNullableString($payload['description_html'] ?? ($existing['description_html'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'old_price_cents')) {
        $fields[] = 'old_price_cents = :old_price_cents';
        $params['old_price_cents'] = isset($payload['old_price_cents']) ? (int) $payload['old_price_cents'] : ($existing['old_price_cents'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'discount_percent')) {
        $fields[] = 'discount_percent = :discount_percent';
        $params['discount_percent'] = isset($payload['discount_percent']) ? (float) $payload['discount_percent'] : ($existing['discount_percent'] ?? null);
    }
    if (tableHasColumn($pdo, 'products', 'stock_status')) {
        $fields[] = 'stock_status = :stock_status';
        $allowedStock = ['in_stock', 'limited', 'preorder', 'out_of_stock'];
        $stockStatus = strtolower((string) ($payload['stock_status'] ?? ($existing['stock_status'] ?? 'in_stock')));
        $params['stock_status'] = in_array($stockStatus, $allowedStock, true) ? $stockStatus : 'in_stock';
    }
    if (tableHasColumn($pdo, 'products', 'primary_image_url')) {
        $fields[] = 'primary_image_url = :primary_image_url';
        $params['primary_image_url'] = sanitizeNullableString($payload['primary_image_url'] ?? ($existing['primary_image_url'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'gallery_json')) {
        $fields[] = 'gallery_json = :gallery_json';
        $params['gallery_json'] = normalizeJsonList($payload['gallery'] ?? ($payload['gallery_json'] ?? ($existing['gallery_json'] ?? null)));
    }
    if (tableHasColumn($pdo, 'products', 'badge_json')) {
        $fields[] = 'badge_json = :badge_json';
        $params['badge_json'] = normalizeJsonList($payload['badges'] ?? ($payload['badge_json'] ?? ($existing['badge_json'] ?? null)));
    }
    if (tableHasColumn($pdo, 'products', 'status')) {
        $fields[] = 'status = :status';
        $allowedStatus = ['draft', 'review', 'published', 'archived'];
        $status = strtolower((string) ($payload['status'] ?? ($existing['status'] ?? 'published')));
        $params['status'] = in_array($status, $allowedStatus, true) ? $status : ($existing['status'] ?? 'published');
    }
    if (tableHasColumn($pdo, 'products', 'is_active')) {
        $fields[] = 'is_active = :is_active';
        $params['is_active'] = isset($payload['is_active']) ? ((bool) $payload['is_active'] ? 1 : 0) : ($existing['is_active'] ?? 1);
    }
    if (tableHasColumn($pdo, 'products', 'meta_title')) {
        $fields[] = 'meta_title = :meta_title';
        $params['meta_title'] = sanitizeNullableString($payload['meta_title'] ?? ($existing['meta_title'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'meta_description')) {
        $fields[] = 'meta_description = :meta_description';
        $params['meta_description'] = sanitizeNullableString($payload['meta_description'] ?? ($existing['meta_description'] ?? null));
    }
    if (tableHasColumn($pdo, 'products', 'published_at')) {
        $fields[] = 'published_at = :published_at';
        $params['published_at'] = ($params['status'] ?? ($existing['status'] ?? 'published')) === 'published'
            ? ($existing['published_at'] ?? date('Y-m-d H:i:s'))
            : null;
    }
    if (tableHasColumn($pdo, 'products', 'available_at')) {
        $fields[] = 'available_at = :available_at';
        $params['available_at'] = sanitizeNullableString($payload['available_at'] ?? ($existing['available_at'] ?? null));
    }

    $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function deleteProduct(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function fetchHomeBlocks(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, type, category_id, title_override, product_limit, show_only_instock, pinned_product_ids, sort_order, is_active FROM home_blocks ORDER BY sort_order ASC, id ASC');
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($blocks as &$block) {
        $block['pinned_product_ids'] = $block['pinned_product_ids'] ? json_decode((string) $block['pinned_product_ids'], true) ?? [] : [];
    }
    unset($block);
    return $blocks;
}

function createHomeBlock(PDO $pdo, array $payload): int
{
    $type = strtolower((string) ($payload['type'] ?? 'category'));
    if (!in_array($type, ['category', 'custom'], true)) {
        $type = 'category';
    }
    $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;
    if ($type === 'category' && ($categoryId === null || $categoryId <= 0)) {
        throw new InvalidArgumentException('Kategori blokları için kategori seçilmelidir.');
    }

    $pinnedJson = null;
    if (isset($payload['pinned_product_ids'])) {
        $ids = $payload['pinned_product_ids'];
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), static function ($value) {
                return $value !== '';
            });
        }
        if (is_array($ids)) {
            $clean = [];
            foreach ($ids as $value) {
                if (is_numeric($value)) {
                    $clean[] = (int) $value;
                }
            }
            if (!empty($clean)) {
                $pinnedJson = json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_SLASHES);
            }
        }
    }

    $stmt = $pdo->prepare('INSERT INTO home_blocks (type, category_id, title_override, product_limit, show_only_instock, pinned_product_ids, sort_order, is_active) VALUES (:type, :category_id, :title_override, :product_limit, :show_only_instock, :pinned_product_ids, :sort_order, :is_active)');
    $stmt->execute([
        'type' => $type,
        'category_id' => $categoryId ?: null,
        'title_override' => sanitizeNullableString($payload['title_override'] ?? null),
        'product_limit' => max(1, (int) ($payload['product_limit'] ?? 6)),
        'show_only_instock' => isset($payload['show_only_instock']) ? ((bool) $payload['show_only_instock'] ? 1 : 0) : 1,
        'pinned_product_ids' => $pinnedJson,
        'sort_order' => (int) ($payload['sort_order'] ?? 0),
        'is_active' => isset($payload['is_active']) ? ((bool) $payload['is_active'] ? 1 : 0) : 1,
    ]);
    return (int) $pdo->lastInsertId();
}

function updateHomeBlock(PDO $pdo, int $id, array $payload): void
{
    $type = strtolower((string) ($payload['type'] ?? 'category'));
    if (!in_array($type, ['category', 'custom'], true)) {
        $type = 'category';
    }
    $categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;
    if ($type === 'category' && ($categoryId === null || $categoryId <= 0)) {
        throw new InvalidArgumentException('Kategori blokları için kategori seçilmelidir.');
    }

    $pinnedJson = null;
    if (isset($payload['pinned_product_ids'])) {
        $ids = $payload['pinned_product_ids'];
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), static function ($value) {
                return $value !== '';
            });
        }
        if (is_array($ids)) {
            $clean = [];
            foreach ($ids as $value) {
                if (is_numeric($value)) {
                    $clean[] = (int) $value;
                }
            }
            if (!empty($clean)) {
                $pinnedJson = json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_SLASHES);
            }
        }
    }

    $stmt = $pdo->prepare('UPDATE home_blocks SET type = :type, category_id = :category_id, title_override = :title_override, product_limit = :product_limit, show_only_instock = :show_only_instock, pinned_product_ids = :pinned_product_ids, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
    $stmt->execute([
        'type' => $type,
        'category_id' => $categoryId ?: null,
        'title_override' => sanitizeNullableString($payload['title_override'] ?? null),
        'product_limit' => max(1, (int) ($payload['product_limit'] ?? 6)),
        'show_only_instock' => isset($payload['show_only_instock']) ? ((bool) $payload['show_only_instock'] ? 1 : 0) : 1,
        'pinned_product_ids' => $pinnedJson,
        'sort_order' => (int) ($payload['sort_order'] ?? 0),
        'is_active' => isset($payload['is_active']) ? ((bool) $payload['is_active'] ? 1 : 0) : 1,
        'id' => $id,
    ]);
}

function deleteHomeBlock(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM home_blocks WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function fetchOrders(PDO $pdo): array
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
        $hasCustomerId ? 'o.customer_id' : 'NULL AS customer_id',
        'o.order_no',
        'o.customer_name',
        'o.customer_email',
        'o.customer_phone',
        'o.status',
        'o.total_cents',
        'o.currency',
        'o.notes',
        'o.created_at',
        $selectColumn,
        $hasWalletDeduction ? 'o.wallet_deduction_cents' : '0 AS wallet_deduction_cents',
        $hasCardCharge ? 'o.card_charge_cents' : '0 AS card_charge_cents',
        $hasPaymentChannel ? 'o.payment_channel' : "NULL AS payment_channel",
        $hasPaymentReference ? 'o.payment_reference' : "NULL AS payment_reference",
    ];

    if (tableHasColumn($pdo, 'order_items', 'id')) {
        $selectParts[] = '(SELECT COALESCE(SUM(quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count';
    } else {
        $selectParts[] = 'NULL AS item_count';
    }

    if ($hasCustomerId) {
        $selectParts[] = 'cu.name AS account_name';
        $selectParts[] = 'cu.email AS account_email';
        $selectParts[] = 'cu.phone AS account_phone';
    } else {
        $selectParts[] = 'NULL AS account_name';
        $selectParts[] = 'NULL AS account_email';
        $selectParts[] = 'NULL AS account_phone';
    }

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM orders o ';
    if ($hasCustomerId) {
        $sql .= 'LEFT JOIN customer_users cu ON cu.id = o.customer_id ';
    }
    $sql .= 'ORDER BY o.created_at DESC';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchOrderById(PDO $pdo, int $id): ?array
{
    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $hasWalletDeduction = tableHasColumn($pdo, 'orders', 'wallet_deduction_cents');
    $hasCardCharge = tableHasColumn($pdo, 'orders', 'card_charge_cents');
    $hasPaymentChannel = tableHasColumn($pdo, 'orders', 'payment_channel');
    $hasPaymentReference = tableHasColumn($pdo, 'orders', 'payment_reference');
    $select = 'SELECT id, ' . ($hasCustomerId ? 'customer_id' : 'NULL AS customer_id')
        . ', order_no, customer_name, customer_email, customer_phone, status, total_cents, currency, notes'
        . ', ' . ($hasWalletDeduction ? 'wallet_deduction_cents' : '0 AS wallet_deduction_cents')
        . ', ' . ($hasCardCharge ? 'card_charge_cents' : '0 AS card_charge_cents')
        . ', ' . ($hasPaymentChannel ? 'payment_channel' : 'NULL AS payment_channel')
        . ', ' . ($hasPaymentReference ? 'payment_reference' : 'NULL AS payment_reference')
        . ' FROM orders WHERE id = :id';
    $stmt = $pdo->prepare($select);
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    return $order ?: null;
}

function createOrder(PDO $pdo, array $payload): int
{
    ensureOrderPaymentColumns($pdo);
    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $hasWalletDeduction = tableHasColumn($pdo, 'orders', 'wallet_deduction_cents');
    $hasCardCharge = tableHasColumn($pdo, 'orders', 'card_charge_cents');
    $hasPaymentChannel = tableHasColumn($pdo, 'orders', 'payment_channel');
    $hasPaymentReference = tableHasColumn($pdo, 'orders', 'payment_reference');

    $columns = ['order_no', 'customer_name', 'customer_email', 'customer_phone', 'status', 'total_cents', 'currency', 'notes'];
    $placeholders = [':order_no', ':customer_name', ':customer_email', ':customer_phone', ':status', ':total_cents', ':currency', ':notes'];
    $params = [
        'order_no' => $payload['order_no'],
        'customer_name' => trim($payload['customer_name'] ?? ''),
        'customer_email' => sanitizeNullableString($payload['customer_email'] ?? null),
        'customer_phone' => sanitizeNullableString($payload['customer_phone'] ?? null),
        'status' => $payload['status'],
        'total_cents' => (int) $payload['total_cents'],
        'currency' => $payload['currency'] ?? 'TRY',
        'notes' => sanitizeNullableString($payload['notes'] ?? null),
    ];

    if ($hasWalletDeduction) {
        $columns[] = 'wallet_deduction_cents';
        $placeholders[] = ':wallet_deduction_cents';
        $params['wallet_deduction_cents'] = (int) ($payload['wallet_deduction_cents'] ?? 0);
    }

    if ($hasCardCharge) {
        $columns[] = 'card_charge_cents';
        $placeholders[] = ':card_charge_cents';
        $params['card_charge_cents'] = (int) ($payload['card_charge_cents'] ?? 0);
    }

    if ($hasPaymentChannel) {
        $columns[] = 'payment_channel';
        $placeholders[] = ':payment_channel';
        $params['payment_channel'] = sanitizeNullableString($payload['payment_channel'] ?? null);
    }

    if ($hasPaymentReference) {
        $columns[] = 'payment_reference';
        $placeholders[] = ':payment_reference';
        $params['payment_reference'] = sanitizeNullableString($payload['payment_reference'] ?? null);
    }

    if ($hasCustomerId) {
        array_unshift($columns, 'customer_id');
        array_unshift($placeholders, ':customer_id');
        $params['customer_id'] = isset($payload['customer_id']) && $payload['customer_id'] !== null
            ? (int) $payload['customer_id']
            : null;
    }

    $managedTransaction = !$pdo->inTransaction();
    if ($managedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $sql = 'INSERT INTO orders (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orderId = (int) $pdo->lastInsertId();

        if (!empty($payload['items']) && is_array($payload['items'])) {
            replaceOrderItems($pdo, $orderId, $payload['items']);
        }

        if ($managedTransaction) {
            $pdo->commit();
        }

        return $orderId;
    } catch (Throwable $e) {
        if ($managedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function replaceOrderItems(PDO $pdo, int $orderId, array $items): void
{
    if (!tableHasColumn($pdo, 'order_items', 'id')) {
        return;
    }

    $delete = $pdo->prepare('DELETE FROM order_items WHERE order_id = :order_id');
    $delete->execute(['order_id' => $orderId]);

    $insert = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price_cents, total_cents) '
        . 'VALUES (:order_id, :product_id, :product_name, :quantity, :unit_price_cents, :total_cents)'
    );

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $productName = trim((string) ($item['product_name'] ?? ''));
        if ($productName === '') {
            continue;
        }
        $quantity = max(1, min(999, (int) ($item['quantity'] ?? 1)));
        $unitPrice = max(0, (int) ($item['unit_price_cents'] ?? 0));
        $total = (int) ($item['total_cents'] ?? ($unitPrice * $quantity));
        if ($total <= 0 && $unitPrice > 0) {
            $total = $unitPrice * $quantity;
        }

        $insert->execute([
            'order_id' => $orderId,
            'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'total_cents' => $total,
        ]);
    }
}

function updateOrder(PDO $pdo, int $id, array $payload): void
{
    ensureOrderPaymentColumns($pdo);
    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $hasWalletDeduction = tableHasColumn($pdo, 'orders', 'wallet_deduction_cents');
    $hasCardCharge = tableHasColumn($pdo, 'orders', 'card_charge_cents');
    $hasPaymentChannel = tableHasColumn($pdo, 'orders', 'payment_channel');
    $hasPaymentReference = tableHasColumn($pdo, 'orders', 'payment_reference');

    $setParts = [
        'order_no = :order_no',
        'customer_name = :customer_name',
        'customer_email = :customer_email',
        'customer_phone = :customer_phone',
        'status = :status',
        'total_cents = :total_cents',
        'currency = :currency',
        'notes = :notes',
    ];

    if ($hasWalletDeduction) {
        $setParts[] = 'wallet_deduction_cents = :wallet_deduction_cents';
    }

    if ($hasCardCharge) {
        $setParts[] = 'card_charge_cents = :card_charge_cents';
    }

    if ($hasPaymentChannel) {
        $setParts[] = 'payment_channel = :payment_channel';
    }

    if ($hasPaymentReference) {
        $setParts[] = 'payment_reference = :payment_reference';
    }

    if ($hasCustomerId) {
        $setParts[] = 'customer_id = :customer_id';
    }

    $params = [
        'order_no' => $payload['order_no'],
        'customer_name' => trim($payload['customer_name'] ?? ''),
        'customer_email' => sanitizeNullableString($payload['customer_email'] ?? null),
        'customer_phone' => sanitizeNullableString($payload['customer_phone'] ?? null),
        'status' => $payload['status'],
        'total_cents' => (int) $payload['total_cents'],
        'currency' => $payload['currency'] ?? 'TRY',
        'notes' => sanitizeNullableString($payload['notes'] ?? null),
        'id' => $id,
    ];

    if ($hasWalletDeduction) {
        $params['wallet_deduction_cents'] = (int) ($payload['wallet_deduction_cents'] ?? 0);
    }

    if ($hasCardCharge) {
        $params['card_charge_cents'] = (int) ($payload['card_charge_cents'] ?? 0);
    }

    if ($hasPaymentChannel) {
        $params['payment_channel'] = sanitizeNullableString($payload['payment_channel'] ?? null);
    }

    if ($hasPaymentReference) {
        $params['payment_reference'] = sanitizeNullableString($payload['payment_reference'] ?? null);
    }

    if ($hasCustomerId) {
        $params['customer_id'] = isset($payload['customer_id']) && $payload['customer_id'] !== null
            ? (int) $payload['customer_id']
            : null;
    }

    $managedTransaction = !$pdo->inTransaction();
    if ($managedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $sql = 'UPDATE orders SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (array_key_exists('items', $payload) && is_array($payload['items'])) {
            replaceOrderItems($pdo, $id, $payload['items']);
        }

        if ($managedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($managedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $orderCountExpr = $hasCustomerId
        ? '(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id)'
        : '(SELECT COUNT(*) FROM orders o WHERE LOWER(o.customer_email) = LOWER(c.email))';
    $ticketCountExpr = '(SELECT COUNT(*) FROM support_tickets t WHERE t.customer_id = c.id)';

    $select = 'SELECT c.id, c.name, c.email, c.phone, c.created_at, c.updated_at, c.last_login_at';
    if (tableHasColumn($pdo, 'customer_users', 'balance_cents')) {
        $select .= ', c.balance_cents';
    }
    $select .= ', ' . $orderCountExpr . ' AS order_count';
    $select .= ', ' . $ticketCountExpr . ' AS ticket_count';
    $select .= ' FROM customer_users c ORDER BY c.created_at DESC';

    $stmt = $pdo->query($select);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchCustomerProfile(PDO $pdo, int $id): ?array
{
    $hasCustomerId = tableHasColumn($pdo, 'orders', 'customer_id');
    $orderCountExpr = $hasCustomerId
        ? '(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id)'
        : '(SELECT COUNT(*) FROM orders o WHERE LOWER(o.customer_email) = LOWER(c.email))';
    $totalSpentExpr = $hasCustomerId
        ? '(SELECT COALESCE(SUM(o.total_cents), 0) FROM orders o WHERE o.customer_id = c.id)'
        : '(SELECT COALESCE(SUM(o.total_cents), 0) FROM orders o WHERE LOWER(o.customer_email) = LOWER(c.email))';
    $ticketCountExpr = '(SELECT COUNT(*) FROM support_tickets t WHERE t.customer_id = c.id)';

    $stmt = $pdo->prepare(
        'SELECT c.*, '
        . ' ' . $orderCountExpr . ' AS order_count,'
        . ' ' . $totalSpentExpr . ' AS total_spent,'
        . ' ' . $ticketCountExpr . ' AS ticket_count'
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

function fetchOrderItemsAdmin(PDO $pdo, int $orderId): array
{
    if (!tableHasColumn($pdo, 'order_items', 'id')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, product_id, product_name, quantity, unit_price_cents, total_cents, created_at FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchWalletTransactionsAdmin(PDO $pdo, ?int $customerId = null, int $limit = 50): array
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_transactions', 'id')) {
        return [];
    }
    $sql = 'SELECT wt.id, wt.customer_id, wt.type, wt.amount_cents, wt.balance_after, wt.reference_type, wt.reference_id, wt.note, wt.created_at,'
        . ' cu.name AS customer_name, cu.email AS customer_email'
        . ' FROM wallet_transactions wt INNER JOIN customer_users cu ON cu.id = wt.customer_id';
    $params = [];
    if ($customerId !== null) {
        $sql .= ' WHERE wt.customer_id = :customer_id';
        $params['customer_id'] = $customerId;
    }
    $sql .= ' ORDER BY wt.created_at DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchWalletTopups(PDO $pdo, ?string $status = null, int $limit = 50): array
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_topups', 'id')) {
        return [];
    }
    $sql = 'SELECT wt.id, wt.customer_id, wt.reference_code, wt.amount_cents, wt.status, wt.payment_channel, wt.proof_url, wt.notes, wt.admin_notes, wt.processed_by, wt.processed_at, wt.created_at, wt.updated_at,'
        . ' cu.name AS customer_name, cu.email AS customer_email'
        . ' FROM wallet_topups wt INNER JOIN customer_users cu ON cu.id = wt.customer_id';
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE wt.status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY wt.created_at DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchWalletTopupById(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_topups', 'id')) {
        return null;
    }
    $sql = 'SELECT wt.*, cu.name AS customer_name, cu.email AS customer_email FROM wallet_topups wt INNER JOIN customer_users cu ON cu.id = wt.customer_id WHERE wt.id = :id LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function approveWalletTopup(PDO $pdo, int $topupId, int $adminId, ?string $adminNote = null): void
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_topups', 'id')) {
        throw new RuntimeException('Bakiye yükleme özelliği devrede değil.');
    }

    $managedTransaction = !$pdo->inTransaction();
    if ($managedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $topup = fetchWalletTopupById($pdo, $topupId, true);
        if (!$topup) {
            throw new RuntimeException('Yükleme talebi bulunamadı.');
        }
        if ($topup['status'] !== 'pending') {
            throw new RuntimeException('Talep zaten işlenmiş.');
        }

        adjustCustomerBalance(
            $pdo,
            (int) $topup['customer_id'],
            (int) $topup['amount_cents'],
            'deposit',
            'Yükleme #' . $topup['reference_code'],
            'wallet_topup',
            $topupId
        );

        $stmt = $pdo->prepare('UPDATE wallet_topups SET status = :status, processed_by = :processed_by, processed_at = NOW(), admin_notes = :admin_notes WHERE id = :id');
        $stmt->execute([
            'status' => 'approved',
            'processed_by' => $adminId,
            'admin_notes' => sanitizeNullableString($adminNote ?? $topup['admin_notes'] ?? null),
            'id' => $topupId,
        ]);

        if ($managedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($managedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rejectWalletTopup(PDO $pdo, int $topupId, int $adminId, ?string $adminNote = null): void
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_topups', 'id')) {
        throw new RuntimeException('Bakiye yükleme özelliği devrede değil.');
    }

    $managedTransaction = !$pdo->inTransaction();
    if ($managedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $topup = fetchWalletTopupById($pdo, $topupId, true);
        if (!$topup) {
            throw new RuntimeException('Yükleme talebi bulunamadı.');
        }
        if ($topup['status'] !== 'pending') {
            throw new RuntimeException('Talep zaten işlenmiş.');
        }

        $stmt = $pdo->prepare('UPDATE wallet_topups SET status = :status, processed_by = :processed_by, processed_at = NOW(), admin_notes = :admin_notes WHERE id = :id');
        $stmt->execute([
            'status' => 'rejected',
            'processed_by' => $adminId,
            'admin_notes' => sanitizeNullableString($adminNote ?? $topup['admin_notes'] ?? null),
            'id' => $topupId,
        ]);

        if ($managedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($managedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function createManualWalletAdjustment(PDO $pdo, int $customerId, int $amountCents, string $type, string $note, int $adminId): array
{
    if (!ensureWalletInfrastructure($pdo) || !tableHasColumn($pdo, 'wallet_transactions', 'id')) {
        throw new RuntimeException('Bakiye kayıtları yapılandırılmadı.');
    }
    $result = adjustCustomerBalance($pdo, $customerId, $amountCents, $type, $note, 'admin_adjustment', $adminId);
    return $result;
}

