<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
$user = requireAuth();
requirePermission($user, 'content.home.manage');
$flashes = getFlashes();

$pageTitle = 'Vitrin Yönetimi';
$activeNav = 'content';
$currentUser = $user;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['_token'] ?? '';

    $tokens = [
        'create_hero_banner' => 'hero-banner-actions',
        'update_hero_banner' => 'hero-banner-actions',
        'delete_hero_banner' => 'hero-banner-actions',
        'reorder_hero_banners' => 'hero-banner-actions',
        'create_hero_slide' => 'hero-slide-actions',
        'update_hero_slide' => 'hero-slide-actions',
        'delete_hero_slide' => 'hero-slide-actions',
        'reorder_hero_slides' => 'hero-slide-actions',
        'create_hero_strip' => 'hero-strip-actions',
        'update_hero_strip' => 'hero-strip-actions',
        'delete_hero_strip' => 'hero-strip-actions',
        'reorder_hero_strip' => 'hero-strip-actions',
        'update_hero_settings' => 'hero-settings-actions',
        'create_block' => 'block-actions',
        'update_block' => 'block-actions',
        'delete_block' => 'block-actions',
    ];

    if (!isset($tokens[$action]) || !validateCsrfToken($tokens[$action], $token)) {
        $errors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $errors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        try {
            switch ($action) {
                case 'create_hero_banner':
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Banner görseli zorunludur.';
                        break;
                    }
                    createHeroBanner($pdo, $_POST);
                    addFlash('success', 'Hero banner eklendi.');
                    redirect('content.php#hero-banners');
                    break;
                case 'update_hero_banner':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Geçersiz banner kaydı.';
                        break;
                    }
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Banner görseli zorunludur.';
                        break;
                    }
                    updateHeroBanner($pdo, $id, $_POST);
                    addFlash('success', 'Hero banner güncellendi.');
                    redirect('content.php#hero-banners');
                    break;
                case 'delete_hero_banner':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        deleteHeroBanner($pdo, $id);
                        addFlash('success', 'Hero banner silindi.');
                        redirect('content.php#hero-banners');
                    }
                    break;
                case 'reorder_hero_banners':
                    $side = (string) ($_POST['side'] ?? 'left');
                    $orderPayload = (string) ($_POST['order'] ?? '');
                    $ids = array_filter(array_map('trim', explode(',', $orderPayload)), static fn($value) => $value !== '');
                    reorderHeroBanners($pdo, $side, $ids);
                    addFlash('success', 'Banner sırası güncellendi.');
                    redirect('content.php#hero-banners');
                    break;
                case 'create_hero_slide':
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Slide görseli zorunludur.';
                        break;
                    }
                    createHeroSlide($pdo, $_POST);
                    addFlash('success', 'Hero slayt eklendi.');
                    redirect('content.php#hero-carousel');
                    break;
                case 'update_hero_slide':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Geçersiz slayt kaydı.';
                        break;
                    }
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Slide görseli zorunludur.';
                        break;
                    }
                    updateHeroSlide($pdo, $id, $_POST);
                    addFlash('success', 'Hero slayt güncellendi.');
                    redirect('content.php#hero-carousel');
                    break;
                case 'delete_hero_slide':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        deleteHeroSlide($pdo, $id);
                        addFlash('success', 'Hero slayt silindi.');
                        redirect('content.php#hero-carousel');
                    }
                    break;
                case 'reorder_hero_slides':
                    $orderPayload = (string) ($_POST['order'] ?? '');
                    $ids = array_filter(array_map('trim', explode(',', $orderPayload)), static fn($value) => $value !== '');
                    reorderHeroSlides($pdo, $ids);
                    addFlash('success', 'Slayt sırası güncellendi.');
                    redirect('content.php#hero-carousel');
                    break;
                case 'create_hero_strip':
                    if (!isset($_POST['title']) || trim((string) $_POST['title']) === '') {
                        $errors[] = 'Mini şerit öğesi için başlık zorunludur.';
                        break;
                    }
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Mini şerit görseli zorunludur.';
                        break;
                    }
                    if (!isset($_POST['link_url']) || trim((string) $_POST['link_url']) === '') {
                        $errors[] = 'Mini şerit bağlantısı zorunludur.';
                        break;
                    }
                    createHeroStripItem($pdo, $_POST);
                    addFlash('success', 'Mini şerit öğesi eklendi.');
                    redirect('content.php#hero-strip');
                    break;
                case 'update_hero_strip':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Geçersiz mini şerit öğesi.';
                        break;
                    }
                    if (!isset($_POST['title']) || trim((string) $_POST['title']) === '') {
                        $errors[] = 'Mini şerit öğesi için başlık zorunludur.';
                        break;
                    }
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Mini şerit görseli zorunludur.';
                        break;
                    }
                    if (!isset($_POST['link_url']) || trim((string) $_POST['link_url']) === '') {
                        $errors[] = 'Mini şerit bağlantısı zorunludur.';
                        break;
                    }
                    updateHeroStripItem($pdo, $id, $_POST);
                    addFlash('success', 'Mini şerit öğesi güncellendi.');
                    redirect('content.php#hero-strip');
                    break;
                case 'delete_hero_strip':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        deleteHeroStripItem($pdo, $id);
                        addFlash('success', 'Mini şerit öğesi silindi.');
                        redirect('content.php#hero-strip');
                    }
                    break;
                case 'reorder_hero_strip':
                    $orderPayload = (string) ($_POST['order'] ?? '');
                    $ids = array_filter(array_map('trim', explode(',', $orderPayload)), static fn($value) => $value !== '');
                    reorderHeroStripItems($pdo, $ids);
                    addFlash('success', 'Mini şerit sırası güncellendi.');
                    redirect('content.php#hero-strip');
                    break;
                case 'update_hero_settings':
                    $autoplayMs = isset($_POST['autoplay_ms']) ? (int) $_POST['autoplay_ms'] : 4000;
                    if ($autoplayMs < 2000) {
                        $autoplayMs = 2000;
                    }
                    updateSettings($pdo, [
                        'hero_autoplay_enabled' => isset($_POST['autoplay']) ? '1' : '0',
                        'hero_autoplay_ms' => (string) $autoplayMs,
                        'hero_show_dots' => isset($_POST['show_dots']) ? '1' : '0',
                        'hero_show_arrows' => isset($_POST['show_arrows']) ? '1' : '0',
                    ]);
                    addFlash('success', 'Hero ayarları güncellendi.');
                    redirect('content.php#hero-settings');
                    break;
                case 'create_block':
                    createHomeBlock($pdo, $_POST);
                    addFlash('success', 'Vitrin bloğu eklendi.');
                    redirect('content.php');
                    break;
                case 'update_block':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Geçersiz vitrin bloğu.';
                        break;
                    }
                    updateHomeBlock($pdo, $id, $_POST);
                    addFlash('success', 'Vitrin bloğu güncellendi.');
                    redirect('content.php');
                    break;
                case 'delete_block':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        deleteHomeBlock($pdo, $id);
                        addFlash('success', 'Vitrin bloğu silindi.');
                        redirect('content.php');
                    }
                    break;
            }
        } catch (Throwable $e) {
            $errors[] = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}

$heroBannerToken = issueCsrfToken('hero-banner-actions');
$heroSlideToken = issueCsrfToken('hero-slide-actions');
$heroStripToken = issueCsrfToken('hero-strip-actions');
$heroSettingsToken = issueCsrfToken('hero-settings-actions');
$blockToken = issueCsrfToken('block-actions');

$heroBanners = ['left' => [], 'right' => []];
$heroSlides = [];
$heroStripItems = [];
$blocks = [];
$categories = [];
$heroSettings = [
    'autoplay' => true,
    'autoplay_ms' => 4000,
    'show_dots' => true,
    'show_arrows' => true,
];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $heroBanners = fetchHeroBanners($pdo);
        $heroSlides = fetchHeroSlides($pdo);
        $heroStripItems = fetchHeroStripItems($pdo);
        $blocks = fetchHomeBlocks($pdo);
        $categories = fetchAllCategories($pdo);
    } catch (Throwable $e) {
        $errors[] = 'Vitrin verileri alınırken hata oluştu: ' . $e->getMessage();
    }
}

$settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchSettings($pdo);
    } catch (Throwable $ignored) {
        $settings = [];
    }
}
$resolvedSettings = resolveSettings($settings);
$heroSettings = [
    'autoplay' => !empty($resolvedSettings['hero_autoplay_enabled']),
    'autoplay_ms' => (int) ($resolvedSettings['hero_autoplay_ms'] ?? 4000),
    'show_dots' => !empty($resolvedSettings['hero_show_dots']),
    'show_arrows' => !empty($resolvedSettings['hero_show_arrows']),
];
$branding = buildBrandingContext($settings);

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!empty($errors)): ?>
    <div class="space-y-2">
        <?php foreach ($errors as $error): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../src/admin_content_sections.php'; ?>

<?php require __DIR__ . '/../src/admin_page_end.php';
