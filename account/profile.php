<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/customer.php';

ensureSession();
$customer = requireCustomerAuth();
$flashes = getFlashes();

$pageTitle = 'Profilim';
$activeNav = 'profile';
$currentCustomer = $customer;

$formErrors = [];
$form = [
    'name' => $customer['name'] ?? '',
    'email' => $customer['email'] ?? '',
    'phone' => $customer['phone'] ?? '',
    'password' => '',
    'password_confirmation' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['email'] = trim((string)($_POST['email'] ?? ''));
    $form['phone'] = trim((string)($_POST['phone'] ?? ''));
    $form['password'] = (string)($_POST['password'] ?? '');
    $form['password_confirmation'] = (string)($_POST['password_confirmation'] ?? '');

    if (!validateCsrfToken('customer-profile', $_POST['_token'] ?? null)) {
        $formErrors[] = 'Güncelleme isteği doğrulanamadı. Lütfen tekrar deneyin.';
    }

    if ($form['name'] === '' || mb_strlen($form['name']) < 3) {
        $formErrors[] = 'Ad Soyad en az 3 karakter olmalıdır.';
    }

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Geçerli bir e-posta adresi girin.';
    }

    if ($form['password'] !== '') {
        if (strlen($form['password']) < 8) {
            $formErrors[] = 'Şifreniz en az 8 karakter olmalıdır.';
        }
        if ($form['password'] !== $form['password_confirmation']) {
            $formErrors[] = 'Şifre ve şifre tekrarı eşleşmiyor.';
        }
    }

    if (empty($formErrors)) {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
        } else {
            $normalizedEmail = strtolower($form['email']);
            if (strtolower((string)($customer['email'] ?? '')) !== $normalizedEmail) {
                $existing = findCustomerByEmail($pdo, $form['email']);
                if ($existing && (int) $existing['id'] !== (int) $customer['id']) {
                    $formErrors[] = 'Bu e-posta adresi başka bir hesap tarafından kullanılıyor.';
                }
            }
        }
    }

    if (empty($formErrors) && isset($pdo) && $pdo instanceof PDO) {
        updateCustomerProfile($pdo, (int) $customer['id'], [
            'name' => $form['name'],
            'email' => $form['email'],
            'phone' => $form['phone'],
            'password' => $form['password'] !== '' ? $form['password'] : null,
        ]);
        $updated = findCustomerById($pdo, (int) $customer['id']);
        if ($updated) {
            $updated = hydrateCustomer($updated);
            setCurrentCustomer($updated);
            $currentCustomer = $updated;
            addFlash('success', 'Profil bilgileriniz güncellendi.');
            redirect('/account/profile.php');
        } else {
            $formErrors[] = 'Güncelleme yapıldı ancak profil bilgileri alınamadı.';
        }
    }
}

$token = issueCsrfToken('customer-profile');

require __DIR__ . '/../src/customer_page_start.php';
?>
<section class="max-w-3xl space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Profil bilgileri</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">İletişim bilgileriniz sipariş ve destek taleplerinde kullanılacaktır.</p>
    </div>

    <?php if (!empty($formErrors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4 space-y-2">
            <?php foreach ($formErrors as $error): ?>
                <p class="text-sm"><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
        <label class="text-sm font-medium text-slate-600 dark:text-slate-300 space-y-2">
            <span>Ad Soyad</span>
            <input type="text" name="name" required value="<?= htmlspecialchars($form['name']) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
        </label>
        <label class="text-sm font-medium text-slate-600 dark:text-slate-300 space-y-2">
            <span>E-posta</span>
            <input type="email" name="email" required value="<?= htmlspecialchars($form['email']) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
        </label>
        <label class="text-sm font-medium text-slate-600 dark:text-slate-300 space-y-2">
            <span>Telefon</span>
            <input type="text" name="phone" value="<?= htmlspecialchars($form['phone']) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="+90 5xx xxx xx xx" />
        </label>
        <div class="text-sm text-slate-500 dark:text-slate-400 space-y-2">
            <span>Şifre</span>
            <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Yeni şifreniz" />
            <input type="password" name="password_confirmation" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Şifre tekrar" />
            <p class="text-xs">Şifreyi boş bırakırsanız mevcut şifreniz korunur.</p>
        </div>
        <div class="md:col-span-2 flex items-center justify-between gap-3 pt-2">
            <div class="text-xs text-slate-500 dark:text-slate-400">
                Son giriş: <?= isset($currentCustomer['last_login_at']) ? htmlspecialchars($currentCustomer['last_login_at']) : '—' ?>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-3 shadow">
                Bilgileri Güncelle
            </button>
        </div>
    </form>
</section>
<?php
require __DIR__ . '/../src/customer_page_end.php';
