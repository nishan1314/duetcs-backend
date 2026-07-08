-- Event Details Schema (Comprehensive JSON-based structure)
-- This creates the event_details table for storing comprehensive event information
-- including guests, sponsors, winners, gallery, and schedule

USE duetcs_db;

-- Drop the old event_details table if it has a different structure
-- CAUTION: Only run this if you want to replace the existing structure
-- DROP TABLE IF EXISTS event_details;

-- Create the comprehensive event_details table
CREATE TABLE IF NOT EXISTS event_details_comprehensive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL UNIQUE,
    about_event TEXT,
    chief_guest JSON DEFAULT NULL COMMENT 'Chief guest info: {name, designation, organization, image}',
    special_guests JSON DEFAULT NULL COMMENT 'Array of special guests: [{role, name, designation, organization, image}]',
    other_guests JSON DEFAULT NULL COMMENT 'Array of other guests/judges/mentors: [{role, name, designation, organization}]',
    sponsors JSON DEFAULT NULL COMMENT 'Array of sponsors: [{name, logo, type, website}]',
    media_partners JSON DEFAULT NULL COMMENT 'Array of media partners: [{name, logo, website}]',
    winners JSON DEFAULT NULL COMMENT 'Array of winners: [{position, team, members, prize, image}]',
    gallery JSON DEFAULT NULL COMMENT 'Array of gallery image URLs',
    schedule JSON DEFAULT NULL COMMENT 'Array of schedule items: [{time, title, description}]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If you're using the existing event_details table, rename it and create the new one
-- ALTER TABLE event_details RENAME TO event_details_legacy;

-- Then rename the new table to event_details
-- ALTER TABLE event_details_comprehensive RENAME TO event_details;

-- Migration for existing databases with old event_details table:
-- This modifies the existing table to add JSON columns if they don't exist

-- You may need to run these ALTER statements if the table exists with different structure:
/*
ALTER TABLE event_details 
DROP COLUMN IF EXISTS detail_type,
DROP COLUMN IF EXISTS content,
DROP COLUMN IF EXISTS display_order,
ADD COLUMN IF NOT EXISTS about_event TEXT,
ADD COLUMN IF NOT EXISTS chief_guest JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS special_guests JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS other_guests JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS sponsors JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS media_partners JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS winners JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS gallery JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS schedule JSON DEFAULT NULL,
ADD UNIQUE KEY unique_event_id (event_id);
*/
