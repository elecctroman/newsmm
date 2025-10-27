<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$categories = [];
$productsByCategory = [];
$catalogPayload = ['categories' => [], 'products' => []];

if (isset($pdo) && $pdo instanceof PDO) {
    $categories = fetchCategories($pdo);
    $products = fetchProducts($pdo);

    foreach ($categories as $category) {
        $slug = $category['slug'];
        $catalogPayload['categories'][] = [
            'id' => (int) $category['id'],
            'slug' => $slug,
            'title' => $category['title'],
            'description' => $category['description'],
            'emoji' => $category['emoji'],
        ];
        $productsByCategory[$slug] = [];
    }

    foreach ($products as $product) {
        $slug = $product['category_slug'];
        if (!array_key_exists($slug, $productsByCategory)) {
            $productsByCategory[$slug] = [];
        }

        $productData = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'price' => $product['price_label'],
            'priceCents' => (int) $product['price_cents'],
            'note' => $product['note'],
        ];

        $productsByCategory[$slug][] = $productData;
    }

    foreach ($productsByCategory as $slug => $items) {
        $catalogPayload['products'][$slug] = $items;
    }
}

$catalogJson = json_encode(
    $catalogPayload,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

?>
<!DOCTYPE html>
<html lang="tr" class="h-full" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BHE Digital — Ürün ve Fiyat Listesi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light dark;
        }
        body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="h-full bg-neutral-50 text-neutral-900 dark:bg-neutral-900 dark:text-neutral-50">
<div class="min-h-screen">
    <header class="sticky top-0 z-40 backdrop-blur supports-[backdrop-filter]:bg-white/70 bg-white dark:bg-neutral-900/80 border-b border-neutral-200/70 dark:border-neutral-800/70">
        <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-orange-500 text-white font-bold shadow-sm">B</span>
                <div class="mr-2">
                    <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">BHE Digital</h1>
                    <p class="text-xs sm:text-sm opacity-80 -mt-0.5">Ürün ve Fiyat Listesi</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="https://wa.me/905333563479" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 px-3 py-1 text-xs sm:text-sm hover:bg-emerald-100 transition">
                        <span class="inline-flex">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0012.05 0C5.5.02.2 5.33.23 11.88c.01 2.1.56 4.14 1.6 5.95L0 24l6.35-1.8a11.86 11.86 0 005.68 1.48h.05c6.54-.03 11.84-5.34 11.82-11.89a11.84 11.84 0 00-3.38-8.31z"></path><path d="M17.04 14.33c-.29-.14-1.74-.86-2.02-.95-.27-.1-.46-.14-.66.14-.19.27-.76.95-.94 1.14-.17.19-.35.2-.64.07a7.7 7.7 0 01-2.27-1.4 8.49 8.49 0 01-1.57-1.95c-.17-.3 0-.46.13-.6.13-.13.3-.35.44-.52.15-.18.19-.3.3-.51.1-.2.05-.38-.02-.53-.07-.14-.66-1.58-.9-2.16-.24-.58-.47-.5-.66-.51h-.56c-.2 0-.52.07-.79.38-.27.3-1.03 1.01-1.03 2.47s1.06 2.87 1.21 3.07c.14.2 2.08 3.18 5.03 4.46.7.3 1.25.48 1.68.61.7.22 1.35.19 1.86.12.57-.09 1.74-.71 1.99-1.4.25-.69.25-1.28.17-1.4-.07-.12-.26-.19-.55-.33z"></path></svg>
                        </span>
                        <span class="font-medium">WhatsApp</span>
                    </a>
                    <a href="https://t.me/bhedigital" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full border border-sky-200 bg-sky-50 text-sky-700 px-3 py-1 text-xs sm:text-sm hover:bg-sky-100 transition">
                        <span class="inline-flex">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.04 15.53l-.36 5.07c.51 0 .73-.22 1-.48l2.4-2.3 4.97 3.65c.91.5 1.56.24 1.8-.85l3.27-15.33.01-.01c.29-1.36-.49-1.89-1.38-1.56L1.63 10.48c-1.33.51-1.31 1.25-.24 1.58l5.7 1.78 13.24-8.35c.62-.39 1.18-.18.72.22L9.04 15.53z"></path></svg>
                        </span>
                        <span class="font-medium">Telegram</span>
                    </a>
                    <a href="https://www.r10.net/bhe" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 text-blue-700 px-3 py-1 text-xs sm:text-sm hover:bg-blue-100 transition">
                        <span class="inline-flex">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM11 7h2v6h-2zm0 8h2v2h-2z"></path></svg>
                        </span>
                        <span class="font-medium">R10</span>
                    </a>
                    <a href="https://hesap.com.tr/u/bhedigital" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 text-purple-700 px-3 py-1 text-xs sm:text-sm hover:bg-purple-100 transition">
                        <span class="inline-flex">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 2a2 2 0 00-2 2v16l2-1 2 1 2-1 2 1 2-1 2 1 2-1V4a2 2 0 00-2-2H6zM8 7h8v2H8V7zm0 4h8v2H8v-2z"></path></svg>
                        </span>
                        <span class="font-medium">Hesap</span>
                    </a>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 rounded-2xl border border-neutral-200 dark:border-neutral-700 px-3 py-2 focus-within:ring-2 focus-within:ring-orange-500 bg-white dark:bg-neutral-800 shadow-sm max-w-md w-[320px]">
                    <span class="w-5 h-5 opacity-70">🔎</span>
                    <input id="top-search" placeholder="Önce bir kategori seçin" disabled class="bg-transparent outline-none w-full text-sm opacity-60" />
                </div>
                <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200 dark:border-neutral-700 px-3 py-2 bg-white dark:bg-neutral-800 shadow-sm text-sm" id="themeBtn">
                    <span class="inline-flex h-5 w-5 items-center justify-center" id="themeIcon"></span>
                    <span class="font-medium" id="themeText">Gündüz</span>
                </button>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:py-10">
        <section>
            <div class="flex items-end justify-between mb-4">
                <h2 class="text-lg sm:text-xl font-semibold">BHE Digital Ürün ve Fiyat Listesi</h2>
                <div class="flex items-center gap-3" id="sortContainer" hidden>
                    <label class="text-sm opacity-80" for="sorter">Sırala:</label>
                    <select id="sorter" class="text-sm rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-2 py-1">
                        <option value="asc">Ucuzdan pahalıya</option>
                        <option value="desc">Pahalıdan ucuza</option>
                    </select>
                    <button type="button" id="clearCategory" class="text-sm underline underline-offset-4 opacity-80 hover:opacity-100">Kategoriyi temizle</button>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" id="categoryList">
                <?php foreach ($categories as $category): ?>
                    <button type="button" class="group rounded-2xl border shadow-sm p-5 text-left transition-all hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 bg-white dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700" data-category="<?= htmlspecialchars($category['slug'], ENT_QUOTES) ?>">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-2xl"><?= htmlspecialchars($category['emoji'], ENT_QUOTES) ?></span>
                            <h3 class="text-base sm:text-lg font-semibold tracking-tight"><?= htmlspecialchars($category['title']) ?></h3>
                        </div>
                        <p class="text-sm opacity-80"><?= htmlspecialchars($category['description']) ?></p>
                        <div class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-orange-600 dark:text-orange-400">
                            <span>Kategoriyi Aç</span>
                            <span class="inline-flex"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 4.5a.75.75 0 011.06 0l6 6a.75.75 0 010 1.06l-6 6a.75.75 0 11-1.06-1.06L18.94 12 13.5 6.56a.75.75 0 010-1.06z"/><path d="M3 12a.75.75 0 01.75-.75h15a.75.75 0 010 1.5h-15A.75.75 0 013 12z"/></svg></span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="mt-8 sm:mt-10" id="productsSection" hidden>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg sm:text-xl font-semibold" id="productsHeading">Kategori</h2>
                <span class="text-sm opacity-70" id="productCount"></span>
            </div>
            <div id="instagramTabs" class="mb-4" hidden>
                <div class="inline-flex rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                    <button type="button" class="px-3 py-1.5 text-sm" data-ig-tab="hesaplar">Hesaplar</button>
                    <button type="button" class="px-3 py-1.5 text-sm" data-ig-tab="takipci">Instagram Takipçi</button>
                </div>
                <p class="mt-3 text-xs sm:text-sm opacity-80">Şu an güncelleme olduğundan dolayı hazırda takipçili hesap stoğumuz yok. Seçtiğiniz takipçi paketlerine göre sizlere özel olarak üretim yapılmaktadır.</p>
            </div>
            <p class="text-sm opacity-80" id="emptyMessage" hidden>Arama sonucuna uygun ürün bulunamadı.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="productGrid"></div>
        </section>
        <?php if (empty($categories)): ?>
            <div class="mt-12 rounded-2xl border border-amber-300 bg-amber-50 text-amber-900 p-6 max-w-3xl mx-auto">
                <h3 class="text-lg font-semibold mb-2">Veritabanı bağlantısı yapılamadı</h3>
                <?php if (!empty($connectionError ?? null)): ?>
                    <p class="text-sm mb-2">Hata: <?= htmlspecialchars($connectionError) ?></p>
                <?php endif; ?>
                <?php if (!empty($missingConfig ?? false)): ?>
                    <p class="text-sm">Lütfen <code>config/config.php</code> dosyasını <code>config/config.example.php</code> dosyasını kopyalayarak oluşturun ve veritabanı bilgilerinizi girin.</p>
                <?php else: ?>
                    <p class="text-sm">Lütfen veritabanı bilgilerinizi kontrol ederek tekrar deneyin.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <section class="mx-auto max-w-6xl px-4 mt-10">
            <h3 class="text-sm font-semibold opacity-80 mb-3">Hızlı Bağlantılar</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:max-w-3xl lg:mx-auto">
                <a href="https://bhedigital.com.tr/odeme/" target="_blank" rel="noopener" class="group cursor-pointer rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-800 shadow-sm p-5 flex items-center gap-4 hover:-translate-y-0.5 transition-transform">
                    <span class="w-5 h-5" aria-hidden="true">₺</span>
                    <div class="flex-1">
                        <h4 class="text-base font-semibold tracking-tight">Ödeme Sayfamız</h4>
                        <p class="text-sm opacity-80">Kart, Havale/EFT ve diğer yöntemler. Güvenli ödeme ekranına geç.</p>
                    </div>
                    <span class="ml-auto inline-flex items-center gap-2 text-base sm:text-lg font-semibold text-orange-600 dark:text-orange-400 py-1.5 px-3 rounded-xl bg-orange-50 dark:bg-orange-950/30 hover:bg-orange-100 dark:hover:bg-orange-900/40 transition-all">
                        Git <span class="inline-flex"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 4.5a.75.75 0 011.06 0l6 6a.75.75 0 010 1.06l-6 6a.75.75 0 11-1.06-1.06L18.94 12 13.5 6.56a.75.75 0 010-1.06z"/><path d="M3 12a.75.75 0 01.75-.75h15a.75.75 0 010 1.5h-15A.75.75 0 013 12z"/></svg></span>
                    </span>
                </a>
                <a href="https://bhedigital.com.tr/talimatlar" target="_blank" rel="noopener" class="group cursor-pointer rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-800 shadow-sm p-5 flex items-center gap-4 hover:-translate-y-0.5 transition-transform">
                    <span class="w-5 h-5" aria-hidden="true">📘</span>
                    <div class="flex-1">
                        <h4 class="text-base font-semibold tracking-tight">Talimatlar</h4>
                        <p class="text-sm opacity-80">Kurulum, teslim ve kullanım rehberleri. Adım adım yönergeler.</p>
                    </div>
                    <span class="ml-auto inline-flex items-center gap-2 text-base sm:text-lg font-semibold text-emerald-700 dark:text-emerald-400 py-1.5 px-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-all">
                        Aç <span class="inline-flex"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 4.5a.75.75 0 011.06 0l6 6a.75.75 0 010 1.06l-6 6a.75.75 0 11-1.06-1.06L18.94 12 13.5 6.56a.75.75 0 010-1.06z"/><path d="M3 12a.75.75 0 01.75-.75h15a.75.75 0 010 1.5h-15A.75.75 0 013 12z"/></svg></span>
                    </span>
                </a>
            </div>
        </section>
    </main>
    <footer class="mt-10 border-t border-neutral-200 dark:border-neutral-800">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="opacity-80">© BHEDigital <?= date('Y') ?></p>
        </div>
    </footer>
</div>
<script>
    window.catalogData = <?= $catalogJson ?>;
    window.catalogMeta = {
        hasConnection: <?= isset($pdo) && $pdo instanceof PDO ? 'true' : 'false' ?>
    };
</script>
<script src="public/assets/js/app.js" defer></script>
</body>
</html>
