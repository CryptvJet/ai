// Mobile AI Chat Interface JavaScript
class MobileAIChat {
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
        return 'mobile_chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
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
            messageInput.style.height = Math.min(messageInput.scrollHeight, 100) + 'px';
        });

        // Add welcome message after a short delay
        setTimeout(() => {
            this.addWelcomeMessage();
        }, 1000);
    }

    async loadAISettings() {
        try {
            const response = await fetch('api/settings.php');
            const settings = await response.json();
            
            if (settings.success) {
                const aiName = settings.data.ai_name || 'Zin AI';
                document.getElementById('aiName').textContent = aiName;
                document.getElementById('aiNameLabel').textContent = aiName + ':';
                document.getElementById('welcomeText').textContent = settings.data.welcome_message || 'Hi! I\'m your AI Assistant powered by PulseCore technology. I hope you are doing well today! What should I call you?';
            }
        } catch (error) {
            console.warn('Could not load AI settings:', error);
        }
    }

    setupVoiceRecognition() {
        // Check if we're on HTTPS or localhost (required for microphone)
        const isSecureContext = window.location.protocol === 'https:' || 
                               window.location.hostname === 'localhost' ||
                               window.location.hostname === '127.0.0.1';
        
        if (!isSecureContext) {
            console.warn('Voice recognition requires HTTPS or localhost');
            document.getElementById('voiceBtn').style.display = 'none';
            return;
        }
        
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            try {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.recognition = new SpeechRecognition();
                this.voiceTimeout = 'normal';
                this.recognitionTimer = null;
                this.isRecognitionActive = false;
                
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 3;
            } catch (error) {
                console.warn('Failed to initialize speech recognition:', error);
                this.recognition = null;
                return;
            }

            this.setupRecognitionHandlers();
        } else {
            document.getElementById('voiceBtn').style.display = 'none';
            console.warn('Speech recognition not supported');
        }
    }

    setupRecognitionHandlers() {
        this.recognition.onstart = () => {
            console.log('Voice recognition started');
            this.isListening = true;
            this.isRecognitionActive = true;
            
            const voiceBtn = document.getElementById('voiceBtn');
            const voiceIndicator = document.getElementById('voiceIndicator');
            const voiceStatus = document.getElementById('voiceStatus');
            
            if (voiceBtn) voiceBtn.classList.add('listening');
            if (voiceIndicator) voiceIndicator.classList.remove('hidden');
            
            if (voiceStatus) {
                const timeoutText = this.voiceTimeout === 'continuous' ? 'Continuous listening...' :
                                  this.voiceTimeout === '30' ? '30 second timeout' :
                                  this.voiceTimeout === '60' ? '60 second timeout' :
                                  '5 second timeout';
                voiceStatus.textContent = `🎤 Listening - ${timeoutText}`;
            }
            
            this.setVoiceRecognitionTimeout();
        };

        this.recognition.onend = () => {
            console.log('Voice recognition ended');
            this.isListening = false;
            this.isRecognitionActive = false;
            
            const voiceBtn = document.getElementById('voiceBtn');
            const voiceStatus = document.getElementById('voiceStatus');
            
            if (voiceBtn) voiceBtn.classList.remove('listening');
            
            if (this.recognitionTimer) {
                clearTimeout(this.recognitionTimer);
                this.recognitionTimer = null;
            }
            
            if (voiceStatus) {
                voiceStatus.textContent = '⏹️ Voice recognition stopped. Tap X to close or 🎤 to start again.';
            }
        };

        this.recognition.onresult = (event) => {
            let finalTranscript = '';
            let interimTranscript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }
            
            const messageInput = document.getElementById('messageInput');
            const voiceStatus = document.getElementById('voiceStatus');
            
            if (finalTranscript) {
                messageInput.value = finalTranscript;
                messageInput.style.fontStyle = 'normal';
                messageInput.style.color = '#e2e8f0';
                
                if (voiceStatus) {
                    voiceStatus.textContent = '✅ Message captured! Sending...';
                }
                
                if (this.voiceTimeout === 'normal') {
                    this.sendMessage();
                    this.stopListening();
                } else {
                    this.sendMessage();
                    if (voiceStatus) {
                        voiceStatus.textContent = 'Message sent! Still listening...';
                    }
                }
            } else if (interimTranscript) {
                messageInput.value = interimTranscript;
                messageInput.style.fontStyle = 'italic';
                messageInput.style.color = '#94a3b8';
                
                if (voiceStatus) {
                    voiceStatus.textContent = '🎤 Speaking detected...';
                }
            }
        };

        this.recognition.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            this.isListening = false;
            this.isRecognitionActive = false;
            
            const voiceBtn = document.getElementById('voiceBtn');
            const voiceStatus = document.getElementById('voiceStatus');
            
            if (voiceBtn) voiceBtn.classList.remove('listening');
            
            if (this.recognitionTimer) {
                clearTimeout(this.recognitionTimer);
                this.recognitionTimer = null;
            }
            
            let errorMsg = '';
            switch (event.error) {
                case 'not-allowed':
                    errorMsg = '⌫ Microphone access denied. Please allow microphone permissions.';
                    break;
                case 'network':
                    errorMsg = '🌐 Network error. Check your internet connection.';
                    break;
                case 'no-speech':
                    errorMsg = '🔇 No speech detected. Try speaking louder.';
                    break;
                default:
                    errorMsg = `⚠️ Speech recognition error: ${event.error}`;
            }
            
            if (voiceStatus) {
                voiceStatus.textContent = errorMsg;
            }
        };
    }

    setVoiceRecognitionTimeout() {
        if (this.recognitionTimer) {
            clearTimeout(this.recognitionTimer);
        }
        
        let timeout;
        switch (this.voiceTimeout) {
            case '30':
                timeout = 30000;
                break;
            case '60':
                timeout = 60000;
                break;
            case 'continuous':
                return;
            default:
                timeout = 5000;
        }
        
        this.recognitionTimer = setTimeout(() => {
            if (this.isListening) {
                this.stopListening();
                
                const messageInput = document.getElementById('messageInput');
                if (messageInput.value.trim()) {
                    messageInput.style.fontStyle = 'normal';
                    messageInput.style.color = '#e2e8f0';
                    this.sendMessage();
                }
            }
        }, timeout);
    }

    stopListening() {
        console.log('Stop listening requested');
        
        if (this.recognitionTimer) {
            clearTimeout(this.recognitionTimer);
            this.recognitionTimer = null;
        }
        
        if (this.recognition && (this.isListening || this.isRecognitionActive)) {
            try {
                this.recognition.stop();
            } catch (error) {
                console.warn('Error stopping recognition:', error);
                this.isListening = false;
                this.isRecognitionActive = false;
                
                const voiceBtn = document.getElementById('voiceBtn');
                if (voiceBtn) voiceBtn.classList.remove('listening');
            }
        } else {
            this.isListening = false;
            this.isRecognitionActive = false;
            
            const voiceBtn = document.getElementById('voiceBtn');
            if (voiceBtn) voiceBtn.classList.remove('listening');
        }
    }

    async checkConnections() {
        // Check PulseCore connection
        try {
            const response = await fetch('api/pulsecore-status.php');
            const status = await response.json();
            const isConnected = status.success && status.status === 'connected';
            this.updateConnectionStatus('pulseStatus', isConnected);
            
            // Update mobile status indicator
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                if (isConnected) {
                    connectionStatus.textContent = 'Active';
                    connectionStatus.style.background = 'rgba(34, 197, 94, 0.2)';
                    connectionStatus.style.color = '#22c55e';
                    connectionStatus.style.borderColor = 'rgba(34, 197, 94, 0.3)';
                } else {
                    connectionStatus.textContent = 'Offline';
                    connectionStatus.style.background = 'rgba(239, 68, 68, 0.2)';
                    connectionStatus.style.color = '#ef4444';
                    connectionStatus.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                }
            }
        } catch (error) {
            console.error('PulseCore status check failed:', error);
            this.updateConnectionStatus('pulseStatus', false);
            
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.textContent = 'Connecting';
                connectionStatus.style.background = 'rgba(234, 179, 8, 0.2)';
                connectionStatus.style.color = '#eab308';
                connectionStatus.style.borderColor = 'rgba(234, 179, 8, 0.3)';
            }
        }

        // Check PC Bridge connection
        try {
            const response = await fetch('api/pc-status.php');
            const status = await response.json();
            
            if (status.success && status.data.is_online) {
                this.updateConnectionStatus('pcStatus', true);
            } else {
                throw new Error('PC Bridge not responding');
            }
        } catch (error) {
            console.log('PC Bridge not available (normal for web-only usage):', error.message);
            this.updateConnectionStatus('pcStatus', false);
        }

        // Check AI connection
        await this.checkLocalLlamaStatus();
    }

    async checkLocalLlamaStatus() {
        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: 'test',
                    session_id: this.sessionId,
                    mode: 'auto',
                    status_check: true
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.ai_source === 'local_ollama') {
                this.currentMode = 'full-power';
                this.updateAIMode(true, result.model);
                return true;
            } else if (result.success && result.ai_source === 'web_ai') {
                this.currentMode = 'full-power';
                this.updateAIMode(true, 'Web AI');
                return true;
            } else if (result.success && (result.ai_source === 'database_template' || result.ai_source === 'basic_fallback')) {
                this.currentMode = 'full-power';
                this.updateAIMode(true, 'Enhanced Chill Mode');
                return true;
            } else if (result.success) {
                this.currentMode = 'full-power';
                this.updateAIMode(true, result.ai_source || 'AI Assistant');
                return true;
            } else {
                throw new Error('AI connection test failed');
            }
        } catch (error) {
            console.log('AI not available:', error.message);
            this.currentMode = 'chill';
            this.updateAIMode(false);
            return false;
        }
    }

    updateAIMode(hasLocalAI, modelName = null) {
        const aiModeElement = document.getElementById('aiMode');
        
        if (hasLocalAI) {
            aiModeElement.textContent = 'Full Power Mode 🧠';
            aiModeElement.style.color = '#22c55e';
            console.log('🚀 Switched to Full Power Mode');
        } else {
            aiModeElement.textContent = 'Chill Mode';
            aiModeElement.style.color = '#94a3b8';
            console.log('🌙 Using Chill Mode');
        }
    }

    updateConnectionStatus(elementId, connected) {
        const element = document.getElementById(elementId);
        if (element) {
            const dot = element.querySelector('.status-dot');
            if (dot) {
                if (connected) {
                    dot.classList.add('connected');
                } else {
                    dot.classList.remove('connected');
                }
            }
        }
    }

    async loadPulseCoreStats() {
        try {
            const response = await fetch('api/pulsecore-stats.php');
            const stats = await response.json();
            
            if (stats.success) {
                document.getElementById('totalClimaxes').textContent = stats.data.total_climaxes || 0;
                document.getElementById('totalNovas').textContent = stats.data.total_novas || 0;
                document.getElementById('avgComplexity').textContent = stats.data.avg_complexity ? stats.data.avg_complexity.toFixed(0) : 'N/A';
                document.getElementById('totalSessions').textContent = stats.data.total_sessions || 0;
            } else {
                throw new Error('API returned error');
            }
        } catch (error) {
            console.error('Could not load PulseCore stats:', error);
            
            // Fallback data
            document.getElementById('totalClimaxes').textContent = '2101';
            document.getElementById('totalNovas').textContent = '3352';
            document.getElementById('avgComplexity').textContent = '17090';
            document.getElementById('totalSessions').textContent = '144';
        }
    }

    toggleVoice() {
        if (!this.recognition) {
            const isSecureContext = window.location.protocol === 'https:' || 
                                   window.location.hostname === 'localhost' ||
                                   window.location.hostname === '127.0.0.1';
            
            if (!isSecureContext) {
                alert('Voice recognition requires HTTPS or localhost.');
            } else {
                alert('Voice recognition not supported in this browser.');
            }
            return;
        }

        if (this.isListening || this.isRecognitionActive) {
            this.stopListening();
        } else {
            this.startListening();
        }
    }

    startListening() {
        if (this.isRecognitionActive) {
            return;
        }
        
        const voiceTimeout = document.getElementById('voiceTimeout');
        if (voiceTimeout) {
            this.voiceTimeout = voiceTimeout.value;
        }
        
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.value = '';
            messageInput.style.fontStyle = 'normal';
            messageInput.style.color = '#e2e8f0';
        }
        
        const voiceStatus = document.getElementById('voiceStatus');
        if (voiceStatus) {
            voiceStatus.textContent = '🔄 Initializing microphone...';
        }
        
        try {
            this.recognition.start();
        } catch (error) {
            console.error('Failed to start voice recognition:', error);
            
            if (error.name === 'InvalidStateError') {
                this.isListening = false;
                this.isRecognitionActive = false;
                
                try {
                    this.recognition.stop();
                } catch (stopError) {
                    console.warn('Error stopping recognition:', stopError);
                }
                
                setTimeout(() => {
                    if (!this.isRecognitionActive) {
                        try {
                            this.recognition.start();
                        } catch (restartError) {
                            console.error('Failed to restart:', restartError);
                            if (voiceStatus) {
                                voiceStatus.textContent = '⌫ Could not start microphone. Please try again.';
                            }
                        }
                    }
                }, 200);
            } else {
                if (voiceStatus) {
                    voiceStatus.textContent = `⌫ Error: ${error.message}`;
                }
            }
        }
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;

        input.value = '';
        input.style.fontStyle = 'normal';
        input.style.color = '#e2e8f0';
        
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;

        this.addMessage('user', message);

        try {
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
                
                if (this.autoSpeak && this.synthesis) {
                    this.speakText(result.response);
                }
            } else {
                this.addMessage('ai', 'Sorry, I encountered an error processing your message. Please try again.');
            }
        } catch (error) {
            console.error('Network error:', error);
            this.addMessage('ai', 'Sorry, I\'m having trouble connecting. Please check your connection and try again.');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    addMessage(type, content) {
        const chatContainer = document.getElementById('chatContainer');
        
        // Remove welcome message if it exists
        const welcomeMessage = document.getElementById('welcomeMessage');
        if (welcomeMessage && type === 'user') {
            welcomeMessage.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;
        
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const aiName = document.getElementById('aiName').textContent;
        
        messageDiv.innerHTML = `
            <div class="message-content">
                <strong>${type === 'user' ? 'You' : aiName}:</strong> ${content}
            </div>
            <div class="message-time">${timestamp}</div>
        `;
        
        chatContainer.appendChild(messageDiv);
        
        setTimeout(() => {
            chatContainer.scrollTo({
                top: chatContainer.scrollHeight,
                behavior: 'smooth'
            });
        }, 10);
    }

    addWelcomeMessage() {
        const chatContainer = document.getElementById('chatContainer');
        
        if (chatContainer.children.length === 1) { // Only welcome message exists
            const welcomeMessage = `Hello! I'm your AI assistant specializing in PulseCore analysis and data exploration. 

I can help you with:
• Analyzing your nova events and patterns
• Exploring complexity and energy data  
• Searching through your variables database
• General conversations about your simulations

I'd love to get to know you better - what's your name?`;

            // Clear existing welcome and add new message
            const existingWelcome = document.getElementById('welcomeMessage');
            if (existingWelcome) {
                existingWelcome.remove();
            }
            
            this.addMessage('ai', welcomeMessage);
        }
    }

    loadVoices() {
        const loadVoicesList = () => {
            this.voices = this.synthesis.getVoices();
            this.populateVoiceDropdown();
        };
        
        if (this.synthesis.getVoices().length !== 0) {
            loadVoicesList();
        } else {
            this.synthesis.addEventListener('voiceschanged', loadVoicesList);
        }
    }

    populateVoiceDropdown() {
        const voiceSelect = document.getElementById('voiceSelect');
        if (!voiceSelect) return;
        
        voiceSelect.innerHTML = '<option value="">Default Voice</option>';
        
        const voicesByLang = {};
        this.voices.forEach((voice, index) => {
            const lang = voice.lang.split('-')[0];
            if (!voicesByLang[lang]) voicesByLang[lang] = [];
            voicesByLang[lang].push({voice, index});
        });
        
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
        
        Object.keys(voicesByLang).forEach(lang => {
            if (lang !== 'en') {
                const langName = new Intl.DisplayNames(['en'], {type: 'language'}).of(lang) || lang;
                addVoiceGroup(lang, langName);
            }
        });
        
        voiceSelect.addEventListener('change', (e) => {
            const voiceIndex = e.target.value;
            this.selectedVoice = voiceIndex ? this.voices[voiceIndex] : null;
            localStorage.setItem('selectedVoice', voiceIndex);
            
            if (this.selectedVoice) {
                this.speakText('Voice changed successfully!');
            }
        });
        
        const savedVoice = localStorage.getItem('selectedVoice');
        if (savedVoice && this.voices[savedVoice]) {
            voiceSelect.value = savedVoice;
            this.selectedVoice = this.voices[savedVoice];
        }
        
        this.setupVoiceControls();
    }

    setupVoiceControls() {
        // Auto-speak toggle
        const autoSpeakToggle = document.getElementById('autoSpeak');
        if (autoSpeakToggle) {
            const savedAutoSpeak = localStorage.getItem('autoSpeak');
            this.autoSpeak = savedAutoSpeak !== 'false';
            autoSpeakToggle.checked = this.autoSpeak;
            
            autoSpeakToggle.addEventListener('change', (e) => {
                this.autoSpeak = e.target.checked;
                localStorage.setItem('autoSpeak', this.autoSpeak.toString());
            });
        }
        
        // Speech controls
        this.setupRangeControl('speechRate', 'speedValue', '1.2', 'x');
        this.setupRangeControl('speechPitch', 'pitchValue', '1.0', '');
        this.setupRangeControl('speechVolume', 'volumeValue', '0.8', '');
        
        // Voice timeout
        const voiceTimeout = document.getElementById('voiceTimeout');
        if (voiceTimeout) {
            const savedTimeout = localStorage.getItem('voiceTimeout') || 'normal';
            voiceTimeout.value = savedTimeout;
            this.voiceTimeout = savedTimeout;
            
            voiceTimeout.addEventListener('change', (e) => {
                this.voiceTimeout = e.target.value;
                localStorage.setItem('voiceTimeout', e.target.value);
            });
        }
        
        // Test voice button
        const testVoiceBtn = document.getElementById('testVoiceBtn');
        if (testVoiceBtn) {
            testVoiceBtn.addEventListener('click', () => {
                this.testCurrentVoiceSettings();
            });
        }
    }

    setupRangeControl(inputId, valueId, defaultValue, suffix) {
        const input = document.getElementById(inputId);
        const valueDisplay = document.getElementById(valueId);
        
        if (input && valueDisplay) {
            const savedValue = localStorage.getItem(inputId) || defaultValue;
            input.value = savedValue;
            valueDisplay.textContent = savedValue + suffix;
            
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                valueDisplay.textContent = value + suffix;
                localStorage.setItem(inputId, value);
            });
        }
    }

    testCurrentVoiceSettings() {
        const testMessages = [
            "Hello! This is a test of your current voice settings.",
            "I'm speaking at the configured speed, pitch, and volume levels.",
            "How do I sound to you?"
        ];
        
        const randomMessage = testMessages[Math.floor(Math.random() * testMessages.length)];
        this.speakText(randomMessage);
    }

    async speakText(text) {
        if (!this.autoSpeak || !this.synthesis) {
            return;
        }
        
        this.synthesis.cancel();
        
        if (this.synthesis.speaking) {
            await new Promise(resolve => {
                let attempts = 0;
                const maxAttempts = 20;
                const checkSpeaking = () => {
                    if (!this.synthesis.speaking || attempts >= maxAttempts) {
                        resolve();
                    } else {
                        attempts++;
                        setTimeout(checkSpeaking, 50);
                    }
                };
                setTimeout(checkSpeaking, 100);
            });
        }
        
        const utterance = new SpeechSynthesisUtterance(text);
        
        const speechRate = document.getElementById('speechRate');
        const speechPitch = document.getElementById('speechPitch');
        const speechVolume = document.getElementById('speechVolume');
        
        let rate = speechRate ? parseFloat(speechRate.value) : 1.2;
        let pitch = speechPitch ? parseFloat(speechPitch.value) : 1.0;
        let volume = speechVolume ? parseFloat(speechVolume.value) : 0.8;
        
        utterance.rate = Math.min(Math.max(rate, 0.5), 2.0);
        utterance.pitch = Math.min(Math.max(pitch, 0.5), 2.0);
        utterance.volume = Math.min(Math.max(volume, 0.1), 1.0);
        
        if (this.selectedVoice) {
            utterance.voice = this.selectedVoice;
        } else {
            const englishVoices = this.voices.filter(voice => 
                voice.lang.startsWith('en-') || voice.name.toLowerCase().includes('english')
            );
            if (englishVoices.length > 0) {
                utterance.voice = englishVoices[0];
            }
        }
        
        utterance.onstart = () => console.log('Speech started');
        utterance.onend = () => console.log('Speech ended');
        utterance.onerror = (e) => {
            console.warn('Speech synthesis error:', e.error);
        };
        
        setTimeout(() => {
            try {
                this.synthesis.speak(utterance);
            } catch (error) {
                console.error('Speech synthesis failed:', error);
            }
        }, 100);
    }

    quickMessage(message) {
        document.getElementById('messageInput').value = message;
        this.sendMessage();
    }

    clearChat() {
        if (confirm('Are you sure you want to clear the chat history?')) {
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.innerHTML = `
                <div class="welcome-message" id="welcomeMessage">
                    <div class="welcome-content">
                        <div class="zin-intro">
                            <h2>Welcome to Zin AI! 🤖</h2>
                            <p><strong id="aiNameLabel">Zin:</strong> <span id="welcomeText">Hi! I'm your AI Assistant powered by PulseCore technology. I hope you are doing well today! What should I call you?</span></p>
                        </div>
                        
                        <div class="quick-actions">
                            <h3>Quick Actions</h3>
                            <div class="action-buttons">
                                <button class="quick-btn" onclick="quickMessage('What are my recent novas?')">Recent Novas</button>
                                <button class="quick-btn" onclick="quickMessage('Analyze my patterns')">Pattern Analysis</button>
                                <button class="quick-btn" onclick="quickMessage('Help me optimize')">Optimization Tips</button>
                                <button class="quick-btn" onclick="quickMessage('Show variables data')">Variables Data</button>
                            </div>
                        </div>

                        <div class="team-section">
                            <h3>Built Through Collaboration</h3>
                            <p>Created through 25+ hours of collaborative development work</p>
                            <div class="team-grid">
                                <div class="team-item">
                                    <span class="team-icon">🚀</span>
                                    <span class="team-name">Powered by Ollama</span>
                                </div>
                                <div class="team-item">
                                    <span class="team-icon">🧠</span>
                                    <span class="team-name">Designed with Claude</span>
                                </div>
                                <div class="team-item">
                                    <span class="team-icon">💫</span>
                                    <span class="team-name">Inspired by Sorya GPT</span>
                                </div>
                                <div class="team-item">
                                    <span class="team-icon">🔬</span>
                                    <span class="team-name">Built for PulseCore Research</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.sessionId = this.generateSessionId();
        }
    }

    openAdmin() {
        window.open('admin/index.html', '_blank');
    }
}

// Mobile Menu Functions
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('mobileMenuOverlay');
    
    menu.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('mobileMenuOverlay');
    
    menu.classList.remove('active');
    overlay.classList.remove('active');
}

// Global functions for compatibility
function sendMessage() {
    window.mobileAiChat.sendMessage();
}

function toggleVoice() {
    window.mobileAiChat.toggleVoice();
}

function quickMessage(message) {
    window.mobileAiChat.quickMessage(message);
}

function clearChat() {
    window.mobileAiChat.clearChat();
}

function openAdmin() {
    window.mobileAiChat.openAdmin();
}

function closeVoiceIndicator() {
    if (window.mobileAiChat.isListening) {
        window.mobileAiChat.stopListening();
    }
    
    document.getElementById('voiceIndicator').classList.add('hidden');
    
    const messageInput = document.getElementById('messageInput');
    messageInput.style.fontStyle = 'normal';
    messageInput.style.color = '#e2e8f0';
    
    const voiceStatus = document.getElementById('voiceStatus');
    if (voiceStatus) {
        voiceStatus.textContent = 'Ready to listen...';
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.mobileAiChat = new MobileAIChat();
    
    // Update stats and connections periodically
    setInterval(() => {
        window.mobileAiChat.loadPulseCoreStats();
        window.mobileAiChat.checkConnections();
    }, 30000);
    
    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('mobileMenu');
        const menuBtn = document.getElementById('menuBtn');
        const overlay = document.getElementById('mobileMenuOverlay');
        
        if (menu.classList.contains('active') && 
            !menu.contains(e.target) && 
            !menuBtn.contains(e.target)) {
            closeMobileMenu();
        }
    });
    
    // Handle swipe to close menu
    let startX = 0;
    let startY = 0;
    
    document.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });
    
    document.addEventListener('touchend', (e) => {
        const menu = document.getElementById('mobileMenu');
        if (!menu.classList.contains('active')) return;
        
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        const diffX = startX - endX;
        const diffY = Math.abs(startY - endY);
        
        // Swipe right to close (from left edge of menu)
        if (startX < 50 && diffX < -100 && diffY < 100) {
            closeMobileMenu();
        }
    });
});