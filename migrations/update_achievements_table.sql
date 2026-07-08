-- Achievements Table Redesign for Competition Wins
-- Run this in phpMyAdmin to update the table structure

-- First, backup existing data if needed
-- CREATE TABLE achievements_backup AS SELECT * FROM achievements;

-- Drop existing table and create new one with competition wins structure
DROP TABLE IF EXISTS achievements;

CREATE TABLE achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_name VARCHAR(255) NOT NULL COMMENT 'Student name or team name',
    competition_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL COMMENT 'e.g., Champion, 1st Place, Runner-up',
    prize VARCHAR(255) DEFAULT NULL COMMENT 'e.g., Gold Medal, 50000 BDT',
    level ENUM('National', 'International') DEFAULT 'National',
    achievement_date DATE NOT NULL,
    image_url TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some sample data (optional)
-- INSERT INTO achievements (student_name, competition_name, position, prize, level, achievement_date, description) VALUES
-- ('Team DUETCS Alpha', 'ICPC Asia Dhaka Regional 2024', '12th Place', 'Bronze Medal', 'International', '2024-12-15', 'Competed against 200+ teams from across Asia'),
-- ('Alice Rahman', 'National Programming Contest 2024', 'Champion', 'Gold Medal & 100,000 BDT', 'National', '2024-11-20', 'Won first place in the national level programming contest');
