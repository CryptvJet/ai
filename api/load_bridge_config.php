<?php
/**
 * Load Bridge Configuration API
 * Loads bridge configuration from file system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $configPath = __DIR__ . '/../../data/pws/bridge_config.json';
    
    if (file_exists($configPath)) {
        $configData = file_get_contents($configPath);
        $config = json_decode($configData, true);
        
        if ($config) {
            echo json_encode([
                'success' => true,
                'config' => [
                    'host' => $config['host'] ?? 'localhost',
                    'port' => $config['port'] ?? '8080',
                    'api_key' => $config['api_key'] ?? '',
                    'type' => $config['type'] ?? 'HTTP',
                    'enabled' => $config['enabled'] ?? true,
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
            'port' => '8080',
            'api_key' => '',
            'type' => 'HTTP',
            'enabled' => true,
            'updated_at' => null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load bridge configuration',
        'message' => $e->getMessage()
    ]);
}
?>