<?php
require_once __DIR__ . '/includes/bootstrap.php';
$product = null;
$pageTitle = 'Add product | ' . SITE_NAME;
$activePage = 'products';
ob_start();
require __DIR__ . '/includes/product-form.php';   // may redirect after a successful save
$body = ob_get_clean();
include __DIR__ . '/includes/admin-header.php';
echo $body;
include __DIR__ . '/includes/admin-footer.php';
