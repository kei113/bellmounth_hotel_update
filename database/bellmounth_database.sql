-- Hotel Bellmounth Master Database Setup
-- Generated from backup and updated with recent changes
-- Includes:
-- - Booking to Reservasi refactoring
-- - foto_identitas column
-- - Staff role and user

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bellmounth`
--
CREATE DATABASE IF NOT EXISTS `bellmounth` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bellmounth`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `record_info` varchar(255) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user_id` (`user_id`),
  KEY `idx_activity_logs_module` (`module`),
  KEY `idx_activity_logs_action` (`action`),
  KEY `idx_activity_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservasi`
--

DROP TABLE IF EXISTS `reservasi`;
CREATE TABLE `reservasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_reservasi` varchar(50) NOT NULL,
  `tanggal_checkin` date NOT NULL,
  `tanggal_checkout` date NOT NULL,
  `nama_tamu` varchar(150) NOT NULL,
  `no_identitas` varchar(50) DEFAULT NULL,
  `foto_identitas` varchar(255) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `jumlah_tamu` int(11) DEFAULT 1,
  `total_bayar` decimal(15,2) DEFAULT 0.00,
  `dp_bayar` decimal(15,2) DEFAULT 0.00,
  `sisa_bayar` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','confirmed','checkin','checkout','cancelled') DEFAULT 'pending',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_reservasi` (`no_reservasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservasi_detail`
--

DROP TABLE IF EXISTS `reservasi_detail`;
CREATE TABLE `reservasi_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservasi_id` int(11) NOT NULL,
  `kamar_id` int(11) NOT NULL,
  `jumlah_malam` int(11) DEFAULT 1,
  `harga` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `reservasi_id` (`reservasi_id`),
  KEY `kamar_id` (`kamar_id`),
  CONSTRAINT `reservasi_detail_ibfk_1` FOREIGN KEY (`reservasi_id`) REFERENCES `reservasi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kamar`
--

DROP TABLE IF EXISTS `kamar`;
CREATE TABLE `kamar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_kamar` varchar(20) NOT NULL,
  `id_tipe` int(11) NOT NULL,
  `lantai` int(11) DEFAULT 1,
  `status` enum('tersedia','kotor','terisi','maintenance','reserved') DEFAULT 'tersedia',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_kamar` (`nomor_kamar`),
  KEY `id_tipe` (`id_tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tipe_kamar`
--

DROP TABLE IF EXISTS `tipe_kamar`;
CREATE TABLE `tipe_kamar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tipe` varchar(100) NOT NULL,
  `harga_per_malam` decimal(15,2) DEFAULT 0.00,
  `kapasitas` int(11) DEFAULT 2,
  `fasilitas` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `nama` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tipe_kamar`
--

INSERT INTO `tipe_kamar` (`id`, `nama_tipe`, `harga_per_malam`, `kapasitas`, `fasilitas`, `status`) VALUES
(1, 'Standard', 350000.00, 2, 'AC, TV, Kamar Mandi Dalam, WiFi', 'aktif'),
(2, 'Deluxe', 550000.00, 2, 'AC, TV 32 inch, Kamar Mandi Dalam, WiFi, Kulkas Mini', 'aktif'),
(3, 'Superior', 750000.00, 3, 'AC, TV 42 inch, Kamar Mandi Dalam, WiFi, Kulkas, Sofa', 'aktif'),
(4, 'Suite', 1200000.00, 4, 'AC, TV 50 inch, Kamar Mandi Dalam, WiFi, Kulkas, Sofa, Ruang Tamu', 'aktif'),
(5, 'Presidential Suite', 2500000.00, 6, 'AC, TV 55 inch, Jacuzzi, WiFi, Kulkas, Dapur Kecil, Ruang Tamu, Balkon', 'aktif');

--
-- Dumping data for table `kamar`
--

INSERT INTO `kamar` (`id`, `nomor_kamar`, `id_tipe`, `lantai`, `status`, `keterangan`) VALUES
(1, '101', 1, 1, 'tersedia', NULL),
(2, '102', 1, 1, 'tersedia', NULL),
(3, '103', 1, 1, 'tersedia', NULL),
(4, '104', 1, 1, 'tersedia', NULL),
(5, '105', 1, 1, 'tersedia', NULL),
(6, '201', 2, 2, 'tersedia', NULL),
(7, '202', 2, 2, 'tersedia', NULL),
(8, '203', 2, 2, 'tersedia', NULL),
(9, '204', 2, 2, 'tersedia', NULL),
(10, '301', 3, 3, 'tersedia', NULL),
(11, '302', 3, 3, 'tersedia', NULL),
(12, '303', 3, 3, 'tersedia', NULL),
(13, '401', 4, 4, 'tersedia', NULL),
(14, '402', 4, 4, 'tersedia', NULL),
(15, '501', 5, 5, 'tersedia', NULL),
(16, '502', 5, 5, 'tersedia', '');

--
-- Dumping data for table `users`
--

-- admin / admin
-- staff / staff123
INSERT INTO `users` (`id`, `username`, `nama`, `password`, `role`) VALUES
(1, 'admin', 'Administrator', '$2b$12$IOO7i8TrKrLhtYOMjSvOUOwuQD/mGvQxNZrOO3a4uLuHzoQs8tDgO', 'admin'),
(2, 'staff', 'Resepsionis', '$2a$12$woG8HV6y4sUvgML3XwMWQuWmqMpVDrcrU7htiIljr5uOOO2UTvXUO', 'staff');

--
-- Add foreign key back (wait until data is loaded)
--
ALTER TABLE `kamar` ADD CONSTRAINT `kamar_ibfk_1` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_kamar` (`id`);
ALTER TABLE `reservasi_detail` ADD CONSTRAINT `reservasi_detail_ibfk_2` FOREIGN KEY (`kamar_id`) REFERENCES `kamar` (`id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
