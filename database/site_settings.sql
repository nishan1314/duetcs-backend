-- Site Settings Table
-- Stores key-value pairs for system configuration

CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'boolean', 'number', 'json') DEFAULT 'string',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'DUET Computer Society', 'string', 'Website name'),
('site_description', 'Official website of DUET Computer Society', 'string', 'Website description'),
('contact_email', 'duetcs@duet.ac.bd', 'string', 'Contact email address'),
('contact_phone', '+880-2-49274000', 'string', 'Contact phone number'),
('address', 'Dhaka University of Engineering & Technology, Gazipur', 'string', 'Physical address'),
('enable_registration', 'true', 'boolean', 'Allow new user registration'),
('require_email_verification', 'true', 'boolean', 'Require email verification for new users'),
('enable_notifications', 'true', 'boolean', 'Enable email notifications'),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode'),
('maintenance_message', 'We are currently performing maintenance. Please check back later.', 'string', 'Message shown during maintenance')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
