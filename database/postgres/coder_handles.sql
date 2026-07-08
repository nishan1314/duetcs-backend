-- Coder Handles Table for storing Codeforces handles
-- Run this SQL to add the coder_handles table to your database

-- Coder Handles Table
CREATE TABLE IF NOT EXISTS coder_handles (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    codeforces_handle VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    UNIQUE (user_id),
    UNIQUE (codeforces_handle),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ;

-- Add codeforces_handle column to users table if you prefer storing it directly in users table
-- ALTER TABLE users ADD COLUMN codeforces_handle VARCHAR(100) NULL AFTER profile_image;
-- ALTER TABLE users ADD UNIQUE INDEX idx_codeforces_handle (codeforces_handle);
