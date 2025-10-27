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
    const categoryList = document.getElementById('categoryList');
    const productsSection = document.getElementById('productsSection');
    const productsHeading = document.getElementById('productsHeading');
    const productGrid = document.getElementById('productGrid');
    const productCount = document.getElementById('productCount');
    const sortContainer = document.getElementById('sortContainer');
    const sortSelect = document.getElementById('sorter');
    const clearCategoryBtn = document.getElementById('clearCategory');
    const searchInput = document.getElementById('top-search');
    const emptyMessage = document.getElementById('emptyMessage');
    const instagramTabs = document.getElementById('instagramTabs');
    const igTabButtons = instagramTabs ? instagramTabs.querySelectorAll('[data-ig-tab]') : [];

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function loadTheme() {
        const stored = localStorage.getItem('bhe-theme');
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
        themeText.textContent = isDark ? 'Gece' : 'Gündüz';
        themeIcon.innerHTML = isDark
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 15.5A9 9 0 1110.5 3a7 7 0 0010.5 12.5z"></path></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5" stroke="currentColor" stroke-width="1.5" fill="none"></path></svg>';
        localStorage.setItem('bhe-theme', state.theme);
    }

    function toggleTheme() {
        state.theme = state.theme === 'light' ? 'dark' : 'light';
        applyTheme();
    }

    function updateCategoryButtons() {
        const buttons = categoryList ? categoryList.querySelectorAll('[data-category]') : [];
        buttons.forEach((btn) => {
            const slug = btn.getAttribute('data-category');
            const isActive = slug === state.selectedCategory;
            btn.classList.toggle('border-orange-400/60', isActive);
            btn.classList.toggle('dark:border-orange-600/40', isActive);
            btn.classList.toggle('bg-orange-50/70', isActive);
            btn.classList.toggle('dark:bg-orange-950/20', isActive);
        });
    }

    function isInstagramFollower(product) {
        return product.name.trim().toLowerCase().startsWith('(takipçi)');
    }

    function getSelectedProducts() {
        if (!state.selectedCategory) {
            return [];
        }
        const raw = catalog.products[state.selectedCategory] || [];
        let list = raw.slice();

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

    function renderProducts() {
        if (!productsSection) {
            return;
        }
        if (!state.selectedCategory) {
            productsSection.hidden = true;
            sortContainer.hidden = true;
            searchInput.value = '';
            searchInput.disabled = true;
            searchInput.placeholder = 'Önce bir kategori seçin';
            return;
        }

        const category = catalog.categories.find((c) => c.slug === state.selectedCategory);
        productsHeading.textContent = category ? category.title : 'Kategori';
        searchInput.disabled = false;
        searchInput.placeholder = 'Seçili kategoride ara…';
        searchInput.value = state.query;
        sortContainer.hidden = false;
        productsSection.hidden = false;

        if (state.selectedCategory === 'instagram') {
            instagramTabs.hidden = false;
            igTabButtons.forEach((btn) => {
                const tab = btn.getAttribute('data-ig-tab');
                const isActive = tab === state.igTab;
                btn.classList.toggle('bg-orange-500', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('bg-white', !isActive);
                btn.classList.toggle('dark:bg-neutral-800', !isActive);
            });
        } else {
            instagramTabs.hidden = true;
        }

        const products = getSelectedProducts();
        productCount.textContent = products.length + ' ürün';
        productGrid.innerHTML = '';

        if (products.length === 0) {
            emptyMessage.hidden = false;
            return;
        }

        emptyMessage.hidden = true;

        products.forEach((product, index) => {
            const badgeHtml = createBadgeGroup(product, index);
            const noteHtml = product.note ? '<p class="text-xs mt-1 opacity-80">' + escapeHtml(product.note) + '</p>' : '';
            const card = document.createElement('article');
            card.className = 'rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 p-5 shadow-sm flex flex-col';
            card.innerHTML = [
                '<div class="mb-2 flex justify-end gap-1">' + badgeHtml + '</div>',
                '<div class="flex-1">',
                '<h3 class="text-base font-semibold tracking-tight pr-24 leading-snug break-words whitespace-pre-line">' + escapeHtml(product.name) + '</h3>',
                noteHtml,
                '</div>',
                '<div class="mt-4 flex items-center justify-between">',
                '<span class="text-lg font-bold text-orange-600 dark:text-orange-400">' + escapeHtml(product.price) + '</span>',
                '<button type="button" class="inline-flex items-center justify-center rounded-xl px-3 py-2 text-sm font-medium bg-orange-500 text-white hover:bg-orange-600 active:bg-orange-700 transition-colors shadow">İncele</button>',
                '</div>'
            ].join('');
            productGrid.appendChild(card);
        });
    }

    function createBadgeGroup(product, index) {
        const badges = [];
        const note = (product.note || '').toLowerCase();
        if (index < 10) {
            badges.push('<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-700 border border-orange-200">Kampanya</span>');
            badges.push('<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 border border-amber-200">Popüler</span>');
        } else if (note.includes('popüler')) {
            badges.push('<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 border border-amber-200">Popüler</span>');
        } else {
            badges.push('<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">%100 Memnuniyet</span>');
        }
        return badges.join('');
    }

    function attachEvents() {
        if (themeBtn) {
            themeBtn.addEventListener('click', toggleTheme);
        }

        if (categoryList) {
            categoryList.addEventListener('click', (event) => {
                const target = event.target.closest('[data-category]');
                if (!target) {
                    return;
                }
                const slug = target.getAttribute('data-category');
                if (!slug) {
                    return;
                }
                state.selectedCategory = slug;
                state.query = '';
                state.sort = sortSelect.value || 'asc';
                state.igTab = 'hesaplar';
                updateCategoryButtons();
                renderProducts();
            });
        }

        if (clearCategoryBtn) {
            clearCategoryBtn.addEventListener('click', () => {
                state.selectedCategory = null;
                state.query = '';
                state.igTab = 'hesaplar';
                updateCategoryButtons();
                renderProducts();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                state.sort = sortSelect.value;
                renderProducts();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                state.query = searchInput.value;
                renderProducts();
            });
        }

        igTabButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-ig-tab');
                if (!tab) {
                    return;
                }
                state.igTab = tab;
                renderProducts();
            });
        });
    }

    loadTheme();
    attachEvents();
    updateCategoryButtons();
    renderProducts();
})();
