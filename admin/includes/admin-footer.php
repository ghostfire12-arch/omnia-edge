<?php
/**
 * Admin shell — bottom half. Optional before including:
 *   $useCharts     bool    load Chart.js
 *   $adminScripts  string  page-specific <script> markup (inline is allowed by the CSP)
 */
?>
    <footer class="adm-foot">
      <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
      <span>Press <kbd>Ctrl</kbd> + <kbd>K</kbd> to search anything</span>
    </footer>
  </main>
</div>

<!-- Command palette -->
<div class="palette" data-palette hidden>
  <div class="palette__scrim" data-palette-close></div>
  <div class="palette__box" role="dialog" aria-modal="true" aria-label="Search">
    <label class="palette__input"><?= admin_icon('search') ?><input type="search" placeholder="Search products, orders, customers or jump to a page…" autocomplete="off" data-palette-input aria-label="Search"><kbd>Esc</kbd></label>
    <div class="palette__results" data-palette-results role="listbox"></div>
    <div class="palette__hint"><span><kbd>↑</kbd><kbd>↓</kbd> to move</span><span><kbd>Enter</kbd> to open</span></div>
  </div>
</div>

<!-- Confirm dialog (replaces window.confirm) -->
<div class="modal" data-confirm-modal hidden>
  <div class="modal__scrim" data-confirm-cancel></div>
  <div class="modal__box" role="alertdialog" aria-modal="true" aria-labelledby="cfTitle" aria-describedby="cfText">
    <span class="modal__ico"><?= admin_icon('alert') ?></span>
    <h2 id="cfTitle">Are you sure?</h2>
    <p id="cfText" data-confirm-text></p>
    <div class="modal__actions">
      <button type="button" class="btn btn--ghost" data-confirm-cancel>Cancel</button>
      <button type="button" class="btn btn--danger" data-confirm-ok>Yes, continue</button>
    </div>
  </div>
</div>

<div class="toasts" data-toasts aria-live="polite"></div>

<script>
window.OE_PAGES = <?= json_encode([
    ['Dashboard', asset_url('admin/dashboard.php')], ['Reports', asset_url('admin/reports.php')],
    ['Products', asset_url('admin/products.php')], ['Add product', asset_url('admin/product-add.php')],
    ['Low-stock products', asset_url('admin/products.php?stock=low')], ['Categories', asset_url('admin/categories.php')],
    ['Import products', asset_url('admin/import-url.php')], ['Orders', asset_url('admin/orders.php')],
    ['Pending orders', asset_url('admin/orders.php?status=pending')], ['Customers', asset_url('admin/customers.php')],
    ['Messages', asset_url('admin/messages.php')], ['Settings', asset_url('admin/settings.php')],
    ['Change password', asset_url('admin/settings.php?tab=security')], ['View store', asset_url('index.php')],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php if (!empty($useCharts)): ?><script src="<?= e(asset_url('assets/vendor/chart.umd.min.js')) ?>"></script><?php endif; ?>
<script src="<?= e(asset_url('assets/js/admin.js')) ?>?v=<?= (int) @filemtime(ROOT_PATH . '/assets/js/admin.js') ?>"></script>
<?= $adminScripts ?? '' ?>
</body>
</html>
