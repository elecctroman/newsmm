(function () {
    'use strict';

    const root = document.documentElement;
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    const mobileToggle = document.getElementById('mobileNavToggle');
    const sidebar = document.getElementById('sidebar');

    const storageKey = 'lisansonay-theme';
    const brandLogos = document.querySelectorAll('[data-brand-logo]');
    let toastRoot = null;

    function ensureToastRoot() {
        if (toastRoot) {
            return toastRoot;
        }
        toastRoot = document.createElement('div');
        toastRoot.className = 'toast-root';
        document.body.appendChild(toastRoot);
        return toastRoot;
    }

    function showToast(message, type) {
        if (!message) {
            return;
        }
        const host = ensureToastRoot();
        const toast = document.createElement('div');
        toast.className = 'toast-item';
        if (type === 'success') {
            toast.classList.add('toast-success');
        } else if (type === 'error') {
            toast.classList.add('toast-error');
        }

        const text = document.createElement('span');
        text.className = 'toast-message';
        text.textContent = message;

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Kapat');
        close.innerHTML = '&times;';

        toast.appendChild(text);
        toast.appendChild(close);
        host.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        function removeToast() {
            toast.classList.remove('is-visible');
            setTimeout(function () {
                if (toast.parentNode === host) {
                    host.removeChild(toast);
                }
            }, 300);
        }

        close.addEventListener('click', removeToast);
        setTimeout(removeToast, 5000);
    }

    window.lsToast = showToast;

    function updateBrandLogos(theme) {
        const isDark = theme === 'dark';
        if (!brandLogos.length) {
            return;
        }
        brandLogos.forEach(function (logo) {
            const lightSrc = logo.getAttribute('data-light-logo') || '';
            const darkSrc = logo.getAttribute('data-dark-logo') || '';
            const target = isDark ? (darkSrc || lightSrc) : (lightSrc || darkSrc);
            if (target && logo.getAttribute('src') !== target) {
                logo.setAttribute('src', target);
            }
        });
    }

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        root.classList.toggle('dark', isDark);
        root.setAttribute('data-theme', theme);
        if (themeIcon) {
            themeIcon.innerHTML = isDark
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 15.5A9 9 0 1110.5 3a7 7 0 0010.5 12.5z"></path></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5" stroke="currentColor" stroke-width="1.5" fill="none"></path></svg>';
        }
        if (themeText) {
            themeText.textContent = isDark ? 'Gece' : 'Aydınlık';
        }
        updateBrandLogos(theme);
        try {
            localStorage.setItem(storageKey, theme);
        } catch (err) {
            // ignore storage limitations
        }
    }

    function loadTheme() {
        let theme = 'light';
        try {
            const stored = localStorage.getItem(storageKey);
            if (stored === 'dark' || stored === 'light') {
                theme = stored;
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
        } catch (err) {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
        }
        applyTheme(theme);
        return theme;
    }

    let currentTheme = loadTheme();

    const flashData = document.getElementById('flash-data');
    if (flashData) {
        try {
            const parsed = JSON.parse(flashData.textContent || '[]');
            if (Array.isArray(parsed)) {
                parsed.forEach(function (item) {
                    if (!item || typeof item.message !== 'string') {
                        return;
                    }
                    showToast(item.message, item.type || 'info');
                });
            }
        } catch (err) {
            // ignore malformed flash payloads
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(currentTheme);
        });
    }

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.contains('!flex');
            if (isOpen) {
                sidebar.classList.remove('!flex');
                sidebar.classList.add('!hidden');
            } else {
                sidebar.classList.remove('!hidden');
                sidebar.classList.add('!flex');
            }
        });

        document.addEventListener('click', function (event) {
            if (!sidebar.classList.contains('!flex')) {
                return;
            }
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (!sidebar.contains(target) && target !== mobileToggle) {
                sidebar.classList.remove('!flex');
                sidebar.classList.add('!hidden');
            }
        });
    }

    document.querySelectorAll('[data-generate-order]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-target');
            const value = button.getAttribute('data-generate-order');
            if (!targetId || !value) {
                return;
            }
            const targetInput = document.getElementById(targetId);
            if (targetInput && 'value' in targetInput) {
                targetInput.value = value;
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });

    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            const form = select.closest('form');
            if (form) {
                form.submit();
            }
        });
    });

    function parseAmountToCents(value) {
        if (!value) {
            return 0;
        }
        const cleaned = String(value).replace(/[^0-9,.-]/g, '').trim();
        if (!cleaned || cleaned === '-' || cleaned === ',') {
            return 0;
        }
        const lastComma = cleaned.lastIndexOf(',');
        const lastDot = cleaned.lastIndexOf('.');
        let normalized = cleaned;
        if (lastComma > -1 && lastDot > -1) {
            if (lastComma > lastDot) {
                normalized = normalized.replace(/\./g, '').replace(',', '.');
            } else {
                normalized = normalized.replace(/,/g, '');
            }
        } else if (lastComma > -1) {
            normalized = normalized.replace(/\./g, '').replace(',', '.');
        } else {
            normalized = normalized.replace(/,/g, '');
        }
        const amount = parseFloat(normalized);
        if (!isFinite(amount)) {
            return 0;
        }
        return Math.round(amount * 100);
    }

    function formatCurrency(cents) {
        const amount = cents / 100;
        try {
            return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
        } catch (err) {
            return '₺' + amount.toFixed(2);
        }
    }

    const checkoutForm = document.querySelector('[data-cart-checkout]');
    if (checkoutForm) {
        const total = parseInt(checkoutForm.getAttribute('data-cart-total') || '0', 10);
        const balance = parseInt(checkoutForm.getAttribute('data-wallet-balance') || '0', 10);
        const modeInputs = checkoutForm.querySelectorAll('input[name="payment_mode"]');
        const walletInput = checkoutForm.querySelector('input[name="wallet_usage"]');
        const walletSection = checkoutForm.querySelector('[data-payment-section="mixed"]');
        const cardSection = checkoutForm.querySelector('[data-payment-section="card"]');
        const summaryWallet = checkoutForm.querySelector('[data-payment-wallet]');
        const summaryCard = checkoutForm.querySelector('[data-payment-card]');
        const summaryBalance = checkoutForm.querySelector('[data-payment-balance]');
        const walletAvailability = checkoutForm.querySelector('[data-wallet-availability]');
        const defaultWallet = checkoutForm.getAttribute('data-wallet-default') || '';

        if (walletAvailability) {
            walletAvailability.innerHTML = 'Mevcut bakiye: <strong class="text-emerald-600 dark:text-emerald-300">' + formatCurrency(balance) + '</strong>';
        }

        if (walletInput && !walletInput.value && defaultWallet) {
            walletInput.value = defaultWallet;
        }

        function updateCheckoutSummary() {
            let mode = 'wallet';
            modeInputs.forEach(function (input) {
                if (input.checked) {
                    mode = input.value;
                }
            });

            if (walletSection) {
                walletSection.classList.toggle('hidden', mode !== 'mixed');
            }
            if (cardSection) {
                cardSection.classList.toggle('hidden', mode === 'wallet');
            }

            let walletCents = 0;
            if (mode === 'wallet') {
                walletCents = Math.min(balance, total);
                if (walletInput) {
                    walletInput.value = formatCurrency(walletCents);
                }
            } else if (mode === 'mixed') {
                walletCents = walletInput ? parseAmountToCents(walletInput.value) : 0;
                walletCents = Math.max(0, Math.min(walletCents, balance, total));
            } else {
                walletCents = 0;
                if (walletInput) {
                    walletInput.value = '0';
                }
            }

            let cardCents = Math.max(0, total - walletCents);
            if (mode === 'card') {
                cardCents = total;
                walletCents = 0;
            }

            if (summaryWallet) {
                summaryWallet.textContent = formatCurrency(walletCents);
            }
            if (summaryCard) {
                summaryCard.textContent = formatCurrency(cardCents);
            }
            if (summaryBalance) {
                summaryBalance.textContent = formatCurrency(Math.max(0, balance - walletCents));
            }
        }

        updateCheckoutSummary();

        modeInputs.forEach(function (input) {
            input.addEventListener('change', updateCheckoutSummary);
        });

        if (walletInput) {
            walletInput.addEventListener('input', updateCheckoutSummary);
            walletInput.addEventListener('blur', updateCheckoutSummary);
        }
    }

    function updateSortableInput(list) {
        if (!list) {
            return;
        }
        const ids = [];
        list.querySelectorAll('[data-sortable-item]').forEach(function (item) {
            const id = item.getAttribute('data-id');
            if (id) {
                ids.push(id);
            }
        });
        const targetSelector = list.getAttribute('data-sortable-target');
        let input = null;
        if (targetSelector) {
            try {
                input = document.querySelector(targetSelector);
            } catch (err) {
                input = null;
            }
        }
        if (!input) {
            input = list.querySelector('[data-sort-order-input]');
        }
        if (input) {
            input.value = ids.join(',');
        }
    }

    function setupSortableList(list) {
        let dragItem = null;

        list.addEventListener('dragstart', function (event) {
            const target = event.target.closest('[data-sortable-item]');
            if (!target) {
                return;
            }
            dragItem = target;
            target.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
            }
        });

        list.addEventListener('dragend', function () {
            if (dragItem) {
                dragItem.classList.remove('is-dragging');
                dragItem = null;
            }
            updateSortableInput(list);
        });

        list.addEventListener('dragover', function (event) {
            event.preventDefault();
            const target = event.target.closest('[data-sortable-item]');
            if (!dragItem || !target || dragItem === target) {
                return;
            }
            const rect = target.getBoundingClientRect();
            const offset = event.clientY - rect.top;
            if (offset > rect.height / 2) {
                target.after(dragItem);
            } else {
                target.before(dragItem);
            }
        });

        updateSortableInput(list);
    }

    document.querySelectorAll('[data-sortable-list]').forEach(function (list) {
        setupSortableList(list);
    });

    document.querySelectorAll('[data-sortable-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const sourceSelector = form.getAttribute('data-sortable-source');
            if (sourceSelector) {
                try {
                    const list = document.querySelector(sourceSelector);
                    updateSortableInput(list);
                } catch (err) {
                    // ignore invalid selector
                }
            }
        });
    });
}
})();
