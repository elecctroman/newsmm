<?php

declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activeNav */
/** @var array $flashes */
/** @var array $currentCustomer */
/** @var string|null $pageSubtitle */
/** @var string|null $companyName */

$navItems = [
    ['key' => 'dashboard', 'label' => 'Ana Sayfa', 'icon' => '🏠', 'href' => '/account/index.php'],
    ['key' => 'cart', 'label' => 'Sepetim', 'icon' => '🛒', 'href' => '/account/cart.php'],
    ['key' => 'orders', 'label' => 'Siparişlerim', 'icon' => '🧾', 'href' => '/account/orders.php'],
    ['key' => 'wallet', 'label' => 'Bakiyem', 'icon' => '💳', 'href' => '/account/wallet.php'],
    ['key' => 'support', 'label' => 'Destek Taleplerim', 'icon' => '💬', 'href' => '/account/support.php'],
    ['key' => 'profile', 'label' => 'Profilim', 'icon' => '👤', 'href' => '/account/profile.php'],
];

$customerName = trim((string)($currentCustomer['name'] ?? ''));
$customerInitial = $customerName !== ''
    ? mb_strtoupper(mb_substr($customerName, 0, 1, 'UTF-8'), 'UTF-8')
    : 'M';
$currentEmail = trim((string)($currentCustomer['email'] ?? ''));
$currentPhone = trim((string)($currentCustomer['phone'] ?? ''));

if (!isset($companyName) || trim((string) $companyName) === '') {
    $companyName = 'Lisansonay';
}

$defaultSubtitle = 'Hesabınızı yönetin, sipariş ve destek kayıtlarını buradan takip edin.';
$pageSubtitle = isset($pageSubtitle) && trim((string) $pageSubtitle) !== ''
    ? trim((string) $pageSubtitle)
    : $defaultSubtitle;

$introLine = $customerName !== ''
    ? sprintf('Merhaba %s! %s', $customerName, $pageSubtitle)
    : $pageSubtitle;

$topNavLinks = [
    ['href' => '/index.php#catalog', 'label' => 'Ürünler', 'active' => false],
    ['href' => '/index.php#services', 'label' => 'Servisler', 'active' => false],
    ['href' => '/index.php#order-form', 'label' => 'Sipariş Ver', 'active' => false],
    ['href' => '/index.php#contact', 'label' => 'İletişim', 'active' => false],
    ['href' => '/account/index.php', 'label' => 'Hesabım', 'active' => true],
];
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle) ?> · <?= htmlspecialchars($companyName) ?> Müşteri Portalı</title>
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
                            200: '#c7d2fe',
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
        :root {
            color-scheme: light dark;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        details summary {
            list-style: none;
        }
        details summary::-webkit-details-marker {
            display: none;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 dark:border-slate-800/70 backdrop-blur bg-white/85 dark:bg-slate-950/75">
        <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between gap-4">
            <a href="/index.php" class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-500 text-white text-xl font-semibold shadow">L</span>
                <div>
                    <p class="text-sm uppercase tracking-[0.35em] text-brand-600 dark:text-brand-200">Lisansonay</p>
                    <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">Müşteri Portalı</h1>
                </div>
            </a>
            <div class="flex items-center gap-3">
                <nav class="hidden md:flex items-center gap-4 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <?php foreach ($topNavLinks as $link): ?>
                        <?php $isActiveTop = $link['active']; ?>
                        <a href="<?= htmlspecialchars($link['href']) ?>" class="transition <?php if ($isActiveTop): ?>text-brand-600 dark:text-brand-200 border-b-2 border-brand-500 pb-1<?php else: ?>hover:text-brand-600 dark:hover:text-brand-300<?php endif; ?>">
                            <?= htmlspecialchars($link['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <button id="theme-toggle" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm bg-white/80 dark:bg-slate-900/70 shadow">
                    <span id="themeIcon" class="inline-flex h-5 w-5 items-center justify-center"></span>
                    <span id="themeText" class="font-medium">Tema</span>
                </button>
                <details class="relative">
                    <summary class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white/80 dark:bg-slate-900/70 text-sm font-semibold cursor-pointer">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-white font-semibold"><?= htmlspecialchars($customerInitial) ?></span>
                        <span class="hidden sm:block max-w-[140px] truncate text-slate-700 dark:text-slate-200"><?= htmlspecialchars($customerName !== '' ? $customerName : 'Müşteri') ?></span>
                    </summary>
                    <div class="absolute right-0 mt-3 w-56 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg py-2 text-sm">
                        <a href="/account/index.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Hesap Panosu</a>
                        <a href="/account/cart.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Sepetim</a>
                        <a href="/account/orders.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Siparişlerim</a>
                        <a href="/account/wallet.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Bakiyem</a>
                        <a href="/account/support.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Destek Taleplerim</a>
                        <a href="/account/profile.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Profilim</a>
                        <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
                        <a href="/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/40">Çıkış Yap</a>
                    </div>
                </details>
            </div>
        </div>
    </header>
    <main class="flex-1 bg-gradient-to-b from-brand-50/40 via-white dark:from-slate-900/80 dark:via-slate-950/90">
        <div class="py-8 lg:py-12">
            <div class="mx-auto max-w-6xl px-4 space-y-8">
                <section class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm px-6 py-6 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Hesap Merkezi</p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50"><?= htmlspecialchars($pageTitle) ?></h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($introLine) ?></p>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-300">
                        <?php if ($currentEmail !== ''): ?>
                            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/50 px-3 py-1">
                                📧 <span class="font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($currentEmail) ?></span>
                            </span>
                        <?php endif; ?>
                        <?php if ($currentPhone !== ''): ?>
                            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/50 px-3 py-1">
                                📞 <span class="font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($currentPhone) ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </section>
                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="lg:w-72 xl:w-80 flex-shrink-0">
                        <aside id="sidebar" class="hidden lg:flex flex-col rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm">
                            <div class="px-6 py-6 border-b border-slate-200/60 dark:border-slate-800/60">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Navigasyon</p>
                            </div>
                            <nav class="flex-1 px-4 py-6">
                                <ul class="space-y-1">
                                    <?php foreach ($navItems as $item): ?>
                                        <?php $isActive = $item['key'] === $activeNav; ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($item['href']) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition <?php if ($isActive): ?>bg-brand-50 text-brand-700 dark:bg-brand-600/20 dark:text-brand-100 shadow<?php else: ?>text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-50 dark:hover:bg-slate-900/60<?php endif; ?>">
                                                <span aria-hidden="true"><?= $item['icon'] ?></span>
                                                <?= htmlspecialchars($item['label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </nav>
                            <div class="px-6 py-6 border-t border-slate-200/60 dark:border-slate-800/60 text-sm text-slate-500 dark:text-slate-400">
                                <p class="font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($customerName !== '' ? $customerName : 'Müşteri') ?></p>
                                <?php if ($currentEmail !== ''): ?>
                                    <p class="text-xs mt-1 break-words"><?= htmlspecialchars($currentEmail) ?></p>
                                <?php endif; ?>
                                <a href="/logout.php" class="inline-flex items-center gap-2 mt-4 text-xs font-semibold text-red-600 hover:text-red-500">Çıkış Yap</a>
                            </div>
                        </aside>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="lg:hidden">
                            <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-4">
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Panel menüsü</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <?php foreach ($navItems as $item): ?>
                                        <?php $isActive = $item['key'] === $activeNav; ?>
                                        <a href="<?= htmlspecialchars($item['href']) ?>" class="inline-flex items-center gap-2 rounded-2xl px-3 py-1.5 text-sm font-medium transition <?php if ($isActive): ?>bg-brand-500 text-white shadow<?php else: ?>bg-slate-100 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-900/80<?php endif; ?>">
                                            <span aria-hidden="true"><?= $item['icon'] ?></span>
                                            <?= htmlspecialchars($item['label']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 space-y-6">
                            <?php if (!empty($flashes)): ?>
                                <div class="space-y-2">
                                    <?php foreach ($flashes as $flash): ?>
                                        <?php $type = $flash['type'] ?? 'info'; ?>
                                        <div class="rounded-xl border px-4 py-3 text-sm <?php if ($type === 'success'): ?>border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-700/40 dark:bg-emerald-900/30 dark:text-emerald-200<?php elseif ($type === 'error'): ?>border-red-200 bg-red-50 text-red-700 dark:border-red-700/40 dark:bg-red-900/30 dark:text-red-200<?php else: ?>border-slate-200 bg-white/80 text-slate-700 dark:border-slate-700/40 dark:bg-slate-900/40 dark:text-slate-200<?php endif; ?>">
                                            <?= htmlspecialchars($flash['message'] ?? '') ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
