-- ============================================================
-- Add 'nama' column to users table
-- Hotel Bellmounth (Sumber Jaya)
-- ============================================================
-- INSTRUCTIONS:
--   1. Open phpMyAdmin, select database `bellmounth`
--   2. Go to "SQL" tab and paste this script
--   3. Click "Go" to execute
-- ============================================================

-- Add nama column after username
ALTER TABLE `users` ADD COLUMN `nama` varchar(150) DEFAULT NULL AFTER `username`;

-- Update existing users with their display names
UPDATE `users` SET `nama` = 'Administrator' WHERE `username` = 'admin';
UPDATE `users` SET `nama` = 'Resepsionis' WHERE `username` = 'staff';
