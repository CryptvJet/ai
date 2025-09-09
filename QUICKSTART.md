# AI Chat Quick Start

## Setup Steps

1. **Copy and configure database settings**
   ```bash
   cd ai/api
   cp db_config.sample.php db_config.php
   # Edit db_config.php with your MySQL credentials
   ```

2. **Run database migrations**
   ```bash
   cd ..
   php migrate.php migrate
   ```

3. **Test the connection**
   ```bash
   php api/db_config.php
   ```

4. **Launch the interface**
   - Chat: Open `ai/index.html` in your browser
   - Admin: Open `ai/admin/index.html`

## Database Configuration

Edit `ai/api/db_config.php`:
```php
$DB_HOST = 'localhost';
$DB_NAME = 'vemite5_pulse-core';  
$DB_USER = 'your_actual_username';    // Your MySQL username
$DB_PASS = 'your_actual_password';    // Your MySQL password
```

## What's Created

The system adds these tables to your `vemite5_pulse-core` database:
- `ai_conversations` - Chat sessions
- `ai_messages` - All messages & responses  
- `ai_response_templates` - Editable responses
- `ai_settings` - AI configuration
- `ai_learning` - Pattern learning data

## Testing Commands

```bash
# Check database connection
php api/db_config.php

# Check migration status  
php migrate.php status

# Run pending migrations
php migrate.php migrate

# View all available commands
php migrate.php
```

## Ready to Go!

Once setup is complete, you'll have:
- 🎤 Voice-enabled chat interface
- 📊 Real-time PulseCore data analysis
- 🔧 Full admin control panel
- 💾 Complete conversation history
- 🧠 Learning AI that grows with your data

Your AI assistant will be able to analyze nova events, search variables, and provide intelligent insights about your PulseCore simulations!