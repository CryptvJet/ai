// AI Chat Interface JavaScript
class AIChat {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.isListening = false;
        this.recognition = null;
        this.synthesis = window.speechSynthesis;
        this.currentMode = 'chill'; // 'chill' or 'full-power'
        
        this.initializeChat();
        this.setupVoiceRecognition();
        this.checkConnections();
        this.loadPulseCoreStats();
    }

    generateSessionId() {
        return 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    initializeChat() {
        // Load AI name and settings
        this.loadAISettings();
        
        // Set up event listeners
        document.getElementById('messageInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Set up send button
        document.getElementById('sendBtn').addEventListener('click', () => {
            this.sendMessage();
        });

        // Auto-resize input
        const messageInput = document.getElementById('messageInput');
        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = messageInput.scrollHeight + 'px';
        });

        this.addMessage('ai', 'Hello! I\'m your AI assistant. I can analyze PulseCore data, help with calculations, and chat about your projects. How can I help you today?');
    }

    async loadAISettings() {
        try {
            const response = await fetch('api/settings.php');
            const settings = await response.json();
            
            if (settings.success) {
                document.getElementById('aiName').textContent = settings.data.ai_name || 'AI Assistant';
                document.getElementById('welcomeText').textContent = settings.data.welcome_message || 'Hello! I\'m your AI assistant.';
            }
        } catch (error) {
            console.warn('Could not load AI settings:', error);
        }
    }

    setupVoiceRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = 'en-US';

            this.recognition.onstart = () => {
                this.isListening = true;
                document.getElementById('voiceBtn').classList.add('listening');
                document.getElementById('voiceIndicator').classList.remove('hidden');
            };

            this.recognition.onend = () => {
                this.isListening = false;
                document.getElementById('voiceBtn').classList.remove('listening');
                document.getElementById('voiceIndicator').classList.add('hidden');
            };

            this.recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                document.getElementById('messageInput').value = transcript;
                this.sendMessage();
            };

            this.recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                this.isListening = false;
                document.getElementById('voiceBtn').classList.remove('listening');
                document.getElementById('voiceIndicator').classList.add('hidden');
            };
        } else {
            document.getElementById('voiceBtn').style.display = 'none';
            console.warn('Speech recognition not supported');
        }
    }

    async checkConnections() {
        // Check PulseCore connection
        try {
            const response = await fetch('api/pulsecore-status.php');
            const status = await response.json();
            this.updateConnectionStatus('pulseStatus', status.connected);
        } catch (error) {
            this.updateConnectionStatus('pulseStatus', false);
        }

        // Check PC AI connection
        try {
            const response = await fetch('http://localhost:8000/health');
            this.updateConnectionStatus('pcStatus', true);
            this.currentMode = 'full-power';
            document.getElementById('aiMode').textContent = 'Full Power';
            document.getElementById('aiMode').className = 'mode-indicator full-power';
        } catch (error) {
            this.updateConnectionStatus('pcStatus', false);
            this.currentMode = 'chill';
        }
    }

    updateConnectionStatus(elementId, connected) {
        const element = document.getElementById(elementId);
        const dot = element.querySelector('.status-dot');
        if (connected) {
            dot.classList.add('connected');
        } else {
            dot.classList.remove('connected');
        }
    }

    async loadPulseCoreStats() {
        try {
            const response = await fetch('api/pulsecore-stats.php');
            const stats = await response.json();
            
            if (stats.success) {
                document.getElementById('totalNovas').textContent = stats.data.total_novas || 0;
                document.getElementById('lastComplexity').textContent = stats.data.last_complexity || 'N/A';
                document.getElementById('avgEnergy').textContent = stats.data.avg_energy ? stats.data.avg_energy.toFixed(2) : 'N/A';
            }
        } catch (error) {
            console.warn('Could not load PulseCore stats:', error);
        }
    }

    toggleVoice() {
        if (!this.recognition) {
            alert('Voice recognition not supported in this browser');
            return;
        }

        if (this.isListening) {
            this.recognition.stop();
        } else {
            this.recognition.start();
        }
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;

        // Clear input and disable send button temporarily
        input.value = '';
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;

        // Add user message to chat
        this.addMessage('user', message);

        try {
            // Send message to AI
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
                
                // Speak response if enabled
                if (result.speak && this.synthesis) {
                    this.speakText(result.response);
                }
            } else {
                this.addMessage('ai', 'Sorry, I encountered an error processing your message. Please try again.');
                console.error('Chat error:', result.error);
            }
        } catch (error) {
            console.error('Network error:', error);
            this.addMessage('ai', 'Sorry, I\'m having trouble connecting right now. Please check your connection and try again.');
        } finally {
            sendBtn.disabled = false;
        }
    }

    addMessage(type, content) {
        const chatContainer = document.getElementById('chatContainer');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;
        
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        messageDiv.innerHTML = `
            <div class="message-content">
                <strong>${type === 'user' ? 'You' : document.getElementById('aiName').textContent}:</strong> ${content}
            </div>
            <div class="message-time">${timestamp}</div>
        `;
        
        chatContainer.appendChild(messageDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    speakText(text) {
        // Cancel any ongoing speech
        this.synthesis.cancel();
        
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 0.9;
        utterance.pitch = 1.0;
        utterance.volume = 0.8;
        
        this.synthesis.speak(utterance);
    }

    quickMessage(message) {
        document.getElementById('messageInput').value = message;
        this.sendMessage();
    }

    clearChat() {
        if (confirm('Are you sure you want to clear the chat history?')) {
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.innerHTML = `
                <div class="message ai-message" id="welcomeMessage">
                    <div class="message-content">
                        <strong>AI:</strong> <span id="welcomeText">Chat cleared! How can I help you?</span>
                    </div>
                    <div class="message-time"></div>
                </div>
            `;
            
            // Generate new session ID
            this.sessionId = this.generateSessionId();
        }
    }

    openAdmin() {
        window.open('admin/index.html', '_blank');
    }
}

// Global functions
function sendMessage() {
    window.aiChat.sendMessage();
}

function toggleVoice() {
    window.aiChat.toggleVoice();
}

function quickMessage(message) {
    window.aiChat.quickMessage(message);
}

function clearChat() {
    window.aiChat.clearChat();
}

function openAdmin() {
    window.aiChat.openAdmin();
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.aiChat = new AIChat();
    
    // Update stats periodically
    setInterval(() => {
        window.aiChat.loadPulseCoreStats();
        window.aiChat.checkConnections();
    }, 30000); // Every 30 seconds
});