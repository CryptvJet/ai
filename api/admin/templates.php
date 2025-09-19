<?php
/**
 * Admin Response Templates API
 * Enhanced CRUD operations for template management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all response templates with enhanced data
        $category = $_GET['category'] ?? null;
        $active_only = $_GET['active_only'] ?? false;
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($category) {
            $whereClause .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($active_only) {
            $whereClause .= " AND active = 1";
        }
        
        $stmt = $ai_pdo->prepare("
            SELECT 
                id, 
                trigger_pattern, 
                response_text, 
                category, 
                priority, 
                active, 
                usage_count,
                created_from_learning,
                created_at, 
                updated_at,
                CASE 
                    WHEN usage_count = 0 THEN 'untested'
                    WHEN usage_count < 5 THEN 'low_usage'
                    WHEN usage_count < 20 THEN 'moderate_usage'
                    ELSE 'high_usage'
                END as usage_level
            FROM ai_response_templates 
            $whereClause
            ORDER BY priority DESC, usage_count DESC, created_at DESC
        ");
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get category summary
        $stmt = $ai_pdo->query("
            SELECT 
                category,
                COUNT(*) as total,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count,
                AVG(usage_count) as avg_usage
            FROM ai_response_templates 
            GROUP BY category 
            ORDER BY total DESC
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $templates,
            'categories' => $categories,
            'total' => count($templates)
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new template
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['trigger_pattern']) || !isset($input['response_text'])) {
            throw new Exception('Missing required fields: trigger_pattern, response_text');
        }
        
        // Validate input
        $trigger_pattern = trim($input['trigger_pattern']);
        $response_text = trim($input['response_text']);
        
        if (empty($trigger_pattern) || empty($response_text)) {
            throw new Exception('Trigger pattern and response text cannot be empty');
        }
        
        // Check for duplicate patterns
        $stmt = $ai_pdo->prepare("SELECT id FROM ai_response_templates WHERE trigger_pattern = ? AND active = 1");
        $stmt->execute([$trigger_pattern]);
        if ($stmt->fetch()) {
            throw new Exception('A template with this trigger pattern already exists');
        }
        
        $stmt = $ai_pdo->prepare("
            INSERT INTO ai_response_templates (
                trigger_pattern, 
                response_text, 
                category, 
                priority, 
                active, 
                created_from_learning
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $trigger_pattern,
            $response_text,
            $input['category'] ?? 'general',
            $input['priority'] ?? 50,
            $input['active'] ?? true,
            $input['created_from_learning'] ?? null
        ]);
        
        $template_id = $ai_pdo->lastInsertId();
        
        // Get the created template
        $stmt = $ai_pdo->prepare("SELECT * FROM ai_response_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template created successfully',
            'template' => $template
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update existing template
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            throw new Exception('Missing required field: id');
        }
        
        // Check if template exists
        $stmt = $ai_pdo->prepare("SELECT * FROM ai_response_templates WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            throw new Exception('Template not found');
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
        
        // Get updated template
        $stmt = $ai_pdo->prepare("SELECT * FROM ai_response_templates WHERE id = ?");
        $stmt->execute([$input['id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template updated successfully',
            'template' => $template
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete template
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('Missing required parameter: id');
        }
        
        // Check if template exists
        $stmt = $ai_pdo->prepare("SELECT * FROM ai_response_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception('Template not found');
        }
        
        $stmt = $ai_pdo->prepare("DELETE FROM ai_response_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template deleted successfully',
            'deleted_template' => $template
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