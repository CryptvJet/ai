<?php
/**
 * Local Llama API Integration
 * Handles communication with local Ollama server
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class LocalLlamaAPI {
    private $ollama_url;
    private $default_model;
    private $available_models;
    private $connection_timeout;
    
    public function __construct() {
        $this->ollama_url = 'http://localhost:11434';
        $this->default_model = 'llama3.1:8b-instruct-q4_K_M'; // fallback options will be tested
        $this->available_models = [
            'llama3.1:8b-instruct-q4_K_M',
            'llama3.1:8b',
            'llama3.2:3b-instruct-q4_K_M',
            'llama3.2:3b',
            'llama3.1:70b-instruct-q4_K_M'
        ];
        $this->connection_timeout = 5; // seconds
    }
    
    /**
     * Check if Ollama server is running and models are available
     */
    public function checkConnection() {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->ollama_url . '/api/tags',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->connection_timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connection_timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => false
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['models']) && is_array($data['models'])) {
                    $model_names = array_column($data['models'], 'name');
                    $available_model = $this->findBestAvailableModel($model_names);
                    
                    return [
                        'status' => 'online',
                        'available_models' => $model_names,
                        'selected_model' => $available_model,
                        'server_url' => $this->ollama_url
                    ];
                }
            }
            
            return ['status' => 'offline', 'error' => 'No models available'];
            
        } catch (Exception $e) {
            return ['status' => 'offline', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Find the best available model from the installed models
     */
    private function findBestAvailableModel($installed_models) {
        foreach ($this->available_models as $preferred_model) {
            if (in_array($preferred_model, $installed_models)) {
                return $preferred_model;
            }
        }
        
        // If no preferred model found, return the first available
        return !empty($installed_models) ? $installed_models[0] : null;
    }
    
    /**
     * Generate AI response using local Llama model
     */
    public function generateResponse($message, $context = [], $system_prompt = null) {
        // Check connection first
        $connection_status = $this->checkConnection();
        if ($connection_status['status'] !== 'online') {
            return [
                'success' => false,
                'error' => 'Local AI unavailable: ' . ($connection_status['error'] ?? 'Unknown error'),
                'fallback' => true
            ];
        }
        
        $model = $connection_status['selected_model'];
        
        try {
            // Build conversation context
            $messages = [];
            
            // Add system prompt
            if ($system_prompt) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $system_prompt
                ];
            } else {
                $messages[] = [
                    'role' => 'system',
                    'content' => $this->getDefaultSystemPrompt($context)
                ];
            }
            
            // Add conversation history
            if (!empty($context['conversation_history'])) {
                foreach ($context['conversation_history'] as $msg) {
                    $messages[] = [
                        'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                        'content' => $msg['content']
                    ];
                }
            }
            
            // Add current message
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];
            
            // Prepare request data
            $request_data = [
                'model' => $model,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => 0.8,
                    'top_p' => 0.9,
                    'max_tokens' => 1000
                ]
            ];
            
            // Make API call
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->ollama_url . '/api/chat',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($request_data),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 60, // Longer timeout for AI generation
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception('Curl error: ' . $curl_error);
            }
            
            if ($http_code !== 200) {
                throw new Exception('API returned HTTP ' . $http_code);
            }
            
            $data = json_decode($response, true);
            if (!$data || !isset($data['message']['content'])) {
                throw new Exception('Invalid response format from Ollama');
            }
            
            return [
                'success' => true,
                'response' => trim($data['message']['content']),
                'model' => $model,
                'context' => $context
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'AI generation failed: ' . $e->getMessage(),
                'fallback' => true
            ];
        }
    }
    
    /**
     * Generate contextual system prompt based on available data
     */
    private function getDefaultSystemPrompt($context = []) {
        $prompt = "You are Zin, an intelligent AI assistant integrated with a PulseCore analysis system. ";
        $prompt .= "You have access to user data including nova events, complexity analysis, and system performance metrics. ";
        $prompt .= "Be helpful, conversational, and insightful. ";
        
        // Add context-specific instructions
        if (!empty($context['mode'])) {
            switch ($context['mode']) {
                case 'journal':
                    $prompt .= "You are currently in journal mode, helping the user analyze and improve their writing. ";
                    $prompt .= "Provide thoughtful feedback, identify patterns, and suggest improvements. ";
                    break;
                case 'pc_analysis':
                    $prompt .= "You are analyzing PC performance data. Focus on system health, performance insights, and recommendations. ";
                    break;
                case 'pulsecore':
                    $prompt .= "You are analyzing PulseCore simulation data. Focus on patterns, complexity, and scientific insights. ";
                    break;
            }
        }
        
        // Add available data context
        if (!empty($context['user_name'])) {
            $prompt .= "The user's name is " . $context['user_name'] . ". ";
        }
        
        if (!empty($context['pc_status'])) {
            $prompt .= "Current PC status: online with recent performance data available. ";
        }
        
        $prompt .= "Keep responses conversational and engaging. Use emojis occasionally but not excessively.";
        
        return $prompt;
    }
    
    /**
     * Get available models from Ollama
     */
    public function getAvailableModels() {
        $connection_status = $this->checkConnection();
        if ($connection_status['status'] === 'online') {
            return $connection_status['available_models'];
        }
        return [];
    }
    
    /**
     * Test model performance with a simple prompt
     */
    public function testModel($model_name = null) {
        $model = $model_name ?: $this->default_model;
        $test_message = "Hello! Can you tell me your name and capabilities in one sentence?";
        
        $start_time = microtime(true);
        $result = $this->generateResponse($test_message, ['mode' => 'test'], "You are Zin, an AI assistant. Respond briefly and helpfully.");
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        if ($result['success']) {
            return [
                'success' => true,
                'model' => $model,
                'response' => $result['response'],
                'response_time_ms' => $response_time
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'response_time_ms' => $response_time
            ];
        }
    }
}

// Handle API requests
$llama = new LocalLlamaAPI();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Connection check or model list
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            echo json_encode($llama->checkConnection());
            break;
        case 'models':
            echo json_encode(['models' => $llama->getAvailableModels()]);
            break;
        case 'test':
            echo json_encode($llama->testModel($_GET['model'] ?? null));
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chat request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['message'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit;
    }
    
    $message = $input['message'];
    $context = $input['context'] ?? [];
    $system_prompt = $input['system_prompt'] ?? null;
    
    echo json_encode($llama->generateResponse($message, $context, $system_prompt));
    
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>