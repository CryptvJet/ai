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
        this.port = 8081;
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

    // Ollama Proxy Method
    ollamaProxy(req, res) {
        const ollamaHost = '127.0.0.1';
        const ollamaPort = 11434;
        
        // Create proxy request options
        const options = {
            hostname: ollamaHost,
            port: ollamaPort,
            path: req.url,
            method: req.method,
            headers: req.headers
        };

        // Create proxy request to local Ollama
        const proxyRequest = http.request(options, (proxyResponse) => {
            // Set response headers
            res.writeHead(proxyResponse.statusCode, proxyResponse.headers);
            
            // Pipe response data
            proxyResponse.pipe(res);
            
            // Log successful proxy
            console.log(`✅ Ollama proxy: ${req.method} ${req.url} -> ${proxyResponse.statusCode}`);
        });

        // Handle proxy request errors
        proxyRequest.on('error', (error) => {
            console.error(`❌ Ollama proxy error: ${error.message}`);
            res.status(503).send('Ollama service unavailable - Local Ollama server is not responding');
        });

        // Handle request timeout
        proxyRequest.setTimeout(30000, () => {
            console.error('⏰ Ollama proxy timeout');
            res.status(504).send('Ollama request timeout - Request to local Ollama server timed out');
            proxyRequest.destroy();
        });

        // Pipe request body to proxy
        req.pipe(proxyRequest);
    }

    // Test Ollama Connection
    async testOllamaConnection() {
        return new Promise((resolve) => {
            const options = {
                hostname: '127.0.0.1',
                port: 11434,
                path: '/api/version',
                method: 'GET',
                timeout: 5000
            };

            const req = http.request(options, (res) => {
                resolve(res.statusCode === 200);
            });

            req.on('error', () => {
                resolve(false);
            });

            req.on('timeout', () => {
                resolve(false);
                req.destroy();
            });

            req.end();
        });
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

        // Setup JSON body parser for Ollama API calls
        this.app.use(express.json({ limit: '50mb' }));
        this.app.use(express.raw({ type: 'application/octet-stream', limit: '50mb' }));

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

        // Ollama API Proxy Routes
        this.app.use('/api/version', this.authenticateRequest.bind(this), (req, res) => {
            this.ollamaProxy(req, res);
        });

        this.app.use('/api/tags', this.authenticateRequest.bind(this), (req, res) => {
            this.ollamaProxy(req, res);
        });

        // Direct proxy routes for streaming (NO authentication)
        this.app.use('/api/generate', (req, res) => {
            this.ollamaProxy(req, res);
        });

        this.app.use('/api/chat', (req, res) => {
            this.ollamaProxy(req, res);
        });

        // Desktop app compatibility endpoint
        this.app.get('/ai/api/pc-bridge-status.php', this.authenticateRequest.bind(this), async (req, res) => {
            try {
                // Test Ollama connection
                const ollamaAvailable = await this.testOllamaConnection();
                
                res.json({
                    success: true,
                    status: 'online',
                    timestamp: new Date().toISOString(),
                    uptime: process.uptime(),
                    bridge: {
                        connected: true,
                        version: '1.0.0'
                    },
                    ollama: {
                        available: ollamaAvailable,
                        proxy_ready: ollamaAvailable
                    },
                    system: {
                        platform: os.platform(),
                        hostname: os.hostname(),
                        memory: Math.round(os.freemem() / 1024 / 1024) + ' MB free'
                    }
                });
            } catch (error) {
                res.status(500).json({
                    success: false,
                    error: 'Bridge status check failed',
                    message: error.message
                });
            }
        });

        // Connect Bridge endpoint for desktop app
        this.app.post('/api/connect', this.authenticateRequest.bind(this), async (req, res) => {
            try {
                const { type, apiKey } = req.body;
                
                // Validate connection type
                const validTypes = ['HTTP', 'HTTPS'];
                if (!validTypes.includes(type)) {
                    return res.status(400).json({
                        success: false,
                        error: 'Invalid connection type',
                        message: 'Connection type must be HTTP or HTTPS'
                    });
                }

                // Test Ollama connection as part of bridge connection
                const ollamaAvailable = await this.testOllamaConnection();
                
                // Log connection establishment
                console.log(`🔌 Bridge connection established: Type=${type}, Ollama=${ollamaAvailable ? 'Available' : 'Unavailable'}`);
                
                res.json({
                    success: true,
                    message: 'Bridge connected successfully',
                    connectionType: type,
                    timestamp: new Date().toISOString(),
                    bridge: {
                        connected: true,
                        version: '1.0.0'
                    },
                    ollama: {
                        available: ollamaAvailable,
                        proxy_ready: ollamaAvailable
                    }
                });
                
            } catch (error) {
                console.error('❌ Bridge connection failed:', error.message);
                res.status(500).json({
                    success: false,
                    error: 'Bridge connection failed',
                    message: error.message
                });
            }
        });

        this.startServer();
    }

    async startServer() {
        // Load SSL configuration if available
        const sslConfig = await this.loadSSLConfig();
        
        if (sslConfig && sslConfig.enabled) {
            // HTTPS Server
            const httpsServer = https.createServer(sslConfig.options, this.app);
            httpsServer.listen(sslConfig.port, () => {
                console.log(`🚀 PC Bridge running on https://localhost:${sslConfig.port}`);
                console.log(`📊 Status: https://localhost:${sslConfig.port}/status`);
                console.log(`🔗 Test DB: https://localhost:${sslConfig.port}/test-db`);
                console.log(`🔑 API Status: https://localhost:${sslConfig.port}/api/status`);
                console.log(`🤖 Ollama Proxy: https://localhost:${sslConfig.port}/api/*`);
            });
        } else {
            // HTTP Server (fallback)
            const httpServer = http.createServer(this.app);
            httpServer.listen(this.port, () => {
                console.log(`🚀 PC Bridge running on http://localhost:${this.port}`);
                console.log(`📊 Status: http://localhost:${this.port}/status`);
                console.log(`🔗 Test DB: http://localhost:${this.port}/test-db`);
                console.log(`🔑 API Status: http://localhost:${this.port}/api/status`);
                console.log(`🤖 Ollama Proxy: http://localhost:${this.port}/api/*`);
            });
        }
    }

    async loadSSLConfig() {
        try {
            // Load SSL configuration from database
            const connection = await mysql.createConnection(this.dbConfig);
            
            try {
                const [rows] = await connection.execute(
                    'SELECT * FROM ai_ssl_config WHERE id = 1'
                );
                
                if (!rows.length) {
                    console.log('⚠️ No SSL configuration found in database');
                    return null;
                }
                
                const config = rows[0];
                
                if (!config.enabled) {
                    console.log('🔒 SSL disabled in configuration');
                    return null;
                }
                
                if (!config.cert_uploaded || !config.key_uploaded) {
                    console.log('⚠️ SSL enabled but certificates not uploaded, falling back to HTTP');
                    return null;
                }

                // Load SSL certificate files
                const certPath = path.join(__dirname, '../data/pws/certs', config.cert_filename || 'server.crt');
                const keyPath = path.join(__dirname, '../data/pws/certs', config.key_filename || 'server.key');
                
                if (!fs.existsSync(certPath) || !fs.existsSync(keyPath)) {
                    console.log('⚠️ SSL certificate files not found on disk, falling back to HTTP');
                    return null;
                }

                const sslOptions = {
                    cert: fs.readFileSync(certPath),
                    key: fs.readFileSync(keyPath)
                };

                console.log('🔒 SSL configuration loaded from database');
                return {
                    enabled: true,
                    port: config.port || 8443,
                    options: sslOptions
                };

            } finally {
                await connection.end();
            }

        } catch (error) {
            console.log('⚠️ Could not load SSL configuration from database, using HTTP:', error.message);
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