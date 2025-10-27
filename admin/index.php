<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
ensureGuest();

$errors = [];
$settings = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchSettings($pdo);
    } catch (Throwable $e) {
        // ignore settings errors on login
    }
}

$branding = buildBrandingContext($settings);
$companyName = $branding['company_name'];
$companyTagline = $branding['company_tagline'];
$logoLight = $branding['logo_light'];
$logoDark = $branding['logo_dark'] ?? null;
$supportEmail = $branding['support_email'];
$primaryPaletteJson = json_encode($branding['primary_palette'], JSON_UNESCAPED_SLASHES);
$accentPaletteJson = json_encode($branding['accent_palette'], JSON_UNESCAPED_SLASHES);
if (!is_string($primaryPaletteJson) || $primaryPaletteJson === 'null') {
    $primaryPaletteJson = '{"50":"#f4f5ff","100":"#e8e9ff","200":"#d2d6ff","500":"#4c5bff","600":"#3b45d6","700":"#2f37a7"}';
}
if (!is_string($accentPaletteJson) || $accentPaletteJson === 'null') {
    $accentPaletteJson = '{"50":"#fef3c7","100":"#fde68a","200":"#fcd34d","500":"#f59e0b","600":"#d97706","700":"#b45309"}';
}
$companyInitial = mb_strtoupper(mb_substr($companyName, 0, 1, 'UTF-8'), 'UTF-8') ?: 'L';
$emailDomain = '';
if (strpos($supportEmail, '@') !== false) {
    $emailDomain = substr(strrchr($supportEmail, '@'), 1) ?: '';
}
$adminEmailPlaceholder = $emailDomain !== '' ? 'admin@' . $emailDomain : 'admin@ornek.com';
$defaultCredentialHint = 'Varsayılan yönetici bilgileri kurulum dokümantasyonunda paylaşılır.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('admin-login', $_POST['_token'] ?? null)) {
        $errors[] = 'Giriş isteği doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $errors[] = 'Veritabanı bağlantısı kurulamadı. Lütfen yapılandırmayı kontrol edin.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $errors[] = 'E-posta ve şifre alanları zorunludur.';
        } else {
            $user = authenticateUser($pdo, $email, $password);
            if ($user) {
                setCurrentUser($user);
                recordSuccessfulLogin($pdo, (int)$user['id']);
                logActivity($pdo, (int)$user['id'], 'login', 'auth', null, 'Kontrol paneline giriş yaptı');
                addFlash('success', 'Başarıyla giriş yaptınız.');
                redirect('/admin/dashboard.php');
            } else {
                $errors[] = 'E-posta veya şifre hatalı.';
            }
        }
    }
}

$token = issueCsrfToken('admin-login');

$connectionMessage = '';
if ((!isset($pdo) || !$pdo instanceof PDO) && !empty($connectionError ?? null)) {
    $connectionMessage = $connectionError;
} elseif (!empty($missingConfig ?? false)) {
    $connectionMessage = 'Yapılandırma dosyası bulunamadı. Lütfen config/config.php dosyasını oluşturun.';
}

?>
<!DOCTYPE html>
<html lang="tr" class="h-full" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($companyName) ?> · Yönetim Girişi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: <?= $primaryPaletteJson ?>,
                        accent: <?= $accentPaletteJson ?>,
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light dark;
            --brand-primary: <?= htmlspecialchars($branding['primary_color']) ?>;
            --brand-accent: <?= htmlspecialchars($branding['accent_color']) ?>;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-xl">
        <div class="mb-8 text-center space-y-2">
            <?php if ($logoLight || $logoDark): ?>
                <?php $initialLogo = $logoLight ?? $logoDark; ?>
                <span class="inline-flex justify-center">
                    <img src="<?= htmlspecialchars($initialLogo) ?>"
                         data-brand-logo="true"
                         data-light-logo="<?= htmlspecialchars($logoLight ?? $initialLogo) ?>"
                         data-dark-logo="<?= htmlspecialchars($logoDark ?? $initialLogo) ?>"
                         alt="<?= htmlspecialchars($companyName) ?> logosu"
                         class="h-12 w-auto object-contain" />
                </span>
            <?php else: ?>
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500 text-white text-xl font-semibold shadow"><?= htmlspecialchars($companyInitial) ?></span>
            <?php endif; ?>
            <h1 class="text-2xl font-semibold tracking-tight"><?= htmlspecialchars($companyName) ?> Yönetim Paneli</h1>
            <?php if ($companyTagline !== ''): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($companyTagline) ?></p>
            <?php endif; ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Lütfen giriş bilgilerinizi kullanarak sisteme erişin.</p>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($defaultCredentialHint) ?></p>
        </div>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-xl backdrop-blur">
            <div class="p-6 sm:p-8">
                <?php if ($connectionMessage !== ''): ?>
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-600/40 dark:bg-amber-900/30 dark:text-amber-200 px-4 py-3 text-sm">
                        <?= htmlspecialchars($connectionMessage) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="mb-4 space-y-2">
                        <?php foreach ($errors as $error): ?>
                            <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="space-y-5" novalidate>
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                    <div class="space-y-2">
                        <label for="email" class="text-sm font-medium text-slate-600 dark:text-slate-300">E-posta Adresi</label>
                        <input type="email" id="email" name="email" required autofocus class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500 focus:outline-none" placeholder="<?= htmlspecialchars($adminEmailPlaceholder) ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
                    </div>
                    <div class="space-y-2">
                        <label for="password" class="text-sm font-medium text-slate-600 dark:text-slate-300">Şifre</label>
                        <input type="password" id="password" name="password" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500 focus:outline-none" placeholder="••••••••" />
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" disabled class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-slate-500">Beni hatırla (yakında)</span>
                        </label>
                        <a href="#" class="text-brand-600 hover:text-brand-500 font-medium">Şifremi unuttum?</a>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-3 shadow-lg shadow-brand-500/30 transition">
                        Kontrol Paneline Giriş Yap
                    </button>
                </form>
            </div>
        </div>
        <div class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
            <p>© <?= date('Y') ?> <?= htmlspecialchars($companyName) ?>. Güvenli yönetim paneli.</p>
        </div>
    </div>
</div>
<script src="/public/assets/js/app.js" defer></script>
</body>
</html>
