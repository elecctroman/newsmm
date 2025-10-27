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
    var activeCategory = 'all';

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

    function applyFilters() {
        var query = searchInput ? (searchInput.value || '').toLowerCase() : '';
        var visible = [];
        cards.forEach(function (card) {
            var matchesCategory = activeCategory === 'all' || card.dataset.category === activeCategory;
            var matchesQuery = !query || (card.dataset.name || '').indexOf(query) !== -1;
            if (matchesCategory && matchesQuery) {
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

    categoryButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeCategory = button.getAttribute('data-category') || 'all';
            categoryButtons.forEach(function (btn) {
                btn.classList.toggle('bg-white/80', btn === button);
                btn.classList.toggle('bg-white/60', btn !== button);
                btn.classList.toggle('dark:bg-slate-900/70', btn === button);
                btn.classList.toggle('dark:bg-slate-900/50', btn !== button);
                btn.classList.toggle('text-brand-600', btn === button);
                btn.classList.toggle('dark:text-brand-200', btn === button);
                btn.classList.toggle('text-slate-600', btn !== button);
                btn.classList.toggle('dark:text-slate-300', btn !== button);
                btn.classList.toggle('shadow', btn === button);
            });
            applyFilters();
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
