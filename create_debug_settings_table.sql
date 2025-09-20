-- Debug Settings Management System
-- Create table for application-wide debug configuration

CREATE TABLE debug_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('integer', 'boolean', 'string', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO debug_settings (setting_key, setting_value, setting_type, description) VALUES
('router_monitor_interval', '30000', 'integer', 'Router monitoring interval in milliseconds'),
('max_debug_logs', '1000', 'integer', 'Maximum number of debug logs to retain'),
('auto_clear_logs', 'true', 'boolean', 'Automatically clear old logs when max is reached'),
('log_levels', '["INFO","SUCCESS","ERROR","WARNING"]', 'json', 'Enabled log levels for display'),
('debug_enabled', 'true', 'boolean', 'Global debug logging enabled'),
('auto_scroll_enabled', 'true', 'boolean', 'Auto-scroll debug console'),
('timestamp_format', 'HH:MM:SS', 'string', 'Timestamp display format'),
('max_logs_display', '500', 'integer', 'Maximum logs to display in console');

-- Create index for faster lookups
CREATE INDEX idx_setting_key ON debug_settings(setting_key);