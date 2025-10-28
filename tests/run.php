<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/customer.php';

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
