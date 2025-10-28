<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';

ensureSession();

$requestSlug = $slug ?? '';
if ($requestSlug === '' && isset($_GET['slug'])) {
    $requestSlug = (string) $_GET['slug'];
}
$requestSlug = trim($requestSlug, '/');
if ($requestSlug === '') {
    redirect('/');
}

$customer = getCurrentCustomer();
$cartCount = getActiveCartCount($pdo ?? null, $customer);
$cartToken = issueCsrfToken('cart-action');

$settings = [];
$catalog = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchPublicSettings($pdo);
        $catalog = fetchPublicCatalog($pdo);
    } catch (Throwable $e) {
        $catalog = [];
    }
}

$category = findCategoryBySlug($catalog, $requestSlug);
if (!$category) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Kategori bulunamadı</title></head><body><p>Kategori bulunamadı.</p></body></html>';
    exit;
}

$company = buildBrandingContext($settings);
$metaTitle = ($category['title'] ?? 'Kategori') . ' | Lisansonay';
$metaDescription = substr(strip_tags((string)($category['description'] ?? '')), 0, 160);
$logoLight = $company['logo_light'] ?: '/public/assets/img/placeholder-hero.svg';
$products = $category['products'] ?? [];

usort($products, function (array $a, array $b): int {
    return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
});
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <link rel="stylesheet" href="/public/assets/css/home.css">
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
                <a href="/account/cart.php" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;background:var(--home-surface);box-shadow:var(--home-shadow);text-decoration:none;">
                    🛒
                    <?php if ($cartCount > 0): ?>
                        <span style="position:absolute;top:-6px;right:-6px;background:var(--home-accent);color:#fff;border-radius:999px;padding:4px 8px;font-size:11px;min-width:20px;text-align:center;"><?= (int)$cartCount ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </div>
</header>

<main class="home-container">
    <section class="home-section">
        <div class="section-heading">
            <h1><?= htmlspecialchars($category['title'] ?? 'Kategori') ?></h1>
            <p><?= count($products) ?> ürün listelendi</p>
        </div>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
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
                <li><a href="/contact">Contact</a></li>
                <li><a href="/blog">Blog</a></li>
            </ul>
        </div>
    </div>
</footer>

<script src="/public/assets/js/home.js" defer></script>
</body>
</html>
