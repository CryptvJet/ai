<?php
/**
 * Update Conversation Status API
 * Updates conversation statuses based on activity
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    // Mark conversations as completed if they haven't had activity in last 2 hours
    $stmt = $ai_pdo->prepare("
        UPDATE ai_conversations 
        SET status = 'completed', ended_at = NOW()
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
    $updated_count = $stmt->rowCount();
    
    // Get current status counts
    $stmt = $ai_pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM ai_conversations 
        GROUP BY status
    ");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'updated_conversations' => $updated_count,
        'message' => "Updated $updated_count conversations from active to completed",
        'current_status_counts' => $status_counts
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update conversation statuses: ' . $e->getMessage()
    ]);
}
?>