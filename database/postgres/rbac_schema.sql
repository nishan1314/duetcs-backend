-- ============================================
-- RBAC Schema for DUETCS Admin System
-- ============================================

-- Drop existing tables if needed (comment out in production)
-- DROP TABLE IF EXISTS audit_logs;
-- DROP TABLE IF EXISTS system_admin;

-- ============================================
-- 1. System Admin Table (Admin Users Only)
-- ============================================
CREATE TABLE IF NOT EXISTS system_admin (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL DEFAULT 'ADMIN',
    phone_number VARCHAR(20) NULL,
    profile_image VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    created_by INT NULL
) ;

-- ============================================
-- 2. Users Table (Regular Members)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    student_id VARCHAR(20) UNIQUE NULL,
    year_semester VARCHAR(20) NULL,
    department VARCHAR(100) NULL,
    phone_number VARCHAR(20) NULL,
    profile_image VARCHAR(255) NULL,
    codeforces_handle VARCHAR(50) NULL,
    role VARCHAR(255) DEFAULT 'MEMBER',
    is_active BOOLEAN DEFAULT 1,
    is_verified BOOLEAN DEFAULT 0,
    verification_token VARCHAR(255) NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
) ;

-- ============================================
-- 3. Audit Logs Table
-- ============================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    actor_id INT NOT NULL,
    actor_type VARCHAR(255) NOT NULL,
    actor_name VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    entity_name VARCHAR(255) NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ;

-- ============================================
-- 4. Role Permissions Reference Table
-- ============================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    role VARCHAR(255) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (role, permission)
) ;

-- ============================================
-- Insert Default Permissions
-- ============================================

-- SUPER_ADMIN permissions (all)
INSERT IGNORE INTO role_permissions (role, permission) VALUES
-- User Management
('SUPER_ADMIN', 'users.view'),
('SUPER_ADMIN', 'users.create'),
('SUPER_ADMIN', 'users.update'),
('SUPER_ADMIN', 'users.delete'),
('SUPER_ADMIN', 'users.activate'),
('SUPER_ADMIN', 'users.deactivate'),
-- Admin Management
('SUPER_ADMIN', 'admins.view'),
('SUPER_ADMIN', 'admins.create'),
('SUPER_ADMIN', 'admins.update'),
('SUPER_ADMIN', 'admins.delete'),
('SUPER_ADMIN', 'admins.promote'),
('SUPER_ADMIN', 'admins.demote'),
-- Executive Committee
('SUPER_ADMIN', 'executive.view'),
('SUPER_ADMIN', 'executive.create'),
('SUPER_ADMIN', 'executive.update'),
('SUPER_ADMIN', 'executive.delete'),
-- Alumni Advisors
('SUPER_ADMIN', 'advisors.view'),
('SUPER_ADMIN', 'advisors.create'),
('SUPER_ADMIN', 'advisors.update'),
('SUPER_ADMIN', 'advisors.delete'),
-- Advisory Board
('SUPER_ADMIN', 'advisory_board.view'),
('SUPER_ADMIN', 'advisory_board.create'),
('SUPER_ADMIN', 'advisory_board.update'),
('SUPER_ADMIN', 'advisory_board.delete'),
-- Posts/Events
('SUPER_ADMIN', 'posts.view'),
('SUPER_ADMIN', 'posts.create'),
('SUPER_ADMIN', 'posts.update'),
('SUPER_ADMIN', 'posts.delete'),
('SUPER_ADMIN', 'posts.publish'),
('SUPER_ADMIN', 'events.view'),
('SUPER_ADMIN', 'events.create'),
('SUPER_ADMIN', 'events.update'),
('SUPER_ADMIN', 'events.delete'),
('SUPER_ADMIN', 'events.publish'),
-- Settings
('SUPER_ADMIN', 'settings.view'),
('SUPER_ADMIN', 'settings.update'),
-- Audit Logs
('SUPER_ADMIN', 'audit.view'),
-- Notices
('SUPER_ADMIN', 'notices.view'),
('SUPER_ADMIN', 'notices.create'),
('SUPER_ADMIN', 'notices.update'),
('SUPER_ADMIN', 'notices.delete');

-- ADMIN permissions (limited)
INSERT IGNORE INTO role_permissions (role, permission) VALUES
-- Executive Committee
('ADMIN', 'executive.view'),
('ADMIN', 'executive.create'),
('ADMIN', 'executive.update'),
('ADMIN', 'executive.delete'),
-- Alumni Advisors
('ADMIN', 'advisors.view'),
('ADMIN', 'advisors.create'),
('ADMIN', 'advisors.update'),
('ADMIN', 'advisors.delete'),
-- Advisory Board
('ADMIN', 'advisory_board.view'),
('ADMIN', 'advisory_board.create'),
('ADMIN', 'advisory_board.update'),
('ADMIN', 'advisory_board.delete'),
-- Posts/Events
('ADMIN', 'posts.view'),
('ADMIN', 'posts.create'),
('ADMIN', 'posts.update'),
('ADMIN', 'posts.delete'),
('ADMIN', 'posts.publish'),
('ADMIN', 'events.view'),
('ADMIN', 'events.create'),
('ADMIN', 'events.update'),
('ADMIN', 'events.delete'),
('ADMIN', 'events.publish'),
-- Notices
('ADMIN', 'notices.view'),
('ADMIN', 'notices.create'),
('ADMIN', 'notices.update'),
('ADMIN', 'notices.delete');

-- MEMBER permissions (minimal)
INSERT IGNORE INTO role_permissions (role, permission) VALUES
('MEMBER', 'profile.view'),
('MEMBER', 'profile.update'),
('MEMBER', 'events.register'),
('MEMBER', 'events.view_registrations');

-- ============================================
-- Create Default Super Admin
-- ============================================
-- Password: adminduetcs (will be hashed by setup script)
