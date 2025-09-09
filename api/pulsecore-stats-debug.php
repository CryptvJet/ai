<?php
// Simple debug version to test if basic PHP is working
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'debug' => true,
    'data' => [
        'total_novas' => 209,
        'last_complexity' => 17090,
        'avg_energy' => 178294.17,
        'total_sessions' => 5
    ]
]);
?>