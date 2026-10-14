<?php
/**
 * OMNIA EDGE STOREFRONT
 * Shared storefront footer.
 * Requires $pdo / functions to already be loaded by the calling page.
 */
$footerCategories = get_active_categories($pdo);
$footerCategories = array_slice($footerCategories, 0, 6);
$whatsapp = get_setting('whatsapp_number'); // e.g. 2348012345678, digits only
?>

  <!-- ===== Newsletter subscribe band ===== -->
  <section class="footer-newsletter">
    <div class="container newsletter-inner">
      <div class="newsletter-copy">
        <h3>New to Omnia Edge?</h3>
        <p>Subscribe to our newsletter to get updates on our latest offers, deals and marketing campaigns.</p>
      </div>
      <form action="<?= asset_url('newsletter-subscribe.php') ?>" method="post" class="newsletter-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="newsletter-input-row">
          <input type="email" name="email" required placeholder="Enter your email address" aria-label="Email address for newsletter">
          <button type="submit" class="btn btn-primary">Subscribe</button>
        </div>
        <label class="newsletter-consent">
          <input type="checkbox" name="consent" required>
          <span>I consent to Omnia Edge processing my data to send me newsletters. Read our <a href="<?= asset_url('privacy-policy.php') ?>">Privacy Policy</a> and <a href="<?= asset_url('cookie-notice.php') ?>">Cookie Notice</a>.</span>
        </label>
        <p class="newsletter-fineprint">You can withdraw your consent at any time by clicking the Unsubscribe link at the bottom of any email we send you.</p>
      </form>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container footer-top">
      <div class="footer-grid">

        <div class="footer-about">
          <a href="<?= asset_url('index.php') ?>" class="logo">
            <img src="<?= e(asset_url('assets/img/logo.svg')) ?>" alt="<?= e(SITE_NAME) ?> logo" class="logo-mark">
            <span class="logo-text">
              <span class="name">OMNIA<span class="x-accent"> EDGE</span></span>
              <span class="sub">Everyday, elevated</span>
            </span>
          </a>
          <p><?= e(get_setting('store_description')) ?></p>
          <div class="footer-social">
            <?php if ($url = get_setting('facebook_url')): ?>
            <a href="<?= e($url) ?>" aria-label="Facebook" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 22v-9h3l1-4h-4V6.5C13 5.4 13.4 5 14.7 5H17V1.2C16.6 1.1 15.3 1 14 1c-3 0-5 1.8-5 5.1V9H6v4h3v9z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($url = get_setting('instagram_url')): ?>
            <a href="<?= e($url) ?>" aria-label="Instagram" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($url = get_setting('twitter_url')): ?>
            <a href="<?= e($url) ?>" aria-label="Twitter" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4 4 0 0 0-6.9 3.7A11.5 11.5 0 0 1 3.6 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5v.1c0 1.9 1.4 3.6 3.2 4a4 4 0 0 1-1.8.1 4 4 0 0 0 3.8 2.8A8.2 8.2 0 0 1 2 18.4a11.6 11.6 0 0 0 6.3 1.8c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.1z"/></svg>
            </a>
            <?php endif; ?>
          </div>
        </div>

        <div class="footer-links">
          <h4>Shop</h4>
          <ul>
            <li><a href="<?= asset_url('shop.php') ?>">All Products</a></li>
            <?php foreach ($footerCategories as $cat): ?>
              <li><a href="<?= asset_url('shop.php') ?>?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Need Help?</h4>
          <ul>
            <!-- TODO: create help-center.php -->
            <li><a href="<?= asset_url('help-center.php') ?>">Help Center</a></li>
            <li><a href="<?= asset_url('contact.php') ?>">Contact Us</a></li>
            <li><a href="<?= asset_url('my-orders.php') ?>">Track Orders</a></li>
            <li><a href="<?= asset_url('cart.php') ?>">Shopping Cart</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Useful Links</h4>
          <ul>
            <!-- TODO: these pages don't exist on the site yet — create them
                 or point each href at wherever the equivalent info lives. -->
            <li><a href="<?= asset_url('help-center.php') ?>?page=how-to-shop">How to Shop on Omnia Edge</a></li>
            <li><a href="<?= asset_url('help-center.php') ?>?page=delivery">Delivery Options &amp; Timelines</a></li>
            <li><a href="<?= asset_url('returns-policy.php') ?>">Returns &amp; Refunds Policy</a></li>
            <li><a href="<?= asset_url('help-center.php') ?>?page=returns">How to Return a Product</a></li>
            <li><a href="<?= asset_url('corporate-bulk.php') ?>">Corporate &amp; Bulk Purchases</a></li>
            <li><a href="<?= asset_url('dispute-resolution.php') ?>">Dispute Resolution Policy</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>About Omnia Edge</h4>
          <ul>
            <li><a href="<?= asset_url('about.php') ?>">About Us</a></li>
            <li><a href="<?= asset_url('terms.php') ?>">Terms &amp; Conditions</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Privacy</h4>
          <ul>
            <li><a href="<?= asset_url('privacy-policy.php') ?>">Privacy Policy</a></li>
            <!-- TODO: create cookie-notice.php -->
            <li><a href="<?= asset_url('cookie-notice.php') ?>">Cookie Notice</a></li>
            <li><a href="<?= asset_url('terms.php') ?>">Terms of Use</a></li>
          </ul>
        </div>

        <div class="footer-links footer-contact">
          <h4>Contact Us</h4>
          <ul>
            <li><strong><?= e(SITE_NAME) ?></strong></li>
            <?php if ($addr = get_setting('address')): ?><li><?= e($addr) ?></li><?php endif; ?>
            <?php if ($phone = get_setting('phone')): ?><li>Phone: <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
            <?php if ($whatsapp): ?><li>WhatsApp: <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener"><?= e($whatsapp) ?></a></li><?php endif; ?>
            <?php if ($email = get_setting('email')): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          </ul>
        </div>

      </div>
    </div>

        <!-- ===== Payment methods =====
          TODO: confirm which of these Omnia Edge actually accepts, and swap
         in real gateway logos (Paystack, Flutterwave, etc.) if you have
         them as image assets — these are plain text badges for now. -->
    <div class="container footer-payments">
      <span class="payments-label">Payment Methods</span>
      <div class="payment-badges">
        <span class="payment-badge">Visa</span>
        <span class="payment-badge">Mastercard</span>
        <span class="payment-badge">Verve</span>
        <span class="payment-badge">Paystack</span>
        <span class="payment-badge">Bank Transfer</span>
        <span class="payment-badge">Pay on Delivery</span>
      </div>
    </div>

    <div class="container footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
      <div class="legal-links">
        <a href="<?= asset_url('privacy-policy.php') ?>">Privacy Policy</a>
        <a href="<?= asset_url('terms.php') ?>">Terms &amp; Conditions</a>
      </div>
    </div>
  </footer>

<?php if ($whatsapp): ?>
  <a class="whatsapp-chat-fab" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3.1.8.8-3-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.7-.3-1.4-.7-2-1.3-.5-.5-1-1.1-1.4-1.7-.1-.2 0-.4.1-.5l.4-.5c.1-.1.2-.3.2-.4.1-.1.1-.3 0-.4-.1-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.2-.9.9-.9 2.2s.9 2.6 1.1 2.8c.1.2 1.9 2.9 4.6 4 .6.3 1.1.4 1.5.6.6.2 1.2.2 1.6.1.5-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.2-.4-.3Z"/></svg>
    <span>Chat with us</span>
  </a>
<?php endif; ?>

<script src="<?= e(asset_url('assets/js/main.js')) ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>