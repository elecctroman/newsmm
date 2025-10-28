(function () {
    'use strict';

    var carousel = document.querySelector('.lk-hero__carousel');
    if (carousel) {
        var track = carousel.querySelector('.lk-hero__track');
        var slideNodes = track ? Array.prototype.slice.call(track.querySelectorAll('.lk-hero__slide')) : [];
        if (!track || !slideNodes.length) {
            carousel.setAttribute('data-autoplay', 'false');
            return;
        }
        var dotsRoot = carousel.querySelector('.lk-hero__dots');
        var prevBtn = carousel.querySelector('.lk-hero__nav--prev');
        var nextBtn = carousel.querySelector('.lk-hero__nav--next');
        var autoplay = carousel.getAttribute('data-autoplay') === 'true';
        var index = 0;
        var timer = null;

        function go(targetIndex) {
            if (!slideNodes.length) {
                return;
            }
            index = (targetIndex + slideNodes.length) % slideNodes.length;
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            slideNodes.forEach(function (slide, idx) {
                slide.setAttribute('aria-hidden', idx === index ? 'false' : 'true');
            });
            updateDots();
        }

        function next() {
            go(index + 1);
        }

        function prev() {
            go(index - 1);
        }

        function start() {
            if (!autoplay || slideNodes.length <= 1) {
                return;
            }
            stop();
            timer = window.setInterval(next, 4000);
        }

        function stop() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function updateDots() {
            if (!dotsRoot) {
                return;
            }
            Array.prototype.forEach.call(dotsRoot.children, function (button, idx) {
                button.setAttribute('aria-selected', idx === index ? 'true' : 'false');
            });
        }

        if (dotsRoot) {
            dotsRoot.innerHTML = '';
            slideNodes.forEach(function (_, idx) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'lk-hero__dot';
                dot.setAttribute('role', 'tab');
                dot.setAttribute('aria-controls', 'lk-hero-slide-' + idx);
                dot.setAttribute('aria-label', (idx + 1) + '. slayt');
                dot.addEventListener('click', function () { go(idx); });
                dotsRoot.appendChild(dot);
            });
        }

        if (slideNodes.length <= 1) {
            autoplay = false;
            if (prevBtn) { prevBtn.setAttribute('hidden', 'hidden'); }
            if (nextBtn) { nextBtn.setAttribute('hidden', 'hidden'); }
            if (dotsRoot) { dotsRoot.setAttribute('hidden', 'hidden'); }
        } else {
            if (prevBtn) {
                prevBtn.addEventListener('click', prev);
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', next);
            }
            carousel.addEventListener('mouseenter', stop);
            carousel.addEventListener('mouseleave', start);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stop();
                } else {
                    start();
                }
            });

            var pointerStart = null;
            track.addEventListener('pointerdown', function (event) {
                pointerStart = event.clientX;
                track.setPointerCapture(event.pointerId);
                stop();
            });
            track.addEventListener('pointerup', function (event) {
                if (pointerStart === null) {
                    return;
                }
                var delta = event.clientX - pointerStart;
                pointerStart = null;
                if (Math.abs(delta) > 45) {
                    if (delta < 0) {
                        next();
                    } else {
                        prev();
                    }
                }
                start();
            });
        }

        go(0);
        start();
    }

    var categoriesToggle = document.querySelector('.lk-menu__categories');
    if (categoriesToggle) {
        document.addEventListener('click', function (event) {
            if (!categoriesToggle.contains(event.target)) {
                categoriesToggle.removeAttribute('open');
            }
        });
        categoriesToggle.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                categoriesToggle.removeAttribute('open');
                categoriesToggle.querySelector('summary').focus();
            }
        });
    }

    var tabRoots = document.querySelectorAll('[data-tabs]');
    tabRoots.forEach(function (root) {
        var buttons = root.querySelectorAll('.lk-tabs__button');
        var panels = root.querySelectorAll('.lk-tabs__panel');

        function activate(target) {
            var panelId = 'tab-' + target;
            buttons.forEach(function (button) {
                var isActive = button.getAttribute('data-tab-target') === target;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var isActive = panel.id === panelId;
                panel.classList.toggle('is-active', isActive);
            });
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-tab-target');
                if (target) {
                    activate(target);
                }
            });
        });

        if (buttons.length) {
            var initial = buttons[0].getAttribute('data-tab-target');
            if (initial) {
                activate(initial);
            }
        }
    });
})();
