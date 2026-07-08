-- Add profile_image column to users table (if not exists)
-- Or modify existing column to use VARCHAR instead of TEXT
-- If column doesn't exist, create it:
-- ALTER TABLE users ADD COLUMN profile_image VARCHAR(500) NULL AFTER password;

-- If column exists as TEXT, modify it:
ALTER TABLE users MODIFY COLUMN profile_image VARCHAR(500) NULL;
