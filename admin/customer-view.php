<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$q = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $q->execute([$id]); $u = $q->fetch();
if (!$u) { set_flash('error', 'That customer could not be found.'); redirect(asset_url('admin/customers.php')); }
$name = $u['first_name'] . ' ' . $u['last_name'];

$o = $pdo->prepare('SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) items FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC');
$o->execute([$id]); $orders = $o->fetchAll();
$paid = array_filter($orders, fn($r) => $r['payment_status'] === 'paid');
$spent = array_sum(array_map(fn($r) => (float) $r['total'], $paid));
$aov = $paid ? $spent / count($paid) : 0;

// Monthly spend for the last 12 months
$months = [];
for ($i = 11; $i >= 0; $i--) $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
foreach ($paid as $r) { $m = substr($r['created_at'], 0, 7); if (isset($months[$m])) $months[$m] += (float) $r['total']; }

$w = $pdo->prepare('SELECT p.id, p.name, (SELECT image FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) img FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.created_at DESC LIMIT 8');
$w->execute([$id]); $wish = $w->fetchAll();
$fav = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) q FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.user_id = ? AND o.order_status <> 'cancelled' GROUP BY oi.product_name ORDER BY q DESC LIMIT 5");
$fav->execute([$id]); $favs = $fav->fetchAll();

$pageTitle = $name . ' | Customers | ' . SITE_NAME;
$activePage = 'customers';
$useCharts = true;
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div><div class="crumbs"><a href="<?= e(asset_url('admin/customers.php')) ?>">Customers</a><span>/</span><span>Profile</span></div><h1><?= e($name) ?></h1><p>Customer since <?= e(format_date($u['created_at'], 'F Y')) ?> · <?= status_pill($u['status']) ?></p></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="mailto:<?= e($u['email']) ?>"><?= admin_icon('mail') ?>Email</a><?php if ($u['phone']): ?><a class="btn btn--ghost" href="tel:<?= e(preg_replace('/[^\d+]/', '', $u['phone'])) ?>"><?= admin_icon('phone') ?>Call</a><?php endif; ?></div>
</div>

<div class="grid grid--1-2">
  <div class="stack">
    <section class="card">
      <div class="profile"><span class="avatar avatar--lg"><?= e(initials($name)) ?></span><div><strong style="font-size:1.1rem"><?= e($name) ?></strong><p class="muted"><?= e($u['email']) ?></p></div></div>
      <div class="mini-stats">
        <div><strong data-count="<?= count($orders) ?>">0</strong><span>Orders</span></div>
        <div><strong data-count="<?= round((float) $spent) ?>" data-prefix="₦">0</strong><span>Spent</span></div>
        <div><strong data-count="<?= round((float) $aov) ?>" data-prefix="₦">0</strong><span>Avg. order</span></div>
      </div>
      <dl class="kv mt">
        <dt>Phone</dt><dd><?= e($u['phone'] ?: '—') ?></dd>
        <dt>Address</dt><dd><?= e(trim(implode(', ', array_filter([$u['address'], $u['city'], $u['state'], $u['country']])))) ?: '—' ?></dd>
        <dt>Last order</dt><dd><?= $orders ? e(time_ago($orders[0]['created_at'])) : 'Never' ?></dd>
      </dl>
    </section>
    <section class="card">
      <div class="card__head"><h2>Buys most</h2></div>
      <?php if (!$favs): ?><p class="muted">No purchases yet.</p><?php else: ?><ul class="feed"><?php foreach ($favs as $fv): ?><li style="padding-bottom:10px"><span class="feed__dot tone-blue"><?= admin_icon('box') ?></span><div><p><?= e($fv['product_name']) ?></p><small>× <?= (int) $fv['q'] ?></small></div></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <section class="card">
      <div class="card__head"><h2>Wishlist</h2></div>
      <?php if (!$wish): ?><p class="muted">Nothing saved yet.</p><?php else: ?><div class="thumbs" style="margin:0"><?php foreach ($wish as $wi): ?><a class="thumb" href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $wi['id'])) ?>" title="<?= e($wi['name']) ?>"><img src="<?= e(product_image_url($wi['img'])) ?>" alt="<?= e($wi['name']) ?>"></a><?php endforeach; ?></div><?php endif; ?>
    </section>
  </div>
  <div class="stack">
    <section class="card">
      <div class="card__head"><div><h2>Spending</h2><p>Paid orders, last 12 months</p></div></div>
      <div class="chart-box chart-box--sm"><canvas id="chSpend" role="img" aria-label="Monthly spending"></canvas></div>
    </section>
    <section class="card card--flush">
      <div class="card__head"><h2>Orders</h2></div>
      <?php if (!$orders): ?><div class="empty"><?= admin_icon('cart') ?><p>This customer has not ordered yet.</p></div><?php else: ?>
      <div class="table-wrap" style="margin-top:12px"><table class="table">
        <thead><tr><th>Order</th><th>Date</th><th class="num">Items</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead>
        <tbody><?php foreach ($orders as $r): ?><tr>
          <td><a href="<?= e(asset_url('admin/order-details.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['order_number']) ?></strong></a></td>
          <td><?= e(format_date($r['created_at'])) ?></td><td class="num"><?= (int) $r['items'] ?></td>
          <td><?= status_pill($r['order_status']) ?></td><td><?= status_pill($r['payment_status']) ?></td><td class="num"><strong><?= format_price($r['total']) ?></strong></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
$adminScripts = '<script>(function(){var el=document.getElementById("chSpend");if(!el||!window.OE)return;'
  . 'var L=' . json_encode(array_map(fn($m) => date('M y', strtotime($m . '-01')), array_keys($months))) . ',D=' . json_encode(array_values($months)) . ';'
  . 'OE.chart(el,{type:"bar",data:{labels:L,datasets:[{data:D,borderRadius:8,maxBarThickness:28,backgroundColor:function(c){if(!c.chart.chartArea)return"#2f6fed";var a=c.chart.chartArea,g=c.chart.ctx.createLinearGradient(0,a.bottom,0,a.top);g.addColorStop(0,"#5b8cff");g.addColorStop(1,"#2f6fed");return g;}}]},'
  . 'options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return OE.naira(c.parsed.y);}}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,border:{display:false},ticks:{callback:OE.nairaShort,maxTicksLimit:5}}}}});OE.applyChartTheme();})();</script>';
include __DIR__ . '/includes/admin-footer.php';
