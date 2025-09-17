<?php
/**
 * AI Database Configuration Template
 * Copy this to db_config.php and update with your credentials
 */

// Database connection settings
$DB_HOST = 'your_host';           // Your MySQL server host
$DB_NAME = 'vemite5_pulse-core-ai'; // Your database name  
$DB_USER = 'your_username';       // Your MySQL username
$DB_PASS = 'your_password';       // Your MySQL password

try {
    // Main PDO connection for AI tables (conversations, messages, settings, admin users)
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
    
    // For now, use same database for PulseCore data (can be separated later)
    $pulse_pdo = $ai_pdo;
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
        'ai_tables' => false
    ];
    
    try {
        $ai_pdo->query("SELECT 1");
        $results['main_database'] = true;
    } catch (Exception $e) {
        $results['main_database'] = $e->getMessage();
    }
    
    try {
        $ai_pdo->query("SELECT 1 FROM ai_admin_users LIMIT 1");
        $results['ai_tables'] = true;
    } catch (Exception $e) {
        $results['ai_tables'] = "AI tables not created yet - run setup_admin_users.sql";
    }
    
    return $results;
}

// CLI test script
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "Testing AI Database Connection\n";
    echo "=============================\n";
    
    $results = testDatabaseConnections();
    
    foreach ($results as $component => $status) {
        $component_name = ucwords(str_replace('_', ' ', $component));
        echo sprintf("%-20s: ", $component_name);
        
        if ($status === true) {
            echo "✓ CONNECTED\n";
        } else {
            echo "✗ $status\n";
        }
    }
    echo "\n";
    
    if ($results['ai_tables'] !== true) {
        echo "Next step: Run SQL to create admin tables\n";
        echo "File: setup_admin_users.sql\n\n";
    } else {
        echo "✓ Database ready!\n";
        echo "Access admin at: ai/admin/login.html\n\n";
    }
}
?>