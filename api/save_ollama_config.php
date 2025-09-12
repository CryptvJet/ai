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
    
    // Include database configuration
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/ollama_config_loader.php';
    
    // Validate and sanitize inputs
    $host = $input['host'] ?? 'localhost';
    $port = (int)($input['port'] ?? 11434);
    $protocol = $input['protocol'] ?? 'http';
    $default_model = $input['default_model'] ?? 'llama3.2';
    
    global $ai_pdo;
    
    // Load database configuration to get the correct database name
    $db_config_path = __DIR__ . '/../data/pws/ai_db_config.json';
    if (!file_exists($db_config_path)) {
        throw new Exception('Database configuration file not found');
    }
    
    $db_config = json_decode(file_get_contents($db_config_path), true);
    if (!$db_config) {
        throw new Exception('Invalid database configuration');
    }
    
    $database_name = $db_config['Database'];
    $ai_pdo->exec("USE `$database_name`");
    
    // Save configuration to database
    $sql = "INSERT INTO ollama_config (id, host, port, protocol, default_model, updated_at) 
            VALUES (1, :host1, :port1, :protocol1, :model1, NOW())
            ON DUPLICATE KEY UPDATE 
            host = :host2,
            port = :port2, 
            protocol = :protocol2,
            default_model = :model2,
            updated_at = NOW()";
    
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute([
        ':host1' => $host,
        ':port1' => $port,
        ':protocol1' => $protocol,
        ':model1' => $default_model,
        ':host2' => $host,
        ':port2' => $port,
        ':protocol2' => $protocol,
        ':model2' => $default_model
    ]);
    
    // Clear the config cache so it gets reloaded
    OllamaConfigLoader::clearCache();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ollama configuration saved successfully',
        'config' => [
            'host' => $host,
            'port' => $port,
            'protocol' => $protocol,
            'default_model' => $default_model
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save Ollama configuration',
        'message' => $e->getMessage()
    ]);
}
?>