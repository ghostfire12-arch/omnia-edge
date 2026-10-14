<?php
require_once __DIR__ . '/includes/bootstrap.php';

$period = pick((string) ($_GET['period'] ?? '30'), ['7', '30', '90', '365', 'custom'], '30');
$validDate = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) && strtotime($d);
if ($period === 'custom' && $validDate($_GET['from'] ?? '') && $validDate($_GET['to'] ?? '')) {
    $from = new DateTimeImmutable($_GET['from']); $to = new DateTimeImmutable($_GET['to']);
    if ($from > $to) [$from, $to] = [$to, $from];
    if ($from->diff($to)->days > 730) $from = $to->modify('-730 days');
} else {
    if ($period === 'custom') $period = '30';
    $to = new DateTimeImmutable('today'); $from = $to->modify('-' . ((int) $period - 1) . ' days');
}
$f = $from->format('Y-m-d 00:00:00'); $t = $to->format('Y-m-d 23:59:59');
$spanDays = $from->diff($to)->days + 1;
$byMonth = $spanDays > 92;

// Trend
$fmt = $byMonth ? '%Y-%m' : '%Y-%m-%d';
$buckets = [];
for ($d = $from; $d <= $to; $d = $d->modify($byMonth ? 'first day of next month' : '+1 day')) $buckets[$d->format($byMonth ? 'Y-m' : 'Y-m-d')] = ['rev' => 0, 'ord' => 0, 'items' => 0];
$q = $pdo->prepare("SELECT DATE_FORMAT(created_at, '$fmt') b, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) rev, SUM(order_status <> 'cancelled') ord
    FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY b");
$q->execute([$f, $t]);
foreach ($q as $r) if (isset($buckets[$r['b']])) { $buckets[$r['b']]['rev'] = (float) $r['rev']; $buckets[$r['b']]['ord'] = (int) $r['ord']; }

$sum = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) rev, SUM(order_status <> 'cancelled') ord,
    SUM(order_status = 'cancelled') cancelled, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN shipping_fee END), 0) ship,
    COUNT(DISTINCT customer_email) buyers FROM orders WHERE created_at BETWEEN ? AND ?");
$sum->execute([$f, $t]); $S = $sum->fetch();
$items = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'cancelled'");
$items->execute([$f, $t]); $unitsSold = (int) $items->fetchColumn();
$allOrders = (int) $S['ord'] + (int) $S['cancelled'];

$cat = $pdo->prepare("SELECT COALESCE(c.name, 'Uncategorised') name, SUM(oi.subtotal) total, SUM(oi.quantity) qty FROM order_items oi JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
    WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'cancelled' GROUP BY name ORDER BY total DESC");
$cat->execute([$f, $t]); $byCat = $cat->fetchAll();

$prod = $pdo->prepare("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) qty, SUM(oi.subtotal) total, COUNT(DISTINCT o.id) orders,
        (SELECT image FROM product_images WHERE product_id = oi.product_id ORDER BY sort_order, id LIMIT 1) img,
        (SELECT stock_quantity FROM products WHERE id = oi.product_id) stock
    FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'cancelled'
    GROUP BY oi.product_id, oi.product_name ORDER BY total DESC LIMIT 20");
$prod->execute([$f, $t]); $topProducts = $prod->fetchAll();

$st = $pdo->prepare("SELECT COALESCE(NULLIF(state, ''), 'Unknown') s, COUNT(*) c, SUM(total) t FROM orders WHERE created_at BETWEEN ? AND ? AND order_status <> 'cancelled' GROUP BY s ORDER BY t DESC LIMIT 12");
$st->execute([$f, $t]); $byState = $st->fetchAll();

$wd = array_fill(0, 7, 0);
$w = $pdo->prepare("SELECT WEEKDAY(created_at) d, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) r FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY d");
$w->execute([$f, $t]); foreach ($w as $r) $wd[(int) $r['d']] = (float) $r['r'];

$pageTitle = 'Reports | ' . SITE_NAME;
$activePage = 'reports';
$useCharts = true;
include __DIR__ . '/includes/admin-header.php';
$range = $from->format('d M Y') . ' – ' . $to->format('d M Y');
?>
<div class="page-head">
  <div><h1>Sales report</h1><p><?= e($range) ?> · <?= $spanDays ?> day<?= $spanDays === 1 ? '' : 's' ?></p></div>
  <div class="page-head__actions no-print">
    <nav class="seg" aria-label="Period"><?php foreach (['7' => '7d', '30' => '30d', '90' => '90d', '365' => '12m'] as $k => $v): ?><a href="?period=<?= $k ?>" class="<?= $period === $k ? 'is-active' : '' ?>"><?= $v ?></a><?php endforeach; ?></nav>
    <form method="get" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="period" value="custom">
      <input class="input" type="date" name="from" value="<?= e($from->format('Y-m-d')) ?>" aria-label="From" style="width:auto;min-height:38px">
      <input class="input" type="date" name="to" value="<?= e($to->format('Y-m-d')) ?>" aria-label="To" style="width:auto;min-height:38px">
      <button class="btn btn--ghost btn--sm">Go</button></form>
    <a class="btn btn--ghost btn--sm" href="<?= e(asset_url('admin/export.php?type=sales&from=' . $from->format('Y-m-d') . '&to=' . $to->format('Y-m-d'))) ?>"><?= admin_icon('download') ?>CSV</a>
    <button class="btn btn--ghost btn--sm" type="button" onclick="window.print()"><?= admin_icon('printer') ?>Print</button>
  </div>
</div>

<div class="grid grid--kpi" data-stagger style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
  <div class="card kpi"><span class="kpi__ico tone-blue"><?= admin_icon('wallet') ?></span><p class="kpi__label">Paid revenue</p><p class="kpi__value" data-count="<?= round((float) $S['rev']) ?>" data-prefix="₦">0</p></div>
  <div class="card kpi" style="--glow:rgba(255,178,30,.22)"><span class="kpi__ico tone-amber"><?= admin_icon('cart') ?></span><p class="kpi__label">Orders</p><p class="kpi__value" data-count="<?= (int) $S['ord'] ?>">0</p></div>
  <div class="card kpi" style="--glow:rgba(18,161,80,.2)"><span class="kpi__ico tone-green"><?= admin_icon('box') ?></span><p class="kpi__label">Units sold</p><p class="kpi__value" data-count="<?= $unitsSold ?>">0</p></div>
  <div class="card kpi" style="--glow:rgba(124,92,255,.2)"><span class="kpi__ico tone-violet"><?= admin_icon('users') ?></span><p class="kpi__label">Unique buyers</p><p class="kpi__value" data-count="<?= (int) $S['buyers'] ?>">0</p></div>
  <div class="card kpi" style="--glow:rgba(229,72,77,.2)"><span class="kpi__ico tone-red"><?= admin_icon('x') ?></span><p class="kpi__label">Cancellation rate</p><p class="kpi__value" data-count="<?= $allOrders ? round($S['cancelled'] / $allOrders * 100, 1) : 0 ?>" data-decimals="1" data-suffix="%">0</p></div>
</div>

<section class="card mt">
  <div class="card__head"><div><h2>Revenue trend</h2><p><?= $byMonth ? 'By month' : 'By day' ?> · paid orders</p></div><div class="legend" style="margin:0"><span><i style="background:#2f6fed"></i>Revenue</span><span><i style="background:#12a150"></i>Orders</span></div></div>
  <div class="chart-box" style="height:340px"><canvas id="rTrend" role="img" aria-label="Revenue trend"></canvas></div>
</section>

<div class="grid grid--1-1 mt">
  <section class="card"><div class="card__head"><div><h2>Revenue by category</h2></div></div><div class="chart-box"><canvas id="rCat" role="img" aria-label="Revenue by category"></canvas><?php if (!$byCat): ?><div class="chart-empty">No sales in this range</div><?php endif; ?></div></section>
  <section class="card"><div class="card__head"><div><h2>Best days of the week</h2><p>Paid revenue by weekday</p></div></div><div class="chart-box"><canvas id="rWeek" role="img" aria-label="Revenue by weekday"></canvas></div></section>
</div>

<div class="grid grid--2-1 mt">
  <section class="card card--flush">
    <div class="card__head"><div><h2>Top 20 products</h2><p>Sorted by revenue</p></div></div>
    <?php if (!$topProducts): ?><div class="empty"><?= admin_icon('box') ?><p>No products sold in this range.</p></div><?php else: ?>
    <div class="table-wrap" style="margin-top:12px"><table class="table" data-sortable>
      <thead><tr><th>#</th><th data-sort="text">Product</th><th class="num" data-sort="num">Units</th><th class="num" data-sort="num">Orders</th><th class="num" data-sort="num">Revenue</th><th class="num" data-sort="num">Stock left</th></tr></thead>
      <tbody><?php foreach ($topProducts as $i => $tp): ?><tr>
        <td><?= $i + 1 ?></td>
        <td><div class="prod-cell"><img src="<?= e(product_image_url($tp['img'])) ?>" alt="" loading="lazy"><strong><?= e($tp['product_name']) ?></strong></div></td>
        <td class="num"><?= (int) $tp['qty'] ?></td><td class="num"><?= (int) $tp['orders'] ?></td>
        <td class="num" data-v="<?= (float) $tp['total'] ?>"><strong><?= format_price($tp['total']) ?></strong></td>
        <td class="num" data-v="<?= (int) $tp['stock'] ?>"><?= $tp['stock'] === null ? '—' : ((int) $tp['stock'] <= LOW_STOCK_LEVEL ? '<span class="pill pill--red">' . (int) $tp['stock'] . '</span>' : (int) $tp['stock']) ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </section>
  <section class="card card--flush">
    <div class="card__head"><div><h2>By state</h2><p>Delivered-to location</p></div></div>
    <?php if (!$byState): ?><div class="empty"><p>No orders in this range.</p></div><?php else: ?>
    <div class="table-wrap" style="margin-top:12px"><table class="table"><thead><tr><th>State</th><th class="num">Orders</th><th class="num">Revenue</th></tr></thead>
      <tbody><?php foreach ($byState as $s): ?><tr><td><?= e($s['s']) ?></td><td class="num"><?= (int) $s['c'] ?></td><td class="num"><?= format_price($s['t']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<?php
$labels = array_map(fn($k) => $byMonth ? date('M Y', strtotime($k . '-01')) : date($spanDays > 31 ? 'd M' : 'D d', strtotime($k)), array_keys($buckets));
$data = ['labels' => $labels, 'rev' => array_values(array_column($buckets, 'rev')), 'ord' => array_values(array_column($buckets, 'ord')),
    'cat' => ['l' => array_column($byCat, 'name'), 'd' => array_map('floatval', array_column($byCat, 'total'))], 'week' => $wd];
$adminScripts = '<script>window.OE_REPORT=' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . <<<'JS'
<script>
(function () {
  var R = window.OE_REPORT, OE = window.OE; if (!R || !window.Chart) return;
  var tr = document.getElementById('rTrend');
  OE.chart(tr, { data: { labels: R.labels.slice(), datasets: [
      { type: 'line', label: 'Revenue', data: R.rev.slice(), yAxisID: 'y', borderColor: '#2f6fed', borderWidth: 3, tension: .35, pointRadius: R.labels.length > 40 ? 0 : 3, pointBackgroundColor: '#2f6fed', fill: true,
        backgroundColor: function (c) { return c.chart.chartArea ? OE.gradient(c.chart.ctx, '#2f6fed', c.chart.chartArea.bottom) : 'transparent'; } },
      { type: 'line', label: 'Orders', data: R.ord.slice(), yAxisID: 'y1', borderColor: '#12a150', borderWidth: 2, borderDash: [6, 5], tension: .35, pointRadius: 0 } ] },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + (c.dataset.yAxisID === 'y' ? OE.naira(c.parsed.y) : c.parsed.y); } } } },
      scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
        y: { beginAtZero: true, border: { display: false }, ticks: { callback: OE.nairaShort } }, y1: { beginAtZero: true, position: 'right', grid: { display: false }, border: { display: false }, ticks: { precision: 0 } } } } });
  if (R.cat.d.length) OE.chart(document.getElementById('rCat'), { type: 'bar', data: { labels: R.cat.l, datasets: [{ data: R.cat.d, borderRadius: 8, maxBarThickness: 26, backgroundColor: OE.palette.map(function (c) { return c + 'cc'; }) }] },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return OE.naira(c.parsed.x); } } } },
      scales: { x: { beginAtZero: true, border: { display: false }, ticks: { callback: OE.nairaShort } }, y: { grid: { display: false }, border: { display: false } } } } });
  OE.chart(document.getElementById('rWeek'), { type: 'radar', data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], datasets: [{ label: 'Revenue', data: R.week, borderColor: '#7c5cff', backgroundColor: 'rgba(124,92,255,.22)', pointBackgroundColor: '#7c5cff', borderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return OE.naira(c.parsed.r); } } } },
      scales: { r: { beginAtZero: true, ticks: { display: false }, grid: { color: OE.css('--line') }, angleLines: { color: OE.css('--line') } } } } });
  OE.applyChartTheme();
})();
</script>
JS;
include __DIR__ . '/includes/admin-footer.php';
