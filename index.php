<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';

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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <link rel="stylesheet" href="/public/assets/css/home.css">
    <link rel="preload" as="image" href="<?= htmlspecialchars($hero['slides'][0]['image'] ?? '/public/assets/img/placeholder-hero.svg') ?>">
</head>
<body class="home">
<header>
    <div class="home-container" style="padding-top:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:24px;">
            <a href="/" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;">
                <img src="<?= htmlspecialchars($logoLight) ?>" alt="Lisansonay" style="width:48px;height:48px;border-radius:12px;object-fit:cover;">
                <div>
                    <strong style="font-size:20px;display:block;">Lisansonay</strong>
                    <span style="color:#475569;font-size:14px;">Lisans Marketi</span>
                </div>
            </a>
            <div style="flex:1;max-width:420px;">
                <form action="/ara" method="get" style="display:flex;background:#fff;border-radius:14px;box-shadow:var(--home-shadow);overflow:hidden;">
                    <input type="search" name="q" placeholder="Ürün ara" style="flex:1;border:none;padding:14px 16px;font-size:14px;">
                    <button type="submit" style="padding:0 18px;background:var(--home-accent);color:#fff;border:none;font-weight:600;">Ara</button>
                </form>
            </div>
            <nav style="display:flex;align-items:center;gap:16px;">
                <a href="/login.php" style="text-decoration:none;color:var(--home-text);font-weight:600;">Giriş Yap</a>
                <a href="/register.php" style="text-decoration:none;color:var(--home-accent);font-weight:600;">Kayıt Ol</a>
                <a href="/account/cart.php" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;background:var(--home-surface);box-shadow:var(--home-shadow);text-decoration:none;">
                    🛒
                    <?php if ($cartCount > 0): ?>
                        <span style="position:absolute;top:-6px;right:-6px;background:var(--home-accent);color:#fff;border-radius:999px;padding:4px 8px;font-size:11px;min-width:20px;text-align:center;"><?= (int)$cartCount ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
        <div style="display:flex;align-items:center;gap:24px;margin-top:20px;font-size:14px;font-weight:600;color:#475569;">
            <span>Categories</span>
            <a href="/" style="color:inherit;text-decoration:none;">Home</a>
            <a href="/about" style="color:inherit;text-decoration:none;">About</a>
            <a href="/contact" style="color:inherit;text-decoration:none;">Contact</a>
            <a href="/blog" style="color:inherit;text-decoration:none;">Blog</a>
        </div>
    </div>
</header>

<main class="home-container">
    <?php if ($catalogError !== ''): ?>
        <div style="background:#fee2e2;border-radius:16px;padding:16px;color:#991b1b;">
            <?= htmlspecialchars($catalogError) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($hero['enabled'])): ?>
    <section class="home-hero" id="hero">
        <div class="hero-carousel" data-autoplay="true">
            <button class="hero-nav prev" aria-label="Önceki">‹</button>
            <div class="hero-track" aria-live="polite">
                <?php foreach ($hero['slides'] as $index => $slide): ?>
                    <a class="hero-slide" href="<?= htmlspecialchars($slide['link'] ?? '#') ?>" id="hero-slide-<?= $index ?>">
                        <img src="<?= htmlspecialchars($slide['image'] ?? '/public/assets/img/placeholder-hero.svg') ?>" alt="<?= htmlspecialchars($slide['alt'] ?? 'Hero') ?>" loading="lazy" decoding="async">
                    </a>
                <?php endforeach; ?>
            </div>
            <button class="hero-nav next" aria-label="Sonraki">›</button>
            <div class="hero-dots" role="tablist"></div>
        </div>
        <a class="hero-banner" href="<?= htmlspecialchars($hero['rightBanner']['link'] ?? '#') ?>">
            <img src="<?= htmlspecialchars($hero['rightBanner']['image'] ?? '/public/assets/img/placeholder-hero.svg') ?>" alt="<?= htmlspecialchars($hero['rightBanner']['alt'] ?? 'Promosyon') ?>" loading="lazy" decoding="async">
        </a>
    </section>
    <?php endif; ?>

    <section class="home-section" id="best-selling">
        <div class="section-heading">
            <h2>Best Selling Licenses</h2>
            <p>En popüler lisans ürünleri</p>
        </div>
        <div class="product-grid">
            <?php foreach ($bestSelling as $product): ?>
                <article class="product-card" data-product="<?= (int)($product['id'] ?? 0) ?>">
                    <a href="/urun/<?= htmlspecialchars($product['slug'] ?? (string)($product['id'] ?? '')) ?>">
                        <figure>
                            <img src="<?= htmlspecialchars($product['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg') ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Ürün') ?>" loading="lazy" decoding="async">
                        </figure>
                    </a>
                    <h3><a href="/urun/<?= htmlspecialchars($product['slug'] ?? (string)($product['id'] ?? '')) ?>" style="text-decoration:none;color:inherit;">
                        <?= htmlspecialchars($product['name'] ?? 'Ürün') ?>
                    </a></h3>
                    <div class="product-meta">
                        <span><?= htmlspecialchars($product['price_label'] ?? formatCurrency((int)($product['price_cents'] ?? 0))) ?></span>
                        <span><?= htmlspecialchars($product['stock_status'] ?? 'in_stock') === 'in_stock' ? 'Stokta' : 'Tükendi' ?></span>
                    </div>
                    <form method="post" action="/cart.php">
                        <input type="hidden" name="form_key" value="cart-action">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>">
                        <input type="hidden" name="cart_action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int)($product['id'] ?? 0) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                        <button type="submit">Add To Cart</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php foreach ($categoryRows as $row): $category = $row['category']; ?>
        <section class="home-section category-row" id="row-<?= htmlspecialchars($category['slug'] ?? 'kategori') ?>">
            <div class="category-tile">
                <?php if (!empty($category['icon'])): ?>
                    <img src="<?= htmlspecialchars($category['icon']) ?>" alt="<?= htmlspecialchars($category['title'] ?? 'Kategori') ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <img src="/public/assets/img/placeholder-hero.svg" alt="<?= htmlspecialchars($category['title'] ?? 'Kategori') ?>" loading="lazy" decoding="async">
                <?php endif; ?>
                <div>
                    <h3><?= htmlspecialchars($category['title'] ?? 'Kategori') ?></h3>
                    <p style="color:var(--home-muted);font-size:14px;">En çok tercih edilen <?= htmlspecialchars($category['title'] ?? '') ?> ürünleri</p>
                </div>
                <a href="/kategori/<?= htmlspecialchars($category['slug'] ?? '') ?>">BUY NOW</a>
            </div>
            <div>
                <div class="row-heading">
                    <h3 style="font-size:22px;font-weight:700;"><?= htmlspecialchars($category['title'] ?? 'Kategori') ?></h3>
                    <a href="/kategori/<?= htmlspecialchars($category['slug'] ?? '') ?>">List All ›</a>
                </div>
                <div class="row-grid">
                    <?php foreach ($row['products'] as $product): ?>
                        <article class="product-card" data-product="<?= (int)($product['id'] ?? 0) ?>">
                            <a href="/urun/<?= htmlspecialchars($product['slug'] ?? (string)($product['id'] ?? '')) ?>">
                                <figure>
                                    <img src="<?= htmlspecialchars($product['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg') ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Ürün') ?>" loading="lazy" decoding="async">
                                </figure>
                            </a>
                            <h3><a href="/urun/<?= htmlspecialchars($product['slug'] ?? (string)($product['id'] ?? '')) ?>" style="text-decoration:none;color:inherit;">
                                <?= htmlspecialchars($product['name'] ?? 'Ürün') ?>
                            </a></h3>
                            <div class="product-meta">
                                <span><?= htmlspecialchars($product['price_label'] ?? formatCurrency((int)($product['price_cents'] ?? 0))) ?></span>
                                <span><?= htmlspecialchars($product['stock_status'] ?? 'in_stock') === 'in_stock' ? 'Stokta' : 'Tükendi' ?></span>
                            </div>
                            <form method="post" action="/cart.php">
                                <input type="hidden" name="form_key" value="cart-action">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>">
                                <input type="hidden" name="cart_action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int)($product['id'] ?? 0) ?>">
                                <input type="hidden" name="quantity" value="1">
                                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                                <button type="submit">Add To Cart</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="home-section">
        <div class="about-block">
            <h2 style="font-size:26px;font-weight:700;margin-bottom:16px;"><?= htmlspecialchars($aboutBlock['title'] ?? 'Lisansonay Hakkında') ?></h2>
            <div><?= $aboutBlock['body_html'] ?></div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="footer-inner">
        <div>
            <strong style="font-size:18px;display:block;margin-bottom:12px;">Lisansonay</strong>
            <p style="opacity:0.8;font-size:14px;">Dijital lisans ve abonelik pazarınız.</p>
        </div>
        <div>
            <h4 style="font-size:16px;margin-bottom:12px;">Links</h4>
            <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;">
                <li><a href="/">Home</a></li>
                <li><a href="/about">About</a></li>
                <li><a href="/contact">Contact</a></li>
                <li><a href="/blog">Blog</a></li>
            </ul>
        </div>
        <div>
            <h4 style="font-size:16px;margin-bottom:12px;">Contact</h4>
            <p style="opacity:0.8;font-size:14px;"><?= htmlspecialchars($company['support_email'] ?: 'destek@lisansonay.com') ?></p>
            <p style="opacity:0.8;font-size:14px;"><?= htmlspecialchars($company['support_phone'] ?: '+90 555 000 00 00') ?></p>
        </div>
    </div>
    <p style="text-align:center;margin-top:24px;font-size:13px;opacity:0.6;">© <?= date('Y') ?> Lisansonay. Tüm hakları saklıdır.</p>
</footer>

<script src="/public/assets/js/home.js" defer></script>
</body>
</html>
