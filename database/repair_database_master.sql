-- Master Repair Script for Hotel Bellmounth (Sumber Jaya)
-- This script drops and recreates the database to fix InnoDB tablespace corruption.

-- Step 1: Drop and Recreate the Database
DROP DATABASE IF EXISTS `bellmounth`;
CREATE DATABASE `bellmounth`;
USE `bellmounth`;

-- Step 2: Restore from Backup (Structure and Data as of 2026-02-11)
-- Note: This part will be handled via command line import of the .sql file

-- Step 3: Re-apply Refactoring (Booking -> Reservasi)
RENAME TABLE `booking` TO `reservasi`;
RENAME TABLE `booking_detail` TO `reservasi_detail`;

-- Step 4: Rename columns to match current code
ALTER TABLE `reservasi` CHANGE `no_booking` `no_reservasi` VARCHAR(20);
ALTER TABLE `reservasi_detail` CHANGE `booking_id` `reservasi_id` INT(11);

-- Step 5: Add foto_identitas column
ALTER TABLE `reservasi` ADD COLUMN `foto_identitas` VARCHAR(255) NULL AFTER `no_identitas`;
