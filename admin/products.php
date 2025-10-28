<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/session.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/helpers.php';

ensureSession();
$user = requireAuth();
requirePermission($user, 'catalog.products.view');
$flashes = getFlashes();

$pageTitle = 'Ürünler';
$activeNav = 'products';
$currentUser = $user;

$formErrors = [];
$oldProduct = [
    'category_id' => '',
    'subcategory_id' => '',
    'name' => '',
    'price_amount' => '',
    'price_label' => '',
    'note' => '',
    'sort_order' => 0,
];

$categoryValidationCache = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('product-actions', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'create':
                    $categoryId = (int)($_POST['category_id'] ?? 0);
                    $subcategoryId = (int)($_POST['subcategory_id'] ?? 0);
                    $name = trim((string)($_POST['name'] ?? ''));
                    $priceAmount = normalizePriceAmount($_POST['price_amount'] ?? null);
                    $priceLabel = formatPriceLabel($_POST['price_label'] ?? '', (float)($priceAmount ?? 0));
                    $note = sanitizeNullableString($_POST['note'] ?? null);
                    $sortOrder = (int)($_POST['sort_order'] ?? 0);

                    $oldProduct = [
                        'category_id' => $categoryId,
                        'subcategory_id' => $subcategoryId,
                        'name' => $name,
                        'price_amount' => $_POST['price_amount'] ?? '',
                        'price_label' => $_POST['price_label'] ?? '',
                        'note' => (string)($note ?? ''),
                        'sort_order' => $sortOrder,
                    ];

                    if ($categoryId <= 0) {
                        $formErrors[] = 'Bir kategori seçmelisiniz.';
                    }
                    if ($subcategoryId > 0) {
                        if ($categoryValidationCache === null) {
                            $categoryValidationCache = fetchAllCategories($pdo);
                        }
                        $subcategoryBelongs = false;
                        foreach ($categoryValidationCache as $category) {
                            if ((int)$category['id'] === $categoryId) {
                                foreach ($category['subcategories'] ?? [] as $sub) {
                                    if ((int)$sub['id'] === $subcategoryId) {
                                        $subcategoryBelongs = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (!$subcategoryBelongs) {
                            $formErrors[] = 'Seçilen alt kategori, seçili kategori ile eşleşmiyor.';
                        }
                    }
                    if ($name === '') {
                        $formErrors[] = 'Ürün adı gereklidir.';
                    }
                    if ($priceAmount === null) {
                        $formErrors[] = 'Geçerli bir fiyat girin.';
                    }

                    if (empty($formErrors)) {
                        $payload = [
                            'category_id' => $categoryId,
                            'subcategory_id' => $subcategoryId,
                            'name' => $name,
                            'price_label' => $priceLabel,
                            'price_cents' => priceToCents((float)$priceAmount),
                            'note' => $note,
                            'sort_order' => $sortOrder,
                        ];
                        $productId = createProduct($pdo, $payload);
                        logActivity($pdo, (int) $user['id'], 'create', 'product', $productId, 'Yeni ürün oluşturuldu');
                        addFlash('success', 'Ürün başarıyla eklendi.');
                        redirect('products.php');
                    }
                    break;
                case 'update':
                    $productId = (int)($_POST['id'] ?? 0);
                    if ($productId <= 0) {
                        $formErrors[] = 'Geçersiz ürün seçimi.';
                        break;
                    }
                    $categoryId = (int)($_POST['category_id'] ?? 0);
                    $subcategoryId = (int)($_POST['subcategory_id'] ?? 0);
                    $name = trim((string)($_POST['name'] ?? ''));
                    $priceAmount = normalizePriceAmount($_POST['price_amount'] ?? null);
                    $priceLabel = formatPriceLabel($_POST['price_label'] ?? '', (float)($priceAmount ?? 0));
                    $note = sanitizeNullableString($_POST['note'] ?? null);
                    $sortOrder = (int)($_POST['sort_order'] ?? 0);

                    if ($categoryId <= 0 || $name === '' || $priceAmount === null) {
                        $formErrors[] = 'Kategori, ürün adı ve fiyat alanları zorunludur.';
                        break;
                    }
                    if ($subcategoryId > 0) {
                        if ($categoryValidationCache === null) {
                            $categoryValidationCache = fetchAllCategories($pdo);
                        }
                        $subcategoryBelongs = false;
                        foreach ($categoryValidationCache as $category) {
                            if ((int)$category['id'] === $categoryId) {
                                foreach ($category['subcategories'] ?? [] as $sub) {
                                    if ((int)$sub['id'] === $subcategoryId) {
                                        $subcategoryBelongs = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (!$subcategoryBelongs) {
                            $formErrors[] = 'Seçilen alt kategori, seçili kategori ile eşleşmiyor.';
                            break;
                        }
                    }

                    $payload = [
                        'category_id' => $categoryId,
                        'subcategory_id' => $subcategoryId,
                        'name' => $name,
                        'price_label' => $priceLabel,
                        'price_cents' => priceToCents((float)$priceAmount),
                        'note' => $note,
                        'sort_order' => $sortOrder,
                    ];
                    updateProduct($pdo, $productId, $payload);
                    logActivity($pdo, (int) $user['id'], 'update', 'product', $productId, 'Ürün güncellendi');
                    addFlash('success', 'Ürün güncellendi.');
                    redirect('products.php');
                    break;
                case 'delete':
                    $productId = (int)($_POST['id'] ?? 0);
                    if ($productId <= 0) {
                        $formErrors[] = 'Silinecek ürün bulunamadı.';
                        break;
                    }
                    deleteProduct($pdo, $productId);
                    logActivity($pdo, (int) $user['id'], 'delete', 'product', $productId, 'Ürün silindi');
                    addFlash('success', 'Ürün silindi.');
                    redirect('products.php');
                    break;
                default:
                    $formErrors[] = 'Bilinmeyen işlem.';
            }
        } catch (PDOException $e) {
            $formErrors[] = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}

$actionToken = issueCsrfToken('product-actions');

$categories = [];
$products = [];
$settings = [];

if (isset($pdo) && $pdo instanceof PDO) {
    $categories = fetchAllCategories($pdo);
    $products = fetchAllProducts($pdo);
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
        <p class="text-xs mt-1">Ürün yönetimi için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Ürün Kataloğu</h2>
                <p class="text-xs text-slate-500">Ürün fiyatlarını, notlarını ve sıralamalarını yönetin.</p>
            </div>
            <span class="text-xs text-slate-400">Toplam <?= number_format(count($products)) ?> ürün</span>
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
                            <th class="px-4 py-3 text-left font-semibold">Ürün</th>
                            <th class="px-4 py-3 text-left font-semibold">Kategori</th>
                            <th class="px-4 py-3 text-left font-semibold">Fiyat</th>
                            <th class="px-4 py-3 text-left font-semibold">Not</th>
                            <th class="px-4 py-3 text-left font-semibold">Sıra</th>
                            <th class="px-4 py-3 text-left font-semibold">Güncellendi</th>
                            <th class="px-4 py-3 text-right font-semibold">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($product['name']) ?></p>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <div>
                                        <span class="font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($product['category_title']) ?></span>
                                        <?php if (!empty($product['subcategory_title'])): ?>
                                            <span class="block text-[11px] text-slate-400 mt-1">Alt: <?= htmlspecialchars($product['subcategory_title']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top text-xs font-semibold text-brand-600 dark:text-brand-200"><?= htmlspecialchars($product['price_label']) ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <?= htmlspecialchars($product['note'] ?? '') ?>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= (int)$product['sort_order'] ?></td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($product['updated_at']))) ?></td>
                                <td class="px-4 py-4 align-top text-right text-xs">
                                    <details class="group">
                                        <summary class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/50 cursor-pointer text-slate-600 dark:text-slate-300">Düzenle</summary>
                                        <div class="mt-3 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-slate-50/80 dark:bg-slate-950/50 space-y-3">
                                            <form method="post" class="space-y-3">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                <input type="hidden" name="action" value="update" />
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>" />
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Kategori</span>
                                                        <select name="category_id" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required>
                                                            <option value="">Seçin</option>
                                                            <?php foreach ($categories as $category): ?>
                                                                <option value="<?= (int)$category['id'] ?>" <?php if ((int)$product['category_id'] === (int)$category['id']): ?>selected<?php endif; ?>><?= htmlspecialchars($category['title']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Alt Kategori</span>
                                                        <select name="subcategory_id" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm">
                                                            <option value="">Alt kategori yok</option>
                                                            <?php foreach ($categories as $category): ?>
                                                                <?php if (!empty($category['subcategories'])): ?>
                                                                    <optgroup label="<?= htmlspecialchars($category['title']) ?>">
                                                                        <?php foreach ($category['subcategories'] as $subcategory): ?>
                                                                            <option value="<?= (int)$subcategory['id'] ?>" <?php if ((int)($product['subcategory_id'] ?? 0) === (int)$subcategory['id']): ?>selected<?php endif; ?>><?= htmlspecialchars($subcategory['title']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </optgroup>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <span class="block text-[10px] text-slate-400">Kategori ile eşleşen alt kategoriyi seçin.</span>
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Sıra</span>
                                                        <input type="number" name="sort_order" value="<?= (int)$product['sort_order'] ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                                                    </label>
                                                </div>
                                                <label class="text-xs font-medium text-slate-500 space-y-1">
                                                    <span>Ürün Adı</span>
                                                    <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                                                </label>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Fiyat (TRY)</span>
                                                        <input type="text" name="price_amount" value="<?= htmlspecialchars(number_format(centsToPrice((int)$product['price_cents']), 2, '.', '')) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-500 space-y-1">
                                                        <span>Fiyat Etiketi</span>
                                                        <input type="text" name="price_label" value="<?= htmlspecialchars($product['price_label']) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                                                    </label>
                                                </div>
                                                <label class="text-xs font-medium text-slate-500 space-y-1">
                                                    <span>Not</span>
                                                    <textarea name="note" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm"><?= htmlspecialchars($product['note'] ?? '') ?></textarea>
                                                </label>
                                                <div class="flex items-center justify-between gap-3 pt-2">
                                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold">Kaydet</button>
                                                </div>
                                            </form>
                                            <form method="post" class="mt-3" onsubmit="return confirm('Bu ürünü silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                                                <input type="hidden" name="action" value="delete" />
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>" />
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
            <h3 class="text-sm font-semibold">Yeni Ürün Ekle</h3>
            <form method="post" class="mt-4 space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($actionToken) ?>" />
                <input type="hidden" name="action" value="create" />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Kategori</span>
                        <select name="category_id" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required>
                            <option value="">Seçin</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?php if ((int)$oldProduct['category_id'] === (int)$category['id']): ?>selected<?php endif; ?>><?= htmlspecialchars($category['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Alt Kategori</span>
                        <select name="subcategory_id" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm">
                            <option value="">Alt kategori yok</option>
                            <?php foreach ($categories as $category): ?>
                                <?php if (!empty($category['subcategories'])): ?>
                                    <optgroup label="<?= htmlspecialchars($category['title']) ?>">
                                        <?php foreach ($category['subcategories'] as $subcategory): ?>
                                            <option value="<?= (int)$subcategory['id'] ?>" <?php if ((int)($oldProduct['subcategory_id'] ?? 0) === (int)$subcategory['id']): ?>selected<?php endif; ?>><?= htmlspecialchars($subcategory['title']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <span class="block text-[10px] text-slate-400">Kategori ile eşleşen alt kategoriyi seçin.</span>
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Sıra</span>
                        <input type="number" name="sort_order" value="<?= (int)($oldProduct['sort_order'] ?? 0) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                    </label>
                </div>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Ürün Adı</span>
                    <input type="text" name="name" value="<?= htmlspecialchars($oldProduct['name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Fiyat (TRY)</span>
                        <input type="text" name="price_amount" value="<?= htmlspecialchars($oldProduct['price_amount'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="299.00" required />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1">
                        <span>Fiyat Etiketi</span>
                        <input type="text" name="price_label" value="<?= htmlspecialchars($oldProduct['price_label'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="₺299" />
                    </label>
                </div>
                <label class="text-xs font-medium text-slate-500 space-y-1">
                    <span>Not</span>
                    <textarea name="note" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Opsiyonel bilgi"><?= htmlspecialchars($oldProduct['note'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">Ürün Oluştur</button>
            </form>
        </div>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
