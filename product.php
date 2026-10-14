<?php
require_once __DIR__ . '/includes/cart-functions.php';

$productId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = :id AND p.status = 'active'"
);
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found | ' . SITE_NAME;
    $canonicalPath = '/product.php';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section"><div class="empty-state"><h3>Product not found</h3><p>This product may have been removed or is no longer available.</p><a href="' . asset_url('shop.php') . '" class="btn btn-primary">Back to Shop</a></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    require_csrf();
    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    [$ok, $msg] = cart_add($pdo, $productId, $qty);
    set_flash($ok ? 'success' : 'error', $msg);
    redirect(asset_url('product.php?id=' . $productId));
}

// Images
$stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC');
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();
if (empty($images)) {
    $images = [['image' => null]];
}

// Related products (same category, excluding this one)
$related = [];
if (!empty($product['category_id'])) {
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.category_id = :cat AND p.id != :id AND p.status = 'active'
         ORDER BY p.created_at DESC LIMIT 4"
    );
    $stmt->execute([':cat' => $product['category_id'], ':id' => $productId]);
    $related = $stmt->fetchAll();
}

$isOut = (int) $product['stock_quantity'] <= 0;
$discountPct = discount_percent($product);

$pageTitle = $product['name'] . ' | ' . SITE_NAME;
$metaDescription = !empty($product['description'])
    ? substr(strip_tags($product['description']), 0, 155)
    : ('Buy ' . $product['name'] . ' at ' . SITE_NAME . '. ' . get_setting('company_tagline'));
$canonicalPath = '/product.php?id=' . $productId;
$activeNav = 'shop';

$flash = get_flash();
include __DIR__ . '/includes/header.php';
?>

<div class="container section-tight">

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" data-autohide><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="product-detail">
  <div class="product-gallery">
    <div class="gallery-main">
      <img id="galleryMain" src="<?= e(product_image_url($images[0]['image'])) ?>" alt="<?= e($product['name']) ?>">
    </div>
    <?php if (count($images) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($images as $i => $img): ?>
          <button type="button" data-gallery-thumb data-full="<?= e(product_image_url($img['image'])) ?>" class="<?= $i === 0 ? 'active' : '' ?>">
            <img src="<?= e(product_image_url($img['image'])) ?>" alt="">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <div class="breadcrumb">
      <a href="<?= asset_url('index.php') ?>">Home</a> /
      <a href="<?= asset_url('shop.php') ?>">Shop</a>
      <?php if (!empty($product['category_name'])): ?>
        / <a href="<?= asset_url('shop.php') ?>?category=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
      <?php endif; ?>
    </div>

    <h1><?= e($product['name']) ?></h1>
    <div class="sku-line">
      <?php if (!empty($product['brand'])): ?>Brand: <strong><?= e($product['brand']) ?></strong> &nbsp;·&nbsp; <?php endif; ?>
      <?php if (!empty($product['sku'])): ?>SKU: <?= e($product['sku']) ?><?php endif; ?>
    </div>

    <div class="price-row">
      <span class="price-now"><?= format_price(effective_price($product)) ?></span>
      <?php if ($discountPct > 0): ?>
        <span class="price-was"><?= format_price($product['price']) ?></span>
        <span class="discount-badge">Save <?= (int) $discountPct ?>%</span>
      <?php endif; ?>
    </div>

    <?php if ($isOut): ?>
      <span class="stock-line out">Out of stock</span>
    <?php elseif ((int) $product['stock_quantity'] <= 5): ?>
      <span class="stock-line low">Only <?= (int) $product['stock_quantity'] ?> left in stock</span>
    <?php else: ?>
      <span class="stock-line in">In stock (<?= (int) $product['stock_quantity'] ?> available)</span>
    <?php endif; ?>

    <?php if (!$isOut): ?>
      <form method="post" action="<?= asset_url('product.php?id=' . $productId) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="qty-selector">
          <label style="margin:0;">Quantity</label>
          <div class="qty-control" data-qty-stepper>
            <button type="button" data-step="down" aria-label="Decrease quantity">&minus;</button>
            <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock_quantity'] ?>">
            <button type="button" data-step="up" aria-label="Increase quantity">&plus;</button>
          </div>
        </div>
        <div class="add-to-cart-row">
          <button type="submit" class="btn btn-primary">Add to Cart</button>
        </div>
      </form>
      <form method="post" action="<?= e(asset_url('wishlist.php')) ?>" class="add-to-cart-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? asset_url('product.php?id=' . $productId)) ?>">
        <button type="submit" class="btn btn-outline" aria-label="Save <?= e($product['name']) ?> to wishlist">&hearts;</button>
      </form>
    <?php else: ?>
      <button class="btn btn-ghost" disabled style="margin-top:16px;">Out of Stock</button>
    <?php endif; ?>

    <div class="product-assurance" aria-label="Shopping assurances">
      <div class="assurance-item">
        <span class="assurance-icon" aria-hidden="true">&#10003;</span>
        <span><strong>Quality checked</strong><small>Selected for everyday use</small></span>
      </div>
      <div class="assurance-item">
        <span class="assurance-icon" aria-hidden="true">&#8594;</span>
        <span><strong>Delivery across Nigeria</strong><small>Reliable doorstep delivery</small></span>
      </div>
      <div class="assurance-item">
        <span class="assurance-icon" aria-hidden="true">&#128274;</span>
        <span><strong>Secure checkout</strong><small>Your details stay protected</small></span>
      </div>
    </div>

    <div class="product-desc">
      <h3>Product Description</h3>
      <p><?= nl2br(e($product['description'] ?: 'No description available for this product yet.')) ?></p>
    </div>
  </div>
</div>

<?php if (!empty($related)): ?>
<section class="section">
  <div class="section-head">
    <h2>Related Products</h2>
  </div>
  <div class="product-grid">
    <?php foreach ($related as $product): ?>
      <?php include __DIR__ . '/includes/product-card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
