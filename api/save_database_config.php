<?php
/**
 * Save Database Configuration API
 * Saves database connection settings to ai_database_configs table
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
    
    // Validate required fields
    $config_type = $input['config_type'] ?? null; // 'pulsecore' or 'ai'
    $server_host = $input['server_host'] ?? '';
    $database_name = $input['database_name'] ?? '';
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $server_port = (int)($input['server_port'] ?? 3306);
    
    if (!$config_type || !in_array($config_type, ['pulsecore', 'ai'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid config_type. Must be pulsecore or ai']);
        exit;
    }
    
    if (empty($server_host) || empty($database_name) || empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields: server_host, database_name, username']);
        exit;
    }
    
    $config_name = $config_type . '_main';
    
    // For AI config: use the provided credentials to connect to that database
    // For PulseCore config: use AI database connection (which should be configured first)
    if ($config_type === 'ai') {
        // Create new connection using the provided AI database credentials
        $dsn = "mysql:host={$server_host};port={$server_port};dbname={$database_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } else {
        // For PulseCore config, save to the AI database (which should already be configured)
        require_once __DIR__ . '/db_config.php';
        global $ai_pdo;
        $pdo = $ai_pdo;
    }
    
    // Encrypt the password before storing
    $encrypted_password = base64_encode(openssl_encrypt($password, 'AES-256-CBC', 'zin-ai-secret-key-2024', 0, '1234567890123456'));
    
    // Save configuration to database
    $sql = "INSERT INTO ai_database_configs 
            (config_name, config_type, server_host, server_port, database_name, username, password, updated_at) 
            VALUES (:config_name, :config_type, :server_host, :server_port, :database_name, :username, :password, NOW())
            ON DUPLICATE KEY UPDATE 
            server_host = VALUES(server_host),
            server_port = VALUES(server_port),
            database_name = VALUES(database_name),
            username = VALUES(username),
            password = VALUES(password),
            updated_at = NOW(),
            test_result = 'pending'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':config_name' => $config_name,
        ':config_type' => $config_type,
        ':server_host' => $server_host,
        ':server_port' => $server_port,
        ':database_name' => $database_name,
        ':username' => $username,
        ':password' => $encrypted_password
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($config_type) . ' database configuration saved successfully',
        'config' => [
            'config_name' => $config_name,
            'config_type' => $config_type,
            'server_host' => $server_host,
            'server_port' => $server_port,
            'database_name' => $database_name,
            'username' => $username
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save database configuration: ' . $e->getMessage(),
        'sql_error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}
?>