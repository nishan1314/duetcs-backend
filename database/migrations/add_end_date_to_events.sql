-- Migration: Add end_date column to events table for multi-day events
-- Run this migration to support date ranges like "09 May - 10 May"

USE duetcs_db;

-- Add end_date column to events table
ALTER TABLE events
    ADD COLUMN end_date DATE NULL AFTER event_date;

-- Add index for end_date
ALTER TABLE events
    ADD INDEX idx_end_date (end_date);

-- Note: For single-day events, end_date should be NULL or same as event_date
-- For multi-day events, end_date will contain the last day of the event
