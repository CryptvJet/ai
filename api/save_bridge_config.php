<?php
/**
 * Save Bridge Configuration API
 * Saves bridge configuration to file system for SmartAIRouter
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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate and sanitize inputs
    $config = [
        'host' => $input['host'] ?? 'localhost',
        'port' => $input['port'] ?? '3001',
        'api_key' => $input['api_key'] ?? '',
        'type' => $input['type'] ?? 'HTTP',
        'enabled' => isset($input['enabled']) ? (bool)$input['enabled'] : true,
        'updated_at' => date('c'),
        'updated_by' => 'admin_panel'
    ];
    
    // Ensure data directory exists
    $dataDir = __DIR__ . '/../../data';
    $pwsDir = $dataDir . '/pws';
    
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    if (!is_dir($pwsDir)) {
        mkdir($pwsDir, 0755, true);
    }
    
    // Save configuration to file
    $configPath = $pwsDir . '/bridge_config.json';
    $configJson = json_encode($config, JSON_PRETTY_PRINT);
    
    if (file_put_contents($configPath, $configJson) === false) {
        throw new Exception('Failed to write configuration file');
    }
    
    // Also update SSL configuration in database if HTTPS
    if ($config['type'] === 'HTTPS') {
        try {
            require_once __DIR__ . '/db_config.php';
            $ai_pdo->exec("USE `vemite5_pulse-core-ai`");
            
            $updateSql = "INSERT INTO ai_ssl_config (id, port, enabled) 
                          VALUES (1, :port, 1)
                          ON DUPLICATE KEY UPDATE 
                          port = :port2, enabled = 1";
            
            $stmt = $ai_pdo->prepare($updateSql);
            $stmt->execute([
                ':port' => (int)$config['port'],
                ':port2' => (int)$config['port']
            ]);
            
        } catch (Exception $e) {
            error_log("Warning: Could not update SSL config: " . $e->getMessage());
            // Don't fail the whole operation if SSL update fails
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Bridge configuration saved successfully',
        'config' => $config
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save bridge configuration',
        'message' => $e->getMessage()
    ]);
}
?>