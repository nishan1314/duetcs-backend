-- Default Roles and Permissions Setup
-- This file seeds the database with default roles and permissions structure

-- ===========================
-- INSERT DEFAULT ROLES
-- ===========================

INSERT INTO admin_roles (role_name, role_description, is_active) VALUES
('Super Admin', 'Full system access - can manage all features and users', TRUE),
('Admin', 'Administrative access - can manage most features and moderate content', TRUE),
('Moderator', 'Content moderation - can review and manage user content', TRUE),
('Executive', 'Executive board member - can manage events and announcements', TRUE),
('User', 'Regular member - standard user access', TRUE)
ON DUPLICATE KEY UPDATE role_description=VALUES(role_description), is_active=VALUES(is_active);

-- ===========================
-- INSERT DEFAULT PERMISSIONS
-- ===========================

-- User Management Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('View Users', 'users.view', 'users', 'Can view user list and details'),
('Create User', 'users.create', 'users', 'Can create new user accounts'),
('Edit User', 'users.edit', 'users', 'Can edit user profile information'),
('Delete User', 'users.delete', 'users', 'Can delete user accounts'),
('Assign Role', 'users.assign_role', 'users', 'Can assign roles to users'),
('Verify Email', 'users.verify_email', 'users', 'Can manually verify user emails')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Payment Management Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('View Payments', 'payments.view', 'payments', 'Can view payment records'),
('Verify Payment', 'payments.verify', 'payments', 'Can verify and confirm payments'),
('Manage Payments', 'payments.manage', 'payments', 'Can create and edit payment records'),
('Refund Payment', 'payments.refund', 'payments', 'Can process refunds')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Content Management Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('Create Event', 'content.create_event', 'content', 'Can create new events'),
('Edit Event', 'content.edit_event', 'content', 'Can edit event details'),
('Delete Event', 'content.delete_event', 'content', 'Can delete events'),
('Create News', 'content.create_news', 'content', 'Can create news articles'),
('Edit News', 'content.edit_news', 'content', 'Can edit news articles'),
('Delete News', 'content.delete_news', 'content', 'Can delete news articles'),
('Create Achievement', 'content.create_achievement', 'content', 'Can create achievements'),
('Edit Achievement', 'content.edit_achievement', 'content', 'Can edit achievements'),
('Delete Achievement', 'content.delete_achievement', 'content', 'Can delete achievements')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Notice Management Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('Create Notice', 'notices.create', 'notices', 'Can create and publish notices'),
('Edit Notice', 'notices.edit', 'notices', 'Can edit notices'),
('Delete Notice', 'notices.delete', 'notices', 'Can delete notices'),
('Archive Notice', 'notices.archive', 'notices', 'Can archive notices')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Role Management Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('View Roles', 'roles.view', 'roles', 'Can view roles and permissions'),
('Create Role', 'roles.create', 'roles', 'Can create new roles'),
('Edit Role', 'roles.edit', 'roles', 'Can edit roles'),
('Delete Role', 'roles.delete', 'roles', 'Can delete roles'),
('Manage Role Permissions', 'roles.manage_permissions', 'roles', 'Can assign permissions to roles')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Gallery Management Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('Manage Gallery', 'gallery.manage', 'gallery', 'Can upload and manage gallery images'),
('Delete Gallery', 'gallery.delete', 'gallery', 'Can delete gallery images')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- System Administration Permissions
INSERT INTO admin_permissions (permission_name, permission_key, module, description) VALUES
('View Dashboard', 'system.view_dashboard', 'system', 'Can access admin dashboard'),
('View Reports', 'system.view_reports', 'system', 'Can view system reports'),
('System Settings', 'system.settings', 'system', 'Can manage system settings')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ===========================
-- ASSIGN PERMISSIONS TO ROLES
-- ===========================

-- Get role IDs (we'll use subqueries)
-- Super Admin - All Permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM admin_roles WHERE role_name = 'Super Admin'),
    id
FROM admin_permissions
ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP;

-- Admin - All except System Settings
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM admin_roles WHERE role_name = 'Admin'),
    id
FROM admin_permissions
WHERE permission_key NOT IN ('system.settings')
ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP;

-- Moderator - Content and User moderation
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM admin_roles WHERE role_name = 'Moderator'),
    id
FROM admin_permissions
WHERE permission_key IN (
    'users.view', 'content.view_event', 'content.edit_event', 
    'notices.view', 'gallery.manage', 'system.view_dashboard'
)
ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP;

-- Executive - Event and News management
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM admin_roles WHERE role_name = 'Executive'),
    id
FROM admin_permissions
WHERE permission_key IN (
    'content.create_event', 'content.edit_event', 'content.create_news', 
    'content.edit_news', 'notices.create', 'system.view_dashboard'
)
ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP;

-- User - Read-only access
INSERT INTO role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM admin_roles WHERE role_name = 'User'),
    id
FROM admin_permissions
WHERE permission_key IN ('system.view_dashboard')
ON DUPLICATE KEY UPDATE created_at=CURRENT_TIMESTAMP;

-- ===========================
-- Optional: Add a test admin user (already exists in sample-content.sql)
-- ===========================
-- The first user (id=1) should be assigned Super Admin role if not already assigned

INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 
    1,
    (SELECT id FROM admin_roles WHERE role_name = 'Super Admin'),
    1
WHERE NOT EXISTS (
    SELECT 1 FROM user_roles WHERE user_id = 1 AND role_id = (SELECT id FROM admin_roles WHERE role_name = 'Super Admin')
);

-- Assign sample users to their roles
INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 2, (SELECT id FROM admin_roles WHERE role_name = 'Executive'), 1
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = 2);

INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 3, (SELECT id FROM admin_roles WHERE role_name = 'Moderator'), 1
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = 3);

INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 4, (SELECT id FROM admin_roles WHERE role_name = 'Executive'), 1
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = 4);

INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 5, (SELECT id FROM admin_roles WHERE role_name = 'User'), 1
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = 5);

INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 6, (SELECT id FROM admin_roles WHERE role_name = 'User'), 1
WHERE NOT EXISTS (SELECT 1 FROM user_roles WHERE user_id = 6);
