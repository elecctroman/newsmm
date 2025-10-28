<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';

ensureSession();
$user = requireAuth();
$flashes = getFlashes();

$pageTitle = 'Destek Talepleri';
$activeNav = 'support';
$currentUser = $user;

$supportErrors = [];
$databaseWarning = '';
$ticketStatusLabels = [
    'open' => 'Açık',
    'customer_reply' => 'Müşteri Yanıtı',
    'staff_reply' => 'Destek Yanıtı',
    'closed' => 'Kapalı',
];
$ticketPriorityLabels = [
    'low' => 'Düşük',
    'normal' => 'Normal',
    'high' => 'Yüksek',
    'urgent' => 'Acil',
];

$selectedId = isset($_GET['ticket']) ? (int) $_GET['ticket'] : 0;
$ticketList = [];
$selectedTicket = null;
$ticketMessages = [];
$settings = [];

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Destek kayıtları görüntülenemiyor.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken('admin-support', $_POST['_token'] ?? null)) {
            $supportErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
        } else {
            $action = $_POST['action'] ?? '';
            switch ($action) {
                case 'reply':
                    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                    $message = trim((string)($_POST['message'] ?? ''));
                    $nextStatus = $_POST['status'] ?? '';
                    if ($ticketId <= 0) {
                        $supportErrors[] = 'Geçerli bir talep seçin.';
                        break;
                    }
                    if ($message === '') {
                        $supportErrors[] = 'Yanıt mesajı boş olamaz.';
                        break;
                    }
                    $ticket = fetchSupportTicketAdmin($pdo, $ticketId);
                    if (!$ticket) {
                        $supportErrors[] = 'Destek talebi bulunamadı.';
                        break;
                    }
                    $statusToSet = isset($ticketStatusLabels[$nextStatus]) ? $nextStatus : null;
                    respondSupportTicket($pdo, $ticketId, (int) $user['id'], $message, $statusToSet);
                    logActivity($pdo, (int) $user['id'], 'support.reply', 'support_tickets', $ticketId, 'Destek talebine yanıt verildi');
                    addFlash('success', 'Müşteri talebine yanıt gönderildi.');
                    redirect('/admin/support.php?ticket=' . $ticketId);
                    break;
                case 'status':
                    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                    $status = $_POST['status'] ?? '';
                    if ($ticketId <= 0 || !isset($ticketStatusLabels[$status])) {
                        $supportErrors[] = 'Geçersiz talep veya durum seçimi.';
                        break;
                    }
                    updateTicketStatus($pdo, $ticketId, $status);
                    logActivity($pdo, (int) $user['id'], 'support.status', 'support_tickets', $ticketId, 'Destek talebi durumu güncellendi');
                    addFlash('success', 'Destek talebi durumu güncellendi.');
                    redirect('/admin/support.php?ticket=' . $ticketId);
                    break;
                default:
                    $supportErrors[] = 'Bilinmeyen işlem.';
            }
        }
    }

    $ticketList = fetchSupportTicketsAdmin($pdo);
    if ($selectedId > 0) {
        $selectedTicket = fetchSupportTicketAdmin($pdo, $selectedId);
        if ($selectedTicket) {
            $ticketMessages = fetchTicketMessages($pdo, $selectedId);
        } else {
            addFlash('error', 'Destek talebi bulunamadı.');
            redirect('/admin/support.php');
        }
    }
    try {
        $settings = fetchSettings($pdo);
    } catch (Throwable $settingsException) {
        $settings = [];
    }
}

$token = issueCsrfToken('admin-support');

$branding = buildBrandingContext($settings);

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if ($databaseWarning !== ''): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <?= htmlspecialchars($databaseWarning) ?>
    </div>
<?php else: ?>
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Destek Talepleri</h2>
                <span class="text-xs text-slate-400">Toplam <?= number_format(count($ticketList)) ?> kayıt</span>
            </div>
            <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm divide-y divide-slate-200/60 dark:divide-slate-800/60">
                <?php foreach ($ticketList as $ticket): ?>
                    <a href="/admin/support.php?ticket=<?= (int) $ticket['id'] ?>" class="block px-4 py-3 text-sm <?php if ($selectedId === (int) $ticket['id']): ?>bg-brand-50 text-brand-700 dark:bg-brand-600/20 dark:text-brand-100<?php else: ?>text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/60<?php endif; ?>">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">#<?= htmlspecialchars($ticket['ticket_no']) ?></span>
                            <span class="text-[11px] text-slate-400"><?= htmlspecialchars(date('d.m', strtotime($ticket['updated_at']))) ?></span>
                        </div>
                        <p class="mt-1 text-xs"><?= htmlspecialchars($ticket['subject']) ?></p>
                        <p class="mt-1 text-[11px] text-slate-400">Müşteri: <?= htmlspecialchars($ticket['customer_name']) ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($ticketList)): ?>
                    <p class="px-4 py-6 text-sm text-slate-500">Destek kaydı bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="xl:col-span-2">
            <?php if (!empty($supportErrors)): ?>
                <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4 space-y-2 mb-4">
                    <?php foreach ($supportErrors as $error): ?>
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($selectedTicket): ?>
                <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
                    <div class="px-6 py-5 border-b border-slate-200/60 dark:border-slate-800/60 flex flex-col gap-3">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">#<?= htmlspecialchars($selectedTicket['ticket_no']) ?> · <?= htmlspecialchars($selectedTicket['subject']) ?></h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Müşteri: <?= htmlspecialchars($selectedTicket['customer_name']) ?> · <?= htmlspecialchars($selectedTicket['customer_email']) ?></p>
                            </div>
                            <form method="post" class="flex items-center gap-2 text-xs">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                                <input type="hidden" name="action" value="status" />
                                <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id'] ?>" />
                                <label for="status-select" class="text-slate-500">Durum:</label>
                                <select id="status-select" name="status" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2" data-autosubmit>
                                    <?php foreach ($ticketStatusLabels as $key => $label): ?>
                                        <option value="<?= htmlspecialchars($key) ?>" <?php if ($selectedTicket['status'] === $key): ?>selected<?php endif; ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Öncelik: <?= htmlspecialchars($ticketPriorityLabels[$selectedTicket['priority']] ?? $selectedTicket['priority']) ?> · Güncelleme: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($selectedTicket['updated_at']))) ?></p>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <?php if (empty($ticketMessages)): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Bu talep için henüz mesaj bulunmuyor.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($ticketMessages as $message): ?>
                                    <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 px-4 py-3">
                                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                            <span>
                                                <?php if ($message['author_type'] === 'staff'): ?>
                                                    Destek Ekibi · <?= htmlspecialchars($message['staff_name'] ?? 'Temsilci') ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($selectedTicket['customer_name']) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span><?= htmlspecialchars(date('d.m.Y H:i', strtotime($message['created_at']))) ?></span>
                                        </div>
                                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($selectedTicket['status'] !== 'closed'): ?>
                        <div class="px-6 py-5 border-t border-slate-200/60 dark:border-slate-800/60">
                            <form method="post" class="space-y-3">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                                <input type="hidden" name="action" value="reply" />
                                <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id'] ?>" />
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-300 space-y-1">
                                    <span>Yanıtınız</span>
                                    <textarea name="message" rows="4" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Müşteriye iletilecek mesajınızı yazın"></textarea>
                                </label>
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-300 space-y-1">
                                    <span>Yanıt sonrası durum</span>
                                    <select name="status" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm">
                                        <option value="">Otomatik (Destek Yanıtı)</option>
                                        <?php foreach ($ticketStatusLabels as $key => $label): ?>
                                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2">Yanıt Gönder</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 bg-white/60 dark:bg-slate-900/40 px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                    İncelemek istediğiniz destek talebini soldaki listeden seçin.
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
