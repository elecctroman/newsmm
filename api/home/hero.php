<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/session.php';
require __DIR__ . '/../../src/public.php';
require_once __DIR__ . '/../../src/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60, s-maxage=60');

ensureSession();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function respondHero(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'GET') {
    respondHero(405, ['error' => 'Yalnızca GET isteği desteklenmektedir.']);
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    respondHero(500, ['error' => 'Veritabanı bağlantısı kurulamadı.']);
}

$cacheTtl = 60;
$cached = loadHeroCache($cacheTtl);
if (is_array($cached)) {
    respondHero(200, $cached);
}

try {
    ensureHeroInfrastructure($pdo);
    $settings = fetchPublicSettings($pdo);
    $layout = fetchHeroLayout($pdo, $settings);
} catch (Throwable $e) {
    respondHero(500, ['error' => 'Hero verileri yüklenemedi: ' . $e->getMessage()]);
}

$leftBanner = $layout['left_banner'] ?? null;
$rightBanner = $layout['right_banner'] ?? null;
$slides = $layout['slides'] ?? [];
$strip = $layout['strip'] ?? [];
$heroSettings = $layout['settings'] ?? [];

$mapBanner = static function (?array $banner): array {
    if (!$banner || empty($banner['image_url'])) {
        return [
            'image' => null,
            'link' => null,
            'alt' => null,
            'visible' => false,
        ];
    }

    return [
        'image' => (string) $banner['image_url'],
        'link' => isset($banner['link_url']) && $banner['link_url'] !== '' ? (string) $banner['link_url'] : null,
        'alt' => isset($banner['alt_text']) && $banner['alt_text'] !== '' ? (string) $banner['alt_text'] : null,
        'visible' => true,
    ];
};

$mapSlide = static function (array $slide): array {
    return [
        'image' => (string) $slide['image_url'],
        'link' => isset($slide['link_url']) && $slide['link_url'] !== '' ? (string) $slide['link_url'] : null,
        'alt' => isset($slide['alt_text']) && $slide['alt_text'] !== '' ? (string) $slide['alt_text'] : null,
    ];
};

$mapStrip = static function (array $item): array {
    return [
        'image' => (string) $item['image_url'],
        'title' => (string) $item['title'],
        'link' => (string) $item['link_url'],
    ];
};

$payload = [
    'leftBanner' => $mapBanner($leftBanner),
    'mainSlides' => array_values(array_map($mapSlide, $slides)),
    'rightBanner' => $mapBanner($rightBanner),
    'miniStrip' => array_values(array_map($mapStrip, $strip)),
    'settings' => [
        'autoplayMs' => (int) ($heroSettings['autoplay_ms'] ?? 4000),
        'autoplay' => !empty($heroSettings['autoplay']),
        'showDots' => !empty($heroSettings['show_dots']),
        'showArrows' => !empty($heroSettings['show_arrows']),
    ],
];

saveHeroCache($payload);

respondHero(200, $payload);
