<?php
require_once __DIR__ . '/includes/auth.php';
customer_logout();
set_flash('success', 'You have been signed out.');
redirect(asset_url('index.php'));
