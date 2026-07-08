-- Complete System Admin Setup
-- Run this SQL in phpMyAdmin or MySQL Workbench

-- Step 1: Create system_admin table
CREATE TABLE IF NOT EXISTS `system_admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `designation` ENUM('Super Admin', 'Admin', 'Moderator') NOT NULL DEFAULT 'Admin',
    `phone_number` VARCHAR(20),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    INDEX `idx_email` (`email`),
    INDEX `idx_designation` (`designation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Insert Super Admin with password 'adminduetcs'
-- Password hash generated using PHP password_hash('adminduetcs', PASSWORD_DEFAULT)
-- This is just a placeholder - you MUST run the PHP script to generate the actual hash

-- Delete existing super admin if exists (for clean setup)
DELETE FROM `system_admin` WHERE `email` = 'duetcs@duet.ac.bd';

-- Insert new super admin
-- NOTE: The password below is a sample hash. You need to generate a proper hash.
-- To generate the hash, create a simple PHP file with this code:
-- <?php echo password_hash('adminduetcs', PASSWORD_DEFAULT); ?>

INSERT INTO `system_admin` (`name`, `email`, `password`, `designation`, `is_active`) 
VALUES (
    'Super Administrator',
    'duetcs@duet.ac.bd',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Super Admin',
    1
);

-- Verify the super admin was created
SELECT id, name, email, designation, is_active, created_at 
FROM `system_admin` 
WHERE email = 'duetcs@duet.ac.bd';
