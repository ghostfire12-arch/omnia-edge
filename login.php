<?php
require_once __DIR__ . '/includes/cart-functions.php';
if (is_customer_logged_in()) redirect(asset_url('my-account.php'));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if (!$email || $password === '') $error = 'Enter your email address and password.';
    else {
        $wait = login_lockout_seconds($pdo, 'customer', $email);
        if ($wait > 0) {
            $error = lockout_message($wait);
        } else {
            $s = $pdo->prepare('SELECT * FROM users WHERE email=:email LIMIT 1'); $s->execute([':email'=>$email]); $user=$s->fetch();
            // Always run password_verify so response time does not reveal whether the email exists.
            $valid = password_verify($password, $user['password'] ?? dummy_password_hash()) && $user;
            if (!$valid || $user['status'] !== 'active') {
                login_record_failure($pdo, 'customer', $email);
                $error = 'Your email or password is incorrect.';
            } else {
                login_clear_failures($pdo, 'customer', $email);
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE users SET password=:p WHERE id=:id')->execute([':p'=>password_hash($password, PASSWORD_DEFAULT), ':id'=>$user['id']]);
                }
                $guestKey = guest_cart_key(); customer_login($user); merge_guest_cart_into_user($pdo, (int)$user['id'], $guestKey);
                $to = safe_local_path($_SESSION['redirect_after_login'] ?? '', asset_url('my-account.php')); unset($_SESSION['redirect_after_login']); redirect($to);
            }
        }
    }
}
$pageTitle='Sign In | '.SITE_NAME; include __DIR__.'/includes/header.php';
?>
<div class="page-header"><div class="container"><h1>Welcome back</h1></div></div>
<main class="container section-tight"><div class="form-card"><h2>Sign in</h2><p class="sub">Access your orders and account details.</p>
<?php if($error): ?><div class="alert alert-error"><?=e($error)?></div><?php endif; ?>
<form method="post"><?=csrf_field()?><div class="form-group"><label>Email address</label><input type="email" name="email" required></div><div class="form-group"><label>Password</label><input type="password" name="password" required></div><button class="btn btn-primary btn-block">Sign In</button></form><p class="form-foot">New to Omnia Edge? <a href="<?= asset_url('register.php') ?>">Create an account</a></p></div></main>
<?php include __DIR__.'/includes/footer.php'; ?>
