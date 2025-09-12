-- AI Database Migration 004: Database Configuration Tables
-- Run date: 2025-09-12
-- Description: Creates database configuration tables for PulseCore and AI database management

USE `vemite5_pulse-core-ai`;

-- Database configurations table - stores connection settings for PulseCore and AI databases
CREATE TABLE IF NOT EXISTS ai_database_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_name VARCHAR(100) NOT NULL UNIQUE,
    config_type ENUM('pulsecore', 'ai') NOT NULL,
    server_host VARCHAR(255) NOT NULL,
    server_port INT DEFAULT 3306,
    database_name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password TEXT NOT NULL,
    connection_timeout INT DEFAULT 30,
    enabled BOOLEAN DEFAULT true,
    last_tested TIMESTAMP NULL,
    test_result ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    test_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100) DEFAULT 'admin',
    INDEX idx_config_type (config_type),
    INDEX idx_enabled (enabled),
    INDEX idx_last_tested (last_tested)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert empty default database configurations (to be filled via admin panel)
INSERT IGNORE INTO ai_database_configs (config_name, config_type, server_host, database_name, username, password, enabled) VALUES
('pulsecore_main', 'pulsecore', 'localhost', 'pulsecore_db', 'username', 'password', false),
('ai_main', 'ai', 'localhost', 'ai_db', 'username', 'password', false);