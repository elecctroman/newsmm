<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/public.php';
require_once __DIR__ . '/src/customer.php';
require_once __DIR__ . '/src/storefront_components.php';

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
$category = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchPublicSettings($pdo);
        $catalog = fetchPublicCatalog($pdo);
        $category = findCategoryBySlug($catalog, $requestSlug);
    } catch (Throwable $e) {
        $category = null;
    }
}

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
$sort = $_GET['sort'] ?? 'popular';
$sortOptions = [
    'popular' => 'Popüler',
    'new' => 'Yeni Eklenenler',
    'price_asc' => 'Fiyat (Artan)',
    'price_desc' => 'Fiyat (Azalan)',
];

switch ($sort) {
    case 'price_asc':
        usort($products, static function (array $a, array $b): int {
            return (int)($a['price_cents'] ?? 0) <=> (int)($b['price_cents'] ?? 0);
        });
        break;
    case 'price_desc':
        usort($products, static function (array $a, array $b): int {
            return (int)($b['price_cents'] ?? 0) <=> (int)($a['price_cents'] ?? 0);
        });
        break;
    case 'new':
        usort($products, static function (array $a, array $b): int {
            return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
        });
        break;
    default:
        usort($products, static function (array $a, array $b): int {
            $orderA = (int)($a['sort_order'] ?? 0);
            $orderB = (int)($b['sort_order'] ?? 0);
            return $orderA <=> $orderB;
        });
}

$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 24;
$totalProducts = count($products);
$totalPages = max(1, (int)ceil($totalProducts / $pageSize));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $pageSize;
$pagedProducts = array_slice($products, $offset, $pageSize);

$currentUrl = $_SERVER['REQUEST_URI'] ?? '/kategori/' . $requestSlug;
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

$categoryImage = storefrontImageSources([
    'lg' => $category['hero_image'] ?? $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
    'md' => $category['hero_image'] ?? $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
    'sm' => $category['hero_image'] ?? $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
    'alt' => $category['title'] ?? 'Kategori',
], '/public/assets/img/placeholder-hero.svg');
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
            <div class="lk-category-hero">
                <div class="lk-category-hero__media">
                    <img src="<?= htmlspecialchars($categoryImage['src']) ?>" srcset="<?= htmlspecialchars($categoryImage['srcset']) ?>" sizes="(max-width: 1024px) 100vw, 320px" alt="<?= htmlspecialchars($category['title'] ?? 'Kategori') ?>" loading="lazy" decoding="async">
                </div>
                <div>
                    <h1><?= htmlspecialchars($category['title'] ?? 'Kategori') ?></h1>
                    <p><?= htmlspecialchars($category['description'] ?? 'Kategori ürünleri') ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="lk-section lk-section--compact">
        <div class="lk-container">
            <div class="lk-sortbar">
                <span><?= $totalProducts ?> ürün listelendi</span>
                <form method="get">
                    <?php foreach ($_GET as $key => $value): if ($key === 'sort') { continue; } ?>
                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars((string) $value) ?>">
                    <?php endforeach; ?>
                    <label for="sort-select">Sırala:</label>
                    <select id="sort-select" name="sort" onchange="this.form.submit()">
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="lk-products-grid">
                <?php foreach ($pagedProducts as $product):
                    storefrontRenderProductCard($product, $cartToken, $currentUrl);
                endforeach; ?>
            </div>
            <?php if ($totalPages > 1):
                $queryBase = $_GET;
                unset($queryBase['page']);
            ?>
            <nav class="lk-pagination" aria-label="Sayfalama">
                <?php for ($i = 1; $i <= $totalPages; $i++):
                    $query = $queryBase;
                    if ($i > 1) {
                        $query['page'] = (string) $i;
                    }
                    $url = '/kategori/' . urlencode($requestSlug);
                    if (!empty($query)) {
                        $url .= '?' . http_build_query($query);
                    }
                ?>
                <?php if ($i === $page): ?>
                    <span class="is-active" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= $i ?></a>
                <?php endif; ?>
                <?php endfor; ?>
            </nav>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php storefrontRenderFooter($company); ?>
<script src="/public/assets/js/app.js" defer></script>
<script src="/public/assets/js/home.js" defer></script>
</body>
</html>
