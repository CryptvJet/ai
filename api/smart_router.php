<?php
/**
 * Smart AI Router - Intelligently routes requests between Web AI and PC Ollama
 * This is the central orchestration layer for hybrid AI capabilities
 */

require_once 'db_config.php';

class SmartAIRouter {
    private $ai_pdo;
    private $circuit_breaker_threshold = 3;
    private $fallback_timeout = 5; // seconds
    
    public function __construct() {
        global $ai_pdo;
        $this->ai_pdo = $ai_pdo;
    }
    
    /**
     * Main routing function - decides between web AI and PC AI
     */
    public function routeRequest($message, $session_id, $mode = 'auto', $context = null) {
        try {
            // 1. Check PC status and capabilities
            $pcStatus = $this->checkPCStatus();
            
            // 2. Determine optimal routing strategy
            $route = $this->determineRoute($message, $mode, $pcStatus, $context);
            
            // 3. Execute with fallback logic
            return $this->executeWithFallback($route, $message, $session_id, $mode, $context, $pcStatus);
            
        } catch (Exception $e) {
            error_log("SmartAIRouter error: " . $e->getMessage());
            return $this->createErrorResponse("Routing failed", $e->getMessage());
        }
    }
    
    /**
     * Check PC status and Ollama availability
     */
    private function checkPCStatus() {
        try {
            // Get latest PC status from database
            $stmt = $this->ai_pdo->prepare("
                SELECT 
                    system_info,
                    last_ping,
                    TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping
                FROM pc_status 
                ORDER BY last_ping DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || $result['seconds_since_ping'] > 60) {
                return [
                    'available' => false,
                    'reason' => 'PC offline or not responding',
                    'load' => 100,
                    'ollama_status' => 'unknown'
                ];
            }
            
            $systemInfo = json_decode($result['system_info'], true);
            $memoryUsage = (($systemInfo['total_memory'] - $systemInfo['free_memory']) / $systemInfo['total_memory']) * 100;
            
            // Check Ollama availability via PC bridge
            $ollamaStatus = $this->checkOllamaStatus();
            
            return [
                'available' => true,
                'load' => round($memoryUsage),
                'free_memory' => $systemInfo['free_memory'],
                'ollama_status' => $ollamaStatus['status'],
                'ollama_models' => $ollamaStatus['models'] ?? [],
                'last_ping' => $result['last_ping'],
                'system_info' => $systemInfo
            ];
            
        } catch (Exception $e) {
            error_log("PC status check failed: " . $e->getMessage());
            return [
                'available' => false,
                'reason' => 'Status check failed',
                'load' => 100,
                'ollama_status' => 'error'
            ];
        }
    }
    
    /**
     * Check Ollama server status via bridge
     */
    private function checkOllamaStatus() {
        try {
            // First try the Node.js bridge at 3001
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost:3001/ollama/status');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Quick 3 second timeout for status check
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // If Node.js bridge fails, try direct PHP test
            if ($error || $httpCode !== 200) {
                return $this->checkOllamaDirectly();
            }
            
            $data = json_decode($response, true);
            
            return [
                'status' => $data['success'] ? 'online' : 'offline',
                'models' => $data['models'] ?? [],
                'preferred_model' => $data['preferred'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'models' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Determine optimal routing strategy
     */
    private function determineRoute($message, $mode, $pcStatus, $context) {
        // Force local if mode is full-power and PC available
        if ($mode === 'full-power' && $pcStatus['available'] && $pcStatus['ollama_status'] !== 'offline') {
            return 'local';
        }
        
        // Force web if mode is chill or PC unavailable
        if ($mode === 'chill' || !$pcStatus['available']) {
            return 'web';
        }
        
        // Smart routing based on message complexity and PC load
        $complexity = $this->analyzeMessageComplexity($message);
        
        // High complexity + PC available + low load = prefer local
        if ($complexity['score'] > 0.7 && $pcStatus['available'] && $pcStatus['load'] < 70) {
            return 'local';
        }
        
        // Training-related queries = always try local first
        if ($this->isTrainingQuery($message)) {
            return $pcStatus['available'] ? 'local' : 'web_training';
        }
        
        // Default to web for simple queries or high PC load
        return 'web';
    }
    
    /**
     * Execute request with intelligent fallback
     */
    private function executeWithFallback($route, $message, $session_id, $mode, $context, $pcStatus) {
        $startTime = microtime(true);
        
        try {
            if ($route === 'local') {
                // Try PC AI first
                $response = $this->executeLocalAI($message, $session_id, $context);
                if ($response && $response['success']) {
                    $response['ai_source'] = 'local_ollama';
                    $response['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);
                    $this->recordRouting('local', 'success', $message, $response);
                    return $response;
                }
                
                // Fallback to web AI if local fails
                error_log("Local AI failed, falling back to web AI");
                $route = 'web';
            }
            
            if ($route === 'web' || $route === 'web_training') {
                $response = $this->executeWebAI($message, $session_id, $context, $route === 'web_training');
                $response['ai_source'] = 'web_ai';
                $response['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);
                $this->recordRouting('web', 'success', $message, $response);
                return $response;
            }
            
        } catch (Exception $e) {
            error_log("AI execution failed: " . $e->getMessage());
            $this->recordRouting($route, 'error', $message, ['error' => $e->getMessage()]);
            
            // Final fallback to basic response
            return $this->createFallbackResponse($message, $session_id);
        }
        
        return $this->createErrorResponse("No available AI route", "All AI services unavailable");
    }
    
    /**
     * Execute request on local PC Ollama via Bridge
     */
    private function executeLocalAI($message, $session_id, $context) {
        try {
            // Try to communicate with PC Bridge
            $bridgeUrl = 'http://localhost:3001'; // PC Bridge URL
            
            $postData = [
                'message' => $message,
                'session_id' => $session_id,
                'context' => $context,
                'model' => null // Let bridge choose preferred model
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $bridgeUrl . '/ollama/chat');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($postData))
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                error_log("Bridge communication error: " . $error);
                return null;
            }
            
            if ($httpCode !== 200) {
                error_log("Bridge returned HTTP " . $httpCode);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !$data['success']) {
                error_log("Bridge response invalid or failed: " . ($data['error'] ?? 'Unknown error'));
                return null;
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Local AI execution failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Execute request on web-based AI
     */
    private function executeWebAI($message, $session_id, $context, $isTrainingQuery = false) {
        // Enhanced web AI with PulseCore integration
        
        if ($isTrainingQuery) {
            return $this->handleTrainingQuery($message, $session_id, $context);
        }
        
        // Check for PulseCore data queries
        if ($this->isPulseCoreQuery($message)) {
            return $this->handlePulseCoreQuery($message, $session_id, $context);
        }
        
        // Default conversational AI
        return $this->handleConversationalQuery($message, $session_id, $context);
    }
    
    /**
     * Analyze message complexity for routing decisions
     */
    private function analyzeMessageComplexity($message) {
        $indicators = [
            'long_form' => strlen($message) > 200 ? 0.3 : 0,
            'technical' => preg_match('/\b(analyze|calculate|optimize|model|training|complex)\b/i', $message) ? 0.4 : 0,
            'data_request' => preg_match('/\b(show|display|chart|graph|statistics|data)\b/i', $message) ? 0.2 : 0,
            'creative' => preg_match('/\b(write|create|generate|compose|design)\b/i', $message) ? 0.3 : 0,
            'code' => preg_match('/\b(code|programming|function|algorithm|debug)\b/i', $message) ? 0.5 : 0
        ];
        
        $score = array_sum($indicators);
        
        return [
            'score' => min($score, 1.0),
            'indicators' => array_filter($indicators),
            'category' => $score > 0.7 ? 'complex' : ($score > 0.3 ? 'moderate' : 'simple')
        ];
    }
    
    /**
     * Check if message is training-related
     */
    private function isTrainingQuery($message) {
        return preg_match('/\b(train|fine-?tune|learn|adapt|teach|improve|personalize)\b/i', $message);
    }
    
    /**
     * Check if message is PulseCore data query
     */
    private function isPulseCoreQuery($message) {
        return preg_match('/\b(nova|climax|pulse|energy|complexity|session|variables)\b/i', $message);
    }
    
    /**
     * Handle training-related queries
     */
    private function handleTrainingQuery($message, $session_id, $context) {
        return [
            'success' => true,
            'response' => "I understand you want to work on training. Currently, I can provide guidance on AI training concepts. When your PC with Ollama is connected, I'll have enhanced training capabilities including fine-tuning assistance.",
            'mode' => 'training_guidance',
            'suggestions' => [
                'Connect your PC for enhanced training features',
                'Visit the admin panel to manage training data',
                'Ask about specific training topics'
            ]
        ];
    }
    
    /**
     * Handle PulseCore data queries
     */
    private function handlePulseCoreQuery($message, $session_id, $context) {
        try {
            // Get recent PulseCore stats
            $stats = $this->getPulseCoreStats();
            
            if (preg_match('/\brecent novas?\b/i', $message)) {
                return $this->getRecentNovas($stats);
            }
            
            if (preg_match('/\bpatterns?\b/i', $message)) {
                return $this->analyzePatterns($stats);
            }
            
            // Default PulseCore response
            return [
                'success' => true,
                'response' => $this->generatePulseCoreResponse($stats, $message),
                'mode' => 'pulsecore_data',
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            error_log("PulseCore query failed: " . $e->getMessage());
            return [
                'success' => true,
                'response' => "I'm having trouble accessing your PulseCore data right now. Please check the database connection.",
                'mode' => 'error'
            ];
        }
    }
    
    /**
     * Handle general conversational queries
     */
    private function handleConversationalQuery($message, $session_id, $context) {
        // Basic conversational AI response
        $responses = [
            'greeting' => "Hello! I'm your AI assistant. I'm running in standard mode right now. For enhanced capabilities, make sure your PC with Ollama is connected.",
            'help' => "I can help you with various tasks. I have basic conversational abilities and can access your PulseCore data. When your PC is connected, I gain enhanced AI capabilities through Ollama.",
            'default' => "I understand you're asking about: " . substr($message, 0, 50) . "... I'm currently running in web mode with basic capabilities. Connect your PC for enhanced AI responses."
        ];
        
        if (preg_match('/\b(hi|hello|hey)\b/i', $message)) {
            $response = $responses['greeting'];
        } elseif (preg_match('/\b(help|what can you)\b/i', $message)) {
            $response = $responses['help'];
        } else {
            $response = $responses['default'];
        }
        
        return [
            'success' => true,
            'response' => $response,
            'mode' => 'conversational',
            'suggestion' => 'Connect your PC with Ollama for enhanced AI capabilities'
        ];
    }
    
    /**
     * Get PulseCore statistics
     */
    private function getPulseCoreStats() {
        // This would query your existing PulseCore database
        // Returning placeholder for now
        return [
            'total_novas' => 0,
            'total_climaxes' => 0,
            'avg_complexity' => 0,
            'recent_activity' => []
        ];
    }
    
    /**
     * Create fallback response when all AI services fail
     */
    private function createFallbackResponse($message, $session_id) {
        return [
            'success' => true,
            'response' => "I'm experiencing some technical difficulties right now. Please try again in a moment, or check if your PC with Ollama is connected for enhanced capabilities.",
            'mode' => 'fallback',
            'ai_source' => 'fallback'
        ];
    }
    
    /**
     * Create error response
     */
    private function createErrorResponse($error, $details = null) {
        return [
            'success' => false,
            'error' => $error,
            'details' => $details,
            'suggestion' => 'Check your PC connection or try again later'
        ];
    }
    
    /**
     * Record routing decisions for analytics
     */
    private function recordRouting($route, $status, $message, $response) {
        try {
            $stmt = $this->ai_pdo->prepare("
                INSERT INTO routing_analytics (
                    route_chosen, status, message_length, response_time_ms, 
                    success, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $route,
                $status,
                strlen($message),
                $response['processing_time_ms'] ?? 0,
                $status === 'success' ? 1 : 0
            ]);
            
        } catch (Exception $e) {
            // Don't let analytics failure break the main flow
            error_log("Analytics recording failed: " . $e->getMessage());
        }
    }
    
    // Additional helper methods...
    private function getRecentNovas($stats) {
        return [
            'success' => true,
            'response' => "Based on your PulseCore data: You have {$stats['total_novas']} total novas recorded. When your PC is connected, I can provide detailed analysis of your recent nova patterns.",
            'mode' => 'nova_summary',
            'data' => $stats
        ];
    }
    
    private function analyzePatterns($stats) {
        return [
            'success' => true,
            'response' => "Your average complexity is {$stats['avg_complexity']}. For detailed pattern analysis and insights, connect your PC for enhanced AI capabilities.",
            'mode' => 'pattern_analysis',
            'data' => $stats
        ];
    }
    
    private function generatePulseCoreResponse($stats, $message) {
        return "I can see your PulseCore data shows {$stats['total_climaxes']} climaxes and {$stats['total_novas']} novas. For deeper insights and personalized analysis, your PC's enhanced AI would provide much more detailed responses.";
    }
    
    /**
     * Check Ollama directly (fallback when bridge is not available)
     */
    private function checkOllamaDirectly() {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost:11434/api/tags');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                return [
                    'status' => 'offline',
                    'models' => [],
                    'error' => $error ?: 'Ollama server not responding'
                ];
            }
            
            $data = json_decode($response, true);
            $models = [];
            
            if (isset($data['models']) && is_array($data['models'])) {
                foreach ($data['models'] as $model) {
                    $models[] = $model['name'];
                }
            }
            
            return [
                'status' => 'online',
                'models' => $models,
                'preferred_model' => !empty($models) ? $models[0] : null,
                'bridge_used' => false
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'models' => [],
                'error' => $e->getMessage(),
                'bridge_used' => false
            ];
        }
    }
}

// Database schema for routing analytics
/*
CREATE TABLE IF NOT EXISTS routing_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_chosen ENUM('local', 'web', 'fallback') NOT NULL,
    status ENUM('success', 'error', 'timeout') NOT NULL,
    message_length INT,
    response_time_ms INT,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_route_status (route_chosen, status),
    INDEX idx_created (created_at)
);
*/
?>