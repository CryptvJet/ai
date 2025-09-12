// PC AI Bridge - Electron Main Process
const { app, BrowserWindow, Tray, Menu, ipcMain, dialog } = require('electron');
const path = require('path');
const mysql = require('mysql2/promise');
const os = require('os');
const cron = require('node-cron');

class PCBridgeApp {
    constructor() {
        this.mainWindow = null;
        this.tray = null;
        this.isConnected = false;
        this.heartbeatJob = null;
        
        this.dbConfig = {
            host: 'localhost',
            user: 'root',  // User will configure this in UI
            password: '',  // User will configure this in UI
            database: 'ai_chat_db'
        };
        
        this.initializeApp();
    }

    async initializeApp() {
        await app.whenReady();
        this.createWindow();
        this.createTray();
        this.setupEventHandlers();
        this.startHeartbeat();
    }

    createWindow() {
        this.mainWindow = new BrowserWindow({
            width: 800,
            height: 600,
            webPreferences: {
                nodeIntegration: true,
                contextIsolation: false,
                enableRemoteModule: true
            },
            icon: path.join(__dirname, 'assets', 'icon.ico'),
            title: 'PC AI Bridge',
            show: false, // Start hidden, will show via tray
            skipTaskbar: false
        });

        this.mainWindow.loadFile('renderer/index.html');

        // Handle window close - minimize to tray instead
        this.mainWindow.on('close', (event) => {
            if (!app.isQuiting) {
                event.preventDefault();
                this.mainWindow.hide();
                this.showTrayNotification('PC AI Bridge is still running in the background');
            }
        });

        // Show window when ready
        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();
        });
    }

    createTray() {
        const iconPath = path.join(__dirname, 'assets', 'tray-icon.png');
        this.tray = new Tray(iconPath);
        
        this.updateTrayMenu();
        
        this.tray.setToolTip('PC AI Bridge');
        
        // Double click tray to show window
        this.tray.on('double-click', () => {
            this.toggleWindow();
        });
    }

    updateTrayMenu() {
        const contextMenu = Menu.buildFromTemplate([
            {
                label: 'PC AI Bridge',
                type: 'normal',
                enabled: false
            },
            { type: 'separator' },
            {
                label: this.isConnected ? '🟢 Connected to Database' : '🔴 Database Disconnected',
                type: 'normal',
                enabled: false
            },
            {
                label: `System: ${os.hostname()}`,
                type: 'normal',
                enabled: false
            },
            { type: 'separator' },
            {
                label: 'Show Window',
                type: 'normal',
                click: () => this.showWindow()
            },
            {
                label: 'Test Connection',
                type: 'normal',
                click: () => this.testConnection()
            },
            { type: 'separator' },
            {
                label: 'Quit',
                type: 'normal',
                click: () => this.quitApp()
            }
        ]);

        this.tray.setContextMenu(contextMenu);
    }

    setupEventHandlers() {
        // IPC handlers for renderer process
        ipcMain.handle('get-system-info', () => {
            return {
                hostname: os.hostname(),
                platform: os.platform(),
                uptime: os.uptime(),
                freemem: Math.round(os.freemem() / 1024 / 1024),
                totalmem: Math.round(os.totalmem() / 1024 / 1024),
                cpus: os.cpus().length
            };
        });

        ipcMain.handle('get-connection-status', () => {
            return this.isConnected;
        });

        ipcMain.handle('update-db-config', async (event, config) => {
            this.dbConfig = { ...this.dbConfig, ...config };
            try {
                await this.testDatabaseConnection();
                return { success: true };
            } catch (error) {
                return { success: false, error: error.message };
            }
        });

        ipcMain.handle('test-connection', async () => {
            try {
                await this.testDatabaseConnection();
                return { success: true, message: 'Database connection successful!' };
            } catch (error) {
                return { success: false, error: error.message };
            }
        });
    }

    async testDatabaseConnection() {
        const connection = await mysql.createConnection(this.dbConfig);
        await connection.execute('SELECT 1');
        await connection.end();
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
                this.isConnected = true;
                this.updateTrayMenu();
                this.showTrayNotification('✅ Connected to AI Web App Database');
                
                // Send to renderer if window is open
                if (this.mainWindow && !this.mainWindow.isDestroyed()) {
                    this.mainWindow.webContents.send('connection-status-changed', true);
                }
            }
            
        } catch (error) {
            if (this.isConnected) {
                this.isConnected = false;
                this.updateTrayMenu();
                this.showTrayNotification('❌ Database Connection Lost');
                
                // Send to renderer if window is open
                if (this.mainWindow && !this.mainWindow.isDestroyed()) {
                    this.mainWindow.webContents.send('connection-status-changed', false);
                }
            }
        }
    }

    startHeartbeat() {
        // Update immediately
        this.updatePCStatus();
        
        // Then every 30 seconds
        this.heartbeatJob = cron.schedule('*/30 * * * * *', () => {
            this.updatePCStatus();
        });
    }

    toggleWindow() {
        if (this.mainWindow.isVisible()) {
            this.mainWindow.hide();
        } else {
            this.showWindow();
        }
    }

    showWindow() {
        this.mainWindow.show();
        this.mainWindow.focus();
    }

    showTrayNotification(message) {
        this.tray.displayBalloon({
            title: 'PC AI Bridge',
            content: message
        });
    }

    async testConnection() {
        try {
            await this.testDatabaseConnection();
            this.showTrayNotification('✅ Database connection successful!');
        } catch (error) {
            this.showTrayNotification(`❌ Database connection failed: ${error.message}`);
        }
    }

    quitApp() {
        app.isQuiting = true;
        if (this.heartbeatJob) {
            this.heartbeatJob.stop();
        }
        app.quit();
    }
}

// Start the application
new PCBridgeApp();