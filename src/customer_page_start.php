<?php

declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activeNav */
/** @var array $flashes */
/** @var array $currentCustomer */

$navItems = [
    ['key' => 'dashboard', 'label' => 'Ana Sayfa', 'icon' => '🏠', 'href' => '/account/index.php'],
    ['key' => 'orders', 'label' => 'Siparişlerim', 'icon' => '🧾', 'href' => '/account/orders.php'],
    ['key' => 'support', 'label' => 'Destek Taleplerim', 'icon' => '💬', 'href' => '/account/support.php'],
    ['key' => 'profile', 'label' => 'Profilim', 'icon' => '👤', 'href' => '/account/profile.php'],
];
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle) ?> · Lisansonay Müşteri Paneli</title>
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
<body class="h-full bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-screen flex">
    <aside id="sidebar" class="hidden lg:flex lg:w-72 xl:w-80 border-r border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 backdrop-blur">
        <div class="flex flex-col w-full">
            <div class="px-6 py-6 border-b border-slate-200/60 dark:border-slate-800/60">
                <a href="/account/index.php" class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-white text-xl font-semibold shadow">L</span>
                    <div>
                        <p class="text-lg font-semibold tracking-tight">Lisansonay</p>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Müşteri Paneli</p>
                    </div>
                </a>
            </div>
            <nav class="flex-1 px-4 py-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3 px-2">Menü</p>
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
                <p class="font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($currentCustomer['name'] ?? 'Müşteri') ?></p>
                <p class="text-xs mt-1 break-words"><?= htmlspecialchars($currentCustomer['email'] ?? '') ?></p>
                <a href="/logout.php" class="inline-flex items-center gap-2 mt-4 text-xs font-semibold text-red-600 hover:text-red-500">Çıkış Yap</a>
            </div>
        </div>
    </aside>
    <div class="flex-1 flex flex-col">
        <header class="sticky top-0 z-30 backdrop-blur supports-[backdrop-filter]:bg-white/80 bg-white/90 dark:bg-slate-950/70 border-b border-slate-200/60 dark:border-slate-800/60">
            <div class="px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button id="mobileNavToggle" class="inline-flex lg:hidden items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 px-3 py-2 text-sm">
                        <span class="sr-only">Menüyü Aç</span>
                        ☰
                    </button>
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Lisansonay</p>
                        <h1 class="text-lg sm:text-xl font-semibold tracking-tight"><?= htmlspecialchars($pageTitle) ?></h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button id="theme-toggle" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-800 px-3 py-2 bg-white/90 dark:bg-slate-900/90 text-sm">
                        <span id="themeIcon" class="inline-flex h-5 w-5 items-center justify-center"></span>
                        <span id="themeText" class="font-medium"></span>
                    </button>
                    <details class="relative">
                        <summary class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 px-3 py-2 bg-white/90 dark:bg-slate-900/80 text-sm font-medium cursor-pointer">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-white font-semibold">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($currentCustomer['name'] ?? 'M', 0, 1, 'UTF-8'), 'UTF-8')) ?>
                            </span>
                            <span class="hidden sm:block max-w-[140px] truncate"><?= htmlspecialchars($currentCustomer['name'] ?? '') ?></span>
                        </summary>
                        <div class="absolute right-0 mt-3 w-56 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-lg py-2 text-sm">
                            <a href="/account/index.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Ana Sayfa</a>
                            <a href="/account/orders.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Siparişlerim</a>
                            <a href="/account/support.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Destek Taleplerim</a>
                            <a href="/account/profile.php" class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Profilim</a>
                            <div class="border-t border-slate-200 dark:border-slate-800 my-1"></div>
                            <a href="/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/40">Çıkış Yap</a>
                        </div>
                    </details>
                </div>
            </div>
        </header>
        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 space-y-6">
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
