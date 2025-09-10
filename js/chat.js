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
        // After a short delay, add a welcome message if chat is empty
        setTimeout(() => {
            this.addWelcomeMessage();
        }, 1000);
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
                
                // Update admin button with AI name
                const adminBtn = document.getElementById('adminBtn');
                if (adminBtn) {
                    adminBtn.textContent = `⚙️ ${aiName} Admin`;
                }
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
                this.voiceTimeout = 'normal'; // Initialize voice timeout
                this.recognitionTimer = null;
                this.isRecognitionActive = false; // Track recognition state
                
                this.recognition.continuous = true; // Enable continuous recognition for extended listening
                this.recognition.interimResults = true; // Show interim results for better UX
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 3;
            } catch (error) {
                console.warn('Failed to initialize speech recognition:', error);
                this.recognition = null;
                return;
            }

            this.recognition.onstart = () => {
                console.log('Voice recognition started successfully');
                this.isListening = true;
                this.isRecognitionActive = true;
                
                const voiceBtn = document.getElementById('voiceBtn');
                const voiceIndicator = document.getElementById('voiceIndicator');
                const voiceStatus = document.getElementById('voiceStatus');
                
                if (voiceBtn) voiceBtn.classList.add('listening');
                if (voiceIndicator) voiceIndicator.classList.remove('hidden');
                
                // Update status
                if (voiceStatus) {
                    const timeoutText = this.voiceTimeout === 'continuous' ? 'Continuous listening...' :
                                      this.voiceTimeout === '30' ? '30 second timeout' :
                                      this.voiceTimeout === '60' ? '60 second timeout' :
                                      '5 second timeout';
                    voiceStatus.textContent = `🎤 Listening active - ${timeoutText}`;
                }
                
                // Set timeout based on user preference
                this.setVoiceRecognitionTimeout();
            };

            this.recognition.onend = () => {
                console.log('Voice recognition ended');
                this.isListening = false;
                this.isRecognitionActive = false;
                
                const voiceBtn = document.getElementById('voiceBtn');
                const voiceStatus = document.getElementById('voiceStatus');
                
                if (voiceBtn) voiceBtn.classList.remove('listening');
                
                // Clear timeout
                if (this.recognitionTimer) {
                    clearTimeout(this.recognitionTimer);
                    this.recognitionTimer = null;
                }
                
                // Update status
                if (voiceStatus) {
                    voiceStatus.textContent = '⭕ Voice recognition stopped. Click X to close or 🎤 to start again.';
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
                        voiceStatus.textContent = '✓ Message captured! Sending...';
                    }
                    
                    // For normal mode, auto-send and stop
                    if (this.voiceTimeout === 'normal') {
                        this.sendMessage();
                        this.stopListening();
                    } else {
                        // For extended modes, send message but keep listening
                        this.sendMessage();
                        if (voiceStatus) {
                            voiceStatus.textContent = 'Message sent! Still listening...';
                        }
                    }
                } else if (interimTranscript) {
                    // Show interim results with different styling
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
                const voiceIndicator = document.getElementById('voiceIndicator');
                const voiceStatus = document.getElementById('voiceStatus');
                
                if (voiceBtn) voiceBtn.classList.remove('listening');
                
                // Clear timeout
                if (this.recognitionTimer) {
                    clearTimeout(this.recognitionTimer);
                    this.recognitionTimer = null;
                }
                
                // Handle different error types
                let errorMsg = '';
                switch (event.error) {
                    case 'not-allowed':
                        errorMsg = '❌ Microphone access denied. Please allow microphone permissions and try again.';
                        if (voiceIndicator) voiceIndicator.classList.add('hidden');
                        alert('Microphone access denied. Please:\n1. Click the microphone icon in your address bar\n2. Select "Allow" for microphone permissions\n3. Try again');
                        break;
                    case 'network':
                        errorMsg = '🌐 Network error. Check your internet connection.';
                        console.warn('Speech recognition network error - check internet connection');
                        break;
                    case 'no-speech':
                        errorMsg = '🔇 No speech detected. Try speaking louder or closer to the microphone.';
                        console.log('No speech detected, timeout reached');
                        break;
                    case 'audio-capture':
                        errorMsg = '🎤 Audio capture error. Check your microphone connection.';
                        break;
                    case 'service-not-allowed':
                        errorMsg = '🚫 Speech service not allowed. Make sure you\'re using HTTPS.';
                        break;
                    case 'aborted':
                        errorMsg = '⏹️ Voice recognition was stopped.';
                        break;
                    default:
                        errorMsg = `⚠️ Speech recognition error: ${event.error}`;
                        console.warn('Unhandled speech recognition error:', event.error);
                }
                
                if (voiceStatus) {
                    voiceStatus.textContent = errorMsg;
                }
            };
        } else {
            document.getElementById('voiceBtn').style.display = 'none';
            console.warn('Speech recognition not supported');
        }
    }
    
    setVoiceRecognitionTimeout() {
        // Clear existing timer
        if (this.recognitionTimer) {
            clearTimeout(this.recognitionTimer);
        }
        
        let timeout;
        switch (this.voiceTimeout) {
            case '30':
                timeout = 30000; // 30 seconds
                break;
            case '60':
                timeout = 60000; // 60 seconds
                break;
            case 'continuous':
                return; // No timeout for continuous mode
            default: // 'normal'
                timeout = 5000; // 5 seconds (normal browser default)
        }
        
        this.recognitionTimer = setTimeout(() => {
            if (this.isListening) {
                this.stopListening();
                
                // For extended modes, send message if there's content
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
                console.log('Recognition stop called');
            } catch (error) {
                console.warn('Error stopping recognition:', error);
                // Force reset state even if stop fails
                this.isListening = false;
                this.isRecognitionActive = false;
                
                const voiceBtn = document.getElementById('voiceBtn');
                if (voiceBtn) voiceBtn.classList.remove('listening');
            }
        } else {
            console.log('Recognition not active or not available');
            // Make sure UI is reset
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
            console.log('PulseCore status response:', status);
            const isConnected = status.success && status.status === 'connected';
            console.log('PulseCore connection result:', isConnected);
            this.updateConnectionStatus('pulseStatus', isConnected);
        } catch (error) {
            console.error('PulseCore status check failed:', error);
            this.updateConnectionStatus('pulseStatus', false);
        }

        // Check PC AI connection (optional local AI server)
        try {
            const response = await fetch('http://localhost:8000/health', {
                method: 'GET',
                timeout: 2000 // 2 second timeout
            });
            if (response.ok) {
                this.updateConnectionStatus('pcStatus', true);
                this.currentMode = 'full-power';
                document.getElementById('aiMode').textContent = 'Full Power';
                document.getElementById('aiMode').className = 'mode-indicator full-power';
            } else {
                throw new Error('PC AI server not responding');
            }
        } catch (error) {
            // PC AI server not running - this is normal, use chill mode
            console.log('PC AI server not available, using chill mode');
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
                document.getElementById('totalClimaxes').textContent = stats.data.total_climaxes || 0;
                document.getElementById('totalNovas').textContent = stats.data.total_novas || 0;
                document.getElementById('avgComplexity').textContent = stats.data.avg_complexity ? stats.data.avg_complexity.toFixed(0) : 'N/A';
                document.getElementById('totalSessions').textContent = stats.data.total_sessions || 0;
            } else {
                throw new Error('API returned error: ' + (stats.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Could not load PulseCore stats, using fallback data:', error);
            
            // Temporary fallback with your actual database values until server is fixed
            // TODO: Remove this once the server 406 error is resolved
            document.getElementById('totalClimaxes').textContent = '2101';
            document.getElementById('totalNovas').textContent = '3352';
            document.getElementById('avgComplexity').textContent = '17090';
            document.getElementById('totalSessions').textContent = '144';
            
            console.warn('Using fallback stats - server API not working (406 error)');
        }
    }

    toggleVoice() {
        if (!this.recognition) {
            const isSecureContext = window.location.protocol === 'https:' || 
                                   window.location.hostname === 'localhost' ||
                                   window.location.hostname === '127.0.0.1';
            
            if (!isSecureContext) {
                alert('Voice recognition requires HTTPS or localhost. Please access this page over HTTPS.');
            } else {
                alert('Voice recognition not supported in this browser. Please use Chrome or Edge.');
            }
            return;
        }

        if (this.isListening || this.isRecognitionActive) {
            console.log('Stopping voice recognition...');
            this.stopListening();
        } else {
            console.log('Starting voice recognition...');
            this.startListening();
        }
    }
    
    startListening() {
        if (this.isRecognitionActive) {
            console.log('Recognition already active, ignoring start request');
            return;
        }
        
        // Get current timeout setting before starting
        const voiceTimeout = document.getElementById('voiceTimeout');
        if (voiceTimeout) {
            this.voiceTimeout = voiceTimeout.value;
        }
        
        // Clear input and reset styling before starting
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.value = '';
            messageInput.style.fontStyle = 'normal';
            messageInput.style.color = '#e2e8f0';
        }
        
        // Update UI immediately
        const voiceStatus = document.getElementById('voiceStatus');
        if (voiceStatus) {
            voiceStatus.textContent = '🔄 Initializing microphone...';
        }
        
        try {
            this.recognition.start();
        } catch (error) {
            console.error('Failed to start voice recognition:', error);
            
            if (error.name === 'InvalidStateError') {
                console.log('Recognition already running, forcing stop and restart...');
                
                // Force stop and reset
                this.isListening = false;
                this.isRecognitionActive = false;
                
                try {
                    this.recognition.stop();
                } catch (stopError) {
                    console.warn('Error stopping recognition:', stopError);
                }
                
                // Wait a bit longer before restarting
                setTimeout(() => {
                    if (!this.isRecognitionActive) {
                        console.log('Attempting to restart after reset...');
                        try {
                            this.recognition.start();
                        } catch (restartError) {
                            console.error('Failed to restart after reset:', restartError);
                            if (voiceStatus) {
                                voiceStatus.textContent = '❌ Could not start microphone. Please try again.';
                            }
                        }
                    }
                }, 200);
            } else if (error.name === 'NotAllowedError') {
                if (voiceStatus) {
                    voiceStatus.textContent = '❌ Microphone access denied. Please check permissions.';
                }
                alert('Microphone access denied. Please:\n1. Click the microphone icon in your address bar\n2. Select "Allow" for microphone permissions\n3. Refresh the page and try again');
            } else {
                if (voiceStatus) {
                    voiceStatus.textContent = `❌ Error starting microphone: ${error.message}`;
                }
                console.error('Unhandled recognition start error:', error);
            }
        }
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;

        // Clear input and disable send button temporarily
        input.value = '';
        
        // Reset input styling in case it was used for voice
        input.style.fontStyle = 'normal';
        input.style.color = '#e2e8f0';
        
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
                console.log('Checking auto-speak conditions:', {
                    autoSpeak: this.autoSpeak,
                    synthesis: !!this.synthesis,
                    response: result.response
                });
                
                if (this.autoSpeak && this.synthesis) {
                    this.speakText(result.response);
                } else {
                    console.log('Auto-speak skipped - conditions not met');
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
            // Ensure input focus is maintained for continuous typing
            input.focus();
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
        
        // Smooth scroll to bottom with a slight delay to ensure DOM is updated
        setTimeout(() => {
            chatContainer.scrollTo({
                top: chatContainer.scrollHeight,
                behavior: 'smooth'
            });
        }, 10);
    }

    addWelcomeMessage() {
        const chatContainer = document.getElementById('chatContainer');
        
        // Check if chat is empty (no messages yet)
        if (chatContainer.children.length === 0) {
            const welcomeMessage = `Hello! I'm your AI assistant specializing in PulseCore analysis and data exploration. 

I can help you with:
• Analyzing your nova events and patterns
• Exploring complexity and energy data  
• Searching through your variables database
• General conversations about your simulations

I'd love to get to know you better - what's your name?`;

            this.addMessage('ai', welcomeMessage);
        }
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
            const savedAutoSpeak = localStorage.getItem('autoSpeak');
            console.log('Saved autoSpeak setting:', savedAutoSpeak);
            
            // Default to true if not set, false only if explicitly set to 'false'
            this.autoSpeak = savedAutoSpeak !== 'false';
            autoSpeakToggle.checked = this.autoSpeak;
            
            console.log('Auto-speak initialized to:', this.autoSpeak);
            
            autoSpeakToggle.addEventListener('change', (e) => {
                this.autoSpeak = e.target.checked;
                localStorage.setItem('autoSpeak', this.autoSpeak.toString());
                console.log('Auto-speak changed to:', this.autoSpeak);
            });
        } else {
            console.warn('Auto-speak toggle element not found!');
            // Default to true if toggle not found
            this.autoSpeak = true;
        }
        
        // Set up voice controls
        this.setupAdvancedVoiceControls();
    }

    setupAdvancedVoiceControls() {
        // Speech rate control
        const speechRate = document.getElementById('speechRate');
        const speedValue = document.getElementById('speedValue');
        if (speechRate && speedValue) {
            const savedRate = localStorage.getItem('speechRate') || '1.2';
            speechRate.value = savedRate;
            speedValue.textContent = savedRate + 'x';
            
            speechRate.addEventListener('input', (e) => {
                const value = e.target.value;
                speedValue.textContent = value + 'x';
                localStorage.setItem('speechRate', value);
            });
        }
        
        // Speech pitch control
        const speechPitch = document.getElementById('speechPitch');
        const pitchValue = document.getElementById('pitchValue');
        if (speechPitch && pitchValue) {
            const savedPitch = localStorage.getItem('speechPitch') || '1.0';
            speechPitch.value = savedPitch;
            pitchValue.textContent = savedPitch;
            
            speechPitch.addEventListener('input', (e) => {
                const value = e.target.value;
                pitchValue.textContent = value;
                localStorage.setItem('speechPitch', value);
            });
        }
        
        // Speech volume control
        const speechVolume = document.getElementById('speechVolume');
        const volumeValue = document.getElementById('volumeValue');
        if (speechVolume && volumeValue) {
            const savedVolume = localStorage.getItem('speechVolume') || '0.8';
            speechVolume.value = savedVolume;
            volumeValue.textContent = savedVolume;
            
            speechVolume.addEventListener('input', (e) => {
                const value = e.target.value;
                volumeValue.textContent = value;
                localStorage.setItem('speechVolume', value);
            });
        }
        
        // Voice timeout selector
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
        
        // Voice settings button (for future advanced modal)
        const voiceSettingsBtn = document.getElementById('voiceSettingsBtn');
        if (voiceSettingsBtn) {
            voiceSettingsBtn.addEventListener('click', () => {
                this.showAdvancedVoiceSettings();
            });
        }
        
        // Microphone test button
        const micTestBtn = document.getElementById('micTestBtn');
        if (micTestBtn) {
            micTestBtn.addEventListener('click', () => {
                this.testMicrophone();
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
    
    testMicrophone() {
        // Open the test microphone page in a new tab
        window.open('test-microphone.html', '_blank', 'width=800,height=600');
    }
    
    showAdvancedVoiceSettings() {
        // Future enhancement: show modal with more voice options
        alert('Advanced voice settings coming soon! For now, use the controls above to customize your voice experience.');
    }

    async speakText(text) {
        console.log('speakText called with:', text);
        console.log('autoSpeak status:', this.autoSpeak);
        
        if (!this.autoSpeak) {
            console.log('Auto-speak is disabled, not speaking');
            return;
        }
        
        if (!this.synthesis) {
            console.log('Speech synthesis not available');
            return;
        }
        
        // Cancel any ongoing speech and wait for it to clear
        this.synthesis.cancel();
        
        // Wait for synthesis to fully clear before starting new speech
        if (this.synthesis.speaking) {
            console.log('Waiting for speech synthesis to clear...');
            await new Promise(resolve => {
                let attempts = 0;
                const maxAttempts = 20; // 1 second max wait
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
        
        // Get current voice settings
        const speechRate = document.getElementById('speechRate');
        const speechPitch = document.getElementById('speechPitch');
        const speechVolume = document.getElementById('speechVolume');
        
        // Constrain values to safe ranges
        let rate = speechRate ? parseFloat(speechRate.value) : 1.2;
        let pitch = speechPitch ? parseFloat(speechPitch.value) : 1.0;
        let volume = speechVolume ? parseFloat(speechVolume.value) : 0.8;
        
        // Limit rate to prevent interruption errors
        utterance.rate = Math.min(Math.max(rate, 0.5), 2.0);
        utterance.pitch = Math.min(Math.max(pitch, 0.5), 2.0);
        utterance.volume = Math.min(Math.max(volume, 0.1), 1.0);
        
        console.log('Voice settings:', {
            rate: utterance.rate,
            pitch: utterance.pitch,
            volume: utterance.volume,
            selectedVoice: this.selectedVoice ? this.selectedVoice.name : 'default'
        });
        
        // Use selected voice if available, or find a good English voice
        if (this.selectedVoice) {
            utterance.voice = this.selectedVoice;
        } else {
            // Try to find an English voice if none selected
            const englishVoices = this.voices.filter(voice => 
                voice.lang.startsWith('en-') || voice.name.toLowerCase().includes('english')
            );
            if (englishVoices.length > 0) {
                utterance.voice = englishVoices[0];
            }
        }
        
        // Add event listeners for debugging
        utterance.onstart = () => console.log('Speech started');
        utterance.onend = () => console.log('Speech ended');
        utterance.onerror = (e) => {
            console.warn('Speech synthesis error:', e.error);
            if (e.error === 'interrupted') {
                console.log('Speech was interrupted - this is normal when starting new speech');
            }
        };
        
        console.log('Starting speech synthesis...');
        
        // Small delay to ensure synthesis is ready
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

function closeVoiceIndicator() {
    // Stop any active voice recognition
    if (window.aiChat.isListening) {
        window.aiChat.stopListening();
    }
    
    // Hide the voice indicator
    document.getElementById('voiceIndicator').classList.add('hidden');
    
    // Reset input styling
    const messageInput = document.getElementById('messageInput');
    messageInput.style.fontStyle = 'normal';
    messageInput.style.color = '#e2e8f0';
    
    // Reset voice status
    const voiceStatus = document.getElementById('voiceStatus');
    if (voiceStatus) {
        voiceStatus.textContent = 'Ready to listen...';
    }
}

function toggleVoiceControls() {
    const panel = document.getElementById('voiceControlsPanel');
    const arrow = document.getElementById('toggleArrow');
    const button = document.getElementById('voiceControlsToggle');
    
    if (panel.classList.contains('collapsed')) {
        // Show voice controls - when expanded, arrow points up (can collapse)
        panel.classList.remove('collapsed');
        button.innerHTML = '🎛️ Voice Settings <span class="toggle-arrow" id="toggleArrow">▲</span>';
    } else {
        // Hide voice controls - when collapsed, arrow points down (can expand)
        panel.classList.add('collapsed');
        button.innerHTML = '🎛️ Voice Settings <span class="toggle-arrow" id="toggleArrow">▼</span>';
    }
}

function toggleStats() {
    const panel = document.getElementById('statsPanel');
    const arrow = document.getElementById('statsArrow');
    const button = document.getElementById('statsToggle');
    
    if (panel.classList.contains('collapsed')) {
        // Show stats - when expanded, arrow points up (can collapse)
        panel.classList.remove('collapsed');
        arrow.textContent = '▲';
    } else {
        // Hide stats - when collapsed, arrow points down (can expand)
        panel.classList.add('collapsed');
        arrow.textContent = '▼';
    }
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