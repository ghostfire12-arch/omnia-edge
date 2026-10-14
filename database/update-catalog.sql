-- =====================================================================
-- OMNIA EDGE — catalog update (run on an EXISTING omnia_edge database)
-- Safe to run more than once.
--
-- * Links every category tile image in assets/img/category/
-- * Adds 3 categories: Groceries & Beverages, Appliances, Kids & Baby
-- * Renames products whose names did not match their picture
-- * Adds the 3 product images that were not in the catalog yet
--   (Hikers 55" TV, Big Bull Rice 50kg, Martini Rosso)
--
-- phpMyAdmin: select the omnia_edge database > Import > choose this file.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------- Categories ----------
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `status`) VALUES
(13, 'Groceries & Beverages', 'groceries-beverages', 'Food staples, drinks and pantry essentials.', 'asset:category/Beverages-deals.png', 'active'),
(14, 'Appliances', 'appliances', 'Freezers, blenders, irons, fans and home appliances.', 'asset:category/Appliances-deals.png', 'active'),
(15, 'Kids & Baby', 'kids-baby', 'Baby carriers, kids clothing and essentials.', 'asset:category/Kids-Babydeals.png', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `image` = VALUES(`image`);

UPDATE `categories` SET `image` = 'asset:category/Television-deals.png', `description` = 'TVs, audio, power and everyday tech.' WHERE `id` = 1;
UPDATE `categories` SET `image` = 'asset:category/Fashion-deals.png'            WHERE `id` = 2;
UPDATE `categories` SET `image` = 'asset:category/Phones-Tablet.png'            WHERE `id` = 3;
UPDATE `categories` SET `image` = 'asset:category/Computing-deals.png'          WHERE `id` = 4;
UPDATE `categories` SET `image` = 'asset:category/Beauty-deals.png'             WHERE `id` = 5;
UPDATE `categories` SET `image` = 'asset:category/Home-Office.png'              WHERE `id` = 6;
UPDATE `categories` SET `image` = 'asset:category/Sneakers-deals.png', `description` = 'Fitness gear, sneakers and sports equipment.' WHERE `id` = 7;
UPDATE `categories` SET `image` = 'asset:category/Mobile-Accessoriesdeals.png', `description` = 'Watches, chargers and mobile accessories.' WHERE `id` = 9;
UPDATE `categories` SET `description` = 'Office furniture, stationery and supplies.' WHERE `id` = 10;

-- ---------- Products whose name did not match the picture ----------
UPDATE `products` SET `category_id` = 14, `name` = '18-Inch Standing Electric Fan', `slug` = '18-inch-standing-electric-fan', `sku` = 'APP-FN-004',
  `brand` = 'Generic', `description` = 'Sturdy 18-inch standing fan with a heavy round base and adjustable height.' WHERE `id` = 4;
UPDATE `products` SET `name` = 'Six-Pair Ankle Socks Set', `slug` = 'six-pair-ankle-socks-set', `sku` = 'FSH-SK-005',
  `description` = 'Six pairs of soft cotton ankle socks in black, grey and white.' WHERE `id` = 5;
UPDATE `products` SET `name` = 'P9 Wireless Bluetooth Headphones', `slug` = 'p9-wireless-bluetooth-headphones', `sku` = 'ELEC-HP-011',
  `description` = 'P9 over-ear wireless Bluetooth stereo headphones for games and music.' WHERE `id` = 11;
UPDATE `products` SET `category_id` = 10, `name` = 'Furgle PU Leather Gaming Chair', `slug` = 'furgle-pu-leather-gaming-chair', `sku` = 'OFF-CH-021',
  `description` = 'Ergonomic Furgle PU leather gaming chair with headrest, lumbar pillow and footrest.' WHERE `id` = 21;
UPDATE `products` SET `category_id` = 14, `name` = 'Silver Crest 2-in-1 Blender & Dry Grinder', `slug` = 'silver-crest-2-in-1-blender-dry-grinder', `sku` = 'APP-BL-035',
  `description` = 'Silver Crest 8500W 2-in-1 blender with a 2L jug and a dry grinding cup.' WHERE `id` = 35;

-- ---------- Products that were in the wrong category ----------
UPDATE `products` SET `category_id` = 14 WHERE `id` IN (6, 14);   -- iron, chest freezer -> Appliances
UPDATE `products` SET `category_id` = 6  WHERE `id` = 13;          -- shoe rack -> Home & Living
UPDATE `products` SET `category_id` = 1  WHERE `id` = 10;          -- power station -> Electronics

-- ---------- New products from images that were not in the catalog ----------
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `description`, `price`, `sale_price`, `stock_quantity`, `brand`, `featured`, `status`) VALUES
(37, 1,  'Hikers 55-Inch Smart TV', 'hikers-55-inch-smart-tv', 'ELEC-TV-037', 'Hikers 55-inch VIDAA smart TV with Wi-Fi, Dolby Audio, Bluetooth and HDMI.', 520000.00, 465000.00, 9, 'Hikers', 1, 'active'),
(38, 13, 'Big Bull Rice 50kg', 'big-bull-rice-50kg', 'GRO-RC-038', 'Big Bull premium parboiled long-grain rice, 50kg bag. Product of Nigeria.', 98000.00, NULL, 30, 'Big Bull', 1, 'active'),
(39, 13, 'Martini Rosso Red Vermouth', 'martini-rosso-red-vermouth', 'BEV-VM-039', 'Martini Rosso Italian red vermouth. For buyers aged 18 and over only.', 21000.00, NULL, 24, 'Martini', 0, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `category_id` = VALUES(`category_id`), `description` = VALUES(`description`);

DELETE FROM `product_images` WHERE `product_id` IN (37, 38, 39) AND `image` LIKE 'asset:%';
INSERT INTO `product_images` (`product_id`, `image`, `sort_order`) VALUES
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
