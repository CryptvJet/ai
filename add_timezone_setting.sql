-- Add timezone setting to ai_settings table
-- This allows users to configure their timezone for accurate time responses

INSERT INTO ai_settings (setting_key, setting_value, category, description) 
VALUES ('user_timezone', 'America/New_York', 'identity', 'User timezone for time display and conversation timestamps');

-- Verify the setting was added
SELECT * FROM ai_settings WHERE setting_key = 'user_timezone';