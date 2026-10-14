<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/demo-data.php';

$tab = pick((string) ($_GET['tab'] ?? 'store'), ['store', 'security', 'demo'], 'store');
$groups = [
    'Business' => ['company_name' => 'Business name', 'company_tagline' => 'Tagline', 'store_description' => 'Store description'],
    'Contact'  => ['phone' => 'Phone', 'email' => 'Email', 'whatsapp_number' => 'WhatsApp number (digits only, e.g. 2348012345678)', 'address' => 'Address'],
    'Money'    => ['currency' => 'Currency code', 'currency_symbol' => 'Currency symbol', 'shipping_fee' => 'Delivery fee (₦)', 'monthly_target' => 'Monthly revenue target (₦)'],
    'Social'   => ['facebook_url' => 'Facebook URL', 'instagram_url' => 'Instagram URL', 'twitter_url' => 'X / Twitter URL'],
];
$numeric = ['shipping_fee', 'monthly_target'];
$pwError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = (string) ($_POST['form'] ?? '');

    if ($form === 'store') {
        $q = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($groups as $fields) foreach ($fields as $key => $label) {
            $v = mb_substr(clean_input($_POST[$key] ?? ''), 0, 1000);
            if (str_ends_with($key, '_url') && $v !== '' && !preg_match('#^https://#i', $v)) $v = '';   // https links only
            if ($key === 'whatsapp_number') $v = preg_replace('/\D+/', '', $v);
            if ($key === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $v = get_setting('email');
            if (in_array($key, $numeric, true)) $v = (string) max(0, round((float) $v));
            $q->execute([$key, $v]);
        }
        admin_log('settings', 'Updated store settings');
        set_flash('success', 'Store settings saved.');
        redirect(asset_url('admin/settings.php'));
    }

    if ($form === 'password') {
        $tab = 'security';
        $current = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['new_password'] ?? ''); $confirm = (string) ($_POST['confirm_password'] ?? '');
        $q = $pdo->prepare('SELECT password FROM admins WHERE id = ?'); $q->execute([current_admin_id()]); $hash = (string) $q->fetchColumn();
        if (!password_verify($current, $hash)) $pwError = 'Your current password is incorrect.';
        elseif (strlen($new) < 10 || strlen($new) > 200 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $pwError = 'Use 10 or more characters with at least one letter and one number.';
        elseif (!hash_equals($new, $confirm)) $pwError = 'The new passwords do not match.';
        elseif (hash_equals('Admin@12345', $new) || password_verify($new, $hash)) $pwError = 'Choose a password you have not used here before.';
        else {
            $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), current_admin_id()]);
            session_regenerate_id(true);
            $_SESSION['admin_default_password'] = false;
            admin_log('security', 'Changed the admin password');
            set_flash('success', 'Password changed.');
            redirect(asset_url('admin/settings.php?tab=security'));
        }
    }

    if ($form === 'profile') {
        $name = mb_substr(clean_input($_POST['admin_name'] ?? ''), 0, 100);
        if ($name !== '') { $pdo->prepare('UPDATE admins SET name = ? WHERE id = ?')->execute([$name, current_admin_id()]); $_SESSION['admin_name'] = $name; set_flash('success', 'Display name updated.'); }
        redirect(asset_url('admin/settings.php?tab=security'));
    }

    if ($form === 'demo') {
        if (($_POST['do'] ?? '') === 'load') { @set_time_limit(120); $n = demo_load($pdo); admin_log('demo', "Loaded sample sales data ($n orders)"); set_flash('success', "Loaded $n sample orders. Open the dashboard to explore."); }
        else { $n = demo_remove($pdo); admin_log('demo', 'Removed sample sales data'); set_flash('success', "Removed $n sample orders, plus sample customers and messages."); }
        redirect(asset_url('admin/settings.php?tab=demo'));
    }
}

$demo = demo_counts($pdo);
$admin = $pdo->prepare('SELECT name, email, updated_at FROM admins WHERE id = ?'); $admin->execute([current_admin_id()]); $admin = $admin->fetch();
login_attempts_table($pdo);
$fails = $pdo->query("SELECT scope, ip_address, attempted_at FROM login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY id DESC LIMIT 10")->fetchAll();

$pageTitle = 'Settings | ' . SITE_NAME;
$activePage = 'settings';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head"><div><h1>Settings</h1><p>Store details, your account and tools.</p></div></div>
<nav class="tabs" aria-label="Settings sections">
  <a href="?tab=store" class="<?= $tab === 'store' ? 'is-active' : '' ?>"><?= admin_icon('store') ?>Store</a>
  <a href="?tab=security" class="<?= $tab === 'security' ? 'is-active' : '' ?>"><?= admin_icon('shield') ?>Password &amp; security</a>
  <a href="?tab=demo" class="<?= $tab === 'demo' ? 'is-active' : '' ?>"><?= admin_icon('database') ?>Sample data<?= $demo['orders'] ? ' <span class="count">on</span>' : '' ?></a>
</nav>

<?php if ($tab === 'store'): ?>
<form method="post" class="grid grid--1-1" data-stagger>
  <?= csrf_field() ?><input type="hidden" name="form" value="store">
  <?php foreach ($groups as $title => $fields): ?>
    <section class="card">
      <div class="card__head"><h2><?= e($title) ?></h2></div>
      <div class="stack" style="gap:14px">
        <?php foreach ($fields as $key => $label): ?>
          <div class="field"><label for="s_<?= e($key) ?>"><?= e($label) ?></label>
            <?php if (in_array($key, ['store_description', 'address'], true)): ?>
              <textarea id="s_<?= e($key) ?>" name="<?= e($key) ?>" style="min-height:90px"><?= e(get_setting($key)) ?></textarea>
            <?php else: ?>
              <input id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e(get_setting($key, $key === 'monthly_target' ? '5000000' : '')) ?>"<?= str_ends_with($key, '_url') ? ' type="url" placeholder="https://"' : (in_array($key, $numeric, true) ? ' type="number" min="0" step="1"' : ($key === 'email' ? ' type="email"' : '')) ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <div class="form-actions" style="grid-column:1/-1"><button class="btn btn--primary"><?= admin_icon('check') ?>Save settings</button></div>
</form>

<?php elseif ($tab === 'security'): ?>
<div class="grid grid--1-1" data-stagger>
  <section class="card">
    <div class="card__head"><div><h2>Change password</h2><p>Use at least 10 characters, with letters and numbers.</p></div></div>
    <?php if ($pwError): ?><div class="alert alert--error" role="alert"><?= e($pwError) ?></div><?php endif; ?>
    <form method="post" class="stack" style="gap:14px" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <div class="field"><label for="cp">Current password</label><input id="cp" type="password" name="current_password" required autocomplete="current-password"></div>
      <div class="field"><label for="np">New password</label><input id="np" type="password" name="new_password" minlength="10" required autocomplete="new-password" data-strength><small data-strength-out>&nbsp;</small><div class="bars__track"><i data-strength-bar style="--w:0%;animation:none;transition:width .4s"></i></div></div>
      <div class="field"><label for="cf">Confirm new password</label><input id="cf" type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></div>
      <button class="btn btn--primary"><?= admin_icon('shield') ?>Change password</button>
    </form>
  </section>
  <div class="stack">
    <section class="card">
      <div class="card__head"><h2>Your profile</h2></div>
      <form method="post" class="stack" style="gap:14px"><?= csrf_field() ?><input type="hidden" name="form" value="profile">
        <div class="field"><label for="an">Display name</label><input id="an" name="admin_name" value="<?= e($admin['name'] ?? '') ?>" maxlength="100"></div>
        <dl class="kv"><dt>Sign-in email</dt><dd><?= e($admin['email'] ?? '') ?></dd></dl>
        <button class="btn btn--ghost">Save name</button>
      </form>
    </section>
    <section class="card">
      <div class="card__head"><div><h2>Failed sign-ins</h2><p>Last 24 hours. Accounts lock for 15 minutes after 5 failures.</p></div></div>
      <?php if (!$fails): ?><p class="muted">No failed sign-in attempts. 🎉</p><?php else: ?>
        <ul class="feed"><?php foreach ($fails as $fl): ?><li style="padding-bottom:10px"><span class="feed__dot tone-red"><?= admin_icon('alert') ?></span><div><p><?= $fl['scope'] === 'admin' ? 'Admin' : 'Customer' ?> sign-in failed from <?= e($fl['ip_address']) ?></p><small><?= e(time_ago($fl['attempted_at'])) ?></small></div></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php else: ?>
<section class="card" data-stagger>
  <div class="demo-card">
    <span class="kpi__ico tone-violet"><?= admin_icon('database') ?></span>
    <div style="flex:1">
      <h2>Sample sales data</h2>
      <p class="muted" style="margin:6px 0 14px;max-width:70ch">Fill the dashboard and reports with about 160 realistic orders over the last four months, 36 customers and 5 messages, so you can explore the charts before real sales arrive. Every sample record is tagged (order numbers start with <code>DEMO-</code>, emails end with <code><?= e(DEMO_DOMAIN) ?></code>) and is removed in one click. Product stock is not changed.</p>
      <p><strong>Currently loaded:</strong> <?= $demo['orders'] ?> orders · <?= $demo['customers'] ?> customers · <?= $demo['messages'] ?> messages</p>
      <div class="form-actions" style="justify-content:flex-start;margin-top:16px">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="demo"><input type="hidden" name="do" value="load"><button class="btn btn--primary"><?= admin_icon('refresh') ?><?= $demo['orders'] ? 'Reload sample data' : 'Load sample data' ?></button></form>
        <?php if ($demo['orders'] || $demo['customers'] || $demo['messages']): ?>
          <form method="post" data-confirm="Remove all sample orders, customers and messages? Real data is not touched." data-confirm-ok="Remove sample data"><?= csrf_field() ?><input type="hidden" name="form" value="demo"><input type="hidden" name="do" value="remove"><button class="btn btn--ghost" style="color:var(--red)"><?= admin_icon('trash') ?>Remove sample data</button></form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
<?php
$adminScripts = <<<'JS'
<script>
(function () {
  var i = document.querySelector('[data-strength]'); if (!i) return;
  var out = document.querySelector('[data-strength-out]'), bar = document.querySelector('[data-strength-bar]');
  i.addEventListener('input', function () {
    var v = i.value, s = 0;
    if (v.length >= 10) s++; if (v.length >= 14) s++; if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
    var labels = ['Too short', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'], colors = ['#e5484d', '#e5484d', '#ffb21e', '#ffb21e', '#12a150', '#12a150'];
    out.textContent = v ? labels[s] : ' ';
    bar.style.setProperty('--w', (v ? Math.max(8, s * 20) : 0) + '%'); bar.style.background = colors[s];
  });
})();
</script>
JS;
include __DIR__ . '/includes/admin-footer.php';
