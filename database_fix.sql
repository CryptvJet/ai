-- Database Fix Script
-- This script fixes table naming inconsistencies and adds missing tables

-- Fix table names - the code expects ai_ prefix but schema had no prefix
-- Create the correctly named tables if they don't exist

-- AI Conversations table (properly named)
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    user_id VARCHAR(100) DEFAULT 'anonymous',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    session_name VARCHAR(255) DEFAULT 'Chat Session',
    total_messages INT DEFAULT 0,
    status ENUM('active', 'ended', 'archived') DEFAULT 'active',
    metadata JSON,
    INDEX idx_session_id (session_id),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Messages table (properly named)  
CREATE TABLE IF NOT EXISTS ai_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    message_type ENUM('user', 'ai') NOT NULL,
    content TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    processing_time_ms INT DEFAULT NULL,
    ai_mode ENUM('chill', 'full-power') DEFAULT 'chill',
    source ENUM('text', 'voice') DEFAULT 'text',
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_message_type (message_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Response Templates table
CREATE TABLE IF NOT EXISTS ai_response_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trigger_pattern VARCHAR(500) NOT NULL,
    response_text TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'general',
    priority INT DEFAULT 50,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    usage_count INT DEFAULT 0,
    INDEX idx_category (category),
    INDEX idx_active (active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Settings table
CREATE TABLE IF NOT EXISTS ai_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Learning table
CREATE TABLE IF NOT EXISTS ai_learning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_type VARCHAR(100) NOT NULL,
    pattern_data JSON NOT NULL,
    confidence_score FLOAT DEFAULT 0.5,
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_confidence_score (confidence_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Missing AI User Preferences table (needed for name storage)
CREATE TABLE IF NOT EXISTS ai_user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    user_name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    preferences JSON,
    INDEX idx_session_id (session_id),
    INDEX idx_user_name (user_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default AI settings (if they don't exist)
INSERT IGNORE INTO ai_settings (setting_key, setting_value, category, description) VALUES
('ai_name', 'ai', 'identity', 'Current name of the AI assistant'),
('personality', 'helpful,analytical,curious', 'personality', 'Core personality traits'),
('response_style', 'conversational', 'behavior', 'How the AI responds (formal, casual, conversational)'),
('pulsecore_integration', 'true', 'features', 'Enable PulseCore data integration'),
('variables_integration', 'true', 'features', 'Enable Variables database integration'),
('voice_enabled', 'true', 'features', 'Enable voice chat capabilities'),
('learning_enabled', 'true', 'features', 'Enable learning from conversations'),
('max_conversation_length', '100', 'limits', 'Maximum messages per conversation');

-- Insert sample response templates including name collection (if they don't exist)
INSERT IGNORE INTO ai_response_templates (trigger_pattern, response_text, category, priority) VALUES
('hello|hi|hey|good morning|good afternoon|good evening', 'Hello! I\'m your AI assistant. I can help analyze your PulseCore simulations and answer questions about your data. What would you like to explore today?', 'greetings', 90),
('what.*your.*name|who are you', 'I\'m currently called "ai" but I\'d love to have a proper name! You can help me choose one in the admin panel. What should I call you?', 'identity', 80),
('my name is|i\'m |call me |i am ', 'Nice to meet you, [NAME]! I\'ll remember your name for our future conversations. How can I help you today?', 'name_collection', 95),
('nova.*count|how many.*nova', 'Let me check your PulseCore nova data for you...', 'pulsecore', 70),
('help|what.*can.*do', 'I can analyze your PulseCore simulations, discuss nova patterns, help with variables calculations, and chat about your projects. What sounds most interesting to you right now?', 'help', 85),
('thank.*you|thanks', 'You\'re welcome[NAME]! I enjoy helping with your simulations and data analysis.', 'social', 60);

-- If old tables exist without ai_ prefix, migrate data
-- This is optional - only run if you have existing data in the wrong tables

-- Uncomment these lines if you need to migrate data:
-- INSERT IGNORE INTO ai_conversations SELECT * FROM conversations;
-- INSERT IGNORE INTO ai_messages SELECT * FROM messages;
-- DROP TABLE IF EXISTS conversations;  
-- DROP TABLE IF EXISTS messages;