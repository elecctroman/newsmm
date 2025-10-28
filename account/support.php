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

$pageTitle = 'Destek Taleplerim';
$activeNav = 'support';
$currentCustomer = $customer;

$supportErrors = [];
$databaseWarning = '';
$ticketStatusLabels = [
    'open' => 'Açık',
    'customer_reply' => 'Yanıt Bekleniyor',
    'staff_reply' => 'Destek Yanıtı',
    'closed' => 'Kapandı',
];
$ticketPriorityLabels = [
    'low' => 'Düşük',
    'normal' => 'Normal',
    'high' => 'Yüksek',
    'urgent' => 'Acil',
];

$viewTicketId = isset($_GET['ticket']) ? (int) $_GET['ticket'] : 0;
$viewTicket = null;
$ticketMessages = [];
$ticketList = [];
$branding = loadBrandingSettings(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
$categoryMenu = loadCategoryMenu(isset($pdo) && $pdo instanceof PDO ? $pdo : null);

if (!isset($pdo) || !$pdo instanceof PDO) {
    $databaseWarning = 'Veritabanı bağlantısı kurulamadı. Destek talepleri görüntülenemiyor.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken('customer-support', $_POST['_token'] ?? null)) {
            $supportErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
        } else {
            $action = $_POST['action'] ?? '';
            switch ($action) {
                case 'create':
                    $subject = trim((string)($_POST['subject'] ?? ''));
                    $priority = $_POST['priority'] ?? 'normal';
                    $message = trim((string)($_POST['message'] ?? ''));
                    if ($subject === '') {
                        $supportErrors[] = 'Konu başlığı zorunludur.';
                    }
                    if ($message === '') {
                        $supportErrors[] = 'Lütfen talebinizi açıklayan bir mesaj yazın.';
                    }
                    if (!isset($ticketPriorityLabels[$priority])) {
                        $priority = 'normal';
                    }
                    if (empty($supportErrors)) {
                        $ticketId = createSupportTicket($pdo, (int) $customer['id'], $subject, $priority, $message);
                        logActivity($pdo, null, 'support.create', 'support_tickets', $ticketId, 'Müşteri yeni destek talebi oluşturdu');
                        addFlash('success', 'Destek talebiniz oluşturuldu.');
                        redirect('/account/support.php?ticket=' . $ticketId);
                    }
                    break;
                case 'reply':
                    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                    $message = trim((string)($_POST['message'] ?? ''));
                    if ($ticketId <= 0) {
                        $supportErrors[] = 'Geçerli bir destek talebi seçilmedi.';
                        break;
                    }
                    if ($message === '') {
                        $supportErrors[] = 'Yanıt mesajı boş olamaz.';
                        break;
                    }
                    $ticket = fetchTicketById($pdo, (int) $customer['id'], $ticketId);
                    if (!$ticket) {
                        $supportErrors[] = 'Destek talebine erişim yetkiniz yok.';
                        break;
                    }
                    addSupportMessage($pdo, $ticketId, [
                        'author_type' => 'customer',
                        'customer_id' => (int) $customer['id'],
                        'message' => $message,
                    ]);
                    logActivity($pdo, null, 'support.reply', 'support_tickets', $ticketId, 'Müşteri destek talebine yanıt verdi');
                    addFlash('success', 'Mesajınız gönderildi.');
                    redirect('/account/support.php?ticket=' . $ticketId);
                    break;
                case 'close':
                    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                    if ($ticketId <= 0) {
                        $supportErrors[] = 'Geçerli bir destek talebi seçilmedi.';
                        break;
                    }
                    $ticket = fetchTicketById($pdo, (int) $customer['id'], $ticketId);
                    if (!$ticket) {
                        $supportErrors[] = 'Destek talebine erişim yetkiniz yok.';
                        break;
                    }
                    closeSupportTicket($pdo, $ticketId);
                    logActivity($pdo, null, 'support.close', 'support_tickets', $ticketId, 'Müşteri destek talebini kapattı');
                    addFlash('success', 'Destek talebi kapatıldı.');
                    redirect('/account/support.php');
                    break;
                default:
                    $supportErrors[] = 'Bilinmeyen işlem türü.';
            }
        }
    }

    $ticketList = fetchCustomerTickets($pdo, (int) $customer['id']);
    if ($viewTicketId > 0) {
        $viewTicket = fetchTicketById($pdo, (int) $customer['id'], $viewTicketId);
        if ($viewTicket) {
            $ticketMessages = fetchTicketMessages($pdo, $viewTicketId);
        } else {
            addFlash('error', 'Destek talebi bulunamadı.');
            redirect('/account/support.php');
        }
    }
}

$token = issueCsrfToken('customer-support');

require __DIR__ . '/../src/customer_page_start.php';
?>
<section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Destek taleplerim</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">Toplam <?= number_format(count($ticketList)) ?></span>
        </div>
        <?php if ($databaseWarning !== ''): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-3 text-sm">
                <?= htmlspecialchars($databaseWarning) ?>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($ticketList as $ticket): ?>
                    <a href="/account/support.php?ticket=<?= (int) $ticket['id'] ?>" class="block rounded-2xl border border-slate-200/60 dark:border-slate-800/60 px-4 py-3 text-sm <?php if ($viewTicketId === (int) $ticket['id']): ?>bg-brand-50 text-brand-700 dark:bg-brand-600/20 dark:text-brand-100<?php else: ?>bg-white/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60<?php endif; ?>">
                        <p class="font-semibold">#<?= htmlspecialchars($ticket['ticket_no']) ?></p>
                        <p class="text-xs mt-1"><?= htmlspecialchars($ticket['subject']) ?></p>
                        <p class="text-[11px] mt-1 text-slate-400">Durum: <?= htmlspecialchars($ticketStatusLabels[$ticket['status']] ?? $ticket['status']) ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($ticketList)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Henüz destek talebi bulunmuyor.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Yeni talep oluştur</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Sorununuzu veya isteğinizi detaylandırın.</p>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                <input type="hidden" name="action" value="create" />
                <label class="text-xs font-semibold text-slate-500 dark:text-slate-300 space-y-1">
                    <span>Konu</span>
                    <input type="text" name="subject" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
                </label>
                <label class="text-xs font-semibold text-slate-500 dark:text-slate-300 space-y-1">
                    <span>Öncelik</span>
                    <select name="priority" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm">
                        <?php foreach ($ticketPriorityLabels as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500 dark:text-slate-300 space-y-1">
                    <span>Mesaj</span>
                    <textarea name="message" rows="4" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Lütfen yaşadığınız sorunu detaylandırın"></textarea>
                </label>
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2">Talep Oluştur</button>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <?php if (!empty($supportErrors)): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4 space-y-2 mb-4">
                <?php foreach ($supportErrors as $error): ?>
                    <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($viewTicket): ?>
            <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
                <div class="px-6 py-5 border-b border-slate-200/60 dark:border-slate-800/60 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">#<?= htmlspecialchars($viewTicket['ticket_no']) ?> · <?= htmlspecialchars($viewTicket['subject']) ?></h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Durum: <?= htmlspecialchars($ticketStatusLabels[$viewTicket['status']] ?? $viewTicket['status']) ?> · Öncelik: <?= htmlspecialchars($ticketPriorityLabels[$viewTicket['priority']] ?? $viewTicket['priority']) ?></p>
                    </div>
                    <?php if ($viewTicket['status'] !== 'closed'): ?>
                        <form method="post" onsubmit="return confirm('Bu destek talebini kapatmak istediğinize emin misiniz?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                            <input type="hidden" name="action" value="close" />
                            <input type="hidden" name="ticket_id" value="<?= (int) $viewTicket['id'] ?>" />
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/70">Talebi Kapat</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <?php if (empty($ticketMessages)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Bu talep için henüz mesaj yok.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($ticketMessages as $message): ?>
                                <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 px-4 py-3">
                                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                        <span>
                                            <?php if ($message['author_type'] === 'staff'): ?>
                                                Destek Ekibi · <?= htmlspecialchars($message['staff_name'] ?? 'Temsilci') ?>
                                            <?php else: ?>
                                                Siz
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
                <?php if ($viewTicket['status'] !== 'closed'): ?>
                    <div class="px-6 py-5 border-t border-slate-200/60 dark:border-slate-800/60">
                        <form method="post" class="space-y-3">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
                            <input type="hidden" name="action" value="reply" />
                            <input type="hidden" name="ticket_id" value="<?= (int) $viewTicket['id'] ?>" />
                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-300 space-y-1">
                                <span>Yanıtınız</span>
                                <textarea name="message" rows="4" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Destek ekibine iletmek istediğiniz detaylar"></textarea>
                            </label>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2">Mesaj Gönder</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/30 px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                Bir destek talebi seçerek detayları görüntüleyebilirsiniz.
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
require __DIR__ . '/../src/customer_page_end.php';
