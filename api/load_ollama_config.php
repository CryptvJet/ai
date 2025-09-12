<?php
/**
 * Load Ollama Configuration API
 * Loads Ollama server configuration from file system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Load from database directly
    require_once __DIR__ . '/db_config.php';
    global $ai_pdo;
    
    $sql = "SELECT * FROM ollama_config WHERE id = 1";
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch();
    
    if ($config) {
        echo json_encode([
            'success' => true,
            'config' => [
                'host' => $config['host'],
                'port' => (int)$config['port'],
                'protocol' => $config['protocol'],
                'default_model' => $config['default_model'],
                'updated_at' => $config['updated_at']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'config' => [
                'host' => 'localhost',
                'port' => 11434,
                'protocol' => 'http',
                'default_model' => 'llama3.2',
                'updated_at' => null
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load Ollama configuration',
        'message' => $e->getMessage()
    ]);
}
?>