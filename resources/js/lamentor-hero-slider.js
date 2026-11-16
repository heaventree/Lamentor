/* Lamentor hero slider: autoplay, arrows, dots, swipe. Reads data-settings of the widget wrapper. */
(function () {
  function init(wrapper) {
    var root = wrapper.querySelector('.lm-hero-slider');
    if (!root || root.getAttribute('data-lm-init')) return;
    root.setAttribute('data-lm-init', '1');
    var slides = [].slice.call(root.querySelectorAll('.lm-hero-slide'));
    var dots = [].slice.call(root.querySelectorAll('.lm-hero-dot'));
    if (slides.length < 2) return;
    var settings = {};
    try { settings = JSON.parse(wrapper.getAttribute('data-settings') || '{}'); } catch (e) {}
    var autoplay = settings.autoplay === 'yes';
    var delay = Math.max(1500, parseInt(settings.autoplay_speed, 10) || 5000);
    var index = 0, timer = null, paused = false;

    function show(next) {
      next = (next + slides.length) % slides.length;
      if (next === index) return;
      slides.forEach(function (s) { s.classList.remove('is-prev'); });
      slides[index].classList.remove('is-active');
      slides[index].classList.add('is-prev');
      slides[next].classList.remove('is-prev');
      // restart the text animations of the incoming slide
      void slides[next].offsetWidth;
      slides[next].classList.add('is-active');
      dots.forEach(function (d, i) { d.classList.toggle('is-active', i === next); });
      index = next;
    }
    function start() { stop(); if (autoplay) timer = setInterval(function () { if (!paused) show(index + 1); }, delay); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    var prev = root.querySelector('.lm-hero-prev'), next = root.querySelector('.lm-hero-next');
    if (prev) prev.addEventListener('click', function () { show(index - 1); start(); });
    if (next) next.addEventListener('click', function () { show(index + 1); start(); });
    dots.forEach(function (d, i) { d.addEventListener('click', function () { show(i); start(); }); });
    root.addEventListener('mouseenter', function () { paused = true; });
    root.addEventListener('mouseleave', function () { paused = false; });

    var x0 = null;
    root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 40) { show(index + (dx < 0 ? 1 : -1)); start(); }
    }, { passive: true });
    document.addEventListener('visibilitychange', function () { paused = document.hidden; });
    start();
  }
  function boot() { [].forEach.call(document.querySelectorAll('.elementor-widget-hero-slider'), init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
