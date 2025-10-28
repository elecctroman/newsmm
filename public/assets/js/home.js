(function () {
    'use strict';

    var carousel = document.querySelector('.hero-carousel');
    if (!carousel) {
        return;
    }

    var track = carousel.querySelector('.hero-track');
    var slides = Array.prototype.slice.call(track.querySelectorAll('.hero-slide'));
    if (!slides.length) {
        return;
    }

    var dotsRoot = carousel.querySelector('.hero-dots');
    var prevBtn = carousel.querySelector('.hero-nav.prev');
    var nextBtn = carousel.querySelector('.hero-nav.next');
    var autoplay = carousel.dataset.autoplay === 'true';
    var index = 0;
    var timer = null;

    function go(i) {
        index = (i + slides.length) % slides.length;
        track.style.transform = 'translateX(' + (-index * 100) + '%)';
        updateDots();
    }

    function next() {
        go(index + 1);
    }

    function prev() {
        go(index - 1);
    }

    function start() {
        if (!autoplay || slides.length <= 1) {
            return;
        }
        stop();
        timer = setInterval(next, 4000);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function updateDots() {
        if (!dotsRoot) {
            return;
        }
        Array.prototype.forEach.call(dotsRoot.children, function (button, i) {
            button.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });
    }

    if (dotsRoot) {
        slides.forEach(function (_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', (i + 1) + '. slayt');
            dot.addEventListener('click', function () { go(i); });
            dotsRoot.appendChild(dot);
        });
    }

    if (slides.length <= 1) {
        if (prevBtn) { prevBtn.style.display = 'none'; }
        if (nextBtn) { nextBtn.style.display = 'none'; }
        if (dotsRoot) { dotsRoot.style.display = 'none'; }
        autoplay = false;
    }

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

    go(0);
    start();
})();
