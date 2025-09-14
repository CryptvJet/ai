-- Create debug logs table for SmartAIRouter real-time debugging
-- Run this SQL to enable debug console integration

USE `vemite5_pulse-core-ai`;

-- Debug logs table for admin console
CREATE TABLE IF NOT EXISTS debug_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp VARCHAR(50) NOT NULL,
    level ENUM('INFO', 'SUCCESS', 'WARN', 'ERROR', 'DEBUG') NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'SmartAIRouter',
    message TEXT NOT NULL,
    data JSON DEFAULT NULL,
    request_id VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_level (level),
    INDEX idx_source (source),
    INDEX idx_request_id (request_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clean up old debug logs automatically (keep last 1000 entries)
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_debug_logs
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM debug_logs 
    WHERE id < (
        SELECT id FROM (
            SELECT id FROM debug_logs 
            ORDER BY created_at DESC 
            LIMIT 1000, 1
        ) AS x
    );
END$$
DELIMITER ;