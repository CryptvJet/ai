<?php
/**
 * Test Database Connection API
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
    
    $server = $input['server'] ?? 'localhost';
    $database = $input['database'] ?? 'pulsecore';
    $username = $input['username'] ?? 'root';
    $password = $input['password'] ?? '';
    $port = (int)($input['port'] ?? 3306);
    
    // Test database connection
    $dsn = "mysql:host=$server;port=$port;dbname=$database;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'server' => $server,
            'database' => $database,
            'port' => $port
        ]);
    } else {
        throw new Exception('Test query failed');
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection test failed: ' . $e->getMessage()
    ]);
}
?>