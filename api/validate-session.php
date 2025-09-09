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

require_once 'db_config.php';
require_once 'login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['token'])) {
        echo json_encode(['success' => false, 'error' => 'Token required']);
        exit;
    }
    
    $auth = new AdminAuth($ai_pdo);
    $result = $auth->validateSession($input['token']);
    
    echo json_encode($result);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>