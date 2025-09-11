-- AI Database Migration 002: Add PC Status Table
-- Description: Creates pc_status table for tracking desktop app connectivity

USE `vemite5_pulse-core-ai`;

-- PC Status table - tracks desktop app connectivity
CREATE TABLE IF NOT EXISTS pc_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    system_info JSON NOT NULL,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ollama_status ENUM('online', 'offline', 'error') DEFAULT 'offline',
    ollama_models JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_last_ping (last_ping)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default PC status record
INSERT IGNORE INTO pc_status (system_info) VALUES 
('{"platform": "unknown", "status": "offline"}');