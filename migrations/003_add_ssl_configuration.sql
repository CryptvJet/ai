-- AI Database Migration 003: SSL Configuration Table
-- Run date: 2025-09-12
-- Description: Creates SSL configuration table for HTTPS bridge server management

USE `vemite5_pulse-core-ai`;

-- SSL Configuration table - stores HTTPS certificate settings
CREATE TABLE IF NOT EXISTS ai_ssl_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enabled BOOLEAN DEFAULT false,
    port INT DEFAULT 8443,
    cert_filename VARCHAR(255) DEFAULT 'server.crt',
    key_filename VARCHAR(255) DEFAULT 'server.key',
    cert_uploaded BOOLEAN DEFAULT false,
    key_uploaded BOOLEAN DEFAULT false,
    cert_upload_date TIMESTAMP NULL,
    key_upload_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100) DEFAULT 'admin',
    INDEX idx_enabled (enabled),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default SSL configuration
INSERT IGNORE INTO ai_ssl_config (id, enabled, port, cert_filename, key_filename) VALUES
(1, true, 8443, 'server.crt', 'server.key');