<?php
/**
 * PC Status API - Check if PC Bridge is connected
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
    // Get latest PC status from database
    $stmt = $ai_pdo->prepare("
        SELECT 
            status,
            system_info,
            last_ping,
            TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping
        FROM pc_status 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => 'No PC status records found'
        ]);
        exit;
    }
    
    // Consider PC offline if no ping in last 60 seconds
    $isOnline = $result['seconds_since_ping'] < 60;
    $status = $isOnline ? $result['status'] : 'offline';
    
    $response = [
        'success' => true,
        'data' => [
            'status' => $status,
            'is_online' => $isOnline,
            'last_ping' => $result['last_ping'],
            'seconds_since_ping' => (int)$result['seconds_since_ping'],
            'system_info' => $result['system_info'] ? json_decode($result['system_info'], true) : null
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check PC status: ' . $e->getMessage()
    ]);
}
?>