<?php
require_once __DIR__ . '/../includes/auth.php';
redirect(asset_url(is_admin_logged_in() ? 'admin/dashboard.php' : 'admin/login.php'));
