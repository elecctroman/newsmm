<?php
/**
 * @var array $sliderItems
 * @var array $stripItems
 * @var array $blocks
 * @var array $categories
 * @var string $sliderToken
 * @var string $stripToken
 * @var string $blockToken
 */
?>

<section class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Slider Öğesi Ekle</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ana sayfa üst slider alanına yeni bir öğe ekleyin.</p>
                </div>
            </div>
            <form method="post" class="p-5 space-y-4">
                <input type="hidden" name="action" value="create_slider" />
                <input type="hidden" name="_token" value="<?= htmlspecialchars($sliderToken) ?>" />
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Görsel URL <span class="text-red-500">*</span></label>
                    <input type="url" name="image_url" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="https://..." />
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Başlık</label>
                        <input type="text" name="headline" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="Öne çıkan mesaj" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Bağlantı</label>
                        <input type="url" name="link_url" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="https://..." />
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Yayın Başlangıcı</label>
                        <input type="datetime-local" name="starts_at" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Yayın Bitişi</label>
                        <input type="datetime-local" name="ends_at" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Sıra</label>
                        <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Aktif
                    </label>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-2 text-sm">Slider öğesini ekle</button>
            </form>
        </div>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Şerit Öğesi Ekle</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Slider altındaki bilgi şeridine öğe ekleyin.</p>
                </div>
            </div>
            <form method="post" class="p-5 space-y-4">
                <input type="hidden" name="action" value="create_strip" />
                <input type="hidden" name="_token" value="<?= htmlspecialchars($stripToken) ?>" />
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Başlık <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="Ödeme Seçenekleri" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Açıklama</label>
                    <textarea name="description" rows="2" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Görsel URL</label>
                        <input type="url" name="image_url" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="https://..." />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Bağlantı</label>
                        <input type="url" name="link_url" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="https://..." />
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-500">Sıra</label>
                        <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Aktif
                    </label>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-accent-500 hover:bg-accent-600 text-slate-900 font-semibold px-4 py-2 text-sm">Şerit öğesini ekle</button>
            </form>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm mt-6">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold">Vitrin Bloku Ekle</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Kategori bazlı veya özel bloklar oluşturun.</p>
            </div>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="create_block" />
            <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken) ?>" />
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Blok Tipi</label>
                    <select name="type" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                        <option value="category">Kategori</option>
                        <option value="custom">Özel</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Kategori</label>
                    <select name="category_id" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                        <option value="">Seçiniz</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name'] ?? $category['title'] ?? 'Kategori') ?></option>
                            <?php if (!empty($category['subcategories'])): ?>
                                <?php foreach ($category['subcategories'] as $sub): ?>
                                    <option value="<?= (int) $sub['id'] ?>">— <?= htmlspecialchars($sub['name'] ?? $sub['title'] ?? 'Alt Kategori') ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Başlık (opsiyonel)</label>
                    <input type="text" name="title_override" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="Kategori başlığını özelleştir" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Gösterilecek Ürün</label>
                    <input type="number" name="product_limit" value="6" min="1" max="12" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-slate-500">Sabitlenecek Ürün ID'leri (virgül ile ayrılmış)</label>
                <input type="text" name="pinned_product_ids" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="12,45,89" />
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-500">Sıra</label>
                    <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                    <input type="checkbox" name="show_only_instock" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Sadece stokta olanlar
                </label>
            </div>
            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                Blok aktif
            </label>
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-4 py-2 text-sm">Vitrin bloğu ekle</button>
        </form>
    </div>
</section>

<section class="mt-8 space-y-8">
    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <h2 class="text-base font-semibold">Slider Öğeleri</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($sliderItems) ?> öğe</span>
        </div>
        <div class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
            <?php if (empty($sliderItems)): ?>
                <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">Henüz slider öğesi eklenmemiş.</p>
            <?php else: ?>
                <?php foreach ($sliderItems as $item): ?>
                    <form method="post" class="px-5 py-4 grid gap-3 md:grid-cols-6 items-center">
                        <input type="hidden" name="action" value="update_slider" />
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>" />
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($sliderToken) ?>" />
                        <div class="md:col-span-2 space-y-1">
                            <label class="text-[11px] font-medium text-slate-500">Görsel</label>
                            <input type="text" name="image_url" value="<?= htmlspecialchars($item['image_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[11px] font-medium text-slate-500">Başlık</label>
                            <input type="text" name="headline" value="<?= htmlspecialchars($item['headline'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[11px] font-medium text-slate-500">Bağlantı</label>
                            <input type="text" name="link_url" value="<?= htmlspecialchars($item['link_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[11px] font-medium text-slate-500">Sıra</label>
                            <input type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                                <input type="checkbox" name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                Aktif
                            </label>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold px-3 py-2">Kaydet</button>
                            <button type="submit" name="action" value="delete_slider" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-2" onclick="return confirm('Bu slider öğesini silmek istediğinize emin misiniz?');">Sil</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <h2 class="text-base font-semibold">Şerit Öğeleri</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($stripItems) ?> öğe</span>
        </div>
        <div class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
            <?php if (empty($stripItems)): ?>
                <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">Henüz şerit öğesi eklenmemiş.</p>
            <?php else: ?>
                <?php foreach ($stripItems as $item): ?>
                    <form method="post" class="px-5 py-4 grid gap-3 md:grid-cols-5 items-center">
                        <input type="hidden" name="action" value="update_strip" />
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>" />
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($stripToken) ?>" />
                        <div class="space-y-1">
                            <label class="text-[11px] font-medium text-slate-500">Başlık</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($item['name'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-[11px] font-medium text-slate-500">Açıklama</label>
                            <input type="text" name="description" value="<?= htmlspecialchars($item['description'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[11px] font-medium text-slate-500">Sıra</label>
                            <input type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                                <input type="checkbox" name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                Aktif
                            </label>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-accent-500 hover:bg-accent-600 text-slate-900 text-xs font-semibold px-3 py-2">Kaydet</button>
                            <button type="submit" name="action" value="delete_strip" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-2" onclick="return confirm('Bu şerit öğesini silmek istediğinize emin misiniz?');">Sil</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/60 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <h2 class="text-base font-semibold">Vitrin Blokları</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($blocks) ?> blok</span>
        </div>
        <div class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
            <?php if (empty($blocks)): ?>
                <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">Henüz vitrin bloğu eklenmemiş.</p>
            <?php else: ?>
                <?php foreach ($blocks as $block): ?>
                    <form method="post" class="px-5 py-4 space-y-3">
                        <input type="hidden" name="action" value="update_block" />
                        <input type="hidden" name="id" value="<?= (int) $block['id'] ?>" />
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken) ?>" />
                        <div class="grid md:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-slate-500">Tip</label>
                                <select name="type" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                                    <option value="category" <?= ($block['type'] ?? 'category') === 'category' ? 'selected' : '' ?>>Kategori</option>
                                    <option value="custom" <?= ($block['type'] ?? 'category') === 'custom' ? 'selected' : '' ?>>Özel</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-slate-500">Kategori</label>
                                <select name="category_id" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= (int) ($block['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name'] ?? $category['title'] ?? 'Kategori') ?></option>
                                        <?php if (!empty($category['subcategories'])): ?>
                                            <?php foreach ($category['subcategories'] as $sub): ?>
                                                <option value="<?= (int) $sub['id'] ?>" <?= (int) ($block['category_id'] ?? 0) === (int) $sub['id'] ? 'selected' : '' ?>>— <?= htmlspecialchars($sub['name'] ?? $sub['title'] ?? 'Alt Kategori') ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-slate-500">Ürün Limiti</label>
                                <input type="number" name="product_limit" value="<?= (int) ($block['product_limit'] ?? 6) ?>" min="1" max="12" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-slate-500">Başlık</label>
                                <input type="text" name="title_override" value="<?= htmlspecialchars($block['title_override'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-slate-500">Sabitlenecek Ürünler</label>
                                <input type="text" name="pinned_product_ids" value="<?= htmlspecialchars(implode(',', $block['pinned_product_ids'] ?? [])) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </div>
                        </div>
                        <div class="grid md:grid-cols-3 gap-3 items-center">
                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-slate-500">Sıra</label>
                                <input type="number" name="sort_order" value="<?= (int) ($block['sort_order'] ?? 0) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </div>
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                                <input type="checkbox" name="show_only_instock" value="1" <?= !empty($block['show_only_instock']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                Sadece stokta olanlar
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                                <input type="checkbox" name="is_active" value="1" <?= !empty($block['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                Aktif
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold px-3 py-2">Kaydet</button>
                            <button type="submit" name="action" value="delete_block" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-2" onclick="return confirm('Bu bloğu silmek istediğinize emin misiniz?');">Sil</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
