/* =====================================================================
   OMNIA EDGE — ADMIN CONSOLE
   Vanilla JS, no dependencies (Chart.js is loaded only on chart pages).
   ===================================================================== */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  var OE = window.OE = window.OE || {};

  /* ---------------- escape helper for anything built from data ---------------- */
  OE.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  /* ---------------- toasts ---------------- */
  OE.toast = function (msg, type) {
    var wrap = $('[data-toasts]'); if (!wrap) return;
    var t = document.createElement('div');
    t.className = 'toast toast--' + (type || 'success');
    t.setAttribute('role', 'status');
    t.innerHTML = (type === 'error'
      ? '<svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>'
      : '<svg class="ico" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>') + '<span></span>';
    t.querySelector('span').textContent = msg;
    wrap.appendChild(t);
    setTimeout(function () { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 400); }, 4200);
  };
  $$('[data-flash]').forEach(function (f) { OE.toast(f.textContent, f.getAttribute('data-type')); });

  /* ---------------- theme ---------------- */
  function currentTheme() {
    return root.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }
  if (!root.getAttribute('data-theme')) root.setAttribute('data-theme', currentTheme());
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      store.set('oe-admin-theme', next);
      document.dispatchEvent(new CustomEvent('oe:theme'));
    });
  });

  /* ---------------- sidebar ---------------- */
  $$('[data-collapse]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = root.classList.toggle('side-collapsed');
      store.set('oe-admin-collapsed', on ? '1' : '0');
      setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 460);
    });
  });
  $$('[data-side-open]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.add('side-open'); }); });
  $$('[data-side-close]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.remove('side-open'); }); });

  /* ---------------- dropdowns ---------------- */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-dropdown]');
    $$('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); var t = $('[data-dropdown]', d); if (t) t.setAttribute('aria-expanded', 'false'); }
    });
    if (trigger) {
      var dd = trigger.parentNode, open = dd.classList.toggle('is-open');
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  });

  /* ---------------- ripple on buttons ---------------- */
  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest('.btn--primary, .btn--danger, .btn--success');
    if (!b || reduce) return;
    var r = b.getBoundingClientRect(), s = Math.max(r.width, r.height), sp = document.createElement('span');
    sp.className = 'ripple';
    sp.style.cssText = 'width:' + s + 'px;height:' + s + 'px;left:' + (e.clientX - r.left - s / 2) + 'px;top:' + (e.clientY - r.top - s / 2) + 'px';
    b.appendChild(sp); setTimeout(function () { sp.remove(); }, 650);
  });

  /* ---------------- confirm dialog for destructive forms/links ---------------- */
  var cModal = $('[data-confirm-modal]'), pending = null, lastFocus = null;
  function closeConfirm() { if (cModal) cModal.hidden = true; pending = null; if (lastFocus) lastFocus.focus(); }
  OE.confirm = function (text, onOk, okLabel) {
    if (!cModal) { if (window.confirm(text)) onOk(); return; }
    lastFocus = document.activeElement;
    $('[data-confirm-text]', cModal).textContent = text;
    $('[data-confirm-ok]', cModal).textContent = okLabel || 'Yes, continue';
    pending = onOk; cModal.hidden = false; $('[data-confirm-ok]', cModal).focus();
  };
  if (cModal) {
    $('[data-confirm-ok]', cModal).addEventListener('click', function () { var f = pending; closeConfirm(); if (f) f(); });
    $$('[data-confirm-cancel]', cModal).forEach(function (b) { b.addEventListener('click', closeConfirm); });
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.hasAttribute('data-confirm') || f.dataset.confirmed === '1') return;
    e.preventDefault();
    OE.confirm(f.getAttribute('data-confirm'), function () { f.dataset.confirmed = '1'; if (f.requestSubmit && e.submitter) f.requestSubmit(e.submitter); else f.submit(); }, f.getAttribute('data-confirm-ok'));
  }, true);

  /* ---------------- count-up numbers ---------------- */
  function countUp(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var prefix = el.getAttribute('data-prefix') || '', suffix = el.getAttribute('data-suffix') || '';
    var dec = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var fmt = function (n) { return prefix + n.toLocaleString('en-NG', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix; };
    if (reduce) { el.textContent = fmt(target); return; }
    var start = null, dur = 1400;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur), eased = 1 - Math.pow(1 - p, 4);
      el.textContent = fmt(target * eased);
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var cio = 'IntersectionObserver' in window ? new IntersectionObserver(function (en) {
    en.forEach(function (x) { if (x.isIntersecting) { countUp(x.target); cio.unobserve(x.target); } });
  }) : null;
  $$('[data-count]').forEach(function (el) { cio ? cio.observe(el) : countUp(el); });

  /* ---------------- client-side table sort ---------------- */
  $$('table[data-sortable]').forEach(function (table) {
    $$('th[data-sort]', table).forEach(function (th, idx) {
      th.setAttribute('tabindex', '0');
      var colIndex = Array.prototype.indexOf.call(th.parentNode.children, th);
      var go = function () {
        var dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
        $$('th[data-sort]', table).forEach(function (o) { o.removeAttribute('data-dir'); });
        th.setAttribute('data-dir', dir);
        var tbody = table.tBodies[0], rows = Array.prototype.slice.call(tbody.rows);
        var type = th.getAttribute('data-sort');
        rows.sort(function (a, b) {
          var av = a.cells[colIndex], bv = b.cells[colIndex];
          av = av ? (av.getAttribute('data-v') || av.textContent.trim()) : '';
          bv = bv ? (bv.getAttribute('data-v') || bv.textContent.trim()) : '';
          if (type === 'num') { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; return dir === 'asc' ? av - bv : bv - av; }
          return dir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      };
      th.addEventListener('click', go);
      th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
  });

  /* ---------------- bulk selection ---------------- */
  $$('[data-bulk]').forEach(function (scope) {
    var all = $('[data-check-all]', scope), bar = $('[data-bulkbar]', scope), countEl = $('[data-bulk-count]', scope);
    var boxes = function () { return $$('[data-check]', scope); };
    function sync() {
      var sel = boxes().filter(function (b) { return b.checked; });
      boxes().forEach(function (b) { var tr = b.closest('tr, .cat-card'); if (tr) tr.classList.toggle('is-selected', b.checked); });
      if (bar) bar.classList.toggle('is-on', sel.length > 0);
      if (countEl) countEl.textContent = sel.length;
      if (all) { all.checked = sel.length && sel.length === boxes().length; all.indeterminate = sel.length > 0 && sel.length < boxes().length; }
    }
    if (all) all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; }); sync(); });
    scope.addEventListener('change', function (e) { if (e.target.matches('[data-check]')) sync(); });
    sync();
  });

  /* ---------------- auto-submit controls (switches, selects) ---------------- */
  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-autosubmit]')) e.target.form && e.target.form.requestSubmit();
  });

  /* ---------------- drop zones with previews ---------------- */
  $$('[data-dropzone]').forEach(function (zone) {
    var input = $('input[type=file]', zone), out = document.getElementById(zone.getAttribute('data-dropzone'));
    ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
    zone.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); } });
    input.addEventListener('change', function () {
      if (!out) return;
      out.innerHTML = '';
      Array.prototype.forEach.call(input.files, function (file, i) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return;
        var url = URL.createObjectURL(file), d = document.createElement('div');
        d.className = 'thumb'; d.innerHTML = '<img alt=""><b>New</b>';
        d.querySelector('img').src = url; out.appendChild(d);
        if (i === 0) document.dispatchEvent(new CustomEvent('oe:first-image', { detail: url }));
      });
    });
  });
  // Visual feedback when ticking "remove" on an existing image.
  document.addEventListener('change', function (e) {
    if (e.target.matches('.thumb input[type=checkbox]')) e.target.closest('.thumb').classList.toggle('is-removing', e.target.checked);
  });

  /* ---------------- command palette ---------------- */
  var pal = $('[data-palette]');
  if (pal) {
    var input = $('[data-palette-input]', pal), results = $('[data-palette-results]', pal), active = 0, timer = null, ctrl = null;
    var api = document.body.getAttribute('data-api');
    var icons = { page: '<svg class="ico" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>' };
    function items() { return $$('.palette__item', results); }
    function highlight(i) {
      var list = items(); if (!list.length) return;
      active = (i + list.length) % list.length;
      list.forEach(function (el, k) { el.classList.toggle('is-active', k === active); el.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
      list[active].scrollIntoView({ block: 'nearest' });
    }
    function render(groups) {
      var html = '';
      groups.forEach(function (g) {
        if (!g.items.length) return;
        html += '<div class="palette__group">' + OE.esc(g.label) + '</div>';
        g.items.forEach(function (it) {
          var lead = it.img ? '<img src="' + OE.esc(it.img) + '" alt="">' : it.initials ? '<span class="avatar">' + OE.esc(it.initials) + '</span>' : '<span class="palette__ico">' + icons.page + '</span>';
          html += '<a class="palette__item" role="option" href="' + OE.esc(it.url) + '">' + lead + '<span><strong>' + OE.esc(it.title) + '</strong>' + (it.sub ? '<small>' + OE.esc(it.sub) + '</small>' : '') + '</span></a>';
        });
      });
      results.innerHTML = html || '<div class="empty"><p>No results. Try a product name, an order number or an email.</p></div>';
      highlight(0);
    }
    function pages(q) {
      return (window.OE_PAGES || []).filter(function (p) { return !q || p[0].toLowerCase().indexOf(q) > -1; })
        .slice(0, q ? 6 : 8).map(function (p) { return { title: p[0], url: p[1], sub: 'Go to page' }; });
    }
    function search() {
      var q = input.value.trim().toLowerCase();
      var groups = [{ label: 'Pages', items: pages(q) }];
      render(groups);
      if (q.length < 2 || !api) return;
      if (ctrl) ctrl.abort();
      ctrl = 'AbortController' in window ? new AbortController() : null;
      fetch(api + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || input.value.trim().toLowerCase() !== q) return;
          render([{ label: 'Products', items: d.products || [] }, { label: 'Orders', items: d.orders || [] }, { label: 'Customers', items: d.customers || [] }, { label: 'Pages', items: pages(q) }]);
        }).catch(function () {});
    }
    function open() { pal.hidden = false; input.value = ''; input.focus(); search(); }
    function close() { pal.hidden = true; }
    $$('[data-palette-open]').forEach(function (b) { b.addEventListener('click', open); });
    $$('[data-palette-close]', pal).forEach(function (b) { b.addEventListener('click', close); });
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 160); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
      else if (e.key === 'Enter') { var it = items()[active]; if (it) { e.preventDefault(); window.location.href = it.href; } }
    });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); pal.hidden ? open() : close(); }
      else if (e.key === '/' && pal.hidden && !/input|textarea|select/i.test(document.activeElement.tagName)) { e.preventDefault(); open(); }
      else if (e.key === 'Escape') {
        if (!pal.hidden) close();
        if (cModal && !cModal.hidden) closeConfirm();
        document.body.classList.remove('side-open');
        $$('.dropdown.is-open').forEach(function (d) { d.classList.remove('is-open'); });
      }
    });
  }

  /* ---------------- Chart.js theme helpers ---------------- */
  OE.charts = [];
  OE.css = function (name) { return getComputedStyle(root).getPropertyValue(name).trim(); };
  OE.palette = ['#2f6fed', '#ffb21e', '#12a150', '#7c5cff', '#e5484d', '#14b8c4', '#f97316', '#ec4899', '#64748b', '#84cc16', '#0ea5e9', '#a855f7'];
  OE.naira = function (v) { return '₦' + Number(v || 0).toLocaleString('en-NG', { maximumFractionDigits: 0 }); };
  OE.nairaShort = function (v) {
    v = Number(v) || 0;
    if (Math.abs(v) >= 1e6) return '₦' + (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'm';
    if (Math.abs(v) >= 1e3) return '₦' + (v / 1e3).toFixed(0) + 'k';
    return '₦' + v;
  };
  OE.applyChartTheme = function () {
    if (!window.Chart) return;
    var text = OE.css('--muted'), grid = OE.css('--line');
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = text;
    Chart.defaults.borderColor = grid;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0f1a36';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 12;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };
    Chart.defaults.plugins.tooltip.boxPadding = 6;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.animation.duration = reduce ? 0 : 1300;
    Chart.defaults.animation.easing = 'easeOutQuart';
    OE.charts.forEach(function (c) {
      ['x', 'y', 'y1', 'r'].forEach(function (k) {
        var s = c.options.scales && c.options.scales[k]; if (!s) return;
        if (s.grid) s.grid.color = grid;
        if (s.ticks) s.ticks.color = text;
        if (s.pointLabels) s.pointLabels.color = text;
        if (s.angleLines) s.angleLines.color = grid;
      });
      if (c.options.plugins && c.options.plugins.legend && c.options.plugins.legend.labels) c.options.plugins.legend.labels.color = text;
      c.update('none');
    });
  };
  OE.chart = function (el, cfg) {
    if (!window.Chart || !el) return null;
    var c = new Chart(el, cfg); OE.charts.push(c); return c;
  };
  OE.gradient = function (ctx, color, h) {
    var g = ctx.createLinearGradient(0, 0, 0, h || 300);
    g.addColorStop(0, color + '55'); g.addColorStop(1, color + '00'); return g;
  };
  document.addEventListener('oe:theme', OE.applyChartTheme);
  OE.applyChartTheme();
})();
