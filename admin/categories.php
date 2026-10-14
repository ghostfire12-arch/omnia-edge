<?php
require_once __DIR__ . '/includes/bootstrap.php';
$error = '';
$old = ['name' => '', 'description' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'create');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE categories SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?")->execute([$id]);
        $n = $pdo->prepare('SELECT name, status FROM categories WHERE id = ?'); $n->execute([$id]); $r = $n->fetch();
        admin_log('category', ($r['name'] ?? 'Category') . ' set to ' . ($r['status'] ?? ''));
        set_flash('success', ($r['name'] ?? 'Category') . ($r && $r['status'] === 'active' ? ' is visible in the store.' : ' is hidden from the store.'));
        redirect(asset_url('admin/categories.php'));
    }
    if ($action === 'delete' && $id) {
        $n = $pdo->prepare('SELECT name, image FROM categories WHERE id = ?'); $n->execute([$id]); $r = $n->fetch();
        if ($r) {
            if ($r['image'] && !str_starts_with($r['image'], 'asset:') && preg_match('/^[A-Za-z0-9._-]+$/', $r['image']) && is_file(CATEGORY_UPLOAD_PATH . $r['image'])) @unlink(CATEGORY_UPLOAD_PATH . $r['image']);
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);  // products become Uncategorised (FK SET NULL)
            admin_log('category', 'Deleted category ' . $r['name']);
            set_flash('success', 'Deleted ' . $r['name'] . '. Its products are now uncategorised.');
        }
        redirect(asset_url('admin/categories.php'));
    }

    // create
    $old = ['name' => clean_input($_POST['name'] ?? ''), 'description' => clean_input($_POST['description'] ?? ''), 'status' => pick((string) ($_POST['status'] ?? 'active'), ['active', 'inactive'], 'active')];
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) $error = 'Enter a category name (up to 100 characters).';
    else {
        $dup = $pdo->prepare('SELECT 1 FROM categories WHERE name = ?'); $dup->execute([$old['name']]);
        if ($dup->fetchColumn()) $error = 'A category called “' . $old['name'] . '” already exists.';
    }
    $image = null;
    if (!$error && !empty($_FILES['image']['name'])) { $image = handle_image_upload($_FILES['image'], CATEGORY_UPLOAD_PATH, $error); }
    if (!$error) {
        $pdo->prepare('INSERT INTO categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$old['name'], unique_slug($pdo, 'categories', $old['name']), $old['description'], $image, $old['status']]);
        admin_log('category', 'Added category ' . $old['name']);
        set_flash('success', 'Added ' . $old['name'] . '.');
        redirect(asset_url('admin/categories.php'));
    }
}

$cats = $pdo->query("SELECT c.*,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') AS live,
        (SELECT COALESCE(SUM(oi.subtotal), 0) FROM order_items oi JOIN products p ON p.id = oi.product_id JOIN orders o ON o.id = oi.order_id
            WHERE p.category_id = c.id AND o.order_status <> 'cancelled') AS revenue
    FROM categories c ORDER BY revenue DESC, c.name")->fetchAll();
$uncat = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE category_id IS NULL')->fetchColumn();
$maxRev = max(1, ...array_map(fn($c) => (float) $c['revenue'], $cats ?: [['revenue' => 1]]));

$pageTitle = 'Categories | ' . SITE_NAME;
$activePage = 'categories';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div><h1>Categories</h1><p><?= count($cats) ?> categories<?= $uncat ? ' · <a href="' . e(asset_url('admin/products.php?cat=-1')) . '">' . $uncat . ' uncategorised product' . ($uncat === 1 ? '' : 's') . '</a>' : '' ?>. Sorted by sales.</p></div>
</div>

<div class="grid grid--2-1">
  <section class="card card--flush">
    <div class="card__head"><div><h2>All categories</h2><p>Revenue bar compares each category with your best seller.</p></div>
      <label class="search-field" style="min-width:220px"><?= admin_icon('search') ?><input class="input" type="search" placeholder="Filter categories" data-filter-cards aria-label="Filter categories"></label></div>
    <div class="cat-grid" style="padding:20px" data-stagger>
      <?php foreach ($cats as $c): ?>
        <article class="cat-card" data-name="<?= e(mb_strtolower($c['name'])) ?>">
          <div class="cat-card__img">
            <?php if ($c['image']): ?><img src="<?= e(category_image_url($c['image'])) ?>" alt="" loading="lazy"><?php else: ?><b><?= e(mb_substr($c['name'], 0, 1)) ?></b><?php endif; ?>
          </div>
          <div class="cat-card__body">
            <div class="cat-card__meta" style="margin:0"><h3><?= e($c['name']) ?></h3>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <label class="switch" title="<?= $c['status'] === 'active' ? 'Visible — click to hide' : 'Hidden — click to show' ?>"><input type="checkbox" data-autosubmit <?= $c['status'] === 'active' ? 'checked' : '' ?> aria-label="Show <?= e($c['name']) ?> in store"><span></span></label></form>
            </div>
            <p class="muted" style="font-size:.82rem;margin-top:4px"><?= (int) $c['products'] ?> product<?= (int) $c['products'] === 1 ? '' : 's' ?> · <?= (int) $c['live'] ?> live</p>
            <div class="bars__track" title="Revenue"><i style="--w: <?= round($c['revenue'] / $maxRev * 100) ?>%"></i></div>
            <div class="cat-card__meta">
              <strong><?= format_price($c['revenue']) ?></strong>
              <div class="row-actions">
                <a href="<?= e(asset_url('admin/products.php?cat=' . (int) $c['id'])) ?>" title="View products" aria-label="View products"><?= admin_icon('box') ?></a>
                <a href="<?= e(asset_url('admin/category-edit.php?id=' . (int) $c['id'])) ?>" title="Edit" aria-label="Edit <?= e($c['name']) ?>"><?= admin_icon('edit') ?></a>
                <form method="post" data-confirm="Delete “<?= e($c['name']) ?>”? Its <?= (int) $c['products'] ?> products will stay but become uncategorised." data-confirm-ok="Delete category"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="is-danger" title="Delete" aria-label="Delete <?= e($c['name']) ?>"><?= admin_icon('trash') ?></button></form>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card sticky-side" style="align-self:start">
    <div class="card__head"><div><h2>New category</h2><p>Appears in the store menu and homepage rail.</p></div></div>
    <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="stack" style="gap:14px">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="field"><label for="cName">Name</label><input id="cName" name="name" value="<?= e($old['name']) ?>" required maxlength="100"></div>
      <div class="field"><label for="cDesc">Description</label><textarea id="cDesc" name="description" style="min-height:90px" maxlength="500"><?= e($old['description']) ?></textarea></div>
      <label class="dropzone" data-dropzone="catThumb"><?= admin_icon('image') ?><strong>Category image</strong><span>Square images look best</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
      <div class="thumbs" id="catThumb"></div>
      <div class="field"><label for="cStatus">Visibility</label><select id="cStatus" name="status"><option value="active">Visible in store</option><option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Hidden</option></select></div>
      <button class="btn btn--primary"><?= admin_icon('plus') ?>Add category</button>
    </form>
  </section>
</div>
<?php
$adminScripts = <<<'JS'
<script>
document.querySelectorAll('[data-filter-cards]').forEach(function (inp) {
  inp.addEventListener('input', function () {
    var q = inp.value.trim().toLowerCase();
    document.querySelectorAll('.cat-card[data-name]').forEach(function (c) { c.style.display = !q || c.getAttribute('data-name').indexOf(q) > -1 ? '' : 'none'; });
  });
});
</script>
JS;
include __DIR__ . '/includes/admin-footer.php';
