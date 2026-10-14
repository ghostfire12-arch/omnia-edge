<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$q = $pdo->prepare('SELECT * FROM orders WHERE id = ?'); $q->execute([$id]); $order = $q->fetch();
if (!$order) { set_flash('error', 'That order could not be found.'); redirect(asset_url('admin/orders.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $newStatus  = pick((string) ($_POST['order_status'] ?? $order['order_status']), ORDER_STATUSES, $order['order_status']);
    $newPayment = pick((string) ($_POST['payment_status'] ?? $order['payment_status']), PAYMENT_STATUSES, $order['payment_status']);
    $note = mb_substr(clean_input($_POST['note'] ?? ''), 0, 1000);
    $changes = [];
    $pdo->beginTransaction();
    // Optionally put items back on the shelf when an order is cancelled.
    if ($newStatus === 'cancelled' && $order['order_status'] !== 'cancelled' && isset($_POST['restock'])) {
        $it = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL'); $it->execute([$id]);
        $up = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?');
        foreach ($it as $row) $up->execute([(int) $row['quantity'], (int) $row['product_id']]);
        $changes[] = 'items returned to stock';
    }
    if ($newStatus !== $order['order_status']) $changes[] = 'status ' . $order['order_status'] . ' → ' . $newStatus;
    if ($newPayment !== $order['payment_status']) $changes[] = 'payment ' . $order['payment_status'] . ' → ' . $newPayment;
    $notes = (string) $order['notes'];
    if ($note !== '') { $notes = trim($notes . "\n[" . date('d M Y H:i') . ' · ' . ($_SESSION['admin_name'] ?? 'Admin') . '] ' . $note); $changes[] = 'note added'; }
    $pdo->prepare('UPDATE orders SET order_status = ?, payment_status = ?, notes = ? WHERE id = ?')->execute([$newStatus, $newPayment, $notes, $id]);
    $pdo->commit();
    if ($changes) admin_log('order', $order['order_number'] . ': ' . implode(', ', $changes));
    set_flash('success', $changes ? 'Order updated — ' . implode(', ', $changes) . '.' : 'Nothing changed.');
    redirect(asset_url('admin/order-details.php?id=' . $id));
}

$it = $pdo->prepare('SELECT oi.*, (SELECT image FROM product_images WHERE product_id = oi.product_id ORDER BY sort_order, id LIMIT 1) img FROM order_items oi WHERE oi.order_id = ?');
$it->execute([$id]); $items = $it->fetchAll();
$history = ['count' => 0, 'spent' => 0];
if ($order['user_id']) {
    $h = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) FROM orders WHERE user_id = ?"); $h->execute([$order['user_id']]);
    [$history['count'], $history['spent']] = $h->fetch(PDO::FETCH_NUM);
} else {
    $h = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) FROM orders WHERE customer_email = ?"); $h->execute([$order['customer_email']]);
    [$history['count'], $history['spent']] = $h->fetch(PDO::FETCH_NUM);
}
$steps = ['pending' => ['Placed', 'clock'], 'processing' => ['Processing', 'box'], 'shipped' => ['Shipped', 'truck'], 'delivered' => ['Delivered', 'check']];
$keys = array_keys($steps);
$cur = $order['order_status'] === 'cancelled' ? -1 : array_search($order['order_status'], $keys, true);
$payLabels = ['cod' => 'Pay on delivery', 'bank_transfer' => 'Bank transfer', 'paystack' => 'Card (Paystack)'];
$next = ['pending' => 'processing', 'processing' => 'shipped', 'shipped' => 'delivered'][$order['order_status']] ?? null;

$pageTitle = 'Order ' . $order['order_number'] . ' | ' . SITE_NAME;
$activePage = 'orders';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div>
    <div class="crumbs"><a href="<?= e(asset_url('admin/orders.php')) ?>">Orders</a><span>/</span><span><?= e($order['order_number']) ?></span></div>
    <h1>Order <?= e($order['order_number']) ?></h1>
    <p>Placed <?= e(format_date($order['created_at'], 'l, d M Y \a\t g:i a')) ?> · <?= status_pill($order['order_status']) ?> <?= status_pill($order['payment_status']) ?></p>
  </div>
  <div class="page-head__actions no-print">
    <button class="btn btn--ghost" type="button" onclick="window.print()"><?= admin_icon('printer') ?>Print invoice</button>
    <?php if ($next): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="order_status" value="<?= $next ?>"><input type="hidden" name="payment_status" value="<?= e($order['payment_status']) ?>">
        <button class="btn btn--primary"><?= admin_icon($steps[$next][1]) ?>Mark as <?= e(strtolower($steps[$next][0])) ?></button></form>
    <?php endif; ?>
  </div>
</div>

<div class="print-only" style="margin-bottom:20px"><h2><?= e(SITE_NAME) ?> — Invoice</h2><p><?= e(get_setting('address')) ?> · <?= e(get_setting('phone')) ?> · <?= e(get_setting('email')) ?></p></div>

<section class="card no-print">
  <?php if ($cur === -1): ?>
    <div class="banner banner--danger" style="margin:0"><?= admin_icon('x') ?><span><strong>This order was cancelled.</strong></span></div>
  <?php else: ?>
    <div class="timeline" style="--p: <?= $cur / (count($steps) - 1) ?>">
      <span class="timeline__fill"></span>
      <?php foreach ($keys as $i => $k): ?>
        <div class="timeline__step <?= $i < $cur ? 'is-done' : ($i === $cur ? ($k === 'delivered' ? 'is-done' : 'is-current') : '') ?>"><span><?= admin_icon($steps[$k][1]) ?></span><?= e($steps[$k][0]) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<div class="grid grid--2-1 mt">
  <div class="stack">
    <section class="card card--flush">
      <div class="card__head"><h2>Items (<?= array_sum(array_column($items, 'quantity')) ?>)</h2></div>
      <div class="table-wrap" style="margin-top:12px">
        <table class="table">
          <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($items as $i): ?>
              <tr>
                <td><div class="prod-cell"><img src="<?= e(product_image_url($i['img'])) ?>" alt=""><div><strong><?php if ($i['product_id']): ?><a href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $i['product_id'])) ?>" style="color:inherit"><?= e($i['product_name']) ?></a><?php else: ?><?= e($i['product_name']) ?><?php endif; ?></strong><?php if (!$i['product_id']): ?><small>Product since deleted</small><?php endif; ?></div></div></td>
                <td class="num"><?= format_price($i['price']) ?></td>
                <td class="num">× <?= (int) $i['quantity'] ?></td>
                <td class="num"><strong><?= format_price($i['subtotal']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:0 22px 22px">
        <div class="totals">
          <div><span class="muted">Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
          <div><span class="muted">Delivery</span><span><?= format_price($order['shipping_fee']) ?></span></div>
          <?php if ((float) $order['discount'] > 0): ?><div><span class="muted">Discount</span><span>−<?= format_price($order['discount']) ?></span></div><?php endif; ?>
          <div class="grand"><span>Total</span><span><?= format_price($order['total']) ?></span></div>
        </div>
      </div>
    </section>

    <?php if (trim((string) $order['notes']) !== ''): ?>
    <section class="card"><div class="card__head"><h2>Notes</h2></div><div class="inbox__msg" style="margin:0"><?= e($order['notes']) ?></div></section>
    <?php endif; ?>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Customer</h2><?php if ($order['user_id']): ?><a class="link-btn no-print" href="<?= e(asset_url('admin/customer-view.php?id=' . (int) $order['user_id'])) ?>">Profile</a><?php else: ?><span class="pill pill--grey">Guest</span><?php endif; ?></div>
      <div class="profile"><span class="avatar avatar--lg"><?= e(initials($order['customer_name'])) ?></span><div><strong><?= e($order['customer_name']) ?></strong><p class="muted" style="font-size:.85rem"><?= (int) $history['count'] ?> order<?= (int) $history['count'] === 1 ? '' : 's' ?> · <?= format_price($history['spent']) ?> spent</p></div></div>
      <dl class="kv mt">
        <dt><?= admin_icon('mail') ?></dt><dd><a href="mailto:<?= e($order['customer_email']) ?>?subject=<?= rawurlencode('Your order ' . $order['order_number']) ?>"><?= e($order['customer_email']) ?></a></dd>
        <?php if ($order['customer_phone']): ?><dt><?= admin_icon('phone') ?></dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $order['customer_phone'])) ?>"><?= e($order['customer_phone']) ?></a></dd><?php endif; ?>
        <dt><?= admin_icon('pin') ?></dt><dd><?= e($order['shipping_address']) ?><br><?= e(trim($order['city'] . ', ' . $order['state'], ', ')) ?><br><?= e($order['country']) ?></dd>
        <dt><?= admin_icon('card') ?></dt><dd><?= e($payLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd>
      </dl>
    </section>

    <section class="card no-print">
      <div class="card__head"><h2>Update order</h2></div>
      <form method="post" class="stack" style="gap:14px" data-cancel-check>
        <?= csrf_field() ?>
        <div class="field"><label for="oStatus">Order status</label><select id="oStatus" name="order_status"><?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $s === $order['order_status'] ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <label class="check-row" data-restock hidden><span><strong>Return items to stock</strong><small>Adds the ordered quantities back to inventory</small></span><input type="checkbox" name="restock" checked></label>
        <div class="field"><label for="oPay">Payment status</label><select id="oPay" name="payment_status"><?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $s === $order['payment_status'] ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="oNote">Internal note <span class="muted">(optional)</span></label><textarea id="oNote" name="note" style="min-height:80px" maxlength="1000" placeholder="e.g. Rider: Musa, 0803… · Customer asked for evening delivery"></textarea></div>
        <button class="btn btn--primary"><?= admin_icon('check') ?>Save changes</button>
      </form>
    </section>
  </div>
</div>
<?php
$adminScripts = <<<'JS'
<script>
(function () {
  var f = document.querySelector('[data-cancel-check]'); if (!f) return;
  var s = f.querySelector('#oStatus'), r = f.querySelector('[data-restock]'), was = s.value;
  function sync() { r.hidden = !(s.value === 'cancelled' && was !== 'cancelled'); }
  s.addEventListener('change', sync); sync();
})();
</script>
JS;
include __DIR__ . '/includes/admin-footer.php';
