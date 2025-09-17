-- Create Admin Users Table and Insert Default Users
-- This links admin authentication to your database

USE `vemite5_pulse-core-ai`;

-- Create admin users table
CREATE TABLE IF NOT EXISTS ai_admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin users (same as hardcoded ones)
INSERT INTO ai_admin_users (username, password_hash, email, role) VALUES 
(
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- PulseCore2024!
    'admin@pulsecore.one',
    'admin'
),
(
    'zin', 
    '$2y$10$h/7QYh3yQy7QrqA5.O5kp.jGQrFKrWWKZd4lHRKQHy4KrBzGZbQ8u', -- ZinAdmin123!
    'zin@pulsecore.one',
    'admin'
)
ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    updated_at = CURRENT_TIMESTAMP;

-- Verify users were created
SELECT username, email, role, is_active, created_at FROM ai_admin_users;