<?php
/**
 * AI Database Configuration Template
 * Copy this to db_config.php and update with your credentials
 */

// Load configuration from external files
function loadDatabaseConfig() {
    // Try to load from the secure config directory first (for local development)
    $configFiles = [
        __DIR__ . '/../../data/pws/pulse_db_config.json',
        __DIR__ . '/../../data/pws/ai_db_config.json'
    ];
    
    $config = [
        'pulse_host' => 'localhost',
        'pulse_user' => 'your_db_username',
        'pulse_pass' => 'your_database_password',
        'pulse_db' => 'your_pulsecore_database',
        'ai_host' => 'localhost',
        'ai_user' => 'your_db_username',
        'ai_pass' => 'your_database_password',
        'ai_db' => 'your_ai_database'
    ];
    
    // Load PulseCore database config if available
    if (file_exists($configFiles[0])) {
        try {
            $pulseConfig = json_decode(file_get_contents($configFiles[0]), true);
            if ($pulseConfig) {
                $config['pulse_host'] = $pulseConfig['Server'] ?? 'localhost';
                $config['pulse_user'] = $pulseConfig['Username'] ?? 'your_db_username';
                $config['pulse_pass'] = $pulseConfig['Password'] ?? 'your_database_password';
                $config['pulse_db'] = $pulseConfig['Database'] ?? 'your_pulsecore_database';
            }
        } catch (Exception $e) {
            // Use defaults if config loading fails
            error_log("Failed to load PulseCore config: " . $e->getMessage());
        }
    }
    
    // Load AI database config if available
    if (file_exists($configFiles[1])) {
        try {
            $aiConfig = json_decode(file_get_contents($configFiles[1]), true);
            if ($aiConfig) {
                $config['ai_host'] = $aiConfig['Server'] ?? 'localhost';
                $config['ai_user'] = $aiConfig['Username'] ?? 'your_db_username';
                $config['ai_pass'] = $aiConfig['Password'] ?? 'your_database_password';
                $config['ai_db'] = $aiConfig['Database'] ?? 'your_ai_database';
            }
        } catch (Exception $e) {
            // Use defaults if config loading fails
            error_log("Failed to load AI config: " . $e->getMessage());
        }
    }
    
    return $config;
}

$dbConfig = loadDatabaseConfig();

// AI Database connection settings
$AI_DB_HOST = $dbConfig['ai_host'];
$AI_DB_USER = $dbConfig['ai_user'];
$AI_DB_PASS = $dbConfig['ai_pass'];
$AI_DB_NAME = $dbConfig['ai_db'];

// PulseCore Database connection settings  
$DB_HOST = $dbConfig['pulse_host'];
$DB_USER = $dbConfig['pulse_user'];
$DB_PASS = $dbConfig['pulse_pass'];
$PULSE_DB_NAME = $dbConfig['pulse_db'];

try {
    // AI PDO connection for AI tables (conversations, messages, settings, ollama_config)
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
    
    // PulseCore PDO connection for PulseCore tables (nova_events, climax_groups)
    $pulse_pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$PULSE_DB_NAME;charset=utf8mb4", 
        $DB_USER, 
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Variables PDO connection for Variables table (pulse_core_variables)
    $vars_pdo = $pulse_pdo;
    
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