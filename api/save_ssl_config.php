<?php
/**
 * Save SSL Configuration API
 * Saves SSL configuration to file system
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
        'enabled' => isset($input['enabled']) ? (bool)$input['enabled'] : false,
        'port' => isset($input['port']) ? (int)$input['port'] : 8443,
        'cert' => 'server.crt',
        'key' => 'server.key',
        'updated_at' => date('c'),
        'updated_by' => 'web_admin',
        'description' => 'SSL configuration for PC Bridge HTTPS server'
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
    
    // Save SSL configuration to file
    $configPath = $pwsDir . '/ssl_config.json';
    $configJson = json_encode($config, JSON_PRETTY_PRINT);
    
    if (file_put_contents($configPath, $configJson) === false) {
        throw new Exception('Failed to write SSL configuration file');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL configuration saved successfully',
        'config' => $config,
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