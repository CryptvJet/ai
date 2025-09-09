# AI Chat Interface Setup Guide

## Quick Start

1. **Configure Database**
   ```bash
   cd ai/api
   # Edit db_config.php with your MySQL credentials
   nano db_config.php
   ```

2. **Run Database Migrations**
   ```bash
   # From ai/ directory
   php migrate.php migrate
   ```

3. **Test Setup**
   ```bash
   php api/db_config.php  # Test database connections
   ```

4. **Access the Interface**
   - Chat Interface: `http://your-server.com/ai/`
   - Admin Panel: `http://your-server.com/ai/admin/`

## Database Configuration

### 1. Update Database Credentials

Edit `ai/api/db_config.php` and replace these values:
```php
$AI_DB_USER = 'your_mysql_username';
$AI_DB_PASS = 'your_mysql_password';
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

## Advanced Setup

### Migration System
Add new database changes:
```bash
# Create new migration file
touch migrations/002_add_new_feature.sql
# Edit the SQL file, then run:
php migrate.php migrate
```

### PC AI Integration (Future)
1. Install local AI model (Ollama + Llama)
2. Run local server on port 8000
3. System automatically detects and uses "Full Power" mode

### Custom Response Templates
Access admin panel → Responses → Add Template:
- **Trigger Pattern** - Regex pattern to match
- **Response Text** - What AI should respond
- **Category** - Organization (greetings, help, etc.)
- **Priority** - Higher numbers trigger first

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