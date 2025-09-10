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
        this.journalMode = false;
        this.journalRecording = false;
        this.journalPaused = false;
        this.journalStartTime = null;
        this.journalTimer = null;
        this.journalRecognition = null;
        
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

        // Initialize Journal mode
        this.initializeJournalMode();
    }

    initializeJournalMode() {
        // Set up journal AI input enter key
        const journalAiInput = document.getElementById('journalAiInput');
        if (journalAiInput) {
            journalAiInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendJournalAiMessage();
                }
            });
        }

        // Set up Zin input enter key
        const zinInput = document.getElementById('zinQuickInput');
        if (zinInput) {
            zinInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendZinMessage();
                }
            });
        }

        // Set up journal recognition if available
        this.setupJournalVoiceRecognition();
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
            console.log('PC Bridge not available (this is normal for web-only usage):', error.message);
            this.updateConnectionStatus('pcStatus', false);
        }

        // Check Local Llama AI connection
        await this.checkLocalLlamaStatus();
    }

    async checkLocalLlamaStatus() {
        try {
            const response = await fetch('api/llama-local.php?action=status');
            const status = await response.json();
            console.log('Local Llama status:', status);
            
            if (status.status === 'online' && status.selected_model) {
                console.log('🧠 Local Llama AI is online with model:', status.selected_model);
                this.currentMode = 'full-power';
                this.updateAIMode(true, status.selected_model);
                return true;
            } else {
                throw new Error('Local Llama not available');
            }
        } catch (error) {
            console.log('Local Llama not available:', error.message);
            this.currentMode = 'chill';
            this.updateAIMode(false);
            return false;
        }
    }

    updateAIMode(hasLocalAI, modelName = null) {
        const aiModeElement = document.getElementById('aiMode');
        
        if (hasLocalAI) {
            aiModeElement.textContent = 'Full Power Mode 🧠';
            aiModeElement.className = 'mode-indicator full-power';
            aiModeElement.title = `Local AI Active (${modelName || 'Unknown Model'})`;
            console.log('🚀 Switched to Full Power Mode with local Llama');
        } else {
            aiModeElement.textContent = 'Chill Mode';
            aiModeElement.className = 'mode-indicator chill';
            aiModeElement.title = 'Using basic AI responses';
            console.log('📝 Using Chill Mode (basic responses)');
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
        const modal = document.getElementById('advancedVoiceModal');
        if (modal) {
            modal.classList.remove('hidden');
            this.initializeAdvancedSettings();
            this.runDiagnostics();
        }
    }
    
    initializeAdvancedSettings() {
        // Initialize all advanced settings
        this.setupAdvancedTabs();
        this.setupAdvancedControls();
        this.loadAdvancedSettings();
        this.setupAdvancedVoiceDropdown();
    }
    
    setupAdvancedTabs() {
        const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(`tab-${target}`).classList.add('active');
            });
        });
    }
    
    setupAdvancedControls() {
        // Recognition Language
        const recognitionLang = document.getElementById('recognitionLang');
        if (recognitionLang) {
            recognitionLang.addEventListener('change', (e) => {
                if (this.recognition) {
                    this.recognition.lang = e.target.value;
                    localStorage.setItem('recognitionLang', e.target.value);
                    this.debugLog(`Language changed to: ${e.target.value}`);
                }
            });
        }
        
        // Recognition Sensitivity
        const sensitivity = document.getElementById('recognitionSensitivity');
        const sensitivityValue = document.getElementById('sensitivityValue');
        if (sensitivity && sensitivityValue) {
            sensitivity.addEventListener('input', (e) => {
                sensitivityValue.textContent = e.target.value;
                localStorage.setItem('recognitionSensitivity', e.target.value);
            });
        }
        
        // Max Alternatives
        const maxAlternatives = document.getElementById('maxAlternatives');
        if (maxAlternatives) {
            maxAlternatives.addEventListener('change', (e) => {
                if (this.recognition) {
                    this.recognition.maxAlternatives = parseInt(e.target.value);
                    localStorage.setItem('maxAlternatives', e.target.value);
                }
            });
        }
        
        // Advanced Speech Controls
        this.setupAdvancedSpeechControls();
        
        // Behavior Settings
        this.setupBehaviorSettings();
        
        // Diagnostics
        this.setupDiagnosticButtons();
        
        // Modal Actions
        this.setupModalActions();
        
        // Preset Buttons
        this.setupPresetButtons();
    }
    
    setupAdvancedSpeechControls() {
        // Advanced speech rate
        const advancedRate = document.getElementById('advancedSpeechRate');
        const advancedSpeedValue = document.getElementById('advancedSpeedValue');
        if (advancedRate && advancedSpeedValue) {
            advancedRate.addEventListener('input', (e) => {
                advancedSpeedValue.textContent = e.target.value + 'x';
                localStorage.setItem('speechRate', e.target.value);
                // Also update the simple control
                const simpleRate = document.getElementById('speechRate');
                if (simpleRate) simpleRate.value = e.target.value;
                const simpleSpeedValue = document.getElementById('speedValue');
                if (simpleSpeedValue) simpleSpeedValue.textContent = e.target.value + 'x';
            });
        }
        
        // Advanced pitch
        const advancedPitch = document.getElementById('advancedSpeechPitch');
        const advancedPitchValue = document.getElementById('advancedPitchValue');
        if (advancedPitch && advancedPitchValue) {
            advancedPitch.addEventListener('input', (e) => {
                advancedPitchValue.textContent = e.target.value;
                localStorage.setItem('speechPitch', e.target.value);
                // Also update the simple control
                const simplePitch = document.getElementById('speechPitch');
                if (simplePitch) simplePitch.value = e.target.value;
                const simplePitchValue = document.getElementById('pitchValue');
                if (simplePitchValue) simplePitchValue.textContent = e.target.value;
            });
        }
        
        // Advanced volume
        const advancedVolume = document.getElementById('advancedSpeechVolume');
        const advancedVolumeValue = document.getElementById('advancedVolumeValue');
        if (advancedVolume && advancedVolumeValue) {
            advancedVolume.addEventListener('input', (e) => {
                advancedVolumeValue.textContent = e.target.value;
                localStorage.setItem('speechVolume', e.target.value);
                // Also update the simple control
                const simpleVolume = document.getElementById('speechVolume');
                if (simpleVolume) simpleVolume.value = e.target.value;
                const simpleVolumeValue = document.getElementById('volumeValue');
                if (simpleVolumeValue) simpleVolumeValue.textContent = e.target.value;
            });
        }
        
        // Sentence pause
        const sentencePause = document.getElementById('sentencePause');
        const pauseValue = document.getElementById('pauseValue');
        if (sentencePause && pauseValue) {
            sentencePause.addEventListener('input', (e) => {
                pauseValue.textContent = e.target.value + 'ms';
                localStorage.setItem('sentencePause', e.target.value);
            });
        }
        
        // Advanced auto-speak
        const advancedAutoSpeak = document.getElementById('advancedAutoSpeak');
        if (advancedAutoSpeak) {
            advancedAutoSpeak.addEventListener('change', (e) => {
                this.autoSpeak = e.target.checked;
                localStorage.setItem('autoSpeak', e.target.checked.toString());
                // Also update the simple control
                const simpleAutoSpeak = document.getElementById('autoSpeak');
                if (simpleAutoSpeak) simpleAutoSpeak.checked = e.target.checked;
            });
        }
    }
    
    setupPresetButtons() {
        // Rate presets
        document.querySelectorAll('.preset-btn[data-rate]').forEach(btn => {
            btn.addEventListener('click', () => {
                const rate = btn.dataset.rate;
                const advancedRate = document.getElementById('advancedSpeechRate');
                const advancedSpeedValue = document.getElementById('advancedSpeedValue');
                if (advancedRate && advancedSpeedValue) {
                    advancedRate.value = rate;
                    advancedSpeedValue.textContent = rate + 'x';
                    localStorage.setItem('speechRate', rate);
                }
            });
        });
        
        // Pitch presets
        document.querySelectorAll('.preset-btn[data-pitch]').forEach(btn => {
            btn.addEventListener('click', () => {
                const pitch = btn.dataset.pitch;
                const advancedPitch = document.getElementById('advancedSpeechPitch');
                const advancedPitchValue = document.getElementById('advancedPitchValue');
                if (advancedPitch && advancedPitchValue) {
                    advancedPitch.value = pitch;
                    advancedPitchValue.textContent = pitch;
                    localStorage.setItem('speechPitch', pitch);
                }
            });
        });
    }
    
    setupBehaviorSettings() {
        const defaultTimeout = document.getElementById('defaultVoiceTimeout');
        if (defaultTimeout) {
            defaultTimeout.addEventListener('change', (e) => {
                this.voiceTimeout = e.target.value;
                localStorage.setItem('voiceTimeout', e.target.value);
                // Update simple control too
                const simpleTimeout = document.getElementById('voiceTimeout');
                if (simpleTimeout) simpleTimeout.value = e.target.value;
            });
        }
    }
    
    setupAdvancedVoiceDropdown() {
        // Initialize the enhanced voice selector system
        this.initializeEnhancedVoiceSelector();
        
        // Legacy dropdown compatibility
        const advancedSelect = document.getElementById('advancedVoiceSelect');
        const simpleSelect = document.getElementById('voiceSelect');
        
        if (advancedSelect && simpleSelect) {
            // Copy options from simple dropdown
            advancedSelect.innerHTML = simpleSelect.innerHTML;
            advancedSelect.value = simpleSelect.value;
            
            // Sync changes
            advancedSelect.addEventListener('change', (e) => {
                simpleSelect.value = e.target.value;
                const voiceIndex = e.target.value;
                this.selectedVoice = voiceIndex ? this.voices[voiceIndex] : null;
                localStorage.setItem('selectedVoice', voiceIndex);
            });
        }
    }
    
    initializeEnhancedVoiceSelector() {
        // Initialize voice data structures
        this.filteredVoices = [...this.voices];
        this.favoriteVoices = new Set(JSON.parse(localStorage.getItem('favoriteVoices') || '[]'));
        this.voiceRatings = JSON.parse(localStorage.getItem('voiceRatings') || '{}');
        this.voiceUsageCount = JSON.parse(localStorage.getItem('voiceUsageCount') || '{}');
        
        // Populate the enhanced voice grid
        this.populateVoicesGrid();
        this.populateLanguageFilter();
        this.setupVoiceFiltering();
        this.setupVoiceTestingFeatures();
        
        // Update selected voice display
        this.updateSelectedVoiceDisplay();
    }
    
    populateVoicesGrid() {
        const grid = document.getElementById('voicesGrid');
        if (!grid || this.voices.length === 0) {
            if (grid) grid.innerHTML = '<div class="loading-voices">No voices available</div>';
            return;
        }
        
        grid.innerHTML = '';
        
        if (this.filteredVoices.length === 0) {
            grid.innerHTML = `
                <div class="no-voices-found">
                    <div class="no-voices-found-icon">🎭</div>
                    <div>No voices match your current filters</div>
                    <button onclick="window.aiChat.clearAllFilters()" class="btn-small" style="margin-top: 10px;">Clear Filters</button>
                </div>
            `;
            return;
        }
        
        // Sort voices by priority: favorites first, then by usage, then by quality
        const sortedVoices = this.filteredVoices.sort((a, b) => {
            const aFav = this.favoriteVoices.has(a.voiceURI) ? 1000 : 0;
            const bFav = this.favoriteVoices.has(b.voiceURI) ? 1000 : 0;
            const aUsage = this.voiceUsageCount[a.voiceURI] || 0;
            const bUsage = this.voiceUsageCount[b.voiceURI] || 0;
            const aQuality = this.getVoiceQuality(a);
            const bQuality = this.getVoiceQuality(b);
            
            return (bFav + bUsage + aQuality) - (aFav + aUsage + bQuality);
        });
        
        sortedVoices.forEach((voice, index) => {
            const voiceCard = this.createVoiceCard(voice, index);
            grid.appendChild(voiceCard);
        });
        
        // Add stats footer
        const stats = document.createElement('div');
        stats.className = 'voice-stats';
        stats.innerHTML = `
            <span>Showing ${this.filteredVoices.length} of ${this.voices.length} voices</span>
            <span>${this.favoriteVoices.size} favorites</span>
        `;
        grid.appendChild(stats);
    }
    
    createVoiceCard(voice, index) {
        const card = document.createElement('div');
        card.className = 'voice-card';
        card.dataset.voiceIndex = index;
        
        const isSelected = this.selectedVoice && this.selectedVoice.voiceURI === voice.voiceURI;
        const isFavorited = this.favoriteVoices.has(voice.voiceURI);
        
        if (isSelected) card.classList.add('selected');
        
        const quality = this.getVoiceQuality(voice);
        const gender = this.getVoiceGender(voice);
        const flag = this.getLanguageFlag(voice.lang);
        
        card.innerHTML = `
            <div class="voice-info">
                <div class="voice-name">${voice.name}</div>
                <div class="voice-details">
                    <div class="voice-language">
                        ${flag} ${voice.lang}
                    </div>
                    <div class="voice-gender">
                        ${this.getGenderIcon(gender)} ${gender}
                    </div>
                    <div class="voice-quality">
                        <span class="quality-badge quality-${quality.toLowerCase()}">${quality}</span>
                    </div>
                </div>
            </div>
            <div class="voice-actions">
                <button class="voice-play-btn" title="Preview voice" data-voice-index="${index}">
                    ▶️
                </button>
                <button class="voice-favorite-btn ${isFavorited ? 'favorited' : ''}" title="Toggle favorite" data-voice-index="${index}">
                    ${isFavorited ? '⭐' : '☆'}
                </button>
            </div>
        `;
        
        // Add click handlers
        card.addEventListener('click', (e) => {
            if (e.target.closest('.voice-actions')) return;
            this.selectVoice(voice, index);
        });
        
        const playBtn = card.querySelector('.voice-play-btn');
        playBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.previewVoice(voice, playBtn);
        });
        
        const favBtn = card.querySelector('.voice-favorite-btn');
        favBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleVoiceFavorite(voice, favBtn);
        });
        
        return card;
    }
    
    getVoiceQuality(voice) {
        // Enhanced quality detection based on voice characteristics
        const name = voice.name.toLowerCase();
        
        // Neural/AI voices (highest quality)
        if (name.includes('neural') || name.includes('wavenet') || name.includes('studio') || 
            name.includes('premium') || name.includes('enhanced') || name.includes('hd')) {
            return 'Neural';
        }
        
        // Premium voices (high quality)
        if (name.includes('pro') || name.includes('plus') || name.includes('edge') || 
            name.includes('natural') || name.includes('expressive')) {
            return 'Premium';
        }
        
        // Standard voices
        return 'Standard';
    }
    
    getVoiceGender(voice) {
        const name = voice.name.toLowerCase();
        
        // Common female voice indicators
        if (name.includes('female') || name.includes('woman') || name.includes('ella') || 
            name.includes('emma') || name.includes('sophia') || name.includes('sarah') ||
            name.includes('anna') || name.includes('karen') || name.includes('susan') ||
            name.includes('samantha') || name.includes('victoria') || name.includes('allison') ||
            name.includes('ava') || name.includes('zoe') || name.includes('aria')) {
            return 'Female';
        }
        
        // Common male voice indicators
        if (name.includes('male') || name.includes('man') || name.includes('daniel') ||
            name.includes('alex') || name.includes('david') || name.includes('thomas') ||
            name.includes('fred') || name.includes('ryan') || name.includes('diego') ||
            name.includes('jorge') || name.includes('ricky') || name.includes('aaron')) {
            return 'Male';
        }
        
        // Neutral/Unknown
        return 'Neutral';
    }
    
    getGenderIcon(gender) {
        switch (gender.toLowerCase()) {
            case 'female': return '♀️';
            case 'male': return '♂️';
            default: return '⚧️';
        }
    }
    
    getLanguageFlag(langCode) {
        const flags = {
            'en-US': '🇺🇸', 'en-GB': '🇬🇧', 'en-AU': '🇦🇺', 'en-CA': '🇨🇦',
            'es-ES': '🇪🇸', 'es-MX': '🇲🇽', 'fr-FR': '🇫🇷', 'de-DE': '🇩🇪',
            'it-IT': '🇮🇹', 'pt-BR': '🇧🇷', 'ja-JP': '🇯🇵', 'ko-KR': '🇰🇷',
            'zh-CN': '🇨🇳', 'zh-TW': '🇹🇼', 'ar-SA': '🇸🇦', 'hi-IN': '🇮🇳',
            'ru-RU': '🇷🇺', 'nl-NL': '🇳🇱', 'sv-SE': '🇸🇪', 'da-DK': '🇩🇰',
            'no-NO': '🇳🇴', 'fi-FI': '🇫🇮', 'pl-PL': '🇵🇱', 'tr-TR': '🇹🇷'
        };
        
        return flags[langCode] || flags[langCode?.split('-')[0]] || '🌐';
    }
    
    populateLanguageFilter() {
        const languageFilter = document.getElementById('languageFilter');
        if (!languageFilter) return;
        
        const languages = new Set();
        this.voices.forEach(voice => {
            const lang = voice.lang.split('-')[0];
            languages.add(lang);
        });
        
        languageFilter.innerHTML = '<option value="">All Languages</option>';
        Array.from(languages).sort().forEach(lang => {
            const displayName = new Intl.DisplayNames(['en'], {type: 'language'}).of(lang) || lang;
            const option = document.createElement('option');
            option.value = lang;
            option.textContent = displayName;
            languageFilter.appendChild(option);
        });
    }
    
    setupVoiceFiltering() {
        const searchInput = document.getElementById('voiceSearch');
        const languageFilter = document.getElementById('languageFilter');
        const genderFilter = document.getElementById('genderFilter');
        const qualityFilter = document.getElementById('qualityFilter');
        const clearFilters = document.getElementById('clearFilters');
        
        const applyFilters = () => {
            const searchTerm = searchInput?.value.toLowerCase() || '';
            const selectedLang = languageFilter?.value || '';
            const selectedGender = genderFilter?.value || '';
            const selectedQuality = qualityFilter?.value || '';
            
            this.filteredVoices = this.voices.filter(voice => {
                const matchesSearch = !searchTerm || 
                    voice.name.toLowerCase().includes(searchTerm) ||
                    voice.lang.toLowerCase().includes(searchTerm);
                
                const matchesLang = !selectedLang || voice.lang.startsWith(selectedLang);
                const matchesGender = !selectedGender || this.getVoiceGender(voice).toLowerCase() === selectedGender;
                const matchesQuality = !selectedQuality || this.getVoiceQuality(voice).toLowerCase() === selectedQuality;
                
                return matchesSearch && matchesLang && matchesGender && matchesQuality;
            });
            
            this.populateVoicesGrid();
        };
        
        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }
        
        [languageFilter, genderFilter, qualityFilter].forEach(filter => {
            if (filter) {
                filter.addEventListener('change', applyFilters);
            }
        });
        
        if (clearFilters) {
            clearFilters.addEventListener('click', () => {
                this.clearAllFilters();
            });
        }
    }
    
    setupVoiceTestingFeatures() {
        const testSelectedBtn = document.getElementById('testSelectedVoice');
        const testRandomBtn = document.getElementById('testRandomVoice');
        const compareBtn = document.getElementById('compareVoices');
        const recommendBtn = document.getElementById('voiceRecommendations');
        const closeComparisonBtn = document.getElementById('closeComparison');
        
        if (testSelectedBtn) {
            testSelectedBtn.addEventListener('click', () => {
                this.testSelectedVoice();
            });
        }
        
        if (testRandomBtn) {
            testRandomBtn.addEventListener('click', () => {
                this.testRandomVoice();
            });
        }
        
        if (compareBtn) {
            compareBtn.addEventListener('click', () => {
                this.showVoiceComparison();
            });
        }
        
        if (recommendBtn) {
            recommendBtn.addEventListener('click', () => {
                this.showVoiceRecommendations();
            });
        }
        
        if (closeComparisonBtn) {
            closeComparisonBtn.addEventListener('click', () => {
                document.getElementById('voiceComparisonPanel').classList.add('hidden');
            });
        }
    }
    
    selectVoice(voice, index) {
        // Update selection
        this.selectedVoice = voice;
        localStorage.setItem('selectedVoice', index.toString());
        
        // Track usage
        this.voiceUsageCount[voice.voiceURI] = (this.voiceUsageCount[voice.voiceURI] || 0) + 1;
        localStorage.setItem('voiceUsageCount', JSON.stringify(this.voiceUsageCount));
        
        // Update UI
        document.querySelectorAll('.voice-card').forEach(card => card.classList.remove('selected'));
        document.querySelector(`[data-voice-index="${index}"]`)?.classList.add('selected');
        
        this.updateSelectedVoiceDisplay();
        this.debugLog(`Voice selected: ${voice.name} (${voice.lang})`);
        
        // Sync with legacy dropdowns
        const simpleSelect = document.getElementById('voiceSelect');
        const advancedSelect = document.getElementById('advancedVoiceSelect');
        if (simpleSelect) simpleSelect.value = index;
        if (advancedSelect) advancedSelect.value = index;
    }
    
    updateSelectedVoiceDisplay() {
        const display = document.getElementById('selectedVoiceDisplay');
        if (!display) return;
        
        if (!this.selectedVoice) {
            display.innerHTML = `
                <div class="no-voice-selected">
                    <span>🎭 No voice selected - using browser default</span>
                </div>
            `;
            return;
        }
        
        const quality = this.getVoiceQuality(this.selectedVoice);
        const gender = this.getVoiceGender(this.selectedVoice);
        const flag = this.getLanguageFlag(this.selectedVoice.lang);
        const isFavorited = this.favoriteVoices.has(this.selectedVoice.voiceURI);
        
        display.innerHTML = `
            <div class="selected-voice-info">
                <div class="selected-voice-details">
                    <div class="selected-voice-name">${this.selectedVoice.name}</div>
                    <div class="selected-voice-meta">
                        <span>${flag} ${this.selectedVoice.lang}</span>
                        <span>${this.getGenderIcon(gender)} ${gender}</span>
                        <span class="quality-badge quality-${quality.toLowerCase()}">${quality}</span>
                        ${isFavorited ? '<span>⭐ Favorite</span>' : ''}
                    </div>
                </div>
                <button onclick="window.aiChat.previewVoice(window.aiChat.selectedVoice)" class="btn-small">🎵 Preview</button>
            </div>
        `;
    }
    
    setupDiagnosticButtons() {
        const testMicBtn = document.getElementById('testMicrophoneBtn');
        if (testMicBtn) {
            testMicBtn.addEventListener('click', () => {
                this.testMicrophone();
            });
        }
        
        const testSpeechBtn = document.getElementById('testSpeechBtn');
        if (testSpeechBtn) {
            testSpeechBtn.addEventListener('click', () => {
                this.testCurrentVoiceSettings();
            });
        }
        
        const testFullCycleBtn = document.getElementById('testFullCycleBtn');
        if (testFullCycleBtn) {
            testFullCycleBtn.addEventListener('click', () => {
                this.testFullCycle();
            });
        }
        
        const calibrationBtn = document.getElementById('voiceCalibrationBtn');
        if (calibrationBtn) {
            calibrationBtn.addEventListener('click', () => {
                this.runVoiceCalibration();
            });
        }
        
        const clearDebugBtn = document.getElementById('clearDebugBtn');
        if (clearDebugBtn) {
            clearDebugBtn.addEventListener('click', () => {
                const debugConsole = document.getElementById('debugConsole');
                if (debugConsole) debugConsole.textContent = '';
            });
        }
        
        const exportDebugBtn = document.getElementById('exportDebugBtn');
        if (exportDebugBtn) {
            exportDebugBtn.addEventListener('click', () => {
                this.exportDebugLog();
            });
        }
    }
    
    setupModalActions() {
        const resetBtn = document.getElementById('resetVoiceSettings');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetVoiceSettings();
            });
        }
        
        const saveBtn = document.getElementById('saveVoiceSettings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveVoiceSettings();
            });
        }
        
        const exportBtn = document.getElementById('exportVoiceSettings');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportVoiceSettings();
            });
        }
        
        const importBtn = document.getElementById('importVoiceSettings');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                this.importVoiceSettings();
            });
        }
    }
    
    loadAdvancedSettings() {
        // Load all saved settings into the advanced modal
        const settings = {
            recognitionLang: localStorage.getItem('recognitionLang') || 'en-US',
            recognitionSensitivity: localStorage.getItem('recognitionSensitivity') || '0.7',
            maxAlternatives: localStorage.getItem('maxAlternatives') || '3',
            speechRate: localStorage.getItem('speechRate') || '1.2',
            speechPitch: localStorage.getItem('speechPitch') || '1.0',
            speechVolume: localStorage.getItem('speechVolume') || '0.8',
            sentencePause: localStorage.getItem('sentencePause') || '500',
            voiceTimeout: localStorage.getItem('voiceTimeout') || 'normal',
            autoSpeak: localStorage.getItem('autoSpeak') !== 'false'
        };
        
        Object.entries(settings).forEach(([key, value]) => {
            const element = document.getElementById(key) || document.getElementById(`advanced${key.charAt(0).toUpperCase() + key.slice(1)}`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = value === true || value === 'true';
                } else {
                    element.value = value;
                }
                
                // Update corresponding value displays
                const valueDisplay = document.getElementById(key.replace(/([A-Z])/g, '$1').toLowerCase() + 'Value') ||
                                   document.getElementById('advanced' + key.charAt(0).toUpperCase() + key.slice(1) + 'Value');
                if (valueDisplay) {
                    if (key.includes('Rate')) valueDisplay.textContent = value + 'x';
                    else if (key.includes('Pause')) valueDisplay.textContent = value + 'ms';
                    else valueDisplay.textContent = value;
                }
            }
        });
    }
    
    async runDiagnostics() {
        const statusElements = {
            browserSupportStatus: document.getElementById('browserSupportStatus'),
            micAccessStatus: document.getElementById('micAccessStatus'),
            speechSynthStatus: document.getElementById('speechSynthStatus'),
            networkStatus: document.getElementById('networkStatus')
        };
        
        // Browser Support Check
        if (statusElements.browserSupportStatus) {
            const hasRecognition = 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
            const hasSynthesis = 'speechSynthesis' in window;
            const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost';
            
            if (hasRecognition && hasSynthesis && isSecure) {
                statusElements.browserSupportStatus.textContent = '✅ Full Support';
                statusElements.browserSupportStatus.style.color = '#22c55e';
            } else {
                statusElements.browserSupportStatus.textContent = '❌ Limited Support';
                statusElements.browserSupportStatus.style.color = '#ef4444';
            }
        }
        
        // Microphone Access Check
        if (statusElements.micAccessStatus) {
            try {
                if (navigator.permissions) {
                    const result = await navigator.permissions.query({ name: 'microphone' });
                    if (result.state === 'granted') {
                        statusElements.micAccessStatus.textContent = '✅ Granted';
                        statusElements.micAccessStatus.style.color = '#22c55e';
                    } else {
                        statusElements.micAccessStatus.textContent = '❌ ' + result.state;
                        statusElements.micAccessStatus.style.color = '#ef4444';
                    }
                } else {
                    statusElements.micAccessStatus.textContent = '? Unknown';
                    statusElements.micAccessStatus.style.color = '#eab308';
                }
            } catch (error) {
                statusElements.micAccessStatus.textContent = '❌ Error';
                statusElements.micAccessStatus.style.color = '#ef4444';
            }
        }
        
        // Speech Synthesis Check
        if (statusElements.speechSynthStatus) {
            if (this.synthesis) {
                const voiceCount = this.voices.length;
                statusElements.speechSynthStatus.textContent = `✅ ${voiceCount} voices`;
                statusElements.speechSynthStatus.style.color = '#22c55e';
            } else {
                statusElements.speechSynthStatus.textContent = '❌ Not Available';
                statusElements.speechSynthStatus.style.color = '#ef4444';
            }
        }
        
        // Network Status Check
        if (statusElements.networkStatus) {
            if (navigator.onLine) {
                statusElements.networkStatus.textContent = '✅ Online';
                statusElements.networkStatus.style.color = '#22c55e';
            } else {
                statusElements.networkStatus.textContent = '❌ Offline';
                statusElements.networkStatus.style.color = '#ef4444';
            }
        }
        
        // Update metrics
        this.updateMetrics();
    }
    
    updateMetrics() {
        const sessionCount = localStorage.getItem('voiceSessionCount') || '0';
        document.getElementById('voiceSessionCount').textContent = sessionCount;
        
        const avgTime = localStorage.getItem('avgResponseTime') || '-';
        document.getElementById('avgResponseTime').textContent = avgTime;
        
        const accuracy = localStorage.getItem('recognitionAccuracy') || '-';
        document.getElementById('recognitionAccuracy').textContent = accuracy;
    }
    
    testFullCycle() {
        this.debugLog('Starting full voice cycle test...');
        
        // Test speech first
        this.speakText('Starting full voice test. Please say "test successful" when I finish speaking.');
        
        // Then start listening after a delay
        setTimeout(() => {
            if (this.recognition) {
                this.debugLog('Starting voice recognition for test...');
                this.startListening();
            }
        }, 3000);
    }
    
    runVoiceCalibration() {
        this.debugLog('Starting voice calibration...');
        alert('Voice calibration will guide you through optimizing your microphone settings. This feature is coming soon!');
    }
    
    debugLog(message) {
        const debugConsole = document.getElementById('debugConsole');
        if (debugConsole) {
            const timestamp = new Date().toLocaleTimeString();
            debugConsole.textContent += `[${timestamp}] ${message}\n`;
            debugConsole.scrollTop = debugConsole.scrollHeight;
        }
        console.log(`[Voice Debug] ${message}`);
    }
    
    exportDebugLog() {
        const debugConsole = document.getElementById('debugConsole');
        if (debugConsole) {
            const content = debugConsole.textContent;
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `voice-debug-${Date.now()}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }
    }
    
    resetVoiceSettings() {
        if (confirm('Reset all voice settings to defaults? This will clear all your customizations.')) {
            // Clear localStorage
            const voiceKeys = [
                'recognitionLang', 'recognitionSensitivity', 'maxAlternatives',
                'speechRate', 'speechPitch', 'speechVolume', 'sentencePause',
                'voiceTimeout', 'autoSpeak', 'selectedVoice'
            ];
            voiceKeys.forEach(key => localStorage.removeItem(key));
            
            // Reload settings
            this.loadAdvancedSettings();
            this.setupAdvancedVoiceControls();
            
            this.debugLog('Voice settings reset to defaults');
            alert('Voice settings have been reset to defaults.');
        }
    }
    
    saveVoiceSettings() {
        this.debugLog('Voice settings saved to browser storage');
        alert('Voice settings have been saved!');
    }
    
    exportVoiceSettings() {
        const settings = {
            recognitionLang: localStorage.getItem('recognitionLang'),
            recognitionSensitivity: localStorage.getItem('recognitionSensitivity'),
            maxAlternatives: localStorage.getItem('maxAlternatives'),
            speechRate: localStorage.getItem('speechRate'),
            speechPitch: localStorage.getItem('speechPitch'),
            speechVolume: localStorage.getItem('speechVolume'),
            sentencePause: localStorage.getItem('sentencePause'),
            voiceTimeout: localStorage.getItem('voiceTimeout'),
            autoSpeak: localStorage.getItem('autoSpeak'),
            selectedVoice: localStorage.getItem('selectedVoice')
        };
        
        const blob = new Blob([JSON.stringify(settings, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `voice-settings-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
        
        this.debugLog('Voice settings exported to file');
    }
    
    importVoiceSettings() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const settings = JSON.parse(e.target.result);
                        
                        // Apply imported settings
                        Object.entries(settings).forEach(([key, value]) => {
                            if (value !== null) {
                                localStorage.setItem(key, value);
                            }
                        });
                        
                        // Reload settings
                        this.loadAdvancedSettings();
                        this.setupAdvancedVoiceControls();
                        
                        this.debugLog('Voice settings imported from file');
                        alert('Voice settings have been imported successfully!');
                    } catch (error) {
                        alert('Error importing settings: Invalid file format');
                        this.debugLog('Error importing settings: ' + error.message);
                    }
                };
                reader.readAsText(file);
            }
        };
        input.click();
    }
    
    // Enhanced Voice Selector Methods
    
    previewVoice(voice, buttonElement) {
        if (!voice) voice = this.selectedVoice;
        if (!voice) {
            alert('No voice selected to preview');
            return;
        }
        
        const testText = document.getElementById('voiceTestText')?.value || 
                        'Hello! This is a preview of this voice. How do I sound to you?';
        
        // Update button state
        if (buttonElement) {
            buttonElement.classList.add('playing');
            buttonElement.textContent = '⏹️';
        }
        
        // Stop any current speech
        if (this.synthesis) {
            this.synthesis.cancel();
        }
        
        // Create utterance with voice
        const utterance = new SpeechSynthesisUtterance(testText);
        utterance.voice = voice;
        
        // Apply current settings
        utterance.rate = parseFloat(document.getElementById('advancedSpeechRate')?.value || 1.2);
        utterance.pitch = parseFloat(document.getElementById('advancedSpeechPitch')?.value || 1.0);
        utterance.volume = parseFloat(document.getElementById('advancedSpeechVolume')?.value || 0.8);
        
        utterance.onend = () => {
            if (buttonElement) {
                buttonElement.classList.remove('playing');
                buttonElement.textContent = '▶️';
            }
        };
        
        utterance.onerror = (e) => {
            console.error('Voice preview error:', e);
            if (buttonElement) {
                buttonElement.classList.remove('playing');
                buttonElement.textContent = '▶️';
            }
        };
        
        if (this.synthesis) {
            this.synthesis.speak(utterance);
            this.debugLog(`Previewing voice: ${voice.name}`);
        }
    }
    
    toggleVoiceFavorite(voice, buttonElement) {
        const voiceURI = voice.voiceURI;
        
        if (this.favoriteVoices.has(voiceURI)) {
            this.favoriteVoices.delete(voiceURI);
            buttonElement.classList.remove('favorited');
            buttonElement.textContent = '☆';
            this.debugLog(`Removed ${voice.name} from favorites`);
        } else {
            this.favoriteVoices.add(voiceURI);
            buttonElement.classList.add('favorited');
            buttonElement.textContent = '⭐';
            this.debugLog(`Added ${voice.name} to favorites`);
        }
        
        // Save to localStorage
        localStorage.setItem('favoriteVoices', JSON.stringify(Array.from(this.favoriteVoices)));
        
        // Update display and re-sort
        this.updateSelectedVoiceDisplay();
        this.populateVoicesGrid();
    }
    
    testSelectedVoice() {
        if (!this.selectedVoice) {
            alert('Please select a voice first');
            return;
        }
        
        this.previewVoice(this.selectedVoice);
    }
    
    testRandomVoice() {
        if (this.filteredVoices.length === 0) {
            alert('No voices available to test');
            return;
        }
        
        const randomVoice = this.filteredVoices[Math.floor(Math.random() * this.filteredVoices.length)];
        this.previewVoice(randomVoice);
        this.debugLog(`Testing random voice: ${randomVoice.name}`);
    }
    
    showVoiceComparison() {
        const panel = document.getElementById('voiceComparisonPanel');
        const container = document.getElementById('comparisonVoices');
        if (!panel || !container) return;
        
        // Get top 3 voices (favorites + most used + highest quality)
        const topVoices = this.getTopVoices(3);
        
        if (topVoices.length === 0) {
            alert('No voices available for comparison');
            return;
        }
        
        container.innerHTML = '';
        
        topVoices.forEach((voice, index) => {
            const quality = this.getVoiceQuality(voice);
            const gender = this.getVoiceGender(voice);
            const flag = this.getLanguageFlag(voice.lang);
            const isFavorited = this.favoriteVoices.has(voice.voiceURI);
            
            const card = document.createElement('div');
            card.className = 'comparison-voice-card';
            card.innerHTML = `
                <div class="comparison-voice-name">${voice.name}</div>
                <div class="comparison-voice-details">
                    ${flag} ${voice.lang} • ${this.getGenderIcon(gender)} ${gender}
                    <br>
                    <span class="quality-badge quality-${quality.toLowerCase()}">${quality}</span>
                    ${isFavorited ? ' • ⭐ Favorite' : ''}
                </div>
                <button class="comparison-play-btn" onclick="window.aiChat.previewVoice(window.aiChat.voices[${this.voices.indexOf(voice)}], this)">
                    🎵 Test Voice ${index + 1}
                </button>
            `;
            container.appendChild(card);
        });
        
        panel.classList.remove('hidden');
        this.debugLog(`Showing comparison of ${topVoices.length} voices`);
    }
    
    showVoiceRecommendations() {
        // Analyze user preferences and recommend voices
        const recommendations = this.getVoiceRecommendations();
        
        if (recommendations.length === 0) {
            alert('No specific recommendations available yet. Try using more voices to get personalized suggestions!');
            return;
        }
        
        let message = '🌟 Recommended voices based on your preferences:\n\n';
        recommendations.slice(0, 5).forEach((rec, index) => {
            message += `${index + 1}. ${rec.voice.name} (${rec.voice.lang})\n`;
            message += `   Reason: ${rec.reason}\n\n`;
        });
        
        alert(message);
        this.debugLog(`Generated ${recommendations.length} voice recommendations`);
    }
    
    getTopVoices(count = 3) {
        if (this.voices.length === 0) return [];
        
        // Score voices based on multiple factors
        const scoredVoices = this.voices.map(voice => {
            let score = 0;
            
            // Favorite voices get high score
            if (this.favoriteVoices.has(voice.voiceURI)) score += 100;
            
            // Usage count
            score += (this.voiceUsageCount[voice.voiceURI] || 0) * 10;
            
            // Quality bonus
            const quality = this.getVoiceQuality(voice);
            if (quality === 'Neural') score += 30;
            else if (quality === 'Premium') score += 20;
            else score += 10;
            
            // Language preference (English gets slight bonus)
            if (voice.lang.startsWith('en')) score += 5;
            
            return { voice, score };
        });
        
        // Sort by score and take top voices
        return scoredVoices
            .sort((a, b) => b.score - a.score)
            .slice(0, count)
            .map(item => item.voice);
    }
    
    getVoiceRecommendations() {
        const recommendations = [];
        
        // Analyze favorites
        if (this.favoriteVoices.size > 0) {
            const favoriteLanguages = new Set();
            const favoriteGenders = new Set();
            
            this.voices.forEach(voice => {
                if (this.favoriteVoices.has(voice.voiceURI)) {
                    favoriteLanguages.add(voice.lang.split('-')[0]);
                    favoriteGenders.add(this.getVoiceGender(voice).toLowerCase());
                }
            });
            
            // Recommend similar voices
            this.voices.forEach(voice => {
                if (this.favoriteVoices.has(voice.voiceURI)) return; // Skip already favorited
                
                const lang = voice.lang.split('-')[0];
                const gender = this.getVoiceGender(voice).toLowerCase();
                
                if (favoriteLanguages.has(lang) && favoriteGenders.has(gender)) {
                    recommendations.push({
                        voice: voice,
                        reason: `Similar to your favorite ${gender} ${lang.toUpperCase()} voices`,
                        score: 90
                    });
                }
            });
        }
        
        // Recommend high-quality voices
        this.voices.forEach(voice => {
            const quality = this.getVoiceQuality(voice);
            if (quality === 'Neural' && !this.favoriteVoices.has(voice.voiceURI)) {
                recommendations.push({
                    voice: voice,
                    reason: 'High-quality neural voice with natural speech',
                    score: 80
                });
            }
        });
        
        // Recommend based on usage patterns
        const mostUsedLang = this.getMostUsedLanguage();
        if (mostUsedLang) {
            this.voices.forEach(voice => {
                if (voice.lang.startsWith(mostUsedLang) && 
                    !this.favoriteVoices.has(voice.voiceURI) &&
                    (this.voiceUsageCount[voice.voiceURI] || 0) === 0) {
                    
                    recommendations.push({
                        voice: voice,
                        reason: `Popular ${mostUsedLang.toUpperCase()} voice you haven't tried`,
                        score: 70
                    });
                }
            });
        }
        
        // Sort and deduplicate
        return recommendations
            .sort((a, b) => b.score - a.score)
            .filter((rec, index, arr) => 
                arr.findIndex(r => r.voice.voiceURI === rec.voice.voiceURI) === index
            );
    }
    
    getMostUsedLanguage() {
        const langCounts = {};
        
        Object.entries(this.voiceUsageCount).forEach(([voiceURI, count]) => {
            const voice = this.voices.find(v => v.voiceURI === voiceURI);
            if (voice) {
                const lang = voice.lang.split('-')[0];
                langCounts[lang] = (langCounts[lang] || 0) + count;
            }
        });
        
        return Object.keys(langCounts).reduce((a, b) => 
            langCounts[a] > langCounts[b] ? a : b, null
        );
    }
    
    clearAllFilters() {
        const searchInput = document.getElementById('voiceSearch');
        const languageFilter = document.getElementById('languageFilter');
        const genderFilter = document.getElementById('genderFilter');
        const qualityFilter = document.getElementById('qualityFilter');
        
        if (searchInput) searchInput.value = '';
        if (languageFilter) languageFilter.value = '';
        if (genderFilter) genderFilter.value = '';
        if (qualityFilter) qualityFilter.value = '';
        
        this.filteredVoices = [...this.voices];
        this.populateVoicesGrid();
        
        this.debugLog('All voice filters cleared');
    }

    // Journal Mode Methods
    setupJournalVoiceRecognition() {
        const isSecureContext = window.location.protocol === 'https:' || 
                               window.location.hostname === 'localhost' ||
                               window.location.hostname === '127.0.0.1';
        
        if (!isSecureContext) {
            console.warn('Journal voice recognition requires HTTPS or localhost');
            return;
        }
        
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            try {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.journalRecognition = new SpeechRecognition();
                
                this.journalRecognition.continuous = true;
                this.journalRecognition.interimResults = true;
                this.journalRecognition.lang = 'en-US';
                this.journalRecognition.maxAlternatives = 1;
                
                this.journalRecognition.onstart = () => {
                    this.journalRecording = true;
                    this.updateJournalStatus('🎤 Recording... Speak your thoughts');
                    this.updateJournalButtons();
                };
                
                this.journalRecognition.onend = () => {
                    if (this.journalRecording && !this.journalPaused) {
                        // Auto-restart if we're still supposed to be recording
                        setTimeout(() => {
                            if (this.journalRecording && this.journalRecognition) {
                                try {
                                    this.journalRecognition.start();
                                } catch (e) {
                                    console.warn('Journal recognition restart failed:', e);
                                }
                            }
                        }, 100);
                    }
                };
                
                this.journalRecognition.onresult = (event) => {
                    this.handleJournalVoiceResult(event);
                };
                
                this.journalRecognition.onerror = (event) => {
                    console.error('Journal speech recognition error:', event.error);
                    this.handleJournalVoiceError(event);
                };
                
            } catch (error) {
                console.warn('Failed to initialize journal speech recognition:', error);
                this.journalRecognition = null;
            }
        }
    }

    handleJournalVoiceResult(event) {
        let interimTranscript = '';
        let finalTranscript = '';
        
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) {
                finalTranscript += transcript + ' ';
            } else {
                interimTranscript += transcript;
            }
        }
        
        const journalText = document.getElementById('journalText');
        if (!journalText) return;
        
        if (finalTranscript) {
            // Add final transcript to journal
            const currentText = journalText.value;
            const newText = currentText + finalTranscript;
            journalText.value = newText;
            
            // Auto-scroll to bottom
            journalText.scrollTop = journalText.scrollHeight;
            
            // Save to localStorage for persistence
            localStorage.setItem('journalText', newText);
        }
        
        if (interimTranscript) {
            // Show interim results in status
            this.updateJournalStatus(`🎤 "${interimTranscript}"`);
        }
    }

    handleJournalVoiceError(event) {
        let errorMsg = '❌ Voice error: ';
        switch (event.error) {
            case 'not-allowed':
                errorMsg += 'Microphone access denied';
                break;
            case 'network':
                errorMsg += 'Network connection issue';
                break;
            case 'no-speech':
                errorMsg += 'No speech detected';
                // Continue recording for no-speech errors
                return;
            case 'audio-capture':
                errorMsg += 'Microphone not available';
                break;
            default:
                errorMsg += event.error;
        }
        
        this.updateJournalStatus(errorMsg);
        setTimeout(() => {
            if (this.journalRecording) {
                this.updateJournalStatus('🎤 Recording... Speak your thoughts');
            }
        }, 3000);
    }

    updateJournalStatus(message) {
        const status = document.getElementById('journalStatus');
        if (status) {
            status.textContent = message;
        }
    }

    updateJournalButtons() {
        const startBtn = document.getElementById('startVoiceBtn');
        const stopBtn = document.getElementById('stopVoiceBtn');
        const pauseBtn = document.getElementById('pauseVoiceBtn');
        
        if (this.journalRecording) {
            if (startBtn) startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = false;
            if (pauseBtn) pauseBtn.disabled = false;
        } else {
            if (startBtn) startBtn.disabled = false;
            if (stopBtn) stopBtn.disabled = true;
            if (pauseBtn) pauseBtn.disabled = true;
        }
    }

    updateJournalTimer() {
        if (!this.journalStartTime) return;
        
        const elapsed = Date.now() - this.journalStartTime;
        const minutes = Math.floor(elapsed / 60000);
        const seconds = Math.floor((elapsed % 60000) / 1000);
        
        const timer = document.getElementById('journalTimer');
        if (timer) {
            timer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }

    async sendJournalAiMessage() {
        const input = document.getElementById('journalAiInput');
        const responseDiv = document.getElementById('journalAiResponse');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Clear input
        input.value = '';
        
        // Add user message to response area
        const userMsg = document.createElement('div');
        userMsg.className = 'journal-ai-user-message';
        userMsg.innerHTML = `<strong>You:</strong> ${message}`;
        responseDiv.appendChild(userMsg);
        
        // Scroll to bottom
        responseDiv.scrollTop = responseDiv.scrollHeight;
        
        try {
            // Get current journal content for context
            const journalContent = document.getElementById('journalText').value;
            
            // Send to AI with journal context
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: this.sessionId + '_journal',
                    mode: 'journal',
                    journal_context: journalContent.substring(-2000) // Last 2000 chars for context
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Add AI response
                const aiMsg = document.createElement('div');
                aiMsg.className = 'journal-ai-response-message';
                aiMsg.innerHTML = `<strong>Zin:</strong> ${result.response}`;
                responseDiv.appendChild(aiMsg);
                
                // Scroll to bottom
                responseDiv.scrollTop = responseDiv.scrollHeight;
                
                // Speak response if enabled
                if (this.autoSpeak && this.synthesis) {
                    this.speakText(result.response);
                }
            } else {
                throw new Error(result.error || 'Unknown error');
            }
        } catch (error) {
            console.error('Journal AI error:', error);
            const errorMsg = document.createElement('div');
            errorMsg.className = 'journal-ai-error-message';
            errorMsg.innerHTML = `<strong>Error:</strong> Could not reach Zin. Please try again.`;
            responseDiv.appendChild(errorMsg);
        }
    }

    toggleJournalMode() {
        this.journalMode = !this.journalMode;
        
        const regularSection = document.getElementById('regularInputSection');
        const journalSection = document.getElementById('journalSection');
        const journalBtn = document.getElementById('journalModeBtn');
        const aiMode = document.getElementById('aiMode');
        const floatingChatIndicator = document.getElementById('floatingChatIndicator');
        
        if (this.journalMode) {
            // Switch to journal mode
            if (regularSection) regularSection.classList.add('hidden');
            if (journalSection) journalSection.classList.remove('hidden');
            if (journalBtn) {
                journalBtn.textContent = '💬 Chat';
                journalBtn.title = 'Switch back to Chat Mode';
            }
            if (aiMode) {
                aiMode.textContent = 'Journal Mode';
                aiMode.className = 'mode-indicator journal';
            }
            
            // Apply premium background styling
            document.body.classList.add('journal-mode');
            
            // Show floating chat indicator
            if (floatingChatIndicator) floatingChatIndicator.classList.remove('hidden');
            
            // Load saved journal text
            const savedText = localStorage.getItem('journalText');
            if (savedText) {
                const journalText = document.getElementById('journalText');
                if (journalText) {
                    journalText.value = savedText;
                    this.updateWordCount();
                }
            }
            
            // Setup word count listener
            this.setupJournalTextListeners();
            
            this.updateJournalStatus('Ready to begin your writing session');
        } else {
            // Switch back to chat mode
            if (regularSection) regularSection.classList.remove('hidden');
            if (journalSection) journalSection.classList.add('hidden');
            if (journalBtn) {
                journalBtn.textContent = '📝 Journal';
                journalBtn.title = 'Switch to Journal Mode';
            }
            if (aiMode) {
                aiMode.textContent = 'Chill Mode';
                aiMode.className = 'mode-indicator chill';
            }
            
            // Remove premium background styling
            document.body.classList.remove('journal-mode');
            
            // Hide floating chat indicator
            if (floatingChatIndicator) floatingChatIndicator.classList.add('hidden');
            
            // Close floating chat if open
            this.closeFloatingChat();
            
            // Stop any recording
            if (this.journalRecording) {
                this.stopJournalVoice();
            }
        }
    }

    setupJournalTextListeners() {
        const journalText = document.getElementById('journalText');
        if (journalText) {
            journalText.addEventListener('input', () => {
                this.updateWordCount();
                // Auto-save
                localStorage.setItem('journalText', journalText.value);
            });
        }
    }

    updateWordCount() {
        const journalText = document.getElementById('journalText');
        const wordCountEl = document.getElementById('wordCount');
        const charCountEl = document.getElementById('charCount');
        
        if (journalText && wordCountEl && charCountEl) {
            const text = journalText.value;
            const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
            const charCount = text.length;
            
            wordCountEl.textContent = `${wordCount} words`;
            charCountEl.textContent = `${charCount} characters`;
        }
    }

    // Floating Chat Methods
    toggleFloatingChat() {
        const panel = document.getElementById('floatingChatPanel');
        const indicator = document.getElementById('floatingChatIndicator');
        
        if (panel && panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            // Load AI name into floating chat
            const floatingAiName = document.getElementById('floatingAiName');
            const aiName = document.getElementById('aiName');
            if (floatingAiName && aiName) {
                floatingAiName.textContent = aiName.textContent;
            }
            
            // Setup floating chat input listener
            const floatingInput = document.getElementById('floatingMessageInput');
            if (floatingInput) {
                floatingInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendFloatingMessage();
                    }
                });
            }
        } else if (panel) {
            this.closeFloatingChat();
        }
    }

    closeFloatingChat() {
        const panel = document.getElementById('floatingChatPanel');
        if (panel) {
            panel.classList.add('hidden');
        }
    }

    async sendFloatingMessage() {
        const input = document.getElementById('floatingMessageInput');
        const container = document.getElementById('floatingChatContainer');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Clear input
        input.value = '';
        
        // Add user message
        this.addFloatingMessage('user', message);
        
        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: this.sessionId + '_floating',
                    mode: this.currentMode
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.addFloatingMessage('ai', result.response);
                
                // Speak response if enabled
                if (this.autoSpeak && this.synthesis) {
                    this.speakText(result.response);
                }
                
                // Show notification pulse
                const pulse = document.getElementById('chatNotificationPulse');
                if (pulse && document.getElementById('floatingChatPanel').classList.contains('hidden')) {
                    pulse.classList.remove('hidden');
                    setTimeout(() => pulse.classList.add('hidden'), 3000);
                }
            } else {
                this.addFloatingMessage('ai', 'Sorry, I encountered an error. Please try again.');
            }
        } catch (error) {
            console.error('Floating chat error:', error);
            this.addFloatingMessage('ai', 'Connection error. Please try again.');
        }
    }

    addFloatingMessage(type, content) {
        const container = document.getElementById('floatingChatContainer');
        if (!container) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `floating-message ${type}-message`;
        
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const aiName = document.getElementById('aiName').textContent;
        
        messageDiv.innerHTML = `
            <div class="floating-message-content">
                <strong>${type === 'user' ? 'You' : aiName}:</strong> ${content}
            </div>
            <div class="floating-message-time">${timestamp}</div>
        `;
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
    }

    // Enhanced Zin Communication
    async sendZinMessage() {
        const input = document.getElementById('zinQuickInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        input.value = '';
        
        // Add user message to Zin area
        this.addZinMessage('user', message);
        
        try {
            const journalContent = document.getElementById('journalText').value;
            
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: this.sessionId + '_journal',
                    mode: 'journal',
                    journal_context: journalContent.substring(-2000)
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.addZinMessage('ai', result.response);
                
                if (this.autoSpeak && this.synthesis) {
                    this.speakText(result.response);
                }
            } else {
                this.addZinMessage('ai', 'I\'m having trouble processing that. Could you try rephrasing?');
            }
        } catch (error) {
            console.error('Zin communication error:', error);
            this.addZinMessage('ai', 'I\'m having connectivity issues. Please try again in a moment.');
        }
    }

    addZinMessage(type, content) {
        const container = document.getElementById('zinResponses');
        if (!container) return;
        
        // Remove welcome message if it exists
        const welcome = container.querySelector('.zin-welcome-message');
        if (welcome) {
            welcome.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `zin-${type}-message`;
        messageDiv.innerHTML = `<strong>${type === 'user' ? 'You' : 'Zin'}:</strong> ${content}`;
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
    }

    // Quick Action Methods
    analyzeJournal() {
        const input = document.getElementById('zinQuickInput');
        if (input) {
            input.value = 'Analyze my writing and give me insights about the content, themes, and tone.';
            this.sendZinMessage();
        }
    }

    improveJournal() {
        const input = document.getElementById('zinQuickInput');
        if (input) {
            input.value = 'How can I improve my writing? Give me specific suggestions for clarity, flow, and impact.';
            this.sendZinMessage();
        }
    }

    continueJournal() {
        const input = document.getElementById('zinQuickInput');
        if (input) {
            input.value = 'What should I write about next? Give me prompts to continue exploring my thoughts.';
            this.sendZinMessage();
        }
    }

    startJournalVoice() {
        if (!this.journalRecognition) {
            alert('Voice recognition not available. Please ensure you\'re using HTTPS and have microphone permissions.');
            return;
        }
        
        this.journalRecording = true;
        this.journalPaused = false;
        this.journalStartTime = Date.now();
        
        // Start timer
        this.journalTimer = setInterval(() => {
            this.updateJournalTimer();
        }, 1000);
        
        try {
            this.journalRecognition.start();
            this.updateJournalButtons();
            this.updateJournalStatus('🎤 Starting recording...');
        } catch (error) {
            console.error('Failed to start journal recording:', error);
            this.updateJournalStatus('❌ Failed to start recording');
            this.journalRecording = false;
            this.updateJournalButtons();
        }
    }

    stopJournalVoice() {
        this.journalRecording = false;
        this.journalPaused = false;
        
        if (this.journalRecognition) {
            try {
                this.journalRecognition.stop();
            } catch (error) {
                console.warn('Error stopping journal recognition:', error);
            }
        }
        
        if (this.journalTimer) {
            clearInterval(this.journalTimer);
            this.journalTimer = null;
        }
        
        this.updateJournalButtons();
        this.updateJournalStatus('⏹️ Recording stopped');
        
        // Save current text
        const journalText = document.getElementById('journalText');
        if (journalText) {
            localStorage.setItem('journalText', journalText.value);
        }
    }

    pauseJournalVoice() {
        if (this.journalRecording && !this.journalPaused) {
            // Pause
            this.journalPaused = true;
            if (this.journalRecognition) {
                try {
                    this.journalRecognition.stop();
                } catch (error) {
                    console.warn('Error pausing journal recognition:', error);
                }
            }
            
            const pauseBtn = document.getElementById('pauseVoiceBtn');
            if (pauseBtn) {
                pauseBtn.innerHTML = '▶️ Resume';
                pauseBtn.title = 'Resume Voice Recording';
            }
            
            this.updateJournalStatus('⏸️ Recording paused');
        } else if (this.journalPaused) {
            // Resume
            this.journalPaused = false;
            try {
                this.journalRecognition.start();
            } catch (error) {
                console.error('Failed to resume journal recording:', error);
                this.updateJournalStatus('❌ Failed to resume recording');
                return;
            }
            
            const pauseBtn = document.getElementById('pauseVoiceBtn');
            if (pauseBtn) {
                pauseBtn.innerHTML = '⏸️ Pause';
                pauseBtn.title = 'Pause Voice Recording';
            }
            
            this.updateJournalStatus('🎤 Recording resumed');
        }
    }

    saveJournalEntry() {
        const journalText = document.getElementById('journalText');
        if (!journalText || !journalText.value.trim()) {
            alert('No journal content to save!');
            return;
        }
        
        const content = journalText.value;
        const timestamp = new Date().toISOString();
        const filename = `journal-entry-${Date.now()}.txt`;
        
        // Save to localStorage
        const savedEntries = JSON.parse(localStorage.getItem('journalEntries') || '[]');
        savedEntries.push({
            timestamp: timestamp,
            content: content,
            id: Date.now()
        });
        localStorage.setItem('journalEntries', JSON.stringify(savedEntries));
        
        // Also trigger download
        this.exportJournalEntry();
        
        alert('Journal entry saved!');
    }

    clearJournalEntry() {
        if (confirm('Clear the current journal entry? This cannot be undone.')) {
            const journalText = document.getElementById('journalText');
            if (journalText) {
                journalText.value = '';
                localStorage.removeItem('journalText');
            }
            
            // Clear AI responses
            const responseDiv = document.getElementById('journalAiResponse');
            if (responseDiv) {
                responseDiv.innerHTML = '<p class="journal-ai-welcome">Hi! I\'m Zin, your writing companion. I can help analyze your journal entries, suggest improvements, or discuss your thoughts. What would you like to explore?</p>';
            }
            
            this.updateJournalStatus('📝 Journal cleared - Ready to record');
        }
    }

    exportJournalEntry() {
        const journalText = document.getElementById('journalText');
        if (!journalText || !journalText.value.trim()) {
            alert('No journal content to export!');
            return;
        }
        
        const content = journalText.value;
        const timestamp = new Date().toLocaleString();
        const exportText = `Journal Entry - ${timestamp}\n${'='.repeat(50)}\n\n${content}\n\n${'='.repeat(50)}\nGenerated by PulseCore AI Journal Mode`;
        
        // Create and download file
        const blob = new Blob([exportText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `journal-${Date.now()}.txt`;
        a.click();
        URL.revokeObjectURL(url);
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

function closeAdvancedVoiceSettings() {
    const modal = document.getElementById('advancedVoiceModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Journal Mode Global Functions
function toggleJournalMode() {
    window.aiChat.toggleJournalMode();
}

function startJournalVoice() {
    window.aiChat.startJournalVoice();
}

function stopJournalVoice() {
    window.aiChat.stopJournalVoice();
}

function pauseJournalVoice() {
    window.aiChat.pauseJournalVoice();
}

function sendJournalAiMessage() {
    window.aiChat.sendJournalAiMessage();
}

function saveJournalEntry() {
    window.aiChat.saveJournalEntry();
}

function clearJournalEntry() {
    window.aiChat.clearJournalEntry();
}

function exportJournalEntry() {
    window.aiChat.exportJournalEntry();
}

// New Journal Interface Functions
function sendZinMessage() {
    window.aiChat.sendZinMessage();
}

function analyzeJournal() {
    window.aiChat.analyzeJournal();
}

function improveJournal() {
    window.aiChat.improveJournal();
}

function continueJournal() {
    window.aiChat.continueJournal();
}

// Floating Chat Functions
function toggleFloatingChat() {
    window.aiChat.toggleFloatingChat();
}

function closeFloatingChat() {
    window.aiChat.closeFloatingChat();
}

function sendFloatingMessage() {
    window.aiChat.sendFloatingMessage();
}

function toggleFloatingVoice() {
    // Implement floating voice functionality if needed
    console.log('Floating voice feature - to be implemented');
}

function toggleAbout() {
    const aboutBox = document.getElementById('aboutBox');
    const aboutToggle = document.getElementById('aboutToggle');
    const aboutArrow = document.getElementById('aboutArrow');
    
    if (aboutBox && aboutToggle) {
        aboutBox.classList.toggle('hidden');
        aboutToggle.classList.toggle('expanded');
        
        // Update arrow direction
        if (aboutArrow) {
            aboutArrow.textContent = aboutBox.classList.contains('hidden') ? '▼' : '▲';
        }
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