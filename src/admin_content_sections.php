<?php
/**
 * @var array $blocks
 * @var array $categories
 * @var string $blockToken
 * @var array $sliderOrphans
 */
?>

<section class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-12">
        <article class="lg:col-span-7 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/70 shadow-sm p-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ana sayfa vitrin blokları</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Kategori, alt kategori veya özel içerik bloklarını buradan düzenleyebilirsiniz. Bloklar yayına alındığında
                ana sayfada sıralı biçimde görüntülenir ve yapılan güncellemeler önbelleğe alınmış sayfalara otomatik olarak yansıtılır.
            </p>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 p-4">
                    <dt class="text-xs uppercase tracking-[0.3em] text-slate-500">Toplam blok</dt>
                    <dd class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100"><?= number_format(count($blocks)) ?></dd>
                    <dd class="mt-1 text-xs text-slate-500">Yayınlanmış vitrin alanı</dd>
                </div>
                <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-950/40 p-4">
                    <dt class="text-xs uppercase tracking-[0.3em] text-slate-500">Kategori kaynağı</dt>
                    <dd class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100"><?= number_format(count($categories)) ?></dd>
                    <dd class="mt-1 text-xs text-slate-500">Blok seçiminde kullanılabilir kategori</dd>
                </div>
            </dl>
        </article>
        <article class="lg:col-span-5 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/85 dark:bg-slate-900/70 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Yayın notları</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-brand-500"></span> Blok sırası düşükten yükseğe doğru ana sayfaya yansır.</li>
                <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-brand-500"></span> "Yalnız stokta olanlar" seçeneği aktif ürünleri öne çıkarır.</li>
                <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-brand-500"></span> Sabit ürün listesi JSON veya virgülle ayrılmış değerler olarak girilebilir.</li>
            </ul>
        </article>
    </div>
</section>

<?php if (!empty($sliderOrphans)): ?>
    <section id="orphan-media" class="mt-8">
        <div class="rounded-3xl border border-amber-200/70 dark:border-amber-500/40 bg-amber-50/80 dark:bg-amber-900/30 shadow-sm p-6">
            <h3 class="text-base font-semibold text-amber-900 dark:text-amber-200">Yetim slider görselleri</h3>
            <p class="mt-2 text-sm text-amber-800 dark:text-amber-200/80">
                Eski slider sisteminden kalan ve artık kullanılmayan medya kayıtları aşağıda listelenmiştir. Bu görselleri medya kütüphanesinden silebilir
                veya yeniden kullanabilirsiniz.
            </p>
            <ul class="mt-4 divide-y divide-amber-200/70 dark:divide-amber-500/40 text-sm">
                <?php foreach ($sliderOrphans as $orphan): ?>
                    <li class="py-2 flex items-center justify-between gap-4">
                        <span class="truncate text-amber-900 dark:text-amber-100"><?= htmlspecialchars($orphan['image_url']) ?></span>
                        <?php if (!empty($orphan['title'])): ?>
                            <span class="text-xs text-amber-700 dark:text-amber-200/80"><?= htmlspecialchars($orphan['title']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 text-xs text-amber-700 dark:text-amber-200/80">Medya klasörünüzde "Yetim (slider)" etiketiyle raporlandılar.</p>
        </div>
    </section>
<?php endif; ?>

<section id="home-blocks" class="mt-10 space-y-8">
    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60">
            <h3 class="text-base font-semibold">Vitrin Bloğu Oluştur</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kategori blokları veya özel vitrin alanları ekleyin.</p>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="create_block" />
            <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken) ?>" />
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Blok tipi</span>
                    <select name="type" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                        <option value="category">Kategori</option>
                        <option value="custom">Özel</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Kategori / Alt kategori</span>
                    <select name="category_id" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                        <option value="">Seçiniz</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name'] ?? $category['title'] ?? 'Kategori') ?></option>
                            <?php if (!empty($category['subcategories'])): ?>
                                <?php foreach ($category['subcategories'] as $sub): ?>
                                    <option value="<?= (int) $sub['id'] ?>">— <?= htmlspecialchars($sub['name'] ?? $sub['title'] ?? 'Alt kategori') ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Başlık (opsiyonel)</span>
                    <input type="text" name="title_override" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="Vitrin başlığı" />
                </label>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Gösterilecek ürün sayısı</span>
                    <input type="number" name="product_limit" value="6" min="1" max="12" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                </label>
            </div>
            <label class="space-y-1 text-xs font-medium text-slate-500">
                <span>Sabitlenecek ürün ID’leri</span>
                <input type="text" name="pinned_product_ids" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="12,45,89" />
            </label>
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Sıra</span>
                    <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                </label>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                    <input type="checkbox" name="show_only_instock" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Yalnız stokta olanlar
                </label>
            </div>
            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                Blok aktif
            </label>
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-4 py-2 text-sm">Blok ekle</button>
        </form>
    </div>

    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <h3 class="text-base font-semibold">Mevcut Vitrin Blokları</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($blocks) ?> blok</span>
        </div>
        <div class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
            <?php if (empty($blocks)): ?>
                <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">Henüz blok eklenmemiş.</p>
            <?php endif; ?>
            <?php foreach ($blocks as $block): ?>
                <form method="post" class="px-5 py-4 grid gap-3 md:grid-cols-6 items-center text-sm">
                    <input type="hidden" name="action" value="update_block" />
                    <input type="hidden" name="id" value="<?= (int) $block['id'] ?>" />
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken) ?>" />
                    <div class="space-y-1">
                        <span class="text-[11px] font-medium text-slate-500">Blok #<?= (int) $block['id'] ?></span>
                        <select name="type" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 px-3 py-1.5 text-xs">
                            <option value="category" <?= ($block['type'] ?? '') === 'custom' ? '' : 'selected' ?>>Kategori</option>
                            <option value="custom" <?= ($block['type'] ?? '') === 'custom' ? 'selected' : '' ?>>Özel</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <span class="text-[11px] font-medium text-slate-500">Kategori ID</span>
                        <input type="number" name="category_id" value="<?= (int) ($block['category_id'] ?? 0) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 px-3 py-1.5 text-xs" />
                    </div>
                    <div class="space-y-1">
                        <span class="text-[11px] font-medium text-slate-500">Başlık</span>
                        <input type="text" name="title_override" value="<?= htmlspecialchars($block['title_override'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 px-3 py-1.5 text-xs" />
                    </div>
                    <div class="space-y-1">
                        <span class="text-[11px] font-medium text-slate-500">Ürün sayısı</span>
                        <input type="number" name="product_limit" value="<?= (int) ($block['product_limit'] ?? 6) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 px-3 py-1.5 text-xs" />
                    </div>
                    <div class="space-y-1">
                        <span class="text-[11px] font-medium text-slate-500">Sıra</span>
                        <input type="number" name="sort_order" value="<?= (int) ($block['sort_order'] ?? 0) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 px-3 py-1.5 text-xs" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <label class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
                            <input type="checkbox" name="show_only_instock" value="1" <?= !empty($block['show_only_instock']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            Stokta
                        </label>
                        <label class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
                            <input type="checkbox" name="is_active" value="1" <?= !empty($block['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            Aktif
                        </label>
                    </div>
                    <div class="md:col-span-6 flex items-center justify-between">
                        <label class="flex-1 space-y-1 text-[11px] font-medium text-slate-500 mr-4">
                            <span>Sabit ürünler (JSON veya virgüllü liste)</span>
                            <input type="text" name="pinned_product_ids" value="<?= htmlspecialchars(is_array($block['pinned_product_ids']) ? implode(',', $block['pinned_product_ids']) : ($block['pinned_product_ids'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 px-3 py-1.5 text-xs" />
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 text-white text-xs font-semibold px-3 py-1.5 hover:bg-slate-700">Güncelle</button>
                            <button type="submit" name="action" value="delete_block" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-1.5" onclick="return confirm('Blok silinsin mi?');">Sil</button>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>
