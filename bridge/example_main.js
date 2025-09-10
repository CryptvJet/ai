// Zin AI Bridge - Electron Main Process
// EXAMPLE FILE - Copy to main.js and configure your database credentials
const { app, BrowserWindow, Tray, Menu, ipcMain, shell } = require('electron');
const path = require('path');
const mysql = require('mysql2/promise');
const os = require('os');
const cron = require('node-cron');

class ZinAIBridge {
    constructor() {
        this.mainWindow = null;
        this.tray = null;
        this.dbConnection = null;
        this.heartbeatJob = null;
        this.isConnected = false;
        
        // Database config - CONFIGURE THESE VALUES
        this.dbConfig = {
            host: 'your-domain.com',      // Your MySQL server host
            user: 'your_mysql_user',      // Your MySQL username
            password: 'your_mysql_pass',  // Your MySQL password
            database: 'your_database'     // Your database name
        };
    }

    async initialize() {
        this.setupEventHandlers(); // Setup IPC handlers FIRST
        await this.setupDatabase();
        this.createWindow();
        this.createTray();
        this.startHeartbeat();
    }

    createWindow() {
        this.mainWindow = new BrowserWindow({
            width: 800,
            height: 600,
            icon: path.join(__dirname, 'assets', 'icon.png'),
            webPreferences: {
                nodeIntegration: true,
                contextIsolation: false
            },
            show: false,
            resizable: true,
            minimizable: true,
            maximizable: true
        });

        this.mainWindow.loadFile('renderer/index.html');
        
        // Show window when ready
        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();
        });

        // Hide to tray instead of close
        this.mainWindow.on('close', (event) => {
            if (!app.isQuiting) {
                event.preventDefault();
                this.mainWindow.hide();
            }
        });
    }

    createTray() {
        try {
            this.tray = new Tray(path.join(__dirname, 'assets', 'tray-icon.png'));
        } catch (error) {
            console.log('⚠️ Tray icon not found, using default');
            // Create a simple default tray without icon for now
            this.createTrayMenu();
            return;
        }
        
        const contextMenu = Menu.buildFromTemplate([
            {
                label: 'Show Zin AI Bridge',
                click: () => {
                    this.mainWindow.show();
                }
            },
            {
                label: 'Connection Status',
                enabled: false
            },
            {
                label: this.isConnected ? '🟢 Connected' : '🔴 Disconnected',
                enabled: false
            },
            { type: 'separator' },
            {
                label: 'Open Web Admin',
                click: () => {
                    shell.openExternal('https://your-domain.com/ai/admin/');
                }
            },
            { type: 'separator' },
            {
                label: 'Quit',
                click: () => {
                    app.isQuiting = true;
                    app.quit();
                }
            }
        ]);

        this.tray.setContextMenu(contextMenu);
        this.tray.setToolTip('Zin AI Bridge - PC Connection');
        
        this.tray.on('double-click', () => {
            this.mainWindow.show();
        });
    }

    createTrayMenu() {
        // For when tray icon is not available
        console.log('🚀 Bridge running without system tray icon');
    }

    async setupDatabase() {
        try {
            this.dbConnection = await mysql.createConnection(this.dbConfig);
            console.log('✅ Database connected');
            this.isConnected = true;
        } catch (error) {
            console.error('❌ Database connection failed:', error.message);
            this.isConnected = false;
        }
    }

    startHeartbeat() {
        // Send heartbeat every 30 seconds
        this.heartbeatJob = cron.schedule('*/30 * * * * *', async () => {
            await this.sendHeartbeat();
        });
    }

    async sendHeartbeat() {
        if (!this.dbConnection) {
            await this.setupDatabase();
            return;
        }

        try {
            const systemInfo = {
                hostname: os.hostname(),
                platform: os.platform(),
                arch: os.arch(),
                uptime: os.uptime(),
                memory: {
                    total: os.totalmem(),
                    free: os.freemem()
                },
                cpus: os.cpus().length,
                loadavg: os.loadavg(),
                timestamp: new Date().toISOString()
            };

            await this.dbConnection.execute(
                'INSERT INTO pc_status (system_info, last_ping) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE system_info = ?, last_ping = NOW()',
                [JSON.stringify(systemInfo), JSON.stringify(systemInfo)]
            );

            this.isConnected = true;
            this.updateTrayStatus();
            
            // Send to renderer if window is open
            if (this.mainWindow && !this.mainWindow.isDestroyed()) {
                this.mainWindow.webContents.send('heartbeat-sent', systemInfo);
            }

        } catch (error) {
            console.error('❌ Heartbeat failed:', error.message);
            this.isConnected = false;
            this.updateTrayStatus();
        }
    }

    updateTrayStatus() {
        if (this.tray) {
            const contextMenu = Menu.buildFromTemplate([
                {
                    label: 'Show Zin AI Bridge',
                    click: () => {
                        this.mainWindow.show();
                    }
                },
                {
                    label: 'Connection Status',
                    enabled: false
                },
                {
                    label: this.isConnected ? '🟢 Connected' : '🔴 Disconnected',
                    enabled: false
                },
                { type: 'separator' },
                {
                    label: 'Open Web Admin',
                    click: () => {
                        shell.openExternal('https://your-domain.com/ai/admin/');
                    }
                },
                { type: 'separator' },
                {
                    label: 'Quit',
                    click: () => {
                        app.isQuiting = true;
                        app.quit();
                    }
                }
            ]);
            this.tray.setContextMenu(contextMenu);
        }
    }

    setupEventHandlers() {
        // IPC handlers for renderer communication
        ipcMain.handle('get-system-info', () => {
            return {
                hostname: os.hostname(),
                platform: os.platform(),
                arch: os.arch(),
                uptime: os.uptime(),
                memory: {
                    total: os.totalmem(),
                    free: os.freemem()
                },
                cpus: os.cpus().length,
                isConnected: this.isConnected
            };
        });

        ipcMain.handle('test-connection', async () => {
            await this.sendHeartbeat();
            return this.isConnected;
        });
    }
}

// App event handlers
app.whenReady().then(async () => {
    const bridge = new ZinAIBridge();
    await bridge.initialize();
});

app.on('window-all-closed', () => {
    // Don't quit on window close, keep running in tray
});

app.on('activate', () => {
    // On macOS, re-create window when dock icon is clicked
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    }
});