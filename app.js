(function () {
  const carousel = document.getElementById('hero-carousel');
  if (!carousel) return;
  const track = carousel.querySelector('.carousel-track');
  const slides = Array.from(track.children);
  const prev = carousel.querySelector('.carousel-nav.prev');
  const next = carousel.querySelector('.carousel-nav.next');
  const dotsWrap = carousel.querySelector('.carousel-dots');
  const AUTOPLAY = 4000;
  let index = 0;
  let timer = null;
  let autoEnabled = slides.length > 1;

  function update() {
    track.style.transform = `translateX(-${index * 100}%)`;
    dotsWrap.querySelectorAll('button').forEach((dot, i) => {
      const active = i === index;
      dot.setAttribute('aria-selected', active ? 'true' : 'false');
      dot.tabIndex = active ? 0 : -1;
    });
  }

  function goTo(i) {
    const total = slides.length;
    index = (i + total) % total;
    update();
  }

  function start() {
    if (!autoEnabled || timer) return;
    timer = setInterval(() => goTo(index + 1), AUTOPLAY);
  }

  function stop() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  slides.forEach((slide, i) => {
    const dot = document.createElement('button');
    dot.type = 'button';
    dot.setAttribute('aria-label', `${i + 1}. slayt`);
    dot.setAttribute('aria-controls', slide.id || `slide-${i}`);
    dot.setAttribute('role', 'tab');
    dot.tabIndex = i === 0 ? 0 : -1;
    dot.addEventListener('click', () => {
      stop();
      goTo(i);
      start();
    });
    dotsWrap.appendChild(dot);
  });

  if (!autoEnabled) {
    prev.style.display = 'none';
    next.style.display = 'none';
    dotsWrap.style.display = 'none';
  }

  prev.addEventListener('click', () => {
    stop();
    goTo(index - 1);
    start();
  });

  next.addEventListener('click', () => {
    stop();
    goTo(index + 1);
    start();
  });

  carousel.addEventListener('mouseenter', stop);
  carousel.addEventListener('mouseleave', start);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else start();
  });

  let pointerStart = null;
  track.addEventListener('pointerdown', (event) => {
    pointerStart = event.clientX;
    track.setPointerCapture(event.pointerId);
    stop();
  });

  track.addEventListener('pointerup', (event) => {
    if (pointerStart == null) return;
    const delta = event.clientX - pointerStart;
    pointerStart = null;
    track.releasePointerCapture(event.pointerId);
    if (Math.abs(delta) > 45) {
      if (delta < 0) goTo(index + 1);
      else goTo(index - 1);
    }
    start();
  });

  update();
  start();
})();

(function () {
  document.querySelectorAll('.btn-primary[data-product-id]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-product-id');
      console.log('add-to-cart', id);
    });
  });
})();
