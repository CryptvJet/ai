-- Fix Learning Settings
-- Check if learning_enabled setting exists and create it if needed

-- First, check current learning setting
SELECT 'Current learning_enabled setting:' as info;
SELECT setting_key, setting_value, category, description 
FROM ai_settings 
WHERE setting_key = 'learning_enabled';

-- Insert learning_enabled setting if it doesn't exist
INSERT IGNORE INTO ai_settings (setting_key, setting_value, category, description) 
VALUES ('learning_enabled', 'true', 'general', 'Enable AI learning from conversations');

-- Verify it was created/updated
SELECT 'After fix - learning_enabled setting:' as info;
SELECT setting_key, setting_value, category, description 
FROM ai_settings 
WHERE setting_key = 'learning_enabled';

-- Check ai_learning table structure
SELECT 'ai_learning table structure:' as info;
DESCRIBE ai_learning;

-- Count current learning entries
SELECT 'Current learning data count:' as info;
SELECT COUNT(*) as total_entries FROM ai_learning;

-- Show recent learning entries if any
SELECT 'Recent learning entries (if any):' as info;
SELECT id, pattern_type, confidence_score, usage_count, created_at 
FROM ai_learning 
ORDER BY created_at DESC 
LIMIT 5;

-- Show all AI settings for reference
SELECT 'All AI settings:' as info;
SELECT setting_key, setting_value 
FROM ai_settings 
ORDER BY setting_key;