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
    // Include database configuration
    require_once __DIR__ . '/db_config.php';
    
    // Force switch to the correct database
    $ai_pdo->exec("USE `vemite5_pulse-core-ai`");
    
    // Get Ollama configuration from database
    $sql = "SELECT * FROM ollama_config WHERE id = 1";
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch();
    
    if ($config) {
        echo json_encode([
            'success' => true,
            'config' => [
                'host' => $config['host'] ?? 'localhost',
                'port' => (int)($config['port'] ?? 11434),
                'protocol' => $config['protocol'] ?? 'http',
                'default_model' => $config['default_model'] ?? 'llama3.2',
                'updated_at' => $config['updated_at'] ?? null
            ]
        ]);
    } else {
        // Return default configuration if no config exists
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