-- Admin Management Schema
-- This schema extends the existing users table with admin-specific features

-- Admin Roles Table
CREATE TABLE IF NOT EXISTS admin_roles (
    id SERIAL PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    role_description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
) ;

-- Admin Permissions Table
CREATE TABLE IF NOT EXISTS admin_permissions (
    id SERIAL PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ;

-- Role Permissions Mapping Table
CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE,
    UNIQUE (role_id, permission_id)
) ;

-- User Roles Mapping Table (extends users table)
CREATE TABLE IF NOT EXISTS user_roles (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (user_id, role_id)
) ;

-- Payment Records Table
CREATE TABLE IF NOT EXISTS payment_records (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id VARCHAR(255) UNIQUE,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_date TIMESTAMP,
    payment_details TEXT,
    verified_by INT,
    verified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ;

-- Notices Table
CREATE TABLE IF NOT EXISTS notices (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    notice_type VARCHAR(255) NOT NULL DEFAULT 'message',
    pdf_url VARCHAR(500),
    pdf_name VARCHAR(255),
    priority VARCHAR(255) DEFAULT 'medium',
    status VARCHAR(255) DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Events Table
CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    end_time TIME NULL,
    venue VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    status VARCHAR(255) DEFAULT 'upcoming',
    image_url VARCHAR(500),
    registration_link VARCHAR(500),
    max_participants INT NULL,
    registration_deadline DATE NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    is_published BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Event Details/Gallery Table
CREATE TABLE IF NOT EXISTS event_details (
    id SERIAL PRIMARY KEY,
    event_id INT NOT NULL,
    detail_type VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ;

-- News & Updates Table
CREATE TABLE IF NOT EXISTS news (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500),
    category VARCHAR(100),
    author_id INT NOT NULL,
    status VARCHAR(255) DEFAULT 'draft',
    published_at TIMESTAMP,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Achievements Table
CREATE TABLE IF NOT EXISTS achievements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    achievement_date DATE NOT NULL,
    category VARCHAR(100),
    image_url VARCHAR(500),
    display_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Executive Body Table
CREATE TABLE IF NOT EXISTS executive_members (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    user_id INT,
    image_url VARCHAR(500),
    bio TEXT,
    email VARCHAR(255),
    linkedin VARCHAR(255),
    github VARCHAR(255),
    term_year VARCHAR(20) NOT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ;

-- Wings (Divisions) Table
CREATE TABLE IF NOT EXISTS wings (
    id SERIAL PRIMARY KEY,
    wing_name VARCHAR(100) NOT NULL,
    wing_description TEXT NOT NULL,
    icon VARCHAR(100),
    color VARCHAR(50),
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
) ;

-- Website Content Management Table (Hero, About, Features, Legacy)
CREATE TABLE IF NOT EXISTS website_content (
    id SERIAL PRIMARY KEY,
    section_name VARCHAR(100) NOT NULL UNIQUE,
    content_data JSON NOT NULL,
    last_updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (last_updated_by) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Gallery Images Table
CREATE TABLE IF NOT EXISTS gallery_images (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    image_url VARCHAR(500) NOT NULL,
    category VARCHAR(100),
    event_id INT,
    uploaded_by INT NOT NULL,
    display_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Insert Default Roles
INSERT INTO admin_roles (role_name, role_description) VALUES
('Super Admin', 'Full system access with all permissions'),
('Admin', 'General administrative access'),
('Moderator', 'Content moderation and user management'),
('Event Manager', 'Manage events and related content'),
('Content Manager', 'Manage website content and news'),
('Member', 'Basic user role with limited access')
ON DUPLICATE KEY UPDATE role_description = VALUES(role_description);

-- Insert Default Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
-- User Management
('View Users', 'users.view', 'users', 'View user list and details'),
('Create Users', 'users.create', 'users', 'Create new users'),
('Edit Users', 'users.edit', 'users', 'Edit user information'),
('Delete Users', 'users.delete', 'users', 'Delete users'),
('Verify Users', 'users.verify', 'users', 'Verify user accounts'),

-- Role Management
('View Roles', 'roles.view', 'roles', 'View roles and permissions'),
('Manage Roles', 'roles.manage', 'roles', 'Create and edit roles'),
('Assign Roles', 'roles.assign', 'roles', 'Assign roles to users'),

-- Payment Management
('View Payments', 'payments.view', 'payments', 'View payment records'),
('Verify Payments', 'payments.verify', 'payments', 'Verify and approve payments'),
('Manage Payments', 'payments.manage', 'payments', 'Full payment management'),

-- Notice Management
('View Notices', 'notices.view', 'notices', 'View notices'),
('Create Notices', 'notices.create', 'notices', 'Create new notices'),
('Edit Notices', 'notices.edit', 'notices', 'Edit existing notices'),
('Delete Notices', 'notices.delete', 'notices', 'Delete notices'),

-- Event Management
('View Events', 'events.view', 'events', 'View events'),
('Create Events', 'events.create', 'events', 'Create new events'),
('Edit Events', 'events.edit', 'events', 'Edit existing events'),
('Delete Events', 'events.delete', 'events', 'Delete events'),

-- News Management
('View News', 'news.view', 'news', 'View news articles'),
('Create News', 'news.create', 'news', 'Create new news articles'),
('Edit News', 'news.edit', 'news', 'Edit existing news'),
('Delete News', 'news.delete', 'news', 'Delete news articles'),
('Publish News', 'news.publish', 'news', 'Publish news articles'),

-- Content Management
('View Content', 'content.view', 'content', 'View website content'),
('Edit Content', 'content.edit', 'content', 'Edit website content'),

-- Executive Management
('Manage Executive', 'executive.manage', 'executive', 'Manage executive board members'),

-- Wings Management
('Manage Wings', 'wings.manage', 'wings', 'Manage club wings/divisions'),

-- Gallery Management
('Manage Gallery', 'gallery.manage', 'gallery', 'Manage gallery images'),

-- Achievement Management
('Manage Achievements', 'achievements.manage', 'achievements', 'Manage achievements')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign all permissions to Super Admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM admin_roles WHERE role_name = 'Super Admin'),
    id
FROM admin_permissions
ON DUPLICATE KEY UPDATE role_id = role_id;
