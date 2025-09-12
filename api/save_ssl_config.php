<?php
/**
 * Save SSL Configuration API
 * Saves SSL configuration to database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate and sanitize inputs
    $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : false;
    $port = isset($input['port']) ? (int)$input['port'] : 8443;
    $cert_filename = 'server.crt';
    $key_filename = 'server.key';
    $updated_by = 'web_admin';
    
    // Insert or update SSL configuration in database (always use id=1 for single config)
    $sql = "INSERT INTO ai_ssl_config (id, enabled, port, cert_filename, key_filename, updated_by) 
            VALUES (1, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            enabled = VALUES(enabled), 
            port = VALUES(port), 
            cert_filename = VALUES(cert_filename), 
            key_filename = VALUES(key_filename),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP";
            
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute([$enabled, $port, $cert_filename, $key_filename, $updated_by]);
    
    // Get the saved configuration
    $getConfigSql = "SELECT * FROM ai_ssl_config WHERE id = 1";
    $configStmt = $ai_pdo->prepare($getConfigSql);
    $configStmt->execute();
    $config = $configStmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL configuration saved successfully',
        'config' => [
            'enabled' => (bool)$config['enabled'],
            'port' => (int)$config['port'],
            'cert_filename' => $config['cert_filename'],
            'key_filename' => $config['key_filename'],
            'cert_uploaded' => (bool)$config['cert_uploaded'],
            'key_uploaded' => (bool)$config['key_uploaded'],
            'updated_at' => $config['updated_at'],
            'updated_by' => $config['updated_by']
        ],
        'note' => 'Restart the bridge server to apply changes'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save SSL configuration',
        'message' => $e->getMessage()
    ]);
}
?>