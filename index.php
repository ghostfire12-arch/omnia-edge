<?php
/**
 * OMNIA EDGE — Landing page
 * Styles: assets/css/landing.css   Scripts: assets/js/landing.js
 * Everything on this page is driven by the products/categories tables.
 */
require_once __DIR__ . '/includes/cart-functions.php';

$pageTitle       = SITE_NAME . ' — ' . get_setting('company_tagline', 'Anything Sellable, Delivered.');
$metaDescription = get_setting('store_description');
$canonicalPath   = '/index.php';
$activeNav       = 'home';

// "Add to cart" posts from the cards and quick view on this page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    require_csrf();
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty       = max(1, (int) ($_POST['quantity'] ?? 1));
    [$ok, $msg] = cart_add($pdo, $productId, $qty);
    set_flash($ok ? 'success' : 'error', $msg);
    redirect(asset_url('index.php') . '#shop');
}

/* ------------------------------------------------------------------
   DATA
   ------------------------------------------------------------------ */
$categories = get_active_categories($pdo);
// Categories with artwork first, so the rail opens with pictures.
usort($categories, function ($a, $b) {
    $ai = empty($a['image']) ? 1 : 0;
    $bi = empty($b['image']) ? 1 : 0;
    return $ai <=> $bi ?: strcmp($a['name'], $b['name']);
});

// One query for every active product + its primary image (no N+1).
$products = $pdo->query(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
            (SELECT pi.image FROM product_images pi
              WHERE pi.product_id = p.id
              ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.status = 'active'
      ORDER BY p.featured DESC, p.created_at DESC, p.id DESC"
)->fetchAll();

$byId = [];
foreach ($products as $p) { $byId[(int) $p['id']] = $p; }
$pick = function (int $id) use ($byId) { return $byId[$id] ?? null; };

// Category product counts for the rail.
$catCounts = [];
foreach ($products as $p) {
    $cid = (int) $p['category_id'];
    $catCounts[$cid] = ($catCounts[$cid] ?? 0) + 1;
}

// Deal of the day: the biggest naira saving among in-stock products on sale.
$deal = null;
foreach ($products as $p) {
    if ((int) $p['stock_quantity'] <= 0 || !has_discount($p)) continue;
    $saving = (float) $p['price'] - effective_price($p);
    if (!$deal || $saving > ((float) $deal['price'] - effective_price($deal))) $deal = $p;
}

// Hero product slide: a featured product on sale that isn't the deal, else any featured product.
$heroProduct = null;
foreach ($products as $p) {
    if ($deal && $p['id'] === $deal['id']) continue;
    if ((int) $p['featured'] === 1 && has_discount($p)) { $heroProduct = $p; break; }
}
if (!$heroProduct) {
    foreach ($products as $p) { if ((int) $p['featured'] === 1) { $heroProduct = $p; break; } }
}

// Collection banners: first product found from each list of ids (falls back gracefully).
$firstOf = function (array $ids) use ($pick) {
    foreach ($ids as $id) { if ($found = $pick($id)) return $found; }
    return null;
};
$banners = array_values(array_filter([
    ['p' => $firstOf([37, 30, 8]),  'title' => 'Big screens, fair prices',       'text' => 'Smart TVs, consoles and laptops for the whole house.', 'cat' => 'electronics', 'tone' => 'navy'],
    ['p' => $firstOf([10, 20, 12]), 'title' => 'Power that outlasts the outage', 'text' => 'Power stations and power banks that keep you on.',   'cat' => 'electronics', 'tone' => 'saffron'],
    ['p' => $firstOf([35, 14, 6]),  'title' => 'Kitchen work, halved',           'text' => 'Blenders, freezers and irons for busy homes.',        'cat' => 'appliances',  'tone' => 'ice'],
], fn($b) => $b['p'] !== null));

// Two aisles shown as carousels — the two categories with the most products.
arsort($catCounts);
$aisleIds = array_slice(array_keys($catCounts), 0, 2);
$aisles = [];
foreach ($aisleIds as $cid) {
    foreach ($categories as $c) {
        if ((int) $c['id'] === (int) $cid) {
            $aisles[] = ['cat' => $c, 'items' => array_values(array_filter($products, fn($p) => (int) $p['category_id'] === (int) $cid))];
        }
    }
}

// Brands for the marquee.
$brands = [];
foreach ($products as $p) {
    $b = trim((string) $p['brand']);
    if ($b !== '' && strcasecmp($b, 'Generic') !== 0) $brands[$b] = true;
}
$brands = array_keys($brands);

$dealEndsAt = (new DateTime('tomorrow', new DateTimeZone('Africa/Lagos')))->getTimestamp() * 1000;
$cheapCap   = 30000;
$initialShow = 12;

/* ------------------------------------------------------------------
   RENDER HELPERS
   ------------------------------------------------------------------ */
function lp_card(array $p, string $returnTo, bool $inRail = false): void
{
    $img      = product_image_url($p['primary_image'] ?? null);
    $url      = asset_url('product.php?id=' . (int) $p['id']);
    $stock    = (int) $p['stock_quantity'];
    $isOut    = $stock <= 0;
    $pct      = discount_percent($p);
    $now      = effective_price($p);
    $filters  = ['all'];
    if ((int) $p['featured'] === 1) $filters[] = 'featured';
    if ($pct > 0)                   $filters[] = 'sale';
    if ($now <= 30000)              $filters[] = 'budget';

    $qv = [
        'id'    => (int) $p['id'],
        'name'  => $p['name'],
        'cat'   => $p['category_name'] ?? '',
        'brand' => $p['brand'] ?? '',
        'desc'  => $p['description'] ?? '',
        'img'   => $img,
        'url'   => $url,
        'now'   => format_price($now),
        'was'   => $pct > 0 ? format_price($p['price']) : '',
        'pct'   => $pct,
        'stock' => $stock,
    ];
    ?>
    <article class="lp-card<?= $inRail ? ' lp-card--rail' : '' ?>" data-filters="<?= e(implode(' ', $filters)) ?>">
      <div class="lp-card__media">
        <a href="<?= e($url) ?>" class="lp-card__img" tabindex="-1" aria-hidden="true">
          <img src="<?= e($img) ?>" alt="" loading="lazy" width="300" height="300">
        </a>
        <?php if ($pct > 0): ?>
          <span class="lp-tag lp-tag--sale">−<?= (int) $pct ?>%</span>
        <?php elseif ((int) $p['featured'] === 1): ?>
          <span class="lp-tag">Featured</span>
        <?php endif; ?>
        <?php if ($isOut): ?><span class="lp-tag lp-tag--out">Sold out</span><?php endif; ?>

        <div class="lp-card__actions">
          <button type="button" class="lp-iconbtn" data-quickview='<?= e(json_encode($qv, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>' aria-label="Quick view: <?= e($p['name']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <form action="<?= e(asset_url('wishlist.php')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <button type="submit" class="lp-iconbtn" aria-label="Save <?= e($p['name']) ?> to wishlist">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.8c0 5.5-8.8 10.2-8.8 10.2S3.2 14.3 3.2 8.8A4.8 4.8 0 0 1 12 6a4.8 4.8 0 0 1 8.8 2.8Z"/></svg>
            </button>
          </form>
        </div>

        <?php if (!$isOut): ?>
          <form class="lp-card__cart" action="<?= e(asset_url('index.php')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <button type="submit">Add to cart</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="lp-card__body">
        <?php if (!empty($p['category_name'])): ?><span class="lp-card__cat"><?= e($p['category_name']) ?></span><?php endif; ?>
        <h3 class="lp-card__name"><a href="<?= e($url) ?>"><?= e($p['name']) ?></a></h3>
        <div class="lp-price">
          <strong><?= format_price($now) ?></strong>
          <?php if ($pct > 0): ?><s><?= format_price($p['price']) ?></s><?php endif; ?>
        </div>
        <?php if (!$isOut && $stock <= 10): ?>
          <span class="lp-card__stock">Only <?= $stock ?> left</span>
        <?php endif; ?>
      </div>
    </article>
    <?php
}

$returnTo = asset_url('index.php');
$flash = get_flash();

$extraHead = '<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&display=swap" rel="stylesheet">'
           . '<link rel="stylesheet" href="' . e(asset_url('assets/css/landing.css')) . '?v=' . (int) @filemtime(ROOT_PATH . '/assets/css/landing.css') . '">';
$extraScripts = '<script src="' . e(asset_url('assets/js/landing.js')) . '?v=' . (int) @filemtime(ROOT_PATH . '/assets/js/landing.js') . '"></script>';

include __DIR__ . '/includes/header.php';
?>

<main class="lp" id="top">

<?php if ($flash): ?>
  <div class="lp-toast lp-toast--<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" role="status" data-toast><?= e($flash['message']) ?></div>
<?php endif; ?>

<!-- =============================== HERO SLIDER =============================== -->
<section class="lp-hero" data-slider aria-roledescription="carousel" aria-label="Featured offers">
  <div class="lp-hero__track">

    <article class="lp-slide lp-slide--brand is-active" aria-roledescription="slide" aria-label="1 of 3">
      <img class="lp-slide__bg" src="<?= e(asset_url('assets/img/omnia-hero-section.png.png')) ?>" alt="" fetchpriority="high">
      <div class="lp-slide__shade"></div>
      <div class="lp-wrap lp-slide__content">
        <p class="lp-slide__kicker" data-anim>Phones, fashion, appliances, groceries</p>
        <h1 class="lp-slide__title" data-anim>Anything sellable, delivered.</h1>
        <p class="lp-slide__text" data-anim>Order today and we deliver to your door anywhere in Nigeria. Pay by card, transfer or cash on delivery.</p>
        <div class="lp-slide__ctas" data-anim>
          <a class="lp-btn lp-btn--light" href="#shop">Start shopping</a>
          <a class="lp-btn lp-btn--ghost" href="#categories">Browse categories</a>
        </div>
      </div>
    </article>

    <article class="lp-slide lp-slide--banner" aria-roledescription="slide" aria-label="2 of 3">
      <img class="lp-slide__bannerbg" src="<?= e(asset_url('assets/img/admin-img/slider.jpg')) ?>" alt="" aria-hidden="true" loading="lazy">
      <a href="<?= e(asset_url('shop.php')) ?>" class="lp-slide__bannerlink" aria-label="Top brands, best prices — shop now">
        <img class="lp-slide__banner" src="<?= e(asset_url('assets/img/admin-img/slider.jpg')) ?>" alt="Top brands, best prices. Only on Omnia Edge." loading="lazy">
      </a>
    </article>

    <?php if ($heroProduct): $hp = $heroProduct; $hpPct = discount_percent($hp); ?>
    <article class="lp-slide lp-slide--product" aria-roledescription="slide" aria-label="3 of 3">
      <div class="lp-wrap lp-slide__split">
        <div class="lp-slide__content">
          <p class="lp-slide__kicker" data-anim><?= e($hp['category_name'] ?? 'Featured') ?></p>
          <h2 class="lp-slide__title" data-anim><?= e($hp['name']) ?></h2>
          <p class="lp-slide__text" data-anim>
            Now <strong><?= format_price(effective_price($hp)) ?></strong>
            <?php if ($hpPct > 0): ?> — <s><?= format_price($hp['price']) ?></s>, you save <?= (int) $hpPct ?>%<?php endif; ?>
          </p>
          <div class="lp-slide__ctas" data-anim>
            <a class="lp-btn lp-btn--light" href="<?= e(asset_url('product.php?id=' . (int) $hp['id'])) ?>">See the details</a>
          </div>
        </div>
        <div class="lp-slide__art" data-anim>
          <span class="lp-slide__disc"></span>
          <img src="<?= e(product_image_url($hp['primary_image'])) ?>" alt="<?= e($hp['name']) ?>" loading="lazy">
        </div>
      </div>
    </article>
    <?php endif; ?>

  </div>

  <div class="lp-hero__ui">
    <div class="lp-wrap lp-hero__bar">
      <div class="lp-hero__dots" role="tablist" aria-label="Choose slide"></div>
      <div class="lp-hero__arrows">
        <button type="button" class="lp-round" data-prev aria-label="Previous slide"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
        <button type="button" class="lp-round" data-next aria-label="Next slide"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
      </div>
    </div>
    <span class="lp-hero__progress" aria-hidden="true"><i></i></span>
  </div>
</section>

<!-- =============================== TICKER =============================== -->
<div class="lp-ticker" aria-label="Why shop with us">
  <div class="lp-ticker__row">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <ul <?= $i ? 'aria-hidden="true"' : '' ?>>
        <li>Delivery across all 36 states</li>
        <li>Pay on delivery available</li>
        <li>Secure card payments with Paystack</li>
        <li><?= count($products) ?> products in <?= count($categories) ?> categories</li>
        <li>Support on WhatsApp, phone and email</li>
      </ul>
    <?php endfor; ?>
  </div>
</div>

<!-- =============================== CATEGORIES =============================== -->
<section class="lp-section" id="categories">
  <div class="lp-wrap">
    <header class="lp-head" data-reveal>
      <h2>Shop by category</h2>
      <div class="lp-head__side">
        <button type="button" class="lp-round lp-round--dark" data-rail-prev="cats" aria-label="Scroll categories left"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
        <button type="button" class="lp-round lp-round--dark" data-rail-next="cats" aria-label="Scroll categories right"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
      </div>
    </header>
  </div>
  <div class="lp-rail lp-rail--cats" data-rail="cats">
    <?php foreach ($categories as $i => $cat): $n = $catCounts[(int) $cat['id']] ?? 0; ?>
      <a class="lp-cat" href="<?= e(asset_url('shop.php')) ?>?category=<?= e($cat['slug']) ?>" data-reveal style="--d: <?= min($i, 8) * 60 ?>ms">
        <span class="lp-cat__img">
          <?php if (!empty($cat['image'])): ?>
            <img src="<?= e(category_image_url($cat['image'])) ?>" alt="" loading="lazy" width="300" height="300">
          <?php else: ?>
            <span class="lp-cat__fallback"><?= e(mb_substr($cat['name'], 0, 1)) ?></span>
          <?php endif; ?>
        </span>
        <span class="lp-cat__name"><?= e($cat['name']) ?></span>
        <span class="lp-cat__count"><?= $n ? $n . ' item' . ($n > 1 ? 's' : '') : 'Coming soon' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- =============================== COLLECTION BANNERS =============================== -->
<?php if ($banners): ?>
<section class="lp-section lp-section--tight">
  <div class="lp-wrap lp-banners">
    <?php foreach ($banners as $i => $b): $bp = $b['p']; ?>
      <a class="lp-banner lp-banner--<?= e($b['tone']) ?><?= $i === 0 ? ' lp-banner--big' : '' ?>"
         href="<?= e(asset_url('shop.php')) ?>?category=<?= e($b['cat']) ?>" data-reveal style="--d: <?= $i * 120 ?>ms">
        <span class="lp-banner__copy">
          <strong><?= e($b['title']) ?></strong>
          <span><?= e($b['text']) ?></span>
          <em>From <?= format_price(effective_price($bp)) ?></em>
        </span>
        <span class="lp-banner__art"><img src="<?= e(product_image_url($bp['primary_image'])) ?>" alt="<?= e($bp['name']) ?>" loading="lazy"></span>
        <span class="lp-banner__shine" aria-hidden="true"></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- =============================== PRODUCT TABS =============================== -->
<section class="lp-section" id="shop">
  <div class="lp-wrap">
    <header class="lp-head" data-reveal>
      <h2>Everything in store</h2>
      <div class="lp-tabs" role="tablist" aria-label="Filter products">
        <button type="button" role="tab" class="lp-tab is-active" aria-selected="true" data-filter="all">All</button>
        <button type="button" role="tab" class="lp-tab" aria-selected="false" data-filter="featured">Featured</button>
        <button type="button" role="tab" class="lp-tab" aria-selected="false" data-filter="sale">On sale</button>
        <button type="button" role="tab" class="lp-tab" aria-selected="false" data-filter="budget">Under <?= format_price($cheapCap) ?></button>
        <span class="lp-tabs__ink" aria-hidden="true"></span>
      </div>
    </header>

    <?php if (!$products): ?>
      <div class="lp-empty">
        <h3>No products yet</h3>
        <p>Add products from the admin panel and they will appear here.</p>
      </div>
    <?php else: ?>
      <div class="lp-grid" data-grid data-initial="<?= $initialShow ?>">
        <?php foreach ($products as $p) lp_card($p, $returnTo . '#shop'); ?>
      </div>
      <div class="lp-more">
        <p class="lp-more__count" data-count aria-live="polite"></p>
        <button type="button" class="lp-btn lp-btn--dark" data-more>Show more products</button>
        <a class="lp-btn lp-btn--outline" href="<?= e(asset_url('shop.php')) ?>">Open the full shop</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- =============================== DEAL OF THE DAY =============================== -->
<?php if ($deal): $dPct = discount_percent($deal); $dStock = (int) $deal['stock_quantity']; ?>
<section class="lp-deal" id="deal">
  <div class="lp-wrap lp-deal__inner">
    <div class="lp-deal__art" data-reveal>
      <span class="lp-deal__badge">−<?= (int) $dPct ?>%</span>
      <img src="<?= e(product_image_url($deal['primary_image'])) ?>" alt="<?= e($deal['name']) ?>" loading="lazy">
    </div>
    <div class="lp-deal__copy" data-reveal style="--d: 120ms">
      <p class="lp-deal__label">Deal of the day</p>
      <h2><?= e($deal['name']) ?></h2>
      <p class="lp-deal__desc"><?= e($deal['description']) ?></p>
      <div class="lp-price lp-price--xl">
        <strong><?= format_price(effective_price($deal)) ?></strong>
        <s><?= format_price($deal['price']) ?></s>
      </div>
      <div class="lp-count" data-countdown="<?= (int) $dealEndsAt ?>" aria-label="Time left on this deal">
        <span><b data-h>00</b>hours</span>
        <span><b data-m>00</b>minutes</span>
        <span><b data-s>00</b>seconds</span>
      </div>
      <div class="lp-stockbar" style="--fill: <?= max(8, min(100, (int) round($dStock / 20 * 100))) ?>%">
        <span>Only <?= $dStock ?> left at this price</span><i></i>
      </div>
      <form action="<?= e(asset_url('index.php')) ?>" method="post" class="lp-deal__cta">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int) $deal['id'] ?>">
        <input type="hidden" name="quantity" value="1">
        <button type="submit" class="lp-btn lp-btn--saffron">Add to cart</button>
        <a class="lp-btn lp-btn--ghost" href="<?= e(asset_url('product.php?id=' . (int) $deal['id'])) ?>">See the details</a>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =============================== AISLE CAROUSELS =============================== -->
<?php foreach ($aisles as $ai => $aisle): $key = 'aisle' . $ai; ?>
<section class="lp-section<?= $ai ? ' lp-section--alt' : '' ?>">
  <div class="lp-wrap">
    <header class="lp-head" data-reveal>
      <div>
        <h2>Popular in <?= e($aisle['cat']['name']) ?></h2>
        <p class="lp-head__sub"><?= e($aisle['cat']['description']) ?></p>
      </div>
      <div class="lp-head__side">
        <a class="lp-link" href="<?= e(asset_url('shop.php')) ?>?category=<?= e($aisle['cat']['slug']) ?>">See all <?= count($aisle['items']) ?></a>
        <button type="button" class="lp-round lp-round--dark" data-rail-prev="<?= $key ?>" aria-label="Scroll left"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
        <button type="button" class="lp-round lp-round--dark" data-rail-next="<?= $key ?>" aria-label="Scroll right"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
      </div>
    </header>
  </div>
  <div class="lp-rail" data-rail="<?= $key ?>">
    <?php foreach ($aisle['items'] as $p) lp_card($p, $returnTo, true); ?>
  </div>
</section>
<?php endforeach; ?>

<!-- =============================== HOW DELIVERY WORKS =============================== -->
<section class="lp-section">
  <div class="lp-wrap">
    <header class="lp-head" data-reveal><h2>From order to doorstep</h2></header>
    <ol class="lp-steps">
      <li data-reveal style="--d: 0ms"><b>1</b><strong>Place your order</strong><span>Add items to your cart and check out as a guest or with an account.</span></li>
      <li data-reveal style="--d: 100ms"><b>2</b><strong>Choose how to pay</strong><span>Card, bank transfer or cash when your package arrives.</span></li>
      <li data-reveal style="--d: 200ms"><b>3</b><strong>We pack and dispatch</strong><span>You can follow every status change under My Orders.</span></li>
      <li data-reveal style="--d: 300ms"><b>4</b><strong>Delivered to you</strong><span>Not right? Our returns policy covers you.</span></li>
    </ol>
  </div>
</section>

<!-- =============================== APP BAND =============================== -->
<section class="lp-app">
  <div class="lp-wrap lp-app__inner">
    <div class="lp-app__copy" data-reveal>
      <h2>Shop on the go with OmniaX</h2>
      <p>App-only offers, quicker checkout and live order tracking from store to door.</p>
      <ul>
        <li>Save delivery addresses and pay in two taps</li>
        <li>Get notified when your order moves</li>
        <li>Wishlist syncs with your account on the web</li>
      </ul>
      <a class="lp-btn lp-btn--light" href="<?= e(asset_url('register.php')) ?>">Create your account</a>
    </div>
    <div class="lp-app__art" data-tilt data-reveal style="--d: 150ms">
      <img src="<?= e(asset_url('assets/img/admin-img/omnia-app.png')) ?>" alt="The OmniaX app showing offers and order tracking" loading="lazy">
    </div>
  </div>
</section>

<!-- =============================== BRANDS =============================== -->
<?php if ($brands): ?>
<section class="lp-brands" aria-label="Brands we stock">
  <div class="lp-brands__row">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <ul <?= $i ? 'aria-hidden="true"' : '' ?>>
        <?php foreach ($brands as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
      </ul>
    <?php endfor; ?>
  </div>
</section>
<?php endif; ?>

</main>

<!-- Quick view -->
<div class="lp-modal" data-modal hidden>
  <div class="lp-modal__backdrop" data-close></div>
  <div class="lp-modal__panel" role="dialog" aria-modal="true" aria-labelledby="qvName">
    <button type="button" class="lp-modal__close" data-close aria-label="Close quick view"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    <div class="lp-modal__img"><img alt="" data-qv-img></div>
    <div class="lp-modal__body">
      <span class="lp-card__cat" data-qv-cat></span>
      <h3 id="qvName" data-qv-name></h3>
      <div class="lp-price lp-price--lg"><strong data-qv-now></strong><s data-qv-was></s></div>
      <p data-qv-desc></p>
      <p class="lp-modal__meta" data-qv-meta></p>
      <form action="<?= e(asset_url('index.php')) ?>" method="post" class="lp-modal__form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="" data-qv-id>
        <div class="lp-qty">
          <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
          <input type="number" name="quantity" value="1" min="1" max="99" aria-label="Quantity" data-qv-qty>
          <button type="button" data-step="1" aria-label="Increase quantity">+</button>
        </div>
        <button type="submit" class="lp-btn lp-btn--dark" data-qv-add>Add to cart</button>
      </form>
      <a class="lp-link" href="#" data-qv-url>See full details</a>
    </div>
  </div>
</div>

<a href="#top" class="lp-totop" aria-label="Back to top" data-totop>
  <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="21" class="lp-totop__track"/><circle cx="24" cy="24" r="21" class="lp-totop__ring" data-ring/><path d="M17 27l7-7 7 7" class="lp-totop__arrow"/></svg>
</a>

<?php include __DIR__ . '/includes/footer.php'; ?>
