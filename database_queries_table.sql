-- Database Query Management Table
-- Stores configurations for AI responses to PulseCore database queries

CREATE TABLE IF NOT EXISTS ai_database_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    trigger_patterns JSON NOT NULL COMMENT 'Array of trigger patterns for this table',
    search_fields JSON NOT NULL COMMENT 'Array of database fields to search in',
    response_template TEXT NOT NULL COMMENT 'Template for formatting responses with {field} placeholders',
    priority INT DEFAULT 50 COMMENT 'Priority for pattern matching (1-100)',
    max_results INT DEFAULT 5 COMMENT 'Maximum number of results to return',
    active BOOLEAN DEFAULT true COMMENT 'Whether this configuration is active',
    usage_count INT DEFAULT 0 COMMENT 'Number of times this query config has been used',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_table_name (table_name),
    INDEX idx_active (active),
    INDEX idx_priority (priority),
    UNIQUE KEY unique_active_table (table_name, active) -- Only one active config per table
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default configurations for common PulseCore tables
INSERT INTO ai_database_queries (
    table_name, 
    trigger_patterns, 
    search_fields, 
    response_template, 
    priority, 
    max_results, 
    active
) VALUES 
(
    'pulse_core_variables',
    '["tell me about *", "what is *", "variable *", "show me *", "find variable *"]',
    '["name", "description", "physical_meaning"]',
    'Variable: {name}\nValue: {value}\nUnit: {unit}\nDescription: {description}\nPhysical Meaning: {physical_meaning}',
    80,
    3,
    true
),
(
    'nova_events',
    '["nova *", "show nova*", "what happened *", "events *", "find event *"]',
    '["event_type", "description", "metadata"]',
    'Nova Event: {event_type}\nTime: {timestamp}\nDescription: {description}\nSeverity: {severity_level}',
    75,
    5,
    true
),
(
    'climax_groups',
    '["climax *", "group *", "show groups*", "find group *"]',
    '["group_name", "description", "purpose"]',
    'Climax Group: {group_name}\nDescription: {description}\nPurpose: {purpose}\nMembers: {member_count}',
    70,
    3,
    true
);

-- Add default response patterns for system diagnostics
INSERT INTO ai_database_queries (
    table_name, 
    trigger_patterns, 
    search_fields, 
    response_template, 
    priority, 
    max_results, 
    active
) VALUES 
(
    'system_diagnostics',
    '["system status*", "diagnostic*", "health check*", "show diagnostics*"]',
    '["component", "status", "message"]',
    'System Component: {component}\nStatus: {status}\nMessage: {message}\nLast Check: {last_check}',
    60,
    10,
    true
);