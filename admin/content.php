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
        'create_slider' => 'slider-actions',
        'update_slider' => 'slider-actions',
        'delete_slider' => 'slider-actions',
        'create_strip' => 'strip-actions',
        'update_strip' => 'strip-actions',
        'delete_strip' => 'strip-actions',
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
                case 'create_slider':
                    if (!isset($_POST['image_url']) || trim((string) $_POST['image_url']) === '') {
                        $errors[] = 'Slider görseli zorunludur.';
                        break;
                    }
                    createHomeSliderItem($pdo, $_POST);
                    addFlash('success', 'Slider öğesi eklendi.');
                    redirect('content.php');
                    break;
                case 'update_slider':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Geçersiz slider öğesi.';
                        break;
                    }
                    updateHomeSliderItem($pdo, $id, $_POST);
                    addFlash('success', 'Slider öğesi güncellendi.');
                    redirect('content.php');
                    break;
                case 'delete_slider':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        deleteHomeSliderItem($pdo, $id);
                        addFlash('success', 'Slider öğesi silindi.');
                        redirect('content.php');
                    }
                    break;
                case 'create_strip':
                    if (!isset($_POST['name']) || trim((string) $_POST['name']) === '') {
                        $errors[] = 'Şerit öğesi için başlık zorunludur.';
                        break;
                    }
                    createHomeStripItem($pdo, $_POST);
                    addFlash('success', 'Şerit öğesi eklendi.');
                    redirect('content.php');
                    break;
                case 'update_strip':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $errors[] = 'Geçersiz şerit öğesi.';
                        break;
                    }
                    updateHomeStripItem($pdo, $id, $_POST);
                    addFlash('success', 'Şerit öğesi güncellendi.');
                    redirect('content.php');
                    break;
                case 'delete_strip':
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id > 0) {
                        deleteHomeStripItem($pdo, $id);
                        addFlash('success', 'Şerit öğesi silindi.');
                        redirect('content.php');
                    }
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

$sliderToken = issueCsrfToken('slider-actions');
$stripToken = issueCsrfToken('strip-actions');
$blockToken = issueCsrfToken('block-actions');

$sliderItems = [];
$stripItems = [];
$blocks = [];
$categories = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sliderItems = fetchHomeSliderItems($pdo);
        $stripItems = fetchHomeStripItems($pdo);
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
