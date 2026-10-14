<?php require_once __DIR__ . '/includes/auth.php';
require_customer_login(asset_url('my-account.php'));
$s = $pdo->prepare('SELECT * FROM users WHERE id=:id');
$s->execute([':id' => current_customer_id()]);
$user = $s->fetch();
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $fields = ['first_name', 'last_name', 'phone', 'address', 'city', 'state', 'country'];
    $data = [];
    foreach ($fields as $f) $data[$f] = clean_input($_POST[$f] ?? '');
    if (!$data['first_name'] || !$data['last_name']) $notice = 'Your name is required.';
    else {
        $sql = 'UPDATE users SET first_name=:first_name,last_name=:last_name,phone=:phone,address=:address,city=:city,state=:state,country=:country WHERE id=:id';
        $data['id'] = current_customer_id();
        $pdo->prepare($sql)->execute($data);
        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $notice = 'Your profile has been updated.';
        $s->execute([':id' => current_customer_id()]);
        $user = $s->fetch();
    }
}
$pageTitle = 'My Account | ' . SITE_NAME;
include __DIR__ . '/includes/header.php'; ?>

<div class="account-hero">
    <div class="container">
        <div class="account-hero-content">
            <div class="account-avatar">
                <span><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></span>
            </div>
            <div class="account-info">
                <h1><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                <p><?= e($user['email']) ?></p>
            </div>
        </div>
    </div>
</div>

<main class="container section">
    <div class="account-layout-new">
        <aside class="account-sidebar">
            <nav class="account-nav-new">
                <a href="<?= asset_url('my-account.php') ?>" class="active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Profile
                </a>
                <a href="<?= asset_url('my-orders.php') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7 12 3 4 7v10l8 4 8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
                    My Orders
                </a>
                <a href="<?= asset_url('cart.php') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
                    Shopping Cart
                </a>
                <a href="<?= asset_url('wishlist.php') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 8.8c0 5.5-8.8 10.2-8.8 10.2S3.2 14.3 3.2 8.8A4.8 4.8 0 0 1 12 6a4.8 4.8 0 0 1 8.8 2.8Z"/></svg>
                    Wishlist
                </a>
                <a href="<?= asset_url('logout.php') ?>" class="logout-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign Out
                </a>
            </nav>
        </aside>

        <div class="account-main">
            <?php if ($notice): ?>
                <div class="alert <?= str_contains($notice, 'required') ? 'alert-error' : 'alert-success' ?>"><?= e($notice) ?></div>
            <?php endif; ?>

            <div class="account-section">
                <div class="section-header">
                    <h2>Personal Information</h2>
                    <p>Update your personal details</p>
                </div>
                <form method="post" class="profile-form">
                    <?= csrf_field() ?>
                    
                    <div class="form-section-title">Basic Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input name="first_name" value="<?= e($user['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input name="last_name" value="<?= e($user['last_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-section-title">Contact Information</div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input value="<?= e($user['email']) ?>" disabled>
                        <small>Email cannot be changed</small>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input name="phone" value="<?= e($user['phone']) ?>" placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-section-title">Delivery Address</div>
                    <div class="form-group">
                        <label>Street Address</label>
                        <textarea name="address" placeholder="Enter your full address"><?= e($user['address']) ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input name="city" value="<?= e($user['city']) ?>" placeholder="City">
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input name="state" value="<?= e($user['state']) ?>" placeholder="State">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input name="country" value="<?= e($user['country'] ?: 'Nigeria') ?>" placeholder="Country">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>