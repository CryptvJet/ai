<?php
/**
 * PC Bridge Status API
 * Returns the current status of the PC AI Bridge connection
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Database connection
    $host = 'pulsecore.one';
    $dbname = 'your_pulsecore_database';
    $username = 'your_username';
    $password = 'l%tN!^6^u4=2';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the latest PC status
    $stmt = $pdo->prepare("
        SELECT 
            system_info,
            last_ping,
            TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping,
            CASE 
                WHEN TIMESTAMPDIFF(SECOND, last_ping, NOW()) < 60 THEN 'online'
                ELSE 'offline'
            END as status
        FROM pc_status 
        ORDER BY last_ping DESC 
        LIMIT 1
    ");
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $system_info = json_decode($result['system_info'], true);
        
        $response = [
            'success' => true,
            'status' => $result['status'],
            'last_ping' => $result['last_ping'],
            'seconds_since_ping' => (int)$result['seconds_since_ping'],
            'system_info' => [
                'hostname' => $system_info['hostname'] ?? 'Unknown',
                'platform' => $system_info['platform'] ?? 'Unknown',
                'arch' => $system_info['arch'] ?? 'Unknown',
                'uptime' => $system_info['uptime'] ?? 0,
                'cpus' => $system_info['cpus'] ?? 0,
                'memory' => [
                    'total' => $system_info['memory']['total'] ?? 0,
                    'free' => $system_info['memory']['free'] ?? 0,
                    'used' => ($system_info['memory']['total'] ?? 0) - ($system_info['memory']['free'] ?? 0),
                    'usage_percent' => $system_info['memory']['total'] > 0 ? 
                        round((($system_info['memory']['total'] - $system_info['memory']['free']) / $system_info['memory']['total']) * 100, 1) : 0
                ],
                'gpu' => [
                    'controllers' => $system_info['gpu']['controllers'] ?? [],
                    'displays' => $system_info['gpu']['displays'] ?? 0,
                    'primary' => !empty($system_info['gpu']['controllers']) ? [
                        'model' => $system_info['gpu']['controllers'][0]['model'] ?? 'Unknown',
                        'vendor' => $system_info['gpu']['controllers'][0]['vendor'] ?? 'Unknown',
                        'vram' => $system_info['gpu']['controllers'][0]['vram'] ?? null,
                        'temperature' => $system_info['gpu']['controllers'][0]['temperature'] ?? null,
                        'utilization_gpu' => $system_info['gpu']['controllers'][0]['utilizationGpu'] ?? null,
                        'utilization_memory' => $system_info['gpu']['controllers'][0]['utilizationMemory'] ?? null
                    ] : null
                ],
                'cpu_load' => $system_info['cpuLoad'] ?? 0,
                'timestamp' => $system_info['timestamp'] ?? null
            ]
        ];
    } else {
        $response = [
            'success' => true,
            'status' => 'never_connected',
            'last_ping' => null,
            'seconds_since_ping' => null,
            'system_info' => null
        ];
    }
    
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ];
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>