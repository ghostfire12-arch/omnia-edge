<?php require_once __DIR__ . '/includes/auth.php';
$id = (int)($_GET['id'] ?? ($_SESSION['last_order_id'] ?? 0));
$order = false;
// Only the shopper who just placed the order, or the signed-in owner, may view it.
if ($id > 0 && ($id === (int) ($_SESSION['last_order_id'] ?? 0) || is_customer_logged_in())) {
    $s = $pdo->prepare('SELECT * FROM orders WHERE id=:id AND (:last = id OR (user_id IS NOT NULL AND user_id = :uid))');
    $s->execute([':id' => $id, ':last' => (int) ($_SESSION['last_order_id'] ?? 0), ':uid' => (int) (current_customer_id() ?? 0)]);
    $order = $s->fetch();
}
if (!$order) redirect(asset_url('index.php'));
$pageTitle = 'Order Confirmed | ' . SITE_NAME;
include __DIR__ . '/includes/header.php'; ?><main class="container section">
    <div class="order-success-box">
        <div class="check">✓</div>
        <h1>Thank you for your order!</h1>
        <p>We have received order <strong><?= e($order['order_number']) ?></strong> and will contact you with the next steps.</p>
        <div class="order-detail-box">
            <div class="summary-row"><span>Order total</span><strong><?= format_price($order['total']) ?></strong></div>
            <div class="summary-row"><span>Payment</span><span class="pill pill-<?= e($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></div>
            <div class="summary-row"><span>Order status</span><span class="pill pill-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span></div>
        </div><a class="btn btn-primary" href="<?= asset_url('shop.php') ?>">Continue Shopping</a>
    </div>
</main><?php include __DIR__ . '/includes/footer.php'; ?>