# Zin AI Chat System - Complete Setup Guide

## Overview

This is a complete web-based AI chat system that integrates with your PulseCore Binary Pulse Theory data. The system provides:

- **Web Chat Interface** (`ai/index.html`) - Voice-enabled chat for users
- **Admin Panel** (`ai/admin/index.html`) - Management interface
- **Database Integration** - Connects to your existing `vemite5_pulse-core` MySQL database
- **PulseCore Analysis** - Real-time nova events and complexity data analysis
- **Voice Features** - Speech recognition and text-to-speech
- **Learning System** - AI learns from conversations and data patterns

## Prerequisites

Before setup, ensure you have:
- **MySQL Server** running with `vemite5_pulse-core` database
- **PHP 7.4+** with PDO MySQL extension
- **Web Server** (Apache/Nginx) or local development server
- **Modern Browser** (Chrome/Edge recommended for voice features)

## Quick Start (4 Steps)

1. **Configure Database**
   ```bash
   cd ai/api
   # Edit db_config.php with your MySQL credentials (see Database Configuration section below)
   ```

2. **Run Database Migrations**
   ```bash
   # From ai/ directory
   php migrate.php migrate
   ```

3. **Test Setup**
   ```bash
   php api/db_config.php  # Test all database connections
   ```

4. **Access the Interface**
   - **Chat Interface**: Open `ai/index.html` in your browser
   - **Admin Panel**: Open `ai/admin/index.html` in your browser

## Database Configuration

### 1. Update Database Credentials

Edit `ai/api/db_config.php` and replace these values:
```php
$DB_HOST = 'localhost';
$DB_NAME = 'vemite5_pulse-core-ai';  // Your AI database
$DB_USER = 'your_mysql_username';     // Replace with your MySQL username
$DB_PASS = 'your_mysql_password';     // Replace with your MySQL password
```

### 2. Database Structure

The system uses your existing `vemite5_pulse-core` database and adds AI tables:
- `ai_conversations` - Chat sessions
- `ai_messages` - All messages and responses  
- `ai_response_templates` - Admin-editable responses
- `ai_settings` - AI configuration
- `ai_learning` - Pattern learning data

### 3. Run Migrations

```bash
# Check migration status
php migrate.php status

# Run all pending migrations
php migrate.php migrate

# Run specific migration
php migrate.php run 001_initial_ai_tables.sql
```

## Features Overview

### Chat Interface (`/ai/index.html`)
- **Voice Recognition** - Click microphone to speak
- **Real-time Stats** - Shows PulseCore nova data
- **Smart Responses** - Integrates with your data
- **Session Management** - Tracks conversation history

### Admin Panel (`/ai/admin/index.html`)
- **AI Identity** - Configure name and personality
- **Response Templates** - Edit pre-written responses
- **Conversation Analytics** - View chat statistics  
- **Database Integration** - Monitor connections
- **Learning Management** - Control AI learning

### API Endpoints
- `api/chat.php` - Main chat processing
- `api/settings.php` - AI configuration
- `api/pulsecore-stats.php` - PulseCore data
- `api/admin.php` - Admin functions

## AI Capabilities

### Data Integration
- **Nova Events** - Analyzes nova patterns, complexity, energy
- **Variables Database** - Searches pulse_core_variables
- **Real-time Stats** - Live PulseCore statistics

### Response Types
1. **Template Responses** - Pre-configured answers
2. **Data Queries** - PulseCore/Variables analysis  
3. **PC AI** - Advanced AI when PC connected
4. **Fallback** - Basic conversational responses

### Voice Features
- **Speech Recognition** - Browser Web Speech API
- **Text-to-Speech** - Automatic response reading
- **Voice Indicators** - Visual feedback while listening

## Advanced Usage Instructions

### 1. Chat Interface Advanced Features

#### Voice Commands
- **Activate Voice**: Click microphone icon or press `Ctrl+M`
- **Voice Settings**: Adjust speech rate and voice in browser settings
- **Voice Indicators**: 
  - Red pulse = Listening for input
  - Blue pulse = Processing speech
  - Green pulse = Speaking response

#### Chat Modes
- **Chill Mode** (Default): Basic conversational AI
- **Full Power Mode**: Advanced AI analysis (requires Ollama integration)
- **Voice Mode**: Continuous voice conversation
- **Text Mode**: Traditional text-based chat

#### Session Management
- **Session Persistence**: Conversations auto-save every message
- **Session History**: Access previous conversations via admin panel
- **Session Export**: Download conversation transcripts as JSON/TXT
- **Multi-Session**: Open multiple chat windows for different topics

### 2. Admin Panel Advanced Operations

#### AI Identity Configuration
```bash
# Access: ai/admin/index.html → AI Identity tab
```
- **Name Configuration**: Set custom AI name and personality
- **Response Style**: Configure formal/casual/conversational tone
- **Welcome Messages**: Customize greeting and introduction text
- **Personality Traits**: Define helpful/analytical/curious characteristics

#### Database Management
```bash
# Direct database queries through admin interface
```
- **Conversation Analytics**: View message counts, session duration, user patterns
- **Data Export**: Bulk export conversations, learning data, statistics
- **Database Cleanup**: Remove old sessions, optimize tables
- **Backup Management**: Schedule automated database backups

#### Response Template System
- **Pattern Matching**: Use regex patterns for trigger detection
- **Priority System**: Higher numbers (90-100) override lower priorities
- **Categories**: Organize templates (greetings, help, pulsecore, analysis)
- **Dynamic Variables**: Use [NAME], [TIME], [DATA] placeholders
- **A/B Testing**: Create multiple responses for same trigger

### 3. PulseCore Data Integration

#### Real-time Statistics
```bash
# Data refreshes every 30 seconds automatically
```
- **Nova Events**: Live count, complexity analysis, energy distribution
- **Climax Groups**: Pattern grouping and trend analysis
- **Variables Database**: Search and analyze pulse_core_variables
- **Performance Metrics**: Query response times, database load

#### Data Analysis Commands
In chat interface, use these commands:
```
analyze nova patterns          # Complexity trend analysis
search variables [term]        # Search pulse_core_variables
show recent nova events        # Last 50 nova events
calculate energy distribution  # Energy analysis across complexity ranges
optimize parameters           # Suggest simulation improvements
export data [format]          # Export data as CSV/JSON
```

#### Custom Queries
```sql
-- Access via admin panel → Database → Custom Query
SELECT * FROM nova_events WHERE complexity > 75;
SELECT * FROM pulse_core_variables WHERE symbol LIKE '%energy%';
```

### 4. Voice Recognition Advanced Setup

#### Browser Configuration
```bash
# Chrome/Edge: Settings → Privacy → Site Settings → Microphone
# Firefox: about:config → media.webspeech.recognition.enable → true
```

#### Voice Commands
- **"Start voice mode"**: Enable continuous voice chat
- **"Stop listening"**: Disable voice input
- **"Repeat that"**: Re-read last AI response
- **"Save conversation"**: Export current session
- **"New session"**: Start fresh conversation

#### Troubleshooting Voice
```bash
# Test microphone access
navigator.mediaDevices.getUserMedia({audio: true})

# Check speech recognition support
'webkitSpeechRecognition' in window || 'SpeechRecognition' in window
```

### 5. Performance Optimization

#### Database Optimization
```sql
-- Run monthly for optimal performance
OPTIMIZE TABLE ai_conversations, ai_messages, ai_learning;
ANALYZE TABLE nova_events, climax_groups, pulse_core_variables;
```

#### Memory Management
```bash
# Monitor PHP memory usage
php -r "echo ini_get('memory_limit');"

# Increase if needed (in php.ini)
memory_limit = 512M
max_execution_time = 60
```

#### Caching Configuration
```php
// Add to db_config.php for better performance
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
$pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
```

### 6. Learning System Management

#### Pattern Learning
- **Automatic Learning**: System learns from conversation patterns
- **Manual Training**: Add specific responses via admin panel
- **Confidence Scoring**: System tracks response effectiveness (0.0-1.0)
- **Usage Analytics**: Monitor which patterns are most effective

#### Learning Data Export
```bash
# Export learning patterns for analysis
php api/export-learning.php > learning_data.json
```

#### Reset Learning
```sql
-- Clear all learning data (use carefully)
TRUNCATE TABLE ai_learning;
DELETE FROM ai_response_templates WHERE category = 'learned';
```

### 7. Integration with External Systems

#### Ollama AI Integration
```bash
# Install Ollama (for Full Power mode)
curl -fsSL https://ollama.ai/install.sh | sh
ollama pull llama2
ollama serve --host 0.0.0.0:11434
```

#### API Endpoints for External Access
```bash
# Chat API
POST /ai/api/chat.php
{
  "message": "Your question",
  "session_id": "unique_session_id",
  "mode": "chill|full-power"
}

# Statistics API  
GET /ai/api/pulsecore-stats.php
GET /ai/api/conversation-stats.php

# Admin API
POST /ai/api/admin.php
{
  "action": "get_settings|update_settings|export_data",
  "data": {}
}
```

#### Webhook Integration
```php
// Add to chat.php for external notifications
function sendWebhook($data) {
    $url = 'https://your-webhook-url.com/ai-notification';
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    file_get_contents($url, false, stream_context_create($options));
}
```

### 8. Security and Authentication

#### Access Control
```php
// Add authentication to admin panel (admin/auth.php)
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
```

#### Rate Limiting
```php
// Add to chat.php to prevent abuse
$redis = new Redis();
$key = 'chat_limit_' . $_SERVER['REMOTE_ADDR'];
if ($redis->incr($key) > 60) { // 60 requests per minute
    http_response_code(429);
    exit('Rate limit exceeded');
}
$redis->expire($key, 60);
```

#### Input Sanitization
```php
// Already implemented in all endpoints
$message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
```

### 9. Monitoring and Logging

#### Error Logging
```php
// Enable detailed logging in php.ini
log_errors = On
error_log = /var/log/php/ai_errors.log
```

#### Performance Monitoring
```bash
# Monitor database performance
SHOW FULL PROCESSLIST;
SHOW STATUS LIKE 'Threads%';
SHOW STATUS LIKE 'Connections';
```

#### Usage Analytics
```sql
-- Daily conversation statistics
SELECT DATE(started_at) as date, 
       COUNT(*) as conversations,
       AVG(total_messages) as avg_messages
FROM ai_conversations 
WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(started_at);
```

### 10. Development and Customization

#### Custom Features Development
```bash
# File structure for new features
ai/
├── api/custom-feature.php     # Backend logic
├── js/custom-feature.js       # Frontend functionality  
├── css/custom-feature.css     # Styling
└── migrations/00X_custom.sql  # Database changes
```

#### Testing Environment
```bash
# Set up development environment
cp ai/api/db_config.php ai/api/db_config_test.php
# Edit test config to use test database
# Run tests against test database
```

#### Backup and Recovery
```bash
# Daily backup script
mysqldump -u username -p vemite5_pulse-core > backup_$(date +%Y%m%d).sql

# Recovery
mysql -u username -p vemite5_pulse-core < backup_20240909.sql
```

## Troubleshooting

### Database Connection Issues
```bash
# Test connections
php api/db_config.php

# Common fixes:
# 1. Check MySQL credentials
# 2. Verify database name: vemite5_pulse-core
# 3. Ensure user has permissions on database
```

### Migration Problems
```bash
# Check migration status
php migrate.php status

# View pending migrations
php migrate.php pending

# Manual SQL execution if needed
mysql -u username -p vemite5_pulse-core < migrations/001_initial_ai_tables.sql
```

### No PulseCore Data
- Verify `nova_events` and `climax_groups` tables exist
- Check table permissions for database user
- Ensure tables have data to analyze

### Voice Not Working
- Requires HTTPS or localhost
- Enable microphone permissions in browser
- Chrome/Edge work best for Web Speech API

## File Structure
```
ai/
├── index.html              # Main chat interface
├── admin/
│   └── index.html         # Admin panel
├── api/
│   ├── db_config.php      # Database connections
│   ├── chat.php           # Chat processing
│   ├── settings.php       # Configuration API
│   └── pulsecore-stats.php # Data statistics
├── css/
│   ├── chat.css           # Chat interface styling
│   └── admin.css          # Admin panel styling
├── js/
│   ├── chat.js            # Chat functionality
│   └── admin.js           # Admin functionality
├── migrations/
│   └── 001_initial_ai_tables.sql # Database schema
├── migrate.php            # Migration tool
└── SETUP.md              # This file
```

## Security Notes
- `db_config.php` is gitignored (contains credentials)
- Always use prepared statements (already implemented)
- Consider HTTPS for production voice features
- Admin panel has no authentication (add if needed)

## Future Enhancements
- PC AI integration with local models
- Advanced pattern learning
- Multi-user support with authentication
- Real-time PulseCore event notifications
- Custom AI personality development