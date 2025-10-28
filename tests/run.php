<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/customer.php';
require_once __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/public.php';
require_once __DIR__ . '/../src/rbac.php';

function expectEquals(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "[FAIL] $message\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function expectFloatEquals(float $expected, float $actual, string $message, float $epsilon = 0.0001): void
{
    if (abs($expected - $actual) > $epsilon) {
        fwrite(STDERR, "[FAIL] $message\nExpected: $expected\nActual:   $actual\n");
        exit(1);
    }
}

expectEquals('lisans-onay', sanitizeSlug(' Lisans Onay! '), 'sanitizeSlug trims and normalizes characters');
expectEquals('multi-section-slug', sanitizeSlug('Multi_section slug??'), 'sanitizeSlug converts separators to hyphen');

expectEquals(null, sanitizeNullableString(null), 'sanitizeNullableString keeps null');
expectEquals(null, sanitizeNullableString('   '), 'sanitizeNullableString treats empty strings as null');
expectEquals('abc', sanitizeNullableString(' abc '), 'sanitizeNullableString trims values');

expectFloatEquals(199.99, normalizePriceAmount('₺199,99'), 'normalizePriceAmount parses localized currency');
expectFloatEquals(59.9, normalizePriceAmount('TRY 59.90'), 'normalizePriceAmount parses decimal with dot');
expectEquals(null, normalizePriceAmount('invalid'), 'normalizePriceAmount returns null for invalid');

expectEquals('₺150', formatPriceLabel('', 150.0), 'formatPriceLabel generates default TRY label');
expectEquals('Özel Etiket', formatPriceLabel('Özel Etiket', 120.5), 'formatPriceLabel keeps custom label');

expectEquals(1250, priceToCents(12.5), 'priceToCents multiplies amount by 100');
expectFloatEquals(12.5, centsToPrice(1250), 'centsToPrice divides cents by 100');

expectEquals('₺1.250', formatCurrency(125000, 'TRY'), 'formatCurrency formats TRY values');
expectEquals('USD 59,90', formatCurrency(5990, 'USD'), 'formatCurrency prefixes non-TRY currencies');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $memoryPdo = new PDO('sqlite::memory:');
    $memoryPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $memoryPdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER DEFAULT NULL, name TEXT, slug TEXT UNIQUE)');
    $memoryPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER DEFAULT NULL, name TEXT, slug TEXT UNIQUE)');

    $initialCategorySlug = generateUniqueCategorySlug($memoryPdo, 'Kurumsal Hizmetler');
    expectEquals('kurumsal-hizmetler', $initialCategorySlug, 'generateUniqueCategorySlug normalizes Turkish names');
    $memoryPdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)')->execute([
        'name' => 'Kurumsal Hizmetler',
        'slug' => $initialCategorySlug,
    ]);
    $duplicateCategorySlug = generateUniqueCategorySlug($memoryPdo, 'Kurumsal Hizmetler');
    expectEquals('kurumsal-hizmetler-2', $duplicateCategorySlug, 'generateUniqueCategorySlug appends numeric suffix for conflicts');
    $existingCategoryId = (int) $memoryPdo->lastInsertId();
    $ignoredSlug = generateUniqueCategorySlug($memoryPdo, 'Kurumsal Hizmetler', $existingCategoryId, 'kurumsal-hizmetler');
    expectEquals('kurumsal-hizmetler', $ignoredSlug, 'generateUniqueCategorySlug respects ignore id parameter');

    $initialProductSlug = generateUniqueProductSlug($memoryPdo, 'Windows 11 Pro');
    expectEquals('windows-11-pro', $initialProductSlug, 'generateUniqueProductSlug normalizes product names');
    $memoryPdo->prepare('INSERT INTO products (name, slug) VALUES (:name, :slug)')->execute([
        'name' => 'Windows 11 Pro',
        'slug' => $initialProductSlug,
    ]);
    $duplicateProductSlug = generateUniqueProductSlug($memoryPdo, 'Windows 11 Pro');
    expectEquals('windows-11-pro-2', $duplicateProductSlug, 'generateUniqueProductSlug appends numeric suffix for conflicts');

    $blockPdo = new PDO('sqlite::memory:');
    $blockPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $blockPdo->exec('CREATE TABLE home_blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, category_id INTEGER, title_override TEXT, product_limit INTEGER, show_only_instock INTEGER, pinned_product_ids TEXT, sort_order INTEGER, is_active INTEGER)');
    $insertBlock = $blockPdo->prepare('INSERT INTO home_blocks (type, category_id, title_override, product_limit, show_only_instock, pinned_product_ids, sort_order, is_active) VALUES (:type, :category_id, :title_override, :product_limit, :show_only_instock, :pinned_product_ids, :sort_order, :is_active)');
    $insertBlock->execute([
        'type' => 'category',
        'category_id' => 2,
        'title_override' => 'Lisans Vitrini',
        'product_limit' => 3,
        'show_only_instock' => 1,
        'pinned_product_ids' => json_encode([11]),
        'sort_order' => 1,
        'is_active' => 1,
    ]);
    $insertBlock->execute([
        'type' => 'custom',
        'category_id' => null,
        'title_override' => 'Özel Kampanya',
        'product_limit' => 2,
        'show_only_instock' => 0,
        'pinned_product_ids' => json_encode([10]),
        'sort_order' => 2,
        'is_active' => 1,
    ]);

    $catalogSample = [
        [
            'id' => 1,
            'slug' => 'yazilim',
            'title' => 'Yazılım',
            'products' => [
                ['id' => 10],
                ['id' => 12],
            ],
            'subcategories' => [
                [
                    'id' => 2,
                    'slug' => 'lisanslar',
                    'title' => 'Lisanslar',
                    'products' => [
                        ['id' => 11],
                    ],
                ],
            ],
        ],
    ];

    $productMap = [
        10 => [
            'id' => 10,
            'name' => 'Office 365',
            'price_label' => '₺100',
            'price_cents' => 10000,
            'note' => null,
            'short_description' => 'Abonelik',
            'stock_status' => 'in_stock',
            'primary_image_url' => null,
            'badges' => ['Kampanya'],
            'category' => ['title' => 'Yazılım', 'emoji' => '💻'],
        ],
        11 => [
            'id' => 11,
            'name' => 'Windows 11 Pro',
            'price_label' => '₺250',
            'price_cents' => 25000,
            'note' => null,
            'short_description' => null,
            'stock_status' => 'in_stock',
            'primary_image_url' => null,
            'badges' => [],
            'category' => ['title' => 'Yazılım', 'emoji' => '💻'],
        ],
        12 => [
            'id' => 12,
            'name' => 'Adobe Creative Cloud',
            'price_label' => '₺400',
            'price_cents' => 40000,
            'note' => null,
            'short_description' => null,
            'stock_status' => 'out_of_stock',
            'primary_image_url' => null,
            'badges' => [],
            'category' => ['title' => 'Yazılım', 'emoji' => '💻'],
        ],
    ];

    $homeBlocks = fetchHomeBlocksForPublic($blockPdo, $catalogSample, $productMap);
    expectEquals(2, count($homeBlocks), 'fetchHomeBlocksForPublic returns active blocks');
    expectEquals('subcategory', $homeBlocks[0]['view_type'], 'fetchHomeBlocksForPublic detects subcategory targets');
    expectEquals('lisanslar', $homeBlocks[0]['view_slug'], 'fetchHomeBlocksForPublic keeps subcategory slug');
    expectEquals(1, count($homeBlocks[0]['products']), 'fetchHomeBlocksForPublic filters out of stock when requested');
    expectEquals(11, $homeBlocks[0]['products'][0]['id'], 'fetchHomeBlocksForPublic prioritises pinned product ordering');
    expectEquals('custom', $homeBlocks[1]['type'], 'fetchHomeBlocksForPublic keeps custom block type');
    expectEquals(10, $homeBlocks[1]['products'][0]['id'], 'fetchHomeBlocksForPublic returns custom pinned products');

    $heroPdo = new PDO('sqlite::memory:');
    $heroPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $heroPdo->exec('CREATE TABLE hero_banners (id INTEGER PRIMARY KEY AUTOINCREMENT, side TEXT, image_url TEXT, link_url TEXT, alt_text TEXT, is_active INTEGER, sort_order INTEGER, starts_at TEXT, ends_at TEXT)');
    $heroPdo->exec('CREATE TABLE hero_slides (id INTEGER PRIMARY KEY AUTOINCREMENT, image_url TEXT, link_url TEXT, alt_text TEXT, is_active INTEGER, sort_order INTEGER, starts_at TEXT, ends_at TEXT)');
    $heroPdo->exec('CREATE TABLE hero_strip_items (id INTEGER PRIMARY KEY AUTOINCREMENT, image_url TEXT, title TEXT, link_url TEXT, is_active INTEGER, sort_order INTEGER)');

    $insertBanner = $heroPdo->prepare('INSERT INTO hero_banners (side, image_url, link_url, alt_text, is_active, sort_order, starts_at, ends_at) VALUES (:side, :image_url, :link_url, :alt_text, :is_active, :sort_order, :starts_at, :ends_at)');
    $insertBanner->execute([
        'side' => 'left',
        'image_url' => '/media/test-left.jpg',
        'link_url' => '/kategori/yazilim',
        'alt_text' => 'Sol Banner',
        'is_active' => 1,
        'sort_order' => 10,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    $insertBanner->execute([
        'side' => 'left',
        'image_url' => '/media/ignored.jpg',
        'link_url' => '/kategori/ignored',
        'alt_text' => 'Pasif',
        'is_active' => 0,
        'sort_order' => 20,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    $insertBanner->execute([
        'side' => 'right',
        'image_url' => '/media/test-right.jpg',
        'link_url' => '/kategori/destek',
        'alt_text' => 'Sağ Banner',
        'is_active' => 1,
        'sort_order' => 10,
        'starts_at' => '2000-01-01 00:00:00',
        'ends_at' => '2099-12-31 23:59:59',
    ]);

    $insertSlide = $heroPdo->prepare('INSERT INTO hero_slides (image_url, link_url, alt_text, is_active, sort_order, starts_at, ends_at) VALUES (:image_url, :link_url, :alt_text, :is_active, :sort_order, :starts_at, :ends_at)');
    $insertSlide->execute([
        'image_url' => '/media/slide-1.jpg',
        'link_url' => '/kampanya/yeni',
        'alt_text' => 'Kampanya 1',
        'is_active' => 1,
        'sort_order' => 10,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    $insertSlide->execute([
        'image_url' => '/media/slide-2.jpg',
        'link_url' => '/kategori/yeni',
        'alt_text' => 'Kampanya 2',
        'is_active' => 1,
        'sort_order' => 20,
        'starts_at' => '2000-01-01 00:00:00',
        'ends_at' => '2099-12-31 23:59:59',
    ]);
    $insertSlide->execute([
        'image_url' => '/media/slide-expired.jpg',
        'link_url' => '/kategori/gecikmis',
        'alt_text' => 'Eski',
        'is_active' => 1,
        'sort_order' => 30,
        'starts_at' => '2000-01-01 00:00:00',
        'ends_at' => '2000-01-02 00:00:00',
    ]);

    $insertStrip = $heroPdo->prepare('INSERT INTO hero_strip_items (image_url, title, link_url, is_active, sort_order) VALUES (:image_url, :title, :link_url, :is_active, :sort_order)');
    $insertStrip->execute([
        'image_url' => '/media/strip-1.jpg',
        'title' => 'Mini Şerit 1',
        'link_url' => '/kategori/mini',
        'is_active' => 1,
        'sort_order' => 10,
    ]);
    $insertStrip->execute([
        'image_url' => '/media/strip-hidden.jpg',
        'title' => 'Mini Şerit 2',
        'link_url' => '/kategori/gizli',
        'is_active' => 0,
        'sort_order' => 20,
    ]);

    $heroSettings = [
        'hero_autoplay_enabled' => '1',
        'hero_autoplay_ms' => '3500',
        'hero_show_dots' => '1',
        'hero_show_arrows' => '1',
    ];

    $heroLayout = fetchHeroLayout($heroPdo, $heroSettings);
    expectEquals(1, count($heroLayout['left_banners']), 'fetchHeroLayout filters inactive left banners');
    expectEquals('/media/test-left.jpg', $heroLayout['left_banners'][0]['image_url'], 'fetchHeroLayout keeps active left banner');
    expectEquals(1, count($heroLayout['right_banners']), 'fetchHeroLayout respects time window for right banners');
    expectEquals(2, count($heroLayout['slides']), 'fetchHeroLayout returns only active slides');
    expectEquals(true, $heroLayout['settings']['autoplay'], 'fetchHeroLayout enables autoplay when slides > 1');
    expectEquals(1, count($heroLayout['strip']), 'fetchHeroLayout filters inactive strip items');

    purgeHeroCache();
    $heroCachePayload = ['leftBanner' => ['image' => '/media/test.jpg']];
    saveHeroCache($heroCachePayload);
    $loadedHeroCache = loadHeroCache(60);
    expectEquals($heroCachePayload, $loadedHeroCache, 'Hero cache round-trip works');
    purgeHeroCache();
    expectEquals(null, loadHeroCache(60), 'Hero cache purge removes stored payload');
} else {
    fwrite(STDOUT, "SQLite driver unavailable, skipping slug and home block assertions.\n");
}

$sampleUser = [
    'roles' => ['content-manager'],
    'permissions' => ['catalog.view', 'catalog.update'],
];
expectEquals(true, userHasPermission($sampleUser, 'catalog.view'), 'userHasPermission grants allowed scopes');
expectEquals(false, userHasPermission($sampleUser, 'orders.manage'), 'userHasPermission denies missing scopes');
$superAdmin = [
    'roles' => ['super-admin'],
    'permissions' => [],
];
expectEquals(true, userHasPermission($superAdmin, 'any.permission'), 'userHasPermission allows super-admin override');

$menuEntries = buildCategoryMenuEntries([
    [
        'id' => 1,
        'slug' => 'yazilim',
        'title' => 'Yazılım',
        'description' => 'Profesyonel çözümler',
        'emoji' => '💻',
        'icon' => '🧩',
        'subcategories' => [
            [
                'id' => 11,
                'slug' => 'antivirus',
                'title' => 'Antivirüs',
                'description' => 'Koruma paketleri',
                'icon' => '/icons/antivirus.svg',
            ],
        ],
    ],
]);
expectEquals(1, count($menuEntries), 'buildCategoryMenuEntries returns top-level items');
expectEquals('yazilim', $menuEntries[0]['slug'], 'buildCategoryMenuEntries preserves category slug');
expectEquals('/icons/antivirus.svg', $menuEntries[0]['subcategories'][0]['icon'], 'buildCategoryMenuEntries keeps subcategory icon');

expectEquals(123456, parseAmountToCents('₺1.234,56'), 'parseAmountToCents handles localized input with thousands separator');
expectEquals(2599, parseAmountToCents('25,99'), 'parseAmountToCents parses comma decimals');
expectEquals(0, parseAmountToCents('invalid'), 'parseAmountToCents returns zero for invalid inputs');

$walletOnly = resolveCartPaymentBreakdown(10000, 12000, 'wallet', 0);
expectEquals(['mode' => 'wallet', 'wallet_cents' => 10000, 'card_cents' => 0], $walletOnly, 'resolveCartPaymentBreakdown uses wallet when balance covers total');

$mixed = resolveCartPaymentBreakdown(15000, 6000, 'mixed', 4000);
expectEquals(['mode' => 'mixed', 'wallet_cents' => 4000, 'card_cents' => 11000], $mixed, 'resolveCartPaymentBreakdown splits wallet and card amounts');

$cardOnly = resolveCartPaymentBreakdown(8000, 3000, 'card', 0);
expectEquals(['mode' => 'card', 'wallet_cents' => 0, 'card_cents' => 8000], $cardOnly, 'resolveCartPaymentBreakdown uses card mode');

ensureSession();
clearSessionCartItems();
saveSessionCartItems([
    '1' => 2,
    'abc' => -5,
    '3' => 1205,
]);

$cartItems = getSessionCartItems();
expectEquals([
    1 => 2,
    3 => 999,
], $cartItems, 'getSessionCartItems normalizes and clamps stored quantities');

expectEquals(1001, getSessionCartCount(), 'getSessionCartCount sums normalized quantities');

clearSessionCartItems();
expectEquals([], getSessionCartItems(), 'clearSessionCartItems removes cart cache');
expectEquals(0, getSessionCartCount(), 'getSessionCartCount returns zero when cart empty');

fwrite(STDOUT, "All helper assertions passed.\n");
