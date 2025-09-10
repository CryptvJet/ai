<?php
/**
 * Get Conversation API
 * Fetches a specific conversation with all its messages
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    // Get conversation ID from query parameters
    $conversationId = $_GET['id'] ?? null;
    
    if (!$conversationId) {
        echo json_encode([
            'success' => false,
            'error' => 'Conversation ID is required'
        ]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete conversation and all its messages
        $ai_pdo->beginTransaction();
        
        try {
            // First, delete all messages for this conversation
            $stmt = $ai_pdo->prepare("DELETE FROM ai_messages WHERE conversation_id = ?");
            $stmt->execute([$conversationId]);
            
            // Then delete the conversation itself
            $stmt = $ai_pdo->prepare("DELETE FROM ai_conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            
            $ai_pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Conversation deleted successfully'
            ]);
            exit;
            
        } catch (Exception $e) {
            $ai_pdo->rollBack();
            throw $e;
        }
    }
    
    // GET method: Get conversation details
    $stmt = $ai_pdo->prepare("
        SELECT 
            id,
            session_id,
            user_id,
            started_at,
            ended_at,
            session_name,
            total_messages,
            status,
            metadata,
            TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW())) as duration_minutes
        FROM ai_conversations 
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode([
            'success' => false,
            'error' => 'Conversation not found'
        ]);
        exit;
    }
    
    // Get all messages for this conversation
    $stmt = $ai_pdo->prepare("
        SELECT 
            id,
            message_type as role,
            content,
            timestamp,
            metadata,
            processing_time_ms,
            ai_mode,
            source
        FROM ai_messages 
        WHERE conversation_id = ?
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format messages for display
    $formattedMessages = array_map(function($message) {
        return [
            'id' => $message['id'],
            'role' => $message['role'] === 'ai' ? 'assistant' : $message['role'],
            'content' => $message['content'],
            'timestamp' => $message['timestamp'],
            'processing_time_ms' => $message['processing_time_ms'],
            'ai_mode' => $message['ai_mode'],
            'source' => $message['source']
        ];
    }, $messages);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'conversation' => [
                'id' => $conversation['id'],
                'session_id' => $conversation['session_id'],
                'user_id' => $conversation['user_id'],
                'started_at' => $conversation['started_at'],
                'ended_at' => $conversation['ended_at'],
                'session_name' => $conversation['session_name'],
                'total_messages' => $conversation['total_messages'],
                'status' => $conversation['status'],
                'duration_minutes' => $conversation['duration_minutes'],
                'metadata' => $conversation['metadata'] ? json_decode($conversation['metadata'], true) : null
            ],
            'messages' => $formattedMessages
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch conversation: ' . $e->getMessage()
    ]);
}
?>