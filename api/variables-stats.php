<?php
/**
 * Variables Statistics API
 * Provides stats from pulse_core_variables table
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db_config.php';

try {
    // Get variables statistics
    $stmt = $vars_pdo->query("
        SELECT 
            COUNT(*) as total_variables,
            COUNT(DISTINCT category) as unique_categories,
            COUNT(CASE WHEN is_fundamental = 1 THEN 1 END) as fundamental_variables,
            MAX(updated_at) as last_updated
        FROM pulse_core_variables
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get category breakdown
    $stmt = $vars_pdo->query("
        SELECT 
            category,
            COUNT(*) as count,
            COUNT(CASE WHEN is_fundamental = 1 THEN 1 END) as fundamental_count
        FROM pulse_core_variables 
        WHERE category IS NOT NULL
        GROUP BY category 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'variable_count' => (int)$stats['total_variables'],
            'category_count' => (int)$stats['unique_categories'],
            'fundamental_count' => (int)$stats['fundamental_variables'],
            'last_updated' => $stats['last_updated'],
            'categories' => $categories
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>