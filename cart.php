<?php
require_once __DIR__ . '/includes/cart-functions.php';

// ---------------- Handle cart actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        [$ok, $msg] = cart_add($pdo, $productId, $qty);
        set_flash($ok ? 'success' : 'error', $msg);
    } elseif ($action === 'update') {
        $cartId = (int) ($_POST['cart_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 1);
        [$ok, $msg] = cart_update_quantity($pdo, $cartId, $qty);
        if (!$ok) {
            set_flash('error', $msg);
        }
    } elseif ($action === 'remove') {
        $cartId = (int) ($_POST['cart_id'] ?? 0);
        cart_remove($pdo, $cartId);
        set_flash('success', 'Item removed from cart.');
    } elseif ($action === 'clear') {
        cart_clear($pdo);
        set_flash('success', 'Cart cleared.');
    }

    redirect(asset_url('cart.php'));
}

$items = cart_items($pdo);
$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += $item['line_total'];
}
$shippingFee = !empty($items) ? (float) get_setting('shipping_fee', '2500') : 0.0;
$total = $subtotal + $shippingFee;

// Get related products
$relatedStmt = $pdo->prepare('SELECT id, name, price, sale_price, stock_quantity, created_at FROM products WHERE status = "active" ORDER BY RAND() LIMIT 8');
$relatedStmt->execute();
$relatedProducts = $relatedStmt->fetchAll();

$pageTitle = 'Shopping Cart | ' . SITE_NAME;
$canonicalPath = '/cart.php';
$flash = get_flash();
include __DIR__ . '/includes/header.php';
?>

<div class="cart-header">
  <div class="container">
    <h1>Shopping Cart</h1>
    <div class="breadcrumb"><a href="<?= asset_url('index.php') ?>">Home</a> / Cart</div>
  </div>
</div>

<div class="container section">

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" data-autohide><?= e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg></div>
    <h3>Your cart is empty</h3>
    <p>Looks like you haven't added anything yet.</p>
    <a href="<?= asset_url('shop.php') ?>" class="btn btn-primary">Continue Shopping</a>
  </div>
<?php else: ?>
  <div class="cart-layout-new">
    <div class="cart-main-new">
      <div class="cart-items-section">
        <div class="section-header">
          <h2>Cart Items (<?= count($items) ?>)</h2>
        </div>
        
        <div class="cart-items-list">
          <?php foreach ($items as $item): ?>
            <div class="cart-item-card">
              <div class="cart-item-image">
                <img src="<?= e(product_image_url($item['image'])) ?>" alt="<?= e($item['name']) ?>">
              </div>
              <div class="cart-item-details">
                <div class="cart-item-name">
                  <a href="<?= asset_url('product.php?id=' . (int) $item['product_id']) ?>"><?= e($item['name']) ?></a>
                </div>
                <div class="cart-item-price"><?= format_price($item['unit_price']) ?></div>
                <?php if ($item['quantity'] > $item['stock_quantity']): ?>
                  <span class="stock-line low">Only <?= (int) $item['stock_quantity'] ?> in stock</span>
                <?php endif; ?>
              </div>
              <div class="cart-item-qty">
                <form method="post" action="<?= asset_url('cart.php') ?>" class="cart-qty-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                  <div class="qty-control">
                    <button type="button" class="qty-btn" onclick="updateQty(this, -1)">-</button>
                    <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['stock_quantity'] ?>" readonly>
                    <button type="button" class="qty-btn" onclick="updateQty(this, 1)">+</button>
                  </div>
                </form>
              </div>
              <div class="cart-item-subtotal">
                <span class="subtotal-amount"><?= format_price($item['line_total']) ?></span>
              </div>
              <div class="cart-item-remove">
                <form method="post" action="<?= asset_url('cart.php') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                  <button type="submit" class="remove-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="cart-actions">
          <a href="<?= asset_url('shop.php') ?>" class="btn btn-ghost">&larr; Continue Shopping</a>
          <form method="post" action="<?= asset_url('cart.php') ?>" data-confirm="Clear all items from your cart?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-outline">Clear Cart</button>
          </form>
        </div>
      </div>
    </div>

    <aside class="cart-sidebar-new">
      <div class="order-summary-card">
        <h3>Order Summary</h3>
        <div class="summary-row"><span>Subtotal</span><span><?= format_price($subtotal) ?></span></div>
        <div class="summary-row"><span>Shipping</span><span><?= format_price($shippingFee) ?></span></div>
        <div class="summary-row total"><span>Total</span><span><?= format_price($total) ?></span></div>
        <a href="<?= asset_url('checkout.php') ?>" class="btn btn-primary btn-block">Proceed to Checkout</a>
      </div>
    </aside>
  </div>

  <!-- Related Products Section -->
  <?php if (!empty($relatedProducts)): ?>
    <section class="related-products-section">
      <div class="section-head">
        <h2>You May Also Like</h2>
        <a href="<?= asset_url('shop.php') ?>" class="view-all">View All</a>
      </div>
      <div class="product-grid">
        <?php foreach ($relatedProducts as $product): ?>
          <?php include __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

</div>

<script>
function updateQty(btn, change) {
  const input = btn.parentElement.querySelector('input');
  const newVal = parseInt(input.value) + change;
  const min = parseInt(input.min);
  const max = parseInt(input.max);
  
  if (newVal >= min && newVal <= max) {
    input.value = newVal;
    input.closest('form').submit();
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>