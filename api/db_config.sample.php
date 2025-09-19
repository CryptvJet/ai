<?php
/**
 * AI Database Configuration Template
 * Copy this to db_config.php and update with your credentials
 */

// AI Database connection settings (only hardcoded config)
$AI_DB_HOST = 'your_host';           // Your MySQL server host
$AI_DB_NAME = 'your_database_name';  // Your AI database name  
$AI_DB_USER = 'your_username';       // Your MySQL username
$AI_DB_PASS = 'your_password';       // Your MySQL password

try {
    // AI PDO connection for AI tables (conversations, messages, settings, admin users, pc_status)
    $ai_pdo = new PDO(
        "mysql:host=$AI_DB_HOST;dbname=$AI_DB_NAME;charset=utf8mb4", 
        $AI_DB_USER, 
        $AI_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Load PulseCore database configuration from ai_database_configs table
    $stmt = $ai_pdo->prepare("
        SELECT server_host, server_port, database_name, username, password 
        FROM ai_database_configs 
        WHERE config_type = 'pulsecore' AND enabled = 1 
        ORDER BY id LIMIT 1
    ");
    $stmt->execute();
    $pulseConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pulseConfig) {
        // PulseCore PDO connection for PulseCore tables (nova_events, climax_groups)
        $pulse_pdo = new PDO(
            "mysql:host={$pulseConfig['server_host']};port={$pulseConfig['server_port']};dbname={$pulseConfig['database_name']};charset=utf8mb4",
            $pulseConfig['username'],
            $pulseConfig['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        // Variables PDO connection for Variables table (pulse_core_variables)
        $vars_pdo = $pulse_pdo;
    } else {
        // Fallback: use AI database if no PulseCore config found
        $pulse_pdo = $ai_pdo;
        $vars_pdo = $ai_pdo;
    }
    
} catch (PDOException $e) {
    // Log error and return JSON response
    error_log("Database connection failed: " . $e->getMessage());
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "error" => "Database connection failed. Please check configuration.",
            "details" => $e->getMessage()
        ]);
        exit;
    } else {
        echo "Database connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Test connections function
function testDatabaseConnections() {
    global $ai_pdo, $pulse_pdo, $vars_pdo;
    
    $results = [
        'ai_database' => false,
        'pulsecore_database' => false,
        'variables_table' => false,
        'ai_tables' => false
    ];
    
    try {
        $ai_pdo->query("SELECT 1");
        $results['ai_database'] = true;
    } catch (Exception $e) {
        $results['ai_database'] = $e->getMessage();
    }
    
    try {
        $stmt = $pulse_pdo->query("SELECT COUNT(*) as count FROM nova_events LIMIT 1");
        $result = $stmt->fetch();
        $results['pulsecore_database'] = "Connected ({$result['count']} events)";
    } catch (Exception $e) {
        $results['pulsecore_database'] = $e->getMessage();
    }
    
    try {
        $stmt = $vars_pdo->query("SELECT COUNT(*) as count FROM pulse_core_variables LIMIT 1");
        $result = $stmt->fetch();
        $results['variables_table'] = "Connected ({$result['count']} variables)";
    } catch (Exception $e) {
        $results['variables_table'] = $e->getMessage();
    }
    
    try {
        $ai_pdo->query("SELECT 1 FROM ai_conversations LIMIT 1");
        $results['ai_tables'] = true;
    } catch (Exception $e) {
        $results['ai_tables'] = "AI tables not created yet - run migrations";
    }
    
    return $results;
}

// CLI test script
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "Testing AI Chat Database Connections\n";
    echo "===================================\n";
    
    $results = testDatabaseConnections();
    
    foreach ($results as $component => $status) {
        $component_name = ucwords(str_replace('_', ' ', $component));
        echo sprintf("%-20s: ", $component_name);
        
        if ($status === true) {
            echo "✓ CONNECTED\n";
        } elseif (is_string($status) && strpos($status, 'Connected') === 0) {
            echo "✓ $status\n";
        } else {
            echo "✗ $status\n";
        }
    }
    echo "\n";
    
    // Show next steps
    if ($results['ai_tables'] !== true) {
        echo "Next step: Run migrations to create AI tables\n";
        echo "Command: php migrate.php migrate\n\n";
    } else {
        echo "✓ All systems ready!\n";
        echo "Access your AI chat at: ai/index.html\n\n";
    }
}
?>