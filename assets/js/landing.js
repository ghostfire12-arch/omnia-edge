/* =====================================================================
   OMNIA EDGE — LANDING PAGE INTERACTIONS
   Vanilla JS, no dependencies. Loaded only on index.php.
   ===================================================================== */
(function () {
  'use strict';

  var root = document.querySelector('.lp');
  if (!root) return;
  root.classList.add('lp-js');

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  /* -------------------------------------------------------------------
     HERO SLIDER — autoplay, arrows, dots, swipe, keyboard, pause on hover
     ------------------------------------------------------------------- */
  (function heroSlider() {
    var hero = $('[data-slider]');
    if (!hero) return;
    var slides = $$('.lp-slide', hero);
    var dotsWrap = $('.lp-hero__dots', hero);
    var interval = 6500;
    var current = 0;
    var timer = null;
    hero.style.setProperty('--lp-interval', interval + 'ms');

    if (slides.length < 2) {
      $('.lp-hero__ui', hero).style.display = 'none';
      return;
    }

    var dots = slides.map(function (_, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'lp-hero__dot' + (i === 0 ? ' is-active' : '');
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-label', 'Slide ' + (i + 1));
      b.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
      b.addEventListener('click', function () { go(i, true); });
      dotsWrap.appendChild(b);
      return b;
    });

    function restartProgress() {
      var bar = $('.lp-hero__progress i', hero);
      hero.classList.remove('is-playing');
      void bar.offsetWidth; // reflow so the CSS animation restarts
      if (!reduceMotion) hero.classList.add('is-playing');
    }

    function go(index, user) {
      var next = (index + slides.length) % slides.length;
      if (next === current) return;
      slides[current].classList.remove('is-active');
      slides[current].setAttribute('aria-hidden', 'true');
      current = next;
      slides[current].classList.add('is-active');
      slides[current].removeAttribute('aria-hidden');
      dots.forEach(function (d, i) {
        d.classList.toggle('is-active', i === current);
        d.setAttribute('aria-selected', i === current ? 'true' : 'false');
      });
      if (user) start();
      else restartProgress();
    }

    function start() {
      stop();
      if (reduceMotion) return;
      restartProgress();
      timer = setInterval(function () { go(current + 1); }, interval);
    }
    function stop() { clearInterval(timer); timer = null; }

    slides.forEach(function (s, i) { if (i) s.setAttribute('aria-hidden', 'true'); });
    $('[data-prev]', hero).addEventListener('click', function () { go(current - 1, true); });
    $('[data-next]', hero).addEventListener('click', function () { go(current + 1, true); });

    hero.addEventListener('mouseenter', function () { stop(); hero.classList.add('is-paused'); });
    hero.addEventListener('mouseleave', function () { hero.classList.remove('is-paused'); start(); });
    hero.addEventListener('focusin', function () { stop(); hero.classList.add('is-paused'); });
    hero.addEventListener('focusout', function () { hero.classList.remove('is-paused'); start(); });
    hero.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') go(current - 1, true);
      if (e.key === 'ArrowRight') go(current + 1, true);
    });

    // swipe
    var startX = null, startY = null;
    hero.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; }, { passive: true });
    hero.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      var dy = e.changedTouches[0].clientY - startY;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) go(current + (dx < 0 ? 1 : -1), true);
      startX = null;
    });

    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
    start();
  })();

  /* -------------------------------------------------------------------
     RAILS — arrow buttons + mouse drag-to-scroll
     ------------------------------------------------------------------- */
  $$('[data-rail]').forEach(function (rail) {
    var key = rail.getAttribute('data-rail');
    var prev = $('[data-rail-prev="' + key + '"]');
    var next = $('[data-rail-next="' + key + '"]');
    var step = function () { return Math.max(240, rail.clientWidth * 0.8); };

    function update() {
      var max = rail.scrollWidth - rail.clientWidth - 4;
      if (prev) prev.disabled = rail.scrollLeft <= 4;
      if (next) next.disabled = rail.scrollLeft >= max;
    }
    if (prev) prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
    if (next) next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
    rail.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();

    // drag with mouse (touch already scrolls natively)
    var down = false, moved = false, sx = 0, sl = 0;
    rail.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse' || e.button !== 0) return;
      down = true; moved = false; sx = e.clientX; sl = rail.scrollLeft;
    });
    window.addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - sx;
      if (!moved && Math.abs(dx) > 6) { moved = true; rail.classList.add('is-dragging'); }
      if (moved) rail.scrollLeft = sl - dx;
    });
    window.addEventListener('pointerup', function () {
      if (!down) return;
      down = false;
      // let the click that ends a drag be swallowed, then restore snapping
      setTimeout(function () { rail.classList.remove('is-dragging'); }, 0);
    });
    rail.addEventListener('dragstart', function (e) { e.preventDefault(); });
  });

  /* -------------------------------------------------------------------
     PRODUCT TABS + "show more"
     ------------------------------------------------------------------- */
  (function productTabs() {
    var grid = $('[data-grid]');
    if (!grid) return;
    var cards = $$('.lp-card', grid);
    var tabs = $$('.lp-tab');
    var ink = $('.lp-tabs__ink');
    var moreBtn = $('[data-more]');
    var countEl = $('[data-count]');
    var pageSize = parseInt(grid.getAttribute('data-initial'), 10) || 12;
    var filter = 'all';
    var limit = pageSize;

    function moveInk(tab) {
      if (!ink || !tab) return;
      ink.style.width = tab.offsetWidth + 'px';
      ink.style.transform = 'translateX(' + tab.offsetLeft + 'px)';
    }

    function render(animateFrom) {
      var matches = cards.filter(function (c) { return (' ' + c.dataset.filters + ' ').indexOf(' ' + filter + ' ') > -1; });
      var shown = 0;
      cards.forEach(function (c) { c.classList.add('is-hidden'); c.classList.remove('is-entering'); });
      matches.forEach(function (c, i) {
        if (i >= limit) return;
        c.classList.remove('is-hidden');
        if (!reduceMotion && i >= animateFrom) {
          c.style.setProperty('--i', Math.min(i - animateFrom, 11) * 55 + 'ms');
          void c.offsetWidth;
          c.classList.add('is-entering');
        }
        shown++;
      });
      if (moreBtn) moreBtn.hidden = shown >= matches.length;
      if (countEl) countEl.textContent = 'Showing ' + shown + ' of ' + matches.length + ' products';
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        if (tab.classList.contains('is-active')) return;
        tabs.forEach(function (t) { t.classList.remove('is-active'); t.setAttribute('aria-selected', 'false'); });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        moveInk(tab);
        filter = tab.dataset.filter;
        limit = pageSize;
        render(0);
      });
    });

    if (moreBtn) moreBtn.addEventListener('click', function () {
      var before = limit;
      limit += pageSize;
      render(before);
    });

    window.addEventListener('resize', function () { moveInk($('.lp-tab.is-active')); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { moveInk($('.lp-tab.is-active')); });
    moveInk($('.lp-tab.is-active'));
    render(Infinity);
  })();

  /* -------------------------------------------------------------------
     QUICK VIEW MODAL
     ------------------------------------------------------------------- */
  (function quickView() {
    var modal = $('[data-modal]');
    if (!modal) return;
    var lastFocus = null;
    var closeTimer = null;
    var qty = $('[data-qv-qty]', modal);

    function fill(d) {
      $('[data-qv-img]', modal).src = d.img;
      $('[data-qv-img]', modal).alt = d.name;
      $('[data-qv-name]', modal).textContent = d.name;
      $('[data-qv-cat]', modal).textContent = d.cat;
      $('[data-qv-now]', modal).textContent = d.now;
      $('[data-qv-was]', modal).textContent = d.was;
      $('[data-qv-desc]', modal).textContent = d.desc;
      var meta = [];
      if (d.brand) meta.push('Brand: ' + d.brand);
      meta.push(d.stock > 0 ? (d.stock <= 10 ? 'Only ' + d.stock + ' left' : 'In stock') : 'Sold out');
      $('[data-qv-meta]', modal).textContent = meta.join('   |   ');
      $('[data-qv-id]', modal).value = d.id;
      $('[data-qv-url]', modal).href = d.url;
      qty.value = 1;
      qty.max = Math.max(1, d.stock);
      var add = $('[data-qv-add]', modal);
      add.disabled = d.stock <= 0;
      add.textContent = d.stock > 0 ? 'Add to cart' : 'Sold out';
    }

    function open(d, trigger) {
      clearTimeout(closeTimer);
      lastFocus = trigger;
      fill(d);
      modal.hidden = false;
      document.body.classList.add('lp-locked');
      requestAnimationFrame(function () {
        modal.classList.add('is-open');
        $('.lp-modal__close', modal).focus();
      });
    }

    function close() {
      modal.classList.remove('is-open');
      document.body.classList.remove('lp-locked');
      closeTimer = setTimeout(function () { modal.hidden = true; }, reduceMotion ? 0 : 400);
      if (lastFocus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-quickview]');
      if (btn) {
        e.preventDefault();
        try { open(JSON.parse(btn.getAttribute('data-quickview')), btn); } catch (err) { /* malformed data: ignore */ }
        return;
      }
      if (e.target.closest('[data-close]') && !modal.hidden) close();
    });

    document.addEventListener('keydown', function (e) {
      if (modal.hidden) return;
      if (e.key === 'Escape') close();
      if (e.key === 'Tab') { // keep focus inside the dialog
        var f = $$('button:not([disabled]), a[href], input', modal).filter(function (el) { return el.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });

    $$('[data-step]', modal).forEach(function (b) {
      b.addEventListener('click', function () {
        var v = (parseInt(qty.value, 10) || 1) + parseInt(b.dataset.step, 10);
        qty.value = Math.min(parseInt(qty.max, 10) || 99, Math.max(1, v));
      });
    });
  })();

  /* -------------------------------------------------------------------
     DEAL COUNTDOWN (ends at midnight, Lagos time — set by PHP)
     ------------------------------------------------------------------- */
  (function countdown() {
    var el = $('[data-countdown]');
    if (!el) return;
    var end = parseInt(el.getAttribute('data-countdown'), 10);
    var parts = { h: $('[data-h]', el), m: $('[data-m]', el), s: $('[data-s]', el) };
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };

    function set(node, val) {
      if (node.textContent === val) return;
      node.textContent = val;
      if (!reduceMotion) { node.classList.remove('is-tick'); void node.offsetWidth; node.classList.add('is-tick'); }
    }
    function tick() {
      var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
      if (left === 0) end += 86400000; // roll over to the next day
      set(parts.h, pad(Math.floor(left / 3600)));
      set(parts.m, pad(Math.floor((left % 3600) / 60)));
      set(parts.s, pad(left % 60));
    }
    tick();
    setInterval(tick, 1000);
  })();

  /* -------------------------------------------------------------------
     SCROLL REVEAL
     ------------------------------------------------------------------- */
  (function reveal() {
    var items = $$('[data-reveal]');
    if (!('IntersectionObserver' in window) || reduceMotion) {
      items.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    items.forEach(function (el) { io.observe(el); });
  })();

  /* -------------------------------------------------------------------
     STICKY HEADER STATE + BACK-TO-TOP PROGRESS RING
     ------------------------------------------------------------------- */
  (function scrollUi() {
    var toTop = $('[data-totop]');
    var ring = $('[data-ring]');
    var circ = 2 * Math.PI * 21;
    if (ring) { ring.style.strokeDasharray = circ; ring.style.strokeDashoffset = circ; }
    var ticking = false;

    function update() {
      var y = window.scrollY;
      var max = document.documentElement.scrollHeight - window.innerHeight;
      document.body.classList.toggle('lp-scrolled', y > 40);
      if (toTop) toTop.classList.toggle('is-shown', y > 600);
      if (ring && max > 0) ring.style.strokeDashoffset = circ * (1 - Math.min(1, y / max));
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    if (toTop) toTop.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
    update();
  })();

  /* -------------------------------------------------------------------
     APP MOCKUP TILT (pointer devices only)
     ------------------------------------------------------------------- */
  (function tilt() {
    var wrap = $('[data-tilt]');
    if (!wrap || reduceMotion || !window.matchMedia('(hover: hover)').matches) return;
    var img = $('img', wrap);
    var band = wrap.closest('.lp-app');
    band.addEventListener('pointermove', function (e) {
      var r = band.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - 0.5;
      var y = (e.clientY - r.top) / r.height - 0.5;
      img.style.setProperty('--ry', (x * 10).toFixed(2) + 'deg');
      img.style.setProperty('--rx', (-y * 6).toFixed(2) + 'deg');
    });
    band.addEventListener('pointerleave', function () {
      img.style.setProperty('--ry', '0deg');
      img.style.setProperty('--rx', '0deg');
    });
  })();
})();
