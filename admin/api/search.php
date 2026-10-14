<?php
/**
 * Admin-only JSON search used by the Ctrl+K command palette.
 * GET ?q=term  →  { products: [...], orders: [...], customers: [...] }
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2 || mb_strlen($q) > 80) { echo json_encode(['products' => [], 'orders' => [], 'customers' => []]); exit; }
$like = '%' . addcslashes($q, '%_\\') . '%';

$p = $pdo->prepare("SELECT p.id, p.name, p.sku, p.stock_quantity,
        (SELECT image FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) AS img
    FROM products p WHERE p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ? ORDER BY p.name LIMIT 6");
$p->execute([$like, $like, $like]);
$products = array_map(fn($r) => [
    'title' => $r['name'], 'sub' => trim(($r['sku'] ?: 'No SKU') . ' · ' . (int) $r['stock_quantity'] . ' in stock'),
    'url' => asset_url('admin/product-edit.php?id=' . (int) $r['id']), 'img' => product_image_url($r['img']),
], $p->fetchAll());

$o = $pdo->prepare('SELECT id, order_number, customer_name, total, order_status FROM orders
    WHERE order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? ORDER BY created_at DESC LIMIT 6');
$o->execute([$like, $like, $like, $like]);
$orders = array_map(fn($r) => [
    'title' => $r['order_number'] . ' — ' . $r['customer_name'], 'sub' => format_price($r['total']) . ' · ' . ucfirst($r['order_status']),
    'url' => asset_url('admin/order-details.php?id=' . (int) $r['id']), 'initials' => '#',
], $o->fetchAll());

$c = $pdo->prepare("SELECT id, first_name, last_name, email FROM users
    WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 6");
$c->execute([$like, $like, $like]);
$customers = array_map(fn($r) => [
    'title' => $r['first_name'] . ' ' . $r['last_name'], 'sub' => $r['email'],
    'url' => asset_url('admin/customer-view.php?id=' . (int) $r['id']), 'initials' => initials($r['first_name'] . ' ' . $r['last_name']),
], $c->fetchAll());

echo json_encode(['products' => $products, 'orders' => $orders, 'customers' => $customers], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
