<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Shared helper functions.
 *
 * Include after db.php. Covers: settings, formatting, sanitization,
 * CSRF tokens, flash messages, slugs, image helpers, pagination.
 */

require_once __DIR__ . '/db.php';

// =====================================================================
// SETTINGS
// =====================================================================

/**
 * Fetch all store settings as an associative array, cached per request.
 */
function get_settings(): array
{
    static $settings = null;

    if ($settings === null) {
        global $pdo;
        $settings = [];
        try {
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log('Settings load error: ' . $e->getMessage());
        }
    }

    return $settings;
}

/**
 * Get one setting value, with an optional fallback default.
 */
function get_setting(string $key, string $default = ''): string
{
    $settings = get_settings();
    return $settings[$key] ?? $default;
}

// =====================================================================
// WISHLIST
// =====================================================================

function wishlist_count(PDO $pdo): int
{
    if (empty($_SESSION['user_id'])) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = :user_id');
    $stmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

// =====================================================================
// FORMATTING
// =====================================================================

/**
 * Format a number as Naira currency, e.g. 85000 -> "₦85,000".
 */
function format_price($amount): string
{
    $symbol = get_setting('currency_symbol', '₦');
    $amount = (float) $amount;
    $hasFraction = abs($amount - round($amount)) > 0.001;
    return $symbol . number_format($amount, $hasFraction ? 2 : 0);
}

/**
 * Human readable date, e.g. "18 Jun 2026".
 */
function format_date($datetime, string $format = 'd M Y'): string
{
    if (empty($datetime)) {
        return '';
    }
    $ts = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    return $ts ? date($format, $ts) : '';
}

// =====================================================================
// SANITIZATION / OUTPUT ESCAPING
// =====================================================================

/**
 * Escape a string for safe HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Build a project-aware URL when the application is installed in a subfolder. */
function asset_url(string $path): string
{
    return BASE_URL_PATH . '/' . ltrim($path, '/');
}

/**
 * Trim and strip tags from user input (does NOT escape for output —
 * use e() at output time; prepared statements handle SQL safety).
 */
function clean_input(?string $value): string
{
    return trim(strip_tags($value ?? ''));
}

// =====================================================================
// CSRF PROTECTION
// =====================================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return !empty($submitted) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Call at the top of any POST handler. Kills the request with a
 * friendly message if the CSRF token is missing or invalid.
 */
function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
        http_response_code(400);
        die('Your session has expired. Please go back and try again.');
    }
}

// =====================================================================
// FLASH MESSAGES (one-time notifications shown after redirect)
// =====================================================================

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// =====================================================================
// REDIRECTS
// =====================================================================

function redirect(string $path): void
{
    // Keep redirects inside this project when it is installed at
    // http://localhost/omnia-edge instead of the server document root.
    if (BASE_URL_PATH !== '' && str_starts_with($path, '/') && !str_starts_with($path, BASE_URL_PATH . '/')) {
        $path = BASE_URL_PATH . $path;
    }
    header('Location: ' . $path);
    exit;
}

// =====================================================================
// SLUGS
// =====================================================================

function make_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . substr(md5(uniqid('', true)), 0, 8);
}

/**
 * Generate a slug for a table/column that is guaranteed unique,
 * appending -2, -3, etc. if needed. $ignoreId excludes a row (for edits).
 */
function unique_slug(PDO $pdo, string $table, string $baseText, int $ignoreId = 0): string
{
    $base = make_slug($baseText);
    $slug = $base;
    $i = 2;

    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = :slug";
        if ($ignoreId > 0) {
            $sql .= ' AND id != :ignore_id';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        if ($ignoreId > 0) {
            $stmt->bindValue(':ignore_id', $ignoreId, PDO::PARAM_INT);
        }
        $stmt->execute();

        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

// =====================================================================
// ORDER NUMBERS
// =====================================================================

function generate_order_number(): string
{
    return 'OE-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

// =====================================================================
// IMAGE HELPERS
// =====================================================================

/**
 * Return a usable <img> src for a product image filename, falling
 * back to a placeholder if the file is missing/empty.
 */
function product_image_url(?string $filename): string
{
    if (!empty($filename) && str_starts_with($filename, 'asset:')) {
        return asset_url('assets/img/' . rawurlencode(substr($filename, 6)));
    }
    if (!empty($filename) && file_exists(UPLOAD_PATH . $filename)) {
        return UPLOAD_URL . rawurlencode($filename);
    }
    return asset_url('assets/img/placeholder.svg');
}

function category_image_url(?string $filename): string
{
    if (!empty($filename) && str_starts_with($filename, 'asset:')) {
        $path = substr($filename, 6);
        $parts = explode('/', $path);
        $encoded = implode('/', array_map('rawurlencode', $parts));
        return asset_url('assets/img/' . $encoded);
    }
    if (!empty($filename) && file_exists(CATEGORY_UPLOAD_PATH . $filename)) {
        return CATEGORY_UPLOAD_URL . rawurlencode($filename);
    }
    return asset_url('assets/img/placeholder.svg');
}

/**
 * Get the primary (first, lowest sort_order) image for a product id.
 */
function get_primary_product_image(PDO $pdo, int $productId): ?string
{
    $stmt = $pdo->prepare(
        'SELECT image FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC LIMIT 1'
    );
    $stmt->execute([':pid' => $productId]);
    $row = $stmt->fetch();
    return $row['image'] ?? null;
}

/**
 * Validate and move an uploaded image file into $destDir with a safe,
 * unique filename. Returns the new filename on success, or null on
 * failure (with $error populated).
 */
function handle_image_upload(array $file, string $destDir, ?string &$error = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // nothing uploaded — not necessarily an error
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
        return null;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $error = 'Image is too large. Maximum size is 4MB.';
        return null;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS, true)) {
        $error = 'Invalid file type. Allowed: JPG, JPEG, PNG, WEBP.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_MIME_TYPES, true)) {
        $error = 'Invalid file content. Please upload a genuine image file.';
        return null;
    }

    // Double-check it's really an image (blocks disguised executables).
    if (@getimagesize($file['tmp_name']) === false) {
        $error = 'The uploaded file is not a valid image.';
        return null;
    }

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($destDir, '/') . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Could not save the uploaded image.';
        return null;
    }

    return $safeName;
}

// =====================================================================
// PAGINATION
// =====================================================================

function get_page_number(): int
{
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    return $page > 0 ? $page : 1;
}

function paginate_offset(int $page, int $perPage): int
{
    return ($page - 1) * $perPage;
}

/**
 * Render a simple pagination nav. $baseUrl should already include any
 * existing query string ending in '&' or be plain (no '?').
 */
function render_pagination(int $currentPage, int $totalPages, string $baseUrl): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    $html = '<nav class="pagination" aria-label="Pagination">';

    if ($currentPage > 1) {
        $html .= '<a class="page-link" href="' . e($baseUrl . $separator . 'page=' . ($currentPage - 1)) . '">&laquo; Prev</a>';
    }

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $html .= '<a class="page-link" href="' . e($baseUrl . $separator . 'page=1') . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-ellipsis">&hellip;</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<a class="page-link' . $active . '" href="' . e($baseUrl . $separator . 'page=' . $i) . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="page-ellipsis">&hellip;</span>';
        }
        $html .= '<a class="page-link" href="' . e($baseUrl . $separator . 'page=' . $totalPages) . '">' . $totalPages . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a class="page-link" href="' . e($baseUrl . $separator . 'page=' . ($currentPage + 1)) . '">Next &raquo;</a>';
    }

    $html .= '</nav>';
    return $html;
}

// =====================================================================
// MISC
// =====================================================================

/**
 * Get all active categories, ordered by name.
 */
function get_active_categories(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Compute the effective selling price for a product row (sale price
 * if present and lower than the regular price, else regular price).
 */
function effective_price(array $product): float
{
    if (!empty($product['sale_price']) && (float) $product['sale_price'] > 0 && (float) $product['sale_price'] < (float) $product['price']) {
        return (float) $product['sale_price'];
    }
    return (float) $product['price'];
}

function has_discount(array $product): bool
{
    return !empty($product['sale_price']) && (float) $product['sale_price'] > 0 && (float) $product['sale_price'] < (float) $product['price'];
}

function discount_percent(array $product): int
{
    if (!has_discount($product)) {
        return 0;
    }
    $price = (float) $product['price'];
    $sale = (float) $product['sale_price'];
    if ($price <= 0) {
        return 0;
    }
    return (int) round((($price - $sale) / $price) * 100);
}
