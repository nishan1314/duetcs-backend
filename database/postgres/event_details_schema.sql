-- Event Details Schema (Comprehensive JSON-based structure)
-- This creates the event_details table for storing comprehensive event information
-- including guests, sponsors, winners, gallery, and schedule

-- Drop the old event_details table if it has a different structure
-- CAUTION: Only run this if you want to replace the existing structure
-- DROP TABLE IF EXISTS event_details;

-- Create the comprehensive event_details table
CREATE TABLE IF NOT EXISTS event_details_comprehensive (
    id SERIAL PRIMARY KEY,
    event_id INT NOT NULL UNIQUE,
    about_event TEXT,
    chief_guest JSON DEFAULT NULL,
    special_guests JSON DEFAULT NULL,
    other_guests JSON DEFAULT NULL,
    sponsors JSON DEFAULT NULL,
    media_partners JSON DEFAULT NULL,
    winners JSON DEFAULT NULL,
    gallery JSON DEFAULT NULL,
    schedule JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ;

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
ADD UNIQUE (event_id);
*/
