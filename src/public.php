<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

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
    $sql = 'SELECT c.id AS category_id, c.slug AS category_slug, c.title AS category_title, c.description AS category_description, '
        . 'c.emoji AS category_emoji, c.sort_order AS category_order, '
        . 'p.id AS product_id, p.name AS product_name, p.price_label, p.price_cents, p.note AS product_note, '
        . 'p.sort_order AS product_order '
        . 'FROM categories c '
        . 'LEFT JOIN products p ON p.category_id = c.id '
        . 'ORDER BY c.sort_order ASC, c.title ASC, p.sort_order ASC, p.name ASC';

    $stmt = $pdo->query($sql);

    $catalog = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryId = (int) $row['category_id'];
        if (!isset($catalog[$categoryId])) {
            $catalog[$categoryId] = [
                'id' => $categoryId,
                'slug' => $row['category_slug'],
                'title' => $row['category_title'],
                'description' => $row['category_description'],
                'emoji' => $row['category_emoji'],
                'products' => [],
            ];
        }

        if ($row['product_id'] !== null) {
            $priceLabel = trim((string) ($row['price_label'] ?? ''));
            $priceCents = (int) $row['price_cents'];
            if ($priceLabel === '') {
                $priceLabel = formatCurrency($priceCents);
            }

            $catalog[$categoryId]['products'][] = [
                'id' => (int) $row['product_id'],
                'name' => $row['product_name'],
                'price_label' => $priceLabel,
                'price_cents' => $priceCents,
                'note' => $row['product_note'],
            ];
        }
    }

    return array_values($catalog);
}

function flattenCatalogProducts(array $catalog): array
{
    $products = [];
    foreach ($catalog as $category) {
        if (!isset($category['products']) || !is_array($category['products'])) {
            continue;
        }
        foreach ($category['products'] as $product) {
            $product['category'] = [
                'id' => $category['id'],
                'slug' => $category['slug'],
                'title' => $category['title'],
                'emoji' => $category['emoji'],
            ];
            $products[] = $product;
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

