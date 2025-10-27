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
            'category' => $slug,
            'categoryTitle' => $product['category_title'] ?? '',
        ];

        $productsByCategory[$slug][] = $productData;
    }

    foreach ($productsByCategory as $slug => $items) {
        $catalogPayload['products'][$slug] = $items;
    }
}

$warningHtml = '';
if (empty($categories)) {
    $warningPieces = [];
    $warningPieces[] = '<h2 class="text-lg font-semibold mb-2">Veritabanı bağlantısı yapılamadı</h2>';
    if (!empty($connectionError ?? null)) {
        $warningPieces[] = '<p class="text-sm mb-1">Hata: ' . htmlspecialchars($connectionError ?? '', ENT_QUOTES) . '</p>';
    }
    if (!empty($missingConfig ?? false)) {
        $warningPieces[] = '<p class="text-sm">Lütfen <code>config/config.php</code> dosyasını <code>config/config.example.php</code> dosyasından kopyalayarak oluşturun ve veritabanı bilgilerinizi girin.</p>';
    } else {
        $warningPieces[] = '<p class="text-sm">Lütfen veritabanı kimlik bilgilerinizi kontrol ederek tekrar deneyin.</p>';
    }
    $warningHtml = implode('', $warningPieces);
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
    <title>Lisansonay Kontrol Paneli</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f5f5ff',
                            100: '#ececff',
                            200: '#d8d8fe',
                            500: '#4c5bff',
                            600: '#3b45d6',
                            700: '#3138ad',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-screen flex">
    <aside class="hidden lg:flex lg:w-72 xl:w-80 border-r border-slate-200/70 dark:border-slate-800/80 bg-white/80 dark:bg-slate-950/40 backdrop-blur">
        <div class="flex flex-col w-full">
            <div class="px-6 py-6 border-b border-slate-200/60 dark:border-slate-800/70">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-white text-xl font-semibold shadow">L</span>
                    <div>
                        <p class="text-lg font-semibold tracking-tight">Lisansonay</p>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Yönetim</p>
                    </div>
                </div>
            </div>
            <nav class="flex-1 px-4 py-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3 px-2">Navigasyon</p>
                <ul class="space-y-1">
                    <li>
                        <a class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-900/60" href="#">
                            <span aria-hidden="true">🏠</span>
                            Ana Panel
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold bg-brand-50 text-brand-700 dark:text-brand-200 dark:bg-brand-700/20" href="#">
                            <span aria-hidden="true">📦</span>
                            Ürün Kataloğu
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-900/60" href="#">
                            <span aria-hidden="true">🧾</span>
                            Siparişler
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-900/60" href="#">
                            <span aria-hidden="true">🙋‍♂️</span>
                            Müşteri Talepleri
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-900/60" href="#">
                            <span aria-hidden="true">📊</span>
                            Raporlar
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-900/60" href="#">
                            <span aria-hidden="true">⚙️</span>
                            Ayarlar
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="px-6 py-6 border-t border-slate-200/60 dark:border-slate-800/70">
                <div class="rounded-2xl bg-brand-50/80 dark:bg-brand-700/20 p-4">
                    <p class="text-sm font-semibold text-brand-700 dark:text-brand-200">Destek Merkezi</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Ekibinize özel kurulum, entegrasyon ve fiyatlama desteği.</p>
                    <button type="button" class="mt-3 inline-flex items-center justify-center rounded-xl bg-brand-600 text-white text-xs font-medium px-3 py-2 hover:bg-brand-700 transition">İletişime Geç</button>
                </div>
            </div>
        </div>
    </aside>
    <div class="flex-1 flex flex-col">
        <header class="border-b border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-950/40 backdrop-blur">
            <div class="px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Lisansonay</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Profesyonel lisans yönetimi paneli</p>
                </div>
                <div class="flex items-center gap-2 sm:gap-3">
                    <button type="button" class="hidden sm:inline-flex items-center justify-center rounded-xl border border-slate-200/70 dark:border-slate-800/70 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-900/60 transition">Bildirimler</button>
                    <button type="button" id="themeBtn" class="inline-flex items-center gap-2 rounded-xl border border-slate-200/70 dark:border-slate-800/70 px-3 py-2 bg-white/70 dark:bg-slate-900/70 text-sm font-medium shadow-sm hover:bg-slate-100 dark:hover:bg-slate-900/50 transition">
                        <span class="inline-flex h-5 w-5 items-center justify-center" id="themeIcon"></span>
                        <span id="themeText">Aydınlık</span>
                    </button>
                    <div class="hidden sm:flex items-center gap-2 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 px-3 py-2 bg-white/80 dark:bg-slate-900/70">
                        <div class="h-9 w-9 rounded-full bg-brand-500 text-white flex items-center justify-center text-sm font-semibold">LK</div>
                        <div>
                            <p class="text-sm font-semibold">Kontrol Kullanıcısı</p>
                            <p class="text-xs text-slate-500">admin@lisansonay.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto">
            <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
                            <span class="uppercase tracking-[0.2em] text-slate-400">Anasayfa</span>
                            <span aria-hidden="true">›</span>
                            <span class="uppercase tracking-[0.2em] text-slate-600">Ürün Kataloğu</span>
                        </nav>
                        <h1 class="mt-3 text-2xl sm:text-3xl font-semibold tracking-tight">Ürün Kataloğu Yönetimi</h1>
                        <p class="mt-2 text-sm text-slate-500 max-w-2xl">Lisansonay ekibiniz için lisans, hesap ve takipçi portföyünü tek merkezden yönetin. Filtreleyin, raporlayın ve anında aksiyon alın.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-200/70 dark:border-slate-800/70 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-900/60 transition">Toplu İçe Aktar</button>
                        <button type="button" class="inline-flex items-center justify-center rounded-xl bg-brand-600 text-white px-4 py-2 text-sm font-semibold shadow hover:bg-brand-700 transition">Yeni Ürün</button>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Toplam Ürün</p>
                        <p class="mt-3 text-3xl font-semibold" id="metricProducts">0</p>
                        <p class="mt-1 text-xs text-slate-500">Katalogda yayınlanan aktif kalemler</p>
                    </article>
                    <article class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Aktif Kategori</p>
                        <p class="mt-3 text-3xl font-semibold" id="metricCategories">0</p>
                        <p class="mt-1 text-xs text-slate-500">Sunulan ana hizmet grupları</p>
                    </article>
                    <article class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Takipçi Paketleri</p>
                        <p class="mt-3 text-3xl font-semibold" id="metricFollowers">0</p>
                        <p class="mt-1 text-xs text-slate-500">Instagram özel üretim paketleri</p>
                    </article>
                    <article class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Ortalama Fiyat</p>
                        <p class="mt-3 text-3xl font-semibold" id="metricAverage">₺0</p>
                        <p class="mt-1 text-xs text-slate-500">Ürün başına ortalama etiket</p>
                    </article>
                </section>

                <section class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm space-y-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="flex flex-col sm:flex-row sm:items-end gap-4 flex-1">
                            <label class="flex-1">
                                <span class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Katalogda Ara</span>
                                <span class="mt-2 inline-flex w-full items-center gap-2 rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/70 px-3 py-2 focus-within:ring-2 focus-within:ring-brand-500">
                                    <span class="text-lg" aria-hidden="true">🔍</span>
                                    <input id="globalSearch" type="search" placeholder="Ürün adı veya anahtar kelime" class="w-full bg-transparent text-sm outline-none" autocomplete="off" />
                                </span>
                            </label>
                            <label class="sm:w-48">
                                <span class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Sıralama</span>
                                <select id="sorter" class="mt-2 w-full rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/70 px-3 py-2 text-sm">
                                    <option value="asc">Ucuzdan pahalıya</option>
                                    <option value="desc">Pahalıdan ucuza</option>
                                </select>
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-xs sm:text-sm text-slate-500" id="selectionLabel">Tüm kategoriler görüntüleniyor</p>
                            <button type="button" id="clearFilters" class="hidden text-xs font-semibold text-brand-600 hover:text-brand-700">Filtreleri sıfırla</button>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 mb-3">Kategoriler</p>
                        <div id="categoryFilters" class="flex flex-wrap gap-2"></div>
                    </div>
                    <div id="instagramTabs" class="flex items-center gap-2" hidden>
                        <button type="button" class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/70" data-ig-tab="hesaplar">Instagram Hesapları</button>
                        <button type="button" class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/70" data-ig-tab="takipci">Takipçi Paketleri</button>
                        <p class="text-xs text-slate-500">Takipçi paketleri seçili kategoriye göre dinamik olarak hazırlanır.</p>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[minmax(0,2.1fr)_minmax(0,1fr)]">
                    <div class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 shadow-sm">
                        <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200/70 dark:border-slate-800/70">
                            <div>
                                <h2 class="text-lg font-semibold tracking-tight">Ürün Listesi</h2>
                                <p class="text-sm text-slate-500">Fiyatlandırılmış tüm lisans ve sosyal medya ürünleri.</p>
                            </div>
                            <span id="productCount" class="inline-flex items-center rounded-xl bg-brand-50 text-brand-700 text-sm font-semibold px-3 py-1">0 ürün</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200/70 dark:divide-slate-800/70 text-sm">
                                <thead class="bg-slate-50/70 dark:bg-slate-900/40 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left font-semibold">Ürün</th>
                                        <th scope="col" class="px-6 py-3 text-left font-semibold">Kategori</th>
                                        <th scope="col" class="px-6 py-3 text-left font-semibold">Fiyat</th>
                                        <th scope="col" class="px-6 py-3 text-left font-semibold">Durum</th>
                                        <th scope="col" class="px-6 py-3 text-right font-semibold">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="productTableBody" class="divide-y divide-slate-200/70 dark:divide-slate-800/70"></tbody>
                            </table>
                        </div>
                        <div id="productEmpty" class="hidden px-6 py-10 text-center text-sm text-slate-500 border-t border-slate-200/70 dark:border-slate-800/70">
                            <p class="font-medium text-slate-600 dark:text-slate-300">Seçili filtrelere uygun ürün bulunamadı.</p>
                            <p class="mt-1">Farklı bir kategori seçerek veya arama sorgusunu güncelleyerek tekrar deneyin.</p>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm">
                            <h3 class="text-sm font-semibold tracking-tight">Portföy Özeti</h3>
                            <p class="text-xs text-slate-500 mt-1">Fiyat dinamikleri ve öne çıkan kalemlere hızlı bakış.</p>
                            <ul id="insightList" class="mt-4 space-y-3 text-sm"></ul>
                        </div>
                        <div class="rounded-3xl bg-white/80 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800/70 p-6 shadow-sm">
                            <h3 class="text-sm font-semibold tracking-tight">Operasyon Notları</h3>
                            <ul class="mt-3 space-y-2 text-sm text-slate-500">
                                <li>• Yeni eklenen ürünlerde fiyat etiketi boş bırakılmamalıdır.</li>
                                <li>• Takipçi paketlerinde teslim süresi not alanında belirtin.</li>
                                <li>• İçeriği güncel tutmak için haftalık stok kontrolü önerilir.</li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
        </main>
        <footer class="border-t border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-950/40">
            <div class="px-4 sm:px-6 lg:px-8 py-4 text-xs text-slate-500 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <p>© Lisansonay <?= date('Y') ?>. Tüm hakları saklıdır.</p>
                <p>Panel sürümü 1.0.0 · Stabil dağıtım</p>
            </div>
        </footer>
    </div>
</div>
<script>
    window.catalogData = <?= $catalogJson ?>;
    window.catalogMeta = {
        hasConnection: <?= isset($pdo) && $pdo instanceof PDO ? 'true' : 'false' ?>
    };
</script>
<script src="public/assets/js/app.js" defer></script>
<?php if (empty($categories)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const main = document.querySelector('main');
        if (!main) {
            return;
        }
        const warning = document.createElement('div');
        warning.className = 'mx-4 sm:mx-6 lg:mx-8 my-6 rounded-3xl border border-amber-300 bg-amber-50 text-amber-900 p-6 shadow-sm';
        warning.innerHTML = <?= json_encode($warningHtml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        main.prepend(warning);
    });
</script>
<?php endif; ?>
</body>
</html>
