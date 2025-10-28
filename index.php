<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/admin.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';

ensureSession();

$customer = getCurrentCustomer();
$customerInitial = '';
if ($customer && isset($customer['name'])) {
    $firstChar = mb_substr(trim((string) $customer['name']), 0, 1, 'UTF-8');
    $customerInitial = $firstChar !== '' ? mb_strtoupper($firstChar, 'UTF-8') : 'M';
}

$catalog = [];
$productOptions = [];
$productMap = [];
$settings = [];
$catalogError = '';
$categoryMenu = [];
$blogHighlights = [];
$heroLayout = [
    'left_banner' => null,
    'right_banner' => null,
    'slides' => [],
    'strip' => [],
    'settings' => [
        'autoplay' => true,
        'autoplay_ms' => 4000,
        'show_dots' => true,
        'show_arrows' => true,
    ],
];
$homeBlocks = [];
$categorySlugIndex = [];
$subcategorySlugIndex = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchPublicSettings($pdo);
        $catalog = fetchPublicCatalog($pdo);
        $productOptions = flattenCatalogProducts($catalog);
        foreach ($productOptions as $product) {
            $productMap[(int) $product['id']] = $product;
        }
        $heroLayout = fetchHeroLayout($pdo, $settings);
        $homeBlocks = fetchHomeBlocksForPublic($pdo, $catalog, $productMap);
        $categoryMenu = buildCategoryMenuEntries($catalog);

        foreach ($catalog as $categoryItem) {
            $categoryTitle = (string) ($categoryItem['title'] ?? 'Kategori');
            $categorySlug = (string) ($categoryItem['slug'] ?? '');
            $iconValue = $categoryItem['icon'] ?? null;
            $emojiValue = $categoryItem['emoji'] ?? '';

            if ($categorySlug !== '') {
                $categorySlugIndex[$categorySlug] = true;
            }

            $baseProducts = count($categoryItem['products'] ?? []);
            $subcategories = $categoryItem['subcategories'] ?? [];
            $subProductCount = 0;
            foreach ($subcategories as $subEntry) {
                $subProductCount += count($subEntry['products'] ?? []);
                $subSlug = (string) ($subEntry['slug'] ?? '');
                if ($subSlug !== '') {
                    $subcategorySlugIndex[$subSlug] = $categorySlug;
                }
            }

            $totalProducts = $baseProducts + $subProductCount;
            $subCount = count($subcategories);

            $excerptParts = [];
            if ($totalProducts > 0) {
                $excerptParts[] = sprintf('%d ürün ve hizmetle portföyümüzü güncelledik.', $totalProducts);
            } else {
                $excerptParts[] = 'Yeni ürün ve kampanyalar üzerinde çalışıyoruz.';
            }
            if ($subCount > 0) {
                $excerptParts[] = sprintf('%d alt kategoriyle detaylı bir yapı sunuyoruz.', $subCount);
            }

            $blogHighlights[] = [
                'title' => $categoryTitle . ' İçgörüleri',
                'excerpt' => implode(' ', $excerptParts),
                'icon' => $iconValue,
                'emoji' => $emojiValue,
                'slug' => $categorySlug,
            ];
        }
    } catch (Throwable $e) {
        $catalogError = 'Katalog verileri yüklenemedi: ' . $e->getMessage();
    }
}

if (empty($blogHighlights)) {
    $blogHighlights = [
        [
            'title' => 'Lisansonay Haberleri',
            'excerpt' => 'Yeni kategori ve ürün duyurularımız için bizi takip edin. Yakında portföyümüzde genişletilmiş seçenekler sunacağız.',
            'icon' => null,
            'emoji' => '📰',
            'slug' => '',
        ],
    ];
}

$blogHighlights = array_slice($blogHighlights, 0, 3);

$leftBanner = $heroLayout['left_banner'] ?? null;
$rightBanner = $heroLayout['right_banner'] ?? null;
$mainSlides = $heroLayout['slides'] ?? [];
$stripItems = $heroLayout['strip'] ?? [];
$heroSettings = $heroLayout['settings'] ?? [];
$hasLeftBanner = is_array($leftBanner) && !empty($leftBanner['image_url']);
$hasRightBanner = is_array($rightBanner) && !empty($rightBanner['image_url']);
$heroHasVisual = $hasLeftBanner || $hasRightBanner || !empty($mainSlides);
$stripHasItems = !empty($stripItems);

$branding = buildBrandingContext($settings);
$companyName = $branding['company_name'];
$companyTagline = $branding['company_tagline'];
$supportEmail = $branding['support_email'];
$supportPhone = $branding['support_phone'];
$supportHours = $branding['support_hours'];
$companyAddress = $branding['address'];
$whatsappLink = $branding['whatsapp_link'];
$metaTitle = $branding['meta_title'];
$metaDescription = $branding['meta_description'];
$heroTitle = $branding['hero_title'];
$heroSubtitle = $branding['hero_subtitle'];
$heroCtaLabel = $branding['hero_cta_label'];
$heroCtaLink = $branding['hero_cta_link'];
$logoLight = $branding['logo_light'];
$logoDark = $branding['logo_dark'] ?? null;
$faviconPath = $branding['favicon'];
$primaryPaletteJson = json_encode($branding['primary_palette'], JSON_UNESCAPED_SLASHES);
$accentPaletteJson = json_encode($branding['accent_palette'], JSON_UNESCAPED_SLASHES);
if (!is_string($primaryPaletteJson) || $primaryPaletteJson === 'null') {
    $primaryPaletteJson = '{"50":"#eef2ff","100":"#e0e7ff","200":"#c7d2fe","500":"#4c51bf","600":"#4338ca","700":"#3730a3"}';
}
if (!is_string($accentPaletteJson) || $accentPaletteJson === 'null') {
    $accentPaletteJson = '{"50":"#fff7ed","100":"#ffedd5","200":"#fed7aa","500":"#f97316","600":"#ea580c","700":"#c2410c"}';
}
$companyInitial = mb_strtoupper(mb_substr($companyName, 0, 1, 'UTF-8'), 'UTF-8') ?: 'L';
$heroHasCta = ($heroCtaLabel !== '') && ($heroCtaLink !== '');
$primaryCtaLink = $heroHasCta ? $heroCtaLink : '#catalog';
$primaryCtaLabel = $heroHasCta ? $heroCtaLabel : 'Kataloğu İncele';

$categoryCount = count($catalog);
$productCount = count($productOptions);
$startingPrice = null;
$startingProductLabel = null;
foreach ($productOptions as $product) {
    $price = (int) $product['price_cents'];
    if ($startingPrice === null || $price < $startingPrice) {
        $startingPrice = $price;
        $startingProductLabel = $product['price_label'];
    }
}

$orderErrors = [];
$orderTokenFormKey = 'public-order';
$orderFormData = [
    'customer_name' => '',
    'customer_email' => '',
    'customer_phone' => '',
    'product_id' => $productOptions[0]['id'] ?? '',
    'quantity' => '1',
    'notes' => '',
];

if ($customer) {
    $orderFormData['customer_name'] = $customer['name'] ?? '';
    $orderFormData['customer_email'] = $customer['email'] ?? '';
    $orderFormData['customer_phone'] = $customer['phone'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place-order') {
    $orderFormData = [
        'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
        'customer_email' => trim((string)($_POST['customer_email'] ?? '')),
        'customer_phone' => trim((string)($_POST['customer_phone'] ?? '')),
        'product_id' => (string)($_POST['product_id'] ?? ''),
        'quantity' => (string)($_POST['quantity'] ?? '1'),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];

    if ($customer) {
        if ($orderFormData['customer_name'] === '') {
            $orderFormData['customer_name'] = $customer['name'] ?? '';
        }
        if ($orderFormData['customer_email'] === '') {
            $orderFormData['customer_email'] = $customer['email'] ?? '';
        }
        if ($orderFormData['customer_phone'] === '') {
            $orderFormData['customer_phone'] = $customer['phone'] ?? '';
        }
    }

    if (!validateCsrfToken($orderTokenFormKey, $_POST['_token'] ?? null)) {
        $orderErrors[] = 'Talebiniz doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $orderErrors[] = 'Siparişinizi kaydetmek için veritabanı bağlantısı kurulamadı.';
    } else {
        $name = $orderFormData['customer_name'];
        $email = $orderFormData['customer_email'];
        $phone = $orderFormData['customer_phone'];
        $productId = (int) $orderFormData['product_id'];
        $quantity = (int) $orderFormData['quantity'];
        $notes = $orderFormData['notes'];

        if ($name === '') {
            $orderErrors[] = 'Ad Soyad alanı zorunludur.';
        }
        if ($productId <= 0 || !isset($productMap[$productId])) {
            $orderErrors[] = 'Lütfen listeden geçerli bir ürün seçin.';
        }
        if ($quantity < 1 || $quantity > 1000) {
            $orderErrors[] = 'Adet 1 ile 1000 arasında olmalıdır.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $orderErrors[] = 'Lütfen geçerli bir e-posta adresi girin.';
        }
        if ($phone !== '' && mb_strlen($phone) < 6) {
            $orderErrors[] = 'Lütfen geçerli bir telefon numarası girin.';
        }

        if (empty($orderErrors)) {
            $product = $productMap[$productId];
            $totalCents = $product['price_cents'] * $quantity;
            $orderNumber = generateOrderNumber($pdo);
            $noteParts = [
                'İlgilenilen ürün: ' . $product['name'],
                'Kategori: ' . $product['category']['title'],
                'Adet: ' . $quantity,
            ];
            if ($notes !== '') {
                $noteParts[] = 'Müşteri Notu: ' . $notes;
            }
            $orderNotes = implode(' | ', $noteParts);

            try {
                $orderId = createOrder($pdo, [
                    'customer_id' => isset($customer['id']) ? (int) $customer['id'] : null,
                    'order_no' => $orderNumber,
                    'customer_name' => $name,
                    'customer_email' => $email !== '' ? $email : null,
                    'customer_phone' => $phone !== '' ? $phone : null,
                    'status' => 'pending',
                    'total_cents' => $totalCents,
                    'currency' => 'TRY',
                    'notes' => $orderNotes,
                ]);

                logActivity($pdo, null, 'order.request', 'orders', $orderId, 'Web formu üzerinden yeni müşteri talebi oluşturuldu');
                addFlash('success', 'Sipariş talebiniz alınmıştır. En kısa sürede sizinle iletişime geçeceğiz.');
                redirect('/index.php#order-form');
            } catch (Throwable $e) {
                $orderErrors[] = 'Talebiniz kaydedilirken bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

$orderToken = issueCsrfToken($orderTokenFormKey);
$cartToken = issueCsrfToken('cart-action');
$currentRequestUri = $_SERVER['REQUEST_URI'] ?? '/index.php';
if ($currentRequestUri === '' || strpos($currentRequestUri, '/') !== 0) {
    $currentRequestUri = '/index.php';
}
$cartCount = 0;
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $cartCount = getActiveCartCount($pdo, $customer);
    } else {
        $cartCount = getSessionCartCount();
    }
} catch (Throwable $cartException) {
    $cartCount = getSessionCartCount();
}
$cartLink = $customer
    ? '/account/cart.php'
    : '/login.php?redirect=' . urlencode('/account/cart.php');
$flashes = getFlashes();
$flashPayload = json_encode(array_values($flashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($flashPayload)) {
    $flashPayload = '[]';
}
$accountMenuLinks = getAccountMenuLinks();
$globalNavLinks = [
    ['href' => '/index.php', 'label' => 'Ana sayfa'],
    ['href' => '/index.php#blog', 'label' => 'Blog'],
    ['href' => '/index.php#contact', 'label' => 'İletişim'],
];

?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($metaTitle !== '' ? $metaTitle : ($companyName . ' · Ürün ve Lisans Çözümleri')) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription !== '' ? $metaDescription : ($companyName . ' müşterileri için lisans, sosyal medya ve dijital ürün çözümleri.')) ?>" />
    <?php if ($faviconPath): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconPath) ?>" type="image/png" />
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: <?= $primaryPaletteJson ?>,
                        accent: <?= $accentPaletteJson ?>,
                    },
                },
            },
        };
    </script>
    <style>
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 2.4fr) minmax(0, 1fr);
            gap: 24px;
            align-items: stretch;
        }

        .hero-grid.has-left:not(.has-right) {
            grid-template-columns: minmax(0, 1fr) minmax(0, 2.4fr);
        }

        .hero-grid.has-right:not(.has-left) {
            grid-template-columns: minmax(0, 2.4fr) minmax(0, 1fr);
        }

        .hero-grid:not(.has-left):not(.has-right) {
            grid-template-columns: minmax(0, 1fr);
        }

        .hero-banner-slot {
            display: flex;
        }

        .hero-banner {
            display: block;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            position: relative;
            min-height: min(52vh, 520px);
            background: rgba(15, 23, 42, 0.55);
        }

        .hero-banner img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-carousel-wrapper {
            position: relative;
        }

        .hero-carousel {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            background: rgba(15, 23, 42, 0.85);
            height: min(52vh, 520px);
            min-height: 320px;
            touch-action: pan-y;
        }

        .hero-track {
            display: flex;
            height: 100%;
            transition: transform 550ms cubic-bezier(.22, .61, .36, 1);
            will-change: transform;
        }

        .hero-slide {
            position: relative;
            min-width: 100%;
            height: 100%;
            display: block;
            color: inherit;
        }

        .hero-slide img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-slide .hero-slide-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.18) 0%, rgba(15, 23, 42, 0.6) 100%);
            pointer-events: none;
        }

        .hero-slide .hero-slide-caption {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            padding: 24px 28px;
            color: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .hero-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 9999px;
            border: 0;
            background: rgba(15, 23, 42, 0.55);
            color: #f8fafc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            transition: background 200ms ease, opacity 200ms ease;
        }

        .hero-nav:hover,
        .hero-nav:focus-visible {
            background: rgba(15, 23, 42, 0.8);
        }

        .hero-nav:focus-visible {
            outline: 2px solid rgba(99, 102, 241, 0.85);
            outline-offset: 2px;
        }

        .hero-nav[disabled] {
            opacity: 0.4;
            cursor: default;
        }

        .hero-nav-prev {
            left: 16px;
        }

        .hero-nav-next {
            right: 16px;
        }

        .hero-dots {
            position: absolute;
            left: 50%;
            bottom: 16px;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }

        .hero-dots button {
            width: 10px;
            height: 10px;
            border-radius: 9999px;
            border: 0;
            background: rgba(248, 250, 252, 0.45);
            cursor: pointer;
            transition: width 200ms ease, background 200ms ease;
        }

        .hero-dots button[aria-selected="true"] {
            width: 24px;
            background: rgba(99, 102, 241, 0.95);
        }

        .hero-dots button:focus-visible {
            outline: 2px solid rgba(99, 102, 241, 0.85);
            outline-offset: 2px;
        }

        .hero-strip {
            margin-top: 24px;
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: clamp(200px, 24vw, 240px);
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 6px;
            scroll-snap-type: x mandatory;
        }

        .hero-strip::-webkit-scrollbar {
            height: 6px;
        }

        .hero-strip::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 9999px;
        }

        .hero-strip-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            color: #0f172a;
            scroll-snap-align: start;
            transition: transform 200ms ease, background 200ms ease;
            text-decoration: none;
        }

        .hero-strip-item:hover,
        .hero-strip-item:focus-visible {
            transform: translateY(-2px);
            background: rgba(99, 102, 241, 0.2);
            outline: 2px solid rgba(99, 102, 241, 0.45);
            outline-offset: 2px;
        }

        .hero-strip-item img {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            object-fit: cover;
            display: block;
        }

        .dark .hero-strip-item {
            background: rgba(148, 163, 184, 0.12);
            color: #f8fafc;
        }

        .dark .hero-strip-item:hover,
        .dark .hero-strip-item:focus-visible {
            background: rgba(99, 102, 241, 0.35);
        }

        @media (max-width: 1439px) {
            .hero-carousel {
                height: min(52vh, 500px);
            }

            .hero-banner {
                min-height: min(52vh, 500px);
            }
        }

        @media (max-width: 1279px) {
            .hero-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-auto-rows: auto;
            }

            .hero-grid.has-left:not(.has-right),
            .hero-grid.has-right:not(.has-left),
            .hero-grid:not(.has-left):not(.has-right) {
                grid-template-columns: minmax(0, 1fr);
            }

            .hero-carousel-wrapper {
                grid-column: 1 / -1;
            }

            .hero-banner {
                min-height: min(48vh, 420px);
            }

            .hero-carousel {
                height: min(48vh, 420px);
            }
        }

        @media (max-width: 1023px) {
            .hero-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .hero-carousel-wrapper,
            .hero-banner-slot-left,
            .hero-banner-slot-right {
                grid-column: 1 / -1;
            }

            .hero-banner {
                min-height: min(44vh, 360px);
            }

            .hero-carousel {
                height: min(44vh, 360px);
            }
        }

        @media (max-width: 639px) {
            .hero-strip {
                grid-auto-columns: clamp(180px, 70vw, 220px);
            }

            .hero-nav {
                width: 38px;
                height: 38px;
            }
        }
    </style>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('lisansonay-theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = (stored === 'dark' || stored === 'light') ? stored : (prefersDark ? 'dark' : 'light');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (err) {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php if (!empty($mainSlides)): ?>
        <link rel="preload" as="image" href="<?= htmlspecialchars($mainSlides[0]['image_url'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <style>
        :root {
            color-scheme: light dark;
            --brand-primary: <?= htmlspecialchars($branding['primary_color']) ?>;
            --brand-accent: <?= htmlspecialchars($branding['accent_color']) ?>;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        details summary {
            list-style: none;
        }
        details summary::-webkit-details-marker {
            display: none;
        }
        .toast-root {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }
        .toast-item {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.5);
            background: rgba(255, 255, 255, 0.95);
            color: #0f172a;
            padding: 0.75rem 1rem;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.15);
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .dark .toast-item {
            border-color: rgba(71, 85, 105, 0.6);
            background: rgba(15, 23, 42, 0.9);
            color: #e2e8f0;
        }
        .toast-item.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-success {
            border-color: rgba(16, 185, 129, 0.45);
            background: rgba(220, 252, 231, 0.95);
            color: #047857;
        }
        .dark .toast-success {
            border-color: rgba(16, 185, 129, 0.55);
            background: rgba(6, 95, 70, 0.7);
            color: #6ee7b7;
        }
        .toast-error {
            border-color: rgba(239, 68, 68, 0.45);
            background: rgba(254, 226, 226, 0.95);
            color: #b91c1c;
        }
        .dark .toast-error {
            border-color: rgba(239, 68, 68, 0.55);
            background: rgba(127, 29, 29, 0.75);
            color: #fecaca;
        }
        .toast-message {
            flex: 1;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .toast-close {
            border: none;
            background: transparent;
            color: inherit;
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            opacity: 0.7;
        }
        .toast-close:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<script id="flash-data" type="application/json"><?= $flashPayload ?></script>
<header class="sticky top-0 z-30 border-b border-slate-200/70 dark:border-slate-800/70 backdrop-blur bg-white/80 dark:bg-slate-900/80">
    <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="/index.php" class="flex items-center gap-3">
                <?php if ($logoLight || $logoDark): ?>
                    <?php $initialLogo = $logoLight ?? $logoDark; ?>
                    <span class="inline-flex">
                        <img src="<?= htmlspecialchars($initialLogo) ?>"
                             data-brand-logo="true"
                             data-light-logo="<?= htmlspecialchars($logoLight ?? $initialLogo) ?>"
                             data-dark-logo="<?= htmlspecialchars($logoDark ?? $initialLogo) ?>"
                             alt="<?= htmlspecialchars($companyName) ?> logosu"
                             class="h-10 w-auto object-contain transition-opacity duration-200" />
                    </span>
                <?php else: ?>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-500 text-white text-xl font-semibold shadow"><?= htmlspecialchars($companyInitial) ?></span>
                <?php endif; ?>
                <div>
                    <p class="text-sm uppercase tracking-[0.35em] text-brand-600 dark:text-brand-200"><?= htmlspecialchars($companyName) ?></p>
                    <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">Müşteri Portalı</h1>
                    <?php if ($companyTagline !== ''): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-300 mt-1 max-w-xs leading-snug"><?= htmlspecialchars($companyTagline) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <div class="flex items-center gap-3">
            <nav class="hidden sm:flex items-center gap-4 text-sm font-medium text-slate-600 dark:text-slate-300">
                <?php foreach ($globalNavLinks as $link): ?>
                    <a href="<?= htmlspecialchars($link['href']) ?>" class="hover:text-brand-600 dark:hover:text-brand-300 transition">
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <a href="<?= htmlspecialchars($cartLink) ?>" class="relative inline-flex items-center justify-center h-11 w-11 rounded-2xl border border-brand-200 dark:border-brand-700 bg-white/80 dark:bg-slate-900/70 text-lg shadow">
                <span class="sr-only">Sepetim</span>
                <span aria-hidden="true">🛒</span>
                <?php if ($cartCount > 0): ?>
                    <span class="absolute -top-1 -right-1 min-w-[1.5rem] h-6 px-1.5 rounded-full bg-brand-500 text-white text-xs font-bold flex items-center justify-center"><?= (int) $cartCount ?></span>
                <?php endif; ?>
            </a>
            <button id="theme-toggle" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm bg-white/80 dark:bg-slate-900/70 shadow">
                <span id="themeIcon" class="inline-flex h-5 w-5 items-center justify-center"></span>
                <span id="themeText" class="font-medium">Tema</span>
            </button>
            <?php if ($customer): ?>
                <details class="relative">
                    <summary class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white/80 dark:bg-slate-900/70 text-sm font-semibold cursor-pointer">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-white font-semibold"><?= htmlspecialchars($customerInitial ?: 'M') ?></span>
                        <span class="hidden sm:block max-w-[140px] truncate text-slate-700 dark:text-slate-200"><?= htmlspecialchars($customer['name'] ?? '') ?></span>
                    </summary>
                    <div class="absolute right-0 mt-3 w-56 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg py-2 text-sm">
                        <?php foreach ($accountMenuLinks as $link): ?>
                            <a href="<?= htmlspecialchars($link['href']) ?>" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <?= htmlspecialchars($link['label']) ?>
                            </a>
                        <?php endforeach; ?>
                        <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
                        <a href="/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/40">Çıkış Yap</a>
                    </div>
                </details>
            <?php else: ?>
                <a href="/login.php" class="inline-flex items-center gap-2 rounded-2xl border border-brand-200 text-brand-600 dark:border-brand-700 dark:text-brand-200 px-3 py-2 text-sm font-semibold bg-white/80 dark:bg-slate-900/70 shadow">Giriş Yap</a>
                <a href="/register.php" class="hidden sm:inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-3 py-2 shadow">Kayıt Ol</a>
            <?php endif; ?>
            <a href="/admin/index.php" class="hidden md:inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-600 dark:text-slate-300 bg-white/80 dark:bg-slate-900/70 shadow">Yönetim</a>
        </div>
    </div>
    <?php if (!empty($categoryMenu)): ?>
        <div class="border-t border-slate-200/70 dark:border-slate-800/70">
            <div class="mx-auto max-w-6xl px-4 overflow-x-auto">
                <nav class="flex items-center gap-3 py-3 min-h-[3.25rem]" aria-label="Kategori menüsü">
                    <?php foreach ($categoryMenu as $menuCategory): ?>
                        <?php
                            $iconValue = trim((string)($menuCategory['icon'] ?? ''));
                            $emojiValue = trim((string)($menuCategory['emoji'] ?? ''));
                            $displayEmoji = $emojiValue !== '' ? $emojiValue : '📂';
                            $isImageIcon = false;
                            if ($iconValue !== '') {
                                $isImageIcon = preg_match('#^(https?:)?//#', $iconValue) === 1 || strncmp($iconValue, '/', 1) === 0;
                            }
                        ?>
                        <details class="group relative header-category-item">
                            <summary class="header-category-trigger inline-flex items-center gap-2 rounded-2xl border border-slate-200/80 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 cursor-pointer transition hover:border-brand-200 hover:text-brand-600 dark:hover:border-brand-500 dark:hover:text-brand-200" data-category="<?= htmlspecialchars($menuCategory['slug']) ?>">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/20 text-base">
                                    <?php if ($iconValue !== ''): ?>
                                        <?php if ($isImageIcon): ?>
                                            <img src="<?= htmlspecialchars($iconValue) ?>" alt="<?= htmlspecialchars($menuCategory['title']) ?> ikon" class="h-full w-full object-contain" />
                                        <?php else: ?>
                                            <?= htmlspecialchars($iconValue) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($displayEmoji) ?>
                                    <?php endif; ?>
                                </span>
                                <span><?= htmlspecialchars($menuCategory['title']) ?></span>
                                <?php if (!empty($menuCategory['subcategories'])): ?>
                                    <span class="text-xs text-slate-400" aria-hidden="true">▾</span>
                                <?php endif; ?>
                            </summary>
                            <?php if (!empty($menuCategory['subcategories'])): ?>
                                <div class="hidden group-open:flex flex-col gap-2 absolute left-0 top-full mt-2 min-w-[220px] rounded-2xl border border-slate-200/80 dark:border-slate-800/70 bg-white dark:bg-slate-900 shadow-lg p-3 z-20">
                                    <?php foreach ($menuCategory['subcategories'] as $sub): ?>
                                        <?php
                                            $subIcon = trim((string)($sub['icon'] ?? ''));
                                            $isSubImage = $subIcon !== '' && (preg_match('#^(https?:)?//#', $subIcon) === 1 || strncmp($subIcon, '/', 1) === 0);
                                        ?>
                                        <button type="button" class="header-subcategory-btn inline-flex items-center justify-between gap-2 rounded-xl border border-slate-200/80 dark:border-slate-800/70 bg-white/90 dark:bg-slate-900/70 px-3 py-2 text-sm text-slate-600 dark:text-slate-300 hover:border-brand-200 hover:text-brand-600 dark:hover:border-brand-500 dark:hover:text-brand-200" data-category="<?= htmlspecialchars($menuCategory['slug']) ?>" data-subcategory="<?= htmlspecialchars($sub['slug']) ?>">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/20 text-sm">
                                                    <?php if ($subIcon !== ''): ?>
                                                        <?php if ($isSubImage): ?>
                                                            <img src="<?= htmlspecialchars($subIcon) ?>" alt="<?= htmlspecialchars($sub['title']) ?> ikon" class="h-full w-full object-contain" />
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($subIcon) ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        •
                                                    <?php endif; ?>
                                                </span>
                                                <span><?= htmlspecialchars($sub['title']) ?></span>
                                            </span>
                                            <span class="text-[11px] text-slate-400">Seç</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    <?php endif; ?>
</header>
<main>
    <section class="bg-gradient-to-b from-brand-50/60 via-white dark:from-slate-900 dark:via-slate-950">
        <div class="mx-auto max-w-6xl px-4 pt-12 pb-16 lg:pt-16">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div>
                    <?php if ($companyTagline !== ''): ?>
                        <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 dark:bg-brand-500/20 text-brand-700 dark:text-brand-200 px-3 py-1 text-sm font-medium">
                            <?= htmlspecialchars($companyTagline) ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 dark:bg-brand-500/20 text-brand-700 dark:text-brand-200 px-3 py-1 text-sm font-medium">
                            <?= htmlspecialchars($companyName) ?> · Dijital çözümler
                        </span>
                    <?php endif; ?>
                    <h2 class="mt-5 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight">
                        <?= htmlspecialchars($heroTitle !== '' ? $heroTitle : ($companyName . ' müşterileri için merkezi katalog ve sipariş yönetimi')) ?>
                    </h2>
                    <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed">
                        <?= nl2br(htmlspecialchars($heroSubtitle !== '' ? $heroSubtitle : 'Tüm lisans, sosyal medya ve dijital ürün ihtiyaçlarınızı tek bir yerden yönetin. Güncel stok bilgisi, şeffaf fiyatlandırma ve profesyonel teslim süreçleriyle işinizi hızlandırın.'), false) ?>
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars($primaryCtaLink) ?>" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm px-4 py-3 shadow-lg shadow-brand-500/30">
                            <?= htmlspecialchars($primaryCtaLabel) ?>
                        </a>
                        <a href="#order-form" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white/80 dark:bg-slate-900/70">
                            Sipariş Talebi Oluştur
                        </a>
                        <a href="<?= htmlspecialchars($whatsappLink) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-700/50 dark:bg-emerald-900/30 dark:text-emerald-200 px-4 py-3 text-sm font-semibold">
                            WhatsApp ile İletişim
                        </a>
                    </div>
                    <?php if ($cartCount > 0): ?>
                        <p class="mt-4 text-sm font-semibold text-brand-600 dark:text-brand-200">
                            Sepetinizde <?= number_format($cartCount) ?> ürün var. <a href="<?= htmlspecialchars($cartLink) ?>" class="underline underline-offset-4">Sepeti görüntüleyin</a> ve tamamlayın.
                        </p>
                    <?php endif; ?>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 -z-10 rounded-3xl bg-gradient-to-br from-brand-200/40 via-brand-100/20 to-white blur-3xl"></div>
                    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/90 dark:bg-slate-900/80 shadow-2xl p-6 sm:p-8 space-y-6">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Anlık Durum</p>
                            <h3 class="mt-2 text-2xl font-semibold">Katalog Özeti</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 p-4">
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Kategori</p>
                                <p class="mt-2 text-3xl font-semibold"><?= number_format($categoryCount) ?></p>
                                <p class="mt-1 text-xs text-slate-500">Aktif hizmet grubu</p>
                            </article>
                            <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 p-4">
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Ürün</p>
                                <p class="mt-2 text-3xl font-semibold"><?= number_format($productCount) ?></p>
                                <p class="mt-1 text-xs text-slate-500">Katalogdaki çözüm</p>
                            </article>
                            <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 p-4">
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Başlangıç</p>
                                <p class="mt-2 text-3xl font-semibold"><?= $startingProductLabel ? htmlspecialchars($startingProductLabel) : 'Fiyat alınız' ?></p>
                                <p class="mt-1 text-xs text-slate-500">En uygun fiyatlı paket</p>
                            </article>
                            <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 p-4">
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Destek</p>
                                <p class="mt-2 text-3xl font-semibold">7/24</p>
                                <p class="mt-1 text-xs text-slate-500">Profesyonel teslim ve destek</p>
                            </article>
                        </div>
                        <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                            <li class="flex items-start gap-3"><span class="mt-1 text-brand-600">•</span> Kategori bazlı filtreleme, hızlı arama ve gerçek zamanlı fiyat sıralama</li>
                            <li class="flex items-start gap-3"><span class="mt-1 text-brand-600">•</span> Her sipariş için otomatik numaralandırma ve yönetim paneli entegrasyonu</li>
                            <li class="flex items-start gap-3"><span class="mt-1 text-brand-600">•</span> Güvenli veri işleme, CSRF korumalı müşteri talepleri</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($heroHasVisual): ?>
        <?php
            $autoplay = !empty($heroSettings['autoplay']);
            $autoplayMs = max(2000, (int) ($heroSettings['autoplay_ms'] ?? 4000));
            $showDots = !empty($heroSettings['show_dots']);
            $showArrows = !empty($heroSettings['show_arrows']);
            $slideCount = count($mainSlides);
            if ($slideCount <= 1) {
                $autoplay = false;
                $showDots = false;
                $showArrows = false;
            }
            $heroGridClasses = 'hero-grid';
            if ($hasLeftBanner) {
                $heroGridClasses .= ' has-left';
            }
            if ($hasRightBanner) {
                $heroGridClasses .= ' has-right';
            }
            $carouselAriaLive = $autoplay ? 'off' : 'polite';
        ?>
        <section id="hero" class="mx-auto max-w-6xl px-4 pb-12" aria-label="Hero vitrin">
            <div class="<?= htmlspecialchars($heroGridClasses, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($hasLeftBanner): ?>
                    <?php
                        $leftLink = $leftBanner['link_url'] ?? '';
                        $leftAlt = $leftBanner['alt_text'] ?? '';
                        $leftAlt = $leftAlt !== '' ? $leftAlt : 'Sol afiş';
                        $leftTag = $leftLink !== '' ? 'a' : 'div';
                    ?>
                    <div class="hero-banner-slot hero-banner-slot-left">
                        <<?= $leftTag ?>
                            id="hero-left"
                            class="hero-banner"
                            <?= $leftLink !== '' ? 'href="' . htmlspecialchars($leftLink, ENT_QUOTES, 'UTF-8') . '"' : 'role="img"' ?>
                            aria-label="<?= htmlspecialchars($leftAlt, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <img src="<?= htmlspecialchars($leftBanner['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($leftAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async" />
                        </<?= $leftTag ?>>
                    </div>
                <?php endif; ?>

                <div class="hero-carousel-wrapper">
                    <?php if ($slideCount > 0): ?>
                        <div
                            id="hero-carousel"
                            class="hero-carousel"
                            data-hero-carousel
                            data-autoplay="<?= $autoplay ? 'true' : 'false' ?>"
                            data-autoplay-ms="<?= $autoplayMs ?>"
                            data-show-dots="<?= $showDots ? 'true' : 'false' ?>"
                            data-show-arrows="<?= $showArrows ? 'true' : 'false' ?>"
                            data-hero-count="<?= $slideCount ?>"
                            role="region"
                            aria-roledescription="carousel"
                            aria-label="Öne çıkanlar"
                            aria-live="<?= $carouselAriaLive ?>"
                            tabindex="0"
                        >
                            <?php if ($showArrows && $slideCount > 1): ?>
                                <button type="button" class="hero-nav hero-nav-prev" aria-label="Önceki" data-hero-prev>‹</button>
                            <?php endif; ?>
                            <div class="hero-track" data-hero-track style="transform: translateX(0%);">
                                <?php foreach ($mainSlides as $index => $slide): ?>
                                    <?php
                                        $slideLink = $slide['link_url'] ?? '';
                                        $slideTag = $slideLink !== '' ? 'a' : 'div';
                                        $slideIndex = $index + 1;
                                        $slideId = 'hero-slide-' . $slideIndex;
                                        $dotId = 'hero-dot-' . $slideIndex;
                                        $isActive = $index === 0;
                                        $slideLabel = $slide['alt_text'] ?? '';
                                        $slideLabel = $slideLabel !== '' ? $slideLabel : ($companyName . ' vitrini ' . $slideIndex);
                                        $tabIndex = $isActive ? '0' : '-1';
                                        $ariaHidden = $isActive ? 'false' : 'true';
                                        $loadingAttr = $isActive ? 'eager' : 'lazy';
                                    ?>
                                    <<?= $slideTag ?>
                                        class="hero-slide"
                                        <?= $slideLink !== '' ? 'href="' . htmlspecialchars($slideLink, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                        id="<?= htmlspecialchars($slideId, ENT_QUOTES, 'UTF-8') ?>"
                                        data-hero-slide="<?= $index ?>"
                                        role="group"
                                        aria-roledescription="slide"
                                        aria-labelledby="<?= htmlspecialchars($dotId, ENT_QUOTES, 'UTF-8') ?>"
                                        aria-hidden="<?= $ariaHidden ?>"
                                        tabindex="<?= $tabIndex ?>"
                                    >
                                        <img src="<?= htmlspecialchars($slide['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($slideLabel, ENT_QUOTES, 'UTF-8') ?>" loading="<?= $loadingAttr ?>" decoding="async" />
                                        <div class="hero-slide-overlay" aria-hidden="true"></div>
                                        <div class="hero-slide-caption">
                                            <span class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-100">Öne çıkan</span>
                                            <p class="text-lg md:text-2xl font-semibold leading-tight text-slate-50">
                                                <?= htmlspecialchars($slideLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </div>
                                    </<?= $slideTag ?>>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($showArrows && $slideCount > 1): ?>
                                <button type="button" class="hero-nav hero-nav-next" aria-label="Sonraki" data-hero-next>›</button>
                            <?php endif; ?>
                            <?php if ($showDots && $slideCount > 1): ?>
                                <div class="hero-dots" role="tablist">
                                    <?php foreach ($mainSlides as $index => $slide): ?>
                                        <?php
                                            $dotIndex = $index + 1;
                                            $dotId = 'hero-dot-' . $dotIndex;
                                            $controlledSlideId = 'hero-slide-' . $dotIndex;
                                            $isDotActive = $index === 0;
                                            $dotLabel = $slide['alt_text'] ?? '';
                                            $dotLabel = $dotLabel !== '' ? $dotLabel : 'Hero slaytı ' . $dotIndex;
                                        ?>
                                        <button
                                            type="button"
                                            class="hero-dot"
                                            id="<?= htmlspecialchars($dotId, ENT_QUOTES, 'UTF-8') ?>"
                                            data-hero-dot="<?= $index ?>"
                                            role="tab"
                                            aria-controls="<?= htmlspecialchars($controlledSlideId, ENT_QUOTES, 'UTF-8') ?>"
                                            aria-selected="<?= $isDotActive ? 'true' : 'false' ?>"
                                            tabindex="<?= $isDotActive ? '0' : '-1' ?>"
                                            aria-label="<?= htmlspecialchars($dotLabel, ENT_QUOTES, 'UTF-8') ?>"
                                        ></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="hero-carousel" role="presentation"></div>
                    <?php endif; ?>
                </div>

                <?php if ($hasRightBanner): ?>
                    <?php
                        $rightLink = $rightBanner['link_url'] ?? '';
                        $rightAlt = $rightBanner['alt_text'] ?? '';
                        $rightAlt = $rightAlt !== '' ? $rightAlt : 'Sağ afiş';
                        $rightTag = $rightLink !== '' ? 'a' : 'div';
                    ?>
                    <div class="hero-banner-slot hero-banner-slot-right">
                        <<?= $rightTag ?>
                            id="hero-right"
                            class="hero-banner"
                            <?= $rightLink !== '' ? 'href="' . htmlspecialchars($rightLink, ENT_QUOTES, 'UTF-8') . '"' : 'role="img"' ?>
                            aria-label="<?= htmlspecialchars($rightAlt, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <img src="<?= htmlspecialchars($rightBanner['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($rightAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async" />
                        </<?= $rightTag ?>>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($stripHasItems): ?>
                <div id="hero-strip" class="hero-strip" role="list" aria-label="Mini şerit bağlantıları">
                    <?php foreach ($stripItems as $strip): ?>
                        <a href="<?= htmlspecialchars($strip['link_url'], ENT_QUOTES, 'UTF-8') ?>" class="hero-strip-item" role="listitem">
                            <img src="<?= htmlspecialchars($strip['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($strip['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async" />
                            <span class="text-sm font-semibold leading-snug">
                                <?= htmlspecialchars($strip['title'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($stripHasItems): ?>
        <section class="mx-auto max-w-6xl px-4 pb-12">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($stripItems as $strip): ?>
                    <a href="<?= htmlspecialchars($strip['link_url']) ?>" class="group flex items-center gap-4 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 px-4 py-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg" role="listitem">
                        <span class="relative h-12 w-12 overflow-hidden rounded-xl bg-slate-900/5 dark:bg-slate-100/10">
                            <img src="<?= htmlspecialchars($strip['image_url']) ?>" alt="<?= htmlspecialchars($strip['title']) ?>" class="h-full w-full object-cover" loading="lazy" />
                        </span>
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($strip['title']) ?></span>
                        </span>
                        <span class="ml-auto inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white/70 text-slate-600 transition group-hover:border-brand-400 group-hover:text-brand-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">➜</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($homeBlocks)): ?>
        <section class="mx-auto max-w-6xl px-4 pb-12">
            <div class="space-y-10">
                <?php foreach ($homeBlocks as $block): ?>
                    <?php
                        $blockTitle = (string) ($block['title'] ?? 'Öne Çıkanlar');
                        $viewSlug = trim((string) ($block['view_slug'] ?? ''));
                        $viewType = (string) ($block['view_type'] ?? 'category');
                        $parentSlug = $viewType === 'subcategory' ? ($subcategorySlugIndex[$viewSlug] ?? '') : ($viewSlug !== '' ? $viewSlug : '');
                        $linkCategory = $viewType === 'subcategory' ? $parentSlug : $viewSlug;
                        $linkSubcategory = $viewType === 'subcategory' ? $viewSlug : '';
                    ?>
                    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 shadow-lg p-6 sm:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($blockTitle) ?></h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Bu bölümde yer alan ürünler vitrin ekibimiz tarafından seçildi.</p>
                            </div>
                            <?php if ($viewSlug !== ''): ?>
                                <a href="#catalog" class="block-view-link inline-flex items-center gap-2 rounded-2xl border border-brand-300/70 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 dark:border-brand-700/60 dark:bg-brand-900/40 dark:text-brand-200" data-category="<?= htmlspecialchars($linkCategory !== '' ? $linkCategory : 'all') ?>" data-subcategory="<?= htmlspecialchars($linkSubcategory) ?>">
                                    Tümünü gör
                                    <span aria-hidden="true">→</span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            <?php foreach ($block['products'] as $product): ?>
                                <?php
                                    $primaryImage = trim((string) ($product['primary_image_url'] ?? ''));
                                    $priceLabel = (string) ($product['price_label'] ?? 'Fiyat alınız');
                                    $badges = $product['badges'] ?? [];
                                    $badgeLabel = '';
                                    $badgeClasses = 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-700/60';
                                    if (!empty($badges) && is_array($badges)) {
                                        $badgeLabel = (string) reset($badges);
                                        $badgeClasses = 'bg-brand-50 text-brand-700 border border-brand-200 dark:bg-brand-900/30 dark:text-brand-200 dark:border-brand-700/60';
                                    }
                                ?>
                                <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/90 dark:bg-slate-900/70 p-5 shadow-sm flex flex-col gap-4">
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-300">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500/10 text-base">
                                                <?= htmlspecialchars($product['category']['emoji'] ?? '📦') ?>
                                            </span>
                                            <?= htmlspecialchars((string) ($product['category']['title'] ?? 'Kategori')) ?>
                                        </span>
                                        <?php if ($badgeLabel !== ''): ?>
                                            <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full <?= $badgeClasses ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($primaryImage !== ''): ?>
                                        <div class="relative overflow-hidden rounded-xl border border-slate-200/60 dark:border-slate-800/60">
                                            <img src="<?= htmlspecialchars($primaryImage) ?>" alt="<?= htmlspecialchars((string) ($product['name'] ?? 'Ürün görseli')) ?>" class="h-40 w-full object-cover" loading="lazy" />
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <h4 class="text-lg font-semibold leading-snug text-slate-900 dark:text-slate-100"><?= htmlspecialchars((string) ($product['name'] ?? 'Ürün')) ?></h4>
                                        <?php if (!empty($product['short_description'])): ?>
                                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars((string) $product['short_description']) ?></p>
                                        <?php elseif (!empty($product['note'])): ?>
                                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars((string) $product['note']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-lg font-semibold text-brand-600 dark:text-brand-200"><?= htmlspecialchars($priceLabel) ?></span>
                                        <form method="post" action="/cart.php" class="flex items-center">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>" />
                                            <input type="hidden" name="form_key" value="cart-action" />
                                            <input type="hidden" name="cart_action" value="add" />
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>" />
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-600">Sepete ekle</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section id="catalog" class="mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight">Ürün kataloğu</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Kategori seçin, arama yapın veya sıralama seçeneklerini kullanın.</p>
            </div>
            <div class="flex flex-wrap gap-3" role="group" aria-label="Kategori filtresi">
                <button type="button" data-category="all" class="catalog-category-btn inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-sm font-medium bg-white/80 dark:bg-slate-900/70 text-brand-600 dark:text-brand-200 shadow">Tümü</button>
                <?php foreach ($catalog as $category): ?>
                    <button type="button" data-category="<?= htmlspecialchars($category['slug']) ?>" class="catalog-category-btn inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-sm font-medium bg-white/60 dark:bg-slate-900/50 text-slate-600 dark:text-slate-300">
                        <span><?= htmlspecialchars($category['emoji']) ?></span>
                        <span><?= htmlspecialchars($category['title']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div id="subcategory-filter" class="hidden flex flex-wrap gap-2 w-full" role="group" aria-label="Alt kategori filtresi">
                <?php foreach ($catalog as $category): ?>
                    <?php if (!empty($category['subcategories'])): ?>
                        <?php foreach ($category['subcategories'] as $subcategory): ?>
                            <?php
                                $subIcon = trim((string)($subcategory['icon'] ?? ''));
                                $isSubImage = $subIcon !== '' && (preg_match('#^(https?:)?//#', $subIcon) === 1 || strncmp($subIcon, '/', 1) === 0);
                            ?>
                            <button type="button" data-parent="<?= htmlspecialchars($category['slug']) ?>" data-subcategory="<?= htmlspecialchars($subcategory['slug']) ?>" class="catalog-subcategory-btn inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-sm font-medium bg-white/70 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300 hidden">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/20 text-xs">
                                    <?php if ($subIcon !== ''): ?>
                                        <?php if ($isSubImage): ?>
                                            <img src="<?= htmlspecialchars($subIcon) ?>" alt="<?= htmlspecialchars($subcategory['title']) ?> ikon" class="h-full w-full object-contain" />
                                        <?php else: ?>
                                            <?= htmlspecialchars($subIcon) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        •
                                    <?php endif; ?>
                                </span>
                                <span><?= htmlspecialchars($subcategory['title']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-1 space-y-4">
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-300" for="catalog-search">Katalogda ara</label>
                <input id="catalog-search" type="search" placeholder="Ürün adı arayın" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-300" for="catalog-sort">Sıralama</label>
                <select id="catalog-sort" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="asc">Fiyat: Ucuzdan pahalıya</option>
                    <option value="desc">Fiyat: Pahalıdan ucuza</option>
                    <option value="name">İsme göre A→Z</option>
                </select>
                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/70 p-4 text-sm text-slate-600 dark:text-slate-300">
                    <p class="font-semibold text-slate-700 dark:text-slate-100">Filtre durumu</p>
                    <p class="mt-2" id="catalog-summary"><?= number_format($productCount) ?> ürün listeleniyor.</p>
                </div>
            </div>
            <div class="lg:col-span-3">
                <?php if ($catalogError !== ''): ?>
                    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
                        <?= htmlspecialchars($catalogError) ?>
                    </div>
                <?php elseif ($productCount === 0): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-600/40 dark:bg-amber-900/30 dark:text-amber-100 px-4 py-4">
                        Şu an katalogda gösterilecek ürün bulunmuyor. Lütfen daha sonra tekrar deneyin.
                    </div>
                <?php else: ?>
                    <div id="catalog-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($productOptions as $index => $product): ?>
                            <?php
                                $badge = 'Öne Çıkan';
                                $badgeClasses = 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-700/60';
                                if ($index < 6) {
                                    $badge = 'Kampanya';
                                    $badgeClasses = 'bg-brand-50 text-brand-700 border border-brand-200 dark:bg-brand-900/30 dark:text-brand-200 dark:border-brand-700/60';
                                } elseif (!empty($product['note']) && stripos((string)$product['note'], 'popüler') !== false) {
                                    $badge = 'Popüler';
                                    $badgeClasses = 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-700/60';
                                }
                            ?>
                            <article class="catalog-card rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm flex flex-col gap-4 transition-transform hover:-translate-y-0.5" data-category="<?= htmlspecialchars($product['category']['slug']) ?>" data-subcategory="<?= htmlspecialchars($product['subcategory']['slug'] ?? '') ?>" data-price="<?= (int) $product['price_cents'] ?>" data-name="<?= htmlspecialchars(mb_strtolower($product['name'])) ?>">
                                <div class="flex items-center justify-between gap-3">
                                    <?php
                                        $categoryIconValue = trim((string)($product['category']['icon'] ?? ''));
                                        $categoryEmojiValue = trim((string)($product['category']['emoji'] ?? ''));
                                        $categoryDisplayEmoji = $categoryEmojiValue !== '' ? $categoryEmojiValue : '📂';
                                        $categoryIconIsImage = $categoryIconValue !== '' && (preg_match('#^(https?:)?//#', $categoryIconValue) === 1 || strncmp($categoryIconValue, '/', 1) === 0);
                                        $subcategoryIconValue = trim((string)($product['subcategory']['icon'] ?? ''));
                                        $subcategoryIconIsImage = $subcategoryIconValue !== '' && (preg_match('#^(https?:)?//#', $subcategoryIconValue) === 1 || strncmp($subcategoryIconValue, '/', 1) === 0);
                                    ?>
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-300">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/20 text-sm">
                                            <?php if ($categoryIconValue !== ''): ?>
                                                <?php if ($categoryIconIsImage): ?>
                                                    <img src="<?= htmlspecialchars($categoryIconValue) ?>" alt="<?= htmlspecialchars($product['category']['title']) ?> ikon" class="h-full w-full object-contain" />
                                                <?php else: ?>
                                                    <?= htmlspecialchars($categoryIconValue) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($categoryDisplayEmoji) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span><?= htmlspecialchars($product['category']['title']) ?></span>
                                        <?php if (!empty($product['subcategory'])): ?>
                                            <span class="text-slate-400" aria-hidden="true">›</span>
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-300">
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 dark:bg-slate-800 text-xs">
                                                    <?php if ($subcategoryIconValue !== ''): ?>
                                                        <?php if ($subcategoryIconIsImage): ?>
                                                            <img src="<?= htmlspecialchars($subcategoryIconValue) ?>" alt="<?= htmlspecialchars($product['subcategory']['title']) ?> ikon" class="h-full w-full object-contain" />
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($subcategoryIconValue) ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        •
                                                    <?php endif; ?>
                                                </span>
                                                <span><?= htmlspecialchars($product['subcategory']['title']) ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full <?= $badgeClasses ?>">
                                        <?= htmlspecialchars($badge) ?>
                                    </span>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold tracking-tight leading-snug"><?= htmlspecialchars($product['name']) ?></h3>
                                    <?php if (!empty($product['note'])): ?>
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($product['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xl font-semibold text-brand-600 dark:text-brand-200"><?= htmlspecialchars($product['price_label']) ?></span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <form method="post" action="/cart.php" class="flex items-center">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>" />
                                            <input type="hidden" name="form_key" value="cart-action" />
                                            <input type="hidden" name="cart_action" value="add" />
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>" />
                                            <input type="hidden" name="quantity" value="1" />
                                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentRequestUri) ?>" />
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-xs sm:text-sm font-semibold px-3 py-2 shadow">
                                                🛒 Sepete Ekle
                                            </button>
                                        </form>
                                        <a href="#order-form" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs sm:text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-300">Hızlı Sipariş</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="services" class="bg-white/80 dark:bg-slate-900/70 border-y border-slate-200/70 dark:border-slate-800/70">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:py-16">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/40 p-5">
                    <h3 class="text-lg font-semibold">Teslimat Takibi</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Siparişleriniz yönetim paneline anında düşer, durum güncellemeleri müşterilere bildirilir.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/40 p-5">
                    <h3 class="text-lg font-semibold">Özelleştirilmiş Paketler</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Kategori bazlı isteğe göre yapılandırılmış lisans ve hesap çözümleri.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/40 p-5">
                    <h3 class="text-lg font-semibold">Güvenli Ödeme</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Kart, havale/EFT ve kripto dahil olmak üzere esnek ödeme yöntemleri.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/40 p-5">
                    <h3 class="text-lg font-semibold">7/24 Destek</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">WhatsApp ve e-posta üzerinden uzman ekibimizle hızlı destek.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="blog" class="mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight">Blog ve Güncellemeler</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Kategorilerimizdeki son hareketleri ve önerilen paketleri takip edin.</p>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-xl">Her kategori için düzenli olarak içerikler, kampanyalar ve kullanım önerileri paylaşıyoruz. Lisansonay topluluğu için hazırladığımız öne çıkan başlıklara göz atın.</p>
        </div>
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($blogHighlights as $highlight): ?>
                <?php
                    $highlightIcon = trim((string)($highlight['icon'] ?? ''));
                    $highlightEmoji = trim((string)($highlight['emoji'] ?? ''));
                    $highlightDisplay = $highlightEmoji !== '' ? $highlightEmoji : '🗂️';
                    $highlightIsImage = $highlightIcon !== '' && (preg_match('#^(https?:)?//#', $highlightIcon) === 1 || strncmp($highlightIcon, '/', 1) === 0);
                    $targetSlug = trim((string)($highlight['slug'] ?? ''));
                    $targetCategory = $targetSlug !== '' ? $targetSlug : 'all';
                ?>
                <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/65 shadow-sm p-6 flex flex-col gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-500/20 text-2xl">
                        <?php if ($highlightIcon !== ''): ?>
                            <?php if ($highlightIsImage): ?>
                                <img src="<?= htmlspecialchars($highlightIcon) ?>" alt="<?= htmlspecialchars($highlight['title']) ?> ikon" class="h-full w-full object-contain" />
                            <?php else: ?>
                                <?= htmlspecialchars($highlightIcon) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= htmlspecialchars($highlightDisplay) ?>
                        <?php endif; ?>
                    </span>
                    <div class="space-y-3">
                        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100 leading-tight"><?= htmlspecialchars($highlight['title']) ?></h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($highlight['excerpt']) ?></p>
                    </div>
                    <div class="mt-auto pt-2">
                        <a href="#catalog" class="blog-category-btn inline-flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-200" data-category="<?= htmlspecialchars($targetCategory) ?>">
                            <?= $targetSlug !== '' ? 'Kategoriyi incele' : 'Kataloğu keşfet' ?>
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="order-form" class="mx-auto max-w-6xl px-4 py-12 sm:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <h2 class="text-2xl font-semibold tracking-tight">Sipariş talebi oluşturun</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Formu doldurun, yönetim ekibimiz siparişinizi sisteme düşürüp size dönüş yapsın.</p>

                <?php if (!empty($orderErrors)): ?>
                    <div class="mt-6 space-y-2">
                        <?php foreach ($orderErrors as $error): ?>
                            <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($flashes as $flash): ?>
                    <div class="mt-6 rounded-2xl border <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-700/40 dark:bg-emerald-900/30 dark:text-emerald-200' : 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-700/40 dark:bg-brand-900/30 dark:text-brand-200' ?> px-4 py-3 text-sm">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endforeach; ?>

                <form method="post" class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-5" novalidate>
                    <input type="hidden" name="action" value="place-order" />
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($orderToken) ?>" />
                    <div class="sm:col-span-2">
                        <label for="customer_name" class="block text-sm font-medium text-slate-600 dark:text-slate-300">Ad Soyad *</label>
                        <input type="text" id="customer_name" name="customer_name" required value="<?= htmlspecialchars($orderFormData['customer_name']) ?>" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Adınız ve soyadınız" />
                    </div>
                    <div>
                        <label for="customer_email" class="block text-sm font-medium text-slate-600 dark:text-slate-300">E-posta</label>
                        <input type="email" id="customer_email" name="customer_email" value="<?= htmlspecialchars($orderFormData['customer_email']) ?>" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="ornek@mail.com" />
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-slate-600 dark:text-slate-300">Telefon</label>
                        <input type="text" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($orderFormData['customer_phone']) ?>" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="+90 5xx xxx xx xx" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="product_id" class="block text-sm font-medium text-slate-600 dark:text-slate-300">Ürün *</label>
                        <select id="product_id" name="product_id" required class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <?php foreach ($catalog as $category): ?>
                                <?php if (empty($category['products'])): ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <optgroup label="<?= htmlspecialchars($category['title']) ?>">
                                    <?php foreach ($category['products'] as $product): ?>
                                        <option value="<?= (int) $product['id'] ?>" data-price="<?= (int) $product['price_cents'] ?>" data-price-label="<?= htmlspecialchars($product['price_label']) ?>" <?= ((string) $product['id'] === (string) $orderFormData['product_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($product['name']) ?> — <?= htmlspecialchars($product['price_label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-slate-600 dark:text-slate-300">Adet *</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="1000" value="<?= htmlspecialchars($orderFormData['quantity']) ?>" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-slate-600 dark:text-slate-300">Ek Not</label>
                        <textarea id="notes" name="notes" rows="4" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Teslimat veya kullanım detaylarını paylaşabilirsiniz."><?= htmlspecialchars($orderFormData['notes']) ?></textarea>
                    </div>
                    <div class="sm:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-brand-200 dark:border-brand-700/40 bg-brand-50/70 dark:bg-brand-900/30 px-5 py-4 text-sm">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-700 dark:text-brand-200">Özet</p>
                            <p class="mt-1 font-semibold text-lg" id="order-total-label">Toplam: <span><?= $productOptions ? htmlspecialchars($productOptions[0]['price_label']) : '—' ?></span></p>
                            <p class="mt-1 text-xs text-brand-700/80 dark:text-brand-200/80">Kesin tutar siparişiniz onaylandığında paylaşılacaktır.</p>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-3 shadow-lg shadow-brand-500/30">
                            Talebimi Gönder
                        </button>
                    </div>
                </form>
            </div>
            <aside class="space-y-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/70 p-6">
                <div>
                    <h3 class="text-lg font-semibold">Süreç Nasıl İşler?</h3>
                    <ol class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 list-decimal list-inside">
                        <li>Talebiniz yönetim paneline düşer, sorumlu ekip atanır.</li>
                        <li>Ödeme ve teslimat detayları size iletilir.</li>
                        <li>Teslim sonrası gerekli kurulum ve eğitim desteği sağlanır.</li>
                    </ol>
                </div>
                <div>
                    <h3 class="text-lg font-semibold">Neden Lisansonay?</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <li>• Kurumsal SLA ve kayıt altına alınan süreçler</li>
                        <li>• Detaylı raporlama ve geçmiş sipariş erişimi</li>
                        <li>• Faturalandırma ve yenileme planlaması</li>
                    </ul>
                </div>
                <div id="contact" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-950/40 p-5 text-sm">
                    <h3 class="text-lg font-semibold">İletişim</h3>
                    <p class="mt-3"><span class="font-medium">E-posta:</span> <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="text-brand-600 hover:text-brand-500"><?= htmlspecialchars($supportEmail) ?></a></p>
                    <?php if ($supportPhone !== ''): ?>
                        <p class="mt-2"><span class="font-medium">Telefon:</span> <a href="tel:<?= htmlspecialchars($supportPhone) ?>" class="text-brand-600 hover:text-brand-500"><?= htmlspecialchars($supportPhone) ?></a></p>
                    <?php endif; ?>
                    <?php if ($supportHours !== ''): ?>
                        <p class="mt-2"><span class="font-medium">Destek Saatleri:</span> <?= htmlspecialchars($supportHours) ?></p>
                    <?php endif; ?>
                    <p class="mt-2"><span class="font-medium">WhatsApp:</span> <a href="<?= htmlspecialchars($whatsappLink) ?>" target="_blank" rel="noopener noreferrer" class="text-brand-600 hover:text-brand-500">Hızlı iletişim</a></p>
                    <?php if ($companyAddress !== ''): ?>
                        <p class="mt-2"><span class="font-medium">Adres:</span><br><span class="text-slate-600 dark:text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($companyAddress), false) ?></span></p>
                    <?php endif; ?>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-base">
                        <?php if ($branding['instagram_url'] !== ''): ?>
                            <a href="<?= htmlspecialchars($branding['instagram_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-slate-600 hover:text-brand-600">📸 Instagram</a>
                        <?php endif; ?>
                        <?php if ($branding['telegram_url'] !== ''): ?>
                            <a href="<?= htmlspecialchars($branding['telegram_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-slate-600 hover:text-brand-600">💬 Telegram</a>
                        <?php endif; ?>
                        <?php if ($branding['twitter_url'] !== ''): ?>
                            <a href="<?= htmlspecialchars($branding['twitter_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-slate-600 hover:text-brand-600">🐦 Twitter</a>
                        <?php endif; ?>
                        <?php if ($branding['facebook_url'] !== ''): ?>
                            <a href="<?= htmlspecialchars($branding['facebook_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-slate-600 hover:text-brand-600">📘 Facebook</a>
                        <?php endif; ?>
                        <?php if ($branding['linkedin_url'] !== ''): ?>
                            <a href="<?= htmlspecialchars($branding['linkedin_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-slate-600 hover:text-brand-600">💼 LinkedIn</a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</main>
<footer class="border-t border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/80">
    <div class="mx-auto max-w-6xl px-4 py-6 text-xs sm:text-sm text-slate-600 dark:text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-3">
        <p>© <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>. Tüm hakları saklıdır.</p>
        <div class="flex items-center gap-4">
            <a href="/admin/index.php" class="text-brand-600 hover:text-brand-500 font-medium">Yönetim Girişi</a>
            <span>Veri güvenliği ve kesintisiz hizmet garantisi.</span>
        </div>
    </div>
</footer>
<script src="/public/assets/js/app.js" defer></script>
<script src="/public/assets/js/public.js" defer></script>
</body>
</html>
