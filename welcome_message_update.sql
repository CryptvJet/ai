-- Update AI Settings with proper welcome message
-- Run this to ensure the AI asks for the user's name on first interaction

INSERT IGNORE INTO ai_settings (setting_key, setting_value, category, description) VALUES
('ai_name', 'AI Assistant', 'identity', 'Display name for the AI'),
('welcome_message', 'Hi! I\'m your AI Assistant. I hope you are doing well today! What should I call you?', 'behavior', 'Initial greeting message that asks for user name'),
('personality', 'friendly,conversational,curious,helpful', 'behavior', 'AI personality traits'),
('response_style', 'conversational', 'behavior', 'How the AI should respond to users');

-- Update existing welcome message if it exists
UPDATE ai_settings 
SET setting_value = 'Hi! I\'m your AI Assistant. I hope you are doing well today! What should I call you?'
WHERE setting_key = 'welcome_message';