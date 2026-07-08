-- Coder Handles Table for storing Codeforces handles
-- Run this SQL to add the coder_handles table to your database

USE duetcs_db;

-- Coder Handles Table
CREATE TABLE IF NOT EXISTS coder_handles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    codeforces_handle VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_handle (user_id),
    UNIQUE KEY unique_codeforces_handle (codeforces_handle),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_codeforces_handle (codeforces_handle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add codeforces_handle column to users table if you prefer storing it directly in users table
-- ALTER TABLE users ADD COLUMN codeforces_handle VARCHAR(100) NULL AFTER profile_image;
-- ALTER TABLE users ADD UNIQUE INDEX idx_codeforces_handle (codeforces_handle);
