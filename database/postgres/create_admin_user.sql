-- Quick Setup: Create First Admin User
-- Run this after importing admin_schema.sql

-- Create admin user with default credentials
-- Email: admin@duet.ac.bd
-- Password: password (Change this after first login!)
INSERT INTO users (full_name, email, student_id, department, year_semester, why_join, password, is_verified)
VALUES (
  'Super Admin',
  'admin@duet.ac.bd',
  'ADMIN-001',
  'CSE',
  '4-2',
  'System Administrator',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: password
  1
)
ON DUPLICATE KEY UPDATE email = email; -- Skip if already exists

-- Get the admin user ID
SET @admin_id = (SELECT id FROM users WHERE email = 'admin@duet.ac.bd' LIMIT 1);

-- Assign Super Admin role
INSERT INTO user_roles (user_id, role_id, assigned_by)
SELECT 
  @admin_id,
  (SELECT id FROM admin_roles WHERE role_name = 'Super Admin'),
  @admin_id
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM user_roles 
  WHERE user_id = @admin_id 
  AND role_id = (SELECT id FROM admin_roles WHERE role_name = 'Super Admin')
);

-- Verify the setup
SELECT 
  u.id,
  u.full_name,
  u.email,
  u.student_id,
  u.is_verified,
  GROUP_CONCAT(ar.role_name) as roles
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN admin_roles ar ON ur.role_id = ar.id
WHERE u.email = 'admin@duet.ac.bd'
GROUP BY u.id;

-- Success message
SELECT 'Admin user created successfully!' as message,
       'Email: admin@duet.ac.bd' as credentials,
       'Password: password' as default_password,
       'IMPORTANT: Change password after first login!' as warning;
