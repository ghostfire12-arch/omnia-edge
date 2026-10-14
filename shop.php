<?php
require_once __DIR__ . '/includes/cart-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    require_csrf();
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty       = max(1, (int) ($_POST['quantity'] ?? 1));
    [$ok, $msg] = cart_add($pdo, $productId, $qty);
    set_flash($ok ? 'success' : 'error', $msg);
    redirect($_SERVER['REQUEST_URI']);
}

// ---------------- Read filters from query string ----------------
$search       = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$minPrice     = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$maxPrice     = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$sort         = $_GET['sort'] ?? 'newest';
$perPage      = 12;
$page         = get_page_number();

$where  = ["p.status = 'active'"];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE :search1 OR p.description LIKE :search2 OR p.sku LIKE :search3)';
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

$activeCategory = null;
if ($categorySlug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = :slug AND status = 'active'");
    $stmt->execute([':slug' => $categorySlug]);
    $activeCategory = $stmt->fetch();
    if ($activeCategory) {
        $where[] = 'p.category_id = :category_id';
        $params['category_id'] = $activeCategory['id'];
    }
}

if ($minPrice !== null) {
    $where[] = 'COALESCE(NULLIF(p.sale_price, 0), p.price) >= :min_price';
    $params['min_price'] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'COALESCE(NULLIF(p.sale_price, 0), p.price) <= :max_price';
    $params['max_price'] = $maxPrice;
}

$whereSql = implode(' AND ', $where);

$orderBy = match ($sort) {
    'price_low'  => 'effective_price ASC',
    'price_high' => 'effective_price DESC',
    'name_az'    => 'p.name ASC',
    'featured'   => 'p.featured DESC, p.created_at DESC',
    default      => 'p.created_at DESC',
};

// ---------------- Count total for pagination ----------------
$countSql = "SELECT COUNT(*) FROM products p WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResults = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = paginate_offset($page, $perPage);

// ---------------- Fetch products ----------------
$sql = "SELECT p.*, c.name AS category_name,
               COALESCE(NULLIF(p.sale_price, 0), p.price) AS effective_price
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $whereSql
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

$categories = get_active_categories($pdo);

$pageTitle = $activeCategory ? $activeCategory['name'] . ' | ' . SITE_NAME : 'Shop All Products | ' . SITE_NAME;
$metaDescription = $activeCategory
    ? 'Shop ' . $activeCategory['name'] . ' at ' . SITE_NAME . '. ' . get_setting('company_tagline')
    : get_setting('store_description');
$canonicalPath = '/shop.php';
$activeNav = 'shop';

$flash = get_flash();

// Build a base URL (without page param) for pagination + toolbar links.
$queryForBase = $_GET;
unset($queryForBase['page']);
$baseUrl = asset_url('shop.php') . (!empty($queryForBase) ? '?' . http_build_query($queryForBase) : '');

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="container">
    <h1><?= $activeCategory ? e($activeCategory['name']) : 'Shop All Products' ?></h1>
    <div class="breadcrumb"><a href="<?= asset_url('index.php') ?>">Home</a> / <?= $activeCategory ? e($activeCategory['name']) : 'Shop' ?></div>
  </div>
</div>

<div class="container section-tight">

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" data-autohide><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="shop-layout">
  <aside class="filter-panel">
    <form method="get" action="<?= asset_url('shop.php') ?>">
      <?php if ($categorySlug !== ''): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>

      <div class="filter-group">
        <h4>Category</h4>
        <div class="filter-option">
          <input type="radio" name="category_radio" id="cat-all" <?= $categorySlug === '' ? 'checked' : '' ?> onclick="window.location='<?= e(asset_url('shop.php')) ?>'">
          <label for="cat-all" style="margin:0;font-weight:500;">All Categories</label>
        </div>
        <?php foreach ($categories as $cat): ?>
          <div class="filter-option">
            <input type="radio" name="category_radio" id="cat-<?= (int) $cat['id'] ?>" <?= $categorySlug === $cat['slug'] ? 'checked' : '' ?>
              onclick="window.location='<?= e(asset_url('shop.php')) ?>?category=<?= e($cat['slug']) ?>'">
            <label for="cat-<?= (int) $cat['id'] ?>" style="margin:0;font-weight:500;display:flex;align-items:center;gap:8px;">
              <?php if (!empty($cat['image'])): ?>
                <img src="<?= e(category_image_url($cat['image'])) ?>" alt="" style="width:24px;height:24px;object-fit:contain;">
              <?php endif; ?>
              <?= e($cat['name']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="filter-group">
        <h4>Price Range (₦)</h4>
        <div class="price-inputs">
          <input type="number" name="min_price" placeholder="Min" min="0" value="<?= e($minPrice !== null ? (string) $minPrice : '') ?>">
          <span>&ndash;</span>
          <input type="number" name="max_price" placeholder="Max" min="0" value="<?= e($maxPrice !== null ? (string) $maxPrice : '') ?>">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-sm">Apply Filters</button>
      <?php if ($minPrice !== null || $maxPrice !== null || $categorySlug !== '' || $search !== ''): ?>
        <a href="<?= asset_url('shop.php') ?>" class="btn btn-ghost btn-block btn-sm" style="margin-top:8px;">Clear Filters</a>
      <?php endif; ?>
    </form>
  </aside>

  <div class="shop-main">
    <div class="shop-toolbar">
      <span class="result-count"><?= (int) $totalResults ?> product<?= $totalResults === 1 ? '' : 's' ?> found<?= $search !== '' ? ' for "' . e($search) . '"' : '' ?></span>
      <form method="get" action="<?= asset_url('shop.php') ?>" id="sortForm">
        <?php foreach ($_GET as $key => $val) : if ($key === 'sort' || $key === 'page') continue; ?>
          <input type="hidden" name="<?= e($key) ?>" value="<?= e($val) ?>">
        <?php endforeach; ?>
        <select name="sort" onchange="document.getElementById('sortForm').submit()">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
          <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
          <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
          <option value="name_az" <?= $sort === 'name_az' ? 'selected' : '' ?>>Name: A-Z</option>
          <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
        </select>
      </form>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty-state">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg></div>
        <h3>No products found</h3>
        <p>Try adjusting your search or filters.</p>
        <a href="<?= asset_url('shop.php') ?>" class="btn btn-primary">Clear Filters</a>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product): ?>
          <?php include __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <?= render_pagination($page, $totalPages, $baseUrl) ?>
    <?php endif; ?>
  </div>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
