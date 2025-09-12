// PC AI Bridge - Basic Connection
const express = require('express');
const mysql = require('mysql2/promise');
const os = require('os');
const cron = require('node-cron');
const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

class PCBridge {
    constructor() {
        this.app = express();
        this.port = 8080;
        this.dbConfig = this.loadDatabaseConfig();
        this.isConnected = false;
        
        this.setupServer();
        this.startHeartbeat();
    }

    loadApiKey() {
        try {
            const configPath = path.join(__dirname, '../data/pws/bridge_config.json');
            if (fs.existsSync(configPath)) {
                const configData = fs.readFileSync(configPath, 'utf8');
                const config = JSON.parse(configData);
                this.apiKey = config.api_key || null;
                console.log('🔑 API key loaded from configuration');
            } else {
                this.apiKey = null;
                console.log('⚠️ No API key configuration found');
            }
        } catch (error) {
            this.apiKey = null;
            console.log('⚠️ Could not load API key configuration');
        }
    }

    authenticateRequest(req, res, next) {
        if (!this.apiKey) {
            return next(); // No API key required if not configured
        }

        const authHeader = req.headers.authorization;
        if (!authHeader || !authHeader.startsWith('Bearer ')) {
            return res.status(401).json({ success: false, error: 'Authorization header required' });
        }

        const token = authHeader.substring(7);
        if (token !== this.apiKey) {
            return res.status(403).json({ success: false, error: 'Invalid API key' });
        }

        next();
    }

    loadDatabaseConfig() {
        try {
            const configPath = path.join(__dirname, '../data/pws/pulse_db_config.json');
            const configData = fs.readFileSync(configPath, 'utf8');
            const config = JSON.parse(configData);
            
            return {
                host: config.Server || 'localhost',
                user: config.Username || 'root',
                password: config.Password || '',
                database: config.Database || 'vemite5_pulse-core'
            };
        } catch (error) {
            console.log('⚠️ Could not load database config, using defaults');
            return {
                host: 'localhost',
                user: 'root',
                password: '',
                database: 'vemite5_pulse-core'
            };
        }
    }

    setupServer() {
        this.app.use(express.json());
        
        // CORS headers for web app access
        this.app.use((req, res, next) => {
            res.header('Access-Control-Allow-Origin', '*');
            res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            next();
        });

        // Load API key configuration
        this.loadApiKey();

        // Basic status endpoint (no auth required)
        this.app.get('/status', (req, res) => {
            res.json({
                status: 'online',
                timestamp: new Date().toISOString(),
                uptime: process.uptime(),
                system: {
                    platform: os.platform(),
                    hostname: os.hostname(),
                    memory: Math.round(os.freemem() / 1024 / 1024) + ' MB free'
                }
            });
        });

        // API status endpoint (with auth)
        this.app.get('/api/status', this.authenticateRequest.bind(this), (req, res) => {
            res.json({
                status: 'online',
                timestamp: new Date().toISOString(),
                uptime: process.uptime(),
                authenticated: true,
                system: {
                    platform: os.platform(),
                    hostname: os.hostname(),
                    memory: Math.round(os.freemem() / 1024 / 1024) + ' MB free'
                }
            });
        });

        // Test database connection
        this.app.get('/test-db', async (req, res) => {
            try {
                await this.testDatabaseConnection();
                res.json({ success: true, message: 'Database connected successfully!' });
            } catch (error) {
                res.json({ success: false, error: error.message });
            }
        });

        this.startServer();
    }

    startServer() {
        // Load SSL configuration if available
        const sslConfig = this.loadSSLConfig();
        
        if (sslConfig && sslConfig.enabled) {
            // HTTPS Server
            const httpsServer = https.createServer(sslConfig.options, this.app);
            httpsServer.listen(sslConfig.port, () => {
                console.log(`🚀 PC Bridge running on https://localhost:${sslConfig.port}`);
                console.log(`📊 Status: https://localhost:${sslConfig.port}/status`);
                console.log(`🔗 Test DB: https://localhost:${sslConfig.port}/test-db`);
                console.log(`🔑 API Status: https://localhost:${sslConfig.port}/api/status`);
            });
        } else {
            // HTTP Server (fallback)
            const httpServer = http.createServer(this.app);
            httpServer.listen(this.port, () => {
                console.log(`🚀 PC Bridge running on http://localhost:${this.port}`);
                console.log(`📊 Status: http://localhost:${this.port}/status`);
                console.log(`🔗 Test DB: http://localhost:${this.port}/test-db`);
                console.log(`🔑 API Status: http://localhost:${this.port}/api/status`);
            });
        }
    }

    loadSSLConfig() {
        try {
            const configPath = path.join(__dirname, '../data/pws/ssl_config.json');
            if (!fs.existsSync(configPath)) {
                return null;
            }

            const configData = fs.readFileSync(configPath, 'utf8');
            const config = JSON.parse(configData);
            
            if (!config.enabled) {
                return null;
            }

            // Load SSL certificate files
            const certPath = path.join(__dirname, '../data/pws/certs', config.cert);
            const keyPath = path.join(__dirname, '../data/pws/certs', config.key);
            
            if (!fs.existsSync(certPath) || !fs.existsSync(keyPath)) {
                console.log('⚠️ SSL certificates not found, falling back to HTTP');
                return null;
            }

            const sslOptions = {
                cert: fs.readFileSync(certPath),
                key: fs.readFileSync(keyPath)
            };

            return {
                enabled: true,
                port: config.port || 8443,
                options: sslOptions
            };

        } catch (error) {
            console.log('⚠️ Could not load SSL configuration, using HTTP');
            return null;
        }
    }

    async testDatabaseConnection() {
        const connection = await mysql.createConnection(this.dbConfig);
        await connection.execute('SELECT 1');
        await connection.end();
        console.log('✅ Database connection successful');
        return true;
    }

    async updatePCStatus() {
        try {
            const connection = await mysql.createConnection(this.dbConfig);
            
            const systemInfo = {
                platform: os.platform(),
                hostname: os.hostname(),
                uptime: os.uptime(),
                freemem: os.freemem(),
                totalmem: os.totalmem(),
                cpus: os.cpus().length
            };

            // Insert PC status in database
            await connection.execute(
                `INSERT INTO pc_status (system_info, last_ping) 
                 VALUES (?, NOW())`,
                [JSON.stringify(systemInfo)]
            );

            await connection.end();
            
            if (!this.isConnected) {
                console.log('✅ PC Status updated in database');
                this.isConnected = true;
            }
            
        } catch (error) {
            console.error('❌ Failed to update PC status:', error.message);
            this.isConnected = false;
        }
    }

    startHeartbeat() {
        console.log('💓 Starting heartbeat every 30 seconds...');
        
        // Update immediately
        this.updatePCStatus();
        
        // Then every 30 seconds
        cron.schedule('*/30 * * * * *', () => {
            this.updatePCStatus();
        });
    }
}

// Start the PC Bridge
console.log('🔧 Initializing PC AI Bridge...');
new PCBridge();