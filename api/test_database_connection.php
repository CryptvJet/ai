<?php
/**
 * Test Database Connection API
 * Actually tests the database connection using saved credentials
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $config_type = $_GET['type'] ?? 'ai'; // 'pulsecore' or 'ai'
    
    if (!in_array($config_type, ['pulsecore', 'ai'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid config_type']);
        exit;
    }
    
    // Load the saved configuration first
    $ai_config_path = __DIR__ . '/../data/pws/ai_db_config.json';
    if (!file_exists($ai_config_path)) {
        throw new Exception('AI database configuration file not found');
    }
    
    $ai_config = json_decode(file_get_contents($ai_config_path), true);
    if (!$ai_config) {
        throw new Exception('Invalid AI database configuration');
    }
    
    // Connect to AI database to get the saved config
    $dsn = "mysql:host={$ai_config['Server']};port=3306;dbname={$ai_config['Database']};charset=utf8mb4";
    $ai_pdo = new PDO($dsn, $ai_config['Username'], $ai_config['Password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Get the saved database configuration
    $sql = "SELECT * FROM ai_database_configs WHERE config_type = :config_type";
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute([':config_type' => $config_type]);
    $config = $stmt->fetch();
    
    if (!$config) {
        echo json_encode([
            'success' => false,
            'error' => 'No saved configuration found for ' . $config_type
        ]);
        exit;
    }
    
    // Now ACTUALLY TEST the database connection using the saved credentials
    $start_time = microtime(true);
    
    $test_dsn = "mysql:host={$config['server_host']};port={$config['server_port']};dbname={$config['database_name']};charset=utf8mb4";
    $test_pdo = new PDO($test_dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    // Test the connection with a simple query
    $test_stmt = $test_pdo->prepare("SELECT 1 as test");
    $test_stmt->execute();
    $result = $test_stmt->fetch();
    
    $end_time = microtime(true);
    $response_time = round(($end_time - $start_time) * 1000, 2);
    
    if ($result && $result['test'] == 1) {
        // Update the test result in the config table
        $update_sql = "UPDATE ai_database_configs 
                      SET test_result = 'success', last_tested = NOW(), test_error = NULL 
                      WHERE config_type = :config_type";
        $update_stmt = $ai_pdo->prepare($update_sql);
        $update_stmt->execute([':config_type' => $config_type]);
        
        echo json_encode([
            'success' => true,
            'message' => ucfirst($config_type) . ' database connection successful',
            'config' => [
                'host' => $config['server_host'],
                'database' => $config['database_name'],
                'port' => $config['server_port']
            ],
            'response_time_ms' => $response_time,
            'test_result' => 'success'
        ]);
    } else {
        throw new Exception('Database test query failed');
    }
    
} catch (Exception $e) {
    // Update the test result as failed
    if (isset($ai_pdo) && isset($config_type)) {
        try {
            $update_sql = "UPDATE ai_database_configs 
                          SET test_result = 'failed', last_tested = NOW(), test_error = :error 
                          WHERE config_type = :config_type";
            $update_stmt = $ai_pdo->prepare($update_sql);
            $update_stmt->execute([
                ':config_type' => $config_type,
                ':error' => $e->getMessage()
            ]);
        } catch (Exception $update_error) {
            // Ignore update errors
        }
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Database connection test failed',
        'message' => $e->getMessage(),
        'test_result' => 'failed'
    ]);
}
?>