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

    $select = 'SELECT p.id, p.category_id, p.name, p.price_label, p.price_cents, p.note, p.sort_order';
    if ($hasSubcategory) {
        $select .= ', p.subcategory_id';
    } else {
        $select .= ', NULL AS subcategory_id';
    }
    $select .= ' FROM products p INNER JOIN categories c ON c.id = p.category_id';
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

        $priceLabel = trim((string) ($row['price_label'] ?? ''));
        $priceCents = (int) $row['price_cents'];
        if ($priceLabel === '') {
            $priceLabel = formatCurrency($priceCents);
        }

        $product = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'price_label' => $priceLabel,
            'price_cents' => $priceCents,
            'note' => $row['note'],
            'sort_order' => (int) $row['sort_order'],
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

