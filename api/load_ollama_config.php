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
    // Use centralized config loader
    require_once __DIR__ . '/ollama_config_loader.php';
    $config = OllamaConfigLoader::getConfig();
    
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load Ollama configuration',
        'message' => $e->getMessage()
    ]);
}
?>