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

try {
    require_once 'db_config.php';
    require_once 'login.php';
    
    // Check if $ai_pdo exists
    if (!isset($ai_pdo)) {
        throw new Exception('AI database connection not available');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database configuration error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
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
        $auth = new AdminAuth($ai_pdo);
        $result = $auth->validateSession($input['token']);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Session validation error: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>