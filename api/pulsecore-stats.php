<?php
/**
 * PulseCore Statistics API
 * Provides real-time stats from PulseCore database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db_config.php';

try {
    // Get nova event statistics
    $stmt = $pulse_pdo->query("
        SELECT 
            COUNT(*) as total_novas,
            MAX(complexity) as max_complexity,
            AVG(complexity) as avg_complexity,
            AVG(pulse_energy) as avg_energy,
            MAX(timestamp) as last_nova_time,
            COUNT(DISTINCT climax_group_id) as total_groups
        FROM nova_events 
        WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $nova_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $stmt = $pulse_pdo->query("
        SELECT 
            COUNT(*) as recent_novas,
            AVG(complexity) as recent_complexity
        FROM nova_events 
        WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $recent_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get genesis mode distribution
    $stmt = $pulse_pdo->query("
        SELECT 
            genesis_mode, 
            COUNT(*) as count,
            AVG(complexity) as avg_complexity
        FROM nova_events 
        WHERE genesis_mode IS NOT NULL 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY genesis_mode 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $genesis_modes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get complexity trends (last 10 novas)
    $stmt = $pulse_pdo->query("
        SELECT complexity, pulse_energy, timestamp 
        FROM nova_events 
        WHERE complexity IS NOT NULL 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    $complexity_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Variables count
    $vars_count = 0;
    try {
        $stmt = $vars_pdo->query("SELECT COUNT(*) as count FROM pulse_core_variables");
        $vars_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $vars_count = $vars_result['count'];
    } catch (Exception $e) {
        // Variables DB might not be available
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_novas' => (int)$nova_stats['total_novas'],
            'last_complexity' => $nova_stats['max_complexity'] ? (int)$nova_stats['max_complexity'] : null,
            'avg_complexity' => $nova_stats['avg_complexity'] ? round($nova_stats['avg_complexity'], 1) : null,
            'avg_energy' => $nova_stats['avg_energy'] ? round($nova_stats['avg_energy'], 3) : null,
            'last_nova_time' => $nova_stats['last_nova_time'],
            'total_groups' => (int)$nova_stats['total_groups'],
            'recent_novas_24h' => (int)$recent_stats['recent_novas'],
            'recent_complexity' => $recent_stats['recent_complexity'] ? round($recent_stats['recent_complexity'], 1) : null,
            'variables_count' => $vars_count,
            'genesis_modes' => $genesis_modes,
            'complexity_trend' => $complexity_trend,
            'last_updated' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>