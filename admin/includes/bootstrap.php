<?php
/**
 * OMNIA EDGE — Admin bootstrap.
 * Every admin page (except login) starts with:
 *     require_once __DIR__ . '/includes/bootstrap.php';
 * It loads the app, enforces admin login and provides admin-only helpers.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin_login();

// Never let admin pages be cached by the browser or a proxy.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('X-Robots-Tag: noindex, nofollow');
}

const ORDER_STATUSES   = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];
const LOW_STOCK_LEVEL  = 5;

/* ---------------------------------------------------------------------
   ICONS — inline SVG (no external icon font, works with the strict CSP)
   --------------------------------------------------------------------- */
function admin_icon(string $name, string $class = ''): string
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'box'       => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'tag'       => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'cart'      => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.5L22 7H6"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>',
        'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'     => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'star'      => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
        'alert'     => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'up'        => '<path d="m18 15-6-6-6 6"/>',
        'down'      => '<path d="m6 9 6 6 6-6"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'printer'   => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'truck'     => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'card'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'refresh'   => '<path d="M21 2v6h-6M3 22v-6h6"/><path d="M3.5 9a9 9 0 0 1 15-3.4L21 8M3 16l2.5 2.4A9 9 0 0 0 20.5 15"/>',
        'external'  => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>',
        'wallet'    => '<path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'activity'  => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.7-4 3-9 3s-9-1.3-9-3M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/>',
        'store'     => '<path d="M3 9 4.5 4h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6"/>',
        'reply'     => '<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
        'filter'    => '<path d="M22 3H2l8 9.5V19l4 2v-8.5Z"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
        'pin'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/>',
        'command'   => '<path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3Z"/>',
    ];
    $p = $paths[$name] ?? $paths['box'];
    return '<svg class="ico ' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/* ---------------------------------------------------------------------
   STATUS PILLS
   --------------------------------------------------------------------- */
function status_pill(string $status): string
{
    $tone = [
        'pending' => 'amber', 'processing' => 'blue', 'shipped' => 'violet', 'delivered' => 'green',
        'cancelled' => 'red', 'paid' => 'green', 'failed' => 'red', 'refunded' => 'grey',
        'active' => 'green', 'inactive' => 'grey', 'blocked' => 'red',
    ][$status] ?? 'grey';
    return '<span class="pill pill--' . $tone . '"><i></i>' . e(ucfirst($status)) . '</span>';
}

/* ---------------------------------------------------------------------
   ACTIVITY LOG — who changed what, shown on the dashboard
   --------------------------------------------------------------------- */
function admin_activity_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_activity` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id` INT UNSIGNED DEFAULT NULL,
        `admin_name` VARCHAR(100) NOT NULL DEFAULT '',
        `action` VARCHAR(40) NOT NULL,
        `detail` VARCHAR(255) NOT NULL DEFAULT '',
        `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_activity_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function admin_log(string $action, string $detail = ''): void
{
    global $pdo;
    try {
        admin_activity_table($pdo);
        $pdo->prepare('INSERT INTO admin_activity (admin_id, admin_name, action, detail, ip_address) VALUES (:a, :n, :ac, :d, :ip)')
            ->execute([':a' => current_admin_id(), ':n' => mb_substr($_SESSION['admin_name'] ?? '', 0, 100), ':ac' => mb_substr($action, 0, 40),
                       ':d' => mb_substr($detail, 0, 255), ':ip' => client_ip()]);
    } catch (Throwable $e) {
        error_log('admin_log: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------------------
   BADGE COUNTS for the sidebar / notification bell (cached per request)
   --------------------------------------------------------------------- */
function admin_counts(PDO $pdo): array
{
    static $c = null;
    if ($c !== null) return $c;
    $c = [
        'pending'   => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn(),
        'unread'    => (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn(),
        'low_stock' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= " . LOW_STOCK_LEVEL)->fetchColumn(),
    ];
    return $c;
}

/* ---------------------------------------------------------------------
   MISC
   --------------------------------------------------------------------- */
/** Prevents CSV/Excel formula injection (=, +, -, @, tab, CR at the start of a cell). */
function csv_safe($v): string
{
    $v = (string) $v;
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}

function time_ago(string $datetime): string
{
    $d = time() - strtotime($datetime);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 604800) return floor($d / 86400) . ' d ago';
    return format_date($datetime);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $i = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    return $i !== '' ? $i : '?';
}

/** Only allow a POSTed value from a fixed list. */
function pick(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

/** Delete a product's uploaded image files (never touches assets/). */
function delete_product_files(PDO $pdo, int $productId): void
{
    $q = $pdo->prepare('SELECT image FROM product_images WHERE product_id = :id');
    $q->execute([':id' => $productId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $f) {
        if (!str_starts_with($f, 'asset:') && preg_match('/^[A-Za-z0-9._-]+$/', $f) && is_file(UPLOAD_PATH . $f)) {
            @unlink(UPLOAD_PATH . $f);
        }
    }
}
