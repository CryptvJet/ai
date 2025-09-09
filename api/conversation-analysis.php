<?php
/**
 * Conversation Analysis API
 * Provides detailed analytics on chat conversations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    // Overall conversation statistics
    $stmt = $ai_pdo->query("
        SELECT 
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(m.id) as total_messages,
            AVG(TIMESTAMPDIFF(MINUTE, c.started_at, MAX(m.timestamp))) as avg_conversation_length_minutes,
            AVG(c.total_messages) as avg_messages_per_conversation,
            COUNT(DISTINCT c.session_id) as unique_users,
            SUM(CASE WHEN m.processing_time_ms IS NOT NULL THEN m.processing_time_ms ELSE 0 END) / 
                COUNT(CASE WHEN m.processing_time_ms IS NOT NULL THEN 1 END) as avg_response_time_ms
        FROM ai_conversations c
        LEFT JOIN ai_messages m ON c.id = m.conversation_id
        WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Message type breakdown
    $stmt = $ai_pdo->query("
        SELECT 
            message_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as percentage
        FROM ai_messages m
        JOIN ai_conversations c ON m.conversation_id = c.id
        WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY message_type
    ");
    $message_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // AI Mode usage
    $stmt = $ai_pdo->query("
        SELECT 
            ai_mode,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as percentage
        FROM ai_messages m
        JOIN ai_conversations c ON m.conversation_id = c.id
        WHERE m.message_type = 'ai' AND c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY ai_mode
    ");
    $ai_modes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conversation activity by day
    $stmt = $ai_pdo->query("
        SELECT 
            DATE(c.created_at) as date,
            COUNT(DISTINCT c.id) as conversations,
            COUNT(m.id) as total_messages,
            COUNT(CASE WHEN m.message_type = 'user' THEN 1 END) as user_messages,
            COUNT(CASE WHEN m.message_type = 'ai' THEN 1 END) as ai_messages
        FROM ai_conversations c
        LEFT JOIN ai_messages m ON c.id = m.conversation_id
        WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY DATE(c.created_at)
        ORDER BY date DESC
    ");
    $daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most active hours
    $stmt = $ai_pdo->query("
        SELECT 
            HOUR(c.created_at) as hour,
            COUNT(DISTINCT c.id) as conversations,
            COUNT(m.id) as messages
        FROM ai_conversations c
        LEFT JOIN ai_messages m ON c.id = m.conversation_id
        WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(c.created_at)
        ORDER BY conversations DESC
    ");
    $hourly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Content analysis - most common words in user messages
    $stmt = $ai_pdo->query("
        SELECT 
            LOWER(m.content) as content
        FROM ai_messages m
        JOIN ai_conversations c ON m.conversation_id = c.id
        WHERE m.message_type = 'user' 
        AND c.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND LENGTH(m.content) > 3
    ");
    $user_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Analyze common topics/keywords
    $topics = [];
    $pulsecore_keywords = ['nova', 'pulsecore', 'complexity', 'energy', 'pattern', 'simulation', 'analysis'];
    $variable_keywords = ['variable', 'calculation', 'math', 'equation', 'formula'];
    $general_keywords = ['help', 'what', 'how', 'explain', 'show', 'tell'];
    
    foreach ($user_messages as $msg) {
        $content = strtolower($msg['content']);
        
        // Count PulseCore related topics
        foreach ($pulsecore_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $topics['pulsecore'] = ($topics['pulsecore'] ?? 0) + 1;
                break;
            }
        }
        
        // Count Variables related topics
        foreach ($variable_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $topics['variables'] = ($topics['variables'] ?? 0) + 1;
                break;
            }
        }
        
        // Count General help topics
        foreach ($general_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $topics['general_help'] = ($topics['general_help'] ?? 0) + 1;
                break;
            }
        }
    }
    
    // Recent conversation samples
    $stmt = $ai_pdo->query("
        SELECT 
            c.id,
            c.session_id,
            c.user_id,
            c.started_at,
            c.total_messages,
            c.status,
            (SELECT m.content FROM ai_messages m WHERE m.conversation_id = c.id AND m.message_type = 'user' ORDER BY m.timestamp LIMIT 1) as first_user_message,
            (SELECT m.content FROM ai_messages m WHERE m.conversation_id = c.id AND m.message_type = 'ai' ORDER BY m.timestamp DESC LIMIT 1) as last_ai_message
        FROM ai_conversations c
        ORDER BY c.started_at DESC
        LIMIT 10
    ");
    $recent_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // User preferences analysis
    $stmt = $ai_pdo->query("
        SELECT 
            COUNT(*) as total_named_users,
            COUNT(CASE WHEN user_name IS NOT NULL THEN 1 END) as users_with_names,
            AVG(TIMESTAMPDIFF(DAY, created_at, last_interaction)) as avg_days_between_interactions
        FROM ai_user_preferences
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $user_preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'analysis_date' => date('Y-m-d H:i:s'),
            'time_period' => '30 days',
            'overall_stats' => [
                'total_conversations' => (int)$overall_stats['total_conversations'],
                'total_messages' => (int)$overall_stats['total_messages'],
                'unique_users' => (int)$overall_stats['unique_users'],
                'avg_conversation_length_minutes' => round($overall_stats['avg_conversation_length_minutes'], 1),
                'avg_messages_per_conversation' => round($overall_stats['avg_messages_per_conversation'], 1),
                'avg_response_time_ms' => round($overall_stats['avg_response_time_ms'])
            ],
            'message_breakdown' => $message_types,
            'ai_mode_usage' => $ai_modes,
            'daily_activity' => $daily_activity,
            'hourly_activity' => $hourly_activity,
            'topic_analysis' => $topics,
            'user_engagement' => [
                'total_named_users' => (int)$user_preferences['total_named_users'],
                'users_with_names' => (int)$user_preferences['users_with_names'],
                'avg_days_between_interactions' => round($user_preferences['avg_days_between_interactions'], 1)
            ],
            'recent_conversations' => $recent_conversations
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Analysis failed: ' . $e->getMessage()
    ]);
}
?>