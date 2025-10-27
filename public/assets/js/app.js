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
})();
