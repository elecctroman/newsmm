<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/customer.php';
require_once __DIR__ . '/../src/admin.php';

ensureSession();
$customer = requireCustomerAuth();
$flashes = getFlashes();

$pageTitle = 'Bakiyem';
$activeNav = 'wallet';
$currentCustomer = $customer;
$databaseWarning = '';
$balanceCents = 0;
$transactions = [];
$topups = [];
$topupForm = [
    'amount' => '',
    'payment_channel' => '',
    'proof_url' => '',
    'notes' => '',
];
$topupErrors = [];

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Bakiye ve işlemler yüklenemiyor.';
} else {
    try {
        $customerId = (int) $customer['id'];
        $balanceCents = getCustomerBalance($pdo, $customerId);
        $transactions = fetchWalletTransactions($pdo, $customerId, 25);
        $topups = fetchCustomerTopups($pdo, $customerId, 15);
    } catch (Throwable $walletException) {
        $databaseWarning = 'Bakiye verileri alınırken bir hata oluştu: ' . $walletException->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'topup-request') {
    $topupForm['amount'] = trim((string) ($_POST['amount'] ?? ''));
    $topupForm['payment_channel'] = trim((string) ($_POST['payment_channel'] ?? ''));
    $topupForm['proof_url'] = trim((string) ($_POST['proof_url'] ?? ''));
    $topupForm['notes'] = trim((string) ($_POST['notes'] ?? ''));

    if (!validateCsrfToken('customer-wallet', $_POST['_token'] ?? null)) {
        $topupErrors[] = 'Talep doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $topupErrors[] = 'Bakiye talebi oluşturmak için veritabanı bağlantısı gerekiyor.';
    } else {
        $amountFloat = normalizePriceAmount($topupForm['amount']);
        if ($amountFloat === null || $amountFloat <= 0) {
            $topupErrors[] = 'Geçerli bir tutar girin.';
        }

        if (empty($topupErrors)) {
            try {
                $amountCents = priceToCents((float) $amountFloat);
                $topupId = createWalletTopupRequest($pdo, (int) $customer['id'], [
                    'amount_cents' => $amountCents,
                    'payment_channel' => $topupForm['payment_channel'],
                    'proof_url' => $topupForm['proof_url'],
                    'notes' => $topupForm['notes'],
                ]);
                $topupRow = fetchWalletTopupById($pdo, $topupId);
                $reference = $topupRow['reference_code'] ?? ('TP-' . $topupId);
                addFlash('success', 'Bakiye yükleme talebiniz alındı. Referans kodu: #' . htmlspecialchars($reference));
                redirect('/account/wallet.php');
            } catch (Throwable $requestException) {
                $topupErrors[] = 'Talep oluşturulurken bir hata oluştu: ' . $requestException->getMessage();
            }
        }
    }
}

$walletToken = issueCsrfToken('customer-wallet');

require __DIR__ . '/../src/customer_page_start.php';
?>
<section class="space-y-6">
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/40 p-4">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bakiye</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-300"><?= formatCurrency((int) $balanceCents) ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hesabınızdaki kullanılabilir tutar</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/40 p-4">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">İşlem</p>
                <p class="mt-2 text-3xl font-semibold text-brand-600 dark:text-brand-200"><?= number_format(count($transactions)) ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Son 25 işlem listelenir</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/40 p-4">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bekleyen talep</p>
                <?php
                $pendingTopups = array_filter($topups, static function (array $row): bool {
                    return ($row['status'] ?? '') === 'pending';
                });
                ?>
                <p class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-300"><?= number_format(count($pendingTopups)) ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Onay bekleyen yükleme talepleri</p>
            </div>
        </div>
        <?php if ($databaseWarning !== ''): ?>
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                <?= htmlspecialchars($databaseWarning) ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Bakiye yükleme talebi</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Talebiniz incelenip onaylandığında bakiyenize yansıtılır.</p>
        <?php if (!empty($topupErrors)): ?>
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm space-y-2">
                <?php foreach ($topupErrors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="topup-request" />
            <input type="hidden" name="_token" value="<?= htmlspecialchars($walletToken) ?>" />
            <label class="flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                <span>Tutar (TRY)</span>
                <input type="text" name="amount" required value="<?= htmlspecialchars($topupForm['amount']) ?>" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="Örn. 500" />
            </label>
            <label class="flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                <span>Ödeme kanalı</span>
                <input type="text" name="payment_channel" value="<?= htmlspecialchars($topupForm['payment_channel']) ?>" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="Havale, kredi kartı vb." />
            </label>
            <label class="md:col-span-2 flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                <span>Ödeme dekontu bağlantısı (opsiyonel)</span>
                <input type="url" name="proof_url" value="<?= htmlspecialchars($topupForm['proof_url']) ?>" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="https://" />
            </label>
            <label class="md:col-span-2 flex flex-col text-sm font-medium text-slate-600 dark:text-slate-300">
                <span>Notlar</span>
                <textarea name="notes" rows="3" class="mt-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="Ödeme açıklaması veya ek bilgiler."><?= htmlspecialchars($topupForm['notes']) ?></textarea>
            </label>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-3 shadow">Talep Oluştur</button>
            </div>
        </form>
    </article>

    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Bakiye işlemleri</h2>
        <?php if (empty($transactions)): ?>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Henüz kayıtlı işleminiz bulunmuyor.</p>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tarih</th>
                            <th class="px-4 py-3 text-left font-semibold">İşlem</th>
                            <th class="px-4 py-3 text-left font-semibold">Tutar</th>
                            <th class="px-4 py-3 text-left font-semibold">Son Bakiye</th>
                            <th class="px-4 py-3 text-left font-semibold">Not</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($txn['created_at']))) ?></td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars(ucfirst((string) $txn['type'])) ?></td>
                                <td class="px-4 py-3 text-xs font-semibold <?= ((int) $txn['amount_cents']) >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' ?>">
                                    <?= formatCurrency((int) $txn['amount_cents']) ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300"><?= formatCurrency((int) $txn['balance_after']) ?></td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($txn['note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Yükleme taleplerim</h2>
        <?php if (empty($topups)): ?>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Henüz yükleme talebinde bulunmadınız.</p>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Referans</th>
                            <th class="px-4 py-3 text-left font-semibold">Tutar</th>
                            <th class="px-4 py-3 text-left font-semibold">Durum</th>
                            <th class="px-4 py-3 text-left font-semibold">Ödeme Kanalı</th>
                            <th class="px-4 py-3 text-left font-semibold">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                        <?php foreach ($topups as $topup): ?>
                            <tr>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-700 dark:text-slate-200">#<?= htmlspecialchars($topup['reference_code'] ?? ('TP-' . $topup['id'])) ?></td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-700 dark:text-slate-200"><?= formatCurrency((int) $topup['amount_cents']) ?></td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300"><?= htmlspecialchars($topup['status'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($topup['payment_channel'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($topup['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php
require __DIR__ . '/../src/customer_page_end.php';
