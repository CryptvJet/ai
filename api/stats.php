<?php
/**
 * AI Statistics API
 * Provides conversation and usage statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db_config.php';

try {
    // Get conversation statistics
    $stmt = $ai_pdo->query("
        SELECT 
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(m.id) as total_messages,
            AVG(m.processing_time_ms) as avg_response_time,
            SUM(CASE WHEN m.message_type = 'ai' AND m.processing_time_ms IS NOT NULL THEN 1 ELSE 0 END) as successful_responses,
            COUNT(CASE WHEN m.message_type = 'ai' THEN 1 END) as total_ai_responses
        FROM ai_conversations c
        LEFT JOIN ai_messages m ON c.id = m.conversation_id
        WHERE c.started_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate success rate
    $success_rate = 0;
    if ($stats['total_ai_responses'] > 0) {
        $success_rate = round(($stats['successful_responses'] / $stats['total_ai_responses']) * 100, 1);
    }
    
    // Get recent conversations
    $stmt = $ai_pdo->query("
        SELECT 
            c.id,
            c.session_id,
            c.user_id,
            c.started_at,
            c.ended_at,
            c.total_messages,
            c.status,
            TIMESTAMPDIFF(MINUTE, c.started_at, COALESCE(c.ended_at, NOW())) as duration_minutes,
            COUNT(m.id) as actual_message_count
        FROM ai_conversations c
        LEFT JOIN ai_messages m ON c.id = m.conversation_id
        GROUP BY c.id
        ORDER BY c.started_at DESC
        LIMIT 10
    ");
    $conversations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format conversations for display
    $recent_conversations = array_map(function($conv) {
        return [
            'id' => $conv['id'],
            'session_id' => $conv['session_id'],
            'user_id' => $conv['user_id'],
            'started_at' => $conv['started_at'],
            'ended_at' => $conv['ended_at'],
            'total_messages' => $conv['total_messages'],
            'status' => $conv['status'],
            'duration_minutes' => $conv['duration_minutes']
        ];
    }, $conversations_raw);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_conversations' => (int)$stats['total_conversations'],
            'total_messages' => (int)$stats['total_messages'],
            'avg_response_time' => $stats['avg_response_time'] ? round($stats['avg_response_time']) : null,
            'success_rate' => $success_rate,
            'recent_conversations' => $recent_conversations
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>