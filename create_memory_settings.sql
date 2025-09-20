-- Memory Management Settings for AI System
-- Add memory-related settings to ai_settings table

INSERT INTO ai_settings (setting_key, setting_value, category, description) VALUES
('conversation_memory_limit_mb', '5', 'limits', 'Browser cache memory limit in MB'),
('threshold_warning_level', '85', 'limits', 'Warning threshold percentage (70-95)'),
('threshold_critical_level', '99', 'limits', 'Critical threshold requiring action (95-99)'),
('default_refresh_option', 'partial', 'limits', 'Default refresh suggestion (full/partial/zip)'),
('compression_ratio', '10', 'limits', 'Zip refresh compression percentage (5-25)'),
('auto_refresh_enabled', 'false', 'limits', 'Automatically perform default refresh when critical threshold is reached'),
('memory_monitoring_enabled', 'true', 'limits', 'Enable real-time memory monitoring in chat interface'),
('threshold_animation_enabled', 'true', 'limits', 'Enable visual animations for threshold warnings')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- Verify the inserted settings
SELECT setting_key, setting_value, category, description 
FROM ai_settings 
WHERE category = 'limits' 
ORDER BY setting_key;