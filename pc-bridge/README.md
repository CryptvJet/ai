# 🚀 PC AI Bridge - Basic Setup

## Quick Start Guide

### 1. Install Dependencies
```bash
cd C:\claude-code\pc-bridge
npm install
```

### 2. Setup Database
1. Open your MySQL/phpMyAdmin
2. Go to your `ai_chat_db` database
3. Run the SQL from `setup-database.sql`

### 3. Update Database Credentials
Edit `app.js` line 10-14:
```javascript
this.dbConfig = {
    host: 'localhost',
    user: 'your_mysql_user',     // ← Update this
    password: 'your_mysql_pass', // ← Update this  
    database: 'ai_chat_db'
};
```

### 4. Start PC Bridge
```bash
npm start
```

### 5. Test Connection
- Open: http://localhost:8080/status
- Should show: `{"status":"online","timestamp":"..."}`
- Check database test: http://localhost:8080/test-db

### 6. Check Web Interface
- Go to your AI web app
- Look for "PC Bridge: 🟢" status indicator
- If green = connected!
- If red = check steps above

## What This Does

✅ **PC App** runs on your computer (Node.js server)
✅ **Heartbeat** every 30 seconds to database  
✅ **Status API** for web app to check connection
✅ **System Info** basic computer details
✅ **Web Indicator** shows PC online/offline

## Next Steps

Once this basic connection works, we can add:
- File system access
- Process monitoring  
- System commands
- AI conversations about your PC
- And much more!

## 🔐 API Key Generation & Security

### Why Use API Keys?
API keys provide secure authentication for your bridge connection, especially important when:
- Using HTTPS on production servers
- Accessing bridge from remote locations  
- Protecting against unauthorized access
- Meeting security compliance requirements

### 🔑 Generating Secure API Keys

#### **Method 1: OpenSSL (Recommended for CentOS/Linux)**
```bash
# Generate a 64-character hex key (very secure)
openssl rand -hex 32

# Generate with sk- prefix (OpenAI style)
echo "sk-$(openssl rand -hex 24)"

# Generate base64 key (shorter, URL-safe)
openssl rand -base64 32
```

**Example output:** `sk-a1b2c3d4e5f6789012345678901234567890abcdef123456`

#### **Method 2: System Random (Linux/macOS)**
```bash
# Generate 32 random bytes as hex
head -c 32 /dev/urandom | xxd -p -c 32

# Generate with base64 encoding
head -c 32 /dev/urandom | base64

# Generate alphanumeric only (no special chars)
head -c 32 /dev/urandom | base64 | tr -d "=+/" | cut -c1-32
```

#### **Method 3: Python (Cross-platform)**
```bash
# Generate UUID-based key
python3 -c "import uuid; print('sk-' + str(uuid.uuid4()).replace('-', ''))"

# Generate random hex key
python3 -c "import secrets; print('sk-' + secrets.token_hex(24))"

# Generate URL-safe base64 key
python3 -c "import secrets; print('sk-' + secrets.token_urlsafe(32))"
```

#### **Method 4: PowerShell (Windows)**
```powershell
# Generate UUID-based key
"sk-" + [System.Guid]::NewGuid().ToString().Replace("-", "")

# Generate random hex key
"sk-" + -join ((1..48) | ForEach {'{0:X}' -f (Get-Random -Max 16)})
```

#### **Method 5: Online Generators (Quick)**
- **UUID Generator**: https://www.uuidgenerator.net/
- **Random.org**: https://www.random.org/strings/
- **JWT.io**: https://jwt.io/ (for JWT-style tokens)

### 🛡️ API Key Security Best Practices

#### **1. Storage & Access**
```bash
# Store in environment variables (recommended)
export BRIDGE_API_KEY="sk-your-generated-key-here"

# Or in a secure config file (outside web root)
echo "sk-your-key" > /etc/bridge/api_key.txt
chmod 600 /etc/bridge/api_key.txt
```

#### **2. Key Characteristics**
- ✅ **Length**: Minimum 32 characters, preferably 48-64
- ✅ **Entropy**: Use cryptographically secure random generators
- ✅ **Prefix**: Use `sk-` prefix for easy identification
- ✅ **Format**: Mix of letters, numbers, and safe symbols
- ❌ **Avoid**: Dictionary words, predictable patterns, short keys

#### **3. Implementation Security**
```javascript
// In your bridge server code
const apiKey = process.env.BRIDGE_API_KEY || 'sk-default-dev-key';

// Validate API key in middleware
function validateApiKey(req, res, next) {
    const providedKey = req.headers.authorization?.replace('Bearer ', '');
    if (providedKey !== apiKey) {
        return res.status(401).json({ error: 'Invalid API key' });
    }
    next();
}
```

#### **4. Regular Rotation**
```bash
# Create a rotation script
#!/bin/bash
NEW_KEY="sk-$(openssl rand -hex 24)"
echo "New API key: $NEW_KEY"
echo "Update your bridge configuration with this key"

# Schedule monthly rotation (crontab)
# 0 0 1 * * /path/to/rotate-api-key.sh
```

### 🔧 Configuring API Keys in Zin AI

#### **Desktop Admin Panel**
1. Open Zin AI Admin Panel
2. Go to **Configuration** tab  
3. Scroll to **Bridge Connection Configuration**
4. Set your connection details:
   - **Host**: `your-server.com`
   - **Port**: `443` (HTTPS) or `80` (HTTP)  
   - **API Key**: `sk-your-generated-key-here`
   - **Connection Type**: `HTTPS`
5. Click **Test Bridge Connection** to verify
6. Click **Save Bridge Configuration**

#### **Web Admin Panel**  
1. Open web admin at `http://localhost/ai/admin/`
2. Go to **🔗 Integrations** tab
3. Scroll to **Bridge Connection Settings**
4. Enter your connection details (same as above)
5. Click **🧪 Test Connection**
6. Click **💾 Save Bridge Settings**

### 🌐 Production HTTPS Setup

#### **SSL Certificate Requirements**
```bash
# For production, ensure you have:
# 1. Valid SSL certificate
# 2. Proper DNS configuration
# 3. Firewall rules for HTTPS (port 443)

# Test your HTTPS setup
curl -H "Authorization: Bearer sk-your-key" \
     https://your-server.com:443/api/status
```

#### **Nginx Proxy Configuration** (if using)
```nginx
server {
    listen 443 ssl;
    server_name your-server.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location /api/ {
        proxy_pass http://localhost:8080;
        proxy_set_header Authorization $http_authorization;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### 🚨 Security Warnings

❌ **Never do this:**
- Store API keys in version control (git)
- Use simple/predictable keys like `123456` or `password`
- Share keys in plain text emails or chat
- Use the same key across multiple environments
- Hard-code keys in source code

✅ **Always do this:**
- Use environment variables or secure config files
- Rotate keys regularly (monthly/quarterly)
- Use HTTPS for all API key transmissions  
- Monitor API key usage and access logs
- Implement rate limiting and access controls

### 📝 Example Complete Setup

```bash
# 1. Generate a secure API key
BRIDGE_KEY=$(echo "sk-$(openssl rand -hex 24)")
echo "Generated key: $BRIDGE_KEY"

# 2. Store securely on server
echo "$BRIDGE_KEY" > /etc/bridge/api_key.txt
chmod 600 /etc/bridge/api_key.txt
chown bridge:bridge /etc/bridge/api_key.txt

# 3. Configure your bridge server to use it
export BRIDGE_API_KEY=$BRIDGE_KEY

# 4. Test the connection
curl -H "Authorization: Bearer $BRIDGE_KEY" \
     https://your-server.com/api/status

# 5. Update Zin AI admin panels with the key
```

This comprehensive security setup ensures your bridge connection is properly authenticated and protected! 🔒

## Troubleshooting

**PC Bridge won't start:**
- Check Node.js is installed: `node --version`
- Check MySQL credentials in app.js
- Run: `npm install` first

**Database connection fails:**
- Verify MySQL is running
- Check username/password in app.js
- Make sure `ai_chat_db` database exists
- Run the setup-database.sql

**Web app shows offline:**
- PC Bridge must be running (`npm start`)
- Check http://localhost:8080/status works
- Check browser console for errors

**API Key authentication fails:**
- Verify key is correct in both bridge server and Zin AI admin
- Check Authorization header format: `Bearer sk-your-key`
- Ensure HTTPS is properly configured for secure key transmission
- Test API key with curl command shown above