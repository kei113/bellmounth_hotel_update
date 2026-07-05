-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bellmounth
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'User who performed the action',
  `username` varchar(100) DEFAULT NULL COMMENT 'Username for display',
  `action` varchar(100) NOT NULL COMMENT 'Action type: login, logout, create, update, delete, status_change, etc',
  `module` varchar(50) NOT NULL COMMENT 'Module name: booking, kamar, room_management, etc',
  `record_id` int(11) DEFAULT NULL COMMENT 'ID of the affected record',
  `record_info` varchar(255) DEFAULT NULL COMMENT 'Brief info about the record (e.g., room number, booking no)',
  `old_value` text DEFAULT NULL COMMENT 'Previous value (JSON format for complex data)',
  `new_value` text DEFAULT NULL COMMENT 'New value (JSON format for complex data)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP address',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser/client info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp of the action',
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user_id` (`user_id`),
  KEY `idx_activity_logs_module` (`module`),
  KEY `idx_activity_logs_action` (`action`),
  KEY `idx_activity_logs_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"maintenance\"}','{\"status\":\"tersedia\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:24:39'),(2,1,'admin','create','booking',9,'BKG-20260202-0006 - Fahmi',NULL,'{\"nama_tamu\":\"Fahmi\",\"checkin\":\"2026-02-02\",\"checkout\":\"2026-02-03\",\"jumlah_tamu\":1,\"status\":\"pending\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:25:40'),(3,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"tersedia\"}','{\"status\":\"kotor\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:25:57'),(4,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"\"}','{\"status\":\"kotor\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:26:34'),(5,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"\"}','{\"status\":\"maintenance\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:26:37'),(6,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"maintenance\"}','{\"status\":\"tersedia\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:26:43'),(7,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"tersedia\"}','{\"status\":\"kotor\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:26:45'),(8,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"tersedia\"}','{\"status\":\"kotor\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:29:57'),(9,1,'admin','status_change','room_management',1,'Kamar 101','{\"status\":\"kotor\"}','{\"status\":\"maintenance\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-02 05:30:35'),(10,1,'admin','login','auth',1,'admin',NULL,'{\"role\":\"admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-10 21:40:11'),(11,1,'admin','logout','auth',1,'admin',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-10 21:52:28'),(12,1,'admin','login','auth',1,'admin',NULL,'{\"role\":\"admin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-10 21:52:34');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking`
--

DROP TABLE IF EXISTS `booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_booking` varchar(50) NOT NULL,
  `tanggal_checkin` date NOT NULL,
  `tanggal_checkout` date NOT NULL,
  `nama_tamu` varchar(150) NOT NULL,
  `no_identitas` varchar(50) DEFAULT NULL,
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
  UNIQUE KEY `no_booking` (`no_booking`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking`
--

LOCK TABLES `booking` WRITE;
/*!40000 ALTER TABLE `booking` DISABLE KEYS */;
INSERT INTO `booking` VALUES (1,'BKG-20250125-0001','2025-01-25','2025-01-27','Ahmad Rizki','3201234567890001','081234567890','ahmad@email.com',NULL,2,700000.00,350000.00,350000.00,'checkin',NULL,'2026-02-01 23:46:48'),(2,'BKG-20250125-0002','2025-01-26','2025-01-28','Siti Aminah','3201234567890002','082345678901','siti@email.com',NULL,3,1100000.00,0.00,1100000.00,'cancelled',NULL,'2026-02-01 23:46:48'),(3,'BKG-20250125-0003','2026-01-27','2026-01-31','Budi Santoso','3201234567890003','083456789012','budi@email.com','',4,10000000.00,10000000.00,0.00,'checkout','','2026-02-01 23:46:48'),(4,'BKG-20260202-0001','2026-02-07','2026-02-12','Ahmad Yani','320912518725812','087812748172515','yanisss@gmail.com','Jl Anggur, No 51, Jakarta Timur',1,1750000.00,0.00,1750000.00,'cancelled','','2026-02-02 00:15:14'),(5,'BKG-20260202-0002','2026-02-02','2026-02-03','Dominic','3209129581928512','08218568125212','dommm@gmail.com','Jl Mawar indah, No 412, Jakarta Barat',1,550000.00,0.00,550000.00,'cancelled','','2026-02-02 00:26:30'),(6,'BKG-20260202-0003','2026-02-02','2026-02-03','Johan','320859128951','0873271756125521','bpljohanesburg@gmail.com','Jl Ahmad Yani, No 61, Jakarta Pusat',3,750000.00,750000.00,0.00,'confirmed','','2026-02-02 00:30:05'),(7,'BKG-20260202-0004','2026-02-02','2026-02-03','Ratnasari','3209192871751222','0811727225562124','rtnssaa@gmail.com','Jl Kusuma Indah, No 122, Jakarta TImur',2,750000.00,750000.00,0.00,'confirmed','','2026-02-02 00:32:39'),(8,'BKG-20260202-0005','2026-02-02','2026-02-03','Budi','32091294192481','082149279155','budd@gmail.com','Jl Mawar, No 65, Jakarta Barat',1,0.00,0.00,0.00,'pending','','2026-02-02 05:11:31'),(9,'BKG-20260202-0006','2026-02-02','2026-02-03','Fahmi','320912941925','0827832738125','fahmi@gmail.com','Jl Barat, No 43, Cirebon',1,0.00,0.00,0.00,'pending','','2026-02-02 05:25:40');
/*!40000 ALTER TABLE `booking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_detail`
--

DROP TABLE IF EXISTS `booking_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `kamar_id` int(11) NOT NULL,
  `jumlah_malam` int(11) DEFAULT 1,
  `harga` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `kamar_id` (`kamar_id`),
  CONSTRAINT `booking_detail_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_detail_ibfk_2` FOREIGN KEY (`kamar_id`) REFERENCES `kamar` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_detail`
--

LOCK TABLES `booking_detail` WRITE;
/*!40000 ALTER TABLE `booking_detail` DISABLE KEYS */;
INSERT INTO `booking_detail` VALUES (1,1,3,2,350000.00,700000.00),(2,2,8,2,550000.00,1100000.00),(5,3,15,4,2500000.00,10000000.00),(6,4,4,5,350000.00,1750000.00),(7,5,6,1,550000.00,550000.00),(9,6,10,1,750000.00,750000.00),(10,7,12,1,750000.00,750000.00);
/*!40000 ALTER TABLE `booking_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kamar`
--

DROP TABLE IF EXISTS `kamar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `id_tipe` (`id_tipe`),
  CONSTRAINT `kamar_ibfk_1` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_kamar` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kamar`
--

LOCK TABLES `kamar` WRITE;
/*!40000 ALTER TABLE `kamar` DISABLE KEYS */;
INSERT INTO `kamar` VALUES (1,'101',1,1,'maintenance',NULL,'2026-02-01 23:46:48'),(2,'102',1,1,'tersedia',NULL,'2026-02-01 23:46:48'),(3,'103',1,1,'terisi',NULL,'2026-02-01 23:46:48'),(4,'104',1,1,'tersedia',NULL,'2026-02-01 23:46:48'),(5,'105',1,1,'maintenance',NULL,'2026-02-01 23:46:48'),(6,'201',2,2,'tersedia',NULL,'2026-02-01 23:46:48'),(7,'202',2,2,'tersedia',NULL,'2026-02-01 23:46:48'),(8,'203',2,2,'terisi',NULL,'2026-02-01 23:46:48'),(9,'204',2,2,'tersedia',NULL,'2026-02-01 23:46:48'),(10,'301',3,3,'tersedia',NULL,'2026-02-01 23:46:48'),(11,'302',3,3,'tersedia',NULL,'2026-02-01 23:46:48'),(12,'303',3,3,'reserved',NULL,'2026-02-01 23:46:48'),(13,'401',4,4,'tersedia',NULL,'2026-02-01 23:46:48'),(14,'402',4,4,'tersedia',NULL,'2026-02-01 23:46:48'),(15,'501',5,5,'tersedia',NULL,'2026-02-01 23:46:48'),(16,'502',5,5,'tersedia','','2026-02-02 05:08:10');
/*!40000 ALTER TABLE `kamar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipe_kamar`
--

DROP TABLE IF EXISTS `tipe_kamar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipe_kamar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tipe` varchar(100) NOT NULL,
  `harga_per_malam` decimal(15,2) DEFAULT 0.00,
  `kapasitas` int(11) DEFAULT 2,
  `fasilitas` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipe_kamar`
--

LOCK TABLES `tipe_kamar` WRITE;
/*!40000 ALTER TABLE `tipe_kamar` DISABLE KEYS */;
INSERT INTO `tipe_kamar` VALUES (1,'Standard',350000.00,2,'AC, TV, Kamar Mandi Dalam, WiFi','aktif','2026-02-01 23:46:48'),(2,'Deluxe',550000.00,2,'AC, TV 32 inch, Kamar Mandi Dalam, WiFi, Kulkas Mini','aktif','2026-02-01 23:46:48'),(3,'Superior',750000.00,3,'AC, TV 42 inch, Kamar Mandi Dalam, WiFi, Kulkas, Sofa','aktif','2026-02-01 23:46:48'),(4,'Suite',1200000.00,4,'AC, TV 50 inch, Kamar Mandi Dalam, WiFi, Kulkas, Sofa, Ruang Tamu','aktif','2026-02-01 23:46:48'),(5,'Presidential Suite',2500000.00,6,'AC, TV 55 inch, Jacuzzi, WiFi, Kulkas, Dapur Kecil, Ruang Tamu, Balkon','aktif','2026-02-01 23:46:48');
/*!40000 ALTER TABLE `tipe_kamar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2b$12$IOO7i8TrKrLhtYOMjSvOUOwuQD/mGvQxNZrOO3a4uLuHzoQs8tDgO','admin','2026-01-05 03:14:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-11  4:52:38
