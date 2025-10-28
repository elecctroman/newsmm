<?php

declare(strict_types=1);

function tableHasColumn(PDO $pdo, string $table, string $column, bool $refresh = false): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (!$refresh && array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sanitizedTable = str_replace('`', '``', $table);

    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$sanitizedTable}` LIKE :column");
        $stmt->execute(['column' => $column]);
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $showColumnsException) {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (\Throwable $fallbackException) {
            $exists = false;
        }
    }

    return $cache[$key] = $exists;
}

function normalizeHexColor(?string $color, string $fallback = '#000000'): string
{
    $value = trim((string) ($color ?? ''));
    if ($value === '') {
        return strtolower($fallback);
    }
    if ($value[0] !== '#') {
        $value = '#' . $value;
    }
    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
        return strtolower($fallback);
    }
    if (strlen($value) === 4) {
        $value = sprintf(
            '#%s%s%s%s%s%s',
            $value[1], $value[1],
            $value[2], $value[2],
            $value[3], $value[3]
        );
    }
    return strtolower($value);
}

function mixHexColors(string $base, string $overlay, float $amount): string
{
    $amount = max(0.0, min(1.0, $amount));
    $base = ltrim(normalizeHexColor($base), '#');
    $overlay = ltrim(normalizeHexColor($overlay), '#');

    $baseR = hexdec(substr($base, 0, 2));
    $baseG = hexdec(substr($base, 2, 2));
    $baseB = hexdec(substr($base, 4, 2));

    $overR = hexdec(substr($overlay, 0, 2));
    $overG = hexdec(substr($overlay, 2, 2));
    $overB = hexdec(substr($overlay, 4, 2));

    $mix = function (int $a, int $b) use ($amount): string {
        $value = (int) round(($a * (1 - $amount)) + ($b * $amount));
        $value = max(0, min(255, $value));
        return str_pad(dechex($value), 2, '0', STR_PAD_LEFT);
    };

    return '#' . $mix($baseR, $overR) . $mix($baseG, $overG) . $mix($baseB, $overB);
}

function generatePaletteFromColor(string $baseColor): array
{
    $base = normalizeHexColor($baseColor, '#4c51bf');
    return [
        '50' => mixHexColors($base, '#ffffff', 0.88),
        '100' => mixHexColors($base, '#ffffff', 0.72),
        '200' => mixHexColors($base, '#ffffff', 0.55),
        '500' => $base,
        '600' => mixHexColors($base, '#000000', 0.18),
        '700' => mixHexColors($base, '#000000', 0.32),
    ];
}

function normalizePublicPath(?string $path): ?string
{
    if ($path === null) {
        return null;
    }
    $trimmed = trim($path);
    if ($trimmed === '') {
        return null;
    }
    $normalized = '/' . ltrim($trimmed, '/');
    return $normalized;
}

function getDefaultSettings(): array
{
    return [
        'company_name' => 'Lisansonay',
        'company_tagline' => 'Dijital lisans çözümlerinde profesyonel iş ortağınız.',
        'support_email' => 'destek@lisansonay.com',
        'support_phone' => '+90 555 555 55 55',
        'support_hours' => 'Hafta içi 09:00 - 18:00',
        'whatsapp_link' => 'https://wa.me/905555555555',
        'address' => '',
        'instagram_url' => '',
        'telegram_url' => '',
        'twitter_url' => '',
        'facebook_url' => '',
        'linkedin_url' => '',
        'meta_title' => 'Lisansonay · Ürün ve Lisans Çözümleri',
        'meta_description' => 'Lisansonay müşterileri için lisans, sosyal medya ve dijital ürün çözümleri.',
        'hero_title' => 'Profesyonel lisans ve dijital ürün yönetimi',
        'hero_subtitle' => 'Tüm lisans ihtiyaçlarınızı tek panelden yönetin, müşteri ve ekip süreçlerinizi hızlandırın.',
        'hero_cta_label' => 'Ürün kataloğunu keşfet',
        'hero_cta_link' => '#catalog',
        'primary_color' => '#4c51bf',
        'accent_color' => '#f97316',
        'logo_light' => '',
        'logo_dark' => '',
        'favicon' => '',
        'login_visual' => '',
    ];
}

function resolveSettings(array $settings): array
{
    $defaults = getDefaultSettings();
    foreach ($settings as $key => $value) {
        if (array_key_exists($key, $defaults)) {
            $defaults[$key] = is_string($value) ? trim($value) : $value;
        }
    }
    return $defaults;
}

function buildBrandingContext(array $settings): array
{
    $resolved = resolveSettings($settings);

    $primary = normalizeHexColor($resolved['primary_color'], '#4c51bf');
    $accent = normalizeHexColor($resolved['accent_color'], '#f97316');

    return array_merge($resolved, [
        'primary_palette' => generatePaletteFromColor($primary),
        'accent_palette' => generatePaletteFromColor($accent),
        'primary_color' => $primary,
        'accent_color' => $accent,
        'logo_light' => normalizePublicPath($resolved['logo_light']),
        'logo_dark' => normalizePublicPath($resolved['logo_dark']),
        'favicon' => normalizePublicPath($resolved['favicon']),
        'login_visual' => normalizePublicPath($resolved['login_visual']),
    ]);
}

function buildCategoryMenuEntries(array $catalog): array
{
    $menu = [];

    foreach ($catalog as $category) {
        if (!is_array($category)) {
            continue;
        }

        $subcategories = [];
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            if (!is_array($subcategory)) {
                continue;
            }

            $subcategories[] = [
                'id' => (int) ($subcategory['id'] ?? 0),
                'slug' => (string) ($subcategory['slug'] ?? ''),
                'title' => (string) ($subcategory['title'] ?? ''),
                'description' => (string) ($subcategory['description'] ?? ''),
                'icon' => $subcategory['icon'] ?? null,
            ];
        }

        $menu[] = [
            'id' => (int) ($category['id'] ?? 0),
            'slug' => (string) ($category['slug'] ?? ''),
            'title' => (string) ($category['title'] ?? ''),
            'description' => (string) ($category['description'] ?? ''),
            'emoji' => $category['emoji'] ?? '',
            'icon' => $category['icon'] ?? null,
            'subcategories' => $subcategories,
        ];
    }

    return $menu;
}

function getAccountMenuLinks(): array
{
    return [
        ['href' => '/account/index.php', 'label' => 'Hesap Panosu'],
        ['href' => '/account/cart.php', 'label' => 'Sepetim'],
        ['href' => '/account/orders.php', 'label' => 'Siparişlerim'],
        ['href' => '/account/wallet.php', 'label' => 'Bakiyem'],
        ['href' => '/account/support.php', 'label' => 'Destek Taleplerim'],
        ['href' => '/account/profile.php', 'label' => 'Profilim'],
    ];
}

function ensureWalletInfrastructure(PDO $pdo): bool
{
    static $attempted = false;
    static $supported = null;

    if ($attempted) {
        return (bool) $supported;
    }

    $attempted = true;

    try {
        if (!tableHasColumn($pdo, 'customer_users', 'balance_cents')) {
            $afterColumn = '';
            if (tableHasColumn($pdo, 'customer_users', 'phone')) {
                $afterColumn = ' AFTER phone';
            } elseif (tableHasColumn($pdo, 'customer_users', 'password_hash')) {
                $afterColumn = ' AFTER password_hash';
            }

            $pdo->exec('ALTER TABLE customer_users ADD COLUMN balance_cents INT NOT NULL DEFAULT 0' . $afterColumn);
            tableHasColumn($pdo, 'customer_users', 'balance_cents', true);
        }
    } catch (Throwable $e) {
        if (!tableHasColumn($pdo, 'customer_users', 'balance_cents', true)) {
            $supported = false;
            return false;
        }
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            type ENUM("deposit", "purchase", "refund", "adjustment", "withdrawal") NOT NULL DEFAULT "deposit",
            amount_cents INT NOT NULL DEFAULT 0,
            balance_after INT NOT NULL DEFAULT 0,
            reference_type VARCHAR(60) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_wallet_transactions_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        tableHasColumn($pdo, 'wallet_transactions', 'id', true);
    } catch (Throwable $e) {
        // ignore, feature still partially works without history
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS wallet_topups (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            reference_code VARCHAR(60) NOT NULL,
            amount_cents INT NOT NULL DEFAULT 0,
            status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "pending",
            payment_channel VARCHAR(60) DEFAULT NULL,
            proof_url VARCHAR(255) DEFAULT NULL,
            notes TEXT,
            admin_notes TEXT,
            processed_by INT UNSIGNED DEFAULT NULL,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_wallet_topups_customer FOREIGN KEY (customer_id) REFERENCES customer_users(id) ON DELETE CASCADE,
            CONSTRAINT fk_wallet_topups_admin FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_reference_code (reference_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        tableHasColumn($pdo, 'wallet_topups', 'id', true);
    } catch (Throwable $e) {
        // ignore optional table creation errors
    }

    $supported = tableHasColumn($pdo, 'customer_users', 'balance_cents');
    return (bool) $supported;
}

function ensureCategoryInfrastructure(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $ensured = true;

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id INT UNSIGNED NULL DEFAULT NULL,
                name VARCHAR(80) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                description TEXT NULL,
                emoji VARCHAR(16) NULL,
                icon_url VARCHAR(255) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_categories_parent (parent_id),
                UNIQUE KEY uniq_categories_parent_name (parent_id, name),
                CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        // ignore bootstrap errors – subsequent alterations handle legacy schemas
    }

    // Legacy column migrations (title -> name, icon -> icon_url, etc.)
    if (tableHasColumn($pdo, 'categories', 'title') && !tableHasColumn($pdo, 'categories', 'name')) {
        try {
            $pdo->exec('ALTER TABLE categories CHANGE COLUMN title name VARCHAR(80) NOT NULL');
            tableHasColumn($pdo, 'categories', 'name', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'name')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN name VARCHAR(80) NOT NULL AFTER parent_id');
            tableHasColumn($pdo, 'categories', 'name', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'slug')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN slug VARCHAR(120) NOT NULL UNIQUE AFTER name');
            tableHasColumn($pdo, 'categories', 'slug', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    } else {
        try {
            $pdo->exec('ALTER TABLE categories MODIFY COLUMN slug VARCHAR(120) NOT NULL');
        } catch (Throwable $ignored) {
            // ignore adjustments
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'description')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN description TEXT NULL AFTER slug');
            tableHasColumn($pdo, 'categories', 'description', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    } else {
        try {
            $pdo->exec('ALTER TABLE categories MODIFY COLUMN description TEXT NULL');
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'emoji')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN emoji VARCHAR(16) NULL AFTER description');
            tableHasColumn($pdo, 'categories', 'emoji', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    } else {
        try {
            $pdo->exec('ALTER TABLE categories MODIFY COLUMN emoji VARCHAR(16) NULL');
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (tableHasColumn($pdo, 'categories', 'icon') && !tableHasColumn($pdo, 'categories', 'icon_url')) {
        try {
            $pdo->exec('ALTER TABLE categories CHANGE COLUMN icon icon_url VARCHAR(255) NULL');
            tableHasColumn($pdo, 'categories', 'icon_url', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'icon_url')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN icon_url VARCHAR(255) NULL AFTER emoji');
            tableHasColumn($pdo, 'categories', 'icon_url', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'parent_id')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN parent_id INT UNSIGNED NULL DEFAULT NULL AFTER id');
            tableHasColumn($pdo, 'categories', 'parent_id', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    if (!tableHasColumn($pdo, 'categories', 'is_active')) {
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
            tableHasColumn($pdo, 'categories', 'is_active', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    try {
        $pdo->exec('ALTER TABLE categories ADD INDEX idx_categories_parent (parent_id)');
    } catch (Throwable $ignored) {
        // ignore duplicate index errors
    }

    try {
        $pdo->exec('ALTER TABLE categories ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL');
    } catch (Throwable $ignored) {
        // ignore if already exists or lacks permissions
    }

    try {
        $pdo->exec('ALTER TABLE categories ADD UNIQUE KEY uniq_categories_parent_name (parent_id, name)');
    } catch (Throwable $ignored) {
        // ignore duplicate index errors
    }

    if (tableHasColumn($pdo, 'category_subcategories', 'id')) {
        // Legacy tables may still exist; keep them for backward compatibility but ensure products reference the unified categories table.
        try {
            $pdo->exec('ALTER TABLE category_subcategories RENAME TO __legacy_category_subcategories');
        } catch (Throwable $ignored) {
            // ignore when rename is not possible
        }
    }

    if (!tableHasColumn($pdo, 'products', 'subcategory_id')) {
        try {
            $pdo->exec('ALTER TABLE products ADD COLUMN subcategory_id INT UNSIGNED DEFAULT NULL AFTER category_id');
            tableHasColumn($pdo, 'products', 'subcategory_id', true);
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    try {
        $pdo->exec('ALTER TABLE products ADD INDEX idx_products_subcategory (subcategory_id)');
    } catch (Throwable $ignored) {
        // ignore duplicate index errors
    }

    try {
        $pdo->exec('ALTER TABLE products DROP FOREIGN KEY fk_products_subcategory');
    } catch (Throwable $ignored) {
        // ignore if constraint does not exist
    }

    try {
        $pdo->exec('ALTER TABLE products ADD CONSTRAINT fk_products_subcategory_category FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE SET NULL');
    } catch (Throwable $ignored) {
        // ignore if cannot create
    }
}

function ensureOrderPaymentColumns(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $ensured = true;

    $definitions = [
        'wallet_deduction_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_cents',
        'card_charge_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER wallet_deduction_cents',
        'payment_channel' => "VARCHAR(60) DEFAULT NULL AFTER card_charge_cents",
        'payment_reference' => "VARCHAR(120) DEFAULT NULL AFTER payment_channel",
    ];

    foreach ($definitions as $column => $definition) {
        if (tableHasColumn($pdo, 'orders', $column)) {
            continue;
        }

        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
            tableHasColumn($pdo, 'orders', $column, true);
        } catch (Throwable $e) {
            // ignore lack of permissions or existing schema differences
        }
    }
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

function sanitizeSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9-]+/u', '-', $value) ?? '';
    $value = preg_replace('/-+/', '-', $value) ?? '';
    return trim($value, '-') ?: '';
}

function sanitizeNullableString(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function normalizeJsonList($value): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        return $trimmed;
    }

    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $entry) {
            if (is_string($entry) || is_numeric($entry)) {
                $normalized[] = trim((string) $entry);
            } elseif (is_array($entry)) {
                $normalized[] = $entry;
            }
        }
        if (empty($normalized)) {
            return null;
        }
        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return null;
}

function normalizeDateTimeInput(?string $value): ?string
{
    $trimmed = sanitizeNullableString($value);
    if ($trimmed === null) {
        return null;
    }
    $timestamp = strtotime($trimmed);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $timestamp);
}

function normalizePriceAmount(?string $value): ?float
{
    if ($value === null) {
        return null;
    }
    $normalized = str_replace(['₺', 'TRY', 'try', ' '], '', $value);
    $normalized = trim($normalized);
    $hasComma = strpos($normalized, ',') !== false;
    $hasDot = strpos($normalized, '.') !== false;
    if ($hasComma && $hasDot) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif ($hasComma) {
        $normalized = str_replace(',', '.', $normalized);
    }
    $normalized = preg_replace('/[^0-9.]/', '', $normalized) ?? '';
    if ($normalized === '') {
        return null;
    }
    return filter_var($normalized, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
}

function formatPriceLabel(?string $label, float $amount): string
{
    $label = trim((string)($label ?? ''));
    if ($label !== '') {
        return $label;
    }
    $precision = (abs($amount - round($amount)) < 0.01) ? 0 : 2;
    $formatted = number_format($amount, $precision, ',', '.');
    return '₺' . $formatted;
}

function parseAmountToCents(?string $value): int
{
    if ($value === null) {
        return 0;
    }

    $normalized = preg_replace('/[^0-9.,-]/u', '', (string) $value) ?? '';
    $normalized = trim($normalized);
    if ($normalized === '' || $normalized === '-' || $normalized === ',') {
        return 0;
    }

    $lastComma = strrpos($normalized, ',');
    $lastDot = strrpos($normalized, '.');

    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } elseif ($lastComma !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } else {
        $normalized = str_replace(',', '', $normalized);
    }

    $amount = filter_var($normalized, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    if ($amount === null) {
        $digits = preg_replace('/\D/', '', $normalized) ?? '';
        if ($digits === '') {
            return 0;
        }
        return (int) min(PHP_INT_MAX, (int) $digits * 100);
    }

    return (int) round($amount * 100);
}

function priceToCents(float $amount): int
{
    return (int) round($amount * 100);
}

function centsToPrice(int $cents): float
{
    return $cents / 100;
}

function formatCurrency(int $cents, string $currency = 'TRY'): string
{
    $amount = $cents / 100;
    $precision = (abs($amount - round($amount)) < 0.01) ? 0 : 2;
    $formatted = number_format($amount, $precision, ',', '.');
    $prefix = $currency === 'TRY' ? '₺' : ($currency . ' ');
    return $prefix . $formatted;
}

