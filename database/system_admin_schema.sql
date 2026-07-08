-- System Admin Table
-- Stores super admin, admin, and moderator accounts
-- Separate from regular users who register through the website

CREATE TABLE IF NOT EXISTS system_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    designation ENUM('Super Admin', 'Admin', 'Moderator') NOT NULL DEFAULT 'Admin',
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_designation (designation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default Super Admin
INSERT INTO system_admin (name, email, password, designation, phone_number) 
VALUES (
    'Super Administrator',
    'duetcs@duet.ac.bd',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: adminduetcs (will be hashed properly)
    'Super Admin',
    NULL
);

-- System Admin Permissions Table (for granular permissions)
CREATE TABLE IF NOT EXISTS system_admin_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES system_admin(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_permission (admin_id, permission_key),
    INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
