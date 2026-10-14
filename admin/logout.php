<?php
/** Sign out — POST only, CSRF-protected, so other sites cannot log the admin out. */
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(asset_url(is_admin_logged_in() ? 'admin/dashboard.php' : 'admin/login.php'));
}
require_csrf();
admin_logout();
set_flash('success', 'You have been signed out.');
redirect(asset_url('admin/login.php'));
