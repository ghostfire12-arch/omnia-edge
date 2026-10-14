# Omnia Edge Strategic Services — Installation Guide

"Anything Sellable, Delivered."

---

## 0. What changed in this version (October 2026)

**New homepage** (`index.php`, `assets/css/landing.css`, `assets/js/landing.js`)
- Full-width hero slider: crossfade, Ken Burns zoom, staggered headline,
  progress bar, dots, arrows, swipe and keyboard control, pauses on hover.
- Category rail with drag-to-scroll, collection banners with zoom + shine,
  product tabs (All / Featured / On sale / Under ₦30,000) with staggered
  transitions and "Show more", quick-view modal with add-to-cart,
  live deal-of-the-day countdown, aisle carousels, brand marquee,
  sticky header, scroll reveals and a back-to-top progress ring.
- All motion is disabled automatically for visitors who ask their device
  for reduced motion.

**Catalog** — every image in `assets/img` is now a named product or a
category tile. Five products had names that did not match their photo and
were corrected. Existing database? Import `database/update-catalog.sql`
(safe to run more than once). Fresh install? Import `database/omnia_edge.sql`.

**Security hardening**
- `includes/security.php`: HttpOnly + SameSite session cookie (Secure on
  HTTPS), strict session mode, idle timeout and periodic id rotation,
  security headers (CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS
  on HTTPS).
- Login brute-force protection for customers and admins (5 failures per
  account or 20 per IP in 15 minutes), timing-safe sign-in.
- Fixed open redirect in the wishlist, fixed order-confirmation page
  showing other people's orders, guest carts no longer store session ids
  in the database.
- `.htaccess` files block `includes/`, `database/`, `.sql`, `.md` and
  hidden files, and stop PHP from running inside `uploads/`.
- Debug output turns on only for localhost — never on the live domain.
- Admin can now change the password in Admin > Settings; a warning shows
  until the default password is changed.

**Import products from image links** (Admin > Import Products)
- Paste one product per line: `image URL | name | category slug | price | sale price | stock | brand | featured | description`.
- Only free-licence images from images.unsplash.com / images.pexels.com are accepted (blocks the server
  being pointed at other sites). Downloads are size-capped, redirects are refused, and every image is
  re-encoded with GD before it is saved.
- HTTPS is verified with `includes/certs/cacert.pem` (Mozilla CA bundle) when PHP has none configured —
  the usual cause of "SSL certificate problem" on WAMP. Verification is never switched off.
- After copying updated PHP files into WAMP, if a change does not show up, PHP's OPcache is serving the
  old copy: restart WAMP (or visit opcache-refresh-7f3a.php from this computer) to clear it.

**Admin console (redesigned)** — `admin/`
- New shell: collapsible sidebar with live badges (pending orders, unread messages, low stock), notification bell,
  light/dark theme, and a **Ctrl+K command palette** that searches products, orders, customers and pages.
- **Dashboard**: animated KPI cards with sparklines and period comparison, revenue + orders chart, monthly target
  gauge (set the target in Settings), order status, sales by category, payment methods, top states, best sellers,
  restock list, an order-time heatmap and an admin activity feed. Charts use Chart.js 4.4.4 bundled in
  `assets/vendor/` (no CDN, works with the Content Security Policy).
- **Reports** (`reports.php`): any date range, revenue trend, category and weekday breakdowns, top 20 products,
  by-state table, print and CSV.
- **Products**: search, filters, sorting, list/grid view, live/hidden switch and featured star per row, bulk
  publish/hide/feature/move/restock/delete, CSV export. Add/edit form with live store preview, drag-and-drop images,
  choose the main image, discount hints and a SKU generator.
- **Orders**: status tabs with counts, filters, bulk status updates, CSV export. Order page has a progress
  timeline, one-click "next step", internal notes, optional restock when cancelling, and a printable invoice.
- **Customers**: lifetime value, repeat-buyer rate, block/unblock, and a profile page with spending chart,
  orders, favourite products and wishlist.
- **Messages**: two-pane inbox with unread state, reply by email or WhatsApp.
- **Settings**: store details, password & security (with failed sign-in log), and **sample sales data** you can
  load or remove in one click (tagged `DEMO-…`, never mixed with real orders, stock untouched).
- Security: every change is POST + CSRF, sign-out is POST-only, admin pages send `no-store`, CSV exports are
  protected against spreadsheet formula injection, and important changes are written to `admin_activity`.

**WAMP** — the project lives at `C:\wamp64\www\omnia-edge` and opens at
`http://localhost/omnia-edge/`. Subfolder installs are supported.

Plain PHP + MySQL e-commerce platform. No framework, no Composer, no
build step. Every page is a normal `.php` file.

---

## 1. Requirements

- PHP 8.0+ with the `pdo_mysql` and `fileinfo` extensions enabled
- MySQL or MariaDB
- Any standard web server (Apache, or PHP's own built-in server for
  local testing)

---

## 2. Local setup (XAMPP / WAMP / MAMP)

Root-relative links (`/assets/css/style.css`, `/shop.php`, etc.) are
used throughout the site so it works identically on any domain once
deployed. That means **the project folder must be served as the web
root**, not as a subfolder — otherwise assets and links will 404.

**Recommended — PHP's built-in server (matches production exactly):**

1. Start MySQL in your XAMPP/WAMP/MAMP control panel (you don't need
   Apache for this method).
2. In phpMyAdmin, create a database named `omnia_edge` and import
   `database/omnia_edge.sql`.
3. Open `includes/config.php` and confirm `DB_USER` / `DB_PASSWORD`
   match your local MySQL (XAMPP defaults are usually `root` / empty
   password, which is already set).
4. In a terminal, `cd` into this project folder (the one containing
   `index.php`) and run:
   ```
   php -S localhost:8000
   ```
5. Visit `http://localhost:8000/index.php`.

**Alternative — Apache virtual host:**

If you'd rather use Apache, create a virtual host whose
`DocumentRoot` points directly at this project folder (not a
subfolder inside `htdocs`), map a local hostname to it in your
hosts file, and restart Apache. Do **not** place the project inside
`htdocs/some-subfolder/` and browse to it as a subfolder — the
root-relative links will not resolve correctly that way.

---

## 3. Live deployment (cPanel / shared hosting)

1. Create a MySQL database and database user in cPanel, and note the
   database name, username, password, and host (usually `localhost`).
2. Import `database/omnia_edge.sql` via phpMyAdmin.
3. Upload every file/folder in this project to `public_html/` (or the
   document root for your domain) via File Manager or FTP, preserving
   the folder structure exactly as it is here.
4. Edit `includes/config.php`:
   - Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` to your live
     database credentials.
   - Confirm `SITE_URL` is set to `https://omniaedgeltm.com` (no
     trailing slash).
   - Set `APP_DEBUG` to `false` (it should already be `false`).
5. Make sure `uploads/products/` and `uploads/categories/` are
   writable by the web server (755 is usually sufficient on cPanel).
6. Visit your domain and confirm the homepage loads.
7. Log into `/admin/login.php` with the default admin account below
   and **change the password immediately**.

---

## 4. Default admin login

```
Email:    
Password: 
```

Change this from the admin panel as soon as you log in for the first
time — do not leave the demo password active on a live site.

---

## 5. Folder structure

```
omnia-edge/
├── admin/                  Admin panel (separate auth from customers)
├── assets/
│   ├── css/style.css       Single shared stylesheet
│   ├── js/main.js          Vanilla JS (mobile menu, cart, gallery, uploads)
│   └── img/                Logo, placeholder image
├── database/
│   └── omnia_edge.sql      Full schema + demo data
├── includes/
│   ├── config.php          DB credentials + site constants (edit this)
│   ├── db.php               PDO connection
│   ├── functions.php        Shared helpers (settings, formatting, uploads…)
│   ├── auth.php              Customer + admin authentication
│   ├── cart-functions.php    Cart logic (guest + logged-in)
│   ├── header.php / footer.php   Shared storefront layout
│   └── product-card.php       Reusable product card partial
├── uploads/
│   ├── products/            Product images (uploaded via admin)
│   └── categories/          Category images (uploaded via admin)
├── index.php, shop.php, product.php, cart.php, checkout.php, …
└── README.md
```

---

## 6. Notes

- All database queries use PDO prepared statements.
- Passwords are hashed with PHP's `password_hash()` / verified with
  `password_verify()`.
- Every POST form is protected with a CSRF token
  (`includes/functions.php` → `csrf_field()` / `require_csrf()`).
- Order line items snapshot the product name and price at time of
  purchase, so historical orders stay accurate even if a product is
  later edited or deleted.
- No `.htaccess` routing is required — every page is a plain `.php`
  file reachable at its own URL (e.g. `/product.php?id=15`).
