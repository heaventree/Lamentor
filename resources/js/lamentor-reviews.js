/* Lamentor reviews: "Read more" toggles and the slider (arrows, dots, autoplay). Reads data-settings of the widget wrapper. */
(function () {
  function readMore(root) {
    root.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('.lm-rv-more') : null;
      if (!btn || !root.contains(btn)) return;
      var text = btn.parentNode, open = btn.getAttribute('aria-expanded') !== 'true';
      text.querySelector('.lm-rv-short').hidden = open;
      text.querySelector('.lm-rv-full').hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!btn.getAttribute('data-more')) btn.setAttribute('data-more', btn.textContent);
      btn.textContent = open ? 'Show less' : btn.getAttribute('data-more');
    });
  }

  function slider(wrapper, root) {
    var track = root.querySelector('.lm-rv-track');
    if (!track) return;
    var cards = [].slice.call(track.querySelectorAll('.lm-rv-card'));
    var prev = root.querySelector('.lm-rv-prev'), next = root.querySelector('.lm-rv-next');
    var dotsBox = root.querySelector('.lm-rv-dots');
    var settings = {};
    try { settings = JSON.parse(wrapper.getAttribute('data-settings') || '{}'); } catch (e) {}
    var delay = Math.max(2000, parseInt(settings.autoplay_speed, 10) || 5000);
    var timer = null, paused = false;

    function perView() {
      if (!cards.length) return 1;
      var w = cards[0].getBoundingClientRect().width;
      return Math.max(1, Math.round(track.clientWidth / (w || track.clientWidth)));
    }
    function pages() { return Math.max(1, Math.ceil(cards.length / perView())); }
    function page() {
      var max = track.scrollWidth - track.clientWidth;
      if (max <= 1) return 0;
      return Math.round(track.scrollLeft / max * (pages() - 1));
    }
    function go(p) {
      var n = pages();
      p = (p + n) % n;
      var card = cards[Math.min(cards.length - 1, p * perView())];
      track.scrollTo({ left: card ? card.offsetLeft - cards[0].offsetLeft : 0 });
    }
    function buildDots() {
      if (!dotsBox) return;
      var n = pages();
      dotsBox.innerHTML = '';
      dotsBox.hidden = n < 2;
      for (var i = 0; i < n; i++) {
        var d = document.createElement('button');
        d.type = 'button';
        d.className = 'lm-rv-dot';
        d.setAttribute('aria-label', 'Go to reviews page ' + (i + 1));
        (function (i) { d.addEventListener('click', function () { go(i); restart(); }); })(i);
        dotsBox.appendChild(d);
      }
      sync();
    }
    function sync() {
      var p = page(), n = pages();
      if (dotsBox) [].forEach.call(dotsBox.children, function (d, i) { d.classList.toggle('is-active', i === p); });
      var scrollable = track.scrollWidth - track.clientWidth > 1;
      if (prev) prev.hidden = !scrollable;
      if (next) next.hidden = !scrollable;
    }
    function restart() {
      if (timer) clearInterval(timer);
      timer = settings.autoplay === 'yes' ? setInterval(function () { if (!paused) go(page() + 1); }, delay) : null;
    }

    if (prev) prev.addEventListener('click', function () { go(page() - 1); restart(); });
    if (next) next.addEventListener('click', function () { go(page() + 1); restart(); });
    var raf = 0;
    track.addEventListener('scroll', function () { cancelAnimationFrame(raf); raf = requestAnimationFrame(sync); }, { passive: true });
    root.addEventListener('mouseenter', function () { paused = true; });
    root.addEventListener('mouseleave', function () { paused = false; });
    root.addEventListener('focusin', function () { paused = true; });
    root.addEventListener('focusout', function () { paused = false; });
    document.addEventListener('visibilitychange', function () { paused = document.hidden; });
    var resizeTimer = 0;
    window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(buildDots, 150); });
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) settings.autoplay = '';
    buildDots();
    restart();
  }

  function init(wrapper) {
    var root = wrapper.querySelector('.lm-reviews');
    if (!root || root.getAttribute('data-lm-init')) return;
    root.setAttribute('data-lm-init', '1');
    readMore(root);
    if (root.classList.contains('lm-rv-layout-slider')) slider(wrapper, root);
  }
  function boot() { [].forEach.call(document.querySelectorAll('.elementor-widget-lamentor-reviews'), init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
