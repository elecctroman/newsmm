<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
$user = requireAuth();
requirePermission($user, 'sales.orders.view');
$flashes = getFlashes();

$pageTitle = 'Siparişler';
$activeNav = 'orders';
$currentUser = $user;

$formErrors = [];
$oldOrder = [
    'order_no' => '',
    'customer_name' => '',
    'customer_email' => '',
    'customer_phone' => '',
    'status' => 'pending',
    'total_amount' => '',
    'currency' => 'TRY',
    'notes' => '',
    'wallet_deduction' => '',
    'card_charge' => '',
    'payment_channel' => '',
    'payment_reference' => '',
];

$orderStatuses = getOrderStatuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('order-actions', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'create':
                    $orderNo = trim((string)($_POST['order_no'] ?? ''));
                    $customerName = trim((string)($_POST['customer_name'] ?? ''));
                    $customerEmail = sanitizeNullableString($_POST['customer_email'] ?? null);
                    $customerPhone = sanitizeNullableString($_POST['customer_phone'] ?? null);
                    $customerAccountId = (int)($_POST['customer_id'] ?? 0);
                    $status = $_POST['status'] ?? 'pending';
                    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'TRY')));
                    $totalAmount = normalizePriceAmount($_POST['total_amount'] ?? null);
                    $notes = sanitizeNullableString($_POST['notes'] ?? null);
                    $walletInput = (string)($_POST['wallet_deduction'] ?? '');
                    $cardInput = (string)($_POST['card_charge'] ?? '');
                    $paymentChannel = sanitizeNullableString($_POST['payment_channel'] ?? null);
                    $paymentReference = sanitizeNullableString($_POST['payment_reference'] ?? null);
                    $walletDeduction = parseAmountToCents($walletInput);
                    $cardCharge = parseAmountToCents($cardInput);

                    $oldOrder = [
                        'order_no' => $orderNo,
                        'customer_name' => $customerName,
                        'customer_email' => (string)($customerEmail ?? ''),
                        'customer_phone' => (string)($customerPhone ?? ''),
                        'status' => $status,
                        'total_amount' => $_POST['total_amount'] ?? '',
                        'currency' => $currency,
                        'notes' => (string)($notes ?? ''),
                        'wallet_deduction' => $walletInput,
                        'card_charge' => $cardInput,
                        'payment_channel' => (string)($paymentChannel ?? ''),
                        'payment_reference' => (string)($paymentReference ?? ''),
                    ];

                    if ($customerName === '') {
                        $formErrors[] = 'Müşteri adı gereklidir.';
                    }
                    if (!isset($orderStatuses[$status])) {
                        $formErrors[] = 'Geçersiz sipariş durumu seçildi.';
                    }
                    if ($totalAmount === null) {
                        $formErrors[] = 'Geçerli bir toplam tutar girin.';
                    }

                    if (empty($formErrors)) {
                        if ($orderNo === '') {
                            $orderNo = generateOrderNumber($pdo);
                        }
                        $totalCents = priceToCents((float)$totalAmount);
                        $walletDeduction = max(0, min($walletDeduction, $totalCents));
                        $cardCharge = max(0, min($cardCharge, $totalCents));
                        if ($walletDeduction + $cardCharge > $totalCents) {
                            $cardCharge = max(0, $totalCents - $walletDeduction);
                        }
                        $payload = [
                            'customer_id' => $customerAccountId > 0 ? $customerAccountId : null,
                            'order_no' => $orderNo,
                            'customer_name' => $customerName,
                            'customer_email' => $customerEmail,
                            'customer_phone' => $customerPhone,
                            'status' => $status,
                            'currency' => $currency,
                            'total_cents' => $totalCents,
                            'notes' => $notes,
                            'wallet_deduction_cents' => $walletDeduction,
                            'card_charge_cents' => $cardCharge,
                            'payment_channel' => $paymentChannel,
                            'payment_reference' => $paymentReference,
                        ];
                        $orderId = createOrder($pdo, $payload);
                        logActivity($pdo, (int) $user['id'], 'create', 'order', $orderId, 'Yeni sipariş kaydı oluşturuldu');
                        addFlash('success', 'Sipariş başarıyla oluşturuldu.');
                        redirect('orders.php');
                    }
                    break;
                case 'update':
                    $orderId = (int)($_POST['id'] ?? 0);
                    if ($orderId <= 0) {
                        $formErrors[] = 'Geçersiz sipariş seçimi.';
                        break;
                    }
                    $orderNo = trim((string)($_POST['order_no'] ?? ''));
                    $customerName = trim((string)($_POST['customer_name'] ?? ''));
                    $customerEmail = sanitizeNullableString($_POST['customer_email'] ?? null);
                    $customerPhone = sanitizeNullableString($_POST['customer_phone'] ?? null);
                    $customerAccountId = (int)($_POST['customer_id'] ?? 0);
                    $status = $_POST['status'] ?? 'pending';
                    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'TRY')));
                    $totalAmount = normalizePriceAmount($_POST['total_amount'] ?? null);
                    $notes = sanitizeNullableString($_POST['notes'] ?? null);
                    $walletInput = (string)($_POST['wallet_deduction'] ?? '');
                    $cardInput = (string)($_POST['card_charge'] ?? '');
                    $paymentChannel = sanitizeNullableString($_POST['payment_channel'] ?? null);
                    $paymentReference = sanitizeNullableString($_POST['payment_reference'] ?? null);
                    $walletDeduction = parseAmountToCents($walletInput);
                    $cardCharge = parseAmountToCents($cardInput);

                    if ($customerName === '' || $totalAmount === null || !isset($orderStatuses[$status])) {
                        $formErrors[] = 'Müşteri adı, durum ve tutar alanları zorunludur.';
                        break;
                    }
                    if ($orderNo === '') {
                        $orderNo = generateOrderNumber($pdo);
                    }
                    $totalCents = priceToCents((float)$totalAmount);
                    $walletDeduction = max(0, min($walletDeduction, $totalCents));
                    $cardCharge = max(0, min($cardCharge, $totalCents));
                    if ($walletDeduction + $cardCharge > $totalCents) {
                        $cardCharge = max(0, $totalCents - $walletDeduction);
                    }
                    $payload = [
                        'customer_id' => $customerAccountId > 0 ? $customerAccountId : null,
                        'order_no' => $orderNo,
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'customer_phone' => $customerPhone,
                        'status' => $status,
                        'currency' => $currency,
                        'total_cents' => $totalCents,
                        'notes' => $notes,
                        'wallet_deduction_cents' => $walletDeduction,
                        'card_charge_cents' => $cardCharge,
                        'payment_channel' => $paymentChannel,
                        'payment_reference' => $paymentReference,
                    ];
                    updateOrder($pdo, $orderId, $payload);
                    logActivity($pdo, (int) $user['id'], 'update', 'order', $orderId, 'Sipariş bilgileri güncellendi');
                    addFlash('success', 'Sipariş güncellendi.');
                    redirect('orders.php');
                    break;
                case 'delete':
                    $orderId = (int)($_POST['id'] ?? 0);
                    if ($orderId <= 0) {
                        $formErrors[] = 'Silinecek sipariş bulunamadı.';
                        break;
                    }
                    deleteOrder($pdo, $orderId);
                    logActivity($pdo, (int) $user['id'], 'delete', 'order', $orderId, 'Sipariş silindi');
                    addFlash('success', 'Sipariş silindi.');
                    redirect('orders.php');
                    break;
                default:
                    $formErrors[] = 'Bilinmeyen işlem.';
            }
        } catch (PDOException $e) {
            $formErrors[] = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}

$actionToken = issueCsrfToken('order-actions');

$orders = [];
$newOrderNumber = '';
$settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    $orders = fetchOrders($pdo);
    $newOrderNumber = generateOrderNumber($pdo);
    try {
        $settings = fetchSettings($pdo);
    } catch (Throwable $settingsException) {
        $settings = [];
    }
}

$branding = buildBrandingContext($settings);

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı gerekli</h2>
        <p class="text-xs mt-1">Sipariş yönetimi için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Sipariş Yönetimi</h2>
                <p class="text-xs text-slate-500">Müşteri siparişlerini takip edin, durumu güncelleyin ve not ekleyin.</p>
            </div>
            <span class="text-xs text-slate-400">Toplam <?= number_format(count($orders)) ?> sipariş</span>
        </div>

        <?php if (!empty($formErrors)): ?>
            <div class="space-y-2">
                <?php foreach ($formErrors as $error): ?>
                    <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100/70 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Sipariş</th>
                            <th class="px-4 py-3 text-left font-semibold">Müşteri</th>
                            <th class="px-4 py-3 text-left font-semibold">Durum</th>
                            <th class="px-4 py-3 text-left font-semibold">Sepet</th>
                            <th class="px-4 py-3 text-left font-semibold">Ödeme</th>
                            <th class="px-4 py-3 text-left font-semibold">Tutar</th>
                            <th class="px-4 py-3 text-left font-semibold">Güncellendi</th>
                            <th class="px-4 py-3 text-left font-semibold">Not</th>
                            <th class="px-4 py-3 text-right font-semibold">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <?php
                                    $itemCount = isset($order['item_count']) ? (int) $order['item_count'] : null;
                                    $walletCents = (int)($order['wallet_deduction_cents'] ?? 0);
                                    $cardCents = (int)($order['card_charge_cents'] ?? 0);
                                    $paymentChannelValue = trim((string)($order['payment_channel'] ?? ''));
                                    $paymentReferenceValue = trim((string)($order['payment_reference'] ?? ''));
                                ?>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($order['order_no']) ?></p>
                                    <p class="text-[11px] text-slate-400">Oluşturma: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($order['created_at']))) ?></p>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <div class="font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($order['customer_name']) ?></div>
                                    <?php if (!empty($order['customer_email'])): ?>
                                        <div><?= htmlspecialchars($order['customer_email']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['customer_phone'])): ?>
                                        <div><?= htmlspecialchars($order['customer_phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['account_name'])): ?>
                                        <div class="mt-1 text-[11px] text-slate-400">Hesap: <?= htmlspecialchars($order['account_name']) ?><?php if (!empty($order['account_email'])): ?> · <?= htmlspecialchars($order['account_email']) ?><?php endif; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs">
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl text-xs font-semibold <?php switch ($order['status']) {
                                        case 'completed':
                                            echo 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200';
                                            break;
                                        case 'processing':
                                            echo 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200';
                                            break;
                                        case 'cancelled':
                                        case 'refunded':
                                            echo 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200';
                                            break;
                                        default:
                                            echo 'bg-slate-100 text-slate-600 dark:bg-slate-900/50 dark:text-slate-200';
                                    } ?>"><?= htmlspecialchars($orderStatuses[$order['status']] ?? $order['status']) ?></span>
                                </td>
                                <td class="px-4 py-4 align-top text-xs">
                                    <?php if ($itemCount !== null): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/40 px-2.5 py-1 font-semibold text-slate-600 dark:text-slate-200">
                                            <?= number_format($itemCount) ?> ürün
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-600 dark:text-slate-300 space-y-1">
                                    <?php if ($walletCents > 0): ?>
                                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200/70 dark:border-emerald-700/50 bg-emerald-50/80 dark:bg-emerald-900/30 px-2.5 py-1 font-semibold text-emerald-600 dark:text-emerald-200">
                                            💼 <?= formatCurrency($walletCents, $order['currency'] ?? 'TRY') ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($cardCents > 0): ?>
                                        <div class="inline-flex items-center gap-2 rounded-full border border-brand-200/70 dark:border-brand-700/50 bg-brand-50/60 dark:bg-brand-700/30 px-2.5 py-1 font-semibold text-brand-600 dark:text-brand-200">
                                            💳 <?= formatCurrency($cardCents, $order['currency'] ?? 'TRY') ?>
                                            <?php if ($paymentChannelValue !== ''): ?>
                                                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-300">(<?= htmlspecialchars($paymentChannelValue) ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($walletCents === 0 && $cardCents === 0): ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                    <?php if ($paymentReferenceValue !== ''): ?>
                                        <div class="text-[11px] text-slate-400">Ref: <?= htmlspecialchars($paymentReferenceValue) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs font-semibold text-brand-600 dark:text-brand-200"><?= formatCurrency((int)$order['total_cents'], $order['currency']) ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($order['updated_at']))) ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500 max-w-xs">
                                    <?= htmlspecialchars($order['notes'] ?? '') ?>
                                </td>
                                <td class="px-4 py-4 align-top text-right text-xs">
                                    <details class="group">
                                        <summary class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/50 cursor-pointer text-slate-600 dark:text-slate-300">Düzenle</summary>
                                        <div class="mt-3 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-slate-50/80 dark:bg-slate-950/50 space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-600 dark:text-slate-300">
                                                <div class="rounded-xl border border-emerald-200/70 dark:border-emerald-700/50 bg-white/80 dark:bg-slate-950/40 px-3 py-2">
                                                    <span class="block text-[11px] uppercase tracking-[0.3em] text-emerald-500 dark:text-emerald-300">Bakiye</span>
                                                    <span class="mt-1 block font-semibold text-emerald-600 dark:text-emerald-200"><?= formatCurrency($walletCents, $order['currency'] ?? 'TRY') ?></span>
                                                </div>
                                                <div class="rounded-xl border border-brand-200/70 dark:border-brand-700/50 bg-white/80 dark:bg-slate-950/40 px-3 py-2">
                                                    <span class="block text-[11px] uppercase tracking-[0.3em] text-brand-500 dark:text-brand-200">Kart / Harici</span>
                                                    <span class="mt-1 block font-semibold text-brand-600 dark:text-brand-200"><?= formatCurrency($cardCents, $order['currency'] ?? 'TRY') ?></span>
                                                    <?php if ($paymentChannelValue !== ''): ?>
                                                        <span class="block text-[11px] text-slate-400 mt-1">Kanal: <?= htmlspecialchars($paymentChannelValue) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($paymentReferenceValue !== ''): ?>
                                                        <span class="block text-[11px] text-slate-400">Ref: <?= htmlspecialchars($paymentReferenceValue) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php $orderItems = fetchOrderItemsAdmin($pdo, (int) $order['id']); ?>
                                            <?php if (!empty($orderItems)): ?>
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <h4 class="text-xs font-semibold text-slate-600 dark:text-slate-200">Sepet Kalemleri</h4>
                                                        <span class="text-[11px] text-slate-400">Toplam <?= number_format(count($orderItems)) ?> kayıt</span>
                                                    </div>
                                                    <div class="overflow-hidden rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/90 dark:bg-slate-950/40">
                                                        <table class="min-w-full text-[11px]">
                                                            <thead class="bg-slate-100/70 dark:bg-slate-900/60 text-slate-500 dark:text-slate-300">
                                                                <tr>
                                                                    <th class="px-3 py-2 text-left font-semibold">Ürün</th>
                                                                    <th class="px-3 py-2 text-right font-semibold">Adet</th>
                                                                    <th class="px-3 py-2 text-right font-semibold">Birim</th>
                                                                    <th class="px-3 py-2 text-right font-semibold">Ara Toplam</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                                                                <?php foreach ($orderItems as $item): ?>
                                                                    <tr>
                                                                        <td class="px-3 py-2 align-top">
                                                                            <div class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($item['product_name'] ?: ('Ürün #' . $item['product_id'])) ?></div>
                                                                            <?php if (!empty($item['product_id'])): ?>
                                                                                <div class="text-[10px] text-slate-400">ID: <?= (int) $item['product_id'] ?></div>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="px-3 py-2 align-top text-right font-semibold text-slate-600 dark:text-slate-200">×<?= number_format((int) $item['quantity']) ?></td>
                                                                        <td class="px-3 py-2 align-top text-right text-slate-600 dark:text-slate-300"><?= formatCurrency((int) $item['unit_price_cents'], $order['currency']) ?></td>
                                                                        <td class="px-3 py-2 align-top text-right font-semibold text-brand-600 dark:text-brand-200"><?= formatCurrency((int) $item['total_cents'], $order['currency']) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            <?php elseif (tableHasColumn($pdo, 'order_items', 'id')): ?>
                                                <p class="text-xs text-slate-500">Bu siparişe ait ürün kaydı bulunmuyor.</p>
                                            <?php endif; ?>
                                            <form method="post" class="space-y-3">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                <input type="hidden" name="action" value="update" />
                                                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>" />
                                                <input type="hidden" name="customer_id" value="<?= (int)($order['customer_id'] ?? 0) ?>" />
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Sipariş No</span>
                                                        <input type="text" name="order_no" value="<?= htmlspecialchars($order['order_no']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Durum</span>
                                                        <select name="status" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required>
                                                            <?php foreach ($orderStatuses as $key => $label): ?>
                                                                <option value="<?= htmlspecialchars($key) ?>" <?php if ($order['status'] === $key): ?>selected<?php endif; ?>><?= htmlspecialchars($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Müşteri Adı</span>
                                                        <input type="text" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Müşteri E-posta</span>
                                                        <input type="email" name="customer_email" value="<?= htmlspecialchars($order['customer_email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                                                    </label>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Telefon</span>
                                                        <input type="text" name="customer_phone" value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Tutar (<?= htmlspecialchars($order['currency']) ?>)</span>
                                                        <input type="text" name="total_amount" value="<?= htmlspecialchars(number_format(centsToPrice((int)$order['total_cents']), 2, '.', '')) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                                                    </label>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Bakiye katkısı (<?= htmlspecialchars($order['currency']) ?>)</span>
                                                        <input type="text" name="wallet_deduction" value="<?= htmlspecialchars((int)$order['wallet_deduction_cents'] > 0 ? number_format(centsToPrice((int)$order['wallet_deduction_cents']), 2, '.', '') : '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Örn. ₺100" />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Kart / diğer ödeme (<?= htmlspecialchars($order['currency']) ?>)</span>
                                                        <input type="text" name="card_charge" value="<?= htmlspecialchars((int)$order['card_charge_cents'] > 0 ? number_format(centsToPrice((int)$order['card_charge_cents']), 2, '.', '') : '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Örn. ₺250" />
                                                    </label>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Ödeme kanalı</span>
                                                        <input type="text" name="payment_channel" value="<?= htmlspecialchars($order['payment_channel'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Kredi kartı, havale vb." />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Ödeme referansı</span>
                                                        <input type="text" name="payment_reference" value="<?= htmlspecialchars($order['payment_reference'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Dekont veya işlem kodu" />
                                                    </label>
                                                </div>
                                                <label class="text-xs font-medium text-slate-500 space-y-1">
                                                    <span>Not</span>
                                                    <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                                                </label>
                                                <div class="flex items-center justify-between gap-3 pt-2">
                                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold">Kaydet</button>
                                                </div>
                                            </form>
                                            <form method="post" class="mt-3" onsubmit="return confirm('Bu siparişi silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                <input type="hidden" name="action" value="delete" />
                                                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>" />
                                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold">Sil</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold">Yeni Sipariş Oluştur</h3>
                    <p class="text-xs text-slate-500">Varsayılan sipariş numarası otomatik atanacaktır.</p>
                </div>
                <?php if ($newOrderNumber): ?>
                    <button type="button" class="text-xs text-brand-600 hover:text-brand-500" data-generate-order="<?= htmlspecialchars($newOrderNumber) ?>" data-target="order_no">Numarayı Kullan</button>
                <?php endif; ?>
            </div>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                <input type="hidden" name="action" value="create" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Sipariş No</span>
                        <input type="text" id="order_no" name="order_no" value="<?= htmlspecialchars($oldOrder['order_no'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="<?= htmlspecialchars($newOrderNumber ?: 'LS-...') ?>" />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Durum</span>
                        <select name="status" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required>
                            <?php foreach ($orderStatuses as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?php if (($oldOrder['status'] ?? 'pending') === $key): ?>selected<?php endif; ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Müşteri Adı</span>
                        <input type="text" name="customer_name" value="<?= htmlspecialchars($oldOrder['customer_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Müşteri E-posta</span>
                        <input type="email" name="customer_email" value="<?= htmlspecialchars($oldOrder['customer_email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                    </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Bakiye katkısı (<?= htmlspecialchars($oldOrder['currency'] ?? 'TRY') ?>)</span>
                        <input type="text" name="wallet_deduction" value="<?= htmlspecialchars($oldOrder['wallet_deduction'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Örn. ₺100" />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Kart / diğer ödeme (<?= htmlspecialchars($oldOrder['currency'] ?? 'TRY') ?>)</span>
                        <input type="text" name="card_charge" value="<?= htmlspecialchars($oldOrder['card_charge'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Örn. ₺250" />
                    </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Ödeme kanalı</span>
                        <input type="text" name="payment_channel" value="<?= htmlspecialchars($oldOrder['payment_channel'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Kredi kartı, havale vb." />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Ödeme referansı</span>
                        <input type="text" name="payment_reference" value="<?= htmlspecialchars($oldOrder['payment_reference'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Dekont veya işlem kodu" />
                    </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Telefon</span>
                        <input type="text" name="customer_phone" value="<?= htmlspecialchars($oldOrder['customer_phone'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Tutar (TRY)</span>
                        <input type="text" name="total_amount" value="<?= htmlspecialchars($oldOrder['total_amount'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="475.00" required />
                    </label>
                </div>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Not</span>
                    <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Teslimat bilgileri, referans notu..."><?= htmlspecialchars($oldOrder['notes'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">Sipariş Kaydet</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
