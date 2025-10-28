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


$blockToken = issueCsrfToken('block-actions');

$blocks = [];
$categories = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
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

$sliderOrphans = [];
if (!empty($settings['media_orphans_slider'])) {
    $decoded = json_decode((string) $settings['media_orphans_slider'], true);
    if (is_array($decoded)) {
        $sliderOrphans = array_values(array_filter($decoded, static function ($item) {
            return is_array($item) && !empty($item['image_url']);
        }));
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
