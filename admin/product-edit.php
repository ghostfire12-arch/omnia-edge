<?php
require_once __DIR__ . '/includes/bootstrap.php';
$q = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$q->execute([(int) ($_GET['id'] ?? 0)]);
$product = $q->fetch();
if (!$product) { set_flash('error', 'That product no longer exists.'); redirect(asset_url('admin/products.php')); }
$pageTitle = 'Edit ' . $product['name'] . ' | ' . SITE_NAME;
$activePage = 'products';
ob_start();
require __DIR__ . '/includes/product-form.php';   // may redirect after a successful save
$body = ob_get_clean();
include __DIR__ . '/includes/admin-header.php';
echo $body;
include __DIR__ . '/includes/admin-footer.php';
