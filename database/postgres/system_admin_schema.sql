-- System Admin Table
-- Stores super admin, admin, and moderator accounts
-- Separate from regular users who register through the website

CREATE TABLE IF NOT EXISTS system_admin (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    designation VARCHAR(255) NOT NULL DEFAULT 'Admin',
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT 1
) ;

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
    id SERIAL PRIMARY KEY,
    admin_id INT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES system_admin(id) ON DELETE CASCADE,
    UNIQUE (admin_id, permission_key)
) ;
