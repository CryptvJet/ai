<?php
/**
 * AI Response Templates API
 * Manages response templates for the AI
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all response templates
        $stmt = $ai_pdo->query("
            SELECT id, trigger_pattern, response_text, category, priority, active, usage_count, created_at, updated_at
            FROM ai_response_templates 
            ORDER BY priority DESC, category, created_at
        ");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $templates
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new template
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['trigger_pattern']) || !isset($input['response_text'])) {
            throw new Exception('Missing required fields: trigger_pattern, response_text');
        }
        
        $stmt = $ai_pdo->prepare("
            INSERT INTO ai_response_templates (trigger_pattern, response_text, category, priority, active) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['trigger_pattern'],
            $input['response_text'],
            $input['category'] ?? 'general',
            $input['priority'] ?? 50,
            $input['active'] ?? true
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template created successfully',
            'id' => $ai_pdo->lastInsertId()
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update existing template
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            throw new Exception('Missing required field: id');
        }
        
        $updateFields = [];
        $params = [];
        
        foreach (['trigger_pattern', 'response_text', 'category', 'priority', 'active'] as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $params[] = $input['id'];
        
        $stmt = $ai_pdo->prepare("
            UPDATE ai_response_templates 
            SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template updated successfully'
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete template
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('Missing required parameter: id');
        }
        
        $stmt = $ai_pdo->prepare("DELETE FROM ai_response_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>