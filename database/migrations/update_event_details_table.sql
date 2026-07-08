-- Migration: Add schedule column to event_details table
-- Run this on existing databases to add the new schedule field

ALTER TABLE event_details 
ADD COLUMN IF NOT EXISTS schedule JSON DEFAULT NULL COMMENT 'Event schedule/timeline as JSON array' AFTER gallery;

-- If your MySQL version doesn't support IF NOT EXISTS, use this instead:
-- ALTER TABLE event_details ADD COLUMN schedule JSON DEFAULT NULL COMMENT 'Event schedule/timeline as JSON array' AFTER gallery;
