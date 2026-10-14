<?php require_once __DIR__ . '/includes/auth.php';
require_customer_login(asset_url('my-orders.php'));
$s = $pdo->prepare('SELECT * FROM orders WHERE user_id=:id ORDER BY created_at DESC');
$s->execute([':id' => current_customer_id()]);
$orders = $s->fetchAll();
$pageTitle = 'My Orders | ' . SITE_NAME;
include __DIR__ . '/includes/header.php'; ?><div class="page-header">
    <div class="container">
        <h1>My Orders</h1>
    </div>
</div>
<main class="container section-tight"><?php if (!$orders): ?><div class="empty-state">
            <h3>No orders yet</h3>
            <p>Your completed purchases will appear here.</p><a class="btn btn-primary" href="<?= asset_url('shop.php') ?>">Start Shopping</a>
        </div><?php else: ?><div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($orders as $o): ?><tr>
                            <td><?= e($o['order_number']) ?></td>
                            <td><?= format_date($o['created_at']) ?></td>
                            <td><?= format_price($o['total']) ?></td>
                            <td><span class="pill pill-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></td>
                            <td><span class="pill pill-<?= e($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
                            <td><a class="btn btn-sm btn-outline" href="<?= asset_url('order-details.php?id=' . (int)$o['id']) ?>">View</a></td>
                        </tr><?php endforeach ?></tbody>
            </table>
        </div><?php endif ?></main><?php include __DIR__ . '/includes/footer.php'; ?>