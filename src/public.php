<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin.php';

function fetchPublicSettings(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function fetchPublicCatalog(PDO $pdo): array
{
    ensureCategoryInfrastructure($pdo);

    $categories = fetchCategoryTree($pdo);
    $catalog = [];
    $subcategoryIndex = [];

    foreach ($categories as $category) {
        if (!($category['is_active'] ?? true)) {
            continue;
        }

        $categoryId = (int) $category['id'];
        $subcategories = $category['subcategories'] ?? [];
        $catalog[$categoryId] = [
            'id' => $categoryId,
            'slug' => $category['slug'],
            'title' => $category['title'],
            'description' => $category['description'],
            'emoji' => $category['emoji'],
            'icon' => $category['icon'] ?? null,
            'is_active' => (bool) ($category['is_active'] ?? true),
            'products' => [],
            'subcategories' => [],
        ];

        foreach ($subcategories as $sub) {
            if (!($sub['is_active'] ?? true)) {
                continue;
            }
            $subId = (int) $sub['id'];
            $catalog[$categoryId]['subcategories'][$subId] = [
                'id' => $subId,
                'category_id' => $categoryId,
                'slug' => $sub['slug'],
                'title' => $sub['title'],
                'description' => $sub['description'] ?? '',
                'icon' => $sub['icon'] ?? null,
                'sort_order' => (int)($sub['sort_order'] ?? 0),
                'is_active' => (bool) ($sub['is_active'] ?? true),
                'products' => [],
            ];
            $subcategoryIndex[$subId] = $categoryId;
        }
    }

    $hasSubcategory = tableHasColumn($pdo, 'products', 'subcategory_id');

    $hasSlug = tableHasColumn($pdo, 'products', 'slug');
    $hasStatus = tableHasColumn($pdo, 'products', 'status');
    $hasIsActive = tableHasColumn($pdo, 'products', 'is_active');
    $hasShortDescription = tableHasColumn($pdo, 'products', 'short_description');
    $hasStockStatus = tableHasColumn($pdo, 'products', 'stock_status');
    $hasPrimaryImage = tableHasColumn($pdo, 'products', 'primary_image_url');
    $hasBadgeJson = tableHasColumn($pdo, 'products', 'badge_json');
    $hasSlugColumn = $hasSlug ? 'p.slug' : "NULL AS slug";

    $selectParts = [
        'p.id',
        'p.category_id',
        'p.name',
        $hasSlug ? 'p.slug' : "NULL AS slug",
        'p.price_label',
        'p.price_cents',
        'p.note',
        'p.sort_order',
    ];
    if ($hasSubcategory) {
        $selectParts[] = 'p.subcategory_id';
    } else {
        $selectParts[] = 'NULL AS subcategory_id';
    }
    if ($hasStatus) {
        $selectParts[] = 'p.status';
    } else {
        $selectParts[] = "'published' AS status";
    }
    if ($hasIsActive) {
        $selectParts[] = 'p.is_active';
    } else {
        $selectParts[] = '1 AS is_active';
    }
    if ($hasShortDescription) {
        $selectParts[] = 'p.short_description';
    } else {
        $selectParts[] = 'NULL AS short_description';
    }
    if ($hasStockStatus) {
        $selectParts[] = 'p.stock_status';
    } else {
        $selectParts[] = "'in_stock' AS stock_status";
    }
    if ($hasPrimaryImage) {
        $selectParts[] = 'p.primary_image_url';
    } else {
        $selectParts[] = 'NULL AS primary_image_url';
    }
    if ($hasBadgeJson) {
        $selectParts[] = 'p.badge_json';
    } else {
        $selectParts[] = 'NULL AS badge_json';
    }

    $select = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p INNER JOIN categories c ON c.id = p.category_id';
    if ($hasSubcategory) {
        $select .= ' LEFT JOIN categories sc ON sc.id = p.subcategory_id';
        $select .= ' ORDER BY c.sort_order ASC, c.name ASC, sc.sort_order ASC, sc.name ASC, p.sort_order ASC, p.name ASC';
    } else {
        $select .= ' ORDER BY c.sort_order ASC, c.name ASC, p.sort_order ASC, p.name ASC';
    }

    $stmt = $pdo->query($select);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryId = (int) $row['category_id'];
        if (!isset($catalog[$categoryId])) {
            continue;
        }

        $status = $hasStatus ? (string) ($row['status'] ?? 'published') : 'published';
        $isActive = $hasIsActive ? (bool) $row['is_active'] : true;
        if ($status !== 'published' || !$isActive) {
            continue;
        }

        $priceLabel = trim((string) ($row['price_label'] ?? ''));
        $priceCents = (int) $row['price_cents'];
        if ($priceLabel === '') {
            $priceLabel = formatCurrency($priceCents);
        }

        $product = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $hasSlug ? (string) $row['slug'] : '',
            'price_label' => $priceLabel,
            'price_cents' => $priceCents,
            'note' => $row['note'],
            'sort_order' => (int) $row['sort_order'],
            'short_description' => $hasShortDescription ? ($row['short_description'] ?? null) : null,
            'stock_status' => $hasStockStatus ? ($row['stock_status'] ?? 'in_stock') : 'in_stock',
            'primary_image_url' => $hasPrimaryImage ? ($row['primary_image_url'] ?? null) : null,
            'badges' => $hasBadgeJson && !empty($row['badge_json']) ? (json_decode((string) $row['badge_json'], true) ?? []) : [],
        ];

        $subcategoryId = $hasSubcategory ? (int) $row['subcategory_id'] : 0;
        if ($subcategoryId > 0 && isset($subcategoryIndex[$subcategoryId])) {
            $parentCategoryId = $subcategoryIndex[$subcategoryId];
            if (isset($catalog[$parentCategoryId]['subcategories'][$subcategoryId]) && ($catalog[$parentCategoryId]['subcategories'][$subcategoryId]['is_active'] ?? true)) {
                $catalog[$parentCategoryId]['subcategories'][$subcategoryId]['products'][] = $product;
                continue;
            }
        }

        $catalog[$categoryId]['products'][] = $product;
    }

    foreach ($catalog as &$category) {
        $category['subcategories'] = array_values($category['subcategories']);
    }
    unset($category);

    return array_values($catalog);
}

function flattenCatalogProducts(array $catalog): array
{
    $products = [];
    foreach ($catalog as $category) {
        $categoryInfo = [
            'id' => $category['id'],
            'slug' => $category['slug'],
            'title' => $category['title'],
            'emoji' => $category['emoji'],
            'icon' => $category['icon'] ?? null,
        ];

        foreach ($category['products'] ?? [] as $product) {
            $product['category'] = $categoryInfo;
            $product['subcategory'] = null;
            $products[] = $product;
        }

        foreach ($category['subcategories'] ?? [] as $subcategory) {
            $subcategoryInfo = [
                'id' => $subcategory['id'],
                'slug' => $subcategory['slug'],
                'title' => $subcategory['title'],
                'icon' => $subcategory['icon'] ?? null,
            ];
            foreach ($subcategory['products'] ?? [] as $product) {
                $product['category'] = $categoryInfo;
                $product['subcategory'] = $subcategoryInfo;
                $products[] = $product;
            }
        }
    }

    return $products;
}

function buildHomePageViewModel(array $settings, array $catalog): array
{
    $hero = buildHomeHeroData($settings, $catalog);
    $bestSelling = buildBestSellingProducts($catalog);
    $rows = buildCategoryRows($catalog);
    $about = buildAboutBlock($settings);

    return [
        'hero' => $hero,
        'best_selling' => $bestSelling,
        'rows' => $rows,
        'about' => $about,
    ];
}

function buildHomeHeroData(array $settings, array $catalog): array
{
    $slides = [];
    $rightBanner = null;

    $rawSlides = [];
    if (!empty($settings['home_hero_json'])) {
        $decoded = json_decode((string) $settings['home_hero_json'], true);
        if (is_array($decoded)) {
            $rawSlides = $decoded;
        }
    }

    if (empty($rawSlides)) {
        foreach ($catalog as $category) {
            $slides[] = [
                'image' => $category['icon'] ?? '/public/assets/img/placeholder-hero.svg',
                'link' => '/kategori/' . ($category['slug'] ?? ''),
                'alt' => $category['title'] ?? 'Kategori',
            ];
            if (count($slides) >= 3) {
                break;
            }
        }
    } else {
        foreach ($rawSlides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $slides[] = [
                'image' => $slide['image'] ?? '/public/assets/img/placeholder-hero.svg',
                'link' => $slide['link'] ?? '#',
                'alt' => $slide['alt'] ?? 'Hero',
            ];
        }
    }

    if (empty($slides)) {
        $slides[] = [
            'image' => '/public/assets/img/placeholder-hero.svg',
            'link' => '#',
            'alt' => 'Lisansonay vitrin',
        ];
    }

    $rightBanner = [
        'image' => $settings['home_hero_banner_image'] ?? '/public/assets/img/placeholder-hero.svg',
        'link' => $settings['home_hero_banner_link'] ?? '#',
        'alt' => $settings['home_hero_banner_alt'] ?? 'Promosyon',
    ];

    return [
        'enabled' => true,
        'slides' => $slides,
        'rightBanner' => $rightBanner,
    ];
}

function buildBestSellingProducts(array $catalog): array
{
    $products = flattenCatalogProducts($catalog);
    usort($products, function (array $a, array $b): int {
        $scoreA = $a['sort_order'] ?? 0;
        $scoreB = $b['sort_order'] ?? 0;
        if ($scoreA === $scoreB) {
            return ($a['price_cents'] ?? 0) <=> ($b['price_cents'] ?? 0);
        }
        return $scoreA <=> $scoreB;
    });

    return array_slice($products, 0, 10);
}

function buildCategoryRows(array $catalog): array
{
    $rows = [];
    foreach ($catalog as $category) {
        $products = $category['products'] ?? [];
        if (empty($products)) {
            continue;
        }

        $rows[] = [
            'category' => $category,
            'products' => array_slice($products, 0, 5),
        ];
        if (count($rows) >= 6) {
            break;
        }
    }

    return $rows;
}

function buildAboutBlock(array $settings): array
{
    if (!empty($settings['home_about_json'])) {
        $decoded = json_decode((string) $settings['home_about_json'], true);
        if (is_array($decoded)) {
            return [
                'title' => (string) ($decoded['title'] ?? 'Lisansonay Market'),
                'body_html' => (string) ($decoded['body_html'] ?? ''),
            ];
        }
    }

    return [
        'title' => 'License Key Market - Güvenilir Lisans Mağazanız',
        'body_html' => '<p>Lisansonay olarak dijital ürün ve lisans ihtiyaçlarınız için güvenilir, hızlı ve müşteri odaklı çözümler sunuyoruz. Premium yazılım ve servis sağlayıcılarıyla olan iş birliklerimiz sayesinde en güncel ürünleri, özel kampanyaları ve satış sonrası desteği tek bir noktada birleştiriyoruz.</p>',
    ];
}

function findCategoryBySlug(array $catalog, string $slug): ?array
{
    foreach ($catalog as $category) {
        if (($category['slug'] ?? '') === $slug) {
            return $category;
        }
    }
    return null;
}

function findProductBySlugInCatalog(array $catalog, string $slug): ?array
{
    foreach ($catalog as $category) {
        foreach ($category['products'] ?? [] as $product) {
            if (($product['slug'] ?? '') === $slug) {
                $product['category'] = [
                    'id' => $category['id'] ?? null,
                    'slug' => $category['slug'] ?? null,
                    'title' => $category['title'] ?? null,
                ];
                return $product;
            }
        }
        foreach ($category['subcategories'] ?? [] as $sub) {
            foreach ($sub['products'] ?? [] as $product) {
                if (($product['slug'] ?? '') === $slug) {
                    $product['category'] = [
                        'id' => $sub['id'] ?? null,
                        'slug' => $sub['slug'] ?? null,
                        'title' => $sub['title'] ?? null,
                    ];
                    return $product;
                }
            }
        }
    }
    return null;
}

function fetchHomeBlocksForPublic(PDO $pdo, array $catalog, array $productMap): array
{
    $stmt = $pdo->query('SELECT id, type, category_id, title_override, product_limit, show_only_instock, pinned_product_ids, sort_order FROM home_blocks WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$rows) {
        return [];
    }

    $categoryMap = [];
    $subcategoryMap = [];
    foreach ($catalog as $category) {
        $categoryMap[(int) $category['id']] = $category;
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            $subcategoryMap[(int) $subcategory['id']] = ['subcategory' => $subcategory, 'category' => $category];
        }
    }

    $blocks = [];
    foreach ($rows as $row) {
        $type = strtolower((string) ($row['type'] ?? 'category'));
        if (!in_array($type, ['category', 'custom'], true)) {
            $type = 'category';
        }

        $productLimit = max(1, (int) ($row['product_limit'] ?? 6));
        $onlyInStock = (bool) ($row['show_only_instock'] ?? 0);
        $pinned = [];
        if (!empty($row['pinned_product_ids'])) {
            $decoded = json_decode((string) $row['pinned_product_ids'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $id) {
                    if (is_numeric($id)) {
                        $pinned[] = (int) $id;
                    }
                }
            }
        }

        $baseProducts = [];
        $viewSlug = '';
        $viewType = $type;
        $title = trim((string) ($row['title_override'] ?? ''));

        if ($type === 'category') {
            $targetId = (int) ($row['category_id'] ?? 0);
            if (isset($categoryMap[$targetId])) {
                $category = $categoryMap[$targetId];
                $baseProducts = $category['products'] ?? [];
                foreach ($category['subcategories'] ?? [] as $sub) {
                    foreach ($sub['products'] ?? [] as $product) {
                        $baseProducts[] = $product;
                    }
                }
                $viewSlug = $category['slug'];
                $viewType = 'category';
                if ($title === '') {
                    $title = $category['title'] ?? 'Kategori';
                }
            } elseif (isset($subcategoryMap[$targetId])) {
                $subInfo = $subcategoryMap[$targetId];
                $baseProducts = $subInfo['subcategory']['products'] ?? [];
                $viewSlug = $subInfo['subcategory']['slug'];
                $viewType = 'subcategory';
                if ($title === '') {
                    $title = $subInfo['subcategory']['title'] ?? 'Alt Kategori';
                }
            }
        }

        if ($type === 'custom' && empty($pinned)) {
            continue;
        }

        $orderedProducts = [];
        $seen = [];

        $addProduct = static function (array $product) use (&$orderedProducts, &$seen, $onlyInStock) {
            $stock = $product['stock_status'] ?? 'in_stock';
            if ($onlyInStock && $stock === 'out_of_stock') {
                return;
            }
            $productId = (int) $product['id'];
            if (isset($seen[$productId])) {
                return;
            }
            $seen[$productId] = true;
            $orderedProducts[] = $product;
        };

        foreach ($pinned as $productId) {
            if (isset($productMap[$productId])) {
                $addProduct($productMap[$productId]);
            }
        }

        if ($type === 'custom') {
            // custom bloklarda sadece sabitlenen ürünler gösterilir
        } else {
            foreach ($baseProducts as $product) {
                $productId = (int) ($product['id'] ?? 0);
                if ($productId > 0 && isset($productMap[$productId])) {
                    $addProduct($productMap[$productId]);
                }
            }
        }

        if (empty($orderedProducts)) {
            continue;
        }

        $orderedProducts = array_slice($orderedProducts, 0, $productLimit);

        $blocks[] = [
            'id' => (int) $row['id'],
            'title' => $title !== '' ? $title : 'Öne Çıkanlar',
            'type' => $type,
            'view_slug' => $viewSlug,
            'view_type' => $viewType,
            'products' => $orderedProducts,
        ];
    }

    return $blocks;
}

function loadBrandingSettings(?PDO $pdo): array
{
    if ($pdo instanceof PDO) {
        try {
            return fetchPublicSettings($pdo);
        } catch (Throwable $brandingException) {
            // ignore and fall back to defaults below
        }
    }

    return [];
}

function loadCategoryMenu(?PDO $pdo): array
{
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        $catalog = fetchPublicCatalog($pdo);
        return buildCategoryMenuEntries($catalog);
    } catch (Throwable $categoryException) {
        return [];
    }
}

