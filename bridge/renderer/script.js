// Zin AI Bridge - Renderer Process JavaScript
console.log('🚀 Zin AI Bridge script loading...');
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
        // Tab system
        this.initializeTabs();
        
        // Status elements
        this.statusDot = document.getElementById('statusDot');
        this.statusText = document.getElementById('statusText');
        this.dbStatus = document.getElementById('dbStatus');
        this.webStatus = document.getElementById('webStatus');
        this.lastHeartbeat = document.getElementById('lastHeartbeat');
        
        // Status details (for dashboard tab)
        this.dbStatusDetail = document.getElementById('dbStatusDetail');
        this.webStatusDetail = document.getElementById('webStatusDetail');
        this.lastHeartbeatDetail = document.getElementById('lastHeartbeatDetail');
        
        // System info elements
        this.hostname = document.getElementById('hostname');
        this.platform = document.getElementById('platform');
        this.arch = document.getElementById('arch');
        this.cpus = document.getElementById('cpus');
        this.uptime = document.getElementById('uptime');
        this.memory = document.getElementById('memory');
        this.gpuModel = document.getElementById('gpuModel');
        this.gpuVram = document.getElementById('gpuVram');
        this.gpuUsage = document.getElementById('gpuUsage');
        this.gpuMemoryUsage = document.getElementById('gpuMemoryUsage');
        this.cpuLoad = document.getElementById('cpuLoad');
        
        // Overview elements (for dashboard)
        this.hostnameOverview = document.getElementById('hostnameOverview');
        this.uptimeOverview = document.getElementById('uptimeOverview');
        this.memoryOverview = document.getElementById('memoryOverview');
        this.cpusOverview = document.getElementById('cpusOverview');
        this.gpuOverview = document.getElementById('gpuOverview');
        this.gpuUsageOverview = document.getElementById('gpuUsageOverview');
        
        // Activity log
        this.activityLog = document.getElementById('activityLog');
        this.autoScrollLog = document.getElementById('autoScrollLog');
        
        // Live conversations
        this.conversationLog = document.getElementById('liveConversations');
        this.chatMonitoring = document.getElementById('chatMonitoring');
        
        this.lastMessageId = 0;
        this.conversationMonitor = null;
        
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
        
        
        // Log element counts for debugging
        this.log(`🔧 Elements found: hostname=${!!this.hostname}, platform=${!!this.platform}, memory=${!!this.memory}, gpuModel=${!!this.gpuModel}`, 'info');
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
        
        // Terminal events
        this.executeCommandBtn.addEventListener('click', () => this.executeCommand());
        this.clearTerminalBtn.addEventListener('click', () => this.clearTerminal());
        this.copyTerminalBtn.addEventListener('click', () => this.copyTerminalOutput());
        this.searchTerminalBtn.addEventListener('click', () => this.toggleSearch());
        this.newTabBtn.addEventListener('click', () => this.createNewTerminal());
        
        // Terminal customization
        this.terminalTheme.addEventListener('change', (e) => this.changeTheme(e.target.value));
        this.terminalFontSize.addEventListener('input', (e) => this.changeFontSize(e.target.value));
        
        // Terminal input events
        this.terminalInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                if (this.autocompleteVisible) {
                    this.selectAutocompletion();
                } else {
                    this.executeCommand();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (this.autocompleteVisible) {
                    this.navigateAutocomplete(-1);
                } else {
                    this.navigateHistory(-1);
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (this.autocompleteVisible) {
                    this.navigateAutocomplete(1);
                } else {
                    this.navigateHistory(1);
                }
            } else if (e.key === 'Tab') {
                e.preventDefault();
                this.showAutocomplete();
            } else if (e.key === 'Escape') {
                this.hideAutocomplete();
            } else if (e.key === 'F3' || (e.ctrlKey && e.key === 'f')) {
                e.preventDefault();
                this.toggleSearch();
            }
        });
        
        this.terminalInput.addEventListener('input', (e) => {
            this.updateAutocomplete();
        });
        
        // Search events
        this.searchInput.addEventListener('input', (e) => {
            this.performSearch(e.target.value);
        });
        
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.toggleSearch();
            }
        });
        
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
        
        // Start conversation monitoring
        this.startConversationMonitoring();
    }

    async loadSystemInfo() {
        try {
            this.log('🔍 Loading system information...', 'info');
            const systemInfo = await ipcRenderer.invoke('get-system-info');
            
            // Update system info elements (check if they exist)
            if (this.hostname) this.hostname.textContent = systemInfo.hostname || '-';
            if (this.platform) this.platform.textContent = systemInfo.platform || '-';
            if (this.arch) this.arch.textContent = systemInfo.arch || '-';
            if (this.cpus) this.cpus.textContent = systemInfo.cpus || '-';
            if (this.uptime) this.uptime.textContent = this.formatUptime(systemInfo.uptime || 0);
            if (this.memory) this.memory.textContent = this.formatMemory(systemInfo.memory);
            
            // Update GPU info
            this.updateGpuInfo(systemInfo);
            if (this.cpuLoad) this.cpuLoad.textContent = systemInfo.cpuLoad ? `${systemInfo.cpuLoad.toFixed(1)}%` : '-';
            
            // Update overview elements
            this.updateSystemOverview(systemInfo);
            
            // Update connection status
            if (systemInfo.isConnected) {
                this.setConnectionStatus('connected', 'Connected');
                if (this.dbStatus) this.dbStatus.textContent = '🟢 Connected';
            } else {
                this.setConnectionStatus('disconnected', 'Disconnected');
                if (this.dbStatus) this.dbStatus.textContent = '🔴 Disconnected';
            }
            
            // Update all status displays
            this.updateAllStatusElements();
            
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
            const response = await fetch('https://pulsecore.one/ai/api/pc-bridge-status.php', {
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

    startConversationMonitoring() {
        if (this.chatMonitoring && this.chatMonitoring.checked) {
            this.conversationMonitor = setInterval(() => {
                this.checkForNewConversations();
            }, 3000); // Check every 3 seconds
            
            this.log('💬 Started conversation monitoring', 'info');
        }
        
        // Handle toggle
        if (this.chatMonitoring) {
            this.chatMonitoring.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startConversationMonitoring();
                } else {
                    this.stopConversationMonitoring();
                }
            });
        }
    }
    
    stopConversationMonitoring() {
        if (this.conversationMonitor) {
            clearInterval(this.conversationMonitor);
            this.conversationMonitor = null;
            this.log('💬 Stopped conversation monitoring', 'info');
        }
    }
    
    async checkForNewConversations() {
        try {
            const result = await ipcRenderer.invoke('get-recent-conversations', this.lastMessageId);
            
            if (result.success && result.messages.length > 0) {
                result.messages.forEach(message => {
                    this.displayConversationMessage(message);
                    if (message.id > this.lastMessageId) {
                        this.lastMessageId = message.id;
                    }
                });
            }
        } catch (error) {
            console.error('Error checking conversations:', error);
        }
    }
    
    displayConversationMessage(message) {
        const messageDiv = document.createElement('div');
        const messageClass = message.isPCQuery ? 'pc-query' : message.sender;
        messageDiv.className = `chat-message ${messageClass}`;
        
        const timestamp = new Date(message.timestamp).toLocaleTimeString();
        const userInfo = message.user_id ? ` (${message.user_id})` : '';
        const processingTime = message.processing_time ? ` - ${message.processing_time}ms` : '';
        
        messageDiv.innerHTML = `
            <div class="chat-timestamp">${timestamp} - ${message.sender}${userInfo}${processingTime}</div>
            <div class="chat-content">${this.escapeHtml(message.text)}</div>
        `;
        
        if (this.conversationLog) {
            this.conversationLog.appendChild(messageDiv);
            this.conversationLog.scrollTop = this.conversationLog.scrollHeight;
            
            // Keep only last 50 messages
            const messages = this.conversationLog.querySelectorAll('.chat-message');
            if (messages.length > 50) {
                messages[0].remove();
            }
        }
        
        // Special logging for PC queries
        if (message.isPCQuery) {
            this.log(`🎯 PC Query detected: ${message.text.substring(0, 50)}...`, 'success');
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    initializeTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.getAttribute('data-tab');
                
                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                const targetContent = document.getElementById(targetTab);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
                
                // Log tab change
                this.log(`📑 Switched to ${button.querySelector('.tab-text').textContent} tab`, 'info');
            });
        });
    }

    updateAllStatusElements() {
        // Update both header and dashboard status elements
        if (this.dbStatus && this.dbStatusDetail) {
            this.dbStatusDetail.textContent = this.dbStatus.textContent;
        }
        if (this.webStatus && this.webStatusDetail) {
            this.webStatusDetail.textContent = this.webStatus.textContent;
        }
        if (this.lastHeartbeat && this.lastHeartbeatDetail) {
            this.lastHeartbeatDetail.textContent = this.lastHeartbeat.textContent;
        }
    }

    updateGpuInfo(systemInfo) {
        this.log(`🎮 GPU data received: ${JSON.stringify(systemInfo.gpu)}`, 'info');
        
        if (systemInfo.gpu && systemInfo.gpu.controllers && systemInfo.gpu.controllers.length > 0) {
            const primaryGpu = systemInfo.gpu.controllers[0];
            this.log(`🎮 Primary GPU: ${primaryGpu.vendor} ${primaryGpu.model}`, 'info');
            
            if (this.gpuModel) {
                this.gpuModel.textContent = `${primaryGpu.vendor || ''} ${primaryGpu.model || 'Unknown GPU'}`.trim();
            }
            
            if (this.gpuVram) {
                this.gpuVram.textContent = primaryGpu.vram ? `${Math.round(primaryGpu.vram / 1024)} GB` : 'N/A';
            }
            
            if (this.gpuUsage) {
                this.gpuUsage.textContent = primaryGpu.utilizationGpu ? `${primaryGpu.utilizationGpu}%` : 'N/A';
            }
            
            if (this.gpuMemoryUsage) {
                this.gpuMemoryUsage.textContent = primaryGpu.utilizationMemory ? `${primaryGpu.utilizationMemory}%` : 'N/A';
            }
            
            // Update overview
            if (this.gpuOverview) {
                this.gpuOverview.textContent = `${primaryGpu.vendor || ''} ${primaryGpu.model || 'GPU'}`.trim().substring(0, 20);
            }
            
            if (this.gpuUsageOverview) {
                this.gpuUsageOverview.textContent = primaryGpu.utilizationGpu ? `${primaryGpu.utilizationGpu}%` : 'N/A';
            }
        } else {
            // No GPU detected
            if (this.gpuModel) this.gpuModel.textContent = 'No GPU detected';
            if (this.gpuVram) this.gpuVram.textContent = 'N/A';
            if (this.gpuUsage) this.gpuUsage.textContent = 'N/A';
            if (this.gpuMemoryUsage) this.gpuMemoryUsage.textContent = 'N/A';
            if (this.gpuOverview) this.gpuOverview.textContent = 'No GPU';
            if (this.gpuUsageOverview) this.gpuUsageOverview.textContent = 'N/A';
        }
    }

    updateSystemOverview(systemInfo) {
        if (this.hostnameOverview && systemInfo.hostname) {
            this.hostnameOverview.textContent = systemInfo.hostname;
        }
        if (this.uptimeOverview && systemInfo.uptime) {
            this.uptimeOverview.textContent = this.formatUptime(Math.floor(systemInfo.uptime));
        }
        if (this.memoryOverview && systemInfo.memory) {
            this.memoryOverview.textContent = this.formatMemory(systemInfo.memory);
        }
        if (this.cpusOverview && systemInfo.cpus) {
            this.cpusOverview.textContent = `${systemInfo.cpus} cores`;
        }
    }

    // Terminal Methods
    async executeCommand() {
        let command = this.terminalInput.value.trim();
        if (!command) return;
        
        // Add original command to history
        this.commandHistory.push(command);
        this.historyIndex = -1;
        
        // Translate Unix/Linux commands to Windows equivalents
        command = this.translateCommand(command);
        
        // Clear input
        this.terminalInput.value = '';
        
        // Show command in output
        this.appendToTerminal(`${this.currentWorkingDirectory}> ${command}`, 'terminal-command');
        
        // Update status
        this.terminalStatus.textContent = 'Executing...';
        this.terminalStatus.style.color = '#f59e0b';
        this.executeCommandBtn.disabled = true;
        
        try {
            const result = await ipcRenderer.invoke('execute-command', command, this.currentWorkingDirectory);
            
            if (result.success) {
                if (result.output.trim()) {
                    this.appendToTerminal(result.output, 'terminal-result');
                }
                
                // Update working directory if command was cd
                if (result.workingDirectory) {
                    this.currentWorkingDirectory = result.workingDirectory;
                    this.terminalPrompt.textContent = `${this.currentWorkingDirectory}>`;
                }
                
                this.terminalStatus.textContent = 'Ready';
                this.terminalStatus.style.color = '#10b981';
            } else {
                this.appendToTerminal(result.error || 'Command failed', 'terminal-error');
                this.terminalStatus.textContent = 'Error';
                this.terminalStatus.style.color = '#ef4444';
                
                setTimeout(() => {
                    this.terminalStatus.textContent = 'Ready';
                    this.terminalStatus.style.color = '#10b981';
                }, 3000);
            }
        } catch (error) {
            this.appendToTerminal(`Error: ${error.message}`, 'terminal-error');
            this.terminalStatus.textContent = 'Error';
            this.terminalStatus.style.color = '#ef4444';
            
            setTimeout(() => {
                this.terminalStatus.textContent = 'Ready';
                this.terminalStatus.style.color = '#10b981';
            }, 3000);
        }
        
        this.executeCommandBtn.disabled = false;
        this.terminalInput.focus();
    }
    
    appendToTerminal(text, className = '') {
        const element = document.createElement('div');
        element.className = `terminal-line ${className}`;
        
        // Create content with line number and text in one go
        const lineNumberText = this.lineNumber++;
        const formattedText = this.applySyntaxHighlighting(text);
        
        element.innerHTML = `<span class="terminal-line-number">${lineNumberText}</span><span>${formattedText}</span>`;
        
        this.terminalOutput.appendChild(element);
        this.smoothScrollToBottom();
    }
    
    applySyntaxHighlighting(text) {
        // Basic syntax highlighting for common patterns
        return text
            .replace(/^(\w+)(\s|$)/g, '<span class="syntax-command">$1</span>$2')
            .replace(/(\/\w+|--?\w+)/g, '<span class="syntax-flag">$1</span>')
            .replace(/"([^"]+)"/g, '<span class="syntax-string">"$1"</span>')
            .replace(/([A-Z]:\\[\w\\.-]*)/g, '<span class="syntax-path">$1</span>')
            .replace(/\b(\d+)\b/g, '<span class="syntax-number">$1</span>')
            .replace(/(>|<|\|)/g, '<span class="syntax-operator">$1</span>');
    }
    
    translateCommand(command) {
        // Command aliases for Unix/Linux commands to Windows equivalents
        const aliases = {
            'ls': 'dir',
            'ls -la': 'dir',
            'ls -l': 'dir',
            'ls -a': 'dir /a',
            'cat': 'type',
            'clear': 'cls',
            'pwd': 'cd',
            'nano': 'notepad',
            'vim': 'notepad',
            'vi': 'notepad',
            'grep': 'findstr',
            'which': 'where',
            'man': 'help',
            'ps': 'tasklist',
            'kill': 'taskkill',
            'rm': 'del',
            'mv': 'move',
            'cp': 'copy',
            'mkdir': 'md',
            'rmdir': 'rd',
            'touch': 'echo.>'
        };
        
        // Check for exact matches first
        if (aliases[command]) {
            return aliases[command];
        }
        
        // Check for command with arguments
        const parts = command.split(' ');
        const baseCommand = parts[0];
        
        if (aliases[baseCommand]) {
            // Replace the base command but keep arguments
            parts[0] = aliases[baseCommand];
            return parts.join(' ');
        }
        
        return command;
    }
    
    smoothScrollToBottom() {
        // Force the output container to scroll to bottom
        if (this.terminalOutput) {
            requestAnimationFrame(() => {
                this.terminalOutput.scrollTop = this.terminalOutput.scrollHeight - this.terminalOutput.clientHeight;
            });
        }
    }
    
    clearTerminal() {
        this.terminalOutput.innerHTML = '';
        this.appendToTerminal('Terminal cleared.', 'terminal-success');
    }
    
    async copyTerminalOutput() {
        try {
            const text = this.terminalOutput.textContent;
            await navigator.clipboard.writeText(text);
            this.log('📋 Terminal output copied to clipboard', 'success');
        } catch (error) {
            this.log(`❌ Failed to copy terminal output: ${error.message}`, 'error');
        }
    }
    
    navigateHistory(direction) {
        if (this.commandHistory.length === 0) return;
        
        if (direction === -1) {
            // Up arrow - previous command
            if (this.historyIndex === -1) {
                this.historyIndex = this.commandHistory.length - 1;
            } else if (this.historyIndex > 0) {
                this.historyIndex--;
            }
        } else {
            // Down arrow - next command
            if (this.historyIndex < this.commandHistory.length - 1) {
                this.historyIndex++;
            } else {
                this.historyIndex = -1;
                this.terminalInput.value = '';
                return;
            }
        }
        
        if (this.historyIndex >= 0 && this.historyIndex < this.commandHistory.length) {
            this.terminalInput.value = this.commandHistory[this.historyIndex];
        }
    }
    
    // Advanced Terminal Features
    showAutocomplete() {
        const input = this.terminalInput.value.toLowerCase();
        if (!input) return;
        
        const matches = this.commonCommands.filter(cmd => cmd.startsWith(input));
        if (matches.length === 0) {
            this.hideAutocomplete();
            return;
        }
        
        this.terminalAutocomplete.innerHTML = '';
        matches.forEach((match, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            if (index === 0) item.classList.add('selected');
            item.textContent = match;
            item.addEventListener('click', () => {
                this.terminalInput.value = match + ' ';
                this.hideAutocomplete();
                this.terminalInput.focus();
            });
            this.terminalAutocomplete.appendChild(item);
        });
        
        this.terminalAutocomplete.style.display = 'block';
        this.autocompleteVisible = true;
        this.selectedAutocompleteIndex = 0;
    }
    
    updateAutocomplete() {
        if (this.autocompleteVisible) {
            this.showAutocomplete();
        }
    }
    
    hideAutocomplete() {
        this.terminalAutocomplete.style.display = 'none';
        this.autocompleteVisible = false;
        this.selectedAutocompleteIndex = -1;
    }
    
    navigateAutocomplete(direction) {
        const items = this.terminalAutocomplete.querySelectorAll('.autocomplete-item');
        if (items.length === 0) return;
        
        items[this.selectedAutocompleteIndex].classList.remove('selected');
        
        if (direction === -1) {
            this.selectedAutocompleteIndex = this.selectedAutocompleteIndex > 0 ? 
                this.selectedAutocompleteIndex - 1 : items.length - 1;
        } else {
            this.selectedAutocompleteIndex = this.selectedAutocompleteIndex < items.length - 1 ? 
                this.selectedAutocompleteIndex + 1 : 0;
        }
        
        items[this.selectedAutocompleteIndex].classList.add('selected');
    }
    
    selectAutocompletion() {
        const selectedItem = this.terminalAutocomplete.querySelector('.autocomplete-item.selected');
        if (selectedItem) {
            this.terminalInput.value = selectedItem.textContent + ' ';
            this.hideAutocomplete();
            this.terminalInput.focus();
        }
    }
    
    toggleSearch() {
        if (this.terminalSearch.style.display === 'none' || !this.terminalSearch.style.display) {
            this.terminalSearch.style.display = 'block';
            this.searchInput.focus();
        } else {
            this.terminalSearch.style.display = 'none';
            this.clearSearchResults();
        }
    }
    
    performSearch(query) {
        if (!query) {
            this.clearSearchResults();
            return;
        }
        
        const lines = this.terminalOutput.querySelectorAll('.terminal-line');
        let matches = 0;
        
        lines.forEach(line => {
            const text = line.textContent.toLowerCase();
            const queryLower = query.toLowerCase();
            
            if (text.includes(queryLower)) {
                line.style.backgroundColor = 'rgba(255, 255, 0, 0.2)';
                matches++;
            } else {
                line.style.backgroundColor = '';
            }
        });
        
        const searchResults = document.getElementById('searchResults');
        searchResults.textContent = `${matches} matches found`;
    }
    
    clearSearchResults() {
        const lines = this.terminalOutput.querySelectorAll('.terminal-line');
        lines.forEach(line => {
            line.style.backgroundColor = '';
        });
        const searchResults = document.getElementById('searchResults');
        searchResults.textContent = '';
    }
    
    createNewTerminal() {
        const newId = this.terminals.length;
        const newTerminal = {
            id: newId,
            name: `Terminal ${newId + 1}`,
            output: '',
            workingDir: 'C:\\',
            history: []
        };
        
        this.terminals.push(newTerminal);
        
        // Add tab
        const tabElement = document.createElement('div');
        tabElement.className = 'terminal-tab';
        tabElement.setAttribute('data-tab', newId);
        tabElement.innerHTML = `
            ${newTerminal.name}
            <span class="close-tab" data-tab="${newId}">×</span>
        `;
        
        tabElement.addEventListener('click', (e) => {
            if (!e.target.classList.contains('close-tab')) {
                this.switchTerminal(newId);
            }
        });
        
        tabElement.querySelector('.close-tab').addEventListener('click', (e) => {
            e.stopPropagation();
            this.closeTerminal(newId);
        });
        
        this.terminalTabs.appendChild(tabElement);
        this.switchTerminal(newId);
    }
    
    switchTerminal(id) {
        // Save current terminal state
        this.terminals[this.activeTerminal].output = this.terminalOutput.innerHTML;
        this.terminals[this.activeTerminal].workingDir = this.currentWorkingDirectory;
        
        // Switch to new terminal
        this.activeTerminal = id;
        this.currentWorkingDirectory = this.terminals[id].workingDir;
        this.terminalOutput.innerHTML = this.terminals[id].output;
        this.terminalPrompt.textContent = `${this.currentWorkingDirectory}>`;
        
        // Update tab UI
        document.querySelectorAll('.terminal-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelector(`[data-tab="${id}"]`).classList.add('active');
        
        this.smoothScrollToBottom();
    }
    
    closeTerminal(id) {
        if (this.terminals.length <= 1) return; // Don't close last terminal
        
        this.terminals = this.terminals.filter(t => t.id !== id);
        document.querySelector(`[data-tab="${id}"]`).remove();
        
        if (this.activeTerminal === id) {
            this.switchTerminal(this.terminals[0].id);
        }
    }
    
    changeTheme(theme) {
        const container = document.querySelector('.terminal-container');
        container.className = container.className.replace(/terminal-theme-\w+/g, '');
        container.classList.add(`terminal-theme-${theme}`);
        
        this.log(`🎨 Terminal theme changed to ${theme}`, 'info');
    }
    
    changeFontSize(size) {
        this.terminalOutput.style.fontSize = `${size}px`;
        this.terminalInput.style.fontSize = `${size}px`;
        
        this.log(`📏 Terminal font size changed to ${size}px`, 'info');
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