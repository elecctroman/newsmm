<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
$user = requireAuth();
requirePermission($user, 'catalog.categories.view');
$flashes = getFlashes();

$pageTitle = 'Kategoriler';
$activeNav = 'categories';
$currentUser = $user;

$formMode = 'create';
$formErrors = [];
$fieldErrors = [];

$formData = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'description' => '',
    'emoji' => '',
    'icon_url' => '',
    'sort_order' => 0,
    'is_active' => 1,
    'as_child' => false,
    'parent_id' => '',
    'reset_form' => '1',
];

if (isset($_GET['prefill_parent'])) {
    $prefillParent = (int) $_GET['prefill_parent'];
    if ($prefillParent > 0) {
        $formData['parent_id'] = $prefillParent;
        $formData['as_child'] = true;
        $formData['reset_form'] = isset($_GET['retain']) && $_GET['retain'] === '1' ? '0' : '1';
    }
}

$categoryTree = [];
$flatCategories = [];
$productCounts = [];
$subcategoryCounts = [];
$settings = [];

if (isset($pdo) && $pdo instanceof PDO) {
    ensureCategoryInfrastructure($pdo);
    $categoryTree = fetchCategoryTree($pdo);
    $flatCategories = fetchFlatCategories($pdo);

    $countStmt = $pdo->query('SELECT category_id, COUNT(*) AS total FROM products GROUP BY category_id');
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $productCounts[(int) $row['category_id']] = (int) $row['total'];
    }

    if (tableHasColumn($pdo, 'products', 'subcategory_id')) {
        $subCountStmt = $pdo->query('SELECT subcategory_id, COUNT(*) AS total FROM products WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id');
        foreach ($subCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $subcategoryCounts[(int) $row['subcategory_id']] = (int) $row['total'];
        }
    }

    try {
        $settings = fetchSettings($pdo);
    } catch (Throwable $settingsException) {
        $settings = [];
    }
}

$descendantIndex = [];

/**
 * @param array<int, array<string, mixed>> $tree
 * @param int $targetId
 * @return array<int, int>
 */
function collectCategoryDescendants(array $tree, int $targetId): array
{
    foreach ($tree as $node) {
        $id = (int) ($node['id'] ?? 0);
        $children = $node['subcategories'] ?? [];
        if ($id === $targetId) {
            return gatherDescendants($node);
        }
        if (!empty($children)) {
            $result = collectCategoryDescendants($children, $targetId);
            if (!empty($result)) {
                return $result;
            }
        }
    }

    return [];
}

/**
 * @param array<string, mixed> $node
 * @return array<int, int>
 */
function gatherDescendants(array $node): array
{
    $ids = [];
    foreach ($node['subcategories'] ?? [] as $child) {
        $childId = (int) ($child['id'] ?? 0);
        if ($childId <= 0) {
            continue;
        }
        $ids[] = $childId;
        $ids = array_merge($ids, gatherDescendants($child));
    }
    return $ids;
}

/**
 * @param array<int, array<string, mixed>> $flat
 * @param int $id
 * @return array<string, mixed>|null
 */
function findFlatCategory(array $flat, int $id): ?array
{
    foreach ($flat as $item) {
        if ((int) $item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0 && isset($pdo) && $pdo instanceof PDO) {
        $editing = fetchCategoryById($pdo, $editId);
        if ($editing) {
            $formMode = 'edit';
            $formData = [
                'id' => (int) $editing['id'],
                'name' => (string) ($editing['name'] ?? $editing['title'] ?? ''),
                'slug' => (string) ($editing['slug'] ?? ''),
                'description' => (string) ($editing['description'] ?? ''),
                'emoji' => (string) ($editing['emoji'] ?? ''),
                'icon_url' => (string) ($editing['icon_url'] ?? $editing['icon'] ?? ''),
                'sort_order' => (int) ($editing['sort_order'] ?? 0),
                'is_active' => !empty($editing['is_active']) ? 1 : 0,
                'as_child' => isset($editing['parent_id']) && $editing['parent_id'] !== null,
                'parent_id' => $editing['parent_id'] ?? '',
                'reset_form' => '1',
            ];
            $descendantIndex = collectCategoryDescendants($categoryTree, $formData['id']);
        } else {
            addFlash('error', 'Düzenlemek istediğiniz kategori bulunamadı.');
            redirect('categories.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if (!validateCsrfToken('category-actions', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        try {
            if ($action === 'delete') {
                $categoryId = (int) ($_POST['id'] ?? 0);
                if ($categoryId <= 0) {
                    $formErrors[] = 'Silinecek kategori seçilemedi.';
                } else {
                    deleteCategory($pdo, $categoryId);
                    logActivity($pdo, (int) $user['id'], 'delete', 'category', $categoryId, 'Kategori silindi');
                    addFlash('success', 'Kategori silindi.');
                    redirect('categories.php');
                }
            } else {
                $categoryId = (int) ($_POST['id'] ?? 0);
                $formMode = $categoryId > 0 ? 'edit' : 'create';

                $formData = [
                    'id' => $categoryId,
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'slug' => sanitizeSlug((string) ($_POST['slug'] ?? '')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'emoji' => trim((string) ($_POST['emoji'] ?? '')),
                    'icon_url' => trim((string) ($_POST['icon_url'] ?? '')),
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'as_child' => isset($_POST['as_child']) && $_POST['as_child'] === '1',
                    'parent_id' => trim((string) ($_POST['parent_id'] ?? '')),
                    'reset_form' => isset($_POST['reset_form']) ? '1' : '0',
                ];

                if ($formData['parent_id'] === '' || !$formData['as_child']) {
                    $formData['parent_id'] = '';
                }

                if (mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 80) {
                    $fieldErrors['name'] = 'Kategori adı 2 ile 80 karakter arasında olmalıdır.';
                }

                if ($formData['slug'] !== '' && !preg_match('/^[a-z0-9-]+$/', $formData['slug'])) {
                    $fieldErrors['slug'] = 'Slug yalnızca küçük harf, rakam ve tire içerebilir.';
                }

                if ($formData['icon_url'] !== '' && filter_var($formData['icon_url'], FILTER_VALIDATE_URL) === false && strncmp($formData['icon_url'], '/', 1) !== 0 && !preg_match('/^[\p{So}\p{Sk}\p{Sc}\p{Sm}]{1,2}$/u', $formData['icon_url'])) {
                    $fieldErrors['icon_url'] = 'Geçerli bir URL veya emoji girin.';
                }

                $parentId = $formData['as_child'] ? (int) $formData['parent_id'] : null;
                if ($parentId !== null && $parentId <= 0) {
                    $parentId = null;
                }

                if ($parentId !== null && !categoryExists($pdo, $parentId)) {
                    $fieldErrors['parent_id'] = 'Seçilen üst kategori bulunamadı.';
                }

                if ($categoryId > 0) {
                    if ($parentId !== null && $parentId === $categoryId) {
                        $fieldErrors['parent_id'] = 'Bir kategori kendi üst kategorisi olamaz.';
                    }
                    if (empty($descendantIndex)) {
                        $descendantIndex = collectCategoryDescendants($categoryTree, $categoryId);
                    }
                    if ($parentId !== null && in_array($parentId, $descendantIndex, true)) {
                        $fieldErrors['parent_id'] = 'Alt kategorinizi üst kategori olarak seçemezsiniz.';
                    }
                }

                if (!empty($fieldErrors)) {
                    $formErrors[] = 'Lütfen formdaki hataları düzeltin.';
                } else {
                    $payload = [
                        'name' => $formData['name'],
                        'slug' => $formData['slug'],
                        'description' => $formData['description'],
                        'emoji' => $formData['emoji'],
                        'icon_url' => $formData['icon_url'],
                        'sort_order' => $formData['sort_order'],
                        'is_active' => (bool) $formData['is_active'],
                        'parent_id' => $parentId,
                    ];

                    if ($categoryId > 0) {
                        updateCategory($pdo, $categoryId, $payload);
                        logActivity($pdo, (int) $user['id'], 'update', 'category', $categoryId, 'Kategori güncellendi');
                        addFlash('success', 'Kategori bilgileri güncellendi.');
                        redirect('categories.php');
                    } else {
                        $newId = createCategory($pdo, $payload);
                        logActivity($pdo, (int) $user['id'], 'create', 'category', $newId, 'Yeni kategori oluşturuldu');
                        addFlash('success', 'Kategori başarıyla oluşturuldu.');

                        if ($formData['reset_form'] === '1') {
                            redirect('categories.php');
                        }

                        $redirectUrl = 'categories.php?prefill_parent=' . ($parentId ?? 0) . '&retain=1';
                        redirect($redirectUrl);
                    }
                }
            }
        } catch (InvalidArgumentException $invalid) {
            $formErrors[] = $invalid->getMessage();
        } catch (PDOException $pdoException) {
            if ((int) $pdoException->getCode() === 23000) {
                $formErrors[] = 'Aynı isim veya slug değerine sahip başka bir kategori mevcut.';
            } else {
                $formErrors[] = 'İşlem sırasında hata oluştu: ' . $pdoException->getMessage();
            }
        } catch (Throwable $exception) {
            $formErrors[] = 'Beklenmeyen bir hata oluştu: ' . $exception->getMessage();
        }
    }
}

$actionToken = issueCsrfToken('category-actions');
$branding = buildBrandingContext($settings);

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
                <p class="text-xs text-slate-500">Tek bir form ile hem üst hem alt kategorileri yönetin.</p>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-300">
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Aktif</span>
                <span class="ml-3 inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span> Pasif</span>
            </div>
        </div>

        <?php if (!empty($formErrors)): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/20 dark:text-red-200 px-4 py-3 text-xs space-y-1">
                <?php foreach ($formErrors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
            <div class="xl:col-span-3 space-y-4">
                <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800/70 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Kategori Listesi</h3>
                            <p class="text-xs text-slate-500">Kategori ve alt kategori hiyerarşisini tek tabloda görüntüleyin.</p>
                        </div>
                        <span class="text-xs text-slate-500">Toplam <?= number_format(count($flatCategories)) ?> kayıt</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Kategori</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Üst Kategori</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Slug</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Sıra</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Ürün</th>
                                    <th scope="col" class="px-4 py-3 text-right font-medium">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php if (empty($flatCategories)): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Henüz kategori oluşturulmamış.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $idToName = [];
                                    foreach ($flatCategories as $flat) {
                                        $idToName[(int) $flat['id']] = $flat['name'];
                                    }
                                    foreach ($flatCategories as $flat):
                                        $id = (int) $flat['id'];
                                        $depth = (int) $flat['depth'];
                                        $indent = $depth > 0 ? str_repeat('— ', $depth) : '';
                                        $parentId = $flat['parent_id'] !== null ? (int) $flat['parent_id'] : null;
                                        $productTotal = $productCounts[$id] ?? 0;
                                        if (isset($subcategoryCounts[$id])) {
                                            $productTotal += $subcategoryCounts[$id];
                                        }
                                        $isActive = !empty($flat['is_active']);
                                    ?>
                                        <tr class="<?= $isActive ? '' : 'opacity-60' ?>">
                                            <td class="px-4 py-3 align-top">
                                                <div class="flex items-center gap-2">
                                                    <?php if (!empty($flat['emoji'])): ?>
                                                        <span class="text-base" aria-hidden="true"><?= htmlspecialchars($flat['emoji']) ?></span>
                                                    <?php elseif (!empty($flat['icon_url'])): ?>
                                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                                            <img src="<?= htmlspecialchars($flat['icon_url']) ?>" alt="<?= htmlspecialchars($flat['name']) ?> ikon" class="h-full w-full object-contain" />
                                                        </span>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="font-medium text-slate-700 dark:text-slate-100"><?= htmlspecialchars($indent . $flat['name']) ?></div>
                                                        <?php if (!empty($flat['description'])): ?>
                                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[200px]"><?= htmlspecialchars($flat['description']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top text-slate-500">
                                                <?= $parentId !== null && isset($idToName[$parentId]) ? htmlspecialchars($idToName[$parentId]) : '—' ?>
                                            </td>
                                            <td class="px-4 py-3 align-top text-slate-500">
                                                <span class="inline-flex items-center gap-1">
                                                    <?= htmlspecialchars($flat['slug']) ?>
                                                    <?php if (!$isActive): ?>
                                                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                                                    <?php else: ?>
                                                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 align-top text-slate-500"><?= (int) $flat['sort_order'] ?></td>
                                            <td class="px-4 py-3 align-top text-slate-500"><?= number_format($productTotal) ?></td>
                                            <td class="px-4 py-3 align-top text-right space-x-2">
                                                <a href="categories.php?edit=<?= $id ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">Düzenle</a>
                                                <form method="post" class="inline" onsubmit="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?');">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                    <input type="hidden" name="action" value="delete" />
                                                    <input type="hidden" name="id" value="<?= $id ?>" />
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-red-200 text-red-600 hover:bg-red-50">Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2">
                <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm p-6 space-y-4">
                    <header>
                        <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Kategori Formu</p>
                        <h3 class="text-base font-semibold mt-1"><?= $formMode === 'edit' ? 'Kategori Düzenle' : 'Yeni Kategori Oluştur' ?></h3>
                        <p class="text-xs text-slate-500 mt-1">Slug boş bırakılırsa kategori adından otomatik üretilir.</p>
                    </header>
                    <form method="post" class="space-y-4" novalidate>
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                        <input type="hidden" name="action" value="save" />
                        <input type="hidden" name="id" value="<?= (int) $formData['id'] ?>" />
                        <div class="space-y-3">
                            <label class="block text-xs font-medium text-slate-500 space-y-1">
                                <span>Ad <span class="text-red-500">*</span></span>
                                <input type="text" id="category-name" name="name" value="<?= htmlspecialchars($formData['name']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" maxlength="80" required />
                                <?php if (isset($fieldErrors['name'])): ?>
                                    <span class="block text-[11px] text-red-500 mt-1"><?= htmlspecialchars($fieldErrors['name']) ?></span>
                                <?php endif; ?>
                            </label>
                            <label class="block text-xs font-medium text-slate-500 space-y-1">
                                <span>Slug</span>
                                <input type="text" id="category-slug" name="slug" value="<?= htmlspecialchars($formData['slug']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="otomatik" />
                                <?php if (isset($fieldErrors['slug'])): ?>
                                    <span class="block text-[11px] text-red-500 mt-1"><?= htmlspecialchars($fieldErrors['slug']) ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <label class="block text-xs font-medium text-slate-500 space-y-1">
                            <span>Açıklama</span>
                            <textarea name="description" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Kategori hakkında kısa bilgi"><?= htmlspecialchars($formData['description']) ?></textarea>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="block text-xs font-medium text-slate-500 space-y-1">
                                <span>Emoji</span>
                                <input type="text" name="emoji" value="<?= htmlspecialchars($formData['emoji']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" maxlength="8" placeholder="🔥" />
                            </label>
                            <label class="block text-xs font-medium text-slate-500 space-y-1">
                                <span>İkon URL / Emoji</span>
                                <input type="text" name="icon_url" value="<?= htmlspecialchars($formData['icon_url']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="/uploads/icon.svg" />
                                <?php if (isset($fieldErrors['icon_url'])): ?>
                                    <span class="block text-[11px] text-red-500 mt-1"><?= htmlspecialchars($fieldErrors['icon_url']) ?></span>
                                <?php endif; ?>
                            </label>
                            <label class="block text-xs font-medium text-slate-500 space-y-1">
                                <span>Sıra</span>
                                <input type="number" name="sort_order" value="<?= (int) $formData['sort_order'] ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" id="category-active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= $formData['is_active'] ? 'checked' : '' ?> />
                            <label for="category-active" class="text-xs text-slate-600">Kategori aktif</label>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="as_child" value="1" id="category-as-child" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= $formData['as_child'] ? 'checked' : '' ?> />
                                    <span>Alt kategori olarak ekle</span>
                                </label>
                                <button type="button" id="parent-clear" class="text-xs text-slate-500 hover:text-slate-700 underline">Temizle</button>
                            </div>
                            <div id="parent-picker" class="space-y-2 <?= $formData['as_child'] ? '' : 'hidden' ?>">
                                <label class="block text-xs font-medium text-slate-500 space-y-1">
                                    <span>Üst kategori seç</span>
                                    <input type="text" id="parent-search" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Kategori ara" autocomplete="off" />
                                </label>
                                <label class="block text-xs font-medium text-slate-500 space-y-1">
                                    <span class="sr-only">Üst Kategori</span>
                                    <select id="parent-select" name="parent_id" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm">
                                        <option value="">Üst kategori yok</option>
                                        <?php foreach ($flatCategories as $option): ?>
                                            <?php
                                                $optionId = (int) $option['id'];
                                                $optionDepth = (int) $option['depth'];
                                                $optionIndent = $optionDepth > 0 ? str_repeat('— ', $optionDepth) : '';
                                                $disabled = '';
                                                if ($formMode === 'edit') {
                                                    if ($optionId === (int) $formData['id']) {
                                                        $disabled = 'disabled';
                                                    } elseif (!empty($descendantIndex) && in_array($optionId, $descendantIndex, true)) {
                                                        $disabled = 'disabled';
                                                    }
                                                }
                                            ?>
                                            <option value="<?= $optionId ?>" data-name="<?= htmlspecialchars($option['name']) ?>" <?= $disabled ?> <?= ($formData['parent_id'] !== '' && (int) $formData['parent_id'] === $optionId) ? 'selected' : '' ?>><?= htmlspecialchars($optionIndent . $option['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($fieldErrors['parent_id'])): ?>
                                        <span class="block text-[11px] text-red-500 mt-1"><?= htmlspecialchars($fieldErrors['parent_id']) ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="reset_form" value="1" id="reset-form-toggle" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= $formData['reset_form'] === '1' ? 'checked' : '' ?> />
                            <label for="reset-form-toggle" class="text-xs text-slate-600">Kaydet sonrası formu sıfırla</label>
                        </div>
                        <div class="flex items-center justify-between pt-2">
                            <?php if ($formMode === 'edit'): ?>
                                <a href="categories.php" class="text-xs text-slate-500 hover:text-slate-700 underline">Yeni kategori oluştur</a>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Hızlı alt kategori girişi için üst kategoriyi seçili bırakabilirsiniz.</span>
                            <?php endif; ?>
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">
                                <?= $formMode === 'edit' ? 'Kategoriyi Güncelle' : 'Kategoriyi Kaydet' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
(function() {
    const nameInput = document.getElementById('category-name');
    const slugInput = document.getElementById('category-slug');
    const asChildToggle = document.getElementById('category-as-child');
    const parentPicker = document.getElementById('parent-picker');
    const parentSelect = document.getElementById('parent-select');
    const parentSearch = document.getElementById('parent-search');
    const parentClear = document.getElementById('parent-clear');

    let slugTouched = false;
    if (slugInput) {
        slugInput.addEventListener('focus', function() { slugTouched = true; });
    }

    function slugify(value) {
        return value.toLowerCase()
            .normalize('NFD').replace(/[^\p{Letter}\p{Number}]+/gu, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            if (!slugTouched || !slugInput.value) {
                slugInput.value = slugify(nameInput.value);
            }
        });
    }

    function toggleParentPicker() {
        if (!asChildToggle || !parentPicker) {
            return;
        }
        const shouldShow = asChildToggle.checked;
        parentPicker.classList.toggle('hidden', !shouldShow);
        if (!shouldShow && parentSelect) {
            parentSelect.value = '';
        }
    }

    if (asChildToggle) {
        asChildToggle.addEventListener('change', toggleParentPicker);
        toggleParentPicker();
    }

    if (parentSearch && parentSelect) {
        parentSearch.addEventListener('input', function() {
            const query = parentSearch.value.trim().toLowerCase();
            const options = parentSelect.querySelectorAll('option');
            options.forEach(function(option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }
                const name = (option.getAttribute('data-name') || option.textContent || '').toLowerCase();
                option.hidden = query !== '' && !name.includes(query);
            });
        });
    }

    if (parentClear && parentSelect) {
        parentClear.addEventListener('click', function() {
            if (asChildToggle) {
                asChildToggle.checked = false;
                toggleParentPicker();
            }
            parentSelect.value = '';
            if (parentSearch) {
                parentSearch.value = '';
                const event = new Event('input', { bubbles: true });
                parentSearch.dispatchEvent(event);
            }
        });
    }
})();
</script>

<?php
require __DIR__ . '/../src/admin_page_end.php';
