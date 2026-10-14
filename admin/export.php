<?php
/**
 * CSV exports (admin only). Every cell goes through csv_safe() so values
 * such as "=HYPERLINK(...)" cannot run as formulas in Excel / Sheets.
 *   ?type=products   (accepts the same filters as products.php)
 *   ?type=orders     (accepts the same filters as orders.php)
 *   ?type=customers
 *   ?type=sales&from=YYYY-MM-DD&to=YYYY-MM-DD
 */
require_once __DIR__ . '/includes/bootstrap.php';
$type = pick((string) ($_GET['type'] ?? ''), ['products', 'orders', 'customers', 'sales'], '');
if ($type === '') { http_response_code(400); exit('Unknown export.'); }

$rows = []; $head = [];
$date = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$k] ?? '')) ? $_GET[$k] : '';

if ($type === 'products') {
    $where = []; $p = [];
    if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') { $l = '%' . addcslashes($q, '%_\\') . '%'; $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)'; array_push($p, $l, $l, $l); }
    $cat = (int) ($_GET['cat'] ?? 0);
    if ($cat > 0) { $where[] = 'p.category_id = ?'; $p[] = $cat; } elseif ($cat === -1) { $where[] = 'p.category_id IS NULL'; }
    if (in_array($_GET['status'] ?? '', ['active', 'inactive'], true)) { $where[] = 'p.status = ?'; $p[] = $_GET['status']; }
    if (($_GET['stock'] ?? '') === 'low') $where[] = 'p.stock_quantity BETWEEN 1 AND ' . LOW_STOCK_LEVEL;
    if (($_GET['stock'] ?? '') === 'out') $where[] = 'p.stock_quantity <= 0';
    $s = $pdo->prepare('SELECT p.id, p.name, p.sku, p.brand, c.name category, p.price, p.sale_price, p.stock_quantity, p.status, p.featured, p.created_at
        FROM products p LEFT JOIN categories c ON c.id = p.category_id ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.name');
    $s->execute($p); $rows = $s->fetchAll(PDO::FETCH_NUM);
    $head = ['ID', 'Name', 'SKU', 'Brand', 'Category', 'Price', 'Sale price', 'Stock', 'Status', 'Featured', 'Created'];
}
if ($type === 'orders') {
    $where = []; $p = [];
    if (in_array($_GET['status'] ?? '', ORDER_STATUSES, true)) { $where[] = 'order_status = ?'; $p[] = $_GET['status']; }
    if (in_array($_GET['payment'] ?? '', PAYMENT_STATUSES, true)) { $where[] = 'payment_status = ?'; $p[] = $_GET['payment']; }
    if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') { $l = '%' . addcslashes($q, '%_\\') . '%'; $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)'; array_push($p, $l, $l, $l); }
    if ($date('from')) { $where[] = 'created_at >= ?'; $p[] = $date('from') . ' 00:00:00'; }
    if ($date('to'))   { $where[] = 'created_at <= ?'; $p[] = $date('to') . ' 23:59:59'; }
    $s = $pdo->prepare('SELECT order_number, created_at, customer_name, customer_email, customer_phone, city, state, payment_method, payment_status, order_status, subtotal, shipping_fee, total
        FROM orders ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC');
    $s->execute($p); $rows = $s->fetchAll(PDO::FETCH_NUM);
    $head = ['Order', 'Date', 'Customer', 'Email', 'Phone', 'City', 'State', 'Payment method', 'Payment status', 'Order status', 'Subtotal', 'Delivery', 'Total'];
}
if ($type === 'customers') {
    $rows = $pdo->query("SELECT u.first_name, u.last_name, u.email, u.phone, u.city, u.state, u.status, u.created_at, COUNT(o.id),
        COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total END), 0) FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_NUM);
    $head = ['First name', 'Last name', 'Email', 'Phone', 'City', 'State', 'Status', 'Joined', 'Orders', 'Lifetime value'];
}
if ($type === 'sales') {
    $from = $date('from') ?: date('Y-m-d', strtotime('-29 days')); $to = $date('to') ?: date('Y-m-d');
    $s = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) orders, SUM(order_status = 'cancelled') cancelled,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) revenue FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY d ORDER BY d");
    $s->execute([$from . ' 00:00:00', $to . ' 23:59:59']); $rows = $s->fetchAll(PDO::FETCH_NUM);
    $head = ['Date', 'Orders', 'Cancelled', 'Paid revenue'];
}

admin_log('export', 'Exported ' . $type . ' (' . count($rows) . ' rows)');
$filename = 'omnia-edge-' . $type . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₦ and names correctly
fputcsv($out, $head);
foreach ($rows as $r) fputcsv($out, array_map('csv_safe', $r));
fclose($out);
