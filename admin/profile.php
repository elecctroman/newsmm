<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Profilim';
$activeNav = ''; // profil yan menüde ayrı ele alınıyor
$currentUser = $user;

$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('profile-update', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

        if ($name === '') {
            $formErrors[] = 'İsim alanı boş bırakılamaz.';
        }
        if ($email === '') {
            $formErrors[] = 'E-posta alanı boş bırakılamaz.';
        }
        if ($password !== '' && $password !== $passwordConfirm) {
            $formErrors[] = 'Şifre ile şifre doğrulama alanları eşleşmiyor.';
        }

        if (empty($formErrors)) {
            try {
                updateAdminProfile($pdo, (int)$user['id'], $name, $email, $password !== '' ? $password : null);
                $freshUser = getAdminUserById($pdo, (int)$user['id']);
                if ($freshUser) {
                    setCurrentUser($freshUser);
                }
                logActivity($pdo, (int) $user['id'], 'update', 'profile', (int)$user['id'], 'Profil bilgileri güncellendi');
                addFlash('success', 'Profiliniz güncellendi.');
                redirect('profile.php');
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) {
                    $formErrors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
                } else {
                    $formErrors[] = 'Profil güncellenemedi: ' . $e->getMessage();
                }
            }
        }
    }
}

$token = issueCsrfToken('profile-update');

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı gerekli</h2>
        <p class="text-xs mt-1">Profil bilgilerinizi güncellemek için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="max-w-3xl space-y-5">
        <div>
            <h2 class="text-base font-semibold">Hesap Bilgileriniz</h2>
            <p class="text-xs text-slate-500">Ad, e-posta ve şifrenizi güncelleyebilirsiniz.</p>
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
            <label class="text-xs font-medium text-slate-500 space-y-1">
                <span>Adınız</span>
                <input type="text" name="name" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
            </label>
            <label class="text-xs font-medium text-slate-500 space-y-1">
                <span>E-posta</span>
                <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Yeni Şifre</span>
                    <input type="password" name="password" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Güncellemek istemiyorsanız boş bırakın" />
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Yeni Şifre (Tekrar)</span>
                    <input type="password" name="password_confirmation" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                </label>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">Değişiklikleri Kaydet</button>
        </form>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
