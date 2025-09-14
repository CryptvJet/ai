<?php
// Setup debug table for SmartAIRouter debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load database config
require_once 'api/load_database_config.php';

// Get AI database connection
$config = loadDatabaseConfig('ai');
if (!$config['success']) {
    die("Error loading AI database config: " . $config['message']);
}

$dbConfig = $config['config'];
$host = $dbConfig['server_host'];
$port = $dbConfig['server_port'];
$dbname = $dbConfig['database_name'];
$username = $dbConfig['username'];
$password = $dbConfig['password'];

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Connected to AI database: $dbname\n\n";
    
    // Create debug_logs table
    $createTable = "
    CREATE TABLE IF NOT EXISTS debug_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp VARCHAR(50) NOT NULL,
        level ENUM('INFO', 'SUCCESS', 'WARN', 'ERROR', 'DEBUG') NOT NULL,
        source VARCHAR(50) NOT NULL DEFAULT 'SmartAIRouter',
        message TEXT NOT NULL,
        data JSON DEFAULT NULL,
        request_id VARCHAR(10) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_timestamp (timestamp),
        INDEX idx_level (level),
        INDEX idx_source (source),
        INDEX idx_request_id (request_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($createTable);
    echo "✅ Created/verified debug_logs table\n\n";
    
    // Insert test debug entries
    $testLogs = [
        ['INFO', 'SmartAIRouter initialized', '{}'],
        ['DEBUG', 'Testing debug logging functionality', '{"test": true}'],
        ['SUCCESS', 'Debug table setup completed', '{"table": "debug_logs"}']
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO debug_logs (timestamp, level, source, message, data, request_id) 
        VALUES (?, ?, 'SmartAIRouter', ?, ?, 'TEST01')
    ");
    
    foreach ($testLogs as $log) {
        $timestamp = date('Y-m-d H:i:s.v');
        $insertStmt->execute([$timestamp, $log[0], $log[1], $log[2]]);
        echo "✅ Added test log: {$log[0]} - {$log[1]}\n";
    }
    
    // Check log count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM debug_logs");
    $result = $stmt->fetch();
    echo "\n📊 Total debug logs in database: {$result['count']}\n";
    
    echo "\n🎯 Setup complete! You can now use these debug commands in the admin console:\n";
    echo "   • router - Show recent debug logs\n";
    echo "   • router live - Start live monitoring\n";
    echo "   • router stats - Show statistics\n";
    echo "   • router clear - Clear all logs\n\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
?>