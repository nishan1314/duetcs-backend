-- Migration: Add new fields to events table
-- Run this migration to add support for additional event fields

USE duetcs_db;

-- Add new columns to events table
ALTER TABLE events
    ADD COLUMN end_time TIME NULL AFTER event_time,
    ADD COLUMN max_participants INT NULL AFTER registration_link,
    ADD COLUMN registration_deadline DATE NULL AFTER max_participants,
    ADD COLUMN contact_email VARCHAR(255) NULL AFTER registration_deadline,
    ADD COLUMN contact_phone VARCHAR(50) NULL AFTER contact_email,
    ADD COLUMN is_featured BOOLEAN DEFAULT FALSE AFTER contact_phone,
    ADD COLUMN is_published BOOLEAN DEFAULT TRUE AFTER is_featured;

-- Add indexes for new columns
ALTER TABLE events
    ADD INDEX idx_is_featured (is_featured),
    ADD INDEX idx_is_published (is_published),
    ADD INDEX idx_registration_deadline (registration_deadline);

-- Update existing records to have default values
UPDATE events SET is_published = TRUE WHERE is_published IS NULL;
UPDATE events SET is_featured = FALSE WHERE is_featured IS NULL;
