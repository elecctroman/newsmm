<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Genel Ayarlar';
$activeNav = 'settings';
$currentUser = $user;

$formErrors = [];
$settings = [];

if (isset($pdo) && $pdo instanceof PDO) {
    $settings = fetchSettings($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('settings-update', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        $companyName = trim((string)($_POST['company_name'] ?? 'Lisansonay'));
        $supportEmail = trim((string)($_POST['support_email'] ?? ''));
        $supportPhone = trim((string)($_POST['support_phone'] ?? ''));
        $whatsappLink = trim((string)($_POST['whatsapp_link'] ?? ''));

        if ($companyName === '') {
            $formErrors[] = 'Firma adı boş bırakılamaz.';
        }
        if ($supportEmail === '') {
            $formErrors[] = 'Destek e-posta adresi gereklidir.';
        }

        if (empty($formErrors)) {
            $updates = [
                'company_name' => $companyName,
                'support_email' => $supportEmail,
                'support_phone' => $supportPhone,
                'whatsapp_link' => $whatsappLink,
            ];
            updateSettings($pdo, $updates);
            logActivity($pdo, (int) $user['id'], 'update', 'settings', null, 'Genel ayarlar güncellendi');
            addFlash('success', 'Ayarlar güncellendi.');
            redirect('settings.php');
        }
    }
}

$token = issueCsrfToken('settings-update');

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı gerekli</h2>
        <p class="text-xs mt-1">Ayarları güncellemek için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="max-w-3xl space-y-5">
        <div>
            <h2 class="text-base font-semibold">Kurumsal Bilgiler</h2>
            <p class="text-xs text-slate-500">Panelde görüntülenen temel kimlik ve iletişim bilgilerini güncelleyin.</p>
        </div>

        <?php if (!empty($formErrors)): ?>
            <div class="space-y-2">
                <?php foreach ($formErrors as $error): ?>
                    <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm p-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Firma Adı</span>
                    <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Destek E-posta</span>
                    <input type="email" name="support_email" value="<?= htmlspecialchars($settings['support_email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                </label>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Destek Telefon</span>
                    <input type="text" name="support_phone" value="<?= htmlspecialchars($settings['support_phone'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>WhatsApp Bağlantısı</span>
                    <input type="url" name="whatsapp_link" value="<?= htmlspecialchars($settings['whatsapp_link'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                </label>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">Ayarları Kaydet</button>
        </form>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
