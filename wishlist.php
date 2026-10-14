<?php
require_once __DIR__ . '/includes/cart-functions.php';

require_customer_login(asset_url('wishlist.php'));
$userId = current_customer_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    require_csrf();
    $productId = (int) ($_POST['product_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $productId]);

    if (!$stmt->fetch()) {
        set_flash('error', 'That product is no longer available.');
    } else {
        $check = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = :user_id AND product_id = :product_id');
        $check->execute([':user_id' => $userId, ':product_id' => $productId]);
        $saved = $check->fetchColumn();

        if ($saved) {
            $pdo->prepare('DELETE FROM wishlists WHERE id = :id')->execute([':id' => $saved]);
            set_flash('success', 'Product removed from your wishlist.');
        } else {
            $pdo->prepare('INSERT INTO wishlists (user_id, product_id) VALUES (:user_id, :product_id)')->execute([
                ':user_id' => $userId,
                ':product_id' => $productId,
            ]);
            set_flash('success', 'Product added to your wishlist.');
        }
    }

    redirect(safe_local_path($_POST['return_to'] ?? '', asset_url('wishlist.php')));
}

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name
     FROM wishlists w
     INNER JOIN products p ON p.id = w.product_id AND p.status = 'active'
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE w.user_id = :user_id
     ORDER BY w.created_at DESC"
);
$stmt->execute([':user_id' => $userId]);
$products = $stmt->fetchAll();
$flash = get_flash();
$pageTitle = 'My Wishlist | ' . SITE_NAME;
$activeNav = 'shop';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1>My Wishlist</h1>
    <div class="breadcrumb"><a href="<?= asset_url('index.php') ?>">Home</a> / Wishlist</div>
  </div>
</div>

<main class="container section">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" data-autohide><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="icon">&hearts;</div>
      <h3>Your wishlist is empty</h3>
      <p>Save products here while you decide what to buy.</p>
      <a href="<?= asset_url('shop.php') ?>" class="btn btn-primary">Browse Products</a>
    </div>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($products as $product): ?>
        <?php include __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>