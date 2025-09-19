<?php
/**
 * Admin Session Validation API
 * Validates admin session tokens
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log that we started
file_put_contents('/tmp/validate-debug.log', date('Y-m-d H:i:s') . " - validate-session.php called\n", FILE_APPEND);

try {
    require_once 'db_config.php';
    file_put_contents('/tmp/validate-debug.log', date('Y-m-d H:i:s') . " - db_config loaded\n", FILE_APPEND);
    
    require_once 'login.php';
    file_put_contents('/tmp/validate-debug.log', date('Y-m-d H:i:s') . " - login.php loaded\n", FILE_APPEND);
    
    // Check if $ai_pdo exists
    if (!isset($ai_pdo)) {
        throw new Exception('AI database connection not available');
    }
    
    file_put_contents('/tmp/validate-debug.log', date('Y-m-d H:i:s') . " - ai_pdo verified\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents('/tmp/validate-debug.log', date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Database configuration error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    file_put_contents('/tmp/validate-debug.log', date('Y-m-d H:i:s') . " - PHP Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'PHP error: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['token'])) {
        echo json_encode(['success' => false, 'error' => 'Token required']);
        exit;
    }
    
    try {
        // Debug log the validation attempt
        $stmt = $ai_pdo->prepare("INSERT INTO debug_logs (timestamp, level, source, message, data) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([date('Y-m-d H:i:s'), 'info', 'validate-session', 'Starting session validation', json_encode(['token_prefix' => substr($input['token'], 0, 16)])]);
        
        $auth = new AdminAuth($ai_pdo);
        $result = $auth->validateSession($input['token']);
        
        // Debug log the result
        $stmt->execute([date('Y-m-d H:i:s'), 'info', 'validate-session', 'Validation result', json_encode($result)]);
        
        echo json_encode($result);
    } catch (Exception $e) {
        // Debug log the error
        $error_msg = 'Session validation error: ' . $e->getMessage();
        $stmt = $ai_pdo->prepare("INSERT INTO debug_logs (timestamp, level, source, message, data) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([date('Y-m-d H:i:s'), 'error', 'validate-session', $error_msg, json_encode(['exception' => $e->getTraceAsString()])]);
        
        echo json_encode(['success' => false, 'error' => $error_msg]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>