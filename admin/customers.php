<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Müşteriler';
$activeNav = 'customers';
$currentUser = $user;

$customers = [];
$selectedCustomer = null;
$selectedOrders = [];
$selectedTickets = [];
$databaseWarning = '';

$selectedId = isset($_GET['customer']) ? (int) $_GET['customer'] : 0;

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Müşteri listesi yüklenemiyor.';
} else {
    $customers = fetchAllCustomers($pdo);
    if ($selectedId > 0) {
        $selectedCustomer = fetchCustomerProfile($pdo, $selectedId);
        if ($selectedCustomer) {
            $selectedOrders = fetchCustomerOrders($pdo, $selectedId, $selectedCustomer['email'] ?? null);
            $selectedTickets = fetchCustomerTickets($pdo, $selectedId);
        }
    }
}

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if ($databaseWarning !== ''): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <?= htmlspecialchars($databaseWarning) ?>
    </div>
<?php else: ?>
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Müşteri Listesi</h2>
                    <p class="text-xs text-slate-500">Kayıtlı kullanıcı hesaplarını görüntüleyin ve detaylarını inceleyin.</p>
                </div>
                <span class="text-xs text-slate-400">Toplam <?= number_format(count($customers)) ?> hesap</span>
            </div>
            <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100/70 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Müşteri</th>
                                <th class="px-4 py-3 text-left font-semibold">İletişim</th>
                                <th class="px-4 py-3 text-left font-semibold">Sipariş</th>
                                <th class="px-4 py-3 text-left font-semibold">Destek</th>
                                <th class="px-4 py-3 text-left font-semibold">Bakiye</th>
                                <th class="px-4 py-3 text-left font-semibold">Son Giriş</th>
                                <th class="px-4 py-3 text-right font-semibold">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                            <?php foreach ($customers as $customerRow): ?>
                                <tr class="<?php if ($selectedId === (int) $customerRow['id']): ?>bg-brand-50/60 dark:bg-brand-600/10<?php endif; ?>">
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($customerRow['name']) ?></p>
                                        <p class="text-[11px] text-slate-400">#<?= (int) $customerRow['id'] ?></p>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs text-slate-500">
                                        <div><?= htmlspecialchars($customerRow['email']) ?></div>
                                        <?php if (!empty($customerRow['phone'])): ?>
                                            <div><?= htmlspecialchars($customerRow['phone']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-[11px] mt-1 text-slate-400">Oluşturma: <?= htmlspecialchars(date('d.m.Y', strtotime($customerRow['created_at']))) ?></div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs font-semibold text-brand-600 dark:text-brand-200">
                                        <?= number_format((int) ($customerRow['order_count'] ?? 0)) ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs font-semibold text-emerald-600 dark:text-emerald-300">
                                        <?= number_format((int) ($customerRow['ticket_count'] ?? 0)) ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <?= formatCurrency((int) ($customerRow['balance_cents'] ?? 0)) ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-xs text-slate-500 dark:text-slate-400">
                                        <?= $customerRow['last_login_at'] ? htmlspecialchars(date('d.m.Y H:i', strtotime($customerRow['last_login_at']))) : '—' ?>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right text-xs">
                                        <a href="?customer=<?= (int) $customerRow['id'] ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60">İncele</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Kayıtlı müşteri bulunmuyor.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="xl:col-span-1">
            <?php if ($selectedCustomer): ?>
                <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-6 space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($selectedCustomer['name']) ?></h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars($selectedCustomer['email']) ?></p>
                        <?php if (!empty($selectedCustomer['phone'])): ?>
                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($selectedCustomer['phone']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 p-3">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Sipariş</p>
                            <p class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100"><?= number_format((int)($selectedCustomer['order_count'] ?? 0)) ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 p-3">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Destek</p>
                            <p class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100"><?= number_format((int)($selectedCustomer['ticket_count'] ?? 0)) ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 p-3">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Bakiye</p>
                            <p class="mt-1 text-lg font-semibold text-emerald-600 dark:text-emerald-300"><?= formatCurrency((int)($selectedCustomer['balance_cents'] ?? 0)) ?></p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Son Siparişler</h4>
                        <?php foreach (array_slice($selectedOrders, 0, 5) as $order): ?>
                            <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 px-4 py-3 text-xs">
                                <p class="font-semibold text-slate-700 dark:text-slate-100">#<?= htmlspecialchars($order['order_no']) ?> · <?= htmlspecialchars($order['status']) ?></p>
                                <p class="text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars(date('d.m.Y', strtotime($order['created_at']))) ?> · <?= formatCurrency((int)$order['total_cents'], $order['currency'] ?? 'TRY') ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($selectedOrders)): ?>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Bu müşteri için kayıtlı sipariş bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Destek Talepleri</h4>
                        <?php foreach (array_slice($selectedTickets, 0, 5) as $ticket): ?>
                            <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 px-4 py-3 text-xs">
                                <p class="font-semibold text-slate-700 dark:text-slate-100">#<?= htmlspecialchars($ticket['ticket_no']) ?> · <?= htmlspecialchars($ticket['status']) ?></p>
                                <p class="text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars(date('d.m.Y', strtotime($ticket['created_at']))) ?> · Öncelik: <?= htmlspecialchars($ticket['priority']) ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($selectedTickets)): ?>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Bu müşteri için destek talebi bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 bg-white/60 dark:bg-slate-900/40 px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                    Müşteri kartını incelemek için listeden seçim yapın.
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
