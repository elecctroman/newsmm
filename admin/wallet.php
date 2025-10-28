<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/customer.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Bakiye Yönetimi';
$activeNav = 'wallet';
$currentUser = $user;

$walletMetrics = [
    'wallet_balance' => 0,
    'pending_topups' => 0,
];
$transactions = [];
$pendingTopups = [];
$recentTopups = [];
$adjustErrors = [];
$topupActionErrors = [];
$adjustForm = [
    'customer_id' => '',
    'customer_email' => '',
    'amount' => '',
    'type' => 'credit',
    'note' => '',
];
$settings = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $walletMetrics = getDashboardMetrics($pdo);
        $transactions = fetchWalletTransactionsAdmin($pdo, null, 50);
        $pendingTopups = fetchWalletTopups($pdo, 'pending', 25);
        $recentTopups = fetchWalletTopups($pdo, null, 25);
        $settings = fetchSettings($pdo);
    } catch (Throwable $walletException) {
        addFlash('error', 'Bakiye verileri yüklenirken hata: ' . $walletException->getMessage());
        $flashes = array_merge($flashes, getFlashes());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!validateCsrfToken('admin-wallet', $_POST['_token'] ?? null)) {
        addFlash('error', 'İstek doğrulanamadı. Lütfen tekrar deneyin.');
        redirect('/admin/wallet.php');
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        addFlash('error', 'Veritabanı bağlantısı kurulamadı.');
        redirect('/admin/wallet.php');
    }

    try {
        if ($action === 'approve-topup') {
            $topupId = (int) ($_POST['topup_id'] ?? 0);
            $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
            approveWalletTopup($pdo, $topupId, (int) $user['id'], $adminNote);
            addFlash('success', 'Yükleme talebi onaylandı ve bakiyeye yansıtıldı.');
        } elseif ($action === 'reject-topup') {
            $topupId = (int) ($_POST['topup_id'] ?? 0);
            $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
            rejectWalletTopup($pdo, $topupId, (int) $user['id'], $adminNote);
            addFlash('success', 'Yükleme talebi reddedildi.');
        } elseif ($action === 'adjust-balance') {
            $adjustForm['customer_id'] = trim((string) ($_POST['customer_id'] ?? ''));
            $adjustForm['customer_email'] = trim((string) ($_POST['customer_email'] ?? ''));
            $adjustForm['amount'] = trim((string) ($_POST['amount'] ?? ''));
            $adjustForm['type'] = (string) ($_POST['type'] ?? 'credit');
            $adjustForm['note'] = trim((string) ($_POST['note'] ?? ''));

            $customerId = (int) $adjustForm['customer_id'];
            if ($customerId <= 0 && $adjustForm['customer_email'] !== '') {
                $customerRecord = findCustomerByEmail($pdo, $adjustForm['customer_email']);
                if ($customerRecord) {
                    $customerId = (int) $customerRecord['id'];
                }
            }

            if ($customerId <= 0) {
                throw new RuntimeException('Geçerli bir müşteri ID veya e-posta belirtin.');
            }

            $amountFloat = normalizePriceAmount($adjustForm['amount']);
            if ($amountFloat === null || $amountFloat <= 0) {
                throw new RuntimeException('Geçerli bir tutar girin.');
            }

            $amountCents = priceToCents((float) $amountFloat);
            if ($adjustForm['type'] === 'debit') {
                $amountCents *= -1;
                $adjustType = 'withdrawal';
            } else {
                $adjustType = 'adjustment';
            }

            createManualWalletAdjustment($pdo, $customerId, $amountCents, $adjustType, $adjustForm['note'], (int) $user['id']);
            addFlash('success', 'Müşteri bakiyesi güncellendi.');
        }
    } catch (Throwable $e) {
        addFlash('error', $e->getMessage());
    }

    redirect('/admin/wallet.php');
}

$walletToken = issueCsrfToken('admin-wallet');

$branding = buildBrandingContext($settings);

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı kurulamadı</h2>
        <p class="text-xs mt-1">Lütfen bağlantı bilgilerinizi kontrol ederek tekrar deneyin.</p>
    </div>
<?php else: ?>
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Toplam Müşteri Bakiyesi</p>
            <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-300"><?= formatCurrency((int) ($walletMetrics['wallet_balance'] ?? 0)) ?></p>
            <p class="mt-2 text-xs text-slate-500">Tüm müşterilerin kullanılabilir bakiyeleri toplamı</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bekleyen Yükleme</p>
            <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-300"><?= number_format((int) ($walletMetrics['pending_topups'] ?? 0)) ?></p>
            <p class="mt-2 text-xs text-slate-500">Onay bekleyen yükleme talepleri</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">İşlem Sayısı</p>
            <p class="mt-3 text-3xl font-semibold text-brand-600 dark:text-brand-200"><?= number_format(count($transactions)) ?></p>
            <p class="mt-2 text-xs text-slate-500">Son 50 işlem görüntüleniyor</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bekleyen Talep</p>
            <p class="mt-3 text-3xl font-semibold text-slate-800 dark:text-slate-100"><?= number_format(count($pendingTopups)) ?></p>
            <p class="mt-2 text-xs text-slate-500">Onaylanması gereken yükleme talepleri</p>
        </article>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Bekleyen yükleme talepleri</h2>
                <span class="text-xs text-slate-500"><?= number_format(count($pendingTopups)) ?> talep</span>
            </div>
            <div class="p-5 space-y-4">
                <?php if (empty($pendingTopups)): ?>
                    <p class="text-sm text-slate-500">Onay bekleyen talep bulunmuyor.</p>
                <?php else: ?>
                    <ul class="space-y-4">
                        <?php foreach ($pendingTopups as $topup): ?>
                            <li class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/40 p-4">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">#<?= htmlspecialchars($topup['reference_code'] ?? ('TP-' . $topup['id'])) ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Müşteri: <?= htmlspecialchars($topup['customer_name'] ?? '') ?> · <?= htmlspecialchars($topup['customer_email'] ?? '') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Tutar: <strong class="text-brand-600 dark:text-brand-200"><?= formatCurrency((int) $topup['amount_cents']) ?></strong></p>
                                        <?php if (!empty($topup['payment_channel'])): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Ödeme: <?= htmlspecialchars($topup['payment_channel']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($topup['notes'])): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Müşteri Notu: <?= htmlspecialchars($topup['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-col gap-2 w-full sm:w-auto">
                                        <form method="post" class="flex flex-col gap-2">
                                            <input type="hidden" name="action" value="approve-topup" />
                                            <input type="hidden" name="topup_id" value="<?= (int) $topup['id'] ?>" />
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($walletToken) ?>" />
                                            <textarea name="admin_note" rows="2" class="rounded-xl border border-emerald-200 dark:border-emerald-700 bg-emerald-50/70 dark:bg-emerald-900/30 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-200" placeholder="Onay notu (opsiyonel)"></textarea>
                                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold px-3 py-2">Onayla</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Bu yükleme talebini reddetmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="action" value="reject-topup" />
                                            <input type="hidden" name="topup_id" value="<?= (int) $topup['id'] ?>" />
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($walletToken) ?>" />
                                            <input type="text" name="admin_note" class="mt-2 rounded-xl border border-red-200 dark:border-red-700 bg-red-50/70 dark:bg-red-900/30 px-3 py-2 text-xs text-red-700 dark:text-red-200" placeholder="Red notu (opsiyonel)" />
                                            <button type="submit" class="mt-2 inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 dark:border-red-700 px-3 py-2 text-xs font-semibold text-red-600 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">Reddet</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>

        <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70">
                <h2 class="text-sm font-semibold">Müşteri bakiyesini güncelle</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kredi veya borç işlemleri için kullanılır.</p>
            </div>
            <form method="post" class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="adjust-balance" />
                <input type="hidden" name="_token" value="<?= htmlspecialchars($walletToken) ?>" />
                <label class="flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                    <span>Müşteri ID</span>
                    <input type="number" name="customer_id" value="<?= htmlspecialchars($adjustForm['customer_id']) ?>" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="ID" />
                </label>
                <label class="flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                    <span>Veya müşteri e-postası</span>
                    <input type="email" name="customer_email" value="<?= htmlspecialchars($adjustForm['customer_email']) ?>" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="ornek@mail.com" />
                </label>
                <label class="flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                    <span>Tutar (TRY)</span>
                    <input type="text" name="amount" required value="<?= htmlspecialchars($adjustForm['amount']) ?>" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="Örn. 150" />
                </label>
                <label class="flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                    <span>İşlem türü</span>
                    <select name="type" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm">
                        <option value="credit" <?php if ($adjustForm['type'] === 'credit'): ?>selected<?php endif; ?>>Bakiye artır</option>
                        <option value="debit" <?php if ($adjustForm['type'] === 'debit'): ?>selected<?php endif; ?>>Bakiye düş</option>
                    </select>
                </label>
                <label class="md:col-span-2 flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                    <span>Not</span>
                    <textarea name="note" rows="3" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="İşlem notu"><?= htmlspecialchars($adjustForm['note']) ?></textarea>
                </label>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-3 shadow">Bakiyeyi Güncelle</button>
                </div>
            </form>
        </article>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Son işlemler</h2>
                <span class="text-xs text-slate-500"><?= number_format(count($transactions)) ?> kayıt</span>
            </div>
            <div class="p-5 overflow-x-auto">
                <?php if (empty($transactions)): ?>
                    <p class="text-sm text-slate-500">Henüz işlem bulunmuyor.</p>
                <?php else: ?>
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Tarih</th>
                                <th class="px-3 py-2 text-left font-semibold">Müşteri</th>
                                <th class="px-3 py-2 text-left font-semibold">İşlem</th>
                                <th class="px-3 py-2 text-left font-semibold">Tutar</th>
                                <th class="px-3 py-2 text-left font-semibold">Son Bakiye</th>
                                <th class="px-3 py-2 text-left font-semibold">Not</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                            <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($txn['created_at']))) ?></td>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                        <?= htmlspecialchars($txn['customer_name'] ?? '') ?><br />
                                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($txn['customer_email'] ?? '') ?></span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($txn['type']) ?></td>
                                    <td class="px-3 py-2 font-semibold <?= ((int) $txn['amount_cents']) >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' ?>"><?= formatCurrency((int) $txn['amount_cents']) ?></td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300"><?= formatCurrency((int) $txn['balance_after']) ?></td>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($txn['note'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </article>

        <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Son yükleme talepleri</h2>
                <span class="text-xs text-slate-500"><?= number_format(count($recentTopups)) ?> kayıt</span>
            </div>
            <div class="p-5 overflow-x-auto">
                <?php if (empty($recentTopups)): ?>
                    <p class="text-sm text-slate-500">Kayıtlı yükleme talebi bulunamadı.</p>
                <?php else: ?>
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Referans</th>
                                <th class="px-3 py-2 text-left font-semibold">Müşteri</th>
                                <th class="px-3 py-2 text-left font-semibold">Tutar</th>
                                <th class="px-3 py-2 text-left font-semibold">Durum</th>
                                <th class="px-3 py-2 text-left font-semibold">Tarih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                            <?php foreach ($recentTopups as $topup): ?>
                                <tr>
                                    <td class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">#<?= htmlspecialchars($topup['reference_code'] ?? ('TP-' . $topup['id'])) ?></td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                        <?= htmlspecialchars($topup['customer_name'] ?? '') ?><br />
                                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($topup['customer_email'] ?? '') ?></span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-200"><?= formatCurrency((int) $topup['amount_cents']) ?></td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($topup['status'] ?? '-') ?></td>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($topup['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
