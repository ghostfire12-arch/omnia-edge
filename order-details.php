<?php require_once __DIR__ . '/includes/auth.php';
require_customer_login();
$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare('SELECT * FROM orders WHERE id=:id AND user_id=:uid');
$s->execute([':id' => $id, ':uid' => current_customer_id()]);
$order = $s->fetch();
if (!$order) {
    http_response_code(404);
    redirect(asset_url('my-orders.php'));
}
$s = $pdo->prepare('SELECT * FROM order_items WHERE order_id=:id');
$s->execute([':id' => $id]);
$items = $s->fetchAll();
$pageTitle = 'Order ' . $order['order_number'] . ' | ' . SITE_NAME;
include __DIR__ . '/includes/header.php'; ?><div class="page-header">
    <div class="container">
        <h1>Order <?= e($order['order_number']) ?></h1>
    </div>
</div>
<main class="container section-tight">
    <div class="order-detail-box">
        <div class="summary-row"><span>Placed <?= format_date($order['created_at'], 'd M Y, g:i a') ?></span><span><span class="pill pill-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span></span></div>
        <p><strong>Delivery to:</strong> <?= e($order['customer_name']) ?>, <?= e($order['shipping_address']) ?>, <?= e($order['city']) ?> <?= e($order['state']) ?></p>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($items as $i): ?><tr>
                            <td><?= e($i['product_name']) ?></td>
                            <td><?= format_price($i['price']) ?></td>
                            <td><?= (int)$i['quantity'] ?></td>
                            <td><?= format_price($i['subtotal']) ?></td>
                        </tr><?php endforeach ?></tbody>
            </table>
        </div>
        <div class="summary-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
        <div class="summary-row"><span>Shipping</span><span><?= format_price($order['shipping_fee']) ?></span></div>
        <div class="summary-row total"><span>Total</span><span><?= format_price($order['total']) ?></span></div>
    </div>
</main><?php include __DIR__ . '/includes/footer.php'; ?>