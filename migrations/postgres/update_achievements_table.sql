-- Achievements Table Redesign for Competition Wins
-- Run this in phpMyAdmin to update the table structure

-- First, backup existing data if needed
-- CREATE TABLE achievements_backup AS SELECT * FROM achievements;

-- Drop existing table and create new one with competition wins structure
DROP TABLE IF EXISTS achievements;

CREATE TABLE achievements (
    id INT PRIMARY KEY SERIAL,
    student_name VARCHAR(255) NOT NULL,
    competition_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    prize VARCHAR(255) DEFAULT NULL,
    level VARCHAR(255) DEFAULT 'National',
    achievement_date DATE NOT NULL,
    image_url TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    is_featured BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ;

-- Add some sample data (optional)
-- INSERT INTO achievements (student_name, competition_name, position, prize, level, achievement_date, description) VALUES
-- ('Team DUETCS Alpha', 'ICPC Asia Dhaka Regional 2024', '12th Place', 'Bronze Medal', 'International', '2024-12-15', 'Competed against 200+ teams from across Asia'),
-- ('Alice Rahman', 'National Programming Contest 2024', 'Champion', 'Gold Medal & 100,000 BDT', 'National', '2024-11-20', 'Won first place in the national level programming contest');
