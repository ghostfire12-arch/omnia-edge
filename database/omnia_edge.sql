-- =====================================================================
-- OMNIA EDGE STRATEGIC SERVICES — DATABASE SCHEMA
-- "Anything Sellable, Delivered."
--
-- Import this file via phpMyAdmin or:
--   mysql -u USERNAME -p DATABASE_NAME < omnia_edge.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table: admins
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: users (customers)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'Nigeria',
  `status` ENUM('active','blocked') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: products
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(60) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(12,2) DEFAULT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `brand` VARCHAR(100) DEFAULT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_featured` (`featured`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: product_images
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `order_number` VARCHAR(40) NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'cod',
  `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `order_status` ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(30) DEFAULT NULL,
  `shipping_address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'Nigeria',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_number` (`order_number`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_status` (`order_status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: order_items
-- Product name/price are snapshotted at time of order so historical
-- orders remain accurate even if the product changes or is deleted.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(180) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: cart
-- Supports both guest carts (session_id) and logged-in carts (user_id)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(191) DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_session` (`session_id`),
  KEY `idx_cart_user` (`user_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: wishlists
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishlists` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlists_user_product` (`user_id`, `product_id`),
  KEY `idx_wishlists_product` (`product_id`),
  CONSTRAINT `fk_wishlists_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wishlists_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: settings (key/value store for store-wide configuration)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: contact_messages
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `subject` VARCHAR(200) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DEMO / SAMPLE DATA
-- =====================================================================

-- Default admin login: admin@omniaedgeltm.com / Admin@12345
-- (Change this password immediately after first login.)
INSERT INTO `admins` (`name`, `email`, `password`) VALUES
('Store Administrator', 'admin@omniaedgeltm.com', '$2y$10$TnKanLGpIQKs1keJTX9L.OnB8DHr42Wu9hhUEftrPcaq25qvtnU8G');

-- Store settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'Omnia Edge Strategic Services'),
('site_url', 'https://omniaedgeltm.com'),
('company_tagline', 'Anything Sellable, Delivered.'),
('logo', 'assets/img/logo.png'),
('phone', '+234 800 000 0000'),
('email', 'support@omniaedgeltm.com'),
('address', 'Lagos, Nigeria'),
('shipping_fee', '2500'),
('currency', 'NGN'),
('currency_symbol', '₦'),
('store_description', 'Omnia Edge Strategic Services brings everything sellable closer to you — electronics, fashion, home essentials and more, delivered with reliability across Nigeria.'),
('facebook_url', ''),
('instagram_url', ''),
('twitter_url', ''),
('whatsapp_number', ''),
('payment_methods', 'cod,bank_transfer'),
('paystack_public_key', ''),
('paystack_secret_key', '');

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `status`) VALUES
(1, 'Electronics', 'electronics', 'TVs, audio, power and everyday tech.', 'asset:category/Television-deals.png', 'active'),
(2, 'Fashion', 'fashion', 'Clothing, shoes and everyday style.', 'asset:category/Fashion-deals.png', 'active'),
(3, 'Phones & Tablets', 'phones-tablets', 'Smartphones, tablets and accessories.', 'asset:category/Phones-Tablet.png', 'active'),
(4, 'Computers', 'computers', 'Laptops, desktops and computer accessories.', 'asset:category/Computing-deals.png', 'active'),
(5, 'Beauty', 'beauty', 'Skincare, fragrances and personal care.', 'asset:category/Beauty-deals.png', 'active'),
(6, 'Home & Living', 'home-living', 'Furniture, decor and household essentials.', 'asset:category/Home-Office.png', 'active'),
(7, 'Sports', 'sports', 'Fitness gear, sneakers and sports equipment.', 'asset:category/Sneakers-deals.png', 'active'),
(8, 'Automotive', 'automotive', 'Car parts, tools and vehicle accessories.', NULL, 'active'),
(9, 'Accessories', 'accessories', 'Watches, chargers and mobile accessories.', 'asset:category/Mobile-Accessoriesdeals.png', 'active'),
(10, 'Office', 'office', 'Office furniture, stationery and supplies.', NULL, 'active'),
(12, 'Others', 'others', 'Everything else that is sellable.', NULL, 'active'),
(13, 'Groceries & Beverages', 'groceries-beverages', 'Food staples, drinks and pantry essentials.', 'asset:category/Beverages-deals.png', 'active'),
(14, 'Appliances', 'appliances', 'Freezers, blenders, irons, fans and home appliances.', 'asset:category/Appliances-deals.png', 'active'),
(15, 'Kids & Baby', 'kids-baby', 'Baby carriers, kids clothing and essentials.', 'asset:category/Kids-Babydeals.png', 'active');

-- Products (catalog seeded from the product images shipped in assets/img)
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `description`, `price`, `sale_price`, `stock_quantity`, `brand`, `featured`, `status`) VALUES
(1, 1, 'Oraimo Space Buds Wireless Earbuds', 'oraimo-space-buds-wireless-earbuds', 'ELEC-EB-001', 'Wireless earbuds for calls, music and everyday listening.', 45000.00, NULL, 24, 'Oraimo', 1, 'active'),
(2, 3, 'Infinix Smart 20 128GB Smartphone', 'infinix-smart-20-128gb-smartphone', 'PHN-SM-002', 'Infinix Smart 20 smartphone with a large display and everyday performance.', 320000.00, 289000.00, 12, 'Infinix', 1, 'active'),
(3, 9, 'Oraimo Watch 6N Smartwatch', 'oraimo-watch-6n-smartwatch', 'ACC-WT-003', 'Oraimo Watch 6N with a 1.83-inch colour screen for fitness and notifications.', 120000.00, NULL, 30, 'Oraimo', 1, 'active'),
(4, 14, '18-Inch Standing Electric Fan', '18-inch-standing-electric-fan', 'APP-FN-004', 'Sturdy 18-inch standing fan with a heavy round base and adjustable height.', 68000.00, 55000.00, 40, 'Generic', 1, 'active'),
(5, 2, 'Six-Pair Ankle Socks Set', 'six-pair-ankle-socks-set', 'FSH-SK-005', 'Six pairs of soft cotton ankle socks in black, grey and white.', 18500.00, NULL, 60, 'Wearhouse', 0, 'active'),
(6, 14, 'Hansen Electric Iron', 'hansen-electric-iron', 'HOM-IR-006', 'Hansen electric iron for convenient garment pressing.', 22000.00, NULL, 25, 'Hansen', 0, 'active'),
(7, 6, 'Cosmetic Storage Box', 'cosmetic-storage-box', 'HOM-CS-007', 'Storage box for organising cosmetics and personal-care items.', 15000.00, 12500.00, 50, 'HomeEase', 0, 'active'),
(8, 4, 'Asus Vivobook 14 Flip Laptop', 'asus-vivobook-14-flip-laptop', 'COM-LP-008', 'Asus Vivobook 14 Flip 2-in-1 touchscreen laptop.', 480000.00, NULL, 8, 'Asus', 1, 'active'),
(9, 5, 'Nivea Men Deep Anti-Perspirant', 'nivea-men-deep-anti-perspirant', 'BEA-PF-009', 'Nivea Men Deep anti-perspirant roll-on.', 32000.00, NULL, 35, 'Nivea', 0, 'active'),
(10, 1, 'Hithium HeroEE 2kWh Portable Power Station', 'hithium-heroee-2kwh-power-station', 'AUT-PS-010', 'Hithium HeroEE portable 2kWh power station.', 450000.00, NULL, 15, 'Hithium', 0, 'active'),
(11, 1, 'P9 Wireless Bluetooth Headphones', 'p9-wireless-bluetooth-headphones', 'ELEC-HP-011', 'P9 over-ear wireless Bluetooth stereo headphones for games and music.', 38000.00, 31000.00, 45, 'P9', 1, 'active'),
(12, 1, 'Ace Elec 20000mAh Power Bank', 'ace-elec-20000mah-power-bank', 'ELEC-PB-012', 'Ace Elec ultra-slim 20000mAh power bank.', 55000.00, NULL, 10, 'Ace Elec', 0, 'active'),
(13, 6, 'Six-Layer Shoe Rack', 'six-layer-shoe-rack', 'HOM-SR-013', 'Six-layer shoe rack for tidy home storage.', 26000.00, NULL, 18, 'HomeEase', 0, 'active'),
(14, 14, 'Aeon 90-Litre Chest Freezer', 'aeon-90-litre-chest-freezer', 'HOM-CF-014', 'Compact 90-litre chest freezer for home use.', 350000.00, NULL, 6, 'Aeon', 1, 'active'),
(15, 4, 'Foldable Aluminium Laptop Stand', 'foldable-aluminium-laptop-stand', 'COM-LS-015', 'Foldable aluminium stand for laptops and tablets.', 28000.00, NULL, 25, 'Generic', 0, 'active'),
(16, 2, 'BerryKey Mens Trench Coat', 'berrykey-mens-trench-coat', 'FSH-CT-016', 'BerryKey mens trench coat and casual blazer.', 85000.00, NULL, 14, 'BerryKey', 0, 'active'),
(18, 10, 'Bluetooth Wireless Office Mouse', 'bluetooth-wireless-office-mouse', 'OFF-MS-018', 'Quiet illuminated Bluetooth mouse for office use.', 18000.00, NULL, 30, 'Generic', 0, 'active'),
(19, 1, 'Bluetooth Mini Speaker', 'bluetooth-mini-speaker', 'ELEC-SP-019', 'Compact Bluetooth mini speaker with colourful lights.', 18000.00, NULL, 35, 'Dazzle', 0, 'active'),
(20, 1, 'FOOME 50000mAh Power Bank', 'foome-50000mah-power-bank', 'ELEC-PB-020', 'FOOME AA50 50000mAh power bank with light.', 70000.00, NULL, 12, 'FOOME', 1, 'active'),
(21, 10, 'Furgle PU Leather Gaming Chair', 'furgle-pu-leather-gaming-chair', 'OFF-CH-021', 'Ergonomic Furgle PU leather gaming chair with headrest, lumbar pillow and footrest.', 180000.00, NULL, 9, 'Furgle', 0, 'active'),
(22, 2, 'Danami Printed Hoodie', 'danami-printed-hoodie', 'FSH-HD-022', 'Danami Los Angeles printed hoodie.', 45000.00, NULL, 22, 'Danami', 0, 'active'),
(23, 4, 'NOVASPARK Gaming Mouse', 'novaspark-gaming-mouse', 'COM-MS-023', 'NOVASPARK wired optical gaming mouse.', 24000.00, NULL, 20, 'NOVASPARK', 0, 'active'),
(24, 2, 'High Quality Tank Top', 'high-quality-tank-top', 'FSH-TT-024', 'Comfortable everyday tank top.', 15000.00, NULL, 45, 'Generic', 0, 'active'),
(25, 10, 'JIYUE 128GB USB Flash Drive', 'jiyue-128gb-usb-flash-drive', 'OFF-USB-025', 'JIYUE USB 3.0 128GB flash drive.', 16000.00, NULL, 40, 'JIYUE', 0, 'active'),
(26, 2, 'Kings Kartel Casual Jacket', 'kings-kartel-casual-jacket', 'FSH-JK-026', 'Kings Kartel stylish cream and wine jacket.', 60000.00, NULL, 16, 'Kings Kartel', 0, 'active'),
(27, 6, 'Mythco Wireless Motion Lamp', 'mythco-wireless-motion-lamp', 'HOM-LP-027', 'Mythco wireless body induction LED motion lamp.', 30000.00, NULL, 24, 'Mythco', 0, 'active'),
(29, 1, 'Ligtech Stereo Headset', 'ligtech-stereo-headset', 'ELEC-HS-029', 'Ligtech stereo headset for calls and entertainment.', 26000.00, NULL, 32, 'Ligtech', 0, 'active'),
(30, 1, 'Sony PlayStation 4 Pro 1TB', 'sony-playstation-4-pro-1tb', 'ELEC-GM-030', 'Sony PlayStation 4 Pro console with 1TB storage.', 280000.00, NULL, 7, 'Sony', 1, 'active'),
(31, 2, 'TOUPHY Mens Long Sleeve T-Shirt', 'touphy-mens-long-sleeve-t-shirt', 'FSH-TS-031', 'TOUPHY mens long sleeve T-shirt.', 22000.00, NULL, 36, 'TOUPHY', 0, 'active'),
(32, 4, 'Yaticrab Mini Alloy Laptop Stand', 'yaticrab-mini-alloy-laptop-stand', 'COM-LS-032', 'Yaticrab mini alloy laptop stand.', 24000.00, NULL, 24, 'Yaticrab', 0, 'active'),
(33, 4, 'Asus Vivobook 14 Laptop', 'asus-vivobook-14-laptop', 'COM-LP-033', 'Asus Vivobook 14 laptop for work, study and everyday use.', 420000.00, NULL, 8, 'Asus', 1, 'active'),
(34, 1, 'Music Pioneer Airpods', 'music-pioneer-airpods', 'ELEC-EB-034', 'Wireless Airpods for music, calls and everyday listening.', 42000.00, NULL, 25, 'Music Pioneer', 0, 'active'),
(35, 14, 'Silver Crest 2-in-1 Blender & Dry Grinder', 'silver-crest-2-in-1-blender-dry-grinder', 'APP-BL-035', 'Silver Crest 8500W 2-in-1 blender with a 2L jug and a dry grinding cup.', 65000.00, NULL, 10, 'Silver Crest', 0, 'active'),
(36, 1, 'Silver Eagle Over-Ear Headphones', 'silver-eagle-over-ear-headphones', 'ELEC-HP-036', 'Silver Eagle wireless over-ear computer headphones.', 55000.00, NULL, 20, 'Silver Eagle', 1, 'active'),
(37, 1, 'Hikers 55-Inch Smart TV', 'hikers-55-inch-smart-tv', 'ELEC-TV-037', 'Hikers 55-inch VIDAA smart TV with Wi-Fi, Dolby Audio, Bluetooth and HDMI.', 520000.00, 465000.00, 9, 'Hikers', 1, 'active'),
(38, 13, 'Big Bull Rice 50kg', 'big-bull-rice-50kg', 'GRO-RC-038', 'Big Bull premium parboiled long-grain rice, 50kg bag. Product of Nigeria.', 98000.00, NULL, 30, 'Big Bull', 1, 'active'),
(39, 13, 'Martini Rosso Red Vermouth', 'martini-rosso-red-vermouth', 'BEV-VM-039', 'Martini Rosso Italian red vermouth. For buyers aged 18 and over only.', 21000.00, NULL, 24, 'Martini', 0, 'active');

-- Product images
INSERT INTO `product_images` (`product_id`, `image`, `sort_order`) VALUES
(1, 'asset:Oraimo space Buds.jpg', 0),
(2, 'asset:Infinix Smart 20 6.78 4GB.jpg', 0),
(3, 'asset:Oraimo Watch 6N 1.83touch Screen Colour SmartWatch.jpg', 0),
(4, 'asset:1 (1).jpg', 0),
(5, 'asset:Six Pieces.jpg', 0),
(6, 'asset:HANSEN Electric Iron Pressing.jpg', 0),
(7, 'asset:Cosmetic Storage Box.jpg', 0),
(8, 'asset:Asus Vivobook 14 Flip 2in1 touchscreen.jpg', 0),
(9, 'asset:NIVEA MEN Deep Anti-perspirant roll-on For Men.jpg', 0),
(10, 'asset:HITHIUM HeroEE 2kwh.jpg', 0),
(11, 'asset:P9 Wireless Bluetooth Stereo For Games And Music.jpg', 0),
(12, 'asset:Ace Elec 20000 MAh Ultra Slim.jpg', 0),
(13, 'asset:6 LAyers Shoe.jpg', 0),
(14, 'asset:Aeon 90Litres Chest Freezer.jpg', 0),
(15, 'asset:Aluminun alloy laptop stand foldable desktop tablet.jpg', 0),
(16, 'asset:BerryKEy Mens Tranch Coats Casual Blazers.jpg', 0),
(18, 'asset:Bluetooth 2.4G silent Illuminated Office Wireless MOUSE.jpg', 0),
(19, 'asset:Bluetooth Mini Speaker Dazzle Colourful LIghts Loud.jpg', 0),
(20, 'asset:FOOME AA50 50000mAH Power Bank With Light.jpg', 0),
(21, 'asset:FURGLE Comfortable PU Leather.jpg', 0),
(22, 'asset:Danami Los Angeles California Printed Hoodie- Nude.jpg', 0),
(23, 'asset:NOVASPARK Wired optical and electrical gaming mouse.jpg', 0),
(24, 'asset:Hot HIgh Quality New Tank Top.jpg', 0),
(25, 'asset:JIYUE Flash Drive 3.0 128gb.jpg', 0),
(26, 'asset:Kingskartel Kings Kartel stylish Cream and wine  jacket.jpg', 0),
(27, 'asset:Mythco wireless body Induction LAmp LED Motion.jpg', 0),
(29, 'asset:Ligtech Stereo Headset.jpg', 0),
(30, 'asset:Sony Playstation 4Pro 1TB.jpg', 0),
(31, 'asset:TOUPHY Men''s Long Sleeve T-Shirt.jpg', 0),
(32, 'asset:Yaticrab Mini Alloy Laptop Stand.jpg', 0),
(33, 'asset:Asus Vivobook 14 Intel Corei5.jpg', 0),
(34, 'asset:Music pioneer Airpods.jpg', 0),
(35, 'asset:Silver Crest.jpg', 0),
(36, 'asset:Silver Eagle  Over-ear Computer Headhphones Wireless.jpg', 0),
(37, 'asset:1.jpg', 0),
(38, 'asset:Big Bull Rice 50Kg.jpg', 0),
(39, 'asset:Martini Rosso REd Vermouth.jpg', 0);

-- Brute-force protection for the customer and admin sign-in forms
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(20) NOT NULL,
  `identifier` CHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_lookup` (`scope`, `identifier`, `attempted_at`),
  KEY `idx_login_ip` (`scope`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
