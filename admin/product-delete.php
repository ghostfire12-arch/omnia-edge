<?php
// Deleting now happens on the Products page (POST + CSRF). Kept for old links.
require_once __DIR__ . '/includes/bootstrap.php';
redirect(asset_url('admin/products.php'));
