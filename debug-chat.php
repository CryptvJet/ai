<?php
// Debug version to test database saving
header('Content-Type: application/json');

require_once 'api/db_config.php';

echo "Testing database connections...\n";

try {
    // Test basic connection
    $stmt = $ai_pdo->query("SELECT 1");
    echo "✓ Database connection works\n";
    
    // Test if tables exist
    $stmt = $ai_pdo->query("SHOW TABLES LIKE 'ai_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ AI tables found: " . implode(', ', $tables) . "\n";
    
    // Test creating a conversation
    $session_id = 'test_' . time();
    $stmt = $ai_pdo->prepare("
        INSERT INTO ai_conversations (session_id, user_id, session_name, status) 
        VALUES (?, 'debug_user', 'Debug Session', 'active')
    ");
    $stmt->execute([$session_id]);
    $conversation_id = $ai_pdo->lastInsertId();
    echo "✓ Created conversation ID: $conversation_id\n";
    
    // Test creating a message
    $stmt = $ai_pdo->prepare("
        INSERT INTO ai_messages (conversation_id, message_type, content, ai_mode, metadata) 
        VALUES (?, 'user', 'Test message', 'chill', '{}')
    ");
    $stmt->execute([$conversation_id]);
    echo "✓ Created user message\n";
    
    // Test creating AI response
    $stmt = $ai_pdo->prepare("
        INSERT INTO ai_messages (conversation_id, message_type, content, ai_mode, metadata) 
        VALUES (?, 'ai', 'Test response', 'chill', '{}')
    ");
    $stmt->execute([$conversation_id]);
    echo "✓ Created AI response\n";
    
    // Check if messages were saved
    $stmt = $ai_pdo->prepare("SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ?");
    $stmt->execute([$conversation_id]);
    $count = $stmt->fetchColumn();
    echo "✓ Found $count messages in conversation\n";
    
    // Show recent conversations
    $stmt = $ai_pdo->query("
        SELECT c.session_id, c.started_at, c.total_messages, COUNT(m.id) as actual_messages
        FROM ai_conversations c 
        LEFT JOIN ai_messages m ON c.id = m.conversation_id 
        GROUP BY c.id 
        ORDER BY c.started_at DESC 
        LIMIT 5
    ");
    $conversations = $stmt->fetchAll();
    
    echo "\nRecent conversations:\n";
    foreach ($conversations as $conv) {
        echo "- Session: {$conv['session_id']}, Started: {$conv['started_at']}, Messages: {$conv['actual_messages']}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "✗ Stack trace: " . $e->getTraceAsString() . "\n";
}
?>