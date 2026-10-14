<?php
require_once __DIR__ . '/includes/bootstrap.php';

$f = [
    'status'  => pick((string) ($_GET['status'] ?? ''), array_merge([''], ORDER_STATUSES), ''),
    'payment' => pick((string) ($_GET['payment'] ?? ''), array_merge([''], PAYMENT_STATUSES), ''),
    'q'       => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
    'from'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '',
    'to'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '',
    'sort'    => pick((string) ($_GET['sort'] ?? 'newest'), ['newest', 'oldest', 'total_hi', 'total_lo'], 'newest'),
];
$qs = function (array $over = []) use ($f) {
    $p = array_filter(array_merge($f, $over), fn($v) => $v !== '' && $v !== null && $v !== 0);
    return $p ? '?' . http_build_query($p) : '';
};
$back = asset_url('admin/orders.php') . $qs(['page' => (int) ($_GET['page'] ?? 1)]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $to = (string) ($_POST['bulk_status'] ?? '');
    if (!$ids) set_flash('error', 'Select at least one order.');
    elseif (in_array($to, ORDER_STATUSES, true)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE orders SET order_status = ? WHERE id IN ($in)")->execute(array_merge([$to], $ids));
        admin_log('order', 'Marked ' . count($ids) . ' order(s) as ' . $to);
        set_flash('success', count($ids) . ' order' . (count($ids) === 1 ? '' : 's') . ' marked as ' . $to . '.');
    } elseif (in_array($to, ['paid'], true)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id IN ($in)")->execute($ids);
        admin_log('order', 'Marked ' . count($ids) . ' order(s) as paid');
        set_flash('success', count($ids) . ' order' . (count($ids) === 1 ? '' : 's') . ' marked as paid.');
    } else set_flash('error', 'Choose what to change.');
    redirect($back);
}

$where = []; $params = [];
if ($f['status'] !== '')  { $where[] = 'o.order_status = ?'; $params[] = $f['status']; }
if ($f['payment'] !== '') { $where[] = 'o.payment_status = ?'; $params[] = $f['payment']; }
if ($f['q'] !== '')       { $l = '%' . addcslashes($f['q'], '%_\\') . '%'; $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)'; array_push($params, $l, $l, $l, $l); }
if ($f['from'] !== '')    { $where[] = 'o.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to'] !== '')      { $where[] = 'o.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = ['newest' => 'o.created_at DESC', 'oldest' => 'o.created_at ASC', 'total_hi' => 'o.total DESC', 'total_lo' => 'o.total ASC'][$f['sort']];

$c = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(o.total), 0) FROM orders o $whereSql"); $c->execute($params); [$total, $sum] = $c->fetch(PDO::FETCH_NUM);
$total = (int) $total;
$perPage = 25; $pages = max(1, (int) ceil($total / $perPage)); $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$q = $pdo->prepare("SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS items FROM orders o $whereSql ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$q->execute($params);
$orders = $q->fetchAll();

$tabCounts = array_fill_keys(ORDER_STATUSES, 0);
foreach ($pdo->query('SELECT order_status s, COUNT(*) c FROM orders GROUP BY order_status') as $r) $tabCounts[$r['s']] = (int) $r['c'];
$allCount = array_sum($tabCounts);
$payLabels = ['cod' => 'Pay on delivery', 'bank_transfer' => 'Bank transfer', 'paystack' => 'Card'];

$pageTitle = 'Orders | ' . SITE_NAME;
$activePage = 'orders';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div><h1>Orders</h1><p><?= number_format($total) ?> order<?= $total === 1 ? '' : 's' ?> worth <?= format_price($sum) ?> match your filters.</p></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(asset_url('admin/export.php')) . e($qs(['type' => 'orders'])) ?>"><?= admin_icon('download') ?>Export CSV</a></div>
</div>

<nav class="tabs" aria-label="Order status">
  <a href="<?= e($qs(['status' => '', 'page' => ''])) ?: '?' ?>" class="<?= $f['status'] === '' ? 'is-active' : '' ?>">All <span class="count"><?= $allCount ?></span></a>
  <?php foreach (ORDER_STATUSES as $s): ?>
    <a href="<?= e($qs(['status' => $s, 'page' => ''])) ?>" class="<?= $f['status'] === $s ? 'is-active' : '' ?>"><?= ucfirst($s) ?> <span class="count"><?= $tabCounts[$s] ?></span></a>
  <?php endforeach; ?>
</nav>

<section class="card card--flush" data-bulk>
  <form class="toolbar" method="get">
    <input type="hidden" name="status" value="<?= e($f['status']) ?>">
    <label class="search-field toolbar__grow"><?= admin_icon('search') ?><span class="sr-only">Search orders</span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Order number, name, email or phone"></label>
    <select name="payment" data-autosubmit aria-label="Payment status"><option value="">Any payment</option><?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $f['payment'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <input type="date" name="from" value="<?= e($f['from']) ?>" aria-label="From date" style="width:auto">
    <input type="date" name="to" value="<?= e($f['to']) ?>" aria-label="To date" style="width:auto">
    <select name="sort" data-autosubmit aria-label="Sort"><option value="newest">Newest first</option><option value="oldest" <?= $f['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option><option value="total_hi" <?= $f['sort'] === 'total_hi' ? 'selected' : '' ?>>Highest total</option><option value="total_lo" <?= $f['sort'] === 'total_lo' ? 'selected' : '' ?>>Lowest total</option></select>
    <button class="btn btn--ghost btn--sm"><?= admin_icon('filter') ?>Apply</button>
    <?php if ($f['q'] !== '' || $f['payment'] !== '' || $f['from'] !== '' || $f['to'] !== ''): ?><a class="btn btn--ghost btn--sm" href="<?= e($qs(['q' => '', 'payment' => '', 'from' => '', 'to' => ''])) ?: '?' ?>"><?= admin_icon('x') ?>Clear</a><?php endif; ?>
  </form>

  <form method="post">
    <?= csrf_field() ?>
    <div class="bulkbar" data-bulkbar>
      <span><b data-bulk-count>0</b> selected — mark as</span>
      <select name="bulk_status" class="input" style="width:auto;min-height:36px" aria-label="New status">
        <option value="processing">Processing</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option>
        <option value="cancelled">Cancelled</option><option value="paid">Paid (payment)</option>
      </select>
      <button class="btn btn--primary btn--sm">Update</button>
    </div>
    <?php if (!$orders): ?>
      <div class="empty"><?= admin_icon('cart') ?><h3>No orders here</h3><p><?= $allCount ? 'Try another tab or clear the filters.' : 'Orders appear here as soon as customers check out.' ?></p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table" data-sortable>
        <thead><tr><th style="width:40px"><input type="checkbox" data-check-all aria-label="Select all"></th><th data-sort="text">Order</th><th data-sort="text">Customer</th><th data-sort="text">Delivery</th><th class="num" data-sort="num">Items</th><th>Status</th><th>Payment</th><th class="num" data-sort="num">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int) $o['id'] ?>" data-check aria-label="Select <?= e($o['order_number']) ?>"></td>
            <td data-v="<?= e($o['created_at']) ?>"><a href="<?= e(asset_url('admin/order-details.php?id=' . (int) $o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a><br><small class="muted" title="<?= e(format_date($o['created_at'], 'd M Y, g:i a')) ?>"><?= e(time_ago($o['created_at'])) ?></small></td>
            <td><div class="prod-cell" style="min-width:180px"><span class="avatar"><?= e(initials($o['customer_name'])) ?></span><div><strong><?= e($o['customer_name']) ?></strong><small><?= e($o['customer_email']) ?></small></div></div></td>
            <td><?= e(trim(($o['city'] ?? '') . ', ' . ($o['state'] ?? ''), ', ')) ?></td>
            <td class="num"><?= (int) $o['items'] ?></td>
            <td><?= status_pill($o['order_status']) ?></td>
            <td><?= status_pill($o['payment_status']) ?><br><small class="muted"><?= e($payLabels[$o['payment_method']] ?? $o['payment_method']) ?></small></td>
            <td class="num" data-v="<?= (float) $o['total'] ?>"><strong><?= format_price($o['total']) ?></strong></td>
            <td class="actions"><div class="row-actions"><a href="<?= e(asset_url('admin/order-details.php?id=' . (int) $o['id'])) ?>" aria-label="Open order" title="Open"><?= admin_icon('eye') ?></a></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </form>
  <div class="pager">
    <span>Showing <?= $total ? (($page - 1) * $perPage + 1) . '–' . min($total, $page * $perPage) : 0 ?> of <?= $total ?></span>
    <?php if ($pages > 1): ?><nav class="pager__links" aria-label="Pages">
      <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><?php if ($i === $page): ?><span class="is-active" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?>
    </nav><?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/admin-footer.php';
