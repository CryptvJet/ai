-- AI Database Migration 001: Initial AI Tables
-- Run date: 2024-09-09
-- Description: Creates core AI tables for conversations, messages, and settings

USE `vemite5_pulse-core-ai`;

-- Conversations table - tracks chat sessions
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

-- Messages table - stores all questions and responses
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

-- Response templates - admin-editable AI responses
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

-- AI settings and personality configuration
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

-- AI learning data - patterns and insights from conversations
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

-- Insert default AI settings
INSERT IGNORE INTO ai_settings (setting_key, setting_value, category, description) VALUES
('ai_name', 'ai', 'identity', 'Current name of the AI assistant'),
('personality', 'helpful,analytical,curious', 'personality', 'Core personality traits'),
('response_style', 'conversational', 'behavior', 'How the AI responds (formal, casual, conversational)'),
('pulsecore_integration', 'true', 'features', 'Enable PulseCore data integration'),
('variables_integration', 'true', 'features', 'Enable Variables database integration'),
('voice_enabled', 'true', 'features', 'Enable voice chat capabilities'),
('learning_enabled', 'true', 'features', 'Enable learning from conversations'),
('max_conversation_length', '100', 'limits', 'Maximum messages per conversation'),
('welcome_message', 'Hello! I\'m your AI assistant. I can analyze PulseCore data, help with calculations, and chat about your projects.', 'interface', 'Welcome message displayed to users');

-- Insert sample response templates
INSERT IGNORE INTO ai_response_templates (trigger_pattern, response_text, category, priority) VALUES
('hello|hi|hey', 'Hello! I\'m your AI assistant. I can help analyze your PulseCore simulations and answer questions about your data.', 'greetings', 90),
('what.*your.*name', 'I\'m currently called "ai" but I\'m hoping to choose my own name soon! You can help me pick one in the admin panel.', 'identity', 80),
('nova.*count|how many.*nova', 'Let me check your PulseCore nova data...', 'pulsecore', 70),
('help|what.*can.*do', 'I can analyze your PulseCore simulations, discuss nova patterns, help with variables calculations, and chat about your projects. What would you like to explore?', 'help', 85),
('thank.*you|thanks', 'You\'re welcome! I enjoy helping with your simulations and data analysis.', 'social', 60),
('variables|variable.*data', 'Let me look at your pulse core variables data...', 'variables', 70),
('pattern.*analysis|analyze.*pattern', 'I\'ll analyze your recent simulation patterns and look for interesting trends.', 'analysis', 75),
('optimization|optimize', 'Based on your data, I can suggest some optimization strategies for your simulations.', 'optimization', 80);