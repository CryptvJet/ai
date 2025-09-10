// Zin AI Bridge - Renderer Process JavaScript
const { ipcRenderer, shell } = require('electron');

class ZinAIBridgeUI {
    constructor() {
        this.startTime = Date.now();
        this.logEntries = [];
        this.isAutoScrollEnabled = true;
        
        this.initializeElements();
        this.bindEvents();
        this.startPeriodicUpdates();
        this.loadSystemInfo();
        
        this.log('🚀 Zin AI Bridge initialized', 'success');
    }

    initializeElements() {
        // Status elements
        this.statusDot = document.getElementById('statusDot');
        this.statusText = document.getElementById('statusText');
        this.dbStatus = document.getElementById('dbStatus');
        this.webStatus = document.getElementById('webStatus');
        this.lastHeartbeat = document.getElementById('lastHeartbeat');
        
        // System info elements
        this.hostname = document.getElementById('hostname');
        this.platform = document.getElementById('platform');
        this.arch = document.getElementById('arch');
        this.cpus = document.getElementById('cpus');
        this.uptime = document.getElementById('uptime');
        this.memory = document.getElementById('memory');
        
        // Activity log
        this.activityLog = document.getElementById('activityLog');
        this.autoScrollLog = document.getElementById('autoScrollLog');
        
        // Footer
        this.appUptime = document.getElementById('appUptime');
        
        // Buttons
        this.testConnectionBtn = document.getElementById('testConnectionBtn');
        this.refreshSystemBtn = document.getElementById('refreshSystemBtn');
        this.clearLogBtn = document.getElementById('clearLogBtn');
        this.openWebAdminBtn = document.getElementById('openWebAdminBtn');
        this.sendTestHeartbeatBtn = document.getElementById('sendTestHeartbeatBtn');
        this.minimizeToTrayBtn = document.getElementById('minimizeToTrayBtn');
        this.viewLogsBtn = document.getElementById('viewLogsBtn');
    }

    bindEvents() {
        // Button events
        this.testConnectionBtn.addEventListener('click', () => this.testConnection());
        this.refreshSystemBtn.addEventListener('click', () => this.loadSystemInfo());
        this.clearLogBtn.addEventListener('click', () => this.clearLog());
        this.openWebAdminBtn.addEventListener('click', () => this.openWebAdmin());
        this.sendTestHeartbeatBtn.addEventListener('click', () => this.sendTestHeartbeat());
        this.minimizeToTrayBtn.addEventListener('click', () => this.minimizeToTray());
        this.viewLogsBtn.addEventListener('click', () => this.viewLogs());
        
        // Auto-scroll checkbox
        this.autoScrollLog.addEventListener('change', (e) => {
            this.isAutoScrollEnabled = e.target.checked;
        });
        
        // IPC events from main process
        ipcRenderer.on('heartbeat-sent', (event, systemInfo) => {
            this.onHeartbeatSent(systemInfo);
        });
        
        // Settings
        const heartbeatInterval = document.getElementById('heartbeatInterval');
        heartbeatInterval.addEventListener('change', (e) => {
            this.log(`⚙️ Heartbeat interval changed to ${e.target.value}s`, 'info');
        });
        
        const startWithWindows = document.getElementById('startWithWindows');
        startWithWindows.addEventListener('change', (e) => {
            this.log(`⚙️ Start with Windows ${e.target.checked ? 'enabled' : 'disabled'}`, 'info');
        });
        
        const minimizeOnStartup = document.getElementById('minimizeOnStartup');
        minimizeOnStartup.addEventListener('change', (e) => {
            this.log(`⚙️ Start minimized ${e.target.checked ? 'enabled' : 'disabled'}`, 'info');
        });
    }

    startPeriodicUpdates() {
        // Update app uptime every second
        setInterval(() => {
            this.updateAppUptime();
        }, 1000);
        
        // Update system info every 30 seconds
        setInterval(() => {
            this.loadSystemInfo();
        }, 30000);
        
        // Check web server status every 60 seconds
        setInterval(() => {
            this.checkWebServerStatus();
        }, 60000);
        
        // Initial checks
        setTimeout(() => {
            this.checkWebServerStatus();
        }, 2000);
    }

    async loadSystemInfo() {
        try {
            this.log('🔍 Loading system information...', 'info');
            const systemInfo = await ipcRenderer.invoke('get-system-info');
            
            this.hostname.textContent = systemInfo.hostname || '-';
            this.platform.textContent = systemInfo.platform || '-';
            this.arch.textContent = systemInfo.arch || '-';
            this.cpus.textContent = systemInfo.cpus || '-';
            this.uptime.textContent = this.formatUptime(systemInfo.uptime || 0);
            this.memory.textContent = this.formatMemory(systemInfo.memory);
            
            // Update connection status
            if (systemInfo.isConnected) {
                this.setConnectionStatus('connected', 'Connected');
                this.dbStatus.textContent = '🟢 Connected';
            } else {
                this.setConnectionStatus('disconnected', 'Disconnected');
                this.dbStatus.textContent = '🔴 Disconnected';
            }
            
            this.log('✅ System information updated', 'success');
        } catch (error) {
            this.log(`❌ Failed to load system info: ${error.message}`, 'error');
        }
    }

    async testConnection() {
        this.log('🔍 Testing connection...', 'info');
        this.testConnectionBtn.disabled = true;
        this.testConnectionBtn.textContent = 'Testing...';
        
        try {
            const isConnected = await ipcRenderer.invoke('test-connection');
            
            if (isConnected) {
                this.setConnectionStatus('connected', 'Connected');
                this.dbStatus.textContent = '🟢 Connected';
                this.log('✅ Connection test successful', 'success');
            } else {
                this.setConnectionStatus('disconnected', 'Connection Failed');
                this.dbStatus.textContent = '🔴 Failed';
                this.log('❌ Connection test failed', 'error');
            }
        } catch (error) {
            this.setConnectionStatus('disconnected', 'Connection Error');
            this.dbStatus.textContent = '🔴 Error';
            this.log(`❌ Connection error: ${error.message}`, 'error');
        }
        
        this.testConnectionBtn.disabled = false;
        this.testConnectionBtn.textContent = 'Test Connection';
    }

    async checkWebServerStatus() {
        try {
            const response = await fetch('http://localhost/claude-code/ai/admin/', {
                method: 'HEAD',
                signal: AbortSignal.timeout(3000)
            });
            
            if (response.ok) {
                this.webStatus.textContent = '🟢 Available';
            } else {
                this.webStatus.textContent = '🟡 Limited';
            }
        } catch (error) {
            this.webStatus.textContent = '🔴 Unavailable';
        }
    }

    onHeartbeatSent(systemInfo) {
        this.lastHeartbeat.textContent = new Date().toLocaleTimeString();
        this.setConnectionStatus('connected', 'Connected');
        this.log(`💓 Heartbeat sent - Memory: ${this.formatMemory(systemInfo.memory)}`, 'success');
    }

    setConnectionStatus(status, text) {
        this.statusText.textContent = text;
        this.statusDot.className = `status-dot ${status}`;
    }

    sendTestHeartbeat() {
        this.log('💓 Sending test heartbeat...', 'info');
        // Trigger a manual heartbeat through the main process
        ipcRenderer.invoke('test-connection');
    }

    openWebAdmin() {
        this.log('🌐 Opening web admin panel...', 'info');
        shell.openExternal('http://localhost/claude-code/ai/admin/');
    }

    minimizeToTray() {
        this.log('📌 Minimizing to system tray...', 'info');
        // The main process handles window hiding
        require('electron').remote?.getCurrentWindow()?.hide();
    }

    viewLogs() {
        this.log('📊 System logs feature coming soon...', 'info');
        // Future: Open system logs viewer
    }

    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const entry = {
            timestamp,
            message,
            type
        };
        
        this.logEntries.push(entry);
        
        // Keep only last 100 entries
        if (this.logEntries.length > 100) {
            this.logEntries.shift();
        }
        
        this.renderLog();
    }

    renderLog() {
        const logHTML = this.logEntries.map(entry => {
            const icon = this.getLogIcon(entry.type);
            return `<div class="log-entry ${entry.type}">[${entry.timestamp}] ${icon} ${entry.message}</div>`;
        }).join('');
        
        this.activityLog.innerHTML = logHTML;
        
        // Auto-scroll if enabled
        if (this.isAutoScrollEnabled) {
            this.activityLog.scrollTop = this.activityLog.scrollHeight;
        }
    }

    getLogIcon(type) {
        switch (type) {
            case 'success': return '✅';
            case 'error': return '❌';
            case 'warning': return '⚠️';
            case 'info': return 'ℹ️';
            default: return '📝';
        }
    }

    clearLog() {
        this.logEntries = [];
        this.activityLog.innerHTML = '';
        this.log('🧹 Activity log cleared', 'info');
    }

    updateAppUptime() {
        const uptime = Math.floor((Date.now() - this.startTime) / 1000);
        this.appUptime.textContent = `Uptime: ${this.formatUptime(uptime)}`;
    }

    formatUptime(seconds) {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (days > 0) {
            return `${days}d ${hours}h ${minutes}m`;
        } else if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        } else {
            return `${secs}s`;
        }
    }

    formatMemory(memory) {
        if (!memory) return '-';
        
        const totalGB = (memory.total / (1024 * 1024 * 1024)).toFixed(1);
        const freeGB = (memory.free / (1024 * 1024 * 1024)).toFixed(1);
        const usedGB = (totalGB - freeGB).toFixed(1);
        const usedPercent = ((usedGB / totalGB) * 100).toFixed(0);
        
        return `${usedGB}GB / ${totalGB}GB (${usedPercent}%)`;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ZinAIBridgeUI();
});

// Global error handler
window.addEventListener('error', (event) => {
    console.error('Unhandled error:', event.error);
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
});