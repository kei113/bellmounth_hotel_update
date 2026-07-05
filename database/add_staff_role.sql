-- Add Staff Role to Hotel Bellmounth (Sumber Jaya)

-- Step 1: Alter the users table to include 'staff' in the role ENUM
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'staff') NOT NULL;

-- Step 2: Add a default staff user (password: staff123)
-- Note: It is recommended to change this password immediately after login.
INSERT INTO `users` (`username`, `password`, `role`) 
VALUES ('staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff')
ON DUPLICATE KEY UPDATE `role` = `role`;

-- Step 3: Verify the changes
-- SELECT * FROM users WHERE role = 'staff';
