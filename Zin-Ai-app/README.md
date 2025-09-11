# Zin AI Management Hub (WPF)

**Desktop management center for your web-based AI chat system**

This is NOT a standalone chat app - it's a **management hub** that connects to and controls your existing web AI system.

## What This App Does

🎯 **Primary Purpose**: Management and monitoring hub for your web AI chat system  
📊 **Real-time Stats**: Live PulseCore database statistics and user activity  
🔧 **Web System Control**: Manage your web-based AI chat from desktop  
🗄️ **Database Management**: Direct access to AI conversations, users, and BPT data  
⚡ **Server Monitoring**: Connection status for all system components  

## Features

### 🖥️ Management Dashboard (Main Interface)
- **Real-time Database Stats**: Live PulseCore data (novas, climaxes, complexity, sessions)
- **Connection Status**: Monitor Ollama AI, MySQL database, web server connectivity  
- **User Activity**: Track active users, conversation counts, system load
- **System Health**: Component status monitoring and alerts

### 💬 Integrated Chat Interface (Secondary Tab)
- Test AI responses without using the web interface
- Voice recognition and synthesis for testing
- Direct Ollama integration for model testing

### ⚙️ System Administration
- **Database Configuration**: Manage MySQL connection settings
- **AI Model Settings**: Configure Ollama models and parameters  
- **App Settings**: Update intervals, notifications, system preferences
- **User Management**: Monitor and manage web chat users

## Requirements

- .NET 8.0 SDK
- Running MySQL server with `vemite5_pulse-core` database
- Your existing web AI chat system (in `/ai` directory)
- Ollama server (for AI model testing)

## Quick Start

```bash
# From project root
cd ai/desktop-app

# Run the management hub
dotnet run
```

## Database Connection

The app connects to your existing database using credentials from `ai/api/db_config.php`:
- **Server**: localhost
- **Database**: vemite5_pulse-core  
- **User**: vemite5_p-core
- **Password**: [automatically loaded]

## Main Interface Layout

### Dashboard (Primary View)
- **Left Panel**: System navigation and controls
- **Center Panel**: Real-time database statistics and connection status
- **Right Panel**: System health monitoring and quick actions

### Secondary Tabs
- **Chat Interface**: Test AI responses and voice features
- **Journal Mode**: AI writing companion for testing
- **Admin Panel**: Deep system configuration

## Usage

### System Monitoring
1. **Launch the app** - Connects automatically to your database
2. **Monitor Stats** - View live PulseCore data updates every 5 minutes (configurable)
3. **Check Connections** - Verify all system components are online
4. **Track Users** - Monitor web chat activity and performance

### Database Management  
- View conversation statistics
- Monitor user activity patterns
- Access BPT calculation data
- Track system performance metrics

### AI System Testing
- Test Ollama model responses
- Verify voice recognition/synthesis  
- Debug chat functionality
- Monitor AI learning patterns

## Architecture

This is a **bridge application** that connects to:
- **Your Web AI System** (main chat interface for users)
- **MySQL Database** (shared data storage)
- **Ollama AI Server** (shared AI model)
- **PulseCore BPT Engine** (calculation system)

## Important Notes

⚠️ **This is NOT a replacement** for your web AI chat system  
✅ **This IS a management tool** to monitor and control that system  
🔗 **Shared Database**: Uses the same MySQL database as your web system  
🖥️ **Desktop Control**: Manage your web AI from a native Windows application  

## Troubleshooting

**Database shows "Error"**: Check MySQL server is running and credentials are correct  
**No stats updating**: Verify database contains PulseCore tables (nova_events, climax_groups)  
**Ollama connection fails**: Ensure `ollama serve` is running on port 11434  
**Build errors**: Verify .NET 8.0 SDK is installed  

## System Integration

```
Web Users → ai/index.html → MySQL Database ← Desktop Management Hub
                ↓                              ↑
           Ollama AI Server ←------------------┘
```

Your users interact with the web interface while you manage the system from this desktop hub.