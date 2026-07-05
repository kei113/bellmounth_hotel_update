-- ============================================================
-- Room Service / In-Room Dining — Database Migration
-- Hotel Bellmounth (Sumber Jaya)
-- ============================================================
-- INSTRUCTIONS:
--   1. Open phpMyAdmin and select database `bellmounth`
--   2. Go to "SQL" tab and paste this entire script
--   3. Click "Go" to execute
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- --------------------------------------------------------
-- Table: fnb_menu (Food & Beverage Master Data)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `fnb_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_item` varchar(150) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kategori` enum('makanan','minuman','snack','dessert') NOT NULL DEFAULT 'makanan',
  `gambar` varchar(255) DEFAULT NULL COMMENT 'Filename in assets/uploads/fnb/',
  `stok` int(11) DEFAULT NULL COMMENT 'NULL = unlimited, 0 = habis',
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=tersedia, 0=nonaktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fnb_kategori` (`kategori`),
  KEY `idx_fnb_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: room_orders (Order Header)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `room_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_order` varchar(50) NOT NULL COMMENT 'Auto: RS-YYYYMMDD-XXXX',
  `reservasi_id` int(11) NOT NULL COMMENT 'FK to active checkin reservation',
  `kamar_id` int(11) NOT NULL COMMENT 'Room where order is delivered',
  `nama_tamu` varchar(150) NOT NULL COMMENT 'Denormalized for quick display',
  `nomor_kamar` varchar(20) NOT NULL COMMENT 'Denormalized for quick display',
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL COMMENT 'Special instructions',
  `status` enum('pending','preparing','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `ordered_by` int(11) DEFAULT NULL COMMENT 'FK to users.id (staff who took order)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_order` (`no_order`),
  KEY `idx_ro_reservasi` (`reservasi_id`),
  KEY `idx_ro_kamar` (`kamar_id`),
  KEY `idx_ro_status` (`status`),
  KEY `idx_ro_created` (`created_at`),
  CONSTRAINT `fk_ro_reservasi` FOREIGN KEY (`reservasi_id`) REFERENCES `reservasi` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ro_kamar` FOREIGN KEY (`kamar_id`) REFERENCES `kamar` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ro_user` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: room_order_details (Order Line Items)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `room_order_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `nama_item` varchar(150) NOT NULL COMMENT 'Snapshot: item name at order time',
  `harga_satuan` decimal(15,2) NOT NULL COMMENT 'Snapshot: price at order time',
  `qty` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_rod_order` (`order_id`),
  KEY `idx_rod_menu` (`menu_id`),
  CONSTRAINT `fk_rod_order` FOREIGN KEY (`order_id`) REFERENCES `room_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rod_menu` FOREIGN KEY (`menu_id`) REFERENCES `fnb_menu` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Sample Data: F&B Menu Items
-- --------------------------------------------------------

INSERT INTO `fnb_menu` (`nama_item`, `deskripsi`, `harga`, `kategori`, `stok`, `is_available`) VALUES
-- Makanan
('Nasi Goreng Spesial', 'Nasi goreng dengan telur, ayam, dan sayuran segar', 45000.00, 'makanan', NULL, 1),
('Mie Goreng Seafood', 'Mie goreng dengan udang, cumi, dan sayuran', 50000.00, 'makanan', NULL, 1),
('Club Sandwich', 'Roti panggang berlapis ayam, telur, selada, dan tomat', 55000.00, 'makanan', NULL, 1),
('Soto Ayam', 'Sup ayam tradisional dengan bihun dan telur rebus', 40000.00, 'makanan', NULL, 1),
('Steak Tenderloin', 'Daging sapi tenderloin 200gr dengan saus mushroom', 150000.00, 'makanan', 20, 1),
('Caesar Salad', 'Salad romaine dengan dressing caesar dan crouton', 45000.00, 'makanan', NULL, 1),
-- Minuman
('Jus Jeruk Segar', 'Jus jeruk peras segar tanpa gula tambahan', 25000.00, 'minuman', NULL, 1),
('Kopi Hitam', 'Kopi arabika pilihan diseduh manual', 20000.00, 'minuman', NULL, 1),
('Teh Tarik', 'Teh susu khas dengan buih lembut', 22000.00, 'minuman', NULL, 1),
('Air Mineral 600ml', 'Air mineral dalam kemasan botol', 10000.00, 'minuman', 100, 1),
('Milkshake Coklat', 'Milkshake coklat premium dengan whipped cream', 35000.00, 'minuman', NULL, 1),
-- Snack
('French Fries', 'Kentang goreng renyah dengan saus sambal dan mayo', 30000.00, 'snack', NULL, 1),
('Chicken Wings (6 pcs)', 'Sayap ayam goreng dengan saus BBQ', 45000.00, 'snack', 30, 1),
('Spring Roll (4 pcs)', 'Lumpia goreng isi sayuran dan ayam', 25000.00, 'snack', NULL, 1),
-- Dessert
('Pisang Goreng Keju', 'Pisang goreng crispy dengan taburan keju dan susu kental manis', 28000.00, 'dessert', NULL, 1),
('Es Krim Vanilla', 'Dua scoop es krim vanilla premium', 30000.00, 'dessert', 50, 1),
('Pudding Caramel', 'Pudding susu dengan saus karamel homemade', 25000.00, 'dessert', NULL, 1);

COMMIT;
