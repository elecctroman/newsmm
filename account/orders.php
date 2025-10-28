<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/public.php';
require_once __DIR__ . '/../src/customer.php';
require_once __DIR__ . '/../src/admin.php';

ensureSession();
$customer = requireCustomerAuth();
$flashes = getFlashes();

$pageTitle = 'Siparişlerim';
$activeNav = 'orders';
$currentCustomer = $customer;

$orderStatuses = getOrderStatuses();
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
$orders = [];
$databaseWarning = '';
$branding = loadBrandingSettings(isset($pdo) && $pdo instanceof PDO ? $pdo : null);

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Siparişler görüntülenemiyor.';
} else {
    $orders = fetchCustomerOrders($pdo, (int) $customer['id'], $customer['email'] ?? null);
    if ($statusFilter !== 'all' && isset($orderStatuses[$statusFilter])) {
        $orders = array_values(array_filter($orders, static function (array $order) use ($statusFilter): bool {
            return ($order['status'] ?? '') === $statusFilter;
        }));
    }
}

require __DIR__ . '/../src/customer_page_start.php';
?>
<section class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Sipariş kayıtlarınız</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Duruma göre filtreleyebilir, detayları inceleyebilirsiniz.</p>
        </div>
        <form method="get" class="flex items-center gap-2 text-sm">
            <label for="status" class="text-slate-500 dark:text-slate-400">Durum:</label>
            <select id="status" name="status" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm" onchange="this.form.submit()">
                <option value="all" <?php if ($statusFilter === 'all'): ?>selected<?php endif; ?>>Tümü</option>
                <?php foreach ($orderStatuses as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?php if ($statusFilter === $key): ?>selected<?php endif; ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($databaseWarning !== ''): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
            <?= htmlspecialchars($databaseWarning) ?>
        </div>
    <?php elseif (empty($orders)): ?>
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 px-4 py-6 text-sm text-slate-500 dark:text-slate-400">
            Kriterlerinize uygun sipariş kaydı bulunamadı.
        </div>
    <?php else: ?>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100/70 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Sipariş</th>
                            <th class="px-4 py-3 text-left font-semibold">Durum</th>
                            <th class="px-4 py-3 text-left font-semibold">Ödeme</th>
                            <th class="px-4 py-3 text-left font-semibold">Toplam</th>
                            <th class="px-4 py-3 text-left font-semibold">Not</th>
                            <th class="px-4 py-3 text-left font-semibold">Oluşturma</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($order['order_no']) ?></p>
                                    <?php if (!empty($order['notes'])): ?>
                                        <p class="text-xs text-slate-400 mt-1">Ref: <?= htmlspecialchars($order['notes']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs">
                                    <?= htmlspecialchars($orderStatuses[$order['status']] ?? $order['status']) ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-600 dark:text-slate-300">
                                    <?php
                                        $walletCents = (int) ($order['wallet_deduction_cents'] ?? 0);
                                        $cardCents = (int) ($order['card_charge_cents'] ?? 0);
                                        $channel = trim((string)($order['payment_channel'] ?? ''));
                                        $reference = trim((string)($order['payment_reference'] ?? ''));
                                        $currencyCode = $order['currency'] ?? 'TRY';
                                    ?>
                                    <?php if ($walletCents > 0): ?>
                                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200/70 dark:border-emerald-700/50 bg-emerald-50/80 dark:bg-emerald-900/30 px-2.5 py-1 font-semibold text-emerald-600 dark:text-emerald-200">
                                            💼 <?= formatCurrency($walletCents, $currencyCode) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($cardCents > 0): ?>
                                        <div class="inline-flex items-center gap-2 rounded-full border border-brand-200/70 dark:border-brand-700/50 bg-brand-50/60 dark:bg-brand-700/30 px-2.5 py-1 font-semibold text-brand-600 dark:text-brand-200 mt-1">
                                            💳 <?= formatCurrency($cardCents, $currencyCode) ?>
                                            <?php if ($channel !== ''): ?>
                                                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-300">(<?= htmlspecialchars($channel) ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($walletCents === 0 && $cardCents === 0): ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                    <?php if ($reference !== ''): ?>
                                        <div class="text-[11px] text-slate-400 mt-1">Ref: <?= htmlspecialchars($reference) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs font-semibold text-brand-600 dark:text-brand-200">
                                    <?= formatCurrency((int) ($order['total_cents'] ?? 0), $currencyCode) ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500 dark:text-slate-400 max-w-xs">
                                    <?= htmlspecialchars($order['notes'] ?? '') ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars(date('d.m.Y H:i', strtotime($order['created_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/../src/customer_page_end.php';
