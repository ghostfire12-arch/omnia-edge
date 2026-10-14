/* OMNIA EDGE — dashboard charts (Chart.js 4) */
(function () {
  'use strict';
  var D = window.OE_DASH, OE = window.OE;
  if (!D || !window.Chart || !OE) return;
  var $ = function (id) { return document.getElementById(id); };
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var BLUE = '#2f6fed', SAFFRON = '#ffb21e';
  var statusColors = ['#ffb21e', '#2f6fed', '#7c5cff', '#12a150', '#e5484d'];

  /* --- sparklines inside KPI cards --- */
  document.querySelectorAll('[data-spark]').forEach(function (cv) {
    var key = cv.getAttribute('data-spark'), color = cv.getAttribute('data-color'), data = D.series[key] || [];
    OE.chart(cv, {
      type: 'line',
      data: { labels: D.labels.slice(), datasets: [{ data: data.slice(), borderColor: color, borderWidth: 2, tension: .42, pointRadius: 0, fill: true,
        backgroundColor: function (c) { return c.chart.chartArea ? OE.gradient(c.chart.ctx, color, c.chart.chartArea.bottom) : 'transparent'; } }] },
      options: { responsive: true, maintainAspectRatio: false, animation: { duration: reduce ? 0 : 1600 },
        plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }
    });
  });

  /* --- revenue (line) + orders (bars) --- */
  var rev = $('chRevenue');
  if (rev) {
    OE.chart(rev, {
      data: {
        labels: D.labels.slice(),
        datasets: [
          { type: 'line', label: 'Revenue', data: D.series.rev, yAxisID: 'y', borderColor: BLUE, borderWidth: 3, tension: .4, pointRadius: 0, pointHoverRadius: 6,
            pointHoverBackgroundColor: BLUE, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3, fill: true, order: 1,
            backgroundColor: function (c) { return c.chart.chartArea ? OE.gradient(c.chart.ctx, BLUE, c.chart.chartArea.bottom) : 'transparent'; } },
          { type: 'bar', label: 'Orders', data: D.series.ord, yAxisID: 'y1', backgroundColor: SAFFRON + 'cc', hoverBackgroundColor: SAFFRON, borderRadius: 6, maxBarThickness: 18, order: 2 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        animations: reduce ? false : { y: { from: function (c) { return c.chart.scales.y ? c.chart.scales.y.getPixelForValue(0) : 0; }, duration: 1200, easing: 'easeOutQuart', delay: function (c) { return c.type === 'data' ? c.dataIndex * 18 : 0; } } },
        plugins: { legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + (c.dataset.yAxisID === 'y' ? OE.naira(c.parsed.y) : c.parsed.y); } } } },
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
          y: { beginAtZero: true, grid: { color: OE.css('--line') }, border: { display: false }, ticks: { callback: OE.nairaShort, maxTicksLimit: 6 } },
          y1: { beginAtZero: true, position: 'right', grid: { display: false }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 6 } }
        }
      }
    });
  }

  /* --- monthly target gauge (half doughnut) --- */
  var tg = $('chTarget');
  if (tg) {
    var pct = Math.max(0, Math.min(100, D.target));
    OE.chart(tg, {
      type: 'doughnut',
      data: { datasets: [{ data: [pct, 100 - pct], backgroundColor: function (c) {
        if (c.dataIndex === 1) return OE.css('--surface-2') || '#eef1f6';
        if (!c.chart.chartArea) return BLUE;
        var a = c.chart.chartArea, g = c.chart.ctx.createLinearGradient(a.left, 0, a.right, 0);
        g.addColorStop(0, '#2f6fed'); g.addColorStop(1, '#7c5cff'); return g;
      }, borderWidth: 0, borderRadius: [12, 0], circumference: 260, rotation: -130, cutout: '78%' }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } },
        animation: { animateRotate: !reduce, duration: reduce ? 0 : 1800 } }
    });
  }

  /* --- order status doughnut --- */
  var st = $('chStatus');
  if (st && D.status.data.reduce(function (a, b) { return a + b; }, 0) > 0) {
    OE.chart(st, {
      type: 'doughnut',
      data: { labels: D.status.labels, datasets: [{ data: D.status.data, backgroundColor: statusColors, borderWidth: 3, borderColor: OE.css('--surface'), hoverOffset: 10, borderRadius: 6, spacing: 2 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'right', labels: { boxWidth: 8, padding: 12 } } },
        animation: { animateRotate: !reduce, animateScale: !reduce, duration: reduce ? 0 : 1400 } }
    });
  }

  /* --- sales by category (polar area) --- */
  var ca = $('chCategory');
  if (ca && D.category.data.length) {
    OE.chart(ca, {
      type: 'polarArea',
      data: { labels: D.category.labels, datasets: [{ data: D.category.data, backgroundColor: OE.palette.map(function (c) { return c + 'b3'; }), borderWidth: 2, borderColor: OE.css('--surface') }] },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right', labels: { boxWidth: 8, padding: 10 } }, tooltip: { callbacks: { label: function (c) { return c.label + ': ' + OE.naira(c.parsed.r); } } } },
        scales: { r: { ticks: { display: false }, grid: { color: OE.css('--line') }, angleLines: { color: OE.css('--line') } } },
        animation: { animateRotate: !reduce, animateScale: !reduce, duration: reduce ? 0 : 1500 } }
    });
  }

  /* --- payment methods (pie) --- */
  var pm = $('chPayment');
  if (pm && D.payment.data.length) {
    OE.chart(pm, {
      type: 'pie',
      data: { labels: D.payment.labels, datasets: [{ data: D.payment.data, backgroundColor: ['#2f6fed', '#12a150', '#ffb21e', '#7c5cff'], borderWidth: 3, borderColor: OE.css('--surface'), hoverOffset: 10 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 14 } },
        tooltip: { callbacks: { label: function (c) { var t = c.dataset.data.reduce(function (a, b) { return a + b; }, 0); return c.label + ': ' + c.parsed + ' (' + Math.round(c.parsed / t * 100) + '%)'; } } } } }
    });
  }

  /* --- top states (horizontal bars) --- */
  var sb = $('chStates');
  if (sb && D.states.data.length) {
    OE.chart(sb, {
      type: 'bar',
      data: { labels: D.states.labels, datasets: [{ label: 'Revenue', data: D.states.data, borderRadius: 8, borderSkipped: false, maxBarThickness: 22,
        backgroundColor: function (c) {
          if (!c.chart.chartArea) return BLUE;
          var a = c.chart.chartArea, g = c.chart.ctx.createLinearGradient(a.left, 0, a.right, 0);
          g.addColorStop(0, '#5b8cff'); g.addColorStop(1, '#2f6fed'); return g;
        } }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        animations: reduce ? false : { x: { from: 0, duration: 1200, easing: 'easeOutQuart', delay: function (c) { return c.dataIndex * 90; } } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return OE.naira(c.parsed.x); } } } },
        scales: { x: { beginAtZero: true, grid: { color: OE.css('--line') }, border: { display: false }, ticks: { callback: OE.nairaShort, maxTicksLimit: 5 } },
          y: { grid: { display: false }, border: { display: false } } } }
    });
  }

  OE.applyChartTheme();
})();
