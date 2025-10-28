<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/public.php';
require_once __DIR__ . '/../src/customer.php';

ensureSession();
$customer = requireCustomerAuth();
$flashes = getFlashes();

$pageTitle = 'Özet';
$activeNav = 'dashboard';
$currentCustomer = $customer;

$orderStats = ['total' => 0, 'by_status' => []];
$allOrders = [];
$recentOrders = [];
$totalSpent = 0;
$tickets = [];
$openTicketCount = 0;
$balanceCents = 0;
$databaseWarning = '';
$branding = loadBrandingSettings(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
$categoryMenu = loadCategoryMenu(isset($pdo) && $pdo instanceof PDO ? $pdo : null);

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Sipariş ve destek verileri görüntülenemiyor.';
} else {
    $customerId = (int) $customer['id'];
    $orderStats = fetchCustomerOrderStats($pdo, $customerId, $customer['email'] ?? null);
    $allOrders = fetchCustomerOrders($pdo, $customerId, $customer['email'] ?? null);
    $recentOrders = array_slice($allOrders, 0, 5);
    foreach ($allOrders as $order) {
        $totalSpent += (int) ($order['total_cents'] ?? 0);
    }

    $tickets = fetchCustomerTickets($pdo, $customerId);
    foreach ($tickets as $ticket) {
        if (($ticket['status'] ?? '') !== 'closed') {
            $openTicketCount++;
        }
    }
    $tickets = array_slice($tickets, 0, 5);

    try {
        $balanceCents = getCustomerBalance($pdo, $customerId);
    } catch (Throwable $balanceException) {
        $balanceCents = 0;
    }
}

require __DIR__ . '/../src/customer_page_start.php';
?>
<section class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Toplam Sipariş</p>
        <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100"><?= number_format($orderStats['total'] ?? 0) ?></p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Hesabınız üzerinden oluşturulan kayıtlı siparişler</p>
    </article>
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bekleyen Sipariş</p>
        <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-300"><?= number_format($orderStats['by_status']['pending'] ?? 0) ?></p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Onay ve teslimat bekleyen sipariş talepleriniz</p>
    </article>
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Aktif Destek</p>
        <p class="mt-3 text-3xl font-semibold text-brand-600 dark:text-brand-200"><?= number_format($openTicketCount) ?></p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Açık veya yanıt bekleyen destek talepleriniz</p>
    </article>
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Mevcut Bakiye</p>
        <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-300"><?= formatCurrency((int) $balanceCents) ?></p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Hesabınızda kullanılabilir bakiye</p>
    </article>
</section>

<section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Son Siparişler</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">En güncel 5 siparişinizi görüntüleyin.</p>
            </div>
            <a href="/account/orders.php" class="text-xs font-semibold text-brand-600 hover:text-brand-500">Tümünü Gör</a>
        </div>
        <?php if ($databaseWarning !== ''): ?>
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                <?= htmlspecialchars($databaseWarning) ?>
            </div>
        <?php elseif (empty($recentOrders)): ?>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Henüz kayıtlı siparişiniz bulunmuyor.</p>
        <?php else: ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($recentOrders as $order): ?>
                    <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 px-4 py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($order['order_no']) ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Durum: <?= htmlspecialchars($order['status']) ?></p>
                        </div>
                        <div class="text-right text-sm font-semibold text-brand-600 dark:text-brand-200">
                            <?= formatCurrency((int)($order['total_cents'] ?? 0), $order['currency'] ?? 'TRY') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                Toplam harcama: <strong class="text-slate-700 dark:text-slate-200"><?= formatCurrency((int) $totalSpent) ?></strong>
            </div>
        <?php endif; ?>
    </article>
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Destek Talepleri</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Son taleplerinizi ve durumlarını takip edin.</p>
            </div>
            <a href="/account/support.php" class="text-xs font-semibold text-brand-600 hover:text-brand-500">Destek Merkezi</a>
        </div>
        <?php if ($databaseWarning !== ''): ?>
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                <?= htmlspecialchars($databaseWarning) ?>
            </div>
        <?php elseif (empty($tickets)): ?>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Açık destek talebiniz bulunmuyor.</p>
        <?php else: ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($tickets as $ticket): ?>
                    <a href="/account/support.php?ticket=<?= (int) $ticket['id'] ?>" class="block rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">#<?= htmlspecialchars($ticket['ticket_no']) ?> · <?= htmlspecialchars($ticket['subject']) ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Durum: <?= htmlspecialchars($ticket['status']) ?> · Mesaj: <?= number_format((int)($ticket['message_count'] ?? 0)) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php
require __DIR__ . '/../src/customer_page_end.php';
