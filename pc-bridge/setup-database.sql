-- PC Bridge Database Setup
-- Run this SQL in your ai_chat_db database

USE ai_chat_db;

-- Create pc_status table if it doesn't exist
CREATE TABLE IF NOT EXISTS pc_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('online', 'offline', 'busy') DEFAULT 'offline',
    system_info JSON,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert initial record (only if table is empty)
INSERT IGNORE INTO pc_status (id, status) VALUES (1, 'offline');

-- Show current status
SELECT * FROM pc_status;