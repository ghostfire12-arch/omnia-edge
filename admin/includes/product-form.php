<?php
/**
 * Shared product form logic + markup for product-add.php and product-edit.php.
 * Expects: $product (array|null), $pdo. Handles POST itself.
 */
$isEdit = !empty($product['id']);
$id = $isEdit ? (int) $product['id'] : 0;
$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$errors = [];
$val = $product ?: ['name' => '', 'category_id' => '', 'sku' => '', 'brand' => '', 'description' => '', 'price' => '', 'sale_price' => '', 'stock_quantity' => 0, 'featured' => 0, 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $val = [
        'name'           => clean_input($_POST['name'] ?? ''),
        'category_id'    => (int) ($_POST['category_id'] ?? 0) ?: null,
        'sku'            => strtoupper(clean_input($_POST['sku'] ?? '')),
        'brand'          => clean_input($_POST['brand'] ?? ''),
        'description'    => clean_input($_POST['description'] ?? ''),
        'price'          => round((float) ($_POST['price'] ?? 0), 2),
        'sale_price'     => ($_POST['sale_price'] ?? '') === '' ? null : round((float) $_POST['sale_price'], 2),
        'stock_quantity' => max(0, min(1000000, (int) ($_POST['stock_quantity'] ?? 0))),
        'featured'       => isset($_POST['featured']) ? 1 : 0,
        'status'         => pick((string) ($_POST['status'] ?? 'active'), ['active', 'inactive'], 'active'),
    ];
    if ($val['name'] === '' || mb_strlen($val['name']) > 180) $errors[] = 'Enter a product name (up to 180 characters).';
    if ($val['price'] <= 0 || $val['price'] > 100000000) $errors[] = 'Enter a price greater than zero.';
    if ($val['sale_price'] !== null && ($val['sale_price'] <= 0 || $val['sale_price'] >= $val['price'])) $errors[] = 'The sale price must be lower than the regular price (leave it empty for no sale).';
    if ($val['category_id']) { $c = $pdo->prepare('SELECT 1 FROM categories WHERE id = ?'); $c->execute([$val['category_id']]); if (!$c->fetchColumn()) $val['category_id'] = null; }
    if ($val['sku'] !== '') {
        $c = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND id <> ?'); $c->execute([$val['sku'], $id]);
        if ($c->fetchColumn()) $errors[] = 'Another product already uses SKU ' . $val['sku'] . '.';
    }

    // Validate uploads before touching the database.
    $newFiles = []; $uploadErrors = [];
    $files = $_FILES['images'] ?? null;
    if ($files && is_array($files['name'])) {
        foreach ($files['name'] as $i => $n) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (count($newFiles) >= 8) { $uploadErrors[] = 'Only 8 images can be uploaded at once.'; break; }
            $err = null;
            $file = handle_image_upload(['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], UPLOAD_PATH, $err);
            if ($file) $newFiles[] = $file; elseif ($err) $uploadErrors[] = $n . ': ' . $err;
        }
    }
    if ($errors) { foreach ($newFiles as $nf) @unlink(UPLOAD_PATH . $nf); }
    $errors = array_merge($errors, $uploadErrors);

    if (!$errors) {
        $pdo->beginTransaction();
        $params = [':cat' => $val['category_id'], ':name' => $val['name'], ':slug' => unique_slug($pdo, 'products', $val['name'], $id),
            ':sku' => $val['sku'] ?: null, ':brand' => $val['brand'], ':description' => $val['description'], ':price' => $val['price'],
            ':sale' => $val['sale_price'], ':stock' => $val['stock_quantity'], ':featured' => $val['featured'], ':status' => $val['status']];
        if ($isEdit) {
            $pdo->prepare('UPDATE products SET category_id=:cat, name=:name, slug=:slug, sku=:sku, brand=:brand, description=:description, price=:price,
                sale_price=:sale, stock_quantity=:stock, featured=:featured, status=:status WHERE id=:id')->execute($params + [':id' => $id]);
            // Remove ticked images
            foreach ((array) ($_POST['remove_image'] ?? []) as $imgId) {
                $im = $pdo->prepare('SELECT image FROM product_images WHERE id = ? AND product_id = ?'); $im->execute([(int) $imgId, $id]);
                if ($f = $im->fetchColumn()) {
                    if (!str_starts_with($f, 'asset:') && preg_match('/^[A-Za-z0-9._-]+$/', $f) && is_file(UPLOAD_PATH . $f)) @unlink(UPLOAD_PATH . $f);
                    $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([(int) $imgId]);
                }
            }
        } else {
            $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, brand, description, price, sale_price, stock_quantity, featured, status)
                VALUES (:cat, :name, :slug, :sku, :brand, :description, :price, :sale, :stock, :featured, :status)')->execute($params);
            $id = (int) $pdo->lastInsertId();
        }
        $max = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?'); $max->execute([$id]); $sort = (int) $max->fetchColumn();
        foreach ($newFiles as $nf) $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)')->execute([$id, $nf, ++$sort]);
        // Main image
        if (!empty($_POST['primary_image'])) {
            $pid = (int) $_POST['primary_image'];
            $pdo->prepare('UPDATE product_images SET sort_order = sort_order + 1 WHERE product_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE product_images SET sort_order = 0 WHERE id = ? AND product_id = ?')->execute([$pid, $id]);
        }
        $pdo->commit();
        admin_log('product', ($isEdit ? 'Updated ' : 'Added ') . $val['name']);
        set_flash('success', ($isEdit ? 'Saved changes to ' : 'Added ') . $val['name'] . '.');
        redirect(asset_url(isset($_POST['save_new']) ? 'admin/product-add.php' : 'admin/product-edit.php?id=' . $id));
    }
}

$images = [];
if ($isEdit) { $im = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id'); $im->execute([$id]); $images = $im->fetchAll(); }
$sold = 0; $revenue = 0;
if ($isEdit) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity),0), COALESCE(SUM(oi.subtotal),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.order_status <> 'cancelled'");
    $s->execute([$id]); [$sold, $revenue] = $s->fetch(PDO::FETCH_NUM);
}
$catName = '';
foreach ($cats as $c) if ((int) $c['id'] === (int) $val['category_id']) $catName = $c['name'];
$previewImg = $images ? product_image_url($images[0]['image']) : asset_url('assets/img/placeholder.svg');
?>
<div class="page-head">
  <div>
    <div class="crumbs"><a href="<?= e(asset_url('admin/products.php')) ?>">Products</a><span>/</span><span><?= $isEdit ? 'Edit' : 'New' ?></span></div>
    <h1><?= $isEdit ? e($val['name']) : 'Add a product' ?></h1>
  </div>
  <?php if ($isEdit): ?><div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(asset_url('product.php?id=' . $id)) ?>" target="_blank" rel="noopener"><?= admin_icon('external') ?>View in store</a>
  </div><?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert alert--error" role="alert"><strong>Please fix the following:</strong><ul style="margin:6px 0 0 18px;padding:0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid grid--2-1" id="productForm" novalidate>
  <?= csrf_field() ?>
  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Details</h2></div>
      <div class="form-grid">
        <div class="field span-2"><label for="pName">Product name</label><input id="pName" name="name" value="<?= e($val['name']) ?>" required maxlength="180" data-preview="name"></div>
        <div class="field"><label for="pCat">Category</label><select id="pCat" name="category_id" data-preview="cat"><option value="">Uncategorised</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $val['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="pBrand">Brand</label><input id="pBrand" name="brand" value="<?= e($val['brand']) ?>" maxlength="100"></div>
        <div class="field span-2"><label for="pDesc">Description</label><textarea id="pDesc" name="description" maxlength="5000" data-counter><?= e($val['description']) ?></textarea><small><span data-count-out>0</span> characters. Mention size, colour, what is in the box and warranty.</small></div>
      </div>
    </section>

    <section class="card">
      <div class="card__head"><div><h2>Images</h2><p>JPG, PNG or WEBP up to 4MB each. The first image is the main one.</p></div></div>
      <?php if ($images): ?>
        <div class="thumbs">
          <?php foreach ($images as $i => $im): ?>
            <div class="thumb">
              <img src="<?= e(product_image_url($im['image'])) ?>" alt="">
              <?php if ($i === 0): ?><b>Main</b><?php endif; ?>
              <label style="inset:auto 6px 34px 6px;background:rgba(47,111,237,.85)"><input type="radio" name="primary_image" value="<?= (int) $im['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>> Main</label>
              <label><input type="checkbox" name="remove_image[]" value="<?= (int) $im['id'] ?>"> Remove</label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <label class="dropzone mt" data-dropzone="newThumbs">
        <?= admin_icon('image') ?><strong>Drop images here or click to browse</strong><span>You can select several at once</span>
        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
      </label>
      <div class="thumbs" id="newThumbs"></div>
    </section>

    <section class="card">
      <div class="card__head"><h2>Pricing &amp; inventory</h2></div>
      <div class="form-grid">
        <div class="field"><label for="pPrice">Price</label><div class="prefix"><span>₦</span><input id="pPrice" type="number" name="price" step="0.01" min="0" value="<?= e((string) $val['price']) ?>" required data-preview="price"></div></div>
        <div class="field"><label for="pSale">Sale price <span class="muted">(optional)</span></label><div class="prefix"><span>₦</span><input id="pSale" type="number" name="sale_price" step="0.01" min="0" value="<?= e((string) ($val['sale_price'] ?? '')) ?>" data-preview="sale"></div><small data-discount></small></div>
        <div class="field"><label for="pStock">Units in stock</label><input id="pStock" type="number" name="stock_quantity" min="0" value="<?= (int) $val['stock_quantity'] ?>" data-preview="stock"></div>
        <div class="field"><label for="pSku">SKU</label><div style="display:flex;gap:8px"><input id="pSku" name="sku" value="<?= e($val['sku'] ?? '') ?>" maxlength="60" style="text-transform:uppercase"><button type="button" class="btn btn--ghost btn--sm" data-gen-sku title="Generate a SKU"><?= admin_icon('refresh') ?></button></div></div>
      </div>
    </section>
  </div>

  <div>
    <div class="stack sticky-side">
      <section class="card">
        <div class="card__head"><h2>Visibility</h2></div>
        <div class="stack" style="gap:10px">
          <label class="check-row"><span><strong>Live in store</strong><small>Customers can find and buy it</small></span><span class="switch"><input type="checkbox" name="status" value="active" <?= $val['status'] === 'active' ? 'checked' : '' ?>><span></span></span></label>
          <label class="check-row"><span><strong>Featured</strong><small>Shown first on the homepage</small></span><span class="switch"><input type="checkbox" name="featured" <?= $val['featured'] ? 'checked' : '' ?>><span></span></span></label>
        </div>
        <input type="hidden" name="status" value="inactive" data-status-fallback>
      </section>

      <section class="card">
        <div class="card__head"><div><h2>Store preview</h2><p>Updates as you type</p></div></div>
        <div class="preview-card">
          <div class="preview-card__img"><img src="<?= e($previewImg) ?>" alt="" data-pv-img></div>
          <div class="preview-card__body">
            <small data-pv-cat><?= e($catName ?: 'Uncategorised') ?></small>
            <h3 data-pv-name><?= e($val['name'] ?: 'Product name') ?></h3>
            <div><span class="price-now" data-pv-now></span><span class="price-was" data-pv-was></span></div>
            <small class="muted" data-pv-stock style="color:var(--muted)"></small>
          </div>
        </div>
      </section>

      <?php if ($isEdit): ?>
      <section class="card">
        <div class="card__head"><h2>Performance</h2></div>
        <div class="mini-stats" style="margin:0;grid-template-columns:1fr 1fr">
          <div><strong data-count="<?= (int) $sold ?>">0</strong><span>Units sold</span></div>
          <div><strong data-count="<?= round($revenue) ?>" data-prefix="₦">0</strong><span>Revenue</span></div>
        </div>
      </section>
      <?php endif; ?>

      <div class="form-actions" style="justify-content:stretch">
        <button class="btn btn--primary" type="submit" style="flex:1"><?= admin_icon('check') ?><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
        <?php if (!$isEdit): ?><button class="btn btn--ghost" type="submit" name="save_new" value="1">Save &amp; add another</button><?php endif; ?>
      </div>
      <?php if ($isEdit): ?>
        <button class="btn btn--ghost" type="submit" form="delForm" style="color:var(--red);width:100%"><?= admin_icon('trash') ?>Delete product</button>
      <?php endif; ?>
    </div>
  </div>
</form>
<?php if ($isEdit): ?>
<form id="delForm" method="post" action="<?= e(asset_url('admin/products.php')) ?>" hidden data-confirm="Delete “<?= e($val['name']) ?>”? Past orders keep their item details." data-confirm-ok="Delete product">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
</form>
<?php endif; ?>
<?php
$adminScripts = <<<'JS'
<script>
(function () {
  var f = document.getElementById('productForm'); if (!f) return;
  var $ = function (s) { return f.querySelector(s); };
  var fmt = function (v) { return '₦' + Number(v || 0).toLocaleString('en-NG', { maximumFractionDigits: 2 }); };
  function update() {
    var name = $('[data-preview=name]').value.trim(), cat = $('[data-preview=cat]');
    var price = parseFloat($('[data-preview=price]').value) || 0, sale = parseFloat($('[data-preview=sale]').value) || 0, stock = parseInt($('[data-preview=stock]').value, 10) || 0;
    f.querySelector('[data-pv-name]').textContent = name || 'Product name';
    f.querySelector('[data-pv-cat]').textContent = cat.options[cat.selectedIndex].text;
    var onSale = sale > 0 && sale < price;
    f.querySelector('[data-pv-now]').textContent = fmt(onSale ? sale : price);
    f.querySelector('[data-pv-was]').textContent = onSale ? fmt(price) : '';
    f.querySelector('[data-pv-stock]').textContent = stock <= 0 ? 'Out of stock' : (stock <= 5 ? 'Only ' + stock + ' left' : 'In stock');
    var d = f.querySelector('[data-discount]');
    d.textContent = onSale ? Math.round((1 - sale / price) * 100) + '% off — customers save ' + fmt(price - sale) : (sale >= price && sale > 0 ? 'Must be lower than the price' : '');
    d.style.color = sale >= price && sale > 0 ? 'var(--red)' : 'var(--green)';
    var t = f.querySelector('[data-counter]'); f.querySelector('[data-count-out]').textContent = t.value.length;
  }
  f.addEventListener('input', update); f.addEventListener('change', update); update();
  document.addEventListener('oe:first-image', function (e) { f.querySelector('[data-pv-img]').src = e.detail; });
  f.querySelector('[data-gen-sku]').addEventListener('click', function () {
    var cat = $('[data-preview=cat]'), c = (cat.value ? cat.options[cat.selectedIndex].text : 'GEN').replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase();
    var n = ($('[data-preview=name]').value.replace(/[^A-Za-z]/g, '').slice(0, 2) || 'XX').toUpperCase();
    var r = Math.random().toString(36).slice(2, 6).toUpperCase();
    f.querySelector('#pSku').value = c + '-' + n + '-' + r;
  });
  // The visible switch sends status=active when on; the hidden fallback (placed after) must not override it.
  var sw = f.querySelector('input[name=status][type=checkbox]'), fb = f.querySelector('[data-status-fallback]');
  function syncStatus() { fb.disabled = sw.checked; } sw.addEventListener('change', syncStatus); syncStatus();
})();
</script>
JS;
