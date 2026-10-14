<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Central configuration file.
 *
 * This is the ONLY place database credentials and site-wide constants
 * are defined. Every other file includes this one (via db.php or
 * functions.php) instead of repeating these values.
 */

// -----------------------------------------------------------------
// ENVIRONMENT
// Set to false on the live server so raw errors are never shown
// to customers. Set to true only while actively developing.
// -----------------------------------------------------------------
// Debug output is only ever shown on a local machine, never on the live site.
$__host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
define('APP_DEBUG', PHP_SAPI === 'cli' || in_array($__host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($__host, '.test') || str_ends_with($__host, '.local'));
unset($__host);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// -----------------------------------------------------------------
// DATABASE CREDENTIALS
// Update these four values to match your cPanel / hosting database.
// -----------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'omnia_edge');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// -----------------------------------------------------------------
// SITE URL
// Used to build absolute links (canonical tags, emails, redirects).
// No trailing slash.
// -----------------------------------------------------------------
define('SITE_URL', 'https://omniaedgeltm.com');
define('SITE_NAME', 'Omnia Edge Strategic Services');

// -----------------------------------------------------------------
// FILESYSTEM PATHS
// -----------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));

// Supports both an XAMPP subfolder (such as /omnia-edge) and a production
// domain document root without editing template asset links.
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$projectRoot = realpath(ROOT_PATH);
$basePath = '';
if ($documentRoot && $projectRoot) {
    $documentRoot = str_replace('\\', '/', $documentRoot);
    $projectRoot = str_replace('\\', '/', $projectRoot);
    if (str_starts_with(strtolower($projectRoot), strtolower($documentRoot))) {
        $basePath = substr($projectRoot, strlen($documentRoot));
    }
}
define('BASE_URL_PATH', rtrim($basePath, '/'));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/products/');
define('UPLOAD_URL', BASE_URL_PATH . '/uploads/products/');
define('CATEGORY_UPLOAD_PATH', ROOT_PATH . '/uploads/categories/');
define('CATEGORY_UPLOAD_URL', BASE_URL_PATH . '/uploads/categories/');

// -----------------------------------------------------------------
// UPLOAD RULES (used by admin/product-add.php, product-edit.php,
// categories.php)
// -----------------------------------------------------------------
define('MAX_UPLOAD_SIZE', 4 * 1024 * 1024); // 4MB
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_IMAGE_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// -----------------------------------------------------------------
// SESSION
// Must start before any output. Every page that needs $_SESSION
// includes this config file first, so we start it here in one place.
// -----------------------------------------------------------------
require_once __DIR__ . '/security.php';
send_security_headers();
secure_session_start();

// -----------------------------------------------------------------
// TIMEZONE
// -----------------------------------------------------------------
date_default_timezone_set('Africa/Lagos');
