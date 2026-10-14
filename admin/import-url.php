<?php
/**
 * OMNIA EDGE — Admin: import products from free-licence image URLs.
 *
 * One product per line:
 *   image URL | product name | category slug | price | sale price | stock | brand | featured (1/0) | description
 * Only the first four fields are required.
 *
 * Security
 * - Admin login + CSRF required.
 * - Only https URLs on an exact allowlist of image CDNs (blocks SSRF:
 *   the server can never be pointed at localhost, LAN or other sites).
 * - Redirects are not followed, size is capped, and download times out.
 * - Every file must be a real JPEG/PNG/WEBP, and is RE-ENCODED with GD,
 *   which strips metadata and destroys any payload hidden in the file.
 */
require_once __DIR__ . '/includes/bootstrap.php';

const IMPORT_ALLOWED_HOSTS = ['images.unsplash.com', 'images.pexels.com'];
const IMPORT_MAX_BYTES     = 8 * 1024 * 1024;
const IMPORT_MAX_LINES     = 40;
const IMPORT_MAX_EDGE      = 1000; // px — longest side after resizing

function import_validate_url(string $url): ?string
{
    if (strlen($url) > 2000) return 'URL is too long.';
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https') return 'URL must start with https://';
    if (isset($p['user']) || isset($p['pass']) || isset($p['port'])) return 'URL format not allowed.';
    if (!in_array(strtolower($p['host'] ?? ''), IMPORT_ALLOWED_HOSTS, true)) {
        return 'Only images from ' . implode(' or ', IMPORT_ALLOWED_HOSTS) . ' can be imported.';
    }
    return null;
}

/**
 * CA bundle for HTTPS verification. Windows/WAMP PHP often ships without
 * one, so fall back to the Mozilla bundle in includes/certs (from certifi,
 * not web-accessible). Verification is NEVER switched off.
 */
function import_ca_bundle(): ?string
{
    foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $f) {
        if ($f && is_readable($f)) return null; // PHP already has one configured
    }
    $bundled = ROOT_PATH . '/includes/certs/cacert.pem';
    return is_readable($bundled) ? $bundled : null;
}

/** Download into memory with hard limits. Returns [bytes|null, error|null]. */
function import_download(string $url): array
{
    $ca = import_ca_bundle();
    if (function_exists('curl_init')) {
        $buf = '';
        $tooBig = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'OmniaEdgeImporter/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: image/webp,image/jpeg,image/png'],
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buf, &$tooBig) {
                $buf .= $chunk;
                if (strlen($buf) > IMPORT_MAX_BYTES) { $tooBig = true; return 0; }
                return strlen($chunk);
            },
        ]);
        if ($ca) curl_setopt($ch, CURLOPT_CAINFO, $ca);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($tooBig) return [null, 'Image is larger than 8MB.'];
        if ($code !== 200) return [null, 'Download failed' . ($code ? " (HTTP $code)" : ($err ? ": $err" : '')) . '.'];
        return [$buf, null];
    }
    // Fallback when the curl extension is disabled.
    $ctx = stream_context_create([
        'http' => ['follow_location' => 0, 'timeout' => 30, 'user_agent' => 'OmniaEdgeImporter/1.0', 'ignore_errors' => true],
        'ssl'  => array_filter(['verify_peer' => true, 'verify_peer_name' => true, 'cafile' => $ca]),
    ]);
    $data = @file_get_contents($url, false, $ctx, 0, IMPORT_MAX_BYTES + 1);
    if ($data === false) return [null, 'Download failed.'];
    if (strlen($data) > IMPORT_MAX_BYTES) return [null, 'Image is larger than 8MB.'];
    $status = $http_response_header[0] ?? '';
    if (!preg_match('#\s200\s#', $status)) return [null, 'Download failed (' . trim($status) . ').'];
    return [$data, null];
}

/** Validate + re-encode to a fresh JPEG in uploads/products. Returns [filename|null, error|null]. */
function import_store_image(string $bytes): array
{
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return [null, 'The URL did not return a JPG, PNG or WEBP image.'];
    }
    [$w, $h] = $info;
    if ($w < 200 || $h < 200 || $w * $h > 40000000) return [null, 'Image dimensions are not usable.'];
    if (!function_exists('imagecreatefromstring')) return [null, 'The PHP GD extension is required (enable it in WAMP > PHP > PHP extensions > gd).'];

    $src = @imagecreatefromstring($bytes);
    if (!$src) return [null, 'Image could not be decoded.'];
    $scale = min(1, IMPORT_MAX_EDGE / max($w, $h));
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency onto white
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $ok = imagejpeg($dst, UPLOAD_PATH . $name, 86);
    imagedestroy($dst);
    return $ok ? [$name, null] : [null, 'Could not save the image.'];
}

$cats = get_active_categories($pdo);
$catBySlug = [];
foreach ($cats as $c) $catBySlug[$c['slug']] = (int) $c['id'];

$results = [];
$input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    @set_time_limit(300);
    $input = (string) ($_POST['lines'] ?? '');
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $input)), fn($l) => $l !== '' && $l[0] !== '#'));

    if (count($lines) > IMPORT_MAX_LINES) {
        $results[] = ['ok' => false, 'name' => '', 'msg' => 'Import at most ' . IMPORT_MAX_LINES . ' lines at a time.'];
        $lines = [];
    }

    $insProduct = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, brand, description, price, sale_price, stock_quantity, featured, status)
                                 VALUES (:cat, :name, :slug, :sku, :brand, :description, :price, :sale, :stock, :featured, \'active\')');
    $insImage = $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (:pid, :image, 0)');
    $dupCheck = $pdo->prepare('SELECT id FROM products WHERE name = :name LIMIT 1');

    foreach ($lines as $n => $line) {
        $f = array_map('trim', explode('|', $line));
        [$url, $name, $catSlug, $price] = array_pad($f, 4, '');
        $sale  = (float) ($f[4] ?? 0);
        $stock = isset($f[5]) && $f[5] !== '' ? max(0, (int) $f[5]) : 20;
        $brand = clean_input($f[6] ?? '');
        $feat  = (($f[7] ?? '0') === '1') ? 1 : 0;
        $desc  = clean_input($f[8] ?? '');
        $name  = clean_input($name);
        $price = (float) $price;
        $row   = ['ok' => false, 'name' => $name ?: 'Line ' . ($n + 1), 'msg' => ''];

        if ($err = import_validate_url($url))          { $row['msg'] = $err; $results[] = $row; continue; }
        if ($name === '' || mb_strlen($name) > 180)    { $row['msg'] = 'Product name is missing or too long.'; $results[] = $row; continue; }
        if (!isset($catBySlug[$catSlug]))              { $row['msg'] = "Unknown category slug \"$catSlug\"."; $results[] = $row; continue; }
        if ($price <= 0 || $price > 100000000)         { $row['msg'] = 'Price must be a positive number.'; $results[] = $row; continue; }
        $dupCheck->execute([':name' => $name]);
        if ($dupCheck->fetch())                        { $row['msg'] = 'Skipped: a product with this name already exists.'; $results[] = $row; continue; }

        [$bytes, $err] = import_download($url);
        if ($err) { $row['msg'] = $err; $results[] = $row; continue; }
        [$file, $err] = import_store_image($bytes);
        if ($err) { $row['msg'] = $err; $results[] = $row; continue; }

        try {
            $pdo->beginTransaction();
            $insProduct->execute([
                ':cat' => $catBySlug[$catSlug], ':name' => $name, ':slug' => unique_slug($pdo, 'products', $name),
                ':sku' => strtoupper(substr(preg_replace('/[^a-z]/', '', $catSlug), 0, 3)) . '-' . strtoupper(bin2hex(random_bytes(3))),
                ':brand' => $brand, ':description' => $desc ?: $name . '.', ':price' => $price,
                ':sale' => ($sale > 0 && $sale < $price) ? $sale : null, ':stock' => $stock, ':featured' => $feat,
            ]);
            $pid = (int) $pdo->lastInsertId();
            $insImage->execute([':pid' => $pid, ':image' => $file]);
            $pdo->commit();
            $row['ok'] = true;
            $row['msg'] = 'Added';
            $row['id'] = $pid;
            $row['img'] = product_image_url($file);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink(UPLOAD_PATH . $file);
            error_log('Import error: ' . $e->getMessage());
            $row['msg'] = 'Database error — product not saved.';
        }
        $results[] = $row;
    }
}

$okTotal = count(array_filter($results, fn($r) => $r['ok']));
if ($okTotal) admin_log('import', "Imported $okTotal product" . ($okTotal === 1 ? '' : 's') . ' from image links');

$pageTitle = 'Import products | ' . SITE_NAME;
$activePage = 'import';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="page-head">
  <div><div class="crumbs"><a href="<?= e(asset_url('admin/products.php')) ?>">Products</a><span>/</span><span>Import</span></div>
    <h1>Import products from image links</h1><p>Add many products at once using free-licence photos from Unsplash or Pexels.</p></div>
</div>

<?php if ($results): ?>
<section class="card" data-stagger>
  <div class="card__head"><h2><?= $okTotal ?> of <?= count($results) ?> imported</h2><a class="btn btn--ghost btn--sm" href="<?= e(asset_url('admin/products.php?sort=newest')) ?>">View products</a></div>
  <div class="cat-grid">
    <?php foreach ($results as $r): ?>
      <article class="cat-card">
        <?php if (!empty($r['img'])): ?><div class="preview-card__img"><img src="<?= e($r['img']) ?>" alt=""></div><?php endif; ?>
        <div class="cat-card__body">
          <h3 style="font:600 .92rem var(--body)"><?= e($r['name']) ?></h3>
          <p style="margin-top:6px"><span class="pill pill--<?= $r['ok'] ? 'green' : 'red' ?>"><i></i><?= e($r['msg']) ?></span></p>
          <?php if (!empty($r['id'])): ?><p style="margin-top:8px"><a href="<?= e(asset_url('admin/product-edit.php?id=' . (int) $r['id'])) ?>">Edit product</a></p><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="grid grid--2-1 mt">
  <section class="card">
    <div class="card__head"><div><h2>Products to import</h2><p>One product per line, fields separated by <code>|</code>. Up to <?= IMPORT_MAX_LINES ?> lines at a time.</p></div></div>
    <form method="post" class="stack" style="gap:14px" data-import-form>
      <?= csrf_field() ?>
      <div class="field"><label for="impLines" class="sr-only">Lines</label>
        <textarea id="impLines" name="lines" style="min-height:300px;font:13px/1.6 Consolas, monospace" placeholder="https://images.unsplash.com/photo-...?w=1000 | Leather Office Chair | office | 145000 | | 10 | Generic | 0 | Ergonomic chair with lumbar support." required><?= e($input) ?></textarea>
        <small data-line-count>0 lines</small></div>
      <button class="btn btn--primary" data-import-btn><?= admin_icon('upload') ?>Import products</button>
    </form>
  </section>
  <section class="card">
    <div class="card__head"><h2>How it works</h2></div>
    <ol class="feed" style="list-style:none">
      <li><span class="feed__dot tone-blue">1</span><div><p>Find a photo on Unsplash or Pexels and copy its image address (it starts with <code>https://images.unsplash.com</code> or <code>https://images.pexels.com</code>).</p></div></li>
      <li><span class="feed__dot tone-blue">2</span><div><p>Write: <code>image URL | name | category | price</code>. Optional extras: <code>| sale price | stock | brand | featured 1/0 | description</code>.</p></div></li>
      <li><span class="feed__dot tone-blue">3</span><div><p>Each image is downloaded, checked and resized to 1000px before it is saved. Duplicate names are skipped.</p></div></li>
    </ol>
    <p class="label" style="margin:6px 0 8px">Category slugs</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px"><?php foreach ($cats as $c): ?><button type="button" class="pill pill--blue" data-insert="<?= e($c['slug']) ?>" style="border:0;cursor:pointer" title="Copy"><?= e($c['slug']) ?></button><?php endforeach; ?></div>
  </section>
</div>
<?php
$adminScripts = <<<'JS'
<script>
(function () {
  var t = document.getElementById('impLines'), out = document.querySelector('[data-line-count]'), f = document.querySelector('[data-import-form]');
  function count() { var n = t.value.split(/\n/).filter(function (l) { return l.trim() && l.trim()[0] !== '#'; }).length; out.textContent = n + ' line' + (n === 1 ? '' : 's'); out.style.color = n > 40 ? 'var(--red)' : ''; }
  t.addEventListener('input', count); count();
  document.querySelectorAll('[data-insert]').forEach(function (b) { b.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.insert); OE.toast('Copied ' + b.dataset.insert); }); });
  f.addEventListener('submit', function () { var b = f.querySelector('[data-import-btn]'); b.disabled = true; b.textContent = 'Importing… this can take a minute'; });
})();
</script>
JS;
include __DIR__ . '/includes/admin-footer.php';
