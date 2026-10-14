<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Shared storefront header.
 *
 * Expects (optional, set by the including page before requiring this file):
 *   $pageTitle        string  e.g. "Shop | Omnia Edge Strategic Services"
 *   $metaDescription  string
 *   $canonicalPath     string  e.g. "/shop.php" (no domain)
 *   $activeNav        string  one of: home, shop, about, contact
 *
 * Requires $pdo, and the functions/auth/cart-functions includes to
 * already be loaded by the calling page.
 */

require_once __DIR__ . '/cart-functions.php';

$pageTitle       = $pageTitle ?? SITE_NAME . ' — ' . get_setting('company_tagline', 'Anything Sellable, Delivered.');
$metaDescription = $metaDescription ?? get_setting('store_description');
$canonicalPath   = $canonicalPath ?? ($_SERVER['REQUEST_URI'] ?? '/');
$activeNav       = $activeNav ?? '';

$navCategories = get_active_categories($pdo);
$cartCount     = cart_count($pdo);
$wishlistCount = wishlist_count($pdo);
$searchQuery   = $_GET['q'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-base-path="<?= e(BASE_URL_PATH) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<link rel="canonical" href="<?= e(SITE_URL . $canonicalPath) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/logo.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>?v=<?= (int) filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
<?= $extraHead ?? '' ?>
</head>
<body>

<div class="store-notice">
  <div class="container store-notice-inner">
    <span>Curated finds. Delivered across Nigeria.</span>
    <a href="<?= asset_url('contact.php') ?>">Need help? Talk to us <span aria-hidden="true">&rarr;</span></a>
  </div>
</div>

<header class="site-header">
  <div class="container header-top">
    <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mainNav">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>

    <a href="<?= asset_url('index.php') ?>" class="logo">
      <img src="<?= e(asset_url('assets/img/logo.svg')) ?>" alt="<?= e(SITE_NAME) ?> logo" class="logo-mark">
      <span class="logo-text">
        <span class="name">OMNIA<span class="x-accent"> EDGE</span></span>
        <span class="sub">Everyday, elevated</span>
      </span>
    </a>

    <form action="<?= asset_url('shop.php') ?>" method="get" class="header-search" role="search">
      <input type="search" name="q" placeholder="Search for anything…" value="<?= e($searchQuery) ?>" aria-label="Search products">
      <button type="submit" class="search-submit" aria-label="Search">Search</button>
    </form>

    <div class="header-actions">
      <div class="header-action-wrap">
        <a href="<?= asset_url(is_customer_logged_in() ? 'my-account.php' : 'login.php') ?>" class="header-action header-action--account">
          <span class="header-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <span class="label"><?= is_customer_logged_in() ? 'Account' : 'Login' ?></span>
        </a>
        <div class="header-dropdown">
          <?php if (is_customer_logged_in()): ?>
            <a href="<?= asset_url('my-account.php') ?>">My Account</a>
            <a href="<?= asset_url('my-orders.php') ?>">My Orders</a>
            <a href="<?= asset_url('logout.php') ?>">Logout</a>
          <?php else: ?>
            <a href="<?= asset_url('login.php') ?>">Login</a>
            <a href="<?= asset_url('register.php') ?>">Register</a>
          <?php endif; ?>
          
        </div>
      </div>
      <a href="<?= asset_url('my-orders.php') ?>" class="header-action">
        <span class="header-action-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7 12 3 4 7v10l8 4 8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
        </span>
        <span class="label">Orders</span>
      </a>
      <a href="<?= asset_url('wishlist.php') ?>" class="header-action">
        <span class="header-action-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 8.8c0 5.5-8.8 10.2-8.8 10.2S3.2 14.3 3.2 8.8A4.8 4.8 0 0 1 12 6a4.8 4.8 0 0 1 8.8 2.8Z"/></svg>
          <?php if ($wishlistCount > 0): ?><span class="cart-badge"><?= (int) $wishlistCount ?></span><?php endif; ?>
        </span>
        <span class="label">Wishlist</span>
      </a>
      <a href="<?= asset_url('cart.php') ?>" class="header-action header-action--cart">
        <span class="header-action-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
          <?php if ($cartCount > 0): ?>
            <span class="cart-badge"><?= (int) $cartCount ?></span>
          <?php endif; ?>
        </span>
        <span class="label">Cart</span>
      </a>
    </div>
  </div>

  <nav class="main-nav" id="mainNav">
    <div class="container nav-scroll-wrap">
      <button type="button" class="nav-scroll-btn nav-scroll-prev" aria-label="Scroll categories left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
      </button>

      <div class="nav-links-track" id="navLinksTrack">
        <a href="<?= asset_url('index.php') ?>" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
        <a href="<?= asset_url('shop.php') ?>" class="<?= $activeNav === 'shop' ? 'active' : '' ?>">Shop</a>
        <?php foreach ($navCategories as $cat): ?>
          <?php $subs = $cat['subcategories'] ?? []; ?>
          <div class="nav-item<?= !empty($subs) ? ' has-dropdown' : '' ?>">
            <a href="<?= asset_url('shop.php') ?>?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
            <?php if (!empty($subs)): ?>
              <div class="nav-dropdown">
                <?php foreach ($subs as $sub): ?>
                  <a href="<?= asset_url('shop.php') ?>?category=<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <a href="<?= asset_url('about.php') ?>" class="<?= $activeNav === 'about' ? 'active' : '' ?>">About</a>
        <a href="<?= asset_url('contact.php') ?>" class="<?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a>
      </div>

      <button type="button" class="nav-scroll-btn nav-scroll-next" aria-label="Scroll categories right">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
  </nav>
</header>

<script>
(function () {
  var track = document.getElementById('navLinksTrack');
  var prevBtn = document.querySelector('.nav-scroll-prev');
  var nextBtn = document.querySelector('.nav-scroll-next');
  var menuToggle = document.getElementById('menuToggle');
  var mainNav = document.getElementById('mainNav');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', String(isOpen));
      menuToggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    });

    mainNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 860) {
          mainNav.classList.remove('open');
          menuToggle.setAttribute('aria-expanded', 'false');
          menuToggle.setAttribute('aria-label', 'Open menu');
        }
      });
    });
  }

  if (!track || !prevBtn || !nextBtn) return;

  function scrollByAmount(dir) {
    track.scrollBy({ left: dir * 220, behavior: 'smooth' });
  }

  prevBtn.addEventListener('click', function () { scrollByAmount(-1); });
  nextBtn.addEventListener('click', function () { scrollByAmount(1); });

  function updateArrowVisibility() {
    var maxScroll = track.scrollWidth - track.clientWidth;
    prevBtn.classList.toggle('is-disabled', track.scrollLeft <= 4);
    nextBtn.classList.toggle('is-disabled', track.scrollLeft >= maxScroll - 4);
    var wrap = track.closest('.nav-scroll-wrap');
    if (wrap) wrap.classList.toggle('has-overflow', maxScroll > 4);
  }

  track.addEventListener('scroll', updateArrowVisibility);
  window.addEventListener('resize', updateArrowVisibility);
  updateArrowVisibility();
})();
</script>