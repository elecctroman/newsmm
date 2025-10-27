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

$pageTitle = 'Sepetim';
$activeNav = 'cart';
$currentCustomer = $customer;
$databaseWarning = '';
$cartSnapshot = [
    'items' => [],
    'subtotal_cents' => 0,
    'total_cents' => 0,
    'currency' => 'TRY',
    'count' => 0,
];
$balanceCents = 0;
$checkoutErrors = [];
$noteField = '';
$branding = loadBrandingSettings(isset($pdo) && $pdo instanceof PDO ? $pdo : null);

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Sepet verileri yüklenemiyor.';
} else {
    try {
        $balanceCents = getCustomerBalance($pdo, (int) $customer['id']);
        $cartSnapshot = fetchCartSnapshot($pdo, $customer);
    } catch (Throwable $snapshotException) {
        $databaseWarning = 'Sepet verileri alınırken bir hata oluştu: ' . $snapshotException->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $noteField = trim((string) ($_POST['order_note'] ?? ''));
    if (!validateCsrfToken('customer-cart-checkout', $_POST['_token'] ?? null)) {
        $checkoutErrors[] = 'Ödeme talebi doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $checkoutErrors[] = 'Siparişi tamamlamak için veritabanı bağlantısı gerekiyor.';
    } else {
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            $cartSnapshot = fetchCartSnapshot($pdo, $customer);
            if (($cartSnapshot['count'] ?? 0) === 0) {
                throw new RuntimeException('Sepetinizde ürün bulunmuyor.');
            }

            $totalCents = (int) ($cartSnapshot['total_cents'] ?? 0);
            if ($totalCents <= 0) {
                throw new RuntimeException('Sepet tutarı hesaplanamadı.');
            }

            $balanceCents = getCustomerBalance($pdo, (int) $customer['id']);
            if ($balanceCents < $totalCents) {
                throw new RuntimeException('Bakiyeniz siparişi tamamlamak için yeterli değil.');
            }

            $orderItems = [];
            foreach ($cartSnapshot['items'] as $item) {
                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['price_cents'],
                    'total_cents' => $item['line_total_cents'],
                ];
            }

            $orderNotes = 'Online sepet siparişi';
            if ($noteField !== '') {
                $orderNotes .= ' · Müşteri notu: ' . $noteField;
            }

            $orderPayload = [
                'customer_id' => (int) $customer['id'],
                'order_no' => generateOrderNumber($pdo),
                'customer_name' => $customer['name'] ?? '',
                'customer_email' => $customer['email'] ?? null,
                'customer_phone' => $customer['phone'] ?? null,
                'status' => 'pending',
                'total_cents' => $totalCents,
                'currency' => 'TRY',
                'notes' => $orderNotes,
                'items' => $orderItems,
            ];

            $orderId = createOrder($pdo, $orderPayload);
            adjustCustomerBalance(
                $pdo,
                (int) $customer['id'],
                -$totalCents,
                'purchase',
                'Sepet siparişi #' . $orderPayload['order_no'],
                'order',
                $orderId
            );

            clearCustomerCart($pdo, (int) $customer['id']);
            syncCustomerCartToSession($pdo, (int) $customer['id']);
            logActivity($pdo, null, 'order.checkout', 'orders', $orderId, 'Müşteri portalından sepet siparişi oluşturuldu');

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            addFlash('success', 'Sepetinizdeki ürünler için sipariş oluşturuldu.');
            redirect('/account/orders.php?recent=' . urlencode((string) $orderId));
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $checkoutErrors[] = $e->getMessage();
            try {
                $cartSnapshot = fetchCartSnapshot($pdo, $customer);
                $balanceCents = getCustomerBalance($pdo, (int) $customer['id']);
            } catch (Throwable $refreshException) {
                $databaseWarning = 'Sepet verileri yenilenirken hata oluştu: ' . $refreshException->getMessage();
            }
        }
    }
}

$cartFormToken = issueCsrfToken('cart-action');
$checkoutToken = issueCsrfToken('customer-cart-checkout');

require __DIR__ . '/../src/customer_page_start.php';
?>
<section class="space-y-6">
    <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Sepet özeti</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Sepetinizdeki ürünleri gözden geçirin ve ödeme yapın.</p>
            </div>
            <div class="text-sm text-slate-600 dark:text-slate-300">
                <p>Ürün sayısı: <strong><?= number_format($cartSnapshot['count'] ?? 0) ?></strong></p>
                <p>Tutar: <strong class="text-brand-600 dark:text-brand-200"><?= formatCurrency((int) ($cartSnapshot['total_cents'] ?? 0)) ?></strong></p>
                <p>Bakiyem: <strong class="text-emerald-600 dark:text-emerald-300"><?= formatCurrency((int) $balanceCents) ?></strong></p>
            </div>
        </div>
        <?php if ($databaseWarning !== ''): ?>
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                <?= htmlspecialchars($databaseWarning) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($checkoutErrors)): ?>
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm space-y-2">
                <?php foreach ($checkoutErrors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <?php if (empty($cartSnapshot['items'])): ?>
        <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6 text-sm text-slate-500 dark:text-slate-300">
            Sepetinizde ürün bulunmuyor. <a href="/index.php#catalog" class="text-brand-600 hover:text-brand-500 font-semibold">Kataloğa dönerek ürün ekleyin.</a>
        </article>
    <?php else: ?>
        <article class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Ürün</th>
                            <th class="px-4 py-3 text-left font-semibold">Birim Fiyat</th>
                            <th class="px-4 py-3 text-left font-semibold">Adet</th>
                            <th class="px-4 py-3 text-left font-semibold">Tutar</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800/70">
                        <?php foreach ($cartSnapshot['items'] as $item): ?>
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($item['name']) ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kategori: <?= htmlspecialchars($item['category']['title'] ?? '') ?></p>
                                    <?php if (!empty($item['note'])): ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Not: <?= htmlspecialchars($item['note']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-slate-600 dark:text-slate-300">
                                    <?= formatCurrency((int) $item['price_cents']) ?>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <form method="post" action="/cart.php" class="flex items-center gap-2">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($cartFormToken) ?>" />
                                        <input type="hidden" name="form_key" value="cart-action" />
                                        <input type="hidden" name="cart_action" value="update" />
                                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>" />
                                        <input type="hidden" name="redirect" value="/account/cart.php" />
                                        <input type="number" name="quantity" min="0" max="999" value="<?= (int) $item['quantity'] ?>" class="w-20 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-2 py-1 text-sm" />
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 px-2 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-200">Güncelle</button>
                                    </form>
                                </td>
                                <td class="px-4 py-4 align-top text-sm font-semibold text-brand-600 dark:text-brand-200">
                                    <?= formatCurrency((int) $item['line_total_cents']) ?>
                                </td>
                                <td class="px-4 py-4 align-top text-right">
                                    <form method="post" action="/cart.php" onsubmit="return confirm('Bu ürünü sepetten kaldırmak istediğinize emin misiniz?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($cartFormToken) ?>" />
                                        <input type="hidden" name="form_key" value="cart-action" />
                                        <input type="hidden" name="cart_action" value="remove" />
                                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>" />
                                        <input type="hidden" name="redirect" value="/account/cart.php" />
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-red-200 dark:border-red-700 px-3 py-1.5 text-xs font-semibold text-red-600 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">Kaldır</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 border-t border-slate-200 dark:border-slate-800 text-sm">
                <form method="post" action="/cart.php">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($cartFormToken) ?>" />
                    <input type="hidden" name="form_key" value="cart-action" />
                    <input type="hidden" name="cart_action" value="clear" />
                    <input type="hidden" name="redirect" value="/account/cart.php" />
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-red-600 dark:hover:text-red-300">Sepeti Temizle</button>
                </form>
                <div class="text-right text-sm text-slate-600 dark:text-slate-300">
                    <p>Toplam: <strong class="text-brand-600 dark:text-brand-200"><?= formatCurrency((int) $cartSnapshot['total_cents']) ?></strong></p>
                </div>
            </div>
        </article>

        <form method="post" class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/60 shadow-sm p-6 space-y-4 mt-6">
            <input type="hidden" name="action" value="checkout" />
            <input type="hidden" name="_token" value="<?= htmlspecialchars($checkoutToken) ?>" />
            <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
                <span>Sipariş notu (isteğe bağlı)</span>
                <textarea name="order_note" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-4 py-3 text-sm" placeholder="Teslimat veya kullanım notlarınızı ekleyin."><?= htmlspecialchars($noteField) ?></textarea>
            </label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Sipariş oluşturulduğunda bakiyenizden <?= formatCurrency((int) ($cartSnapshot['total_cents'] ?? 0)) ?> düşülecektir.</p>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-3 shadow disabled:opacity-60" <?php if (($cartSnapshot['count'] ?? 0) === 0): ?>disabled<?php endif; ?>>
                Sepeti Onayla ve Öde
            </button>
        </form>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/../src/customer_page_end.php';
