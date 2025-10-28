(function () {
    'use strict';

    function initHeroCarousels() {
        var carousels = Array.prototype.slice.call(document.querySelectorAll('[data-hero-carousel]'));
        if (!carousels.length) {
            return;
        }

        carousels.forEach(function (carousel) {
            var slides = Array.prototype.slice.call(carousel.querySelectorAll('[data-hero-slide]'));
            if (!slides.length) {
                return;
            }

            var dots = Array.prototype.slice.call(carousel.querySelectorAll('[data-hero-dot]'));
            var prevButton = carousel.querySelector('[data-hero-prev]');
            var nextButton = carousel.querySelector('[data-hero-next]');
            var autoplayAttr = carousel.getAttribute('data-autoplay') || 'false';
            var autoplayMsAttr = carousel.getAttribute('data-autoplay-ms') || '4000';
            var allowAutoplay = autoplayAttr === 'true' && slides.length > 1;
            var autoplayMs = parseInt(autoplayMsAttr, 10);
            if (!autoplayMs || autoplayMs < 2000) {
                autoplayMs = 4000;
            }

            var activeIndex = 0;
            var timerId = null;
            var paused = !allowAutoplay;
            var touchStartX = null;
            var touchStartY = null;

            function setPaused(state) {
                paused = state;
                carousel.classList.toggle('is-paused', state);
                var ariaLive = (!allowAutoplay || state) ? 'polite' : 'off';
                carousel.setAttribute('aria-live', ariaLive);
            }

            function stopAutoplay() {
                if (timerId !== null) {
                    clearInterval(timerId);
                    timerId = null;
                }
            }

            function startAutoplay() {
                if (!allowAutoplay || paused) {
                    return;
                }
                stopAutoplay();
                timerId = window.setInterval(function () {
                    goToSlide(activeIndex + 1);
                }, autoplayMs);
            }

            function pauseAutoplay() {
                if (!allowAutoplay) {
                    return;
                }
                setPaused(true);
                stopAutoplay();
            }

            function resumeAutoplay() {
                if (!allowAutoplay) {
                    return;
                }
                setPaused(false);
                startAutoplay();
            }

            function restartAutoplay() {
                if (!allowAutoplay || paused) {
                    return;
                }
                stopAutoplay();
                startAutoplay();
            }

            function updateActiveStates(index) {
                slides.forEach(function (slide, slideIndex) {
                    var isActive = slideIndex === index;
                    slide.classList.toggle('is-active', isActive);
                    slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                    slide.setAttribute('tabindex', isActive ? '0' : '-1');
                });

                dots.forEach(function (dot, dotIndex) {
                    var dotActive = dotIndex === index;
                    dot.classList.toggle('is-active', dotActive);
                    dot.setAttribute('aria-selected', dotActive ? 'true' : 'false');
                    dot.setAttribute('tabindex', dotActive ? '0' : '-1');
                });
            }

            function goToSlide(index, options) {
                if (!slides.length) {
                    return;
                }
                var newIndex = index;
                if (newIndex < 0) {
                    newIndex = slides.length - 1;
                } else if (newIndex >= slides.length) {
                    newIndex = 0;
                }

                activeIndex = newIndex;
                updateActiveStates(newIndex);

                if (options && options.focusDot && dots[newIndex]) {
                    try {
                        dots[newIndex].focus({ preventScroll: true });
                    } catch (err) {
                        // ignore focus errors
                    }
                }
            }

            function handleKeydown(event) {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    goToSlide(activeIndex - 1, { focusDot: true });
                    restartAutoplay();
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    goToSlide(activeIndex + 1, { focusDot: true });
                    restartAutoplay();
                }
            }

            function hasFocusWithin() {
                return carousel.contains(document.activeElement);
            }

            if (prevButton) {
                prevButton.addEventListener('click', function () {
                    goToSlide(activeIndex - 1, { focusDot: true });
                    restartAutoplay();
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', function () {
                    goToSlide(activeIndex + 1, { focusDot: true });
                    restartAutoplay();
                });
            }

            dots.forEach(function (dot, dotIndex) {
                dot.addEventListener('click', function () {
                    goToSlide(dotIndex, { focusDot: true });
                    restartAutoplay();
                });
            });

            carousel.addEventListener('keydown', handleKeydown);
            carousel.addEventListener('mouseenter', pauseAutoplay);
            carousel.addEventListener('mouseleave', function () {
                if (!hasFocusWithin()) {
                    resumeAutoplay();
                }
            });
            carousel.addEventListener('focusin', pauseAutoplay);
            carousel.addEventListener('focusout', function () {
                window.setTimeout(function () {
                    if (!hasFocusWithin()) {
                        resumeAutoplay();
                    }
                }, 50);
            });

            carousel.addEventListener('touchstart', function (event) {
                if (!event.touches || event.touches.length !== 1) {
                    return;
                }
                touchStartX = event.touches[0].clientX;
                touchStartY = event.touches[0].clientY;
                pauseAutoplay();
            }, { passive: true });

            carousel.addEventListener('touchend', function (event) {
                if (!event.changedTouches || !event.changedTouches.length) {
                    resumeAutoplay();
                    return;
                }
                var touch = event.changedTouches[0];
                if (touchStartX !== null && touchStartY !== null) {
                    var deltaX = touch.clientX - touchStartX;
                    var deltaY = touch.clientY - touchStartY;
                    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 40) {
                        if (deltaX < 0) {
                            goToSlide(activeIndex + 1);
                        } else {
                            goToSlide(activeIndex - 1);
                        }
                    }
                }
                touchStartX = null;
                touchStartY = null;
                window.setTimeout(resumeAutoplay, 120);
            }, { passive: true });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    pauseAutoplay();
                } else if (!hasFocusWithin()) {
                    resumeAutoplay();
                }
            });

            setPaused(!allowAutoplay);
            goToSlide(activeIndex);
            if (allowAutoplay) {
                setPaused(false);
                startAutoplay();
            }
        });
    }

    initHeroCarousels();

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
