<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $q = $pdo->prepare('SELECT first_name, last_name, status FROM users WHERE id = ?'); $q->execute([$id]); $u = $q->fetch();
    if ($u) {
        $new = $u['status'] === 'active' ? 'blocked' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
        $name = $u['first_name'] . ' ' . $u['last_name'];
        admin_log('customer', ($new === 'blocked' ? 'Blocked ' : 'Unblocked ') . $name);
        set_flash('success', $new === 'blocked' ? "$name can no longer sign in." : "$name can sign in again.");
    }
    redirect(asset_url('admin/customers.php') . (isset($_GET['q']) ? '?' . http_build_query(['q' => (string) $_GET['q']]) : ''));
}

$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$sort = pick((string) ($_GET['sort'] ?? 'spent'), ['spent', 'orders', 'newest', 'name', 'recent'], 'spent');
$where = ''; $params = [];
if ($search !== '') { $l = '%' . addcslashes($search, '%_\\') . '%'; $where = "WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.state LIKE ?"; $params = [$l, $l, $l, $l]; }
$order = ['spent' => 'spent DESC', 'orders' => 'orders DESC', 'newest' => 'u.created_at DESC', 'name' => 'u.first_name, u.last_name', 'recent' => 'last_order DESC'][$sort];
$q = $pdo->prepare("SELECT u.*, COUNT(o.id) orders, COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total END), 0) spent, MAX(o.created_at) last_order
    FROM users u LEFT JOIN orders o ON o.user_id = u.id $where GROUP BY u.id ORDER BY $order LIMIT 300");
$q->execute($params);
$users = $q->fetchAll();
$s = $pdo->query("SELECT COUNT(*) total, SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) new30, SUM(status = 'blocked') blocked FROM users")->fetch();
$repeat = (int) $pdo->query('SELECT COUNT(*) FROM (SELECT user_id FROM orders WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) > 1) t')->fetchColumn();
$buyers = (int) $pdo->query('SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL')->fetchColumn();
$top = $users ? max(1, max(array_map(fn($u) => (float) $u['spent'], $users))) : 1;

$pageTitle = 'Customers | ' . SITE_NAME;
$activePage = 'customers';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div><h1>Customers</h1><p>Everyone with an account, ranked by how much they have spent.</p></div>
  <div class="page-head__actions"><a class="btn btn--ghost" href="<?= e(asset_url('admin/export.php?type=customers')) ?>"><?= admin_icon('download') ?>Export CSV</a></div>
</div>

<div class="grid grid--kpi" data-stagger>
  <div class="card kpi"><span class="kpi__ico tone-blue"><?= admin_icon('users') ?></span><p class="kpi__label">Customers</p><p class="kpi__value" data-count="<?= (int) $s['total'] ?>">0</p></div>
  <div class="card kpi" style="--glow:rgba(18,161,80,.2)"><span class="kpi__ico tone-green"><?= admin_icon('plus') ?></span><p class="kpi__label">Joined in the last 30 days</p><p class="kpi__value" data-count="<?= (int) $s['new30'] ?>">0</p></div>
  <div class="card kpi" style="--glow:rgba(124,92,255,.2)"><span class="kpi__ico tone-violet"><?= admin_icon('refresh') ?></span><p class="kpi__label">Repeat buyers</p><p class="kpi__value" data-count="<?= $buyers ? round($repeat / $buyers * 100) : 0 ?>" data-suffix="%">0</p></div>
  <div class="card kpi" style="--glow:rgba(229,72,77,.2)"><span class="kpi__ico tone-red"><?= admin_icon('shield') ?></span><p class="kpi__label">Blocked</p><p class="kpi__value" data-count="<?= (int) $s['blocked'] ?>">0</p></div>
</div>

<section class="card card--flush mt">
  <form class="toolbar" method="get">
    <label class="search-field toolbar__grow"><?= admin_icon('search') ?><span class="sr-only">Search customers</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, phone or state"></label>
    <select name="sort" data-autosubmit aria-label="Sort"><?php foreach (['spent' => 'Top spenders', 'orders' => 'Most orders', 'recent' => 'Recently ordered', 'newest' => 'Newest accounts', 'name' => 'Name A–Z'] as $k => $v): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$users): ?>
    <div class="empty"><?= admin_icon('users') ?><h3><?= $search !== '' ? 'No customers match' : 'No customers yet' ?></h3><p><?= $search !== '' ? 'Try another name or email.' : 'Customers appear here when they create an account.' ?></p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table" data-sortable>
      <thead><tr><th data-sort="text">Customer</th><th data-sort="text">Location</th><th class="num" data-sort="num">Orders</th><th data-sort="num">Lifetime value</th><th data-sort="text">Last order</th><th>Status</th><th class="actions"></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $name = $u['first_name'] . ' ' . $u['last_name']; ?>
        <tr>
          <td><a class="prod-cell" style="color:inherit" href="<?= e(asset_url('admin/customer-view.php?id=' . (int) $u['id'])) ?>"><span class="avatar"><?= e(initials($name)) ?></span><div><strong><?= e($name) ?></strong><small><?= e($u['email']) ?></small></div></a></td>
          <td><?= e(trim(($u['city'] ?? '') . ', ' . ($u['state'] ?? ''), ', ') ?: '—') ?></td>
          <td class="num"><?= (int) $u['orders'] ?></td>
          <td data-v="<?= (float) $u['spent'] ?>" style="min-width:150px"><strong><?= format_price($u['spent']) ?></strong><div class="bars__track"><i style="--w: <?= round($u['spent'] / $top * 100) ?>%"></i></div></td>
          <td data-v="<?= e($u['last_order'] ?? '') ?>"><?= $u['last_order'] ? e(time_ago($u['last_order'])) : '<span class="muted">Never</span>' ?></td>
          <td><?= status_pill($u['status']) ?></td>
          <td class="actions"><div class="row-actions">
            <a href="<?= e(asset_url('admin/customer-view.php?id=' . (int) $u['id'])) ?>" title="View profile" aria-label="View <?= e($name) ?>"><?= admin_icon('eye') ?></a>
            <a href="mailto:<?= e($u['email']) ?>" title="Email" aria-label="Email <?= e($name) ?>"><?= admin_icon('mail') ?></a>
            <form method="post" data-confirm="<?= $u['status'] === 'active' ? 'Block ' . e($name) . '? They will not be able to sign in or place orders with their account.' : 'Unblock ' . e($name) . '?' ?>" data-confirm-ok="<?= $u['status'] === 'active' ? 'Block' : 'Unblock' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="<?= $u['status'] === 'active' ? 'is-danger' : '' ?>" title="<?= $u['status'] === 'active' ? 'Block' : 'Unblock' ?>" aria-label="<?= $u['status'] === 'active' ? 'Block' : 'Unblock' ?> <?= e($name) ?>"><?= admin_icon($u['status'] === 'active' ? 'shield' : 'check') ?></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php';
