# 🤖 Zin AI Bridge - Setup Guide

A Windows desktop application that connects your PC to your web-based AI system with real-time monitoring and heartbeat tracking.

## 🚀 Quick Setup

### 1. Database Setup
1. Open your MySQL/phpMyAdmin
2. Make sure you have an `ai` database
3. Run the SQL in `setup-database.sql`:
   ```sql
   USE ai;
   CREATE TABLE IF NOT EXISTS pc_status (
       id INT AUTO_INCREMENT PRIMARY KEY,
       system_info JSON NOT NULL,
       last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       UNIQUE KEY unique_pc (id)
   );
   ```

### 2. Configure Database Connection
1. **Copy the example file**: `cp example_main.js main.js`
2. **Edit `main.js`** lines 18-23 with your MySQL credentials:
```javascript
this.dbConfig = {
    host: 'your-domain.com',    // Your MySQL server host
    user: 'your_mysql_user',    // Your MySQL username
    password: 'your_mysql_pass', // Your MySQL password  
    database: 'your_database'   // Your database name
};
```
3. **Update the web admin URL** line 93 & 199:
```javascript
shell.openExternal('https://your-domain.com/ai/admin/');
```

**⚠️ Security Note**: `main.js` is in `.gitignore` to protect your credentials!

### 3. Run the Application
```bash
cd C:\claude-code\ai\bridge
npm start
```

## 🌐 Web Integration

The bridge creates a new API endpoint at:
- `http://localhost/claude-code/ai/api/pc-bridge-status.php`

### Test the API
Visit the URL above or use:
```javascript
fetch('/claude-code/ai/api/pc-bridge-status.php')
  .then(r => r.json())
  .then(data => console.log(data));
```

### API Response Format
```json
{
  "success": true,
  "status": "online",
  "last_ping": "2025-09-10 08:15:30",
  "seconds_since_ping": 15,
  "system_info": {
    "hostname": "YOUR-PC-NAME",
    "platform": "win32",
    "arch": "x64",
    "uptime": 123456,
    "cpus": 8,
    "memory": {
      "total": 17179869184,
      "free": 8589934592,
      "used": 8589934592,
      "usage_percent": 50.0
    }
  }
}
```

## 📊 Add to Web Admin Panel

Update your admin panel JavaScript to include PC Bridge status:
```javascript
async function checkPCBridgeStatus() {
    try {
        const response = await fetch('../api/pc-bridge-status.php');
        const data = await response.json();
        
        if (data.success && data.status === 'online') {
            // Update UI to show PC is connected
            document.getElementById('pcStatus').innerHTML = '🟢 PC Bridge Online';
            document.getElementById('pcInfo').innerHTML = `
                ${data.system_info.hostname} - 
                ${data.system_info.memory.usage_percent}% RAM
            `;
        } else {
            document.getElementById('pcStatus').innerHTML = '🔴 PC Bridge Offline';
        }
    } catch (error) {
        document.getElementById('pcStatus').innerHTML = '⚠️ PC Bridge Unknown';
    }
}

// Check every 30 seconds
setInterval(checkPCBridgeStatus, 30000);
checkPCBridgeStatus(); // Initial check
```

## 🔧 Features

- **Real-time System Monitoring** - CPU, memory, uptime tracking
- **Database Integration** - Stores PC status and system info
- **Web API** - RESTful endpoint for web integration  
- **Beautiful UI** - Matches your PulseCore theme
- **System Tray** - Runs in background with tray icon
- **Activity Logging** - Green console-style activity feed
- **Auto-reconnect** - Handles database disconnections gracefully

## 🚀 Development Workflow

### During Development (Recommended)
```bash
# Quick launch for development
npm start

# Or double-click: Launch-Bridge.bat
```

### Build for Distribution (When Development Complete)
```bash
# Option 1: Simple portable folder (works reliably)
npm run build-simple

# Option 2: Professional installer (may need admin rights)
npm run build-win

# Option 3: Unpacked version (good for testing)
npm run pack
```

**Development Tip**: Use `npm start` during development - it's fast and you see all console output. Build the .exe only when your features are complete!

## ⚡ Troubleshooting

**"Database connection failed"** - Check MySQL credentials in `main.js`
**"IPC handler not registered"** - Restart the app, handlers are now fixed
**"Tray icon not found"** - App works without icon, add `.png` files to `assets/`

## 🎯 Next Steps

1. **Database Setup** ✅ Run the SQL
2. **Configure Credentials** ✅ Update main.js
3. **Test Connection** ✅ Check the web API
4. **Add to Admin Panel** ✅ Integrate status checks
5. **Add Icons** (Optional) Place icon files in assets/
6. **Build .exe** (Optional) Create installer

Your PC AI Bridge is ready! 🚀