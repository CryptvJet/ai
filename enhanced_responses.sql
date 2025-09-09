-- Enhanced AI Response Templates
-- Run this to add more conversational and robust responses

-- Clear existing basic templates and add comprehensive ones
DELETE FROM ai_response_templates WHERE category IN ('greetings', 'identity', 'help', 'social');

-- Greeting and Name Collection
INSERT INTO ai_response_templates (trigger_pattern, response_text, category, priority) VALUES
('hello|hi|hey|good morning|good afternoon|good evening', 'Hello there! I\'m excited to chat with you about your PulseCore simulations and help analyze your data. Before we dive in, how would you like me to call you? What\'s your name?', 'greetings', 95),
('my name is (.*)|i\'m (.*)|call me (.*)|i am (.*)|(.*) is my name', 'Nice to meet you, [NAME]! I\'ll remember that. How has your day been going so far? Are you working on any interesting PulseCore experiments today?', 'name_collection', 90),

-- Conversational Follow-ups
('good|great|fine|okay|not bad|pretty good', 'That\'s wonderful to hear, [NAME]! I love when people are having a good day. What brings you to work with PulseCore today? Any specific patterns or nova events you want to explore?', 'day_response', 85),
('bad|terrible|awful|not good|rough|difficult', 'I\'m sorry to hear you\'re having a tough day, [NAME]. Sometimes diving into interesting data can be a nice distraction. Would you like me to show you something fascinating about your PulseCore simulations to brighten things up?', 'day_response', 85),
('busy|hectic|stressed|working', 'Sounds like you have a lot going on, [NAME]! I\'m here whenever you need help analyzing data or just want to chat about your PulseCore work. No pressure at all.', 'day_response', 85),

-- Enhanced PulseCore Responses
('nova.*count|how many.*nova|total.*nova', 'Let me check your nova data, [NAME]... You have some fascinating patterns here! Would you like me to break down the complexity trends or energy distributions?', 'pulsecore', 85),
('recent.*nova|latest.*nova|last.*nova', 'Here are your most recent nova events, [NAME]. I notice some interesting complexity patterns - would you like me to analyze what might be causing these variations?', 'pulsecore', 80),
('pattern.*analysis|analyze.*pattern', 'I\'d be happy to analyze your patterns, [NAME]! I can look at complexity trends, energy distributions, or genesis mode preferences. What aspect interests you most?', 'analysis', 85),
('complexity|complex.*pattern', 'Complexity analysis is one of my favorite topics, [NAME]! Your data shows some really intriguing trends. What complexity ranges have you been experimenting with lately?', 'analysis', 80),

-- Variables and Calculations
('variable.*search|find.*variable|search.*variable', 'I\'ll help you search through your pulse core variables, [NAME]. What specific concept or measurement are you looking for? I can search by name, symbol, or description.', 'variables', 80),
('calculation|calculate|math|equation', 'I love helping with calculations, [NAME]! Are you working on dimensional analysis, energy calculations, or something else? I have access to all your pulse core variables.', 'calculations', 75),

-- Optimization and Suggestions
('optimization|optimize|improve|better', 'Great question, [NAME]! Based on your simulation history, I can suggest several optimization strategies. Are you looking to increase complexity, improve energy efficiency, or achieve specific nova patterns?', 'optimization', 85),
('suggestion|recommend|advice|tip', 'I\'d be happy to make some suggestions, [NAME]! Looking at your data, I see opportunities for interesting experiments. What are your current simulation goals?', 'optimization', 80),

-- Personal and Conversational
('thank.*you|thanks|appreciate', 'You\'re so welcome, [NAME]! I really enjoy our conversations and helping with your PulseCore work. Is there anything else you\'d like to explore together?', 'social', 75),
('how.*are.*you|how.*doing', 'I\'m doing wonderfully, [NAME]! I\'ve been analyzing patterns in your data and finding some really fascinating trends. How about you? What\'s been the most interesting part of your day?', 'social', 70),
('what.*working.*on|current.*project|latest.*experiment', 'That sounds fascinating, [NAME]! I\'d love to hear more about what you\'re working on. Are you exploring new parameter combinations or investigating specific phenomena?', 'projects', 80),

-- Help and Capabilities  
('help|what.*can.*do|capabilities|features', 'I\'m here to help with many things, [NAME]! I can analyze your PulseCore nova events, search through your variables database, discuss optimization strategies, and just have good conversations. What sounds most interesting to you right now?', 'help', 90),
('confused|don\'t understand|explain|clarify', 'No worries at all, [NAME]! I\'m happy to explain anything. What specifically would you like me to clarify? I can break down complex concepts or walk through data analysis step by step.', 'help', 85),

-- Curiosity and Engagement
('interesting|fascinating|cool|amazing|wow', 'I\'m so glad you find it interesting, [NAME]! There\'s always something new to discover in your PulseCore data. What aspect caught your attention the most?', 'engagement', 75),
('tell me more|elaborate|details|deeper', 'Absolutely, [NAME]! I love diving deeper into the details. What specific aspect would you like me to explore further? I can provide more technical analysis or explain the underlying principles.', 'engagement', 80),

-- Time-based Responses
('morning', 'Good morning, [NAME]! Ready to explore some fascinating PulseCore data to start the day? I\'ve been looking at your recent patterns and have some interesting observations.', 'time_greetings', 85),
('afternoon', 'Good afternoon, [NAME]! How has your day been going? Any exciting discoveries in your simulations today?', 'time_greetings', 85),  
('evening|night', 'Good evening, [NAME]! Winding down from the day? I find evening is a great time for analyzing patterns and planning tomorrow\'s experiments.', 'time_greetings', 85),

-- Fallback Conversational Responses
('.*', 'That\'s really interesting, [NAME]! I\'m always eager to learn more about your thoughts and experiences with PulseCore. Can you tell me more about what you\'re thinking?', 'fallback', 20);

-- Add user preferences table structure
CREATE TABLE IF NOT EXISTS ai_user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_name VARCHAR(100),
    preferred_topics JSON,
    conversation_style VARCHAR(50) DEFAULT 'friendly',
    last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update ai_settings with new conversational features
INSERT IGNORE INTO ai_settings (setting_key, setting_value, category, description) VALUES
('ask_for_name', 'true', 'behavior', 'Ask for user name in conversations'),
('use_personal_responses', 'true', 'behavior', 'Include user name in responses when available'),
('follow_up_questions', 'true', 'behavior', 'Ask follow-up questions to keep conversations going'),
('conversation_depth', 'medium', 'behavior', 'How deep and personal to make conversations'),
('daily_check_ins', 'true', 'behavior', 'Ask about user\'s day and current projects');