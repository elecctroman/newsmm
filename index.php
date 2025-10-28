<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';
require_once __DIR__ . '/src/storefront_components.php';

ensureSession();

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($requestPath !== '/' && $requestPath !== '/index.php') {
    if (preg_match('#^/kategori/([A-Za-z0-9\-]+)$#', $requestPath, $matches)) {
        $slug = $matches[1];
        require __DIR__ . '/kategori.php';
        return;
    }
    if (preg_match('#^/urun/([A-Za-z0-9\-]+)$#', $requestPath, $matches)) {
        $slug = $matches[1];
        require __DIR__ . '/urun.php';
        return;
    }
}

$customer = getCurrentCustomer();
$cartCount = getActiveCartCount($pdo ?? null, $customer);
$cartToken = issueCsrfToken('cart-action');

$settings = [];
$catalog = [];
$catalogError = '';

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchPublicSettings($pdo);
        $catalog = fetchPublicCatalog($pdo);
    } catch (Throwable $e) {
        $catalogError = 'Katalog verileri yüklenemedi: ' . $e->getMessage();
    }
} else {
    $catalogError = 'Veritabanı bağlantısı kurulamadı.';
}

$home = buildHomePageViewModel($settings, $catalog);
$hero = $home['hero'];
$bestSelling = $home['best_selling'];
$categoryRows = $home['rows'];
$aboutBlock = $home['about'];

$company = buildBrandingContext($settings);
$metaTitle = $company['meta_title'] ?: 'Lisansonay | Lisans Pazarı';
$metaDescription = $company['meta_description'] ?: 'Dijital ürünler, lisanslar ve abonelikler için Lisansonay vitrinini keşfedin.';
$logoLight = $company['logo_light'] ?: '/public/assets/img/placeholder-hero.svg';

$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
$navCategories = [];
foreach ($catalog as $category) {
    $slug = (string) ($category['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $navCategories[] = [
        'slug' => $slug,
        'title' => $category['title'] ?? 'Kategori',
    ];
    if (count($navCategories) >= 8) {
        break;
    }
}

$heroSlides = $hero['slides'] ?? [];
$heroBanner = $hero['rightBanner'] ?? null;
$heroEnabled = !empty($hero['enabled']) && !empty($heroSlides);
$mainClass = 'lk-main';
if (!$heroEnabled) {
    $mainClass .= ' lk-main--no-hero';
}
$bestSectionClass = 'lk-section lk-section--compact';
if (!$heroEnabled) {
    $bestSectionClass .= ' lk-section--first';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <link rel="stylesheet" href="/public/assets/css/home.css">
    <?php if ($heroEnabled && !empty($heroSlides[0]['image'])): ?>
        <link rel="preload" as="image" href="<?= htmlspecialchars($heroSlides[0]['image']) ?>">
    <?php endif; ?>
</head>
<body class="lk-page">
<?php storefrontRenderHeader($company, $cartCount, $customer, $navCategories, $logoLight); ?>
<main class="<?= $mainClass ?>">
    <?php if ($catalogError !== ''): ?>
        <div class="lk-container" style="margin-top: var(--lk-spacing-lg);">
            <div class="lk-alert"><?= htmlspecialchars($catalogError) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($heroEnabled): ?>
    <section class="lk-hero lk-section--first" id="hero">
        <div class="lk-container">
            <div class="lk-hero__grid">
                <div class="lk-hero__carousel" role="region" aria-roledescription="carousel" aria-label="Öne çıkanlar" data-autoplay="true">
                    <button class="lk-hero__nav lk-hero__nav--prev" aria-label="Önceki">‹</button>
                    <div class="lk-hero__track" aria-live="polite">
                        <?php foreach ($heroSlides as $index => $slide):
                            $slideSources = storefrontImageSources(['lg' => $slide['image'] ?? '', 'md' => $slide['image'] ?? '', 'sm' => $slide['image'] ?? '', 'alt' => $slide['alt'] ?? 'Hero'], '/public/assets/img/placeholder-hero.svg');
                            $slideAlt = $slideSources['alt'] !== '' ? $slideSources['alt'] : ($slide['alt'] ?? 'Hero');
                        ?>
                        <a class="lk-hero__slide" id="lk-hero-slide-<?= $index ?>" href="<?= htmlspecialchars($slide['link'] ?? '#') ?>" aria-hidden="true">
                            <img src="<?= htmlspecialchars($slideSources['src']) ?>" srcset="<?= htmlspecialchars($slideSources['srcset']) ?>" sizes="(max-width: 1024px) 100vw, 820px" alt="<?= htmlspecialchars($slideAlt) ?>" loading="lazy" decoding="async">
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <button class="lk-hero__nav lk-hero__nav--next" aria-label="Sonraki">›</button>
                    <div class="lk-hero__dots" role="tablist" aria-label="Slayt kontrolleri"></div>
                </div>
                <?php if (!empty($heroBanner['image'])):
                    $bannerSources = storefrontImageSources(['lg' => $heroBanner['image'], 'md' => $heroBanner['image'], 'sm' => $heroBanner['image'], 'alt' => $heroBanner['alt'] ?? 'Promosyon'], '/public/assets/img/placeholder-hero.svg');
                    $bannerAlt = $bannerSources['alt'] !== '' ? $bannerSources['alt'] : ($heroBanner['alt'] ?? 'Promosyon');
                ?>
                <a class="lk-hero__promo" href="<?= htmlspecialchars($heroBanner['link'] ?? '#') ?>">
                    <img src="<?= htmlspecialchars($bannerSources['src']) ?>" srcset="<?= htmlspecialchars($bannerSources['srcset']) ?>" sizes="(max-width: 1024px) 100vw, 420px" alt="<?= htmlspecialchars($bannerAlt) ?>" loading="lazy" decoding="async">
                </a>
                <?php else: ?>
                <div class="lk-hero__promo" hidden></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="<?= $bestSectionClass ?>" id="best-selling">
        <div class="lk-container">
            <div class="lk-section__header">
                <div>
                    <h2>Best Selling Licenses</h2>
                    <p>En popüler lisans ürünlerini keşfedin.</p>
                </div>
            </div>
            <div class="lk-products-grid">
                <?php foreach ($bestSelling as $product):
                    storefrontRenderProductCard($product, $cartToken, $currentUrl);
                endforeach; ?>
            </div>
        </div>
    </section>

    <?php foreach ($categoryRows as $row):
        $category = $row['category'];
        $categorySlug = $category['slug'] ?? '';
        $categoryTitle = $category['title'] ?? 'Kategori';
        $categoryDescription = trim((string) ($category['description'] ?? ''));
        if ($categoryDescription === '') {
            $categoryDescription = $categoryTitle . ' kategorisindeki seçili ürünler';
        }
        $leftImageSources = storefrontImageSources([
            'lg' => $category['hero_image'] ?? $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
            'md' => $category['hero_image'] ?? $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
            'sm' => $category['hero_image'] ?? $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
            'alt' => $categoryTitle,
        ], '/public/assets/img/placeholder-hero.svg');
    ?>
    <section class="lk-row">
        <div class="lk-container lk-row__inner">
            <div class="lk-row__left">
                <div class="lk-row__left-media">
                    <img src="<?= htmlspecialchars($leftImageSources['src']) ?>" srcset="<?= htmlspecialchars($leftImageSources['srcset']) ?>" sizes="(max-width: 1024px) 100vw, 320px" alt="<?= htmlspecialchars($categoryTitle) ?>" loading="lazy" decoding="async">
                </div>
                <div>
                    <h3><?= htmlspecialchars($categoryTitle) ?></h3>
                    <p><?= htmlspecialchars($categoryDescription) ?></p>
                </div>
                <a class="lk-btn lk-btn--primary" href="/kategori/<?= htmlspecialchars($categorySlug) ?>">BUY NOW</a>
            </div>
            <div class="lk-row__right">
                <div class="lk-row__header">
                    <h3><?= htmlspecialchars($categoryTitle) ?></h3>
                    <a class="lk-listall" href="/kategori/<?= htmlspecialchars($categorySlug) ?>">
                        List All
                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3l5 5-5 5-1.4-1.4L7.2 8 4.6 5.4z"></path></svg>
                    </a>
                </div>
                <div class="lk-row__grid">
                    <?php foreach ($row['products'] as $product):
                        storefrontRenderProductCard($product, $cartToken, $currentUrl);
                    endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if (!empty(trim(strip_tags($aboutBlock['body_html'] ?? '')))): ?>
    <section class="lk-section">
        <div class="lk-container">
            <div class="lk-about">
                <h3><?= htmlspecialchars($aboutBlock['title'] ?? 'Lisansonay Hakkında') ?></h3>
                <div><?= $aboutBlock['body_html'] ?></div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php storefrontRenderFooter($company); ?>
<script src="/public/assets/js/app.js" defer></script>
<script src="/public/assets/js/home.js" defer></script>
</body>
</html>
