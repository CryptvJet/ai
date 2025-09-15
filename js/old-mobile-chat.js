// Mobile Chat Interface JavaScript - Identical to Desktop
class AIChat {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.currentMode = 'chill';
        
        this.initializeChat();
        this.checkConnections();
    }

    generateSessionId() {
        return 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    initializeChat() {
        // Set up event listeners - EXACTLY like desktop
        document.getElementById('messageInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Set up send button
        document.getElementById('sendButton').addEventListener('click', () => {
            this.sendMessage();
        });

        // Auto-resize input
        const messageInput = document.getElementById('messageInput');
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = messageInput.scrollHeight + 'px';
        });
    }

    async checkConnections() {
        // Check PulseCore connection - EXACTLY like desktop
        try {
            const response = await fetch('api/pulsecore-status.php');
            const status = await response.json();
            console.log('PulseCore status response:', status);
            const isConnected = status.success && status.status === 'connected';
            console.log('PulseCore connection result:', isConnected);
            this.updateConnectionStatus('pulseStatus', isConnected);
        } catch (error) {
            console.error('PulseCore status check failed:', error);
            this.updateConnectionStatus('pulseStatus', false);
        }

        // Check PC Bridge connection
        try {
            const response = await fetch('api/pc-status.php');
            const status = await response.json();
            console.log('PC Bridge status response:', status);
            
            if (status.success && status.data.is_online) {
                this.updateConnectionStatus('pcStatus', true);
                console.log('✅ PC Bridge is online');
            } else {
                throw new Error('PC Bridge not responding');
            }
        } catch (error) {
            // PC Bridge not running - this is normal for web-only usage
            console.log('PC Bridge offline (normal for web-only usage):', error.message);
            this.updateConnectionStatus('pcStatus', false);
        }
    }

    updateConnectionStatus(statusId, isConnected) {
        // Update mobile status indicators
        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        
        if (statusDot && statusText) {
            if (isConnected) {
                statusDot.style.background = '#22c55e';
                statusText.textContent = 'Active';
                statusText.style.color = '#22c55e';
            } else {
                statusDot.style.background = '#ef4444';
                statusText.textContent = 'Connecting...';
                statusText.style.color = '#ef4444';
            }
        }
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;

        // Clear input and disable send button temporarily - EXACTLY like desktop
        input.value = '';
        input.style.height = 'auto';
        
        const sendBtn = document.getElementById('sendButton');
        sendBtn.disabled = true;

        // Add user message to chat - EXACTLY like desktop
        this.addMessage('user', message);

        try {
            // Send message to AI - EXACTLY like desktop
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: this.sessionId,
                    mode: this.currentMode
                })
            });

            const result = await response.json();

            if (result.success) {
                this.addMessage('ai', result.response);
            } else {
                this.addMessage('ai', 'Sorry, I encountered an error processing your message. Please try again.');
                console.error('Chat error:', result.error);
            }
        } catch (error) {
            console.error('Network error:', error);
            this.addMessage('ai', 'Sorry, I\'m having trouble connecting right now. Please check your connection and try again.');
        } finally {
            sendBtn.disabled = false;
            // Ensure input focus is maintained for continuous typing
            input.focus();
        }
    }

    addMessage(type, content) {
        // Remove welcome message if it exists
        const welcomeMessage = document.getElementById('welcomeMessage');
        if (welcomeMessage) {
            welcomeMessage.remove();
        }

        const chatContainer = document.getElementById('chatContainer');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;
        
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        messageDiv.innerHTML = `
            <div class="message-content">
                <strong>${type === 'user' ? 'You' : 'Zin AI'}:</strong> ${content}
            </div>
            <div class="message-time">${timestamp}</div>
        `;
        
        chatContainer.appendChild(messageDiv);
        
        // Smooth scroll to bottom with a slight delay to ensure DOM is updated
        setTimeout(() => {
            chatContainer.scrollTo({
                top: chatContainer.scrollHeight,
                behavior: 'smooth'
            });
        }, 10);
    }
}

// Global functions for HTML onclick handlers
function sendQuickMessage(message) {
    if (window.aiChat) {
        const input = document.getElementById('messageInput');
        input.value = message;
        window.aiChat.sendMessage();
    }
}

function sendMessage() {
    if (window.aiChat) {
        window.aiChat.sendMessage();
    }
}

// Initialize when page loads - EXACTLY like desktop
document.addEventListener('DOMContentLoaded', () => {
    window.aiChat = new AIChat();
    
    // Update stats periodically - EXACTLY like desktop
    setInterval(() => {
        window.aiChat.checkConnections();
    }, 30000); // Every 30 seconds
    
    console.log('Mobile AI Chat Interface initialized');
});