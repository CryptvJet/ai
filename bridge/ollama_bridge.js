/**
 * Ollama Bridge - Handles communication between web AI and local Ollama server
 * This runs on your PC and provides the "superpower" connection to your web AI
 */

const express = require('express');
const cors = require('cors');
const axios = require('axios');
const mysql = require('mysql2/promise');

class OllamaBridge {
    constructor() {
        this.app = express();
        this.port = 3001;
        this.ollamaUrl = 'http://localhost:11434';
        this.webServerUrl = 'https://pulsecore.one';
        this.dbConfig = {
            host: 'localhost',
            user: 'vemite5_p-core',
            password: 'HHsJgdR6$ZMpV#F*',
            database: 'vemite5_pulse-core'
        };
        this.isConnected = false;
        this.availableModels = [];
        this.preferredModel = null;
        
        this.setupMiddleware();
        this.setupRoutes();
        this.initializeOllama();
    }

    setupMiddleware() {
        this.app.use(cors({
            origin: ['https://pulsecore.one', 'http://localhost:8080'],
            credentials: true
        }));
        this.app.use(express.json({ limit: '10mb' }));
        this.app.use(express.urlencoded({ extended: true }));
    }

    setupRoutes() {
        // Health check endpoint
        this.app.get('/health', (req, res) => {
            res.json({
                status: 'healthy',
                ollama_connected: this.isConnected,
                available_models: this.availableModels,
                preferred_model: this.preferredModel,
                timestamp: new Date().toISOString()
            });
        });

        // Ollama status endpoint
        this.app.get('/ollama/status', async (req, res) => {
            try {
                const status = await this.checkOllamaStatus();
                res.json(status);
            } catch (error) {
                res.status(500).json({
                    success: false,
                    error: 'Failed to check Ollama status',
                    details: error.message
                });
            }
        });

        // Chat completion endpoint
        this.app.post('/ollama/chat', async (req, res) => {
            try {
                const { message, session_id, context, model } = req.body;
                
                if (!this.isConnected) {
                    return res.status(503).json({
                        success: false,
                        error: 'Ollama not connected'
                    });
                }

                const response = await this.generateResponse(message, session_id, context, model);
                res.json(response);
                
            } catch (error) {
                console.error('Chat completion error:', error);
                res.status(500).json({
                    success: false,
                    error: 'Chat completion failed',
                    details: error.message
                });
            }
        });

        // Training endpoints
        this.app.post('/ollama/train', async (req, res) => {
            try {
                const { dataset_id, config } = req.body;
                
                // This will be expanded for actual training implementation
                res.json({
                    success: true,
                    message: 'Training pipeline not yet implemented',
                    job_id: `training_${Date.now()}`,
                    status: 'pending'
                });
                
            } catch (error) {
                res.status(500).json({
                    success: false,
                    error: 'Training initiation failed',
                    details: error.message
                });
            }
        });

        // Model management
        this.app.get('/ollama/models', async (req, res) => {
            try {
                const models = await this.getAvailableModels();
                res.json({
                    success: true,
                    models: models,
                    preferred: this.preferredModel
                });
            } catch (error) {
                res.status(500).json({
                    success: false,
                    error: 'Failed to get models',
                    details: error.message
                });
            }
        });

        this.app.post('/ollama/model/set-preferred', async (req, res) => {
            try {
                const { model } = req.body;
                
                if (!this.availableModels.find(m => m.name === model)) {
                    return res.status(400).json({
                        success: false,
                        error: 'Model not available'
                    });
                }

                this.preferredModel = model;
                res.json({
                    success: true,
                    preferred_model: this.preferredModel
                });
                
            } catch (error) {
                res.status(500).json({
                    success: false,
                    error: 'Failed to set preferred model',
                    details: error.message
                });
            }
        });
    }

    async initializeOllama() {
        console.log('🚀 Initializing Ollama Bridge...');
        
        try {
            // Check if Ollama is running
            await this.checkOllamaStatus();
            
            // Get available models
            await this.getAvailableModels();
            
            // Set default preferred model if none set
            if (!this.preferredModel && this.availableModels.length > 0) {
                // Prefer models in this order: llama3.1, llama3, llama2, first available
                const preferredOrder = ['llama3.1:latest', 'llama3:latest', 'llama2:latest'];
                
                for (const preferred of preferredOrder) {
                    if (this.availableModels.find(m => m.name === preferred)) {
                        this.preferredModel = preferred;
                        break;
                    }
                }
                
                if (!this.preferredModel) {
                    this.preferredModel = this.availableModels[0].name;
                }
            }
            
            console.log(`✅ Ollama initialized successfully`);
            console.log(`📋 Available models: ${this.availableModels.map(m => m.name).join(', ')}`);
            console.log(`⭐ Preferred model: ${this.preferredModel}`);
            
            // Start periodic status updates to web server
            this.startStatusUpdates();
            
        } catch (error) {
            console.error('❌ Ollama initialization failed:', error.message);
            console.log('🔄 Will retry connection periodically...');
            
            // Retry every 30 seconds
            setTimeout(() => this.initializeOllama(), 30000);
        }
    }

    async checkOllamaStatus() {
        try {
            const response = await axios.get(`${this.ollamaUrl}/api/tags`, {
                timeout: 5000
            });
            
            this.isConnected = true;
            return {
                success: true,
                status: 'online',
                version: response.data?.version || 'unknown',
                models_count: response.data?.models?.length || 0
            };
            
        } catch (error) {
            this.isConnected = false;
            return {
                success: false,
                status: 'offline',
                error: error.message
            };
        }
    }

    async getAvailableModels() {
        try {
            const response = await axios.get(`${this.ollamaUrl}/api/tags`, {
                timeout: 10000
            });
            
            this.availableModels = response.data.models.map(model => ({
                name: model.name,
                size: model.size,
                modified: model.modified_at,
                digest: model.digest
            }));
            
            return this.availableModels;
            
        } catch (error) {
            console.error('Failed to get Ollama models:', error.message);
            this.availableModels = [];
            return [];
        }
    }

    async generateResponse(message, sessionId, context = null, modelOverride = null) {
        const startTime = Date.now();
        const model = modelOverride || this.preferredModel;
        
        if (!model) {
            throw new Error('No model available for generation');
        }

        try {
            // Build enhanced prompt with context
            const enhancedPrompt = await this.buildEnhancedPrompt(message, sessionId, context);
            
            console.log(`🧠 Generating response with ${model}...`);
            
            const response = await axios.post(`${this.ollamaUrl}/api/generate`, {
                model: model,
                prompt: enhancedPrompt,
                stream: false,
                options: {
                    temperature: 0.7,
                    top_p: 0.9,
                    top_k: 40
                }
            }, {
                timeout: 60000 // 60 second timeout
            });

            const responseTime = Date.now() - startTime;
            console.log(`✅ Response generated in ${responseTime}ms`);

            return {
                success: true,
                response: response.data.response.trim(),
                model: model,
                context_used: context ? Object.keys(context).join(', ') : 'none',
                processing_time_ms: responseTime,
                token_count: response.data.eval_count || 0,
                ai_source: 'local_ollama'
            };

        } catch (error) {
            console.error(`❌ Generation failed:`, error.message);
            throw new Error(`Ollama generation failed: ${error.message}`);
        }
    }

    async buildEnhancedPrompt(message, sessionId, context) {
        let prompt = `You are Zin, an advanced AI assistant with access to the user's personal data and preferences. You are powered by PulseCore technology and can provide personalized, context-aware responses.

Current context: You are running on the user's personal computer with enhanced capabilities, providing more detailed and personalized responses than the web-based version.

`;

        // Add PulseCore context if available
        if (context && context.pulsecore_data) {
            prompt += `PulseCore Data Context:
- Total Novas: ${context.pulsecore_data.total_novas || 0}
- Total Climaxes: ${context.pulsecore_data.total_climaxes || 0}
- Average Complexity: ${context.pulsecore_data.avg_complexity || 0}

`;
        }

        // Add conversation history context
        if (sessionId) {
            try {
                const history = await this.getRecentConversationHistory(sessionId);
                if (history.length > 0) {
                    prompt += `Recent conversation history:\n`;
                    history.forEach(msg => {
                        prompt += `${msg.role}: ${msg.content}\n`;
                    });
                    prompt += `\n`;
                }
            } catch (error) {
                console.log('Could not retrieve conversation history:', error.message);
            }
        }

        prompt += `User message: ${message}

Please provide a helpful, personalized response. Since you're running on the user's PC with enhanced capabilities, you can provide more detailed analysis and insights than the basic web version.`;

        return prompt;
    }

    async getRecentConversationHistory(sessionId, limit = 5) {
        try {
            const connection = await mysql.createConnection(this.dbConfig);
            
            const [rows] = await connection.execute(`
                SELECT role, content, created_at 
                FROM ai_messages 
                WHERE session_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            `, [sessionId, limit]);
            
            await connection.end();
            
            return rows.reverse(); // Return in chronological order
            
        } catch (error) {
            console.log('Database query failed:', error.message);
            return [];
        }
    }

    startStatusUpdates() {
        // Update web server with status every 30 seconds
        setInterval(async () => {
            try {
                await this.updateWebServerStatus();
            } catch (error) {
                console.error('Status update failed:', error.message);
            }
        }, 30000);

        // Immediate status update
        this.updateWebServerStatus();
    }

    async updateWebServerStatus() {
        try {
            const status = {
                bridge_status: 'online',
                ollama_connected: this.isConnected,
                available_models: this.availableModels,
                preferred_model: this.preferredModel,
                bridge_version: '1.0.0',
                last_update: new Date().toISOString()
            };

            // This would typically update the pc_status table or call a web API
            console.log('📊 Status update:', {
                ollama: this.isConnected ? 'online' : 'offline',
                models: this.availableModels.length,
                preferred: this.preferredModel
            });

        } catch (error) {
            console.error('Failed to update web server status:', error.message);
        }
    }

    start() {
        this.app.listen(this.port, () => {
            console.log(`🌉 Ollama Bridge running on port ${this.port}`);
            console.log(`🔗 Web server: ${this.webServerUrl}`);
            console.log(`🤖 Ollama URL: ${this.ollamaUrl}`);
            console.log(`📡 Bridge endpoints available at http://localhost:${this.port}`);
        });
    }
}

// Create and start the bridge
const bridge = new OllamaBridge();
bridge.start();

module.exports = OllamaBridge;