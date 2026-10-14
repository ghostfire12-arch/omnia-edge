<?php
require_once __DIR__ . '/includes/bootstrap.php';

$period = (int) pick((string) ($_GET['period'] ?? '30'), ['7', '30', '90'], '30');
$now    = new DateTimeImmutable('today');
$from   = $now->modify('-' . ($period - 1) . ' days');           // inclusive start (today counts)
$prevFrom = $from->modify('-' . $period . ' days');
$f = $from->format('Y-m-d 00:00:00');
$p = $prevFrom->format('Y-m-d 00:00:00');

/* ---------- KPI totals: this period vs previous period ---------- */
$kpiSql = "SELECT
    COALESCE(SUM(CASE WHEN payment_status = 'paid' AND created_at >= :f THEN total END), 0) AS rev,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' AND created_at <  :f2 AND created_at >= :p THEN total END), 0) AS rev_prev,
    SUM(CASE WHEN order_status <> 'cancelled' AND created_at >= :f3 THEN 1 ELSE 0 END) AS ord,
    SUM(CASE WHEN order_status <> 'cancelled' AND created_at <  :f4 AND created_at >= :p2 THEN 1 ELSE 0 END) AS ord_prev,
    SUM(CASE WHEN payment_status = 'paid' AND created_at >= :f5 THEN 1 ELSE 0 END) AS paid_cnt,
    SUM(CASE WHEN payment_status = 'paid' AND created_at <  :f6 AND created_at >= :p3 THEN 1 ELSE 0 END) AS paid_prev
  FROM orders WHERE created_at >= :p4";
$q = $pdo->prepare($kpiSql);
$q->execute([':f' => $f, ':f2' => $f, ':f3' => $f, ':f4' => $f, ':f5' => $f, ':f6' => $f, ':p' => $p, ':p2' => $p, ':p3' => $p, ':p4' => $p]);
$k = $q->fetch();
$q = $pdo->prepare('SELECT SUM(created_at >= :f) cur, SUM(created_at < :f2 AND created_at >= :p) prev FROM users WHERE created_at >= :p2');
$q->execute([':f' => $f, ':f2' => $f, ':p' => $p, ':p2' => $p]);
$cust = $q->fetch();

$aov     = $k['paid_cnt'] ? $k['rev'] / $k['paid_cnt'] : 0;
$aovPrev = $k['paid_prev'] ? $k['rev_prev'] / $k['paid_prev'] : 0;
$delta = function ($cur, $prev) { if ($prev <= 0) return $cur > 0 ? 100.0 : 0.0; return ($cur - $prev) / $prev * 100; };

/* ---------- Daily series for charts + sparklines ---------- */
$days = [];
for ($d = $from; $d <= $now; $d = $d->modify('+1 day')) $days[$d->format('Y-m-d')] = ['rev' => 0, 'ord' => 0, 'cust' => 0];
$q = $pdo->prepare("SELECT DATE(created_at) d,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) rev,
        SUM(order_status <> 'cancelled') ord
    FROM orders WHERE created_at >= :f GROUP BY DATE(created_at)");
$q->execute([':f' => $f]);
foreach ($q as $r) if (isset($days[$r['d']])) { $days[$r['d']]['rev'] = (float) $r['rev']; $days[$r['d']]['ord'] = (int) $r['ord']; }
$q = $pdo->prepare('SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= :f GROUP BY DATE(created_at)');
$q->execute([':f' => $f]);
foreach ($q as $r) if (isset($days[$r['d']])) $days[$r['d']]['cust'] = (int) $r['c'];
$labels = array_map(fn($d) => date($period > 31 ? 'd M' : 'D d', strtotime($d)), array_keys($days));
$series = [
    'rev'  => array_column($days, 'rev'),
    'ord'  => array_column($days, 'ord'),
    'cust' => array_column($days, 'cust'),
    'aov'  => array_values(array_map(fn($x) => $x['ord'] ? round($x['rev'] / max(1, $x['ord'])) : 0, $days)),
];

/* ---------- Breakdowns ---------- */
$q = $pdo->prepare('SELECT order_status s, COUNT(*) c FROM orders WHERE created_at >= :f GROUP BY order_status');
$q->execute([':f' => $f]);
$statusCounts = array_fill_keys(ORDER_STATUSES, 0);
foreach ($q as $r) $statusCounts[$r['s']] = (int) $r['c'];

$q = $pdo->prepare("SELECT COALESCE(c.name, 'Uncategorised') name, SUM(oi.subtotal) total
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
    WHERE o.created_at >= :f AND o.order_status <> 'cancelled' GROUP BY name ORDER BY total DESC LIMIT 8");
$q->execute([':f' => $f]);
$byCategory = $q->fetchAll();

$q = $pdo->prepare("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) qty, SUM(oi.subtotal) total,
        (SELECT image FROM product_images WHERE product_id = oi.product_id ORDER BY sort_order, id LIMIT 1) img
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.created_at >= :f AND o.order_status <> 'cancelled'
    GROUP BY oi.product_id, oi.product_name ORDER BY total DESC LIMIT 6");
$q->execute([':f' => $f]);
$topProducts = $q->fetchAll();
$topMax = $topProducts ? max(array_column($topProducts, 'total')) : 1;

$q = $pdo->prepare("SELECT payment_method m, COUNT(*) c FROM orders WHERE created_at >= :f AND order_status <> 'cancelled' GROUP BY payment_method ORDER BY c DESC");
$q->execute([':f' => $f]);
$byPayment = $q->fetchAll();

$q = $pdo->prepare("SELECT COALESCE(NULLIF(state, ''), 'Unknown') s, SUM(total) t, COUNT(*) c FROM orders WHERE created_at >= :f AND order_status <> 'cancelled' GROUP BY s ORDER BY t DESC LIMIT 7");
$q->execute([':f' => $f]);
$byState = $q->fetchAll();

// Heatmap: weekday x 2-hour block over the last 90 days.
$heat = array_fill(0, 7, array_fill(0, 12, 0));
foreach ($pdo->query("SELECT WEEKDAY(created_at) wd, FLOOR(HOUR(created_at) / 2) hb, COUNT(*) c FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY wd, hb") as $r) {
    $heat[(int) $r['wd']][(int) $r['hb']] = (int) $r['c'];
}
$heatMax = max(1, max(array_map('max', $heat)));

// Monthly target ring
$target = (float) get_setting('monthly_target', '5000000');
$monthRev = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$targetPct = $target > 0 ? min(100, $monthRev / $target * 100) : 0;
$daysInMonth = (int) date('t'); $dayOfMonth = (int) date('j');
$projected = $dayOfMonth ? $monthRev / $dayOfMonth * $daysInMonth : 0;

$recentOrders = $pdo->query('SELECT id, order_number, customer_name, total, order_status, payment_status, created_at FROM orders ORDER BY created_at DESC LIMIT 7')->fetchAll();
$lowStock = $pdo->query("SELECT p.id, p.name, p.stock_quantity, (SELECT image FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) img
    FROM products p WHERE p.status = 'active' AND p.stock_quantity <= " . (LOW_STOCK_LEVEL * 2) . " ORDER BY p.stock_quantity ASC LIMIT 6")->fetchAll();
admin_activity_table($pdo);
$activity = $pdo->query('SELECT action, detail, admin_name, created_at FROM admin_activity ORDER BY id DESC LIMIT 7')->fetchAll();
$counts = admin_counts($pdo);
$catalog = $pdo->query("SELECT COUNT(*) total, SUM(status = 'active') active FROM products")->fetch();

$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $_SESSION['admin_name'] ?? 'there')[0];
$hasData = array_sum($series['ord']) > 0;

$pageTitle = 'Dashboard | ' . SITE_NAME;
$activePage = 'dashboard';
$useCharts = true;
include __DIR__ . '/includes/admin-header.php';

$kpis = [
    ['Revenue', 'wallet', 'blue', $k['rev'], $delta($k['rev'], $k['rev_prev']), '₦', 'rev', 'rgba(47,111,237,.18)', 'Paid orders only'],
    ['Orders', 'cart', 'amber', $k['ord'], $delta($k['ord'], $k['ord_prev']), '', 'ord', 'rgba(255,178,30,.22)', 'Excludes cancelled'],
    ['Average order', 'card', 'violet', $aov, $delta($aov, $aovPrev), '₦', 'aov', 'rgba(124,92,255,.2)', 'Revenue ÷ paid orders'],
    ['New customers', 'users', 'green', (int) $cust['cur'], $delta((float) $cust['cur'], (float) $cust['prev']), '', 'cust', 'rgba(18,161,80,.2)', 'Accounts created'],
];
$payLabels = ['cod' => 'Pay on delivery', 'bank_transfer' => 'Bank transfer', 'paystack' => 'Card (Paystack)'];
$activityIcon = ['order' => ['cart', 'amber'], 'product' => ['box', 'blue'], 'category' => ['tag', 'violet'], 'settings' => ['settings', 'blue'], 'security' => ['shield', 'red'], 'import' => ['upload', 'green'], 'customer' => ['users', 'green'], 'message' => ['mail', 'blue'], 'demo' => ['database', 'violet']];
?>

<section class="hello">
  <div>
    <h1><?= e($greet) ?>, <?= e($firstName) ?></h1>
    <p>Here is how Omnia Edge is doing <?= $period === 7 ? 'this week' : 'over the last ' . $period . ' days' ?>.</p>
  </div>
  <div class="hello__stats">
    <div><strong data-count="<?= (int) $counts['pending'] ?>">0</strong><span>Orders to process</span></div>
    <div><strong data-count="<?= (int) $catalog['active'] ?>">0</strong><span>Live products</span></div>
    <div><strong data-count="<?= (int) $counts['unread'] ?>">0</strong><span>Unread messages</span></div>
  </div>
</section>

<?php if (!$hasData): ?>
  <div class="banner banner--info"><?= admin_icon('chart') ?><span><strong>No orders in this period yet.</strong> Charts fill in automatically as orders come in. To explore the dashboard now, load sample sales data in Settings (it is tagged and removable in one click).</span>
  <a class="btn btn--sm btn--ghost" href="<?= e(asset_url('admin/settings.php?tab=demo')) ?>">Load sample data</a></div>
<?php endif; ?>

<div class="page-head">
  <div><h2>Performance</h2></div>
  <div class="page-head__actions">
    <nav class="seg" aria-label="Period">
      <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $val => $lab): ?>
        <a href="?period=<?= $val ?>" class="<?= $period === $val ? 'is-active' : '' ?>"<?= $period === $val ? ' aria-current="true"' : '' ?>><?= $lab ?></a>
      <?php endforeach; ?>
    </nav>
    <a class="btn btn--ghost btn--sm" href="<?= e(asset_url('admin/reports.php?period=' . $period)) ?>"><?= admin_icon('chart') ?>Full report</a>
  </div>
</div>

<div class="grid grid--kpi" data-stagger>
  <?php foreach ($kpis as [$label, $icon, $tone, $value, $d, $prefix, $key, $glow, $hint]): $dir = abs($d) < 0.5 ? 'flat' : ($d > 0 ? 'up' : 'down'); ?>
    <article class="card kpi" style="--glow: <?= $glow ?>">
      <div class="kpi__top">
        <span class="kpi__ico tone-<?= $tone ?>"><?= admin_icon($icon) ?></span>
        <span class="kpi__delta kpi__delta--<?= $dir ?>" title="Compared with the previous <?= $period ?> days">
          <?= $dir === 'flat' ? '' : admin_icon($dir) ?><?= $dir === 'flat' ? '0%' : number_format(abs($d), abs($d) >= 10 ? 0 : 1) . '%' ?>
        </span>
      </div>
      <p class="kpi__label" title="<?= e($hint) ?>"><?= e($label) ?></p>
      <p class="kpi__value" data-count="<?= round((float) $value) ?>" data-prefix="<?= $prefix ?>">0</p>
      <div class="kpi__spark"><canvas data-spark="<?= $key ?>" data-color="<?= ['blue' => '#2f6fed', 'amber' => '#ffb21e', 'violet' => '#7c5cff', 'green' => '#12a150'][$tone] ?>" aria-hidden="true"></canvas></div>
    </article>
  <?php endforeach; ?>
</div>

<div class="grid grid--2-1 mt" data-stagger>
  <section class="card">
    <div class="card__head">
      <div><h2>Revenue &amp; orders</h2><p>Daily paid revenue (line) and orders placed (bars)</p></div>
      <div class="legend" style="margin:0"><span><i style="background:#2f6fed"></i>Revenue</span><span><i style="background:#ffb21e"></i>Orders</span></div>
    </div>
    <div class="chart-box"><canvas id="chRevenue" aria-label="Revenue and orders per day" role="img"></canvas><?php if (!$hasData): ?><div class="chart-empty">No orders in this period</div><?php endif; ?></div>
  </section>
  <section class="card">
    <div class="card__head"><div><h2>Monthly target</h2><p><?= e(date('F Y')) ?></p></div><a class="link-btn" href="<?= e(asset_url('admin/settings.php')) ?>">Edit</a></div>
    <div class="chart-box chart-box--sm" style="height:210px"><canvas id="chTarget" aria-label="Monthly revenue target progress" role="img"></canvas>
      <div class="chart-empty" style="flex-direction:column"><div><strong style="display:block;font:800 1.9rem var(--display);color:var(--text)" data-count="<?= round((float) $targetPct) ?>" data-suffix="%">0%</strong><span>of <?= format_price($target) ?></span></div></div>
    </div>
    <dl class="kv" style="margin-top:14px">
      <dt>Earned so far</dt><dd><?= format_price($monthRev) ?></dd>
      <dt>On track for</dt><dd><?= format_price(round($projected)) ?> <?= $projected >= $target ? '<span class="pill pill--green"><i></i>Ahead</span>' : '<span class="pill pill--amber"><i></i>Behind</span>' ?></dd>
      <dt>Days left</dt><dd><?= $daysInMonth - $dayOfMonth ?></dd>
    </dl>
  </section>
</div>

<div class="grid grid--3 mt" data-stagger>
  <section class="card">
    <div class="card__head"><div><h2>Order status</h2><p>Orders placed in this period</p></div></div>
    <div class="chart-box chart-box--sm"><canvas id="chStatus" role="img" aria-label="Orders by status"></canvas><?php if (!array_sum($statusCounts)): ?><div class="chart-empty">No orders yet</div><?php endif; ?></div>
  </section>
  <section class="card">
    <div class="card__head"><div><h2>Sales by category</h2><p>Item revenue, cancelled orders excluded</p></div></div>
    <div class="chart-box chart-box--sm"><canvas id="chCategory" role="img" aria-label="Sales by category"></canvas><?php if (!$byCategory): ?><div class="chart-empty">No sales yet</div><?php endif; ?></div>
  </section>
  <section class="card">
    <div class="card__head"><div><h2>Payment methods</h2><p>Share of orders</p></div></div>
    <div class="chart-box chart-box--sm"><canvas id="chPayment" role="img" aria-label="Orders by payment method"></canvas><?php if (!$byPayment): ?><div class="chart-empty">No orders yet</div><?php endif; ?></div>
  </section>
</div>

<div class="grid grid--1-1 mt" data-stagger>
  <section class="card">
    <div class="card__head"><div><h2>Best sellers</h2><p>By revenue in this period</p></div><a class="link-btn" href="<?= e(asset_url('admin/reports.php?period=' . $period)) ?>">See all</a></div>
    <?php if (!$topProducts): ?><div class="empty"><?= admin_icon('box') ?><p>Best sellers appear after your first orders.</p></div><?php else: ?>
    <div class="bars">
      <?php foreach ($topProducts as $tp): ?>
        <div class="bars__row">
          <img src="<?= e(product_image_url($tp['img'])) ?>" alt="" loading="lazy">
          <div style="min-width:0"><div class="bars__name"><?= e($tp['product_name']) ?></div><div class="bars__track"><i style="--w: <?= round($tp['total'] / $topMax * 100) ?>%"></i></div></div>
          <div class="bars__val"><?= format_price($tp['total']) ?><br><small class="muted"><?= (int) $tp['qty'] ?> sold</small></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <section class="card">
    <div class="card__head"><div><h2>Top states</h2><p>Where your orders are delivered</p></div></div>
    <div class="chart-box chart-box--sm" style="height:280px"><canvas id="chStates" role="img" aria-label="Revenue by state"></canvas><?php if (!$byState): ?><div class="chart-empty">No deliveries yet</div><?php endif; ?></div>
  </section>
</div>

<div class="grid grid--2-1 mt" data-stagger>
  <section class="card card--flush">
    <div class="card__head"><div><h2>Recent orders</h2><p>Latest 7 orders across all statuses</p></div><a class="btn btn--ghost btn--sm" href="<?= e(asset_url('admin/orders.php')) ?>">All orders</a></div>
    <div class="table-wrap" style="margin-top:14px">
      <?php if (!$recentOrders): ?><div class="empty"><?= admin_icon('cart') ?><h3>No orders yet</h3><p>New orders will show up here the moment they are placed.</p></div><?php else: ?>
      <table class="table">
        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td><a href="<?= e(asset_url('admin/order-details.php?id=' . (int) $o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a><br><small class="muted"><?= e(time_ago($o['created_at'])) ?></small></td>
              <td><?= e($o['customer_name']) ?></td>
              <td><?= status_pill($o['order_status']) ?></td>
              <td><?= status_pill($o['payment_status']) ?></td>
              <td class="num"><strong><?= format_price($o['total']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>
  <div class="stack">
    <section class="card">
      <div class="card__head"><div><h2>Restock soon</h2><p>Active products with <?= LOW_STOCK_LEVEL * 2 ?> or fewer units</p></div><a class="link-btn" href="<?= e(asset_url('admin/products.php?stock=low')) ?>">View</a></div>
      <?php if (!$lowStock): ?><p class="muted">Everything is well stocked.</p><?php else: ?>
      <div class="bars">
        <?php foreach ($lowStock as $ls): $s = (int) $ls['stock_quantity']; ?>
          <a class="bars__row" style="color:inherit" href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $ls['id'])) ?>">
            <img src="<?= e(product_image_url($ls['img'])) ?>" alt="" loading="lazy">
            <div style="min-width:0"><div class="bars__name"><?= e($ls['name']) ?></div><div class="bars__track"><i style="--w: <?= min(100, $s * 10) ?>%; background: <?= $s <= LOW_STOCK_LEVEL ? 'var(--red)' : 'var(--saffron)' ?>"></i></div></div>
            <div class="bars__val" style="color: <?= $s <= LOW_STOCK_LEVEL ? 'var(--red)' : 'inherit' ?>"><?= $s ?> left</div>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<div class="grid grid--2-1 mt" data-stagger>
  <section class="card">
    <div class="card__head"><div><h2>When customers order</h2><p>Orders by day and time over the last 90 days. Darker means busier.</p></div></div>
    <div class="heat" role="img" aria-label="Order heatmap by weekday and time of day">
      <span></span><?php for ($h = 0; $h < 12; $h++): ?><span style="justify-content:center"><?= $h % 2 === 0 ? sprintf('%02d', $h * 2) : '' ?></span><?php endfor; ?>
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd => $wdName): ?>
        <span><?= $wdName ?></span>
        <?php foreach ($heat[$wd] as $hb => $c): ?><i style="--v: <?= round($c / $heatMax, 2) ?>" title="<?= $wdName ?> <?= sprintf('%02d:00–%02d:59', $hb * 2, $hb * 2 + 1) ?>: <?= $c ?> order<?= $c === 1 ? '' : 's' ?>"></i><?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <div class="card__head"><div><h2>Admin activity</h2><p>Latest changes in this console</p></div></div>
    <?php if (!$activity): ?><p class="muted">Changes you make (orders, products, settings) will be listed here.</p><?php else: ?>
    <ul class="feed">
      <?php foreach ($activity as $a): [$ico, $tone] = $activityIcon[$a['action']] ?? ['activity', 'blue']; ?>
        <li><span class="feed__dot tone-<?= $tone ?>"><?= admin_icon($ico) ?></span><div><p><?= e($a['detail']) ?></p><small><?= e($a['admin_name'] ?: 'Admin') ?> · <?= e(time_ago($a['created_at'])) ?></small></div></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<?php
$chartData = [
    'labels' => $labels, 'series' => $series,
    'status' => ['labels' => array_map('ucfirst', array_keys($statusCounts)), 'data' => array_values($statusCounts)],
    'category' => ['labels' => array_column($byCategory, 'name'), 'data' => array_map('floatval', array_column($byCategory, 'total'))],
    'payment' => ['labels' => array_map(fn($r) => $payLabels[$r['m']] ?? ucfirst(str_replace('_', ' ', $r['m'])), $byPayment), 'data' => array_map('intval', array_column($byPayment, 'c'))],
    'states' => ['labels' => array_column($byState, 's'), 'data' => array_map('floatval', array_column($byState, 't'))],
    'target' => round($targetPct, 1),
];
$adminScripts = '<script>window.OE_DASH = ' . json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
    . '<script src="' . e(asset_url('assets/js/admin-dashboard.js')) . '?v=' . (int) @filemtime(ROOT_PATH . '/assets/js/admin-dashboard.js') . '"></script>';
include __DIR__ . '/includes/admin-footer.php';
