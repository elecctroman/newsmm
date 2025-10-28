<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';

ensureSession();

$productSlug = $slug ?? '';
if ($productSlug === '' && isset($_GET['slug'])) {
    $productSlug = (string) $_GET['slug'];
}
$productSlug = trim($productSlug, '/');
if ($productSlug === '') {
    redirect('/');
}

$customer = getCurrentCustomer();
$cartCount = getActiveCartCount($pdo ?? null, $customer);
$cartToken = issueCsrfToken('cart-action');

$settings = [];
$catalog = [];
$product = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchPublicSettings($pdo);
        $catalog = fetchPublicCatalog($pdo);
        $product = findProductBySlugInCatalog($catalog, $productSlug);
    } catch (Throwable $e) {
        $product = null;
    }
}

if (!$product) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ürün bulunamadı</title></head><body><p>Ürün bulunamadı.</p></body></html>';
    exit;
}

$company = buildBrandingContext($settings);
$metaTitle = ($product['name'] ?? 'Ürün') . ' | Lisansonay';
$metaDescription = substr(strip_tags((string)($product['note'] ?? '')), 0, 160);
$logoLight = $company['logo_light'] ?: '/public/assets/img/placeholder-hero.svg';
$related = [];

if (!empty($product['category']['slug'])) {
    $category = findCategoryBySlug($catalog, $product['category']['slug']);
    if ($category) {
        foreach ($category['products'] ?? [] as $candidate) {
            if (($candidate['slug'] ?? '') === $productSlug) {
                continue;
            }
            $related[] = $candidate;
            if (count($related) >= 8) {
                break;
            }
        }
    }
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
    <section class="home-section" style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">
        <div>
            <figure style="background:#fff;border-radius:16px;box-shadow:var(--home-shadow);padding:16px;">
                <img src="<?= htmlspecialchars($product['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg') ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Ürün') ?>" style="width:100%;border-radius:12px;object-fit:cover;" loading="lazy" decoding="async">
            </figure>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <span style="color:var(--home-accent);font-weight:600;">Kategori: <a href="/kategori/<?= htmlspecialchars($product['category']['slug'] ?? '') ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($product['category']['title'] ?? '') ?></a></span>
            <h1 style="font-size:30px;font-weight:700;">
                <?= htmlspecialchars($product['name'] ?? 'Ürün') ?>
            </h1>
            <p style="color:var(--home-muted);line-height:1.6;">
                <?= htmlspecialchars($product['note'] ?? 'Dijital lisans ve abonelik hizmeti.') ?>
            </p>
            <div style="display:flex;align-items:center;gap:20px;font-size:24px;font-weight:700;">
                <span><?= htmlspecialchars($product['price_label'] ?? formatCurrency((int)($product['price_cents'] ?? 0))) ?></span>
            </div>
            <form method="post" action="/cart.php" style="display:flex;gap:12px;align-items:center;">
                <input type="hidden" name="form_key" value="cart-action">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>">
                <input type="hidden" name="cart_action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)($product['id'] ?? 0) ?>">
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                <button type="submit" style="border:none;border-radius:12px;background:var(--home-accent);color:#fff;padding:14px 22px;font-weight:600;cursor:pointer;">Add To Cart</button>
            </form>
        </div>
    </section>

    <?php if (!empty($related)): ?>
    <section class="home-section">
        <div class="section-heading">
            <h2>Related Products</h2>
            <a href="/kategori/<?= htmlspecialchars($product['category']['slug'] ?? '') ?>" style="color:var(--home-accent);text-decoration:none;">List All ›</a>
        </div>
        <div class="row-grid">
            <?php foreach ($related as $relatedProduct): ?>
                <article class="product-card" data-product="<?= (int)($relatedProduct['id'] ?? 0) ?>">
                    <a href="/urun/<?= htmlspecialchars($relatedProduct['slug'] ?? (string)($relatedProduct['id'] ?? '')) ?>">
                        <figure>
                            <img src="<?= htmlspecialchars($relatedProduct['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg') ?>" alt="<?= htmlspecialchars($relatedProduct['name'] ?? 'Ürün') ?>" loading="lazy" decoding="async">
                        </figure>
                    </a>
                    <h3><a href="/urun/<?= htmlspecialchars($relatedProduct['slug'] ?? (string)($relatedProduct['id'] ?? '')) ?>" style="text-decoration:none;color:inherit;">
                        <?= htmlspecialchars($relatedProduct['name'] ?? 'Ürün') ?>
                    </a></h3>
                    <div class="product-meta">
                        <span><?= htmlspecialchars($relatedProduct['price_label'] ?? formatCurrency((int)($relatedProduct['price_cents'] ?? 0))) ?></span>
                        <span><?= htmlspecialchars($relatedProduct['stock_status'] ?? 'in_stock') === 'in_stock' ? 'Stokta' : 'Tükendi' ?></span>
                    </div>
                    <form method="post" action="/cart.php">
                        <input type="hidden" name="form_key" value="cart-action">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>">
                        <input type="hidden" name="cart_action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int)($relatedProduct['id'] ?? 0) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                        <button type="submit">Add To Cart</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
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
