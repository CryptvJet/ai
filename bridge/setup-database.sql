-- Zin AI Bridge Database Setup
-- Run this SQL to create the required table for PC status tracking

USE ai;

-- Create pc_status table if it doesn't exist
CREATE TABLE IF NOT EXISTS pc_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    system_info JSON NOT NULL,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pc (id)
);

-- Insert or update PC status (the app will handle this, but here's the structure)
-- The system_info JSON will contain:
-- {
--   "hostname": "PC-NAME",
--   "platform": "win32",
--   "arch": "x64", 
--   "uptime": 123456,
--   "memory": {
--     "total": 17179869184,
--     "free": 8589934592
--   },
--   "cpus": 8,
--   "loadavg": [0.1, 0.2, 0.3],
--   "timestamp": "2025-09-10T12:00:00.000Z"
-- }

-- Test query to check PC status (for web interface)
-- SELECT 
--   system_info,
--   last_ping,
--   TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping,
--   CASE 
--     WHEN TIMESTAMPDIFF(SECOND, last_ping, NOW()) < 60 THEN 'online'
--     ELSE 'offline'
--   END as status
-- FROM pc_status 
-- ORDER BY last_ping DESC 
-- LIMIT 1;