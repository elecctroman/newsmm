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

$pageTitle = 'Kategoriler';
$activeNav = 'categories';
$currentUser = $user;

$formErrors = [];
$oldCreate = [
    'slug' => '',
    'title' => '',
    'description' => '',
    'emoji' => '',
    'sort_order' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('category-actions', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'create':
                    $payload = [
                        'slug' => sanitizeSlug((string)($_POST['slug'] ?? '')),
                        'title' => trim((string)($_POST['title'] ?? '')),
                        'description' => trim((string)($_POST['description'] ?? '')),
                        'emoji' => trim((string)($_POST['emoji'] ?? '')),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                    ];
                    $oldCreate = $payload;

                    if ($payload['slug'] === '') {
                        $formErrors[] = 'Slug alanı boş bırakılamaz.';
                    }
                    if ($payload['title'] === '') {
                        $formErrors[] = 'Kategori adı gereklidir.';
                    }
                    if ($payload['description'] === '') {
                        $formErrors[] = 'Açıklama gereklidir.';
                    }
                    if ($payload['emoji'] === '') {
                        $formErrors[] = 'Kategori için emoji belirleyin.';
                    }
                    if (empty($formErrors)) {
                        $categoryId = createCategory($pdo, $payload);
                        logActivity($pdo, (int) $user['id'], 'create', 'category', $categoryId, 'Yeni kategori oluşturuldu');
                        addFlash('success', 'Kategori başarıyla oluşturuldu.');
                        redirect('categories.php');
                    }
                    break;
                case 'update':
                    $categoryId = (int)($_POST['id'] ?? 0);
                    if ($categoryId <= 0) {
                        $formErrors[] = 'Geçersiz kategori seçimi.';
                        break;
                    }
                    $payload = [
                        'slug' => sanitizeSlug((string)($_POST['slug'] ?? '')),
                        'title' => trim((string)($_POST['title'] ?? '')),
                        'description' => trim((string)($_POST['description'] ?? '')),
                        'emoji' => trim((string)($_POST['emoji'] ?? '')),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                    ];
                    if ($payload['slug'] === '' || $payload['title'] === '' || $payload['description'] === '' || $payload['emoji'] === '') {
                        $formErrors[] = 'Tüm alanları doldurun.';
                        break;
                    }
                    updateCategory($pdo, $categoryId, $payload);
                    logActivity($pdo, (int) $user['id'], 'update', 'category', $categoryId, 'Kategori bilgileri güncellendi');
                    addFlash('success', 'Kategori güncellendi.');
                    redirect('categories.php');
                    break;
                case 'delete':
                    $categoryId = (int)($_POST['id'] ?? 0);
                    if ($categoryId <= 0) {
                        $formErrors[] = 'Silinecek kategori bulunamadı.';
                        break;
                    }
                    deleteCategory($pdo, $categoryId);
                    logActivity($pdo, (int) $user['id'], 'delete', 'category', $categoryId, 'Kategori silindi');
                    addFlash('success', 'Kategori silindi.');
                    redirect('categories.php');
                    break;
                default:
                    $formErrors[] = 'Bilinmeyen işlem.';
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $formErrors[] = 'Aynı slug değerine sahip başka bir kategori mevcut.';
            } else {
                $formErrors[] = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

$actionToken = issueCsrfToken('category-actions');

$categories = [];
$productCounts = [];

if (isset($pdo) && $pdo instanceof PDO) {
    $categories = fetchAllCategories($pdo);
    $countStmt = $pdo->query('SELECT category_id, COUNT(*) AS total FROM products GROUP BY category_id');
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $productCounts[(int)$row['category_id']] = (int)$row['total'];
    }
}

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı gerekli</h2>
        <p class="text-xs mt-1">Kategori yönetimi için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Katalog Kategorileri</h2>
                <p class="text-xs text-slate-500">Kategori sıralamasını düzenleyin, açıklamaları güncelleyin ve yeni alanlar ekleyin.</p>
            </div>
            <span class="text-xs text-slate-400">Toplam <?= number_format(count($categories)) ?> kategori</span>
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
                            <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                            <th class="px-4 py-3 text-left font-semibold">Slug</th>
                            <th class="px-4 py-3 text-left font-semibold">Ürün Sayısı</th>
                            <th class="px-4 py-3 text-left font-semibold">Sıra</th>
                            <th class="px-4 py-3 text-left font-semibold">Güncellendi</th>
                            <th class="px-4 py-3 text-right font-semibold">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                        <span class="text-lg" aria-hidden="true"><?= htmlspecialchars($category['emoji']) ?></span>
                                        <?= htmlspecialchars($category['title']) ?>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?= htmlspecialchars($category['description']) ?></p>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= htmlspecialchars($category['slug']) ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= number_format($productCounts[(int)$category['id']] ?? 0) ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= (int)$category['sort_order'] ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($category['updated_at']))) ?></td>
                                <td class="px-4 py-4 align-top text-right text-xs">
                                    <details class="group">
                                        <summary class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/50 cursor-pointer text-slate-600 dark:text-slate-300">Düzenle</summary>
                                        <div class="mt-3 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-slate-50/80 dark:bg-slate-950/50 space-y-3">
                                            <form method="post" class="space-y-3">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                <input type="hidden" name="action" value="update" />
                                                <input type="hidden" name="id" value="<?= (int)$category['id'] ?>" />
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Slug</span>
                                                        <input type="text" name="slug" value="<?= htmlspecialchars($category['slug']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Emoji</span>
                                                        <input type="text" name="emoji" value="<?= htmlspecialchars($category['emoji']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" maxlength="4" required />
                                                    </label>
                                                </div>
                                                <label class="text-xs font-medium text-slate-500 space-y-1">
                                                    <span>Başlık</span>
                                                    <input type="text" name="title" value="<?= htmlspecialchars($category['title']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                                                </label>
                                                <label class="text-xs font-medium text-slate-500 space-y-1">
                                                    <span>Açıklama</span>
                                                    <textarea name="description" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required><?= htmlspecialchars($category['description']) ?></textarea>
                                                </label>
                                                <label class="text-xs font-medium text-slate-500 space-y-1">
                                                    <span>Sıra</span>
                                                    <input type="number" name="sort_order" value="<?= (int)$category['sort_order'] ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                                                </label>
                                                <div class="flex items-center justify-between gap-3 pt-2">
                                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold">Kaydet</button>
                                                </div>
                                            </form>
                                            <form method="post" class="mt-3" onsubmit="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                <input type="hidden" name="action" value="delete" />
                                                <input type="hidden" name="id" value="<?= (int)$category['id'] ?>" />
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
            <h3 class="text-sm font-semibold">Yeni Kategori Oluştur</h3>
            <p class="text-xs text-slate-500 mt-1">Kategori slug değeri benzersiz olmalıdır.</p>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                <input type="hidden" name="action" value="create" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Slug</span>
                        <input type="text" name="slug" value="<?= htmlspecialchars($oldCreate['slug'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Emoji</span>
                        <input type="text" name="emoji" value="<?= htmlspecialchars($oldCreate['emoji'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" maxlength="4" required />
                    </label>
                </div>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Başlık</span>
                    <input type="text" name="title" value="<?= htmlspecialchars($oldCreate['title'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Açıklama</span>
                    <textarea name="description" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required><?= htmlspecialchars($oldCreate['description'] ?? '') ?></textarea>
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Sıra</span>
                    <input type="number" name="sort_order" value="<?= (int)($oldCreate['sort_order'] ?? 0) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                </label>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">Kategoriyi Oluştur</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
