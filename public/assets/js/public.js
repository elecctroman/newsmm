(function () {
    'use strict';

    var grid = document.getElementById('catalog-grid');
    if (!grid) {
        return;
    }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.catalog-card'));
    var searchInput = document.getElementById('catalog-search');
    var sortSelect = document.getElementById('catalog-sort');
    var summary = document.getElementById('catalog-summary');
    var categoryButtons = Array.prototype.slice.call(document.querySelectorAll('.catalog-category-btn'));
    var subcategoryButtons = Array.prototype.slice.call(document.querySelectorAll('.catalog-subcategory-btn'));
    var headerCategoryTriggers = Array.prototype.slice.call(document.querySelectorAll('.header-category-trigger'));
    var headerSubcategoryButtons = Array.prototype.slice.call(document.querySelectorAll('.header-subcategory-btn'));
    var blockLinks = Array.prototype.slice.call(document.querySelectorAll('.block-view-link'));
    var subcategoryFilter = document.getElementById('subcategory-filter');
    var blogCategoryButtons = Array.prototype.slice.call(document.querySelectorAll('.blog-category-btn'));
    var activeCategory = 'all';
    var activeSubcategory = null;

    function updateSummary(visibleCount) {
        if (!summary) {
            return;
        }
        var total = cards.length;
        if (visibleCount === total) {
            summary.textContent = total + ' ürün listeleniyor.';
        } else {
            summary.textContent = visibleCount + ' / ' + total + ' ürün listeleniyor.';
        }
    }

    function sortCards(filtered) {
        if (!sortSelect) {
            return filtered;
        }
        var mode = sortSelect.value;
        return filtered.sort(function (a, b) {
            if (mode === 'name') {
                var nameA = a.dataset.name || '';
                var nameB = b.dataset.name || '';
                return nameA.localeCompare(nameB, 'tr', { sensitivity: 'base' });
            }
            var priceA = parseInt(a.dataset.price || '0', 10);
            var priceB = parseInt(b.dataset.price || '0', 10);
            if (mode === 'desc') {
                return priceB - priceA;
            }
            return priceA - priceB;
        });
    }

    function updateCategoryButtonStyles() {
        categoryButtons.forEach(function (btn) {
            var slug = btn.getAttribute('data-category') || 'all';
            var isActive = (activeCategory === 'all' && slug === 'all') || (slug !== 'all' && slug === activeCategory);
            btn.classList.toggle('bg-white/80', isActive);
            btn.classList.toggle('bg-white/60', !isActive);
            btn.classList.toggle('dark:bg-slate-900/70', isActive);
            btn.classList.toggle('dark:bg-slate-900/50', !isActive);
            btn.classList.toggle('text-brand-600', isActive);
            btn.classList.toggle('dark:text-brand-200', isActive);
            btn.classList.toggle('text-slate-600', !isActive);
            btn.classList.toggle('dark:text-slate-300', !isActive);
            btn.classList.toggle('shadow', isActive);
        });
    }

    function updateSubcategoryButtonStyles() {
        var hasVisible = false;
        subcategoryButtons.forEach(function (btn) {
            var parent = btn.getAttribute('data-parent') || '';
            var slug = btn.getAttribute('data-subcategory') || '';
            var shouldShow = activeCategory !== 'all' && parent === activeCategory;
            var isActive = shouldShow && activeSubcategory === slug;
            btn.classList.toggle('hidden', !shouldShow);
            btn.classList.toggle('bg-white/85', isActive);
            btn.classList.toggle('border-brand-200', isActive);
            btn.classList.toggle('text-brand-600', isActive);
            btn.classList.toggle('dark:text-brand-200', isActive);
            btn.classList.toggle('shadow', isActive);
            btn.classList.toggle('text-slate-600', !isActive);
            btn.classList.toggle('dark:text-slate-300', !isActive);
            if (shouldShow) {
                hasVisible = true;
            }
        });
        if (subcategoryFilter) {
            subcategoryFilter.style.display = hasVisible ? '' : 'none';
        }
    }

    function applyFilters() {
        var query = searchInput ? (searchInput.value || '').toLowerCase() : '';
        var visible = [];
        cards.forEach(function (card) {
            var matchesCategory = activeCategory === 'all' || card.dataset.category === activeCategory;
            var matchesSubcategory = !activeSubcategory || (card.dataset.subcategory || '') === activeSubcategory;
            var matchesQuery = !query || (card.dataset.name || '').indexOf(query) !== -1;
            if (matchesCategory && matchesSubcategory && matchesQuery) {
                card.style.display = '';
                visible.push(card);
            } else {
                card.style.display = 'none';
            }
        });

        var sorted = sortCards(visible.slice());
        sorted.forEach(function (card) {
            grid.appendChild(card);
        });

        updateSummary(sorted.length);
    }

    function scrollToCatalog() {
        var anchor = document.getElementById('catalog');
        if (!anchor) {
            return;
        }
        try {
            anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            window.location.hash = '#catalog';
        }
    }

    function setCategory(slug, options) {
        var newCategory = slug || 'all';
        var shouldScroll = options && options.scroll === true;
        activeCategory = newCategory;
        activeSubcategory = null;
        updateCategoryButtonStyles();
        updateSubcategoryButtonStyles();
        applyFilters();
        if (shouldScroll) {
            scrollToCatalog();
        }
    }

    function setSubcategory(subSlug, parentSlug, options) {
        var shouldScroll = options && options.scroll === true;
        activeCategory = parentSlug || 'all';
        activeSubcategory = subSlug || null;
        updateCategoryButtonStyles();
        updateSubcategoryButtonStyles();
        applyFilters();
        if (shouldScroll) {
            scrollToCatalog();
        }
    }

    categoryButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setCategory(button.getAttribute('data-category'));
        });
    });

    subcategoryButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setSubcategory(button.getAttribute('data-subcategory'), button.getAttribute('data-parent'));
        });
    });

    headerCategoryTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            var slug = trigger.getAttribute('data-category');
            setCategory(slug, { scroll: true });
        });
    });

    headerSubcategoryButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var details = button.closest('details');
            setSubcategory(button.getAttribute('data-subcategory'), button.getAttribute('data-category'), { scroll: true });
            if (details) {
                details.open = false;
            }
        });
    });

    blockLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var category = link.getAttribute('data-category') || '';
            var subcategory = link.getAttribute('data-subcategory') || '';
            if (!category && !subcategory) {
                return;
            }
            event.preventDefault();
            if (subcategory) {
                setSubcategory(subcategory, category || 'all', { scroll: true });
            } else {
                setCategory(category || 'all', { scroll: true });
            }
        });
    });

    blogCategoryButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            var category = button.getAttribute('data-category');
            if (category) {
                event.preventDefault();
                setCategory(category === 'all' ? 'all' : category, { scroll: true });
            }
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyFilters();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            applyFilters();
        });
    }

    updateCategoryButtonStyles();
    updateSubcategoryButtonStyles();
    applyFilters();

    var productSelect = document.getElementById('product_id');
    var quantityInput = document.getElementById('quantity');
    var totalLabel = document.querySelector('#order-total-label span');

    function formatCurrency(amount) {
        try {
            return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
        } catch (err) {
            return '₺' + amount.toFixed(2);
        }
    }

    function updateOrderTotal() {
        if (!productSelect || !totalLabel) {
            return;
        }
        var option = productSelect.options[productSelect.selectedIndex];
        if (!option) {
            totalLabel.textContent = '—';
            return;
        }
        var priceCents = parseInt(option.getAttribute('data-price') || '0', 10);
        var priceLabel = option.getAttribute('data-price-label') || '';
        var quantity = quantityInput ? parseInt(quantityInput.value || '1', 10) : 1;
        if (!quantity || quantity < 1) {
            quantity = 1;
        }
        var totalAmount = (priceCents * quantity) / 100;
        var formatted = totalAmount > 0 ? formatCurrency(totalAmount) : priceLabel;
        totalLabel.textContent = formatted + ' (x' + quantity + ')';
    }

    if (productSelect) {
        productSelect.addEventListener('change', updateOrderTotal);
    }
    if (quantityInput) {
        quantityInput.addEventListener('input', updateOrderTotal);
        quantityInput.addEventListener('change', updateOrderTotal);
    }

    updateOrderTotal();
})();
