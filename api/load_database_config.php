<?php
/**
 * Load Database Configuration API
 * Loads database connection settings from ai_database_configs table
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // For AI database configs, we need to connect to the AI database directly
    // Use the ai_db_config.json to get AI database connection
    $ai_config_path = __DIR__ . '/../data/pws/ai_db_config.json';
    if (!file_exists($ai_config_path)) {
        throw new Exception('AI database configuration file not found');
    }
    
    $ai_config = json_decode(file_get_contents($ai_config_path), true);
    if (!$ai_config) {
        throw new Exception('Invalid AI database configuration');
    }
    
    // Connect to AI database using config file
    $dsn = "mysql:host={$ai_config['Server']};port=3306;dbname={$ai_config['Database']};charset=utf8mb4";
    $ai_pdo = new PDO($dsn, $ai_config['Username'], $ai_config['Password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $config_type = $_GET['type'] ?? 'all'; // 'pulsecore', 'ai', or 'all'
    
    if ($config_type === 'all') {
        // Load all database configurations
        $sql = "SELECT config_name, config_type, server_host, server_port, database_name, username, 
                       enabled, last_tested, test_result, test_error, updated_at
                FROM ai_database_configs 
                ORDER BY config_type, config_name";
        $stmt = $ai_pdo->prepare($sql);
        $stmt->execute();
        $configs = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'configs' => $configs
        ]);
        
    } else {
        // Load specific configuration type
        if (!in_array($config_type, ['pulsecore', 'ai'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid config_type']);
            exit;
        }
        
        $sql = "SELECT config_name, config_type, server_host, server_port, database_name, username, 
                       CASE WHEN password IS NOT NULL AND password != '' THEN 1 ELSE 0 END as has_password,
                       enabled, last_tested, test_result, test_error, updated_at
                FROM ai_database_configs 
                WHERE config_type = :config_type
                ORDER BY config_name";
        $stmt = $ai_pdo->prepare($sql);
        $stmt->execute([':config_type' => $config_type]);
        $config = $stmt->fetch();
        
        if ($config) {
            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Configuration not found',
                'config_type' => $config_type
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load database configuration',
        'message' => $e->getMessage()
    ]);
}
?>