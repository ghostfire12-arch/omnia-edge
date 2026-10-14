<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$q = $pdo->prepare('SELECT * FROM categories WHERE id = ?'); $q->execute([$id]); $cat = $q->fetch();
if (!$cat) { set_flash('error', 'That category no longer exists.'); redirect(asset_url('admin/categories.php')); }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = clean_input($_POST['name'] ?? '');
    $desc = clean_input($_POST['description'] ?? '');
    $status = pick((string) ($_POST['status'] ?? 'active'), ['active', 'inactive'], 'active');
    if ($name === '' || mb_strlen($name) > 100) $error = 'Enter a category name (up to 100 characters).';
    else { $d = $pdo->prepare('SELECT 1 FROM categories WHERE name = ? AND id <> ?'); $d->execute([$name, $id]); if ($d->fetchColumn()) $error = 'Another category already has that name.'; }
    $image = $cat['image'];
    if (!$error && !empty($_FILES['image']['name'])) {
        $new = handle_image_upload($_FILES['image'], CATEGORY_UPLOAD_PATH, $error);
        if ($new) {
            if ($image && !str_starts_with($image, 'asset:') && preg_match('/^[A-Za-z0-9._-]+$/', $image) && is_file(CATEGORY_UPLOAD_PATH . $image)) @unlink(CATEGORY_UPLOAD_PATH . $image);
            $image = $new;
        }
    }
    if (!$error && isset($_POST['remove_image'])) $image = null;
    if (!$error) {
        $pdo->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, status = ?, image = ? WHERE id = ?')
            ->execute([$name, unique_slug($pdo, 'categories', $name, $id), $desc, $status, $image, $id]);
        admin_log('category', 'Updated category ' . $name);
        set_flash('success', 'Saved ' . $name . '.');
        redirect(asset_url('admin/categories.php'));
    }
    $cat = array_merge($cat, ['name' => $name, 'description' => $desc, 'status' => $status]);
}

$pageTitle = 'Edit ' . $cat['name'] . ' | ' . SITE_NAME;
$activePage = 'categories';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head"><div><div class="crumbs"><a href="<?= e(asset_url('admin/categories.php')) ?>">Categories</a><span>/</span><span>Edit</span></div><h1><?= e($cat['name']) ?></h1></div></div>
<?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="grid grid--2-1">
  <?= csrf_field() ?>
  <section class="card stack" style="gap:16px">
    <div class="field"><label for="cName">Name</label><input id="cName" name="name" value="<?= e($cat['name']) ?>" required maxlength="100"></div>
    <div class="field"><label for="cDesc">Description</label><textarea id="cDesc" name="description" maxlength="500"><?= e($cat['description']) ?></textarea></div>
    <div class="field"><label for="cStatus">Visibility</label><select id="cStatus" name="status"><option value="active" <?= $cat['status'] === 'active' ? 'selected' : '' ?>>Visible in store</option><option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : '' ?>>Hidden</option></select></div>
    <div class="form-actions"><a class="btn btn--ghost" href="<?= e(asset_url('admin/categories.php')) ?>">Cancel</a><button class="btn btn--primary"><?= admin_icon('check') ?>Save changes</button></div>
  </section>
  <section class="card">
    <div class="card__head"><h2>Image</h2></div>
    <div class="cat-card" style="margin-bottom:14px"><div class="cat-card__img" style="height:170px"><?php if ($cat['image']): ?><img src="<?= e(category_image_url($cat['image'])) ?>" alt="" style="width:150px;height:150px"><?php else: ?><b><?= e(mb_substr($cat['name'], 0, 1)) ?></b><?php endif; ?></div></div>
    <label class="dropzone" data-dropzone="catThumb"><?= admin_icon('image') ?><strong>Replace image</strong><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    <div class="thumbs" id="catThumb"></div>
    <?php if ($cat['image']): ?><label class="check-row mt"><span><strong>Remove image</strong><small>Show the first letter instead</small></span><input type="checkbox" name="remove_image"></label><?php endif; ?>
  </section>
</form>
<?php include __DIR__ . '/includes/admin-footer.php';
