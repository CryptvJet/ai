<?php
/**
 * Template Testing API
 * Tests template pattern matching and response generation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../db_config.php';
require_once '../chat.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['test_message'])) {
            throw new Exception('Missing required field: test_message');
        }
        
        $test_message = trim($input['test_message']);
        $template_id = $input['template_id'] ?? null;
        
        if (empty($test_message)) {
            throw new Exception('Test message cannot be empty');
        }
        
        // Create AI chat instance for testing
        $chat = new AIChat($ai_pdo, $pulse_pdo ?? $ai_pdo, $vars_pdo ?? $ai_pdo);
        
        // Test specific template if provided
        if ($template_id) {
            $stmt = $ai_pdo->prepare("SELECT * FROM ai_response_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            // Test pattern matching
            $reflection = new ReflectionClass($chat);
            $method = $reflection->getMethod('matchesPattern');
            $method->setAccessible(true);
            $matches = $method->invoke($chat, $test_message, $template['trigger_pattern']);
            
            $result = [
                'template_matches' => $matches,
                'template' => $template,
                'test_message' => $test_message
            ];
            
            if ($matches) {
                $result['would_respond_with'] = $template['response_text'];
                $result['category'] = $template['category'];
                $result['priority'] = $template['priority'];
            }
            
        } else {
            // Test against all templates to see which would match
            $reflection = new ReflectionClass($chat);
            $method = $reflection->getMethod('getTemplateResponse');
            $method->setAccessible(true);
            $matched_template = $method->invoke($chat, $test_message);
            
            $result = [
                'test_message' => $test_message,
                'matched_template' => $matched_template,
                'template_found' => !empty($matched_template)
            ];
            
            if ($matched_template) {
                $result['would_respond_with'] = $matched_template['response_text'];
                $result['category'] = $matched_template['category'];
                $result['priority'] = $matched_template['priority'];
            } else {
                $result['would_respond_with'] = 'No template match - would use AI generation';
            }
        }
        
        echo json_encode([
            'success' => true,
            'result' => $result
        ]);
        
    } else {
        throw new Exception('Only POST method allowed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>