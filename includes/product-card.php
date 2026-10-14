<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Reusable product card.
 *
 * Expects $product (array from `products`, plus optional 'category_name')
 * and $pdo already available in scope.
 *
 * Usage:
 *   foreach ($products as $product) {
 *       include __DIR__ . '/product-card.php';
 *   }
 */

$image        = get_primary_product_image($pdo, (int) $product['id']);
$imgUrl       = product_image_url($image);
$isOut        = (int) $product['stock_quantity'] <= 0;
$isLow        = !$isOut && (int) $product['stock_quantity'] <= 5;
$discountPct  = discount_percent($product);
$isNew        = isset($product['created_at']) && strtotime($product['created_at']) >= strtotime('-14 days');
$productUrl   = asset_url('product.php?id=' . (int) $product['id']);
?>
<div class="product-card">
  <a href="<?= e($productUrl) ?>" class="thumb">
    <?php if ($discountPct > 0): ?>
      <span class="badge badge-sale">-<?= (int) $discountPct ?>%</span>
    <?php elseif ($isNew): ?>
      <span class="badge badge-new">New</span>
    <?php endif; ?>
    <?php if ($isOut): ?>
      <span class="badge badge-out" style="left:auto;right:10px;">Out of stock</span>
    <?php endif; ?>
    <img src="<?= e($imgUrl) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
  </a>
  <div class="body">
    <?php if (!empty($product['category_name'])): ?>
      <span class="cat-label"><?= e($product['category_name']) ?></span>
    <?php endif; ?>
    <h3><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h3>
    <div class="price-row">
      <span class="price-now"><?= format_price(effective_price($product)) ?></span>
      <?php if ($discountPct > 0): ?>
        <span class="price-was"><?= format_price($product['price']) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($isOut): ?>
      <span class="stock-line out">Out of stock</span>
    <?php elseif ($isLow): ?>
      <span class="stock-line low">Only <?= (int) $product['stock_quantity'] ?> left</span>
    <?php else: ?>
      <span class="stock-line in">In stock</span>
    <?php endif; ?>
    <div class="actions">
      <form action="<?= e(asset_url('wishlist.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? asset_url('shop.php')) ?>">
        <button type="submit" class="btn btn-ghost btn-sm wishlist-button" aria-label="Save <?= e($product['name']) ?> to wishlist" title="Save to wishlist">&hearts;</button>
      </form>
      <?php if ($isOut): ?>
        <button class="btn btn-ghost btn-sm" disabled>Out of Stock</button>
      <?php else: ?>
        <form action="<?= e(asset_url('cart.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
          <input type="hidden" name="quantity" value="1">
          <button type="submit" class="btn btn-primary btn-sm btn-block">Add to Cart</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
