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

if (!isset($branding) || !is_array($branding)) {
    $branding = buildBrandingContext([]);
} else {
    $branding = buildBrandingContext($branding);
}

$companyName = $branding['company_name'];
$companyTagline = $branding['company_tagline'];
$logoLight = $branding['logo_light'];
$logoDark = $branding['logo_dark'] ?? null;
$primaryPaletteJson = json_encode($branding['primary_palette'], JSON_UNESCAPED_SLASHES);
$accentPaletteJson = json_encode($branding['accent_palette'], JSON_UNESCAPED_SLASHES);
if (!is_string($primaryPaletteJson) || $primaryPaletteJson === 'null') {
    $primaryPaletteJson = '{"50":"#eef2ff","100":"#e0e7ff","200":"#c7d2fe","500":"#4c51bf","600":"#4338ca","700":"#3730a3"}';
}
if (!is_string($accentPaletteJson) || $accentPaletteJson === 'null') {
    $accentPaletteJson = '{"50":"#fff7ed","100":"#ffedd5","200":"#fed7aa","500":"#f97316","600":"#ea580c","700":"#c2410c"}';
}
$companyInitial = mb_strtoupper(mb_substr($companyName, 0, 1, 'UTF-8'), 'UTF-8') ?: 'L';

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

$flashPayload = json_encode(array_values($flashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($flashPayload)) {
    $flashPayload = '[]';
}

if (!isset($cartCount)) {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $cartCount = getActiveCartCount($pdo, $currentCustomer);
        } else {
            $cartCount = getSessionCartCount();
        }
    } catch (Throwable $cartEx) {
        $cartCount = getSessionCartCount();
    }
}

$cartLink = ($currentCustomer && isset($currentCustomer['id']))
    ? '/account/cart.php'
    : '/login.php?redirect=' . urlencode('/account/cart.php');

$globalNavLinks = [
    ['href' => '/index.php#catalog', 'label' => 'Ürünler'],
    ['href' => '/index.php#services', 'label' => 'Servisler'],
    ['href' => '/index.php#order-form', 'label' => 'Sipariş Ver'],
    ['href' => '/index.php#contact', 'label' => 'İletişim'],
    ['href' => '/account/index.php', 'label' => 'Hesabım'],
];

$currentRequestUri = $_SERVER['REQUEST_URI'] ?? '/account/index.php';
$currentPath = strtok($currentRequestUri, '?') ?: '/account/index.php';
$topNavLinks = [];
foreach ($globalNavLinks as $link) {
    $href = $link['href'];
    $isAccountSection = strncmp($href, '/account/', 9) === 0;
    $isActive = false;
    if ($isAccountSection) {
        $isActive = strncmp($currentPath, '/account/', 9) === 0;
    } elseif (strncmp($href, '/index.php', 10) === 0 && $currentPath === '/index.php') {
        $isActive = true;
    }

    $topNavLinks[] = [
        'href' => $href,
        'label' => $link['label'],
        'active' => $isActive,
    ];
}
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
                        brand: <?= $primaryPaletteJson ?>,
                        accent: <?= $accentPaletteJson ?>,
                    },
                },
            },
        };
    </script>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('lisansonay-theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = (stored === 'dark' || stored === 'light') ? stored : (prefersDark ? 'dark' : 'light');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (err) {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            }
        })();
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
        details summary {
            list-style: none;
        }
        details summary::-webkit-details-marker {
            display: none;
        }
        .toast-root {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }
        .toast-item {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.5);
            background: rgba(255, 255, 255, 0.95);
            color: #0f172a;
            padding: 0.75rem 1rem;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.15);
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .dark .toast-item {
            border-color: rgba(71, 85, 105, 0.6);
            background: rgba(15, 23, 42, 0.9);
            color: #e2e8f0;
        }
        .toast-item.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-success {
            border-color: rgba(16, 185, 129, 0.45);
            background: rgba(220, 252, 231, 0.95);
            color: #047857;
        }
        .dark .toast-success {
            border-color: rgba(16, 185, 129, 0.55);
            background: rgba(6, 95, 70, 0.7);
            color: #6ee7b7;
        }
        .toast-error {
            border-color: rgba(239, 68, 68, 0.45);
            background: rgba(254, 226, 226, 0.95);
            color: #b91c1c;
        }
        .dark .toast-error {
            border-color: rgba(239, 68, 68, 0.55);
            background: rgba(127, 29, 29, 0.75);
            color: #fecaca;
        }
        .toast-message {
            flex: 1;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .toast-close {
            border: none;
            background: transparent;
            color: inherit;
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            opacity: 0.7;
        }
        .toast-close:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<script id="flash-data" type="application/json"><?= $flashPayload ?></script>
<div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 dark:border-slate-800/70 backdrop-blur bg-white/85 dark:bg-slate-950/75">
        <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between gap-4">
            <a href="/index.php" class="flex items-center gap-3">
                <?php if ($logoLight || $logoDark): ?>
                    <?php $customerLogo = $logoLight ?? $logoDark; ?>
                    <span class="inline-flex">
                        <img src="<?= htmlspecialchars($customerLogo) ?>"
                             data-brand-logo="true"
                             data-light-logo="<?= htmlspecialchars($logoLight ?? $customerLogo) ?>"
                             data-dark-logo="<?= htmlspecialchars($logoDark ?? $customerLogo) ?>"
                             alt="<?= htmlspecialchars($companyName) ?> logosu"
                             class="h-10 w-auto object-contain" />
                    </span>
                <?php else: ?>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-500 text-white text-xl font-semibold shadow"><?= htmlspecialchars($companyInitial) ?></span>
                <?php endif; ?>
                <div>
                    <p class="text-sm uppercase tracking-[0.35em] text-brand-600 dark:text-brand-200"><?= htmlspecialchars($companyName) ?></p>
                    <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">Müşteri Portalı</h1>
                    <?php if ($companyTagline !== ''): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-300 mt-0.5 leading-tight"><?= htmlspecialchars($companyTagline) ?></p>
                    <?php endif; ?>
                </div>
            </a>
            <div class="flex items-center gap-3">
                <nav class="hidden md:flex items-center gap-4 text-sm font-medium">
                    <?php foreach ($topNavLinks as $link): ?>
                        <?php $isActiveTop = $link['active']; ?>
                        <a href="<?= htmlspecialchars($link['href']) ?>" class="transition hover:text-brand-600 dark:hover:text-brand-300 <?php if ($isActiveTop): ?>text-brand-600 dark:text-brand-200<?php else: ?>text-slate-600 dark:text-slate-300<?php endif; ?>">
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
                <a href="/admin/index.php" class="hidden md:inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-600 dark:text-slate-300 bg-white/80 dark:bg-slate-900/70 shadow">Yönetim</a>
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
