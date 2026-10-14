<?php require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Order Not Completed | ' . SITE_NAME;
include __DIR__ . '/includes/header.php'; ?><main class="container section">
    <div class="empty-state">
        <h1>We could not complete your order</h1>
        <p>Your cart is still available. Please review it and try again.</p><a class="btn btn-primary" href="/cart.php">Return to Cart</a>
    </div>
</main><?php include __DIR__ . '/includes/footer.php'; ?>