<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';
require_once __DIR__ . '/src/storefront_components.php';

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

$navCategories = [];
foreach ($catalog as $navCategory) {
    $slugItem = (string) ($navCategory['slug'] ?? '');
    if ($slugItem === '') {
        continue;
    }
    $navCategories[] = [
        'slug' => $slugItem,
        'title' => $navCategory['title'] ?? 'Kategori',
    ];
    if (count($navCategories) >= 8) {
        break;
    }
}

$productImage = storefrontImageSources([
    'lg' => $product['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg',
    'md' => $product['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg',
    'sm' => $product['primary_image_url'] ?? '/public/assets/img/placeholder-hero.svg',
    'alt' => $product['name'] ?? 'Ürün',
], '/public/assets/img/placeholder-hero.svg');

$priceLabel = (string) ($product['price_label'] ?? formatCurrency((int)($product['price_cents'] ?? 0)));
$stockStatus = (string) ($product['stock_status'] ?? 'in_stock');
$descriptionHtml = $product['description_html'] ?? null;
if (empty($descriptionHtml)) {
    $descriptionHtml = '<p>' . htmlspecialchars($product['note'] ?? 'Dijital lisans ve abonelik hizmeti.', ENT_QUOTES) . '</p>';
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
<body class="lk-page">
<?php storefrontRenderHeader($company, $cartCount, $customer, $navCategories, $logoLight); ?>
<main class="lk-main lk-main--no-hero">
    <section class="lk-section lk-section--first">
        <div class="lk-container">
            <div class="lk-product">
                <div class="lk-product__gallery">
                    <div class="lk-product__image">
                        <img src="<?= htmlspecialchars($productImage['src']) ?>" srcset="<?= htmlspecialchars($productImage['srcset']) ?>" sizes="(max-width: 1024px) 100vw, 520px" alt="<?= htmlspecialchars($product['name'] ?? 'Ürün') ?>" loading="lazy" decoding="async">
                    </div>
                </div>
                <div class="lk-product__summary">
                    <a class="lk-product__category" href="/kategori/<?= htmlspecialchars($product['category']['slug'] ?? '') ?>">
                        <?= htmlspecialchars($product['category']['title'] ?? 'Kategori') ?>
                    </a>
                    <h1 class="lk-product__title"><?= htmlspecialchars($product['name'] ?? 'Ürün') ?></h1>
                    <div class="lk-product__price">
                        <span><?= htmlspecialchars($priceLabel) ?></span>
                    </div>
                    <p class="lk-product__description"><?= htmlspecialchars($product['note'] ?? 'Dijital lisans ve abonelik hizmeti.') ?></p>
                    <span class="lk-stock <?= $stockStatus === 'in_stock' ? 'lk-stock--in' : '' ?>">
                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6.173 14.727a1 1 0 01-1.414 0l-3.486-3.486a1 1 0 111.414-1.414l2.779 2.779 6.364-6.364a1 1 0 111.414 1.414l-7.071 7.071z"></path></svg>
                        <?= $stockStatus === 'in_stock' ? 'Stokta' : 'Stokta değil' ?>
                    </span>
                    <form method="post" action="/cart.php">
                        <input type="hidden" name="form_key" value="cart-action">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>">
                        <input type="hidden" name="cart_action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int)($product['id'] ?? 0) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                        <button type="submit" class="lk-btn lk-btn--primary">Sepete Ekle</button>
                    </form>
                </div>
            </div>

            <div class="lk-tabs" data-tabs>
                <div class="lk-tabs__list" role="tablist">
                    <button class="lk-tabs__button is-active" role="tab" aria-selected="true" data-tab-target="description">Açıklama</button>
                    <button class="lk-tabs__button" role="tab" aria-selected="false" data-tab-target="related">Benzer Ürünler</button>
                </div>
                <div class="lk-tabs__panel is-active" id="tab-description" role="tabpanel">
                    <?= $descriptionHtml ?>
                </div>
                <div class="lk-tabs__panel" id="tab-related" role="tabpanel">
                    <?php if (!empty($related)): ?>
                        <div class="lk-row__grid" style="margin-top: var(--lk-spacing-lg);">
                            <?php foreach ($related as $relatedProduct):
                                storefrontRenderProductCard($relatedProduct, $cartToken, $_SERVER['REQUEST_URI'] ?? '/');
                            endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--lk-muted);">Benzer ürün bulunamadı.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php storefrontRenderFooter($company); ?>
<script src="/public/assets/js/app.js" defer></script>
<script src="/public/assets/js/home.js" defer></script>
</body>
</html>
