<?php
require_once __DIR__ . '/includes/bootstrap.php';

/* ---------- Filters (whitelisted) ---------- */
$f = [
    'q'      => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
    'cat'    => (int) ($_GET['cat'] ?? 0),
    'status' => pick((string) ($_GET['status'] ?? ''), ['', 'active', 'inactive'], ''),
    'stock'  => pick((string) ($_GET['stock'] ?? ''), ['', 'low', 'out', 'in'], ''),
    'sort'   => pick((string) ($_GET['sort'] ?? 'newest'), ['newest', 'oldest', 'name', 'price_hi', 'price_lo', 'stock_lo', 'stock_hi', 'best'], 'newest'),
    'view'   => pick((string) ($_GET['view'] ?? 'list'), ['list', 'grid'], 'list'),
];
$qs = function (array $over = []) use ($f) {
    $p = array_filter(array_merge($f, $over), fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
$back = asset_url('admin/products.php') . $qs(['page' => (int) ($_GET['page'] ?? 1)]);

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [$_POST['id'] ?? 0]))));
    if (!$ids) { set_flash('error', 'Select at least one product.'); redirect($back); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $n = count($ids);
    $label = $n === 1 ? '1 product' : "$n products";

    switch ($action) {
        case 'toggle_status':
            $pdo->prepare("UPDATE products SET status = IF(status = 'active', 'inactive', 'active') WHERE id IN ($in)")->execute($ids);
            $s = $pdo->prepare('SELECT name, status FROM products WHERE id = ?'); $s->execute([$ids[0]]); $r = $s->fetch();
            admin_log('product', ($r ? $r['name'] : 'Product') . ' set to ' . ($r['status'] ?? ''));
            set_flash('success', ($r ? $r['name'] : 'Product') . ($r && $r['status'] === 'active' ? ' is now live.' : ' is now hidden from the store.'));
            break;
        case 'toggle_featured':
            $pdo->prepare("UPDATE products SET featured = 1 - featured WHERE id IN ($in)")->execute($ids);
            set_flash('success', 'Featured setting updated.');
            break;
        case 'activate': case 'deactivate':
            $pdo->prepare("UPDATE products SET status = ? WHERE id IN ($in)")->execute(array_merge([$action === 'activate' ? 'active' : 'inactive'], $ids));
            admin_log('product', ucfirst($action) . "d $label");
            set_flash('success', ($action === 'activate' ? 'Published ' : 'Hid ') . $label . '.');
            break;
        case 'feature': case 'unfeature':
            $pdo->prepare("UPDATE products SET featured = ? WHERE id IN ($in)")->execute(array_merge([$action === 'feature' ? 1 : 0], $ids));
            set_flash('success', ($action === 'feature' ? 'Featured ' : 'Unfeatured ') . $label . '.');
            break;
        case 'move':
            $cat = (int) ($_POST['category_id'] ?? 0);
            $ok = $pdo->prepare('SELECT name FROM categories WHERE id = ?'); $ok->execute([$cat]); $catName = $ok->fetchColumn();
            if (!$catName) { set_flash('error', 'Choose a category to move the products to.'); break; }
            $pdo->prepare("UPDATE products SET category_id = ? WHERE id IN ($in)")->execute(array_merge([$cat], $ids));
            admin_log('product', "Moved $label to $catName");
            set_flash('success', "Moved $label to $catName.");
            break;
        case 'restock':
            $add = max(0, min(100000, (int) ($_POST['qty'] ?? 0)));
            if (!$add) { set_flash('error', 'Enter how many units to add.'); break; }
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id IN ($in)")->execute(array_merge([$add], $ids));
            admin_log('product', "Added $add units to $label");
            set_flash('success', "Added $add units to $label.");
            break;
        case 'delete':
            foreach ($ids as $id) delete_product_files($pdo, $id);
            $pdo->prepare("DELETE FROM products WHERE id IN ($in)")->execute($ids);
            admin_log('product', "Deleted $label");
            set_flash('success', "Deleted $label. Past orders keep their item details.");
            break;
        default:
            set_flash('error', 'Unknown action.');
    }
    redirect($back);
}

/* ---------- Query ---------- */
$where = []; $params = [];
if ($f['q'] !== '')      { $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)'; $l = '%' . addcslashes($f['q'], '%_\\') . '%'; array_push($params, $l, $l, $l); }
if ($f['cat'] > 0)       { $where[] = 'p.category_id = ?'; $params[] = $f['cat']; }
if ($f['cat'] === -1)    { $where[] = 'p.category_id IS NULL'; }
if ($f['status'] !== '') { $where[] = 'p.status = ?'; $params[] = $f['status']; }
if ($f['stock'] === 'low') { $where[] = 'p.stock_quantity BETWEEN 1 AND ' . LOW_STOCK_LEVEL; }
if ($f['stock'] === 'out') { $where[] = 'p.stock_quantity <= 0'; }
if ($f['stock'] === 'in')  { $where[] = 'p.stock_quantity > ' . LOW_STOCK_LEVEL; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = [
    'newest' => 'p.created_at DESC, p.id DESC', 'oldest' => 'p.created_at ASC, p.id ASC', 'name' => 'p.name ASC',
    'price_hi' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) DESC', 'price_lo' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) ASC',
    'stock_lo' => 'p.stock_quantity ASC', 'stock_hi' => 'p.stock_quantity DESC', 'best' => 'sold DESC',
][$f['sort']];

$c = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql"); $c->execute($params); $total = (int) $c->fetchColumn();
$perPage = 24; $pages = max(1, (int) ceil($total / $perPage)); $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$q = $pdo->prepare("SELECT p.*, c.name AS category_name,
        (SELECT image FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) AS img,
        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.order_status <> 'cancelled') AS sold
    FROM products p LEFT JOIN categories c ON c.id = p.category_id $whereSql ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$q->execute($params);
$products = $q->fetchAll();

$stats = $pdo->query("SELECT COUNT(*) total, SUM(status = 'active') active,
    SUM(stock_quantity BETWEEN 1 AND " . LOW_STOCK_LEVEL . ") low, SUM(stock_quantity <= 0) out_of_stock,
    COALESCE(SUM(stock_quantity * COALESCE(NULLIF(sale_price, 0), price)), 0) value FROM products")->fetch();
$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Products | ' . SITE_NAME;
$activePage = 'products';
include __DIR__ . '/includes/admin-header.php';

function stock_badge(int $s): string {
    $c = $s <= 0 ? 'var(--red)' : ($s <= LOW_STOCK_LEVEL ? 'var(--saffron)' : 'var(--green)');
    return '<span class="stock"><span class="stock__bar"><i style="--w:' . min(100, max(4, $s * 4)) . '%;--c:' . $c . '"></i></span>' . ($s <= 0 ? '<span style="color:var(--red)">Out</span>' : $s) . '</span>';
}
?>
<div class="page-head">
  <div><h1>Products</h1><p>Manage your catalogue, prices and stock.</p></div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(asset_url('admin/export.php')) . e($qs(['type' => 'products', 'view' => ''])) ?>"><?= admin_icon('download') ?>Export CSV</a>
    <a class="btn btn--ghost" href="<?= e(asset_url('admin/import-url.php')) ?>"><?= admin_icon('upload') ?>Import</a>
    <a class="btn btn--primary" href="<?= e(asset_url('admin/product-add.php')) ?>"><?= admin_icon('plus') ?>Add product</a>
  </div>
</div>

<div class="grid grid--kpi" data-stagger style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))">
  <a class="card kpi" href="<?= e(asset_url('admin/products.php')) ?>" style="color:inherit"><span class="kpi__ico tone-blue"><?= admin_icon('box') ?></span><p class="kpi__label">All products</p><p class="kpi__value" data-count="<?= (int) $stats['total'] ?>">0</p></a>
  <a class="card kpi" href="?status=active" style="color:inherit"><span class="kpi__ico tone-green"><?= admin_icon('eye') ?></span><p class="kpi__label">Live in store</p><p class="kpi__value" data-count="<?= (int) $stats['active'] ?>">0</p></a>
  <a class="card kpi" href="?stock=low" style="color:inherit;--glow:rgba(255,178,30,.22)"><span class="kpi__ico tone-amber"><?= admin_icon('alert') ?></span><p class="kpi__label">Low stock (1–<?= LOW_STOCK_LEVEL ?>)</p><p class="kpi__value" data-count="<?= (int) $stats['low'] ?>">0</p></a>
  <a class="card kpi" href="?stock=out" style="color:inherit;--glow:rgba(229,72,77,.2)"><span class="kpi__ico tone-red"><?= admin_icon('x') ?></span><p class="kpi__label">Out of stock</p><p class="kpi__value" data-count="<?= (int) $stats['out_of_stock'] ?>">0</p></a>
  <div class="card kpi" style="--glow:rgba(124,92,255,.2)"><span class="kpi__ico tone-violet"><?= admin_icon('wallet') ?></span><p class="kpi__label">Stock value</p><p class="kpi__value" data-count="<?= round((float) $stats['value']) ?>" data-prefix="₦">0</p></div>
</div>

<section class="card card--flush mt" data-bulk>
  <form class="toolbar" method="get">
    <label class="search-field toolbar__grow"><?= admin_icon('search') ?><span class="sr-only">Search products</span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search name, SKU or brand"></label>
    <select name="cat" data-autosubmit aria-label="Category"><option value="">All categories</option><option value="-1" <?= $f['cat'] === -1 ? 'selected' : '' ?>>Uncategorised</option><?php foreach ($cats as $ct): ?><option value="<?= (int) $ct['id'] ?>" <?= $f['cat'] === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option><?php endforeach; ?></select>
    <select name="status" data-autosubmit aria-label="Status"><option value="">Any status</option><option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Live</option><option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>>Hidden</option></select>
    <select name="stock" data-autosubmit aria-label="Stock"><option value="">Any stock</option><option value="in" <?= $f['stock'] === 'in' ? 'selected' : '' ?>>In stock</option><option value="low" <?= $f['stock'] === 'low' ? 'selected' : '' ?>>Low stock</option><option value="out" <?= $f['stock'] === 'out' ? 'selected' : '' ?>>Out of stock</option></select>
    <select name="sort" data-autosubmit aria-label="Sort">
      <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'name' => 'Name A–Z', 'best' => 'Best selling', 'price_hi' => 'Price: high to low', 'price_lo' => 'Price: low to high', 'stock_lo' => 'Stock: lowest first', 'stock_hi' => 'Stock: highest first'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="view" value="<?= e($f['view']) ?>">
    <nav class="seg" aria-label="Layout">
      <a href="<?= e($qs(['view' => 'list'])) ?: '?' ?>" class="<?= $f['view'] === 'list' ? 'is-active' : '' ?>" aria-label="List view"><?= admin_icon('list') ?></a>
      <a href="<?= e($qs(['view' => 'grid'])) ?>" class="<?= $f['view'] === 'grid' ? 'is-active' : '' ?>" aria-label="Grid view"><?= admin_icon('grid') ?></a>
    </nav>
    <?php if ($f['q'] !== '' || $f['cat'] || $f['status'] !== '' || $f['stock'] !== ''): ?><a class="btn btn--ghost btn--sm" href="?view=<?= e($f['view']) ?>"><?= admin_icon('x') ?>Clear</a><?php endif; ?>
  </form>

  <form method="post" id="bulkForm" data-confirm-when="delete">
    <?= csrf_field() ?>
    <div class="bulkbar" data-bulkbar>
      <span><b data-bulk-count>0</b> selected</span>
      <select name="action" class="input" style="width:auto;min-height:36px" aria-label="Bulk action" data-bulk-action>
        <option value="activate">Publish</option><option value="deactivate">Hide</option>
        <option value="feature">Mark featured</option><option value="unfeature">Remove featured</option>
        <option value="move">Move to category…</option><option value="restock">Add stock…</option>
        <option value="delete">Delete</option>
      </select>
      <select name="category_id" class="input" style="width:auto;min-height:36px" data-bulk-extra="move" hidden aria-label="Target category"><?php foreach ($cats as $ct): ?><option value="<?= (int) $ct['id'] ?>"><?= e($ct['name']) ?></option><?php endforeach; ?></select>
      <input type="number" name="qty" min="1" max="100000" placeholder="Units" class="input" style="width:110px;min-height:36px" data-bulk-extra="restock" hidden aria-label="Units to add">
      <button class="btn btn--primary btn--sm" type="submit">Apply</button>
    </div>

    <?php if (!$products): ?>
      <div class="empty"><?= admin_icon('box') ?><h3>No products found</h3><p>Try a different search or filter, or add a new product.</p></div>
    <?php elseif ($f['view'] === 'grid'): ?>
      <div class="cat-grid" style="padding:20px">
        <?php foreach ($products as $p): $pct = discount_percent($p); ?>
          <article class="cat-card">
            <label style="position:absolute;top:10px;left:10px;z-index:2"><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-check aria-label="Select <?= e($p['name']) ?>"></label>
            <?php if ($pct): ?><span class="pill pill--red" style="position:absolute;top:10px;right:10px;z-index:2">−<?= $pct ?>%</span><?php endif; ?>
            <a href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $p['id'])) ?>" class="preview-card__img" style="display:block"><img src="<?= e(product_image_url($p['img'])) ?>" alt="" loading="lazy"></a>
            <div class="cat-card__body">
              <small class="muted"><?= e($p['category_name'] ?? 'Uncategorised') ?></small>
              <h3 style="font:600 .95rem var(--body);margin-top:2px"><a href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $p['id'])) ?>" style="color:inherit"><?= e($p['name']) ?></a></h3>
              <div class="cat-card__meta"><span><span class="price-now"><?= format_price(effective_price($p)) ?></span></span><?= stock_badge((int) $p['stock_quantity']) ?></div>
              <div class="cat-card__meta"><?= status_pill($p['status']) ?><small class="muted"><?= (int) $p['sold'] ?> sold</small></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table" data-sortable>
          <thead><tr>
            <th style="width:40px"><input type="checkbox" data-check-all aria-label="Select all"></th>
            <th data-sort="text">Product</th><th data-sort="text">Category</th><th class="num" data-sort="num">Price</th>
            <th data-sort="num">Stock</th><th class="num" data-sort="num">Sold</th><th>Featured</th><th>Live</th><th class="actions">Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($products as $p): $pct = discount_percent($p); ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-check aria-label="Select <?= e($p['name']) ?>"></td>
              <td><div class="prod-cell"><img src="<?= e(product_image_url($p['img'])) ?>" alt="" loading="lazy"><div><strong><a href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $p['id'])) ?>" style="color:inherit"><?= e($p['name']) ?></a></strong><small><?= e($p['sku'] ?: 'No SKU') ?><?= $p['brand'] ? ' · ' . e($p['brand']) : '' ?></small></div></div></td>
              <td><?= e($p['category_name'] ?? 'Uncategorised') ?></td>
              <td class="num" data-v="<?= effective_price($p) ?>"><strong><?= format_price(effective_price($p)) ?></strong><?php if ($pct): ?><br><small class="muted" style="text-decoration:line-through"><?= format_price($p['price']) ?></small><?php endif; ?></td>
              <td data-v="<?= (int) $p['stock_quantity'] ?>"><?= stock_badge((int) $p['stock_quantity']) ?></td>
              <td class="num" data-v="<?= (int) $p['sold'] ?>"><?= (int) $p['sold'] ?></td>
              <td><button class="star-btn<?= $p['featured'] ? ' is-on' : '' ?>" form="tf<?= (int) $p['id'] ?>" aria-pressed="<?= $p['featured'] ? 'true' : 'false' ?>" aria-label="Featured" title="<?= $p['featured'] ? 'Featured on the homepage' : 'Feature on the homepage' ?>"><?= admin_icon('star') ?></button></td>
              <td><label class="switch" title="<?= $p['status'] === 'active' ? 'Live — click to hide' : 'Hidden — click to publish' ?>"><input type="checkbox" form="ts<?= (int) $p['id'] ?>" data-autosubmit <?= $p['status'] === 'active' ? 'checked' : '' ?> aria-label="Show <?= e($p['name']) ?> in store"><span></span></label></td>
              <td class="actions"><div class="row-actions">
                <a href="<?= e(asset_url('product.php?id=' . (int) $p['id'])) ?>" target="_blank" rel="noopener" aria-label="View in store" title="View in store"><?= admin_icon('external') ?></a>
                <a href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $p['id'])) ?>" aria-label="Edit" title="Edit"><?= admin_icon('edit') ?></a>
                <button class="is-danger" form="td<?= (int) $p['id'] ?>" aria-label="Delete" title="Delete"><?= admin_icon('trash') ?></button>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </form>

  <?php /* Per-row forms live outside the bulk form (HTML forms cannot be nested). */ foreach ($products as $p): $id = (int) $p['id']; ?>
    <form id="ts<?= $id ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= $id ?>"></form>
    <form id="tf<?= $id ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="toggle_featured"><input type="hidden" name="id" value="<?= $id ?>"></form>
    <form id="td<?= $id ?>" method="post" hidden data-confirm="Delete “<?= e($p['name']) ?>”? Past orders keep their item details." data-confirm-ok="Delete product"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"></form>
  <?php endforeach; ?>

  <div class="pager">
    <span>Showing <?= $total ? (($page - 1) * $perPage + 1) . '–' . min($total, $page * $perPage) : 0 ?> of <?= $total ?></span>
    <?php if ($pages > 1): ?><nav class="pager__links" aria-label="Pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $page): ?><span class="is-active" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?>
    </nav><?php endif; ?>
  </div>
</section>

<?php
$adminScripts = <<<'JS'
<script>
(function () {
  var form = document.getElementById('bulkForm'); if (!form) return;
  var sel = form.querySelector('[data-bulk-action]');
  function sync() { form.querySelectorAll('[data-bulk-extra]').forEach(function (el) { el.hidden = el.getAttribute('data-bulk-extra') !== sel.value; }); }
  sel.addEventListener('change', sync); sync();
  form.addEventListener('submit', function (e) {
    if (sel.value !== 'delete' || form.dataset.confirmed === '1') return;
    e.preventDefault();
    var n = form.querySelectorAll('[data-check]:checked').length;
    OE.confirm('Delete ' + n + ' product' + (n === 1 ? '' : 's') + '? This cannot be undone. Past orders keep their item details.', function () { form.dataset.confirmed = '1'; form.submit(); }, 'Delete');
  });
})();
</script>
JS;
include __DIR__ . '/includes/admin-footer.php';
