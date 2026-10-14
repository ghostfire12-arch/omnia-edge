<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    redirect(asset_url('admin/dashboard.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email    = (string) filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    $wait     = login_lockout_seconds($pdo, 'admin', $email);

    if ($wait > 0) {
        $error = lockout_message($wait);
    } else {
        $q = $pdo->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
        $q->execute([':email' => $email]);
        $admin = $q->fetch();
        // Verify against a dummy hash when the email is unknown (constant timing).
        $valid = password_verify($password, $admin['password'] ?? dummy_password_hash()) && $admin;

        if (!$valid) {
            login_record_failure($pdo, 'admin', $email);
            $error = 'Invalid email or password.';
        } else {
            login_clear_failures($pdo, 'admin', $email);
            admin_login($admin);
            require_once __DIR__ . '/includes/bootstrap.php';
            admin_log('security', 'Signed in');
            // Flag the shipped demo password so the dashboard nags until it is changed.
            $_SESSION['admin_default_password'] = hash_equals('Admin@12345', $password);
            redirect(asset_url('admin/dashboard.php'));
        }
    }
}


/** Icon helper for the login page (bootstrap.php, which defines admin_icon(), requires a signed-in admin). */
function admin_icon_plain(string $n): string {
    $p = ['eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
          'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>'][$n] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}
$pageTitle = 'Admin sign in | ' . SITE_NAME;
$flash = get_flash();
$v = fn($rel) => e(asset_url($rel)) . '?v=' . (int) @filemtime(ROOT_PATH . '/' . $rel);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/logo.svg')) ?>">
<script>(function(){try{var t=localStorage.getItem('oe-admin-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $v('assets/css/admin.css') ?>">
<style>
  .auth { min-height: 100vh; display: grid; grid-template-columns: 1.1fr 1fr; background: var(--bg); }
  .auth__art { position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; padding: 44px; color: #fff;
    background: radial-gradient(80% 60% at 100% 0%, rgba(255,178,30,.35), transparent 60%), radial-gradient(70% 70% at 0% 100%, rgba(124,92,255,.45), transparent 60%), linear-gradient(140deg, #071a4a, #1f55d8); }
  .auth__art h1 { color: #fff; font-size: clamp(2rem, 1.4rem + 2.2vw, 3.2rem); max-width: 13ch; line-height: 1.04; }
  .auth__art p { color: rgba(255,255,255,.8); max-width: 42ch; margin-top: 14px; }
  .auth__brand { display: flex; align-items: center; gap: 12px; font: 800 1.1rem var(--display); }
  .auth__chips { display: flex; flex-wrap: wrap; gap: 10px; }
  .auth__chips span { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); font-size: .85rem; backdrop-filter: blur(6px); }
  .orb { position: absolute; border-radius: 50%; filter: blur(2px); opacity: .5; animation: drift 14s ease-in-out infinite; pointer-events: none; }
  .orb--1 { width: 340px; height: 340px; right: -90px; top: 22%; border: 46px solid rgba(255,255,255,.08); }
  .orb--2 { width: 180px; height: 180px; left: 46%; bottom: -60px; background: rgba(255,178,30,.25); animation-delay: -5s; }
  .orb--3 { width: 90px; height: 90px; left: 12%; top: 38%; background: rgba(91,140,255,.5); animation-delay: -9s; }
  @keyframes drift { 50% { transform: translate(-24px, -30px) scale(1.08); } }
  .auth__card-preview { position: relative; width: min(380px, 100%); padding: 18px; border-radius: 20px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); backdrop-filter: blur(10px); animation: rise 1s var(--ease) .3s both; }
  .auth__card-preview strong { font: 800 1.8rem var(--display); display: block; }
  .auth__card-preview svg.line { width: 100%; height: 70px; margin-top: 10px; }
  .auth__card-preview path { stroke-dasharray: 600; stroke-dashoffset: 600; animation: draw 2.4s var(--ease) .8s forwards; }
  @keyframes draw { to { stroke-dashoffset: 0; } }
  .auth__form { display: grid; place-items: center; padding: 40px 24px; }
  .auth__box { width: min(400px, 100%); animation: rise .8s var(--ease) both; }
  .auth__box h2 { font: 800 1.8rem var(--display); }
  .auth__box > p { color: var(--muted); margin: 6px 0 26px; }
  .pw { position: relative; }
  .pw button { position: absolute; right: 6px; top: 50%; translate: 0 -50%; border: 0; background: none; color: var(--muted); padding: 8px; border-radius: 8px; cursor: pointer; }
  .auth__foot { margin-top: 26px; font-size: .82rem; color: var(--faint); display: flex; align-items: center; gap: 8px; }
  @media (max-width: 900px) { .auth { grid-template-columns: 1fr; } .auth__art { min-height: 260px; padding: 28px; } .auth__card-preview, .auth__chips { display: none; } }
</style>
</head>
<body class="adm">
<main class="auth">
  <section class="auth__art" aria-hidden="true">
    <span class="orb orb--1"></span><span class="orb orb--2"></span><span class="orb orb--3"></span>
    <div class="auth__brand"><img src="<?= e(asset_url('assets/img/logo.svg')) ?>" alt="" width="40" height="40"> Omnia Edge</div>
    <div>
      <h1>Run the whole store from one place.</h1>
      <p>Orders, stock, customers and sales reports — live, in naira, on any device.</p>
    </div>
    <div class="auth__card-preview">
      <small style="opacity:.75">Revenue this month</small><strong>Live dashboard</strong>
      <svg class="line" viewBox="0 0 300 70" fill="none"><path d="M0 60 C 30 55, 45 30, 70 38 S 120 58, 145 34 S 190 8, 215 22 S 260 40, 300 6" stroke="#ffb21e" stroke-width="4" stroke-linecap="round"/></svg>
    </div>
    <div class="auth__chips"><span>🔒 Brute-force protection</span><span>⚡ Ctrl + K search</span><span>📊 Chart.js reports</span></div>
  </section>

  <section class="auth__form">
    <div class="auth__box">
      <h2>Welcome back</h2>
      <p>Sign in to the Omnia Edge admin console.</p>
      <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <?php if ($flash): ?><div class="alert alert--success" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
      <form method="post" class="stack" style="gap:16px" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" required autocomplete="username" autofocus value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@company.com"></div>
        <div class="field"><label for="password">Password</label>
          <div class="pw"><input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Your password" style="padding-right:46px">
            <button type="button" data-toggle-pw aria-label="Show password"><?= admin_icon_plain('eye') ?></button></div>
          <small data-caps hidden style="color:var(--amber)">Caps Lock is on</small>
        </div>
        <button class="btn btn--primary" type="submit" style="min-height:48px">Sign in</button>
      </form>
      <p class="auth__foot"><?= admin_icon_plain('shield') ?>Too many wrong passwords locks the account for 15 minutes.</p>
      <p class="auth__foot" style="margin-top:8px"><a href="<?= e(asset_url('index.php')) ?>">← Back to the store</a></p>
    </div>
  </section>
</main>
<script>
(function () {
  var pw = document.getElementById('password'), b = document.querySelector('[data-toggle-pw]'), caps = document.querySelector('[data-caps]');
  b.addEventListener('click', function () { var show = pw.type === 'password'; pw.type = show ? 'text' : 'password'; b.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); pw.focus(); });
  pw.addEventListener('keyup', function (e) { if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock'); });
  document.querySelector('form').addEventListener('submit', function (e) { var btn = e.target.querySelector('[type=submit]'); setTimeout(function () { btn.disabled = true; btn.textContent = 'Signing in…'; }, 0); });
})();
</script>
</body>
</html>
