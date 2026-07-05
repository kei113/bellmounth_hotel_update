-- Activity Log Table for Hotel Bellmounth
-- Run this SQL in phpMyAdmin or MySQL CLI

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NULL COMMENT 'User who performed the action',
    `username` VARCHAR(100) NULL COMMENT 'Username for display',
    `action` VARCHAR(100) NOT NULL COMMENT 'Action type: login, logout, create, update, delete, status_change, etc',
    `module` VARCHAR(50) NOT NULL COMMENT 'Module name: booking, kamar, room_management, etc',
    `record_id` INT(11) NULL COMMENT 'ID of the affected record',
    `record_info` VARCHAR(255) NULL COMMENT 'Brief info about the record (e.g., room number, booking no)',
    `old_value` TEXT NULL COMMENT 'Previous value (JSON format for complex data)',
    `new_value` TEXT NULL COMMENT 'New value (JSON format for complex data)',
    `ip_address` VARCHAR(45) NULL COMMENT 'Client IP address',
    `user_agent` VARCHAR(255) NULL COMMENT 'Browser/client info',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of the action'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for faster queries
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_module ON activity_logs(module);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Sample data for testing (optional - can be removed)
-- INSERT INTO `activity_logs` (`user_id`, `username`, `action`, `module`, `record_id`, `record_info`, `old_value`, `new_value`, `ip_address`) VALUES
-- (1, 'admin', 'login', 'auth', NULL, NULL, NULL, NULL, '127.0.0.1'),
-- (1, 'admin', 'create', 'booking', 1, 'BK-2024001', NULL, '{"nama_tamu": "John Doe", "status": "pending"}', '127.0.0.1'),
-- (1, 'admin', 'status_change', 'kamar', 101, 'Kamar 101', '{"status": "kotor"}', '{"status": "tersedia"}', '127.0.0.1');
