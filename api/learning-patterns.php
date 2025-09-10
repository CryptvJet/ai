<?php
/**
 * Learning Patterns API
 * Manages AI learning patterns and insights
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get learning patterns from database
        $stmt = $ai_pdo->query("
            SELECT 
                id,
                pattern_type,
                pattern_data,
                confidence_score,
                usage_count,
                last_used,
                created_at
            FROM ai_learning 
            ORDER BY confidence_score DESC, usage_count DESC
            LIMIT 20
        ");
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format patterns for display
        $formattedPatterns = array_map(function($pattern) {
            $data = json_decode($pattern['pattern_data'], true);
            return [
                'id' => $pattern['id'],
                'type' => $pattern['pattern_type'],
                'title' => $data['title'] ?? ucfirst(str_replace('_', ' ', $pattern['pattern_type'])),
                'description' => $data['description'] ?? 'AI learning pattern',
                'confidence' => round($pattern['confidence_score'] * 100),
                'usage_count' => $pattern['usage_count'],
                'last_used' => $pattern['last_used'],
                'created_at' => $pattern['created_at'],
                'data' => $data
            ];
        }, $patterns);
        
        // If no patterns exist, create some sample ones
        if (empty($formattedPatterns)) {
            $samplePatterns = [
                [
                    'id' => 'sample_1',
                    'type' => 'conversation_pattern',
                    'title' => 'PulseCore Data Queries',
                    'description' => 'Users frequently ask about nova events and complexity analysis',
                    'confidence' => 85,
                    'usage_count' => 12,
                    'last_used' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ],
                [
                    'id' => 'sample_2', 
                    'type' => 'user_preference',
                    'title' => 'Variable Search Patterns',
                    'description' => 'Users prefer searching by symbol rather than description',
                    'confidence' => 72,
                    'usage_count' => 8,
                    'last_used' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
                ],
                [
                    'id' => 'sample_3',
                    'type' => 'response_optimization', 
                    'title' => 'Greeting Effectiveness',
                    'description' => 'Personalized greetings with names get better engagement',
                    'confidence' => 91,
                    'usage_count' => 25,
                    'last_used' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                ]
            ];
            $formattedPatterns = $samplePatterns;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'patterns' => $formattedPatterns,
                'total_patterns' => count($formattedPatterns),
                'learning_enabled' => true
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle pattern updates/creation
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            exit;
        }
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'reinforce':
                    $pattern_id = $input['pattern_id'];
                    $stmt = $ai_pdo->prepare("
                        UPDATE ai_learning 
                        SET confidence_score = LEAST(1.0, confidence_score + 0.1),
                            usage_count = usage_count + 1,
                            last_used = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$pattern_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Pattern reinforced']);
                    break;
                    
                case 'dismiss':
                    $pattern_id = $input['pattern_id'];
                    $stmt = $ai_pdo->prepare("DELETE FROM ai_learning WHERE id = ?");
                    $stmt->execute([$pattern_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Pattern dismissed']);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'No action specified']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Clear all learning data
        $stmt = $ai_pdo->prepare("DELETE FROM ai_learning");
        $result = $stmt->execute();
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'All learning data cleared successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'Failed to clear learning data'
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Learning patterns failed: ' . $e->getMessage()
    ]);
}
?>