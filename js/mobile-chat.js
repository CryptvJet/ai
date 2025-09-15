/**
 * Mobile Chat JavaScript - Zin AI Mobile Interface
 * Integrates with existing AI Chat API system
 */

class MobileChatInterface {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.conversationHistory = [];
        this.isTyping = false;
        this.currentMode = 'chill';
        
        this.init();
        this.checkSystemStatus();
    }

    init() {
        // DOM elements
        this.messageInput = document.getElementById('messageInput');
        this.chatContainer = document.getElementById('chatContainer');
        this.typingIndicator = document.getElementById('typingIndicator');
        this.sendButton = document.getElementById('sendButton');
        this.statusDot = document.getElementById('statusDot');
        this.statusText = document.getElementById('statusText');
        this.welcomeMessage = document.getElementById('welcomeMessage');
        
        // Event listeners
        this.setupEventListeners();
        
        // Check for existing session
        this.loadSession();
    }

    setupEventListeners() {
        // Auto-resize textarea
        this.messageInput.addEventListener('input', () => {
            this.messageInput.style.height = 'auto';
            this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 120) + 'px';
        });

        // Handle Enter key
        this.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Send button
        this.sendButton.addEventListener('click', () => this.sendMessage());
    }

    generateSessionId() {
        return 'mobile_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    loadSession() {
        // Try to load existing session from localStorage
        const savedSession = localStorage.getItem('zin_mobile_session');
        if (savedSession) {
            try {
                const sessionData = JSON.parse(savedSession);
                this.sessionId = sessionData.sessionId || this.sessionId;
                this.conversationHistory = sessionData.history || [];
                this.currentMode = sessionData.mode || 'chill';
                
                // Restore conversation if exists
                if (this.conversationHistory.length > 0) {
                    this.restoreConversation();
                }
            } catch (e) {
                console.warn('Failed to load session data:', e);
            }
        }
    }

    saveSession() {
        const sessionData = {
            sessionId: this.sessionId,
            history: this.conversationHistory.slice(-50), // Keep last 50 messages
            mode: this.currentMode,
            timestamp: Date.now()
        };
        localStorage.setItem('zin_mobile_session', JSON.stringify(sessionData));
    }

    restoreConversation() {
        // Remove welcome message
        if (this.welcomeMessage) {
            this.welcomeMessage.remove();
        }

        // Add messages from history
        this.conversationHistory.forEach(msg => {
            this.addMessageToDOM(msg.content, msg.sender, msg.timestamp);
        });
    }

    async checkSystemStatus() {
        try {
            // Check PulseCore status
            const pulseResponse = await fetch('api/pulsecore-status.php');
            
            // Get content type to detect if PHP is processed
            const contentType = pulseResponse.headers.get('content-type');
            console.log('Status response content-type:', contentType);
            
            // Check if we get a proper JSON response or raw PHP (indicating static server)
            const pulseText = await pulseResponse.text();
            console.log('Status response preview:', pulseText.substring(0, 100));
            
            let pulseData;
            let isStaticServer = false;
            
            // Detect static server by content-type or PHP code
            if (contentType && contentType.includes('application/x-httpd-php') || 
                pulseText.startsWith('<?php') || 
                pulseText.includes('<?php')) {
                // Static server - simulate connection for demo
                console.warn('PHP backend not available - activating demo mode');
                pulseData = { connected: true, demo_mode: true };
                isStaticServer = true;
            } else {
                try {
                    pulseData = JSON.parse(pulseText);
                    console.log('Parsed status response:', pulseData);
                } catch (parseError) {
                    console.warn('Failed to parse status response, using demo mode');
                    pulseData = { connected: true, demo_mode: true };
                    isStaticServer = true;
                }
            }
            
            // For static server, skip PC Bridge check
            let pcData = { connected: true, demo_mode: isStaticServer };
            
            if (!isStaticServer) {
                // Check PC Bridge status only if we have real PHP backend
                try {
                    const pcResponse = await fetch('api/pc-bridge-status.php');
                    const pcText = await pcResponse.text();
                    pcData = JSON.parse(pcText);
                } catch (pcError) {
                    console.warn('PC Bridge check failed:', pcError);
                    pcData = { connected: false };
                }
            }
            
            // Update status indicator
            this.updateStatus(pulseData.connected && pcData.connected, pulseData.demo_mode || isStaticServer);
            
        } catch (error) {
            console.warn('Status check failed:', error);
            // Fallback to demo mode instead of showing "Connecting..."
            this.updateStatus(true, true);
        }
    }

    updateStatus(isConnected, isDemo = false) {
        if (isConnected) {
            this.statusDot.style.background = '#22c55e';
            this.statusText.textContent = isDemo ? 'Demo Mode' : 'Active';
            this.statusText.style.color = '#22c55e';
        } else {
            this.statusDot.style.background = '#ef4444';
            this.statusText.textContent = 'Connecting...';
            this.statusText.style.color = '#ef4444';
        }
    }

    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message || this.isTyping) return;

        // Add user message to DOM
        this.addMessageToDOM(message, 'user');
        this.messageInput.value = '';
        this.messageInput.style.height = 'auto';

        // Add to conversation history
        this.conversationHistory.push({
            content: message,
            sender: 'user',
            timestamp: Date.now()
        });

        // Show typing indicator
        this.showTypingIndicator();

        try {
            // Send to API
            const response = await this.callChatAPI(message);
            
            // Hide typing indicator
            this.hideTypingIndicator();
            
            // Add AI response to DOM
            this.addMessageToDOM(response.response, 'assistant');
            
            // Add to conversation history
            this.conversationHistory.push({
                content: response.response,
                sender: 'assistant',
                timestamp: Date.now()
            });

            // Save session
            this.saveSession();

        } catch (error) {
            console.error('Chat API error:', error);
            this.hideTypingIndicator();
            
            // Show error message
            this.addMessageToDOM(
                "I'm having trouble connecting right now. Please check your connection and try again.",
                'assistant'
            );
        }
    }

    async callChatAPI(message) {
        const requestData = {
            message: message,
            session_id: this.sessionId,
            mode: this.currentMode,
            timestamp: Date.now()
        };

        try {
            console.log('Sending chat request:', requestData);
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            console.log('Chat response status:', response.status);
            console.log('Chat response content-type:', response.headers.get('content-type'));

            if (!response.ok) {
                throw new Error(`API request failed: ${response.status}`);
            }

            const responseText = await response.text();
            console.log('Chat response preview:', responseText.substring(0, 200));
            
            // Get content type to detect if PHP is processed
            const contentType = response.headers.get('content-type');
            
            // Check if we get PHP code back (static server) by content-type or content
            if (contentType && contentType.includes('application/x-httpd-php') ||
                responseText.startsWith('<?php') || 
                responseText.includes('<?php')) {
                console.warn('PHP backend not available - using demo responses');
                return this.generateDemoResponse(message);
            }

            try {
                const jsonResponse = JSON.parse(responseText);
                console.log('Parsed chat response:', jsonResponse);
                return jsonResponse;
            } catch (parseError) {
                console.warn('Failed to parse chat response, using demo mode:', parseError);
                return this.generateDemoResponse(message);
            }
            
        } catch (error) {
            console.warn('API call failed, using demo mode:', error);
            return this.generateDemoResponse(message);
        }
    }

    generateDemoResponse(message) {
        // Generate contextual demo responses
        const lowerMessage = message.toLowerCase();
        
        let response = "I understand you're asking about that. This is a demo response since the PHP backend isn't currently available.";
        
        if (lowerMessage.includes('nova')) {
            response = "Based on your recent nova data, I can see several interesting patterns. Your most recent novas show complexity values ranging from 4,200 to 12,847, with Nova #4789 representing a significant energy surge event. *(Demo Mode)*";
        } else if (lowerMessage.includes('variable') || lowerMessage.includes('harmonic')) {
            response = "Local Harmonic Amplifier is a critical variable in your PulseCore system. It represents a localized field mechanism that amplifies harmonic resonance patterns within specific dimensional boundaries. Currently showing 3.4x baseline values. *(Demo Mode)*";
        } else if (lowerMessage.includes('complexity')) {
            response = "Your complexity patterns show fascinating trends. The current average is 5,792.4 with recent peaks reaching 17,090. I'm detecting strong correlations with your harmonic variables. *(Demo Mode)*";
        } else if (lowerMessage.includes('file') || lowerMessage.includes('organize')) {
            response = "I can help you organize your project files. Based on your current structure, I recommend categorizing by research phases and creating automated backup workflows. *(Demo Mode)*";
        }
        
        return {
            response: response,
            session_id: this.sessionId,
            timestamp: Date.now(),
            demo_mode: true
        };
    }

    sendQuickMessage(message) {
        this.messageInput.value = message;
        this.sendMessage();
    }

    addMessageToDOM(content, sender, timestamp = null) {
        // Remove welcome message if it exists
        if (this.welcomeMessage && this.welcomeMessage.parentNode) {
            this.welcomeMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const displayTime = timestamp ? new Date(timestamp) : new Date();
        const timeString = displayTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        messageDiv.innerHTML = `
            <div class="message-content">
                ${this.formatMessage(content)}
                <div class="message-time">${timeString}</div>
            </div>
        `;
        
        this.chatContainer.appendChild(messageDiv);
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    formatMessage(content) {
        // Basic formatting for common patterns
        return content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    showTypingIndicator() {
        this.isTyping = true;
        this.typingIndicator.style.display = 'flex';
        this.sendButton.disabled = true;
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    hideTypingIndicator() {
        this.isTyping = false;
        this.typingIndicator.style.display = 'none';
        this.sendButton.disabled = false;
    }

    // Admin functions
    clearChat() {
        // Clear conversation history
        this.conversationHistory = [];
        
        // Clear DOM (keep welcome message)
        const messages = this.chatContainer.querySelectorAll('.message');
        messages.forEach(msg => msg.remove());
        
        // Reset welcome message
        this.showWelcomeMessage();
        
        // Clear session
        localStorage.removeItem('zin_mobile_session');
        this.sessionId = this.generateSessionId();
    }

    showWelcomeMessage() {
        if (this.chatContainer.querySelector('.welcome-message')) return;
        
        const welcomeHTML = `
            <div class="welcome-message" id="welcomeMessage">
                <h2>Welcome to Zin AI</h2>
                <p>Your intelligent research partner with persistent memory and PulseCore integration</p>
                
                <div class="quick-actions">
                    <div class="quick-action" onclick="mobileChat.sendQuickMessage('What are my recent novas?')">
                        <span class="icon">🌟</span>
                        Recent Novas
                    </div>
                    <div class="quick-action" onclick="mobileChat.sendQuickMessage('What is local harmonic amplifier?')">
                        <span class="icon">🔬</span>
                        Variables
                    </div>
                    <div class="quick-action" onclick="mobileChat.sendQuickMessage('Show me complexity patterns')">
                        <span class="icon">📊</span>
                        Analysis
                    </div>
                    <div class="quick-action" onclick="mobileChat.sendQuickMessage('Help me organize my project files')">
                        <span class="icon">📁</span>
                        File System
                    </div>
                </div>

                <div class="team-section">
                    <h3>Built Through Collaboration</h3>
                    <p>This project represents genuine collaboration between human vision and AI assistance</p>
                    
                        <div class="team-item">
                            <span class="team-icon">🦙</span>
                            <span>Based on Ollama</span>
                        </div>
                        <div class="team-item">
                            <span class="team-icon">🧠</span>
                            <span>Developed with Claude (Anthropic)</span>
                        </div>
                        <div class="team-item">
                            <span class="team-icon">💫</span>
                            <span>Inspired by Sorya GPT</span>
                        </div>
                        <div class="team-item">
                            <span class="team-icon">🚀</span>
                            <span>Powered by PulseCore</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.chatContainer.innerHTML = welcomeHTML + this.chatContainer.innerHTML;
        this.welcomeMessage = document.getElementById('welcomeMessage');
    }

    switchToDesktop() {
        // Save session before redirect
        this.saveSession();
        
        // Redirect to desktop version
        window.location.href = 'index.html';
    }
}

// Global functions for HTML onclick handlers
function sendQuickMessage(message) {
    if (window.mobileChat) {
        window.mobileChat.sendQuickMessage(message);
    }
}

function sendMessage() {
    if (window.mobileChat) {
        window.mobileChat.sendMessage();
    }
}

// Initialize mobile chat interface when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.mobileChat = new MobileChatInterface();
    
    // Check status periodically
    setInterval(() => {
        window.mobileChat.checkSystemStatus();
    }, 30000); // Every 30 seconds
    
    console.log('Zin AI Mobile Chat Interface initialized');
});