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

class AIChat {
    private $ai_pdo;
    private $pulse_pdo;
    private $vars_pdo;
    
    public function __construct($ai_pdo, $pulse_pdo, $vars_pdo) {
        $this->ai_pdo = $ai_pdo;
        $this->pulse_pdo = $pulse_pdo;
        $this->vars_pdo = $vars_pdo;
    }
    
    public function processMessage($message, $session_id, $mode = 'chill') {
        $start_time = microtime(true);
        
        try {
            // Get or create conversation
            $conversation_id = $this->getOrCreateConversation($session_id);
            
            // Store user message
            $this->storeMessage($conversation_id, 'user', $message, $mode);
            
            // Generate response
            $response = $this->generateResponse($message, $conversation_id, $mode);
            
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
    
    private function storeMessage($conversation_id, $type, $content, $mode, $processing_time = null) {
        $stmt = $this->ai_pdo->prepare("
            INSERT INTO ai_messages (conversation_id, message_type, content, ai_mode, processing_time_ms, metadata) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $metadata = json_encode(['timestamp' => time(), 'mode' => $mode]);
        $stmt->execute([$conversation_id, $type, $content, $mode, $processing_time, $metadata]);
    }
    
    private function generateResponse($message, $conversation_id, $mode) {
        // Check for template responses first
        $template_response = $this->checkTemplateResponses($message);
        if ($template_response) {
            return $template_response;
        }
        
        // Check for PulseCore data requests
        $pulsecore_response = $this->checkPulseCoreQueries($message);
        if ($pulsecore_response) {
            return $pulsecore_response;
        }
        
        // Check for Variables data requests
        $variables_response = $this->checkVariablesQueries($message);
        if ($variables_response) {
            return $variables_response;
        }
        
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
    
    private function checkTemplateResponses($message) {
        $stmt = $this->ai_pdo->prepare("
            SELECT response_text, id FROM ai_response_templates 
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
            
            return $template['response_text'];
        }
        
        return null;
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
        // Try to call PC AI server (when implemented)
        // This would make HTTP request to localhost:8000
        return null;
    }
    
    private function generateBasicResponse($message, $conversation_id) {
        $responses = [
            "That's interesting! Can you tell me more about what you're trying to accomplish with your PulseCore simulations?",
            "I'd be happy to help! Could you be more specific about what data or analysis you're looking for?",
            "Let me think about that... Are you looking for pattern analysis, optimization suggestions, or something else?",
            "I can help with PulseCore data analysis, variables exploration, or general questions about your simulations. What would you like to focus on?",
            "That's a great question! I can access your nova events and variables data to help provide insights. What specific aspect interests you most?"
        ];
        
        return $responses[array_rand($responses)];
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
    $result = $chat->processMessage(
        $input['message'], 
        $input['session_id'], 
        $input['mode'] ?? 'chill'
    );
    
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>