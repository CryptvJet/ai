<?php
/**
 * Save Ollama Configuration API
 * Saves Ollama server configuration to file system
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
        'port' => (int)($input['port'] ?? 11434),
        'protocol' => $input['protocol'] ?? 'http',
        'default_model' => $input['default_model'] ?? 'llama3.2',
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
    $configPath = $pwsDir . '/ollama_config.json';
    $configJson = json_encode($config, JSON_PRETTY_PRINT);
    
    if (file_put_contents($configPath, $configJson) === false) {
        throw new Exception('Failed to write configuration file');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ollama configuration saved successfully',
        'config' => $config
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save Ollama configuration',
        'message' => $e->getMessage()
    ]);
}
?>