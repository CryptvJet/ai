// PC AI Bridge - Basic Connection
const express = require('express');
const mysql = require('mysql2/promise');
const os = require('os');
const cron = require('node-cron');

class PCBridge {
    constructor() {
        this.app = express();
        this.port = 8080;
        this.dbConfig = {
            host: 'localhost',
            user: 'root',  // Update with your MySQL credentials
            password: '',  // Update with your MySQL password
            database: 'ai_chat_db'
        };
        this.isConnected = false;
        
        this.setupServer();
        this.startHeartbeat();
    }

    setupServer() {
        this.app.use(express.json());
        
        // CORS headers for web app access
        this.app.use((req, res, next) => {
            res.header('Access-Control-Allow-Origin', '*');
            res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            res.header('Access-Control-Allow-Headers', 'Content-Type');
            next();
        });

        // Basic status endpoint
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

        // Test database connection
        this.app.get('/test-db', async (req, res) => {
            try {
                await this.testDatabaseConnection();
                res.json({ success: true, message: 'Database connected successfully!' });
            } catch (error) {
                res.json({ success: false, error: error.message });
            }
        });

        this.app.listen(this.port, () => {
            console.log(`🚀 PC Bridge running on http://localhost:${this.port}`);
            console.log(`📊 Status: http://localhost:${this.port}/status`);
            console.log(`🔗 Test DB: http://localhost:${this.port}/test-db`);
        });
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

            // Insert or update PC status in database
            await connection.execute(
                `INSERT INTO pc_status (status, system_info, last_ping) 
                 VALUES ('online', ?, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 status = 'online', 
                 system_info = ?, 
                 last_ping = NOW()`,
                [JSON.stringify(systemInfo), JSON.stringify(systemInfo)]
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