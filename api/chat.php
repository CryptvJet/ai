<?php
/**
 * AI Chat API Endpoint
 * Handles incoming messages and generates responses
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';
require_once 'smart_router.php';

class AIChat {
    private $ai_pdo;
    private $pulse_pdo;
    private $vars_pdo;
    private $settings;
    
    public function __construct($ai_pdo, $pulse_pdo, $vars_pdo) {
        $this->ai_pdo = $ai_pdo;
        $this->pulse_pdo = $pulse_pdo;
        $this->vars_pdo = $vars_pdo;
        $this->settings = $this->loadAISettings();
        $this->router = new SmartAIRouter();
    }
    
    private function loadAISettings() {
        try {
            $stmt = $this->ai_pdo->query("SELECT setting_key, setting_value FROM ai_settings");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (Exception $e) {
            error_log("Failed to load AI settings: " . $e->getMessage());
            return $this->getDefaultSettings();
        }
    }
    
    private function getDefaultSettings() {
        return [
            'ai_name' => 'Zin',
            'personality' => 'helpful,analytical,curious',
            'response_style' => 'conversational',
            'pulsecore_integration' => 'true',
            'variables_integration' => 'true',
            'ask_for_name' => 'true',
            'use_personal_responses' => 'true',
            'follow_up_questions' => 'true',
            'conversation_depth' => 'light'
        ];
    }
    
    public function statusCheck($session_id, $mode = 'auto') {
        // Status check - test AI connection without logging conversations/messages
        try {
            $conversation_id = $this->getOrCreateConversation($session_id);
            $response = $this->generateResponse('Hi', $conversation_id, $mode, null);
            
            return [
                'success' => true,
                'response' => $response,
                'mode' => $mode,
                'processing_time_ms' => 0,
                'speak' => false,
                'status_check' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_check' => true
            ];
        }
    }

    public function processMessage($message, $session_id, $mode = 'chill', $journal_context = null) {
        $start_time = microtime(true);
        
        try {
            // Get or create conversation
            $conversation_id = $this->getOrCreateConversation($session_id);
            
            // Store user message with journal context if provided
            $metadata = ['timestamp' => time(), 'mode' => $mode];
            if ($journal_context !== null) {
                $metadata['journal_context'] = substr($journal_context, -2000); // Store last 2000 chars
            }
            
            $this->storeMessage($conversation_id, 'user', $message, $mode, null, $metadata);
            
            // Generate response
            $response = $this->generateResponse($message, $conversation_id, $mode, $journal_context);
            
            // Store AI response
            $processing_time = round((microtime(true) - $start_time) * 1000);
            $this->storeMessage($conversation_id, 'ai', $response, $mode, $processing_time);
            
            // Update conversation stats
            $this->updateConversationStats($conversation_id);
            
            return [
                'success' => true,
                'response' => $response,
                'mode' => $mode,
                'processing_time_ms' => $processing_time,
                'speak' => $this->shouldSpeak($message)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getOrCreateConversation($session_id) {
        $stmt = $this->ai_pdo->prepare("SELECT id FROM ai_conversations WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $conversation = $stmt->fetch();
        
        if ($conversation) {
            return $conversation['id'];
        }
        
        // Create new conversation
        $stmt = $this->ai_pdo->prepare("
            INSERT INTO ai_conversations (session_id, user_id, session_name, status) 
            VALUES (?, 'anonymous', ?, 'active')
        ");
        $session_name = 'Chat Session ' . date('Y-m-d H:i');
        $stmt->execute([$session_id, $session_name]);
        
        return $this->ai_pdo->lastInsertId();
    }
    
    private function storeMessage($conversation_id, $type, $content, $mode, $processing_time = null, $metadata = null) {
        $stmt = $this->ai_pdo->prepare("
            INSERT INTO ai_messages (conversation_id, message_type, content, ai_mode, processing_time_ms, metadata) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($metadata === null) {
            $metadata = ['timestamp' => time(), 'mode' => $mode];
        }
        
        $metadata_json = json_encode($metadata);
        $stmt->execute([$conversation_id, $type, $content, $mode, $processing_time, $metadata_json]);
    }
    
    private function generateResponse($message, $conversation_id, $mode, $journal_context = null) {
        // Special handling for Journal mode - keep existing logic
        if ($mode === 'journal') {
            return $this->generateJournalResponse($message, $conversation_id, $journal_context);
        }
        
        // Build context for Smart Router with AI settings
        $context = [
            'conversation_id' => $conversation_id,
            'journal_context' => $journal_context,
            'ai_settings' => $this->settings
        ];
        
        // Add PulseCore data context if enabled and relevant
        if ($this->settings['pulsecore_integration'] === 'true' && $this->isPulseCoreQuery($message)) {
            $context['pulsecore_data'] = $this->getPulseCoreContext();
        }
        
        // Route through Smart AI Router with settings context
        $session_id = "conv_" . $conversation_id;
        $router_response = $this->router->routeRequest($message, $session_id, $mode, $context);
        
        if ($router_response && $router_response['success']) {
            $response = $router_response['response'];
            return $this->applyPersonalityToResponse($response, $message, $conversation_id);
        }
        
        // Fallback to original logic if router fails
        error_log("Smart Router failed, using fallback logic");
        return $this->generateFallbackResponse($message, $conversation_id, $mode, $journal_context);
    }
    
    private function isPulseCoreQuery($message) {
        return preg_match('/\b(nova|climax|pulse|energy|complexity|session|variables)\b/i', $message);
    }
    
    private function getPulseCoreContext() {
        // Get basic PulseCore stats for context
        try {
            // This would query your existing PulseCore database
            return [
                'total_novas' => 0,
                'total_climaxes' => 0,
                'avg_complexity' => 0
            ];
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function generateFallbackResponse($message, $conversation_id, $mode, $journal_context = null) {
        // If in full-power mode, try to call PC AI
        if ($mode === 'full-power') {
            $pc_response = $this->tryPCAIResponse($message, $conversation_id);
            if ($pc_response) {
                return $pc_response;
            }
        }
        
        // Fallback to basic AI response
        return $this->generateBasicResponse($message, $conversation_id);
    }
    
    private function checkTemplateResponses($message, $conversation_id) {
        $stmt = $this->ai_pdo->prepare("
            SELECT response_text, id, category FROM ai_response_templates 
            WHERE active = 1 AND ? REGEXP trigger_pattern 
            ORDER BY priority DESC, usage_count ASC 
            LIMIT 1
        ");
        $stmt->execute([strtolower($message)]);
        $template = $stmt->fetch();
        
        if ($template) {
            // Update usage count
            $stmt = $this->ai_pdo->prepare("UPDATE ai_response_templates SET usage_count = usage_count + 1 WHERE id = ?");
            $stmt->execute([$template['id']]);
            
            // Handle name collection
            if ($template['category'] === 'name_collection') {
                $extracted_name = $this->extractNameFromMessage($message);
                if ($extracted_name) {
                    $this->storeUserName($conversation_id, $extracted_name);
                }
            }
            
            // Get user name and personalize response
            $user_name = $this->getUserName($conversation_id);
            $response = $this->personalizeResponse($template['response_text'], $user_name, $extracted_name ?? null);
            
            return $response;
        }
        
        return null;
    }
    
    private function extractNameFromMessage($message) {
        $patterns = [
            '/my name is\s+(.+?)(?:\.|$|,|\s+and)/i',
            '/i\'m\s+(.+?)(?:\.|$|,|\s+and)/i', 
            '/call me\s+(.+?)(?:\.|$|,|\s+and)/i',
            '/i am\s+(.+?)(?:\.|$|,|\s+and)/i',
            '/(.+?)\s+is my name/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $name = trim($matches[1]);
                // Clean up the name (remove common words)
                $name = preg_replace('/\b(a|an|the|just|really|very|quite)\b/i', '', $name);
                $name = trim($name);
                
                // Only accept reasonable names (2-30 chars, letters and spaces)
                if (strlen($name) >= 2 && strlen($name) <= 30 && preg_match('/^[a-zA-Z\s]+$/', $name)) {
                    return ucwords(strtolower($name));
                }
            }
        }
        
        return null;
    }
    
    private function storeUserName($conversation_id, $name) {
        // Get session_id from conversation
        $stmt = $this->ai_pdo->prepare("SELECT session_id FROM ai_conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
        $session = $stmt->fetch();
        
        if ($session) {
            // Update conversation with user name
            $stmt = $this->ai_pdo->prepare("UPDATE ai_conversations SET user_id = ? WHERE id = ?");
            $stmt->execute([$name, $conversation_id]);
            
            // Store in user preferences
            $stmt = $this->ai_pdo->prepare("
                INSERT INTO ai_user_preferences (session_id, user_name, last_interaction) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_interaction = NOW()
            ");
            $stmt->execute([$session['session_id'], $name]);
        }
    }
    
    private function getUserName($conversation_id) {
        $stmt = $this->ai_pdo->prepare("
            SELECT c.user_id, up.user_name 
            FROM ai_conversations c 
            LEFT JOIN ai_user_preferences up ON c.session_id = up.session_id 
            WHERE c.id = ?
        ");
        $stmt->execute([$conversation_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['user_name'] ?: ($result['user_id'] !== 'anonymous' ? $result['user_id'] : null);
        }
        
        return null;
    }
    
    private function personalizeResponse($response, $user_name, $new_name = null) {
        // Use the newly extracted name if available, otherwise fall back to stored name
        $name_to_use = $new_name ?: $user_name;
        
        if ($name_to_use) {
            $response = str_replace('[NAME]', $name_to_use, $response);
        } else {
            // Remove [NAME] tokens if no name is available
            $response = str_replace('[NAME], ', '', $response);
            $response = str_replace(', [NAME]', '', $response);
            $response = str_replace('[NAME]', 'there', $response);
        }
        
        return $response;
    }
    
    private function checkPulseCoreQueries($message) {
        $message_lower = strtolower($message);
        
        // Nova count queries
        if (preg_match('/nova.*count|how many.*nova|total.*nova/i', $message)) {
            return $this->getNovaCount();
        }
        
        // Recent novas
        if (preg_match('/recent.*nova|latest.*nova|last.*nova/i', $message)) {
            return $this->getRecentNovas();
        }
        
        // Complexity analysis
        if (preg_match('/complexity|complex.*pattern/i', $message)) {
            return $this->getComplexityAnalysis();
        }
        
        // Energy analysis
        if (preg_match('/energy|pulse.*energy|average.*energy/i', $message)) {
            return $this->getEnergyAnalysis();
        }
        
        return null;
    }
    
    private function checkVariablesQueries($message) {
        $message_lower = strtolower($message);
        
        // Variable search
        if (preg_match('/variable.*(\w+)|find.*variable|search.*variable/i', $message, $matches)) {
            $search_term = $matches[1] ?? '';
            return $this->searchVariables($search_term);
        }
        
        // Variable count
        if (preg_match('/variable.*count|how many.*variable/i', $message)) {
            return $this->getVariableCount();
        }
        
        return null;
    }
    
    // PulseCore Data Methods
    private function getNovaCount() {
        try {
            $stmt = $this->pulse_pdo->query("SELECT COUNT(*) as count FROM nova_events");
            $result = $stmt->fetch();
            return "I found {$result['count']} total nova events in your PulseCore database. Would you like me to analyze any specific patterns or time periods?";
        } catch (Exception $e) {
            return "I'm having trouble accessing your PulseCore database right now. Please check the connection.";
        }
    }
    
    private function getRecentNovas() {
        try {
            $stmt = $this->pulse_pdo->query("
                SELECT timestamp, complexity, pulse_energy, genesis_mode 
                FROM nova_events 
                ORDER BY timestamp DESC 
                LIMIT 5
            ");
            $novas = $stmt->fetchAll();
            
            if (empty($novas)) {
                return "No nova events found in your database.";
            }
            
            $response = "Here are your 5 most recent nova events:\n\n";
            foreach ($novas as $nova) {
                $date = date('M j, Y H:i', strtotime($nova['timestamp']));
                $response .= "• {$date} - Complexity: {$nova['complexity']}, Energy: {$nova['pulse_energy']}, Mode: {$nova['genesis_mode']}\n";
            }
            
            return $response;
        } catch (Exception $e) {
            return "I'm having trouble accessing your recent nova data.";
        }
    }
    
    private function getComplexityAnalysis() {
        try {
            $stmt = $this->pulse_pdo->query("
                SELECT 
                    AVG(complexity) as avg_complexity,
                    MIN(complexity) as min_complexity,
                    MAX(complexity) as max_complexity,
                    COUNT(*) as total_novas
                FROM nova_events 
                WHERE complexity IS NOT NULL
            ");
            $stats = $stmt->fetch();
            
            return "Complexity Analysis:\n" .
                   "• Average: " . round($stats['avg_complexity'], 2) . "\n" .
                   "• Range: {$stats['min_complexity']} - {$stats['max_complexity']}\n" .
                   "• Total analyzed: {$stats['total_novas']} events\n\n" .
                   "Your simulations show " . $this->interpretComplexity($stats['avg_complexity']);
        } catch (Exception $e) {
            return "I'm having trouble analyzing complexity data.";
        }
    }
    
    private function getEnergyAnalysis() {
        try {
            $stmt = $this->pulse_pdo->query("
                SELECT 
                    AVG(pulse_energy) as avg_energy,
                    MIN(pulse_energy) as min_energy,
                    MAX(pulse_energy) as max_energy
                FROM nova_events 
                WHERE pulse_energy IS NOT NULL
            ");
            $stats = $stmt->fetch();
            
            return "Energy Analysis:\n" .
                   "• Average Energy: " . round($stats['avg_energy'], 3) . "\n" .
                   "• Range: " . round($stats['min_energy'], 3) . " - " . round($stats['max_energy'], 3) . "\n\n" .
                   $this->interpretEnergy($stats['avg_energy']);
        } catch (Exception $e) {
            return "I'm having trouble analyzing energy data.";
        }
    }
    
    // Variables Data Methods
    private function searchVariables($search_term) {
        try {
            $stmt = $this->vars_pdo->prepare("
                SELECT symbol, name, description, category 
                FROM pulse_core_variables 
                WHERE symbol LIKE ? OR name LIKE ? OR description LIKE ?
                LIMIT 5
            ");
            $search_pattern = "%{$search_term}%";
            $stmt->execute([$search_pattern, $search_pattern, $search_pattern]);
            $variables = $stmt->fetchAll();
            
            if (empty($variables)) {
                return "I couldn't find any variables matching '{$search_term}'. Try a different search term.";
            }
            
            $response = "Found variables matching '{$search_term}':\n\n";
            foreach ($variables as $var) {
                $response .= "• **{$var['symbol']}** ({$var['name']}) - {$var['category']}\n";
                $response .= "  " . substr($var['description'], 0, 100) . "...\n\n";
            }
            
            return $response;
        } catch (Exception $e) {
            return "I'm having trouble searching the variables database.";
        }
    }
    
    private function getVariableCount() {
        try {
            $stmt = $this->vars_pdo->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT category) as categories
                FROM pulse_core_variables
            ");
            $stats = $stmt->fetch();
            
            return "Variables Database Stats:\n" .
                   "• Total Variables: {$stats['total']}\n" .
                   "• Categories: {$stats['categories']}\n\n" .
                   "You can search for specific variables by name or symbol!";
        } catch (Exception $e) {
            return "I'm having trouble accessing the variables database.";
        }
    }
    
    private function tryPCAIResponse($message, $conversation_id) {
        // First try local Llama AI
        $llama_response = $this->tryLocalLlamaResponse($message, $conversation_id);
        if ($llama_response) {
            return $llama_response;
        }
        
        // Fallback to PC status analysis if no Llama available
        if ($this->isPCStatusQuery($message)) {
            return $this->getPCAnalysis($message);
        }
        
        return null;
    }
    
    private function tryLocalLlamaResponse($message, $conversation_id) {
        try {
            // Check if local Llama is available
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'http://localhost:11434/api/tags',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // If Ollama is not available, return null for fallback
            if ($http_code !== 200 || !$response) {
                return null;
            }
            
            // Prepare context for Llama
            $context = $this->buildLlamaContext($message, $conversation_id);
            
            // Call local Llama API
            $llama_ch = curl_init();
            curl_setopt_array($llama_ch, [
                CURLOPT_URL => 'http://localhost/ai/api/llama-local.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'message' => $message,
                    'context' => $context
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 45 // Longer timeout for AI generation
            ]);
            
            $llama_response = curl_exec($llama_ch);
            $llama_http_code = curl_getinfo($llama_ch, CURLINFO_HTTP_CODE);
            curl_close($llama_ch);
            
            if ($llama_http_code === 200 && $llama_response) {
                $data = json_decode($llama_response, true);
                if ($data && isset($data['success']) && $data['success']) {
                    // Add indicator that this is from local AI
                    return "🧠 **[Local AI]** " . $data['response'];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            // Silent fallback on any error
            return null;
        }
    }
    
    private function buildLlamaContext($message, $conversation_id) {
        $context = [
            'mode' => 'chat',
            'user_name' => $this->getUserName($conversation_id),
            'conversation_history' => []
        ];
        
        try {
            // Get recent conversation history (last 10 messages)
            $stmt = $this->ai_pdo->prepare("
                SELECT message_type, content 
                FROM ai_messages 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$conversation_id]);
            $messages = $stmt->fetchAll();
            
            // Reverse to get chronological order
            $messages = array_reverse($messages);
            
            foreach ($messages as $msg) {
                $context['conversation_history'][] = [
                    'role' => $msg['message_type'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['content']
                ];
            }
            
            // Add PC status if message relates to PC
            if ($this->isPCStatusQuery($message)) {
                $context['mode'] = 'pc_analysis';
                $context['pc_status'] = $this->getLatestPCStatus();
            }
            
            // Add PulseCore context for data-related queries
            if ($this->isPulseCoreQuery($message)) {
                $context['mode'] = 'pulsecore';
                $context['pulsecore_stats'] = $this->getRecentPulseCoreStats();
            }
            
        } catch (Exception $e) {
            // Continue with basic context if database queries fail
        }
        
        return $context;
    }
    
    private function getLatestPCStatus() {
        try {
            $stmt = $this->pulse_pdo->prepare("
                SELECT system_info, last_ping, 
                       TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_ago
                FROM pc_status 
                ORDER BY last_ping DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                return [
                    'system_info' => json_decode($result['system_info'], true),
                    'last_seen' => $result['seconds_ago'],
                    'status' => $result['seconds_ago'] < 60 ? 'online' : 'offline'
                ];
            }
        } catch (Exception $e) {
            // Return null if PC status unavailable
        }
        
        return null;
    }
    
    private function getRecentPulseCoreStats() {
        try {
            $stmt = $this->pulse_pdo->query("
                SELECT COUNT(*) as total_novas,
                       AVG(complexity) as avg_complexity,
                       AVG(pulse_energy) as avg_energy
                FROM nova_events 
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats = $stmt->fetch();
            
            return $stats ?: null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function isPCStatusQuery($message) {
        $pc_keywords = [
            'pc', 'computer', 'system', 'performance', 'memory', 'ram', 'cpu',
            'how.*my.*pc', 'pc.*doing', 'computer.*status', 'system.*info',
            'memory.*usage', 'performance.*check', 'heartbeat', 'uptime'
        ];
        
        $message_lower = strtolower($message);
        foreach ($pc_keywords as $pattern) {
            if (preg_match("/$pattern/i", $message_lower)) {
                return true;
            }
        }
        return false;
    }
    
    private function getPCAnalysis($message) {
        try {
            // Get latest PC status from heartbeat
            $stmt = $this->pulse_pdo->prepare("
                SELECT 
                    system_info,
                    last_ping,
                    TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping,
                    CASE 
                        WHEN TIMESTAMPDIFF(SECOND, last_ping, NOW()) < 60 THEN 'online'
                        ELSE 'offline'
                    END as status
                FROM pc_status 
                ORDER BY last_ping DESC 
                LIMIT 1
            ");
            
            $stmt->execute();
            $pc_data = $stmt->fetch();
            
            if (!$pc_data) {
                return "I don't see any PC data yet. Make sure your PC Bridge is running and connected! 🖥️";
            }
            
            $system_info = json_decode($pc_data['system_info'], true);
            $status = $pc_data['status'];
            $last_seen = $pc_data['seconds_since_ping'];
            
            // Analyze the data and create response
            return $this->formatPCAnalysis($system_info, $status, $last_seen, $message);
            
        } catch (Exception $e) {
            return "I'm having trouble accessing your PC data right now. The bridge might be disconnected. 🔧";
        }
    }
    
    private function formatPCAnalysis($system_info, $status, $last_seen, $original_message) {
        if ($status === 'offline') {
            return "Your PC appears to be offline. Last seen " . $this->formatTimeAgo($last_seen) . " ago. 💤";
        }
        
        $hostname = $system_info['hostname'] ?? 'Unknown';
        $uptime_hours = round(($system_info['uptime'] ?? 0) / 3600, 1);
        $memory_total = $system_info['memory']['total'] ?? 0;
        $memory_free = $system_info['memory']['free'] ?? 0;
        $memory_used = $memory_total - $memory_free;
        $memory_percent = $memory_total > 0 ? round(($memory_used / $memory_total) * 100, 1) : 0;
        $cpu_count = $system_info['cpus'] ?? 0;
        
        $analysis = [];
        
        // Status overview
        $analysis[] = "🟢 **{$hostname}** is online and healthy!";
        $analysis[] = "⏱️ Uptime: {$uptime_hours} hours";
        $analysis[] = "🧠 Memory: {$memory_percent}% used (" . $this->formatBytes($memory_used) . " / " . $this->formatBytes($memory_total) . ")";
        $analysis[] = "💻 CPUs: {$cpu_count} cores";
        
        // Performance assessment
        if ($memory_percent > 90) {
            $analysis[] = "⚠️ **Memory Warning**: Your system is using {$memory_percent}% of RAM. Consider closing some applications.";
        } elseif ($memory_percent > 75) {
            $analysis[] = "📊 Memory usage is moderately high at {$memory_percent}%. Everything looks normal.";
        } else {
            $analysis[] = "✅ Memory usage is healthy at {$memory_percent}%.";
        }
        
        // Uptime assessment
        if ($uptime_hours > 168) { // 1 week
            $analysis[] = "💡 Your PC has been running for over a week. Consider restarting for optimal performance.";
        }
        
        $analysis[] = "📡 Last heartbeat: " . $this->formatTimeAgo($last_seen) . " ago";
        
        return implode("\n", $analysis);
    }
    
    private function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
    
    private function formatTimeAgo($seconds) {
        if ($seconds < 60) return $seconds . ' seconds';
        if ($seconds < 3600) return round($seconds / 60) . ' minutes';
        if ($seconds < 86400) return round($seconds / 3600) . ' hours';
        return round($seconds / 86400) . ' days';
    }
    
    private function generateBasicResponse($message, $conversation_id) {
        $user_name = $this->getUserName($conversation_id);
        $name_part = $user_name ? $user_name . ', ' : '';
        
        $responses = [
            "That's really interesting, {$name_part}! Can you tell me more about what you're thinking? I'd love to understand your perspective better.",
            "I'm curious about that, {$name_part}! Could you help me understand what specifically you're looking for? I want to give you the most helpful response.",
            "Hmm, let me think about that, {$name_part}... Are you exploring PulseCore patterns, working on calculations, or something else entirely?",
            "That's a great point, {$name_part}! I can help with data analysis, pattern exploration, or just have a good conversation. What sounds most interesting to you right now?",
            "I find that fascinating, {$name_part}! What made you think of that? I'd love to dive deeper into whatever's on your mind.",
            "You know, {$name_part}, that reminds me of some interesting patterns I've seen in your data. What's sparking your curiosity today?",
            "That's really thoughtful, {$name_part}! I'm always eager to learn from your insights. Can you elaborate on what you're thinking?",
            "I appreciate you sharing that, {$name_part}! What aspects of your PulseCore work are you most excited about lately?"
        ];
        
        return $responses[array_rand($responses)];
    }

    private function generateJournalResponse($message, $conversation_id, $journal_context = null) {
        $user_name = $this->getUserName($conversation_id);
        $name_part = $user_name ? $user_name : 'there';
        
        $message_lower = strtolower($message);
        
        // Analyze request type and generate appropriate response
        if (preg_match('/analyze|what.*think|thoughts|feedback|review/i', $message)) {
            return $this->analyzeJournalContent($journal_context, $name_part, $message);
        }
        
        if (preg_match('/improve|better|edit|rewrite|suggestion/i', $message)) {
            return $this->suggestJournalImprovements($journal_context, $name_part, $message);
        }
        
        if (preg_match('/theme|pattern|recurring|meaning|deeper/i', $message)) {
            return $this->identifyJournalThemes($journal_context, $name_part, $message);
        }
        
        if (preg_match('/continue|next|write.*more|expand/i', $message)) {
            return $this->suggestJournalContinuation($journal_context, $name_part, $message);
        }
        
        if (preg_match('/structure|organize|format|flow/i', $message)) {
            return $this->suggestJournalStructure($journal_context, $name_part, $message);
        }
        
        // Default journal companion responses
        $responses = [
            "That's a fascinating question, {$name_part}! Looking at your journal, I can see you're exploring some deep thoughts. What specifically would you like me to help you with?",
            "I love how you're using this journal space to think through ideas, {$name_part}. How can I support your writing process right now?",
            "Your writing voice is really coming through, {$name_part}. Are you looking for feedback on what you've written so far, or would you like help developing these thoughts further?",
            "I'm here to help you explore your ideas, {$name_part}. Would you like me to analyze what you've written, suggest improvements, or help you continue developing your thoughts?",
            "That's an interesting perspective, {$name_part}. I can see from your journal that you're working through some complex ideas. What aspect would you like to dive deeper into?",
            "Your journal entries show real thoughtfulness, {$name_part}. I can help you analyze patterns, improve your writing, or explore themes - what sounds most helpful right now?"
        ];
        
        return $responses[array_rand($responses)];
    }
    
    private function analyzeJournalContent($journal_context, $name, $original_message) {
        if (!$journal_context || empty(trim($journal_context))) {
            return "I'd love to help analyze your writing, {$name}, but I don't see any journal content yet. Once you start writing, I can help you identify themes, patterns, and insights in your thoughts!";
        }
        
        $word_count = str_word_count($journal_context);
        $sentences = preg_split('/[.!?]+/', $journal_context);
        $sentence_count = count(array_filter($sentences, 'trim'));
        
        // Simple sentiment analysis
        $positive_words = ['happy', 'joy', 'excited', 'grateful', 'love', 'amazing', 'wonderful', 'great', 'good', 'hope', 'dream', 'success'];
        $negative_words = ['sad', 'angry', 'frustrated', 'worried', 'fear', 'anxious', 'difficult', 'hard', 'struggle', 'problem', 'issue'];
        
        $positive_count = 0;
        $negative_count = 0;
        $lower_text = strtolower($journal_context);
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($lower_text, $word);
        }
        foreach ($negative_words as $word) {
            $negative_count += substr_count($lower_text, $word);
        }
        
        $analysis = [];
        $analysis[] = "**Content Analysis for {$name}:**";
        $analysis[] = "📊 **Stats:** {$word_count} words, {$sentence_count} sentences";
        
        if ($positive_count > $negative_count) {
            $analysis[] = "🌟 **Tone:** Your writing has a generally positive and optimistic tone!";
        } elseif ($negative_count > $positive_count) {
            $analysis[] = "🤔 **Tone:** I notice some challenging themes - this can be really valuable for processing difficult experiences.";
        } else {
            $analysis[] = "⚖️ **Tone:** Your writing shows a balanced emotional perspective.";
        }
        
        // Look for questions
        $questions = substr_count($journal_context, '?');
        if ($questions > 0) {
            $analysis[] = "❓ **Self-Inquiry:** You're asking {$questions} questions - great for self-reflection!";
        }
        
        $analysis[] = "\n💡 **What stands out:** Your writing shows authentic self-expression and genuine reflection. Keep exploring these thoughts!";
        
        return implode("\n", $analysis);
    }
    
    private function suggestJournalImprovements($journal_context, $name, $original_message) {
        if (!$journal_context || empty(trim($journal_context))) {
            return "I'd be happy to help improve your writing, {$name}! Once you have some content in your journal, I can suggest ways to enhance clarity, flow, and impact.";
        }
        
        $suggestions = [];
        $suggestions[] = "**Writing Suggestions for {$name}:**";
        
        // Check for varied sentence structure
        $sentences = preg_split('/[.!?]+/', $journal_context);
        $short_sentences = 0;
        $long_sentences = 0;
        
        foreach ($sentences as $sentence) {
            $word_count = str_word_count(trim($sentence));
            if ($word_count > 0 && $word_count < 8) $short_sentences++;
            if ($word_count > 20) $long_sentences++;
        }
        
        if ($short_sentences > count($sentences) * 0.7) {
            $suggestions[] = "✏️ Consider combining some short sentences for better flow";
        }
        if ($long_sentences > count($sentences) * 0.5) {
            $suggestions[] = "✂️ Some sentences could be broken down for clarity";
        }
        
        // Check for specific details
        if (substr_count($journal_context, 'I feel') > 3) {
            $suggestions[] = "🎨 Try varying how you express emotions beyond 'I feel' - describe sensations, use metaphors";
        }
        
        if (preg_match_all('/\b(very|really|quite|pretty|somewhat)\b/i', $journal_context) > 5) {
            $suggestions[] = "💪 Consider replacing some adverbs with stronger, more specific words";
        }
        
        $suggestions[] = "\n🌟 **Strengths I notice:** Your authentic voice and willingness to explore your thoughts deeply. Keep that up!";
        
        return implode("\n", $suggestions);
    }
    
    private function identifyJournalThemes($journal_context, $name, $original_message) {
        if (!$journal_context || empty(trim($journal_context))) {
            return "I'd love to help identify themes in your writing, {$name}! As you write more, I'll be able to spot recurring patterns and deeper meanings in your thoughts.";
        }
        
        $themes = [];
        $lower_text = strtolower($journal_context);
        
        // Common life themes
        if (preg_match('/work|job|career|professional|office|boss|colleague/i', $journal_context)) {
            $themes[] = "💼 **Career & Work Life** - Processing professional experiences and goals";
        }
        
        if (preg_match('/relationship|friend|family|love|partner|connection/i', $journal_context)) {
            $themes[] = "❤️ **Relationships** - Exploring connections with others";
        }
        
        if (preg_match('/dream|goal|future|plan|hope|aspire|want to/i', $journal_context)) {
            $themes[] = "🎯 **Goals & Aspirations** - Thinking about future possibilities";
        }
        
        if (preg_match('/challenge|difficult|problem|struggle|hard|tough/i', $journal_context)) {
            $themes[] = "🎭 **Life Challenges** - Working through difficulties";
        }
        
        if (preg_match('/learn|grow|change|develop|improve|better/i', $journal_context)) {
            $themes[] = "🌱 **Personal Growth** - Focusing on self-development";
        }
        
        if (preg_match('/creative|write|art|music|design|imagine/i', $journal_context)) {
            $themes[] = "🎨 **Creativity** - Exploring artistic and creative expression";
        }
        
        $response = "**Themes I notice in your writing, {$name}:**\n\n";
        
        if (empty($themes)) {
            $response .= "Your writing is still developing its themes. As you continue journaling, I'll be able to identify deeper patterns and recurring interests that emerge in your thoughts.";
        } else {
            $response .= implode("\n\n", $themes);
            $response .= "\n\n🔍 **Insight:** These themes suggest you're actively engaging with important life areas. Your journal is becoming a valuable space for processing and growth!";
        }
        
        return $response;
    }
    
    private function suggestJournalContinuation($journal_context, $name, $original_message) {
        if (!$journal_context || empty(trim($journal_context))) {
            return "Great idea to keep writing, {$name}! Here are some prompts to get started:\n• What's been on your mind lately?\n• Describe a moment from today that stood out\n• What are you grateful for right now?\n• What challenge are you working through?";
        }
        
        $suggestions = [];
        $suggestions[] = "**Ways to continue your journal, {$name}:**";
        
        // Analyze last few sentences for context
        $sentences = array_filter(array_map('trim', preg_split('/[.!?]+/', $journal_context)));
        $last_few = array_slice($sentences, -3);
        $recent_text = implode(' ', $last_few);
        
        if (preg_match('/feel|emotion|mood/i', $recent_text)) {
            $suggestions[] = "💭 Dig deeper: What caused these feelings? How do they connect to your values?";
        }
        
        if (preg_match('/decision|choice|should|maybe/i', $recent_text)) {
            $suggestions[] = "⚖️ Explore: What are the pros and cons? What would your future self advise?";
        }
        
        if (preg_match('/person|someone|friend|family/i', $recent_text)) {
            $suggestions[] = "👥 Consider: How do your relationships shape your perspective on this?";
        }
        
        // General continuation prompts
        $suggestions[] = "🎯 **Try these prompts:**";
        $suggestions[] = "• If I could give my past self advice about this...";
        $suggestions[] = "• What I'm learning about myself is...";
        $suggestions[] = "• The bigger picture here might be...";
        $suggestions[] = "• What would happen if I...";
        
        return implode("\n", $suggestions);
    }
    
    private function suggestJournalStructure($journal_context, $name, $original_message) {
        if (!$journal_context || empty(trim($journal_context))) {
            return "I can help you structure your journal writing, {$name}! Some popular formats:\n• **Stream of consciousness** - Just let thoughts flow\n• **Daily reflection** - What happened, how you felt, what you learned\n• **Gratitude + Challenge** - What you're thankful for and what you're working on\n• **Question exploration** - Pick a question and write your way to answers";
        }
        
        $structure_suggestions = [];
        $structure_suggestions[] = "**Structure suggestions for your journal, {$name}:**";
        
        $paragraphs = explode("\n", $journal_context);
        $paragraph_count = count(array_filter($paragraphs, 'trim'));
        
        if ($paragraph_count <= 2) {
            $structure_suggestions[] = "📝 Consider breaking your thoughts into smaller paragraphs for easier reading";
        }
        
        if (!preg_match('/\?/', $journal_context)) {
            $structure_suggestions[] = "❓ Try adding some questions to guide your exploration: 'What does this mean?' 'How does this connect?'";
        }
        
        $structure_suggestions[] = "📋 **Effective journal structures:**";
        $structure_suggestions[] = "• **Beginning:** What's happening in my life/mind right now?";
        $structure_suggestions[] = "• **Middle:** Why does this matter? How do I feel about it?";
        $structure_suggestions[] = "• **End:** What am I learning? What do I want to remember?";
        
        $structure_suggestions[] = "\n✨ **Your current style:** Your writing has an authentic, flowing quality. Structure can enhance this without limiting your natural expression!";
        
        return implode("\n", $structure_suggestions);
    }
    
    private function interpretComplexity($avg) {
        if ($avg < 10) return "relatively simple patterns with low complexity.";
        if ($avg < 50) return "moderate complexity with interesting emergent behaviors.";
        if ($avg < 100) return "high complexity with rich pattern formations.";
        return "very high complexity - your simulations are generating sophisticated structures!";
    }
    
    private function interpretEnergy($avg) {
        if ($avg < 0.1) return "Low energy levels suggest stable, controlled patterns.";
        if ($avg < 0.5) return "Moderate energy levels indicate balanced dynamics.";
        return "High energy levels show active, dynamic pattern evolution.";
    }
    
    private function applyPersonalityToResponse($response, $message, $conversation_id) {
        // Get user name for personalization
        $user_name = null;
        if ($this->settings['use_personal_responses'] === 'true') {
            $user_name = $this->getUserName($conversation_id);
        }
        
        // Apply AI name and personality
        $ai_name = $this->settings['ai_name'] ?? 'Zin';
        $response = $this->personalizeResponse($response, $user_name, null, $ai_name);
        
        // Add follow-up questions if enabled
        if ($this->settings['follow_up_questions'] === 'true') {
            $response = $this->addFollowUpQuestion($response, $message, $user_name);
        }
        
        // Ask for name if enabled and we don't have one
        if ($this->settings['ask_for_name'] === 'true' && !$user_name && $this->shouldAskForName($conversation_id)) {
            $response = $this->addNameRequest($response);
        }
        
        return $response;
    }
    
    private function personalizeResponse($response, $user_name, $extracted_name, $ai_name = 'Zin') {
        // Use provided name or extracted name
        $name_to_use = $user_name ?: $extracted_name;
        
        // Add personal touch if we have a name
        if ($name_to_use && $this->settings['use_personal_responses'] === 'true') {
            // Add name naturally to response
            if (!preg_match('/\b' . preg_quote($name_to_use) . '\b/i', $response)) {
                $response = $name_to_use . ', ' . lcfirst($response);
            }
        }
        
        // Ensure AI introduces itself with correct name
        $response = str_replace(['I\'m Zin', 'I am Zin'], ["I'm $ai_name", "I am $ai_name"], $response);
        
        return $response;
    }
    
    private function addFollowUpQuestion($response, $original_message, $user_name = null) {
        // Only add follow-up for certain conversation depths
        $depth = $this->settings['conversation_depth'] ?? 'light';
        
        if ($depth === 'light') {
            // Light follow-ups only for specific topics
            if (preg_match('/\b(project|work|study|learning)\b/i', $original_message)) {
                $followUps = [
                    'How is that going for you?',
                    'What aspect interests you most?',
                    'Any particular challenges you\'re facing?'
                ];
                $response .= ' ' . $followUps[array_rand($followUps)];
            }
        } else if ($depth === 'deep') {
            // More engaging follow-ups
            $followUps = [
                'What are your thoughts on that?',
                'How does that make you feel?',
                'What would you like to explore further?',
                'Is there more you\'d like to share about this?'
            ];
            $response .= ' ' . $followUps[array_rand($followUps)];
        }
        
        return $response;
    }
    
    private function addNameRequest($response) {
        $nameRequests = [
            'By the way, what should I call you?',
            'What\'s your name, if you don\'t mind me asking?',
            'I\'d love to know your name so I can personalize our conversation.',
            'Feel free to tell me your name so I can address you properly.'
        ];
        
        return $response . ' ' . $nameRequests[array_rand($nameRequests)];
    }
    
    private function shouldAskForName($conversation_id) {
        // Ask for name only once per conversation, and not on first message
        $stmt = $this->ai_pdo->prepare("SELECT COUNT(*) as count FROM ai_messages WHERE conversation_id = ?");
        $stmt->execute([$conversation_id]);
        $message_count = $stmt->fetch()['count'];
        
        // Ask after 2-3 messages if we still don't have a name
        return $message_count >= 2 && $message_count <= 4;
    }
    
    private function shouldSpeak($message) {
        // Simple heuristic - speak responses to questions
        return preg_match('/\?/', $message) || 
               preg_match('/what|how|why|when|where|tell|explain/i', $message);
    }
    
    private function updateConversationStats($conversation_id) {
        $stmt = $this->ai_pdo->prepare("
            UPDATE ai_conversations 
            SET total_messages = (
                SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$conversation_id, $conversation_id]);
        
        // Auto-mark old conversations as completed
        $this->markOldConversationsAsCompleted();
    }
    
    private function markOldConversationsAsCompleted() {
        // First, ensure the status column can hold 'inactive' 
        try {
            // Try to modify the column to allow longer status values
            $this->ai_pdo->exec("ALTER TABLE ai_conversations MODIFY COLUMN status VARCHAR(20) DEFAULT 'active'");
        } catch (Exception $e) {
            // If ALTER fails, column might already be correct or we don't have permissions
            error_log("Could not modify status column: " . $e->getMessage());
        }
        
        // Mark conversations as inactive if they haven't had activity in last 2 hours
        $stmt = $this->ai_pdo->prepare("
            UPDATE ai_conversations 
            SET status = 'inactive', ended_at = NOW()
            WHERE status = 'active' 
            AND id IN (
                SELECT conversation_id 
                FROM (
                    SELECT conversation_id, MAX(timestamp) as last_message
                    FROM ai_messages 
                    GROUP BY conversation_id
                    HAVING last_message < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                ) as old_convs
            )
        ");
        $stmt->execute();
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['message']) || !isset($input['session_id'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        exit;
    }
    
    $chat = new AIChat($ai_pdo, $pulse_pdo, $vars_pdo);
    
    // Handle status check requests with lightweight test (DON'T LOG TO DATABASE)
    if (isset($input['status_check']) && $input['status_check'] === true) {
        // Status check - test AI connection without logging to database
        $result = $chat->statusCheck($input['session_id'], $input['mode'] ?? 'auto');
    } else {
        // Normal message processing
        $result = $chat->processMessage(
            $input['message'], 
            $input['session_id'], 
            $input['mode'] ?? 'chill',
            $input['journal_context'] ?? null
        );
    }
    
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>