<?php
/**
 * @var array $heroBanners
 * @var array $heroSlides
 * @var array $heroStripItems
 * @var array $heroSettings
 * @var array $blocks
 * @var array $categories
 * @var string $heroBannerToken
 * @var string $heroSlideToken
 * @var string $heroStripToken
 * @var string $heroSettingsToken
 * @var string $blockToken
 */
?>

<section id="hero" class="space-y-8">
    <div class="grid gap-6 lg:grid-cols-12">
        <article class="lg:col-span-4 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm p-6">
            <h2 class="text-base font-semibold">Hero Önizleme</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sol/sağ banner, ana carousel ve mini şerit alanlarını buradan yönetin.</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">Sol banner</dt>
                    <dd class="font-semibold text-slate-700 dark:text-slate-200"><?= count($heroBanners['left'] ?? []) ?> kayıt</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">Ana slayt</dt>
                    <dd class="font-semibold text-slate-700 dark:text-slate-200"><?= count($heroSlides) ?> kayıt</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">Sağ banner</dt>
                    <dd class="font-semibold text-slate-700 dark:text-slate-200"><?= count($heroBanners['right'] ?? []) ?> kayıt</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">Mini şerit</dt>
                    <dd class="font-semibold text-slate-700 dark:text-slate-200"><?= count($heroStripItems) ?> kayıt</dd>
                </div>
            </dl>
        </article>
        <article class="lg:col-span-8 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-gradient-to-br from-brand-50/60 via-white to-white dark:from-slate-900/80 dark:via-slate-950/70 dark:to-slate-950/70 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Yayın Notları</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-brand-500"></span> Banner ve slayt görselleri 16:7, bannerlar 3:5 oranına uygun hazırlanmalıdır.</li>
                <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-brand-500"></span> Başlangıç/bitiş tarihleri dolduğunda ilgili içerik otomatik olarak yayından kalkar.</li>
                <li class="flex items-start gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-brand-500"></span> Sıralama değişikliklerinden sonra “Sıralamayı Kaydet” butonunu kullanmayı unutmayın.</li>
            </ul>
        </article>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60">
                <h3 class="text-base font-semibold">Banner Oluştur</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sol veya sağ banner sütununa yeni bir görsel ekleyin.</p>
            </div>
            <form method="post" class="p-5 space-y-4">
                <input type="hidden" name="action" value="create_hero_banner" />
                <input type="hidden" name="_token" value="<?= htmlspecialchars($heroBannerToken) ?>" />
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Konum <span class="text-red-500">*</span></span>
                        <select name="side" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm">
                            <option value="left">Sol Banner</option>
                            <option value="right">Sağ Banner</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Sıra</span>
                        <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                </div>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Görsel URL <span class="text-red-500">*</span></span>
                    <input type="url" name="image_url" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="/media/hero/left.jpg" />
                </label>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Bağlantı</span>
                        <input type="url" name="link_url" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="https://..." />
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Alt metin</span>
                        <input type="text" name="alt_text" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="Kampanya görseli" />
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Yayın başlangıcı</span>
                        <input type="datetime-local" name="starts_at" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Yayın bitişi</span>
                        <input type="datetime-local" name="ends_at" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Banner aktif
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-2 text-sm">Banner ekle</button>
            </form>
        </div>
        <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60">
                <h3 class="text-base font-semibold">Slayt Oluştur</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Carousel için yeni bir görsel ve bağlantı ekleyin.</p>
            </div>
            <form method="post" class="p-5 space-y-4">
                <input type="hidden" name="action" value="create_hero_slide" />
                <input type="hidden" name="_token" value="<?= htmlspecialchars($heroSlideToken) ?>" />
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Görsel URL <span class="text-red-500">*</span></span>
                    <input type="url" name="image_url" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="/media/hero/slide-1.jpg" />
                </label>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Bağlantı</span>
                        <input type="url" name="link_url" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Alt metin</span>
                        <input type="text" name="alt_text" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Sıra</span>
                        <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 mt-6">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Slayt aktif
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Yayın başlangıcı</span>
                        <input type="datetime-local" name="starts_at" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">
                        <span>Yayın bitişi</span>
                        <input type="datetime-local" name="ends_at" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                    </label>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-4 py-2 text-sm">Slayt ekle</button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <?php foreach (['left' => 'Sol Banner', 'right' => 'Sağ Banner'] as $sideKey => $sideLabel): ?>
            <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
                <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
                    <h3 class="text-base font-semibold"><?= $sideLabel ?> Listesi</h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($heroBanners[$sideKey] ?? []) ?> öğe</span>
                </div>
                <div class="p-5 space-y-4">
                    <div id="banner-list-<?= $sideKey ?>" class="space-y-3" data-sortable-list data-sortable-target="#banner-order-<?= $sideKey ?>">
                        <?php foreach ($heroBanners[$sideKey] ?? [] as $banner): ?>
                            <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/60 bg-white/95 dark:bg-slate-950/60 p-4 space-y-3" data-sortable-item data-id="<?= (int) $banner['id'] ?>" draggable="true">
                                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                    <span>#<?= (int) $banner['id'] ?> · sıra <?= (int) $banner['sort_order'] ?></span>
                                    <span class="cursor-move text-slate-400 dark:text-slate-500">⇅</span>
                                </div>
                                <form method="post" class="grid gap-3 md:grid-cols-2" autocomplete="off">
                                    <input type="hidden" name="action" value="update_hero_banner" />
                                    <input type="hidden" name="id" value="<?= (int) $banner['id'] ?>" />
                                    <input type="hidden" name="side" value="<?= htmlspecialchars($sideKey) ?>" />
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($heroBannerToken) ?>" />
                                    <label class="space-y-1 text-xs font-medium text-slate-500 md:col-span-2">
                                        <span>Görsel URL</span>
                                        <input type="text" name="image_url" value="<?= htmlspecialchars($banner['image_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                    </label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">
                                        <span>Bağlantı</span>
                                        <input type="text" name="link_url" value="<?= htmlspecialchars($banner['link_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                    </label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">
                                        <span>Alt metin</span>
                                        <input type="text" name="alt_text" value="<?= htmlspecialchars($banner['alt_text'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                    </label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">
                                        <span>Sıra</span>
                                        <input type="number" name="sort_order" value="<?= (int) ($banner['sort_order'] ?? 0) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                    </label>
                                    <div class="grid sm:grid-cols-2 gap-3 md:col-span-2">
                                        <label class="space-y-1 text-xs font-medium text-slate-500">
                                            <span>Başlangıç</span>
                                            <input type="datetime-local" name="starts_at" value="<?= htmlspecialchars(isset($banner['starts_at']) && $banner['starts_at'] ? date('Y-m-d\TH:i', strtotime((string) $banner['starts_at'])) : '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                        </label>
                                        <label class="space-y-1 text-xs font-medium text-slate-500">
                                            <span>Bitiş</span>
                                            <input type="datetime-local" name="ends_at" value="<?= htmlspecialchars(isset($banner['ends_at']) && $banner['ends_at'] ? date('Y-m-d\TH:i', strtotime((string) $banner['ends_at'])) : '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                        </label>
                                    </div>
                                    <div class="flex items-center justify-between md:col-span-2">
                                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                                            <input type="checkbox" name="is_active" value="1" <?= !empty($banner['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                            Aktif
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 text-white text-xs font-semibold px-3 py-1.5 hover:bg-slate-700">Güncelle</button>
                                            <button type="submit" name="action" value="delete_hero_banner" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-1.5" onclick="return confirm('Banner silinsin mi?');">Sil</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($heroBanners[$sideKey])): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Bu sütunda kayıt bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($heroBanners[$sideKey])): ?>
                        <form method="post" class="inline-flex items-center gap-3" data-sortable-form data-sortable-source="#banner-list-<?= $sideKey ?>">
                            <input type="hidden" name="action" value="reorder_hero_banners" />
                            <input type="hidden" name="side" value="<?= htmlspecialchars($sideKey) ?>" />
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($heroBannerToken) ?>" />
                            <input type="hidden" name="order" id="banner-order-<?= $sideKey ?>" data-sort-order-input />
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 text-sm font-semibold px-4 py-2" data-sortable-submit>Sıralamayı Kaydet</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <h3 class="text-base font-semibold">Carousel Slaytları</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($heroSlides) ?> öğe</span>
        </div>
        <div class="p-5 space-y-4">
            <div id="slide-list" class="space-y-3" data-sortable-list data-sortable-target="#slide-order">
                <?php foreach ($heroSlides as $slide): ?>
                    <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/60 bg-white/95 dark:bg-slate-950/60 p-4 space-y-3" data-sortable-item data-id="<?= (int) $slide['id'] ?>" draggable="true">
                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>#<?= (int) $slide['id'] ?> · sıra <?= (int) $slide['sort_order'] ?></span>
                            <span class="cursor-move text-slate-400 dark:text-slate-500">⇅</span>
                        </div>
                        <form method="post" class="grid gap-3 md:grid-cols-2" autocomplete="off">
                            <input type="hidden" name="action" value="update_hero_slide" />
                            <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>" />
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($heroSlideToken) ?>" />
                            <label class="space-y-1 text-xs font-medium text-slate-500 md:col-span-2">
                                <span>Görsel URL</span>
                                <input type="text" name="image_url" value="<?= htmlspecialchars($slide['image_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Bağlantı</span>
                                <input type="text" name="link_url" value="<?= htmlspecialchars($slide['link_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Alt metin</span>
                                <input type="text" name="alt_text" value="<?= htmlspecialchars($slide['alt_text'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Sıra</span>
                                <input type="number" name="sort_order" value="<?= (int) ($slide['sort_order'] ?? 0) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <div class="grid sm:grid-cols-2 gap-3 md:col-span-2">
                                <label class="space-y-1 text-xs font-medium text-slate-500">
                                    <span>Başlangıç</span>
                                    <input type="datetime-local" name="starts_at" value="<?= htmlspecialchars(isset($slide['starts_at']) && $slide['starts_at'] ? date('Y-m-d\TH:i', strtotime((string) $slide['starts_at'])) : '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                </label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">
                                    <span>Bitiş</span>
                                    <input type="datetime-local" name="ends_at" value="<?= htmlspecialchars(isset($slide['ends_at']) && $slide['ends_at'] ? date('Y-m-d\TH:i', strtotime((string) $slide['ends_at'])) : '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                                </label>
                            </div>
                            <div class="flex items-center justify-between md:col-span-2">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <input type="checkbox" name="is_active" value="1" <?= !empty($slide['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                    Aktif
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 text-white text-xs font-semibold px-3 py-1.5 hover:bg-slate-700">Güncelle</button>
                                    <button type="submit" name="action" value="delete_hero_slide" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-1.5" onclick="return confirm('Slayt silinsin mi?');">Sil</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($heroSlides)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Henüz slayt eklenmemiş.</p>
                <?php endif; ?>
            </div>
            <?php if (!empty($heroSlides)): ?>
                <form method="post" class="inline-flex items-center gap-3" data-sortable-form data-sortable-source="#slide-list">
                    <input type="hidden" name="action" value="reorder_hero_slides" />
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($heroSlideToken) ?>" />
                    <input type="hidden" name="order" id="slide-order" data-sort-order-input />
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 text-sm font-semibold px-4 py-2" data-sortable-submit>Sıralamayı Kaydet</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
            <h3 class="text-base font-semibold">Mini Şerit Öğeleri</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($heroStripItems) ?> öğe</span>
        </div>
        <div class="p-5 space-y-6">
            <form method="post" class="grid gap-3 md:grid-cols-2">
                <input type="hidden" name="action" value="create_hero_strip" />
                <input type="hidden" name="_token" value="<?= htmlspecialchars($heroStripToken) ?>" />
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Başlık <span class="text-red-500">*</span></span>
                    <input type="text" name="title" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="Valorant" />
                </label>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Görsel URL <span class="text-red-500">*</span></span>
                    <input type="text" name="image_url" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="/media/strip-cs2.jpg" />
                </label>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Bağlantı <span class="text-red-500">*</span></span>
                    <input type="text" name="link_url" required class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" placeholder="/kategori/cs2" />
                </label>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Sıra</span>
                    <input type="number" name="sort_order" value="0" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                </label>
                <div class="md:col-span-2 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Öğeyi yayınla
                    </label>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-2 text-sm">Mini şerit ekle</button>
                </div>
            </form>

            <div id="strip-list" class="space-y-3" data-sortable-list data-sortable-target="#strip-order">
                <?php foreach ($heroStripItems as $strip): ?>
                    <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/60 bg-white/95 dark:bg-slate-950/60 p-4 space-y-3" data-sortable-item data-id="<?= (int) $strip['id'] ?>" draggable="true">
                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>#<?= (int) $strip['id'] ?> · sıra <?= (int) $strip['sort_order'] ?></span>
                            <span class="cursor-move text-slate-400 dark:text-slate-500">⇅</span>
                        </div>
                        <form method="post" class="grid gap-3 md:grid-cols-2" autocomplete="off">
                            <input type="hidden" name="action" value="update_hero_strip" />
                            <input type="hidden" name="id" value="<?= (int) $strip['id'] ?>" />
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($heroStripToken) ?>" />
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Başlık</span>
                                <input type="text" name="title" value="<?= htmlspecialchars($strip['title'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Görsel</span>
                                <input type="text" name="image_url" value="<?= htmlspecialchars($strip['image_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Bağlantı</span>
                                <input type="text" name="link_url" value="<?= htmlspecialchars($strip['link_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <label class="space-y-1 text-xs font-medium text-slate-500">
                                <span>Sıra</span>
                                <input type="number" name="sort_order" value="<?= (int) ($strip['sort_order'] ?? 0) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                            </label>
                            <div class="flex items-center justify-between md:col-span-2">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <input type="checkbox" name="is_active" value="1" <?= !empty($strip['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                    Aktif
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 text-white text-xs font-semibold px-3 py-1.5 hover:bg-slate-700">Güncelle</button>
                                    <button type="submit" name="action" value="delete_hero_strip" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold px-3 py-1.5" onclick="return confirm('Mini şerit öğesi silinsin mi?');">Sil</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($heroStripItems)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Henüz mini şerit öğesi eklenmemiş.</p>
                <?php endif; ?>
            </div>
            <?php if (!empty($heroStripItems)): ?>
                <form method="post" class="inline-flex items-center gap-3" data-sortable-form data-sortable-source="#strip-list">
                    <input type="hidden" name="action" value="reorder_hero_strip" />
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($heroStripToken) ?>" />
                    <input type="hidden" name="order" id="strip-order" data-sort-order-input />
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-950/60 text-sm font-semibold px-4 py-2" data-sortable-submit>Sıralamayı Kaydet</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div id="hero-settings" class="rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200/60 dark:border-slate-800/60">
            <h3 class="text-base font-semibold">Hero Ayarları</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Carousel autoplay ve gezinme seçeneklerini yönetin.</p>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="update_hero_settings" />
            <input type="hidden" name="_token" value="<?= htmlspecialchars($heroSettingsToken) ?>" />
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="autoplay" value="1" <?= !empty($heroSettings['autoplay']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Otomatik kaydırmayı etkinleştir
                </label>
                <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="show_arrows" value="1" <?= !empty($heroSettings['show_arrows']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Navigasyon okları açık
                </label>
                <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="show_dots" value="1" <?= !empty($heroSettings['show_dots']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Nokta göstergeleri açık
                </label>
                <label class="space-y-1 text-xs font-medium text-slate-500">
                    <span>Autoplay süresi (ms)</span>
                    <input type="number" name="autoplay_ms" min="2000" step="500" value="<?= (int) ($heroSettings['autoplay_ms'] ?? 4000) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/60 px-3 py-2 text-sm" />
                </label>
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 text-white font-semibold px-4 py-2 text-sm hover:bg-slate-700">Ayarları Kaydet</button>
        </form>
    </div>
</section>

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
