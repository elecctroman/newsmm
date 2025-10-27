(function () {
    'use strict';

    const catalog = window.catalogData || { categories: [], products: {} };
    const state = {
        theme: 'light',
        selectedCategory: null,
        sort: 'asc',
        query: '',
        igTab: 'hesaplar',
    };

    const root = document.documentElement;
    const themeBtn = document.getElementById('themeBtn');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    const searchInput = document.getElementById('globalSearch');
    const sortSelect = document.getElementById('sorter');
    const categoryFilters = document.getElementById('categoryFilters');
    const instagramTabs = document.getElementById('instagramTabs');
    const selectionLabel = document.getElementById('selectionLabel');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const productCount = document.getElementById('productCount');
    const productTableBody = document.getElementById('productTableBody');
    const productEmpty = document.getElementById('productEmpty');
    const insightList = document.getElementById('insightList');
    const metricProducts = document.getElementById('metricProducts');
    const metricCategories = document.getElementById('metricCategories');
    const metricFollowers = document.getElementById('metricFollowers');
    const metricAverage = document.getElementById('metricAverage');

    const categoryMap = new Map();
    const normalizedProducts = {};

    catalog.categories.forEach((cat) => {
        categoryMap.set(cat.slug, cat);
    });

    Object.keys(catalog.products || {}).forEach((slug) => {
        const items = Array.isArray(catalog.products[slug]) ? catalog.products[slug] : [];
        normalizedProducts[slug] = items.map((item) => ({
            id: item.id,
            name: item.name,
            price: item.price,
            priceCents: Number(item.priceCents || item.price_cents || 0),
            note: item.note || '',
            category: item.category || slug,
            categoryTitle: item.categoryTitle || (categoryMap.get(item.category || slug)?.title || ''),
        }));
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('tr-TR').format(Number(value) || 0);
    }

    function formatCurrencyFromCents(value) {
        const amount = Number(value) / 100;
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY',
            minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount || 0);
    }

    function getAllProducts() {
        const list = [];
        Object.keys(normalizedProducts).forEach((slug) => {
            normalizedProducts[slug].forEach((item) => {
                list.push(item);
            });
        });
        return list;
    }

    function isInstagramFollower(product) {
        return product.category === 'instagram' && product.name.trim().toLowerCase().startsWith('(takipçi)');
    }

    function buildCategoryFilters() {
        if (!categoryFilters) {
            return;
        }
        const fragments = [];
        const totalCount = formatNumber(getAllProducts().length);
        fragments.push([
            '<button type="button" data-category="all" class="group inline-flex items-center gap-2 rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/70 px-3 py-2 text-sm transition hover:border-brand-500 hover:text-brand-600">',
            '<span class="font-medium">Tümü</span>',
            `<span class="text-xs px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 group-hover:bg-brand-100 group-hover:text-brand-700">${totalCount}</span>`,
            '</button>'
        ].join(''));

        catalog.categories.forEach((cat) => {
            const count = formatNumber((normalizedProducts[cat.slug] || []).length);
            fragments.push([
                '<button type="button" data-category="',
                escapeHtml(cat.slug),
                '" class="group inline-flex items-center gap-2 rounded-xl border border-slate-200/70 dark:border-slate-800/70 bg-white/70 dark:bg-slate-900/70 px-3 py-2 text-sm transition hover:border-brand-500 hover:text-brand-600">',
                '<span class="font-medium">',
                escapeHtml(cat.title),
                '</span>',
                `<span class="text-xs px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 group-hover:bg-brand-100 group-hover:text-brand-700">${count}</span>`,
                '</button>'
            ].join(''));
        });

        categoryFilters.innerHTML = fragments.join('');
    }

    function updateCategoryButtons() {
        if (!categoryFilters) {
            return;
        }
        const buttons = categoryFilters.querySelectorAll('[data-category]');
        buttons.forEach((btn) => {
            const slug = btn.getAttribute('data-category');
            const isActive = (!state.selectedCategory && slug === 'all') || (!!state.selectedCategory && slug === state.selectedCategory);
            btn.classList.toggle('border-brand-500', isActive);
            btn.classList.toggle('text-brand-600', isActive);
            btn.classList.toggle('dark:text-brand-200', isActive);
            btn.classList.toggle('shadow', isActive);
        });
    }

    function loadTheme() {
        const stored = localStorage.getItem('lisansonay-theme');
        if (stored === 'dark' || stored === 'light') {
            state.theme = stored;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            state.theme = 'dark';
        }
        applyTheme();
    }

    function applyTheme() {
        const isDark = state.theme === 'dark';
        root.classList.toggle('dark', isDark);
        if (themeText) {
            themeText.textContent = isDark ? 'Gece' : 'Aydınlık';
        }
        if (themeIcon) {
            themeIcon.innerHTML = isDark
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 15.5A9 9 0 1110.5 3a7 7 0 0010.5 12.5z"></path></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5" stroke="currentColor" stroke-width="1.5" fill="none"></path></svg>';
        }
        try {
            localStorage.setItem('lisansonay-theme', state.theme);
        } catch (err) {
            // ignore storage errors in restricted environments
        }
    }

    function toggleTheme() {
        state.theme = state.theme === 'light' ? 'dark' : 'light';
        applyTheme();
    }

    function getFilteredProducts() {
        let list = [];
        if (state.selectedCategory) {
            list = (normalizedProducts[state.selectedCategory] || []).slice();
        } else {
            list = getAllProducts();
        }

        if (state.selectedCategory === 'instagram') {
            list = list.filter((item) => {
                const follower = isInstagramFollower(item);
                return state.igTab === 'takipci' ? follower : !follower;
            });
        }

        if (state.query.trim() !== '') {
            const q = state.query.trim().toLowerCase();
            list = list.filter((item) => item.name.toLowerCase().includes(q));
        }

        list.sort((a, b) => {
            const priceA = Number(a.priceCents || 0);
            const priceB = Number(b.priceCents || 0);
            return state.sort === 'asc' ? priceA - priceB : priceB - priceA;
        });

        return list;
    }

    function createBadgeGroup(product, index) {
        const badges = [];
        const note = (product.note || '').toLowerCase();
        if (index < 5) {
            badges.push('<span class="inline-flex items-center rounded-lg bg-brand-100 text-brand-700 px-2 py-0.5 text-[11px] font-semibold">Öne Çıkan</span>');
        }
        if (note.includes('popüler')) {
            badges.push('<span class="inline-flex items-center rounded-lg bg-amber-100 text-amber-700 px-2 py-0.5 text-[11px] font-semibold">Popüler</span>');
        }
        if (!badges.length && product.category === 'instagram') {
            badges.push('<span class="inline-flex items-center rounded-lg bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[11px] font-semibold">Sosyal</span>');
        }
        return badges.join('');
    }

    function createStatusBadge(product) {
        let label = 'Standart';
        let classes = 'bg-slate-100 text-slate-600';
        if (product.category === 'instagram') {
            if (isInstagramFollower(product)) {
                label = 'Takipçi Paketi';
                classes = 'bg-purple-100 text-purple-700';
            } else {
                label = 'Instagram Hesabı';
                classes = 'bg-blue-100 text-blue-700';
            }
        } else if ((product.note || '').toLowerCase().includes('garanti')) {
            label = 'Garantili';
            classes = 'bg-emerald-100 text-emerald-700';
        } else if (product.priceCents >= 40000) {
            label = 'Premium';
            classes = 'bg-brand-100 text-brand-700';
        } else if (product.priceCents <= 5000) {
            label = 'Ekonomik';
            classes = 'bg-slate-100 text-slate-600';
        }
        return `<span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold ${classes}">${label}</span>`;
    }

    function renderInstagramTabs() {
        if (!instagramTabs) {
            return;
        }
        const shouldShow = state.selectedCategory === 'instagram';
        instagramTabs.hidden = !shouldShow;
        if (!shouldShow) {
            return;
        }
        const buttons = Array.from(instagramTabs.querySelectorAll('[data-ig-tab]'));
        buttons.forEach((btn) => {
            const tab = btn.getAttribute('data-ig-tab');
            const isActive = tab === state.igTab;
            btn.classList.toggle('bg-brand-600', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('border-brand-600', isActive);
            btn.classList.toggle('bg-white/70', !isActive);
            btn.classList.toggle('dark:bg-slate-900/70', !isActive);
            btn.classList.toggle('text-slate-600', !isActive);
            btn.classList.toggle('dark:text-slate-300', !isActive);
        });
    }

    function renderFilterSummary() {
        if (!selectionLabel) {
            return;
        }
        const parts = [];
        if (state.selectedCategory) {
            const category = categoryMap.get(state.selectedCategory);
            parts.push(category ? `${category.title} kategorisi` : 'Kategori seçildi');
        } else {
            parts.push('Tüm kategoriler görüntüleniyor');
        }
        if (state.query.trim() !== '') {
            parts.push(`“${state.query.trim()}” için arama uygulanıyor`);
        }
        if (state.selectedCategory === 'instagram' && state.igTab === 'takipci') {
            parts.push('Sadece takipçi paketleri listeleniyor');
        }
        selectionLabel.textContent = parts.join(' · ');
        if (clearFiltersBtn) {
            const shouldShow = Boolean(state.selectedCategory || state.query.trim() || (state.selectedCategory === 'instagram' && state.igTab === 'takipci'));
            clearFiltersBtn.classList.toggle('hidden', !shouldShow);
        }
    }

    function renderProducts() {
        if (!productTableBody || !productCount) {
            return;
        }
        const products = getFilteredProducts();
        productCount.textContent = `${formatNumber(products.length)} ürün`;
        productTableBody.innerHTML = '';

        if (products.length === 0) {
            if (productEmpty) {
                productEmpty.classList.remove('hidden');
            }
            renderInsights(products);
            return;
        }

        if (productEmpty) {
            productEmpty.classList.add('hidden');
        }

        products.forEach((product, index) => {
            const row = document.createElement('tr');
            row.className = 'bg-white/70 dark:bg-slate-950/30';
            const categoryTitle = product.categoryTitle || (categoryMap.get(product.category)?.title || 'Kategori');
            const badgeHtml = createBadgeGroup(product, index);
            const statusBadge = createStatusBadge(product);
            row.innerHTML = [
                '<td class="px-6 py-4 align-top">',
                '<div class="flex flex-col gap-2">',
                '<div class="flex flex-wrap items-center gap-2">',
                '<span class="font-semibold text-slate-800 dark:text-slate-100">', escapeHtml(product.name), '</span>',
                badgeHtml,
                '</div>',
                product.note ? `<p class="text-xs text-slate-500 max-w-xl">${escapeHtml(product.note)}</p>` : '',
                '</div>',
                '</td>',
                '<td class="px-6 py-4 align-top">',
                `<span class="inline-flex items-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-900/70 dark:text-slate-200 px-2.5 py-1 text-xs font-medium">${escapeHtml(categoryTitle)}</span>`,
                '</td>',
                '<td class="px-6 py-4 align-top">',
                `<span class="font-semibold text-slate-800 dark:text-slate-100">${escapeHtml(product.price)}</span>`,
                '</td>',
                '<td class="px-6 py-4 align-top">',
                statusBadge,
                '</td>',
                '<td class="px-6 py-4 align-top text-right">',
                '<div class="inline-flex items-center gap-2">',
                '<button type="button" class="rounded-lg border border-slate-200/70 dark:border-slate-800/70 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 hover:border-brand-500 hover:text-brand-600 transition">Detay</button>',
                '<button type="button" class="rounded-lg bg-brand-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-brand-700 transition">Düzenle</button>',
                '</div>',
                '</td>'
            ].join('');
            productTableBody.appendChild(row);
        });

        renderInsights(products);
    }

    function renderInsights(products) {
        if (!insightList) {
            return;
        }
        if (!products || products.length === 0) {
            insightList.innerHTML = '<li class="text-sm text-slate-500">Veri bulunamadı. Farklı filtreler deneyin.</li>';
            return;
        }
        const byPriceDesc = products.slice().sort((a, b) => (Number(b.priceCents) || 0) - (Number(a.priceCents) || 0));
        const byPriceAsc = products.slice().sort((a, b) => (Number(a.priceCents) || 0) - (Number(b.priceCents) || 0));
        const mostExpensive = byPriceDesc[0];
        const mostAffordable = byPriceAsc[0];
        const followerCount = products.filter((item) => isInstagramFollower(item)).length;
        const insights = [];
        if (mostExpensive) {
            insights.push([
                '<li class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 px-3 py-3">',
                '<div>',
                '<p class="text-xs uppercase tracking-[0.2em] text-slate-500">En yüksek fiyatlı</p>',
                `<p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">${escapeHtml(mostExpensive.name)}</p>`,
                '</div>',
                `<span class="text-sm font-semibold text-brand-600 dark:text-brand-200">${escapeHtml(mostExpensive.price)}</span>`,
                '</li>'
            ].join(''));
        }
        if (mostAffordable && mostAffordable !== mostExpensive) {
            insights.push([
                '<li class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 px-3 py-3">',
                '<div>',
                '<p class="text-xs uppercase tracking-[0.2em] text-slate-500">En erişilebilir</p>',
                `<p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">${escapeHtml(mostAffordable.name)}</p>`,
                '</div>',
                `<span class="text-sm font-semibold text-emerald-600 dark:text-emerald-300">${escapeHtml(mostAffordable.price)}</span>`,
                '</li>'
            ].join(''));
        }
        insights.push([
            '<li class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 px-3 py-3">',
            '<div>',
            '<p class="text-xs uppercase tracking-[0.2em] text-slate-500">Takipçi odaklı</p>',
            `<p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">${formatNumber(followerCount)} paket görüntüleniyor</p>`,
            '</div>',
            '<span class="text-xs text-slate-500">Instagram seçiliyse detayları kontrol edin</span>',
            '</li>'
        ].join(''));
        insightList.innerHTML = insights.join('');
    }

    function renderMetrics() {
        if (!metricProducts || !metricCategories || !metricFollowers || !metricAverage) {
            return;
        }
        const allProducts = getAllProducts();
        const followerCount = allProducts.filter((item) => isInstagramFollower(item)).length;
        const totalCents = allProducts.reduce((sum, item) => sum + (Number(item.priceCents) || 0), 0);
        const averageCents = allProducts.length ? Math.round(totalCents / allProducts.length) : 0;

        metricProducts.textContent = formatNumber(allProducts.length);
        metricCategories.textContent = formatNumber(catalog.categories.length);
        metricFollowers.textContent = formatNumber(followerCount);
        metricAverage.textContent = formatCurrencyFromCents(averageCents);
    }

    function attachEvents() {
        if (themeBtn) {
            themeBtn.addEventListener('click', toggleTheme);
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                state.sort = sortSelect.value;
                renderFilterSummary();
                renderInstagramTabs();
                renderProducts();
            });
        }
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                state.query = searchInput.value;
                renderFilterSummary();
                renderInstagramTabs();
                renderProducts();
            });
        }
        if (categoryFilters) {
            categoryFilters.addEventListener('click', (event) => {
                const target = event.target.closest('[data-category]');
                if (!target) {
                    return;
                }
                const slug = target.getAttribute('data-category');
                if (slug === 'all') {
                    state.selectedCategory = null;
                } else {
                    state.selectedCategory = slug;
                }
                state.igTab = 'hesaplar';
                renderFilterSummary();
                updateCategoryButtons();
                renderInstagramTabs();
                renderProducts();
            });
        }
        if (instagramTabs) {
            instagramTabs.addEventListener('click', (event) => {
                const target = event.target.closest('[data-ig-tab]');
                if (!target) {
                    return;
                }
                const tab = target.getAttribute('data-ig-tab');
                if (!tab) {
                    return;
                }
                state.igTab = tab;
                renderFilterSummary();
                renderInstagramTabs();
                renderProducts();
            });
        }
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                state.selectedCategory = null;
                state.query = '';
                state.sort = 'asc';
                state.igTab = 'hesaplar';
                if (searchInput) {
                    searchInput.value = '';
                }
                if (sortSelect) {
                    sortSelect.value = 'asc';
                }
                renderFilterSummary();
                updateCategoryButtons();
                renderInstagramTabs();
                renderProducts();
            });
        }
    }

    buildCategoryFilters();
    updateCategoryButtons();
    loadTheme();
    renderMetrics();
    renderFilterSummary();
    renderInstagramTabs();
    renderProducts();
    attachEvents();
})();
