# SSL/HTTPS Setup for PC Bridge

## Getting SSL Certificates from cPanel

1. **Login to cPanel** on your server
2. **Go to SSL/TLS section**
3. **Generate or import certificates**:
   - Use **Let's Encrypt** (free, auto-renewing)
   - Or upload purchased SSL certificates

## Certificate Files Needed

Place these files in `/ai/data/pws/certs/`:
- `server.crt` - SSL certificate file
- `server.key` - Private key file

## Configuration

1. **Copy certificate files** from cPanel to `/ai/data/pws/certs/`
2. **Enable HTTPS** by editing `/ai/data/pws/ssl_config.json`:
   ```json
   {
     "enabled": true,
     "port": 8443,
     "cert": "server.crt",
     "key": "server.key"
   }
   ```
3. **Restart the bridge server**

## Security Notes

- Keep private key files secure and never share them
- Certificate files should have restricted permissions
- Consider using Let's Encrypt for automatic renewal

## Testing HTTPS

Once configured, the bridge will run on:
- `https://localhost:8443/status` 
- `https://localhost:8443/api/status`

## API Key Setup

1. Generate API key: `openssl rand -hex 32`
2. Add to bridge config in desktop/web admin panels
3. Both desktop and web save to the same config file automatically