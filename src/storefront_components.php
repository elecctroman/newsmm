<?php

declare(strict_types=1);

function storefrontImageSources($image, string $fallback): array
{
    $alt = '';
    if (is_array($image)) {
        $lg = (string) ($image['lg'] ?? $image['src'] ?? $fallback);
        $md = (string) ($image['md'] ?? $lg);
        $sm = (string) ($image['sm'] ?? $md);
        $alt = (string) ($image['alt'] ?? '');
    } else {
        $lg = (string) ($image ?: $fallback);
        $md = $lg;
        $sm = $lg;
    }

    return [
        'src' => $lg,
        'srcset' => htmlspecialchars($sm, ENT_QUOTES) . ' 384w, ' . htmlspecialchars($md, ENT_QUOTES) . ' 768w, ' . htmlspecialchars($lg, ENT_QUOTES) . ' 1280w',
        'alt' => $alt,
    ];
}

function storefrontRenderHeader(array $company, int $cartCount, ?array $customer, array $navCategories, string $logo): void
{
    $brandName = $company['name'] ?? 'Lisansonay';
    $tagline = $company['tagline'] ?? 'Lisans Marketi';
    $searchPlaceholder = 'Ürün ara';
    $accountUrl = $customer ? '/account/index.php' : '/login.php';
    $userLabel = $customer ? 'Hesabım' : 'Giriş Yap';
    ?>
<header class="lk-header">
    <div class="lk-container">
        <div class="lk-header__top">
            <a class="lk-header__brand" href="/">
                <span class="lk-header__logo">
                    <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($brandName) ?>" loading="lazy" decoding="async">
                </span>
                <div class="lk-header__brand-text">
                    <h1><?= htmlspecialchars($brandName) ?></h1>
                    <p><?= htmlspecialchars($tagline) ?></p>
                </div>
            </a>
            <div class="lk-header__search">
                <form class="lk-search" action="/ara" method="get">
                    <input type="search" name="q" placeholder="<?= htmlspecialchars($searchPlaceholder) ?>" aria-label="Ürün ara">
                    <button type="submit">Ara</button>
                </form>
            </div>
            <div class="lk-header__actions">
                <?php if ($customer): ?>
                    <a class="lk-header__link" href="/account/index.php">Hesabım</a>
                <?php else: ?>
                    <a class="lk-header__link" href="/login.php">Giriş</a>
                    <a class="lk-header__link" href="/register.php">Kayıt</a>
                <?php endif; ?>
                <a class="lk-header__icon" href="<?= htmlspecialchars($accountUrl) ?>" aria-label="<?= htmlspecialchars($userLabel) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a5 5 0 110 10 5 5 0 010-10zm0 12c4.42 0 8 2.14 8 4.78V21H4v-2.22C4 16.14 7.58 14 12 14z"></path></svg>
                </a>
                <button class="lk-header__icon" type="button" aria-label="Favoriler">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09A6.01 6.01 0 0116.5 3C19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path></svg>
                </button>
                <a class="lk-header__icon" href="/account/cart.php" aria-label="Sepet">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h-2l-1 2v2h2l2.68 8.03A2 2 0 0010.58 18h7.84a2 2 0 001.94-1.47L22 7H7.42l-.42-1.24A1 1 0 006 5V4h1zm3 16a2 2 0 110 4 2 2 0 010-4zm8 0a2 2 0 110 4 2 2 0 010-4z"></path></svg>
                    <?php if ($cartCount > 0): ?>
                        <span class="lk-header__badge"><?= (int) $cartCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <nav class="lk-header__menu" aria-label="Birincil menü">
            <details class="lk-menu__categories">
                <summary aria-haspopup="listbox">Categories</summary>
                <div class="lk-menu__dropdown" role="listbox">
                    <?php foreach ($navCategories as $item): ?>
                        <a href="/kategori/<?= htmlspecialchars($item['slug']) ?>" role="option"><?= htmlspecialchars($item['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <a class="lk-menu__link" href="/">Home</a>
            <a class="lk-menu__link" href="/about">About</a>
            <a class="lk-menu__link" href="/contact">Contact</a>
            <a class="lk-menu__link" href="/blog">Blog</a>
        </nav>
    </div>
</header>
<?php
}

function storefrontRenderFooter(array $company): void
{
    $brandName = $company['name'] ?? 'Lisansonay';
    $tagline = $company['tagline'] ?? 'Dijital lisans ve abonelik pazarınız.';
    $email = $company['support_email'] ?? 'destek@lisansonay.com';
    $phone = $company['support_phone'] ?? '+90 555 000 00 00';
    ?>
<footer class="lk-footer">
    <div class="lk-container">
        <div class="lk-footer__inner">
            <div class="lk-footer__brand">
                <strong><?= htmlspecialchars($brandName) ?></strong>
                <p><?= htmlspecialchars($tagline) ?></p>
                <div class="lk-footer__social" aria-label="Sosyal bağlantılar">
                    <a href="https://wa.me/" aria-label="WhatsApp" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0012.05 0C5.5.02.2 5.33.23 11.88c.01 2.1.56 4.14 1.6 5.95L0 24l6.35-1.8a11.86 11.86 0 005.68 1.48h.05c6.54-.03 11.84-5.34 11.82-11.89a11.84 11.84 0 00-3.38-8.31z"></path></svg></a>
                    <a href="https://t.me/" aria-label="Telegram" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.04 15.53l-.36 5.07c.51 0 .73-.22 1-.48l2.4-2.3 4.97 3.65c.91.5 1.56.24 1.8-.85l3.27-15.33.01-.01c.29-1.36-.49-1.89-1.38-1.56L1.63 10.48c-1.33.51-1.31 1.25-.24 1.58l5.7 1.78 13.24-8.35c.62-.39 1.18-.18.72.22L9.04 15.53z"></path></svg></a>
                    <a href="mailto:<?= htmlspecialchars($email) ?>" aria-label="E-posta"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm8 8l10-6H2l10 6zm0 2l-10-6v10h20V8l-10 6z"></path></svg></a>
                </div>
            </div>
            <div class="lk-footer__links">
                <h4>Keşfet</h4>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/about">About</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/blog">Blog</a></li>
                </ul>
            </div>
            <div class="lk-footer__links">
                <h4>İletişim</h4>
                <ul>
                    <li><?= htmlspecialchars($email) ?></li>
                    <li><?= htmlspecialchars($phone) ?></li>
                </ul>
            </div>
        </div>
        <p class="lk-footer__meta">© <?= date('Y') ?> <?= htmlspecialchars($brandName) ?>. Tüm hakları saklıdır.</p>
    </div>
</footer>
<?php
}

function storefrontRenderProductCard(array $product, string $cartToken, string $currentUrl): void
{
    $productId = (int) ($product['id'] ?? 0);
    $productSlug = (string) ($product['slug'] ?? $productId);
    $productName = (string) ($product['name'] ?? 'Ürün');
    $priceLabel = (string) ($product['price_label'] ?? formatCurrency((int)($product['price_cents'] ?? 0)));
    $stockStatus = (string) ($product['stock_status'] ?? 'in_stock');
    $badges = [];
    if (!empty($product['badges']) && is_array($product['badges'])) {
        foreach ($product['badges'] as $badge) {
            if (is_string($badge) && $badge !== '') {
                $badges[] = $badge;
            }
        }
    }
    $image = storefrontImageSources($product['image'] ?? $product['primary_image'] ?? $product['primary_image_url'] ?? null, '/public/assets/img/placeholder-hero.svg');
    $alt = $image['alt'] !== '' ? $image['alt'] : $productName;
    ?>
<article class="lk-card" data-product="<?= $productId ?>">
    <div class="lk-card__media">
        <?php if (!empty($badges)): ?>
            <div class="lk-badge-group">
                <?php foreach ($badges as $badge): ?>
                    <span class="lk-badge"><?= htmlspecialchars($badge) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="/urun/<?= htmlspecialchars($productSlug) ?>">
            <img src="<?= htmlspecialchars($image['src']) ?>" srcset="<?= htmlspecialchars($image['srcset']) ?>" sizes="(max-width: 640px) 90vw, (max-width: 1024px) 45vw, 240px" alt="<?= htmlspecialchars($alt) ?>" loading="lazy" decoding="async">
        </a>
    </div>
    <h3 class="lk-card__title"><a href="/urun/<?= htmlspecialchars($productSlug) ?>" style="text-decoration:none; color:inherit;"><?= htmlspecialchars($productName) ?></a></h3>
    <div class="lk-card__meta">
        <span class="lk-price"><?= htmlspecialchars($priceLabel) ?></span>
        <span class="lk-stock <?= $stockStatus === 'in_stock' ? 'lk-stock--in' : '' ?>">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6.173 14.727a1 1 0 01-1.414 0l-3.486-3.486a1 1 0 111.414-1.414l2.779 2.779 6.364-6.364a1 1 0 111.414 1.414l-7.071 7.071z"></path></svg>
            <?= $stockStatus === 'in_stock' ? 'Stokta' : 'Stokta değil' ?>
        </span>
    </div>
    <div class="lk-card__actions">
        <form method="post" action="/cart.php">
            <input type="hidden" name="form_key" value="cart-action">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($cartToken) ?>">
            <input type="hidden" name="cart_action" value="add">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <input type="hidden" name="quantity" value="1">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
            <button type="submit" class="lk-btn lk-btn--primary lk-btn--full">Add To Cart</button>
        </form>
    </div>
</article>
<?php
}
