<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/customer.php';

ensureSession();
ensureCustomerGuest();

$errors = [];
$form = [
    'email' => '',
    'password' => '',
];
$redirectTarget = isset($_GET['redirect']) ? (string) $_GET['redirect'] : '/account/index.php';
if ($redirectTarget === '' || preg_match('/^https?:/i', $redirectTarget)) {
    $redirectTarget = '/account/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['email'] = trim((string)($_POST['email'] ?? ''));
    $form['password'] = (string)($_POST['password'] ?? '');
    $redirectTarget = (string)($_POST['redirect'] ?? $redirectTarget);
    if ($redirectTarget === '' || preg_match('/^https?:/i', $redirectTarget)) {
        $redirectTarget = '/account/index.php';
    }

    if (!validateCsrfToken('customer-login', $_POST['_token'] ?? null)) {
        $errors[] = 'Giriş isteği doğrulanamadı. Lütfen tekrar deneyin.';
    }

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }
    if ($form['password'] === '') {
        $errors[] = 'Şifrenizi girin.';
    }

    if (empty($errors)) {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $errors[] = 'Veritabanı bağlantısı kurulamadı.';
        } else {
            $customer = authenticateCustomer($pdo, $form['email'], $form['password']);
            if (!$customer) {
                $errors[] = 'E-posta veya şifre hatalı.';
            } else {
                $customerId = (int) $customer['id'];
                mergeSessionCartIntoCustomer($pdo, $customerId);
                syncCustomerCartToSession($pdo, $customerId);
                $fresh = findCustomerById($pdo, $customerId);
                if ($fresh) {
                    $customer = hydrateCustomer($fresh);
                }
                setCurrentCustomer($customer);
                recordCustomerLogin($pdo, $customerId);
                addFlash('success', 'Tekrar hoş geldiniz, ' . ($customer['name'] ?? 'müşterimiz') . '!');
                redirect($redirectTarget);
            }
        }
    }
}

$token = issueCsrfToken('customer-login');

?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Lisansonay · Müşteri Girişi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#4c51bf',
                            600: '#4338ca',
                            700: '#3730a3',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="min-h-screen flex">
        <section class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand-500 to-brand-700 text-white p-12 flex-col justify-between">
            <div>
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-2xl font-semibold">L</span>
                <h1 class="mt-6 text-4xl font-semibold leading-tight">Lisansonay müşteri portalına hoş geldiniz</h1>
                <p class="mt-4 text-base text-brand-50/90 max-w-md">Ürün siparişi, destek talepleri ve lisans yönetimi için tüm araçlara tek panelden ulaşın.</p>
            </div>
            <ul class="space-y-4 text-sm text-brand-50/80">
                <li>• Güncel sipariş durumlarınızı anlık takip edin</li>
                <li>• Destek ekibimizle doğrudan iletişim kurun</li>
                <li>• Profil bilgilerinizi ve faturalarınızı yönetin</li>
            </ul>
        </section>
        <section class="flex-1 flex items-center justify-center py-12 px-6">
            <div class="w-full max-w-md space-y-8">
                <div class="text-center">
                    <a href="/" class="inline-flex items-center gap-2 text-sm text-brand-600 hover:text-brand-500">← Ana sayfaya dön</a>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight">Müşteri girişi</h2>
                    <p class="mt-2 text-sm text-slate-500">Hesabınıza erişmek için kayıtlı e-posta adresinizi ve şifrenizi girin.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4 space-y-2">
                        <?php foreach ($errors as $error): ?>
                            <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget) ?>" />
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
                        <span>E-posta adresi</span>
                        <input type="email" name="email" required value="<?= htmlspecialchars($form['email']) ?>" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="ornek@mail.com" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
                        <span>Şifre</span>
                        <input type="password" name="password" required class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="••••••••" />
                    </label>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-3 shadow">
                        Giriş Yap
                    </button>
                </form>

                <p class="text-sm text-slate-500 text-center">Henüz hesabınız yok mu? <a href="/register.php?redirect=<?= urlencode($redirectTarget) ?>" class="text-brand-600 hover:text-brand-500 font-medium">Kayıt olun</a></p>
            </div>
        </section>
    </main>
</body>
</html>
