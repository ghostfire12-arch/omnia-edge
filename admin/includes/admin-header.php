<?php
/**
 * Admin shell — top half. Pages set before including:
 *   $pageTitle   string   browser title
 *   $activePage  string   sidebar key (dashboard, reports, products, categories, import, orders, customers, messages, settings)
 *   $useCharts   bool     load Chart.js
 */
$pageTitle  = $pageTitle  ?? 'Admin | ' . SITE_NAME;
$activePage = $activePage ?? '';
$counts     = admin_counts($pdo);
$adminName  = $_SESSION['admin_name'] ?? 'Administrator';
$flash      = $flash ?? get_flash();
$v = function (string $rel) { return e(asset_url($rel)) . '?v=' . (int) @filemtime(ROOT_PATH . '/' . $rel); };

$nav = [
    'Overview' => [
        ['dashboard', 'Dashboard', 'admin/dashboard.php', 'dashboard', 0],
        ['reports', 'Reports', 'admin/reports.php', 'chart', 0],
    ],
    'Catalog' => [
        ['products', 'Products', 'admin/products.php', 'box', $counts['low_stock']],
        ['categories', 'Categories', 'admin/categories.php', 'tag', 0],
        ['import', 'Import products', 'admin/import-url.php', 'upload', 0],
    ],
    'Sales' => [
        ['orders', 'Orders', 'admin/orders.php', 'cart', $counts['pending']],
        ['customers', 'Customers', 'admin/customers.php', 'users', 0],
        ['messages', 'Messages', 'admin/messages.php', 'mail', $counts['unread']],
    ],
    'System' => [
        ['settings', 'Settings', 'admin/settings.php', 'settings', 0],
    ],
];
$bellTotal = $counts['pending'] + $counts['unread'] + $counts['low_stock'];
?>
<!doctype html>
<html lang="en" data-base-path="<?= e(BASE_URL_PATH) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/logo.svg')) ?>">
<script>
  /* Apply the saved theme before first paint (no flash). */
  (function () { try { var t = localStorage.getItem('oe-admin-theme'); if (t) document.documentElement.setAttribute('data-theme', t);
    if (localStorage.getItem('oe-admin-collapsed') === '1') document.documentElement.classList.add('side-collapsed'); } catch (e) {} })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $v('assets/css/admin.css') ?>">
</head>
<body class="adm" data-csrf="<?= e(csrf_token()) ?>" data-api="<?= e(asset_url('admin/api/search.php')) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<aside class="adm-side" id="admSide" aria-label="Admin navigation">
  <a class="adm-brand" href="<?= e(asset_url('admin/dashboard.php')) ?>">
    <img src="<?= e(asset_url('assets/img/logo.svg')) ?>" alt="" width="38" height="38">
    <span><strong>Omnia Edge</strong><small>Admin console</small></span>
  </a>
  <nav class="adm-nav">
    <?php foreach ($nav as $navGroup => $navItems): ?>
      <p class="adm-nav__group"><?= e($navGroup) ?></p>
      <?php foreach ($navItems as [$navKey, $navLabel, $navHref, $navIcon, $navBadge]): ?>
        <a href="<?= e(asset_url($navHref)) ?>" class="adm-nav__link<?= $activePage === $navKey ? ' is-active' : '' ?>"<?= $activePage === $navKey ? ' aria-current="page"' : '' ?> data-tip="<?= e($navLabel) ?>">
          <?= admin_icon($navIcon) ?><span><?= e($navLabel) ?></span>
          <?php if ($navBadge > 0): ?><b class="adm-badge<?= $navKey === 'products' ? ' adm-badge--warn' : '' ?>"><?= $navBadge > 99 ? '99+' : (int) $navBadge ?></b><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="adm-side__foot">
    <a class="adm-nav__link" href="<?= e(asset_url('index.php')) ?>" target="_blank" rel="noopener" data-tip="View store"><?= admin_icon('store') ?><span>View store</span></a>
    <form method="post" action="<?= e(asset_url('admin/logout.php')) ?>">
      <?= csrf_field() ?>
      <button class="adm-nav__link" type="submit" data-tip="Sign out"><?= admin_icon('logout') ?><span>Sign out</span></button>
    </form>
    <button class="adm-collapse" type="button" data-collapse aria-label="Collapse sidebar"><?= admin_icon('up') ?></button>
  </div>
</aside>
<div class="adm-scrim" data-side-close></div>

<div class="adm-main">
  <header class="adm-top">
    <button class="icon-btn adm-top__menu" type="button" data-side-open aria-label="Open menu"><?= admin_icon('menu') ?></button>
    <button class="adm-search" type="button" data-palette-open>
      <?= admin_icon('search') ?><span>Search products, orders, customers…</span><kbd>Ctrl K</kbd>
    </button>
    <div class="adm-top__actions">
      <a class="btn btn--primary btn--sm adm-top__add" href="<?= e(asset_url('admin/product-add.php')) ?>"><?= admin_icon('plus') ?><span>New product</span></a>
      <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch light or dark theme"><?= admin_icon('moon', 'only-light') ?><?= admin_icon('sun', 'only-dark') ?></button>
      <div class="dropdown">
        <button class="icon-btn" type="button" data-dropdown aria-expanded="false" aria-label="Notifications (<?= (int) $bellTotal ?>)">
          <?= admin_icon('bell') ?><?php if ($bellTotal): ?><b class="dot-badge"><?= $bellTotal > 9 ? '9+' : (int) $bellTotal ?></b><?php endif; ?>
        </button>
        <div class="dropdown__menu dropdown__menu--wide" role="menu">
          <p class="dropdown__title">Needs your attention</p>
          <a href="<?= e(asset_url('admin/orders.php?status=pending')) ?>" class="note"><span class="note__ico tone-amber"><?= admin_icon('cart') ?></span><span><strong><?= (int) $counts['pending'] ?> pending order<?= $counts['pending'] === 1 ? '' : 's' ?></strong><small>Confirm and start processing</small></span></a>
          <a href="<?= e(asset_url('admin/messages.php?filter=unread')) ?>" class="note"><span class="note__ico tone-blue"><?= admin_icon('mail') ?></span><span><strong><?= (int) $counts['unread'] ?> unread message<?= $counts['unread'] === 1 ? '' : 's' ?></strong><small>Reply to customers</small></span></a>
          <a href="<?= e(asset_url('admin/products.php?stock=low')) ?>" class="note"><span class="note__ico tone-red"><?= admin_icon('alert') ?></span><span><strong><?= (int) $counts['low_stock'] ?> product<?= $counts['low_stock'] === 1 ? '' : 's' ?> low on stock</strong><small><?= LOW_STOCK_LEVEL ?> units or fewer left</small></span></a>
        </div>
      </div>
      <div class="dropdown">
        <button class="adm-user" type="button" data-dropdown aria-expanded="false">
          <span class="avatar"><?= e(initials($adminName)) ?></span>
          <span class="adm-user__name"><?= e($adminName) ?></span><?= admin_icon('down') ?>
        </button>
        <div class="dropdown__menu" role="menu">
          <a href="<?= e(asset_url('admin/settings.php?tab=security')) ?>"><?= admin_icon('shield') ?>Password &amp; security</a>
          <a href="<?= e(asset_url('admin/settings.php')) ?>"><?= admin_icon('settings') ?>Store settings</a>
          <form method="post" action="<?= e(asset_url('admin/logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= admin_icon('logout') ?>Sign out</button></form>
        </div>
      </div>
    </div>
  </header>

  <main class="adm-content" id="main" tabindex="-1">
    <?php if (!empty($_SESSION['admin_default_password'])): ?>
      <div class="banner banner--danger" role="alert">
        <?= admin_icon('shield') ?>
        <span><strong>You are still using the default admin password.</strong> Anyone who has read the README can sign in.</span>
        <a class="btn btn--sm btn--light" href="<?= e(asset_url('admin/settings.php?tab=security')) ?>">Change it now</a>
      </div>
    <?php endif; ?>
    <?php if ($flash): ?>
      <div class="flash" data-flash data-type="<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" hidden><?= e($flash['message']) ?></div>
    <?php endif; ?>
