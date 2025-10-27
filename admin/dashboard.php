<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Kontrol Paneli';
$activeNav = 'dashboard';
$currentUser = $user;

$metrics = [
    'category_count' => 0,
    'product_count' => 0,
    'inventory_value' => 0,
    'order_count' => 0,
    'revenue_total' => 0,
    'pending_orders' => 0,
    'wallet_balance' => 0,
    'pending_topups' => 0,
];
$recentProducts = [];
$recentOrders = [];
$recentActivity = [];
$settings = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $metrics = getDashboardMetrics($pdo);
        $recentProducts = getRecentProducts($pdo, 6);
        $recentOrders = getRecentOrders($pdo, 6);
        $recentActivity = getRecentActivity($pdo, 8);
        $settings = fetchSettings($pdo);
    } catch (Throwable $e) {
        addFlash('error', 'Veriler yüklenirken bir hata oluştu: ' . $e->getMessage());
        $flashes = array_merge($flashes, getFlashes());
    }
}

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı kurulamadı</h2>
        <p class="text-xs mt-1">Lütfen <code>config/config.php</code> dosyasındaki bilgilerinizi kontrol ederek yeniden deneyin.</p>
    </div>
<?php else: ?>
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Kategoriler</p>
            <p class="mt-3 text-3xl font-semibold"><?= number_format($metrics['category_count']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Aktif kategori sayısı</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Ürünler</p>
            <p class="mt-3 text-3xl font-semibold"><?= number_format($metrics['product_count']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Katalogda yayınlanan ürünler</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Stok Değeri</p>
            <p class="mt-3 text-3xl font-semibold"><?= formatCurrency((int)$metrics['inventory_value']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Tüm ürünlerin toplam liste fiyatı</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Siparişler</p>
            <p class="mt-3 text-3xl font-semibold"><?= number_format($metrics['order_count']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Toplam kayıtlı sipariş</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Gelir</p>
            <p class="mt-3 text-3xl font-semibold"><?= formatCurrency((int)$metrics['revenue_total']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Tamamlanan ve işlenen siparişler</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bekleyen</p>
            <p class="mt-3 text-3xl font-semibold"><?= number_format($metrics['pending_orders']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Hızlı aksiyon bekleyen siparişler</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Müşteri Bakiyesi</p>
            <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-300"><?= formatCurrency((int)$metrics['wallet_balance']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Toplam müşteri bakiyesi</p>
        </article>
        <article class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Bekleyen Yükleme</p>
            <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-300"><?= number_format($metrics['pending_topups']) ?></p>
            <p class="mt-2 text-xs text-slate-500">Onay bekleyen bakiye talepleri</p>
        </article>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Son Eklenen Ürünler</h2>
                <a href="/admin/products.php" class="text-xs font-medium text-brand-600 hover:text-brand-500">Tümü</a>
            </div>
            <div class="p-5 space-y-4">
                <?php if (empty($recentProducts)): ?>
                    <p class="text-sm text-slate-500">Henüz ürün bilgisi bulunmuyor.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($recentProducts as $product): ?>
                            <li class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($product['name']) ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                        <span><?= htmlspecialchars($product['category_title']) ?></span>
                                        <span class="inline-flex h-1 w-1 rounded-full bg-slate-400"></span>
                                        <span><?= htmlspecialchars(date('d.m.Y H:i', strtotime($product['updated_at']))) ?></span>
                                    </p>
                                </div>
                                <span class="text-sm font-semibold text-brand-600 dark:text-brand-200"><?= htmlspecialchars($product['price_label']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Son Siparişler</h2>
                <a href="/admin/orders.php" class="text-xs font-medium text-brand-600 hover:text-brand-500">Siparişleri Yönet</a>
            </div>
            <div class="p-5 space-y-4">
                <?php if (empty($recentOrders)): ?>
                    <p class="text-sm text-slate-500">Kayıtlı sipariş bulunamadı.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($recentOrders as $order): ?>
                            <li class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($order['order_no']) ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                        <span><?= htmlspecialchars($order['customer_name']) ?></span>
                                        <span class="inline-flex h-1 w-1 rounded-full bg-slate-400"></span>
                                        <span><?= htmlspecialchars(date('d.m.Y H:i', strtotime($order['updated_at']))) ?></span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-semibold block text-brand-600 dark:text-brand-200"><?= formatCurrency((int)$order['total_cents'], $order['currency']) ?></span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($order['status']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Aktivite Akışı</h2>
                <a href="/admin/activity.php" class="text-xs font-medium text-brand-600 hover:text-brand-500">Tümünü Gör</a>
            </div>
            <div class="p-5 space-y-4 max-h-80 overflow-y-auto scrollbar-thin">
                <?php if (empty($recentActivity)): ?>
                    <p class="text-sm text-slate-500">Kayıtlı aktivite bulunamadı.</p>
                <?php else: ?>
                    <ul class="space-y-4">
                        <?php foreach ($recentActivity as $activity): ?>
                            <li class="flex items-start gap-3">
                                <span class="mt-1 text-lg" aria-hidden="true">🔔</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        <?= htmlspecialchars(ucfirst($activity['action'])) ?>
                                        <?php if (!empty($activity['entity'])): ?>
                                            · <span class="text-xs uppercase tracking-widest text-slate-500"><?= htmlspecialchars($activity['entity']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($activity['message'])): ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars($activity['message']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($activity['created_at']))) ?> · <?= htmlspecialchars($activity['user_name'] ?? 'Sistem') ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-5 border-b border-slate-200/70 dark:border-slate-800/70">
                <h2 class="text-sm font-semibold">Destek İletişimi</h2>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">E-posta</p>
                    <p class="mt-1 font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars($settings['support_email'] ?? 'destek@lisansonay.com') ?></p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Telefon</p>
                    <p class="mt-1 font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars($settings['support_phone'] ?? '+90 555 555 55 55') ?></p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">WhatsApp</p>
                    <p class="mt-1 font-medium text-brand-600 dark:text-brand-200 break-words">
                        <a href="<?= htmlspecialchars($settings['whatsapp_link'] ?? 'https://wa.me/905555555555') ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars($settings['whatsapp_link'] ?? 'https://wa.me/905555555555') ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
