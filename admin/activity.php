<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Aktivite Kayıtları';
$activeNav = 'activity';
$currentUser = $user;

$logs = [];
if (isset($pdo) && $pdo instanceof PDO) {
    $logs = getRecentActivity($pdo, 100);
}

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı gerekli</h2>
        <p class="text-xs mt-1">Aktivite kayıtlarına erişmek için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="space-y-4">
        <div>
            <h2 class="text-base font-semibold">Son 100 Aktivite</h2>
            <p class="text-xs text-slate-500">Oluşturma, güncelleme ve silme işlemlerinin geçmişi.</p>
        </div>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100/70 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Zaman</th>
                            <th class="px-4 py-3 text-left font-semibold">Kullanıcı</th>
                            <th class="px-4 py-3 text-left font-semibold">İşlem</th>
                            <th class="px-4 py-3 text-left font-semibold">Varlık</th>
                            <th class="px-4 py-3 text-left font-semibold">Mesaj</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($log['created_at']))) ?></td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-200"><?= htmlspecialchars($log['user_name'] ?? 'Sistem') ?></td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-700 dark:text-slate-100"><?= htmlspecialchars($log['action']) ?></td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    <?php if (!empty($log['entity'])): ?>
                                        <?= htmlspecialchars($log['entity']) ?>
                                        <?php if (!empty($log['entity_id'])): ?>
                                            <span class="text-slate-400">#<?= (int)$log['entity_id'] ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 max-w-xl">
                                    <?= htmlspecialchars($log['message'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
