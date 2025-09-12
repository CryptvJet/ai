<?php
/**
 * AI Database Configuration Template
 * Copy this to db_config.php and update with your credentials
 */

// All connections use the same database (your_database_name)
$DB_HOST = 'localhost';
$DB_NAME = 'your_database_name';
$DB_USER = 'your_username';  // Replace with your actual MySQL username
$DB_PASS = 'your_password';  // Replace with your actual MySQL password

try {
    // Main PDO connection for all AI tables
    $ai_pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", 
        $DB_USER, 
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Use same connection for PulseCore tables (nova_events, climax_groups)
    $pulse_pdo = $ai_pdo;
    
    // Use same connection for Variables table (pulse_core_variables)
    $vars_pdo = $ai_pdo;
    
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
        'main_database' => false,
        'nova_events_table' => false,
        'variables_table' => false,
        'ai_tables' => false
    ];
    
    try {
        $ai_pdo->query("SELECT 1");
        $results['main_database'] = true;
    } catch (Exception $e) {
        $results['main_database'] = $e->getMessage();
    }
    
    try {
        $stmt = $pulse_pdo->query("SELECT COUNT(*) as count FROM nova_events LIMIT 1");
        $result = $stmt->fetch();
        $results['nova_events_table'] = "Connected ({$result['count']} events)";
    } catch (Exception $e) {
        $results['nova_events_table'] = $e->getMessage();
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