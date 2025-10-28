(function () {
    'use strict';

    function initHeroCarousels() {
        var carousels = Array.prototype.slice.call(document.querySelectorAll('.hero-carousel'));
        if (!carousels.length) {
            return;
        }

        carousels.forEach(function (carousel) {
            var track = carousel.querySelector('.hero-track');
            if (!track) {
                return;
            }

            var slides = Array.prototype.slice.call(track.querySelectorAll('.hero-slide'));
            if (!slides.length) {
                carousel.setAttribute('aria-live', 'polite');
                return;
            }

            var prevButton = carousel.querySelector('.hero-nav.prev');
            var nextButton = carousel.querySelector('.hero-nav.next');
            var dotsContainer = carousel.querySelector('.hero-dots');

            var autoplayAttr = carousel.getAttribute('data-autoplay') || 'false';
            var autoplayMsValue = parseInt(carousel.getAttribute('data-autoplay-ms') || '4000', 10);
            if (!autoplayMsValue || autoplayMsValue < 2000) {
                autoplayMsValue = 4000;
            }

            var requestedDots = (carousel.getAttribute('data-show-dots') || 'false') === 'true';
            var requestedArrows = (carousel.getAttribute('data-show-arrows') || 'false') === 'true';
            var slideCount = slides.length;
            var allowAutoplay = autoplayAttr === 'true' && slideCount > 1;
            var enableDots = requestedDots && slideCount > 1;
            var enableArrows = requestedArrows && slideCount > 1;

            var dots = [];
            if (dotsContainer) {
                dotsContainer.innerHTML = '';
                if (enableDots) {
                    slides.forEach(function (slide, index) {
                        var dot = document.createElement('button');
                        dot.type = 'button';
                        dot.setAttribute('role', 'tab');
                        var slideId = slide.getAttribute('id');
                        if (!slideId) {
                            slideId = 'hero-slide-auto-' + (index + 1);
                            slide.id = slideId;
                        }
                        dot.setAttribute('aria-controls', slideId);
                        dot.setAttribute('aria-label', (index + 1) + '. slayta git');
                        dot.setAttribute('aria-selected', 'false');
                        dot.setAttribute('tabindex', '-1');
                        dot.addEventListener('click', function () {
                            goTo(index);
                            restartAutoplay();
                            try {
                                dot.focus({ preventScroll: true });
                            } catch (err) {
                                // ignore focus issues
                            }
                        });
                        dotsContainer.appendChild(dot);
                        dots.push(dot);
                    });
                    dotsContainer.style.display = '';
                    dotsContainer.setAttribute('aria-hidden', 'false');
                } else {
                    dotsContainer.style.display = 'none';
                    dotsContainer.setAttribute('aria-hidden', 'true');
                }
            }

            var currentIndex = 0;
            var autoplayTimer = null;
            var autoplayPaused = !allowAutoplay;
            var pointerId = null;
            var pointerActive = false;
            var pointerStartX = 0;

            if (prevButton) {
                prevButton.style.display = enableArrows ? '' : 'none';
                prevButton.disabled = !enableArrows;
                prevButton.setAttribute('aria-hidden', enableArrows ? 'false' : 'true');
                if (enableArrows) {
                    prevButton.addEventListener('click', function () {
                        goTo(currentIndex - 1);
                        restartAutoplay();
                    });
                }
            }

            if (nextButton) {
                nextButton.style.display = enableArrows ? '' : 'none';
                nextButton.disabled = !enableArrows;
                nextButton.setAttribute('aria-hidden', enableArrows ? 'false' : 'true');
                if (enableArrows) {
                    nextButton.addEventListener('click', function () {
                        goTo(currentIndex + 1);
                        restartAutoplay();
                    });
                }
            }

            function updateAria(index) {
                slides.forEach(function (slide, i) {
                    var isActive = i === index;
                    slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                    slide.setAttribute('tabindex', isActive ? '0' : '-1');
                });
            }

            function updateDots() {
                if (!dots.length) {
                    return;
                }
                dots.forEach(function (dot, i) {
                    var isActive = i === currentIndex;
                    dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    dot.setAttribute('tabindex', isActive ? '0' : '-1');
                });
            }

            function goTo(index) {
                if (!slideCount) {
                    return;
                }
                var newIndex = index;
                if (newIndex < 0) {
                    newIndex = slideCount - 1;
                } else if (newIndex >= slideCount) {
                    newIndex = 0;
                }
                currentIndex = newIndex;
                track.style.transform = 'translateX(-' + (newIndex * 100) + '%)';
                updateAria(newIndex);
                updateDots();
            }

            function stopAutoplay() {
                if (autoplayTimer !== null) {
                    window.clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
            }

            function startAutoplay() {
                if (!allowAutoplay || autoplayPaused || autoplayTimer !== null) {
                    return;
                }
                autoplayTimer = window.setInterval(function () {
                    goTo(currentIndex + 1);
                }, autoplayMsValue);
            }

            function pauseAutoplay() {
                if (!allowAutoplay || autoplayPaused) {
                    return;
                }
                autoplayPaused = true;
                stopAutoplay();
                carousel.setAttribute('aria-live', 'polite');
            }

            function resumeAutoplay() {
                if (!allowAutoplay || !autoplayPaused) {
                    return;
                }
                autoplayPaused = false;
                carousel.setAttribute('aria-live', 'off');
                startAutoplay();
            }

            function restartAutoplay() {
                if (!allowAutoplay || autoplayPaused) {
                    return;
                }
                stopAutoplay();
                startAutoplay();
            }

            function hasFocusWithin() {
                return carousel.contains(document.activeElement);
            }

            function endPointerGesture(deltaX) {
                if (!pointerActive) {
                    return;
                }
                pointerActive = false;
                if (pointerId !== null) {
                    try {
                        track.releasePointerCapture(pointerId);
                    } catch (err) {
                        // ignore release errors
                    }
                }
                pointerId = null;
                pointerStartX = 0;
                if (typeof deltaX === 'number' && Math.abs(deltaX) > 45) {
                    if (deltaX < 0) {
                        goTo(currentIndex + 1);
                    } else {
                        goTo(currentIndex - 1);
                    }
                }
                restartAutoplay();
            }

            track.addEventListener('pointerdown', function (event) {
                if (event.pointerType === 'mouse' && event.button !== 0) {
                    return;
                }
                pointerActive = true;
                pointerId = event.pointerId;
                pointerStartX = event.clientX;
                try {
                    track.setPointerCapture(pointerId);
                } catch (err) {
                    // ignore capture errors
                }
                stopAutoplay();
            });

            track.addEventListener('pointerup', function (event) {
                if (!pointerActive || event.pointerId !== pointerId) {
                    return;
                }
                var deltaX = event.clientX - pointerStartX;
                endPointerGesture(deltaX);
            });

            track.addEventListener('pointercancel', function (event) {
                if (pointerActive && event.pointerId === pointerId) {
                    endPointerGesture();
                }
            });

            track.addEventListener('pointerleave', function (event) {
                if (pointerActive && event.pointerId === pointerId) {
                    endPointerGesture();
                }
            });

            carousel.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    goTo(currentIndex - 1);
                    restartAutoplay();
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    goTo(currentIndex + 1);
                    restartAutoplay();
                }
            });

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
                }, 120);
            });

            document.addEventListener('visibilitychange', function () {
                if (!allowAutoplay) {
                    return;
                }
                if (document.hidden) {
                    stopAutoplay();
                } else if (!autoplayPaused) {
                    startAutoplay();
                }
            });

            carousel.setAttribute('aria-live', allowAutoplay ? 'off' : 'polite');
            goTo(0);
            if (allowAutoplay) {
                autoplayPaused = false;
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
