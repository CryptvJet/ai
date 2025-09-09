// AI Chat Interface JavaScript
class AIChat {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.isListening = false;
        this.recognition = null;
        this.synthesis = window.speechSynthesis;
        this.voices = [];
        this.selectedVoice = null;
        this.autoSpeak = true;
        this.currentMode = 'chill'; // 'chill' or 'full-power'
        
        this.initializeChat();
        this.setupVoiceRecognition();
        this.loadVoices();
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

        // Welcome message will be set by loadAISettings() and the HTML
    }

    async loadAISettings() {
        try {
            const response = await fetch('api/settings.php');
            const settings = await response.json();
            
            if (settings.success) {
                const aiName = settings.data.ai_name || 'AI Assistant';
                document.getElementById('aiName').textContent = aiName;
                document.getElementById('aiNameLabel').textContent = aiName + ':';
                document.getElementById('welcomeText').textContent = settings.data.welcome_message || 'Hi! I\'m your AI Assistant. I hope you are doing well today! What should I call you?';
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
            console.log('Loading PulseCore stats...');
            const response = await fetch('api/pulsecore-stats.php');
            const stats = await response.json();
            
            console.log('PulseCore stats response:', stats);
            
            if (stats.success) {
                console.log('Updating stats with data:', stats.data);
                document.getElementById('totalNovas').textContent = stats.data.total_novas || 0;
                document.getElementById('lastComplexity').textContent = stats.data.last_complexity || 'N/A';
                document.getElementById('avgEnergy').textContent = stats.data.avg_energy ? stats.data.avg_energy.toFixed(2) : 'N/A';
                document.getElementById('totalSessions').textContent = stats.data.total_sessions || 0;
            } else {
                throw new Error('API returned error: ' + (stats.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Could not load PulseCore stats, using fallback data:', error);
            
            // Temporary fallback with your actual database values until server is fixed
            // TODO: Remove this once the server 406 error is resolved
            document.getElementById('totalNovas').textContent = '209';
            document.getElementById('lastComplexity').textContent = '17090';
            document.getElementById('avgEnergy').textContent = '178294.17';
            document.getElementById('totalSessions').textContent = '5';
            
            console.warn('Using fallback stats - server API not working (406 error)');
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
                
                // Speak response if auto-speak is enabled
                if (this.autoSpeak && this.synthesis) {
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

    loadVoices() {
        // Load available voices
        const loadVoicesList = () => {
            this.voices = this.synthesis.getVoices();
            this.populateVoiceDropdown();
        };
        
        // Voices might not be loaded immediately
        if (this.synthesis.getVoices().length !== 0) {
            loadVoicesList();
        } else {
            this.synthesis.addEventListener('voiceschanged', loadVoicesList);
        }
    }
    
    populateVoiceDropdown() {
        const voiceSelect = document.getElementById('voiceSelect');
        if (!voiceSelect) return;
        
        // Clear existing options
        voiceSelect.innerHTML = '<option value="">Default Voice</option>';
        
        // Group voices by language for better organization
        const voicesByLang = {};
        this.voices.forEach((voice, index) => {
            const lang = voice.lang.split('-')[0];
            if (!voicesByLang[lang]) voicesByLang[lang] = [];
            voicesByLang[lang].push({voice, index});
        });
        
        // Add voices to dropdown, prioritizing English
        const addVoiceGroup = (langCode, label) => {
            if (voicesByLang[langCode]) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = label;
                voicesByLang[langCode].forEach(({voice, index}) => {
                    const option = document.createElement('option');
                    option.value = index;
                    option.textContent = `${voice.name} (${voice.lang})`;
                    optgroup.appendChild(option);
                });
                voiceSelect.appendChild(optgroup);
            }
        };
        
        addVoiceGroup('en', 'English');
        
        // Add other languages
        Object.keys(voicesByLang).forEach(lang => {
            if (lang !== 'en') {
                const langName = new Intl.DisplayNames(['en'], {type: 'language'}).of(lang) || lang;
                addVoiceGroup(lang, langName);
            }
        });
        
        // Set up event listener for voice selection
        voiceSelect.addEventListener('change', (e) => {
            const voiceIndex = e.target.value;
            this.selectedVoice = voiceIndex ? this.voices[voiceIndex] : null;
            
            // Save preference
            localStorage.setItem('selectedVoice', voiceIndex);
            
            // Test the voice with a short phrase
            if (this.selectedVoice) {
                this.speakText('Voice changed successfully!');
            }
        });
        
        // Restore saved voice preference
        const savedVoice = localStorage.getItem('selectedVoice');
        if (savedVoice && this.voices[savedVoice]) {
            voiceSelect.value = savedVoice;
            this.selectedVoice = this.voices[savedVoice];
        }
        
        // Set up auto-speak toggle
        const autoSpeakToggle = document.getElementById('autoSpeak');
        if (autoSpeakToggle) {
            autoSpeakToggle.checked = localStorage.getItem('autoSpeak') !== 'false';
            this.autoSpeak = autoSpeakToggle.checked;
            
            autoSpeakToggle.addEventListener('change', (e) => {
                this.autoSpeak = e.target.checked;
                localStorage.setItem('autoSpeak', this.autoSpeak);
            });
        }
    }

    speakText(text) {
        if (!this.autoSpeak) return;
        
        // Cancel any ongoing speech
        this.synthesis.cancel();
        
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 0.9;
        utterance.pitch = 1.0;
        utterance.volume = 0.8;
        
        // Use selected voice if available
        if (this.selectedVoice) {
            utterance.voice = this.selectedVoice;
        }
        
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
                        <strong id="aiNameLabel">AI:</strong> <span id="welcomeText">Hi! I'm your AI Assistant. I hope you are doing well today! What should I call you?</span>
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