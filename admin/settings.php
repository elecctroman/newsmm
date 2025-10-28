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

$pageTitle = 'Genel Ayarlar';
$activeNav = 'settings';
$currentUser = $user;

$formErrors = [];
$settings = [];
$branding = buildBrandingContext([]);

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $settings = fetchSettings($pdo);
    } catch (Throwable $settingsException) {
        $formErrors[] = 'Ayarlar okunurken bir hata oluştu: ' . $settingsException->getMessage();
    }
}

if (!empty($settings)) {
    $branding = buildBrandingContext($settings);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken('settings-update', $_POST['_token'] ?? null)) {
        $formErrors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif (!isset($pdo) || !$pdo instanceof PDO) {
        $formErrors[] = 'Veritabanı bağlantısı kurulamadı.';
    } else {
        $inputs = [
            'company_name' => trim((string)($_POST['company_name'] ?? 'Lisansonay')),
            'company_tagline' => trim((string)($_POST['company_tagline'] ?? '')),
            'support_email' => trim((string)($_POST['support_email'] ?? '')),
            'support_phone' => trim((string)($_POST['support_phone'] ?? '')),
            'support_hours' => trim((string)($_POST['support_hours'] ?? '')),
            'whatsapp_link' => trim((string)($_POST['whatsapp_link'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'hero_title' => trim((string)($_POST['hero_title'] ?? '')),
            'hero_subtitle' => trim((string)($_POST['hero_subtitle'] ?? '')),
            'hero_cta_label' => trim((string)($_POST['hero_cta_label'] ?? '')),
            'hero_cta_link' => trim((string)($_POST['hero_cta_link'] ?? '')),
            'instagram_url' => trim((string)($_POST['instagram_url'] ?? '')),
            'telegram_url' => trim((string)($_POST['telegram_url'] ?? '')),
            'twitter_url' => trim((string)($_POST['twitter_url'] ?? '')),
            'facebook_url' => trim((string)($_POST['facebook_url'] ?? '')),
            'linkedin_url' => trim((string)($_POST['linkedin_url'] ?? '')),
            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
        ];

        $primaryInput = trim((string)($_POST['primary_color'] ?? ''));
        $accentInput = trim((string)($_POST['accent_color'] ?? ''));

        if ($inputs['company_name'] === '') {
            $formErrors[] = 'Firma adı boş bırakılamaz.';
        }
        if ($inputs['support_email'] === '' || !filter_var($inputs['support_email'], FILTER_VALIDATE_EMAIL)) {
            $formErrors[] = 'Geçerli bir destek e-posta adresi girin.';
        }

        $validateUrl = static function (string $value, bool $allowHash = false): bool {
            if ($value === '') {
                return true;
            }
            if ($allowHash && strpos($value, '#') === 0) {
                return true;
            }
            if (preg_match('/^(mailto:|tel:|\/)/i', $value)) {
                return true;
            }
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        };

        foreach (['whatsapp_link', 'instagram_url', 'telegram_url', 'twitter_url', 'facebook_url', 'linkedin_url'] as $urlKey) {
            if (!$validateUrl($inputs[$urlKey])) {
                $formErrors[] = ucfirst(str_replace('_', ' ', $urlKey)) . ' alanı için geçerli bir bağlantı girin.';
                break;
            }
        }

        if ($inputs['hero_cta_link'] !== '' && !$validateUrl($inputs['hero_cta_link'], true)) {
            $formErrors[] = 'CTA bağlantısı için geçerli bir bağlantı girin.';
        }

        if ($inputs['meta_description'] !== '' && mb_strlen($inputs['meta_description']) > 320) {
            $formErrors[] = 'Meta açıklaması 320 karakteri geçmemelidir.';
        }

        if ($primaryInput !== '' && !preg_match('/^#?[0-9a-fA-F]{3,6}$/', $primaryInput)) {
            $formErrors[] = 'Ana renk kodu geçerli bir HEX değeri olmalıdır.';
        }
        if ($accentInput !== '' && !preg_match('/^#?[0-9a-fA-F]{3,6}$/', $accentInput)) {
            $formErrors[] = 'Vurgu rengi geçerli bir HEX değeri olmalıdır.';
        }

        $primaryColor = normalizeHexColor($primaryInput !== '' ? $primaryInput : ($branding['primary_color'] ?? '#4c51bf'), '#4c51bf');
        $accentColor = normalizeHexColor($accentInput !== '' ? $accentInput : ($branding['accent_color'] ?? '#f97316'), '#f97316');

        $updates = [
            'company_name' => $inputs['company_name'],
            'company_tagline' => $inputs['company_tagline'],
            'support_email' => $inputs['support_email'],
            'support_phone' => $inputs['support_phone'],
            'support_hours' => $inputs['support_hours'],
            'whatsapp_link' => $inputs['whatsapp_link'],
            'address' => $inputs['address'],
            'hero_title' => $inputs['hero_title'],
            'hero_subtitle' => $inputs['hero_subtitle'],
            'hero_cta_label' => $inputs['hero_cta_label'],
            'hero_cta_link' => $inputs['hero_cta_link'],
            'instagram_url' => $inputs['instagram_url'],
            'telegram_url' => $inputs['telegram_url'],
            'twitter_url' => $inputs['twitter_url'],
            'facebook_url' => $inputs['facebook_url'],
            'linkedin_url' => $inputs['linkedin_url'],
            'meta_title' => $inputs['meta_title'] !== '' ? $inputs['meta_title'] : 'Lisansonay · Ürün ve Lisans Çözümleri',
            'meta_description' => $inputs['meta_description'],
            'primary_color' => $primaryColor,
            'accent_color' => $accentColor,
        ];

        $mediaDefinitions = [
            'logo_light' => ['label' => 'Aydınlık Tema Logosu'],
            'logo_dark' => ['label' => 'Karanlık Tema Logosu'],
            'favicon' => ['label' => 'Favicon', 'max_size' => 2 * 1024 * 1024],
            'login_visual' => ['label' => 'Giriş Ekranı Görseli', 'max_size' => 5 * 1024 * 1024],
        ];
        $allowedMimes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ];

        $uploadDir = __DIR__ . '/../public/uploads/branding';
        $publicBase = '/public/uploads/branding';
        $directoryEnsured = false;

        $removeStoredFile = static function (?string $storedPath): void {
            if ($storedPath === null) {
                return;
            }
            $trimmed = trim($storedPath);
            if ($trimmed === '' || strpos($trimmed, '/public/uploads/branding/') !== 0) {
                return;
            }
            $absolute = realpath(__DIR__ . '/..' . dirname($trimmed));
            if ($absolute === false) {
                $absolute = __DIR__ . '/..' . dirname($trimmed);
            }
            $target = rtrim($absolute, '/') . '/' . basename($trimmed);
            if (is_file($target)) {
                @unlink($target);
            }
        };

        foreach ($mediaDefinitions as $key => $definition) {
            $removeKey = 'remove_' . $key;
            if (isset($_POST[$removeKey]) && $_POST[$removeKey] === '1') {
                $updates[$key] = '';
                $removeStoredFile($branding[$key] ?? null);
            }

            if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = $_FILES[$key];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $formErrors[] = $definition['label'] . ' yüklenirken bir hata oluştu.';
                continue;
            }

            $maxSize = $definition['max_size'] ?? (4 * 1024 * 1024);
            if ($file['size'] > $maxSize) {
                $formErrors[] = $definition['label'] . ' en fazla ' . number_format($maxSize / 1024 / 1024, 1) . 'MB olabilir.';
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']) ?: '';
            if (!isset($allowedMimes[$mime])) {
                $formErrors[] = $definition['label'] . ' için desteklenmeyen dosya türü yüklediniz.';
                continue;
            }

            if (!$directoryEnsured) {
                ensureDirectory($uploadDir);
                $directoryEnsured = true;
            }

            try {
                $random = bin2hex(random_bytes(4));
            } catch (Throwable $randomException) {
                $random = substr(md5((string) microtime(true)), 0, 8);
            }

            $filename = sprintf('%s-%s-%s.%s', $key, date('YmdHis'), $random, $allowedMimes[$mime]);
            $targetPath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $formErrors[] = $definition['label'] . ' sunucuya kaydedilemedi.';
                continue;
            }

            @chmod($targetPath, 0644);

            $publicPath = $publicBase . '/' . $filename;
            $updates[$key] = $publicPath;
            $removeStoredFile($branding[$key] ?? null);
        }

        if (empty($formErrors)) {
            updateSettings($pdo, $updates);
            logActivity($pdo, (int) $user['id'], 'update', 'settings', null, 'Genel ayarlar güncellendi');
            addFlash('success', 'Ayarlar başarıyla güncellendi.');
            redirect('settings.php');
        } else {
            $branding = buildBrandingContext(array_merge($settings, $updates));
        }
    }
}

$token = issueCsrfToken('settings-update');

require __DIR__ . '/../src/admin_page_start.php';
?>
<?php if (!isset($pdo) || !$pdo instanceof PDO): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 dark:border-red-600/40 dark:bg-red-900/30 dark:text-red-200 px-4 py-4">
        <h2 class="text-sm font-semibold">Veritabanı bağlantısı gerekli</h2>
        <p class="text-xs mt-1">Ayarları güncellemek için veritabanı bağlantısı kurulmalıdır.</p>
    </div>
<?php else: ?>
    <section class="max-w-5xl space-y-6">
        <div class="space-y-1">
            <h2 class="text-base font-semibold">Marka ve site ayarları</h2>
            <p class="text-xs text-slate-500">Logolarınızı, iletişim bilgilerinizi ve müşteri portalında görünen metinleri buradan yönetin.</p>
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

        <form method="post" enctype="multipart/form-data" class="space-y-6 rounded-3xl border border-slate-200/70 dark:border-slate-800/70 bg-white/80 dark:bg-slate-900/70 shadow-sm p-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Kurumsal Bilgiler</h3>
                        <p class="text-xs text-slate-500">Firma kimliği ve iletişim ayrıntıları.</p>
                    </div>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>Firma Adı *</span>
                        <input type="text" name="company_name" value="<?= htmlspecialchars($branding['company_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>Firma Sloganı</span>
                        <input type="text" name="company_tagline" value="<?= htmlspecialchars($branding['company_tagline'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="text-xs font-medium text-slate-500 space-y-1 block">
                            <span>Destek E-posta *</span>
                            <input type="email" name="support_email" value="<?= htmlspecialchars($branding['support_email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" required />
                        </label>
                        <label class="text-xs font-medium text-slate-500 space-y-1 block">
                            <span>Destek Telefon</span>
                            <input type="text" name="support_phone" value="<?= htmlspecialchars($branding['support_phone'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                        </label>
                    </div>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>Destek Saatleri</span>
                        <input type="text" name="support_hours" value="<?= htmlspecialchars($branding['support_hours'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="Örn. Hafta içi 09:00 - 18:00" />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>Adres</span>
                        <textarea name="address" rows="3" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm resize-none" placeholder="İsteğe bağlı">
<?= htmlspecialchars($branding['address'] ?? '') ?></textarea>
                    </label>
                </div>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">İletişim & Sosyal Bağlantılar</h3>
                        <p class="text-xs text-slate-500">Müşterilerinizin size ulaşması için kanallar.</p>
                    </div>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>WhatsApp Bağlantısı</span>
                        <input type="url" name="whatsapp_link" value="<?= htmlspecialchars($branding['whatsapp_link'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="https://wa.me/..." />
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="text-xs font-medium text-slate-500 space-y-1 block">
                            <span>Instagram</span>
                            <input type="url" name="instagram_url" value="<?= htmlspecialchars($branding['instagram_url'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                        </label>
                        <label class="text-xs font-medium text-slate-500 space-y-1 block">
                            <span>Telegram</span>
                            <input type="url" name="telegram_url" value="<?= htmlspecialchars($branding['telegram_url'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                        </label>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="text-xs font-medium text-slate-500 space-y-1 block">
                            <span>Twitter / X</span>
                            <input type="url" name="twitter_url" value="<?= htmlspecialchars($branding['twitter_url'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                        </label>
                        <label class="text-xs font-medium text-slate-500 space-y-1 block">
                            <span>Facebook</span>
                            <input type="url" name="facebook_url" value="<?= htmlspecialchars($branding['facebook_url'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                        </label>
                    </div>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>LinkedIn</span>
                        <input type="url" name="linkedin_url" value="<?= htmlspecialchars($branding['linkedin_url'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                    </label>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Ana Sayfa İçeriği</h3>
                    <p class="text-xs text-slate-500">Katalog sayfanızın üst kısmında görünen hero metinleri.</p>
                </div>
                <label class="text-xs font-medium text-slate-500 space-y-1 block">
                    <span>Başlık</span>
                    <input type="text" name="hero_title" value="<?= htmlspecialchars($branding['hero_title'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1 block">
                    <span>Alt Başlık</span>
                    <textarea name="hero_subtitle" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm resize-none" placeholder="Tanıtım metni">
<?= htmlspecialchars($branding['hero_subtitle'] ?? '') ?></textarea>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>CTA Etiket</span>
                        <input type="text" name="hero_cta_label" value="<?= htmlspecialchars($branding['hero_cta_label'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>CTA Bağlantısı</span>
                        <input type="text" name="hero_cta_link" value="<?= htmlspecialchars($branding['hero_cta_link'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="#catalog veya https://..." />
                    </label>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Görsel Kimlik</h3>
                    <p class="text-xs text-slate-500">Renk paleti ve logo yüklemeleri.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>Ana Renk</span>
                        <input type="text" name="primary_color" value="<?= htmlspecialchars($branding['primary_color'] ?? '#4c51bf') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="#4c51bf" />
                    </label>
                    <label class="text-xs font-medium text-slate-500 space-y-1 block">
                        <span>Vurgu Rengi</span>
                        <input type="text" name="accent_color" value="<?= htmlspecialchars($branding['accent_color'] ?? '#f97316') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" placeholder="#f97316" />
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach (['logo_light' => 'Aydınlık Tema Logosu', 'logo_dark' => 'Karanlık Tema Logosu', 'favicon' => 'Favicon', 'login_visual' => 'Giriş Görseli'] as $mediaKey => $label): ?>
                        <div class="space-y-2">
                            <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($label) ?></p>
                            <?php $currentMedia = $branding[$mediaKey] ?? null; ?>
                            <?php if ($currentMedia): ?>
                                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900/50 p-3 flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($currentMedia) ?>" alt="<?= htmlspecialchars($label) ?>" class="h-12 w-auto max-w-[140px] object-contain" />
                                    <label class="text-xs text-red-500 inline-flex items-center gap-2">
                                        <input type="checkbox" name="remove_<?= htmlspecialchars($mediaKey) ?>" value="1" class="rounded border-slate-300"> Kaldır
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="<?= htmlspecialchars($mediaKey) ?>" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-brand-500 file:text-white hover:file:bg-brand-600" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Meta & SEO</h3>
                    <p class="text-xs text-slate-500">Arama motorlarında görünen başlık ve açıklama.</p>
                </div>
                <label class="text-xs font-medium text-slate-500 space-y-1 block">
                    <span>Meta Başlık</span>
                    <input type="text" name="meta_title" value="<?= htmlspecialchars($branding['meta_title'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500 space-y-1 block">
                    <span>Meta Açıklama</span>
                    <textarea name="meta_description" rows="3" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/60 px-3 py-2 text-sm resize-none" placeholder="Maksimum 320 karakter">
<?= htmlspecialchars($branding['meta_description'] ?? '') ?></textarea>
                </label>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold shadow">
                    Ayarları Kaydet
                </button>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/../src/admin_page_end.php';
