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
    $configPath = __DIR__ . '/../../data/pws/ollama_config.json';
    
    if (file_exists($configPath)) {
        $configData = file_get_contents($configPath);
        $config = json_decode($configData, true);
        
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
            exit;
        }
    }
    
    // Return default configuration if file doesn't exist
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
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load Ollama configuration',
        'message' => $e->getMessage()
    ]);
}
?>