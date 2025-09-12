<?php
/**
 * PulseCore Connection Status API
 * Returns connection status and basic statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    // Test PulseCore database connection
    $stmt = $pulse_pdo->query("SELECT 1");
    
    // Get basic statistics
    $stats = [];
    
    // Total nova events
    $stmt = $pulse_pdo->query("SELECT COUNT(*) as count FROM nova_events");
    $result = $stmt->fetch();
    $stats['total_novas'] = (int)$result['count'];
    
    // Total climax groups
    try {
        $stmt = $pulse_pdo->query("SELECT COUNT(*) as count FROM climax_groups");
        $result = $stmt->fetch();
        $stats['total_groups'] = (int)$result['count'];
    } catch (Exception $e) {
        $stats['total_groups'] = 0;
    }
    
    // Last nova timestamp
    $stmt = $pulse_pdo->query("SELECT MAX(timestamp) as last_timestamp FROM nova_events");
    $result = $stmt->fetch();
    $stats['last_nova_time'] = $result['last_timestamp'];
    
    // Average complexity
    $stmt = $pulse_pdo->query("
        SELECT AVG(complexity) as avg_complexity 
        FROM nova_events 
        WHERE complexity IS NOT NULL
    ");
    $result = $stmt->fetch();
    $stats['avg_complexity'] = $result['avg_complexity'] ? round((float)$result['avg_complexity'], 2) : null;
    
    // Average energy
    $stmt = $pulse_pdo->query("
        SELECT AVG(pulse_energy) as avg_energy 
        FROM nova_events 
        WHERE pulse_energy IS NOT NULL
    ");
    $result = $stmt->fetch();
    $stats['avg_energy'] = $result['avg_energy'] ? round((float)$result['avg_energy'], 4) : null;
    
    // Connection info
    $stats['connection_time'] = date('Y-m-d H:i:s');
    $stats['database_name'] = 'your_pulsecore_database';
    
    echo json_encode([
        'success' => true,
        'status' => 'connected',
        'data' => $stats,
        'message' => 'PulseCore database connection successful'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'error' => 'Database connection failed: ' . $e->getMessage(),
        'data' => [
            'total_novas' => 0,
            'total_groups' => 0,
            'last_nova_time' => null,
            'avg_complexity' => null,
            'avg_energy' => null
        ]
    ]);
}
?>