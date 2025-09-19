<?php
/**
 * Admin Learning Data API
 * Manages AI learning data for the Learning tab
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
        // Get learning data with patterns and frequency
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        // Get learning patterns grouped by pattern type (using correct schema)
        $stmt = $ai_pdo->prepare("
            SELECT 
                l.id,
                l.pattern_type,
                l.pattern_data,
                l.confidence_score,
                l.usage_count,
                l.last_used,
                l.created_at,
                COUNT(*) OVER (PARTITION BY l.pattern_type) as frequency,
                ROW_NUMBER() OVER (PARTITION BY l.pattern_type ORDER BY l.created_at DESC) as rn
            FROM ai_learning l
            WHERE l.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY l.confidence_score DESC, l.usage_count DESC, l.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([(int)$limit, (int)$offset]);
        $learning_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON pattern_data and format for display
        foreach ($learning_data as &$entry) {
            $pattern_data = json_decode($entry['pattern_data'], true);
            if ($pattern_data) {
                $entry['user_message'] = $pattern_data['user_message'] ?? '';
                $entry['ai_response'] = $pattern_data['ai_response'] ?? '';
                $entry['source'] = $pattern_data['source'] ?? 'unknown';
                $entry['conversation_id'] = $pattern_data['conversation_id'] ?? null;
            }
            // Add display properties for frontend
            $entry['pattern'] = $entry['pattern_type'];
            $entry['confidence'] = $entry['confidence_score'];
        }
        unset($entry);
        
        // Get summary stats
        $stmt = $ai_pdo->query("
            SELECT 
                COUNT(*) as total_entries,
                COUNT(DISTINCT pattern_type) as unique_patterns,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM ai_learning
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $learning_data,
            'stats' => $stats,
            'pagination' => [
                'limit' => (int)$limit,
                'offset' => (int)$offset,
                'total' => (int)$stats['total_entries']
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Convert learning entry to template
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['learning_id'])) {
            throw new Exception('Missing required field: learning_id');
        }
        
        // Get learning entry
        $stmt = $ai_pdo->prepare("SELECT * FROM ai_learning WHERE id = ?");
        $stmt->execute([$input['learning_id']]);
        $learning = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$learning) {
            throw new Exception('Learning entry not found');
        }
        
        // Parse pattern data from JSON
        $pattern_data = json_decode($learning['pattern_data'], true);
        $user_message = $pattern_data['user_message'] ?? '';
        $ai_response = $pattern_data['ai_response'] ?? '';
        
        // Create template from learning data
        $trigger_pattern = $input['trigger_pattern'] ?? $user_message;
        $response_text = $input['response_text'] ?? $ai_response;
        $category = $input['category'] ?? 'learned';
        $priority = $input['priority'] ?? 75; // Higher priority for learned patterns
        
        $stmt = $ai_pdo->prepare("
            INSERT INTO ai_response_templates (trigger_pattern, response_text, category, priority, active, created_from_learning)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([$trigger_pattern, $response_text, $category, $priority, $input['learning_id']]);
        $template_id = $ai_pdo->lastInsertId();
        
        // Mark learning entry as converted
        $stmt = $ai_pdo->prepare("
            UPDATE ai_learning 
            SET converted_to_template = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$template_id, $input['learning_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Learning pattern converted to template successfully',
            'template_id' => $template_id,
            'learning_id' => $input['learning_id']
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Dismiss/delete learning entry
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('Missing required parameter: id');
        }
        
        $stmt = $ai_pdo->prepare("DELETE FROM ai_learning WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Learning entry dismissed successfully'
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