<?php
/**
 * Database Query Management API
 * Manages table configurations for AI responses to PulseCore database queries
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'tables':
                // Get available PulseCore tables
                $tables = getAvailableTables($pulse_pdo);
                echo json_encode([
                    'success' => true,
                    'tables' => $tables
                ]);
                break;
                
            case 'list':
            default:
                // Get all table configurations
                $stmt = $ai_pdo->prepare("
                    SELECT 
                        id,
                        table_name,
                        trigger_patterns,
                        search_fields,
                        response_template,
                        priority,
                        max_results,
                        active,
                        created_at,
                        updated_at,
                        usage_count
                    FROM ai_database_queries 
                    ORDER BY priority DESC, table_name ASC
                ");
                $stmt->execute();
                $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Decode JSON fields
                foreach ($configs as &$config) {
                    $config['trigger_patterns'] = json_decode($config['trigger_patterns'], true) ?: [];
                    $config['search_fields'] = json_decode($config['search_fields'], true) ?: [];
                }
                
                // Get summary stats
                $stmt = $ai_pdo->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count
                    FROM ai_database_queries
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'configs' => $configs,
                    'stats' => $stats
                ]);
                break;
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new table configuration
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['table_name', 'trigger_patterns', 'search_fields', 'response_template'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Validate patterns and fields are arrays
        if (!is_array($input['trigger_patterns']) || !is_array($input['search_fields'])) {
            throw new Exception('Trigger patterns and search fields must be arrays');
        }
        
        // Check for duplicate table configurations
        $stmt = $ai_pdo->prepare("SELECT id FROM ai_database_queries WHERE table_name = ? AND active = 1");
        $stmt->execute([$input['table_name']]);
        if ($stmt->fetch()) {
            throw new Exception('An active configuration for this table already exists');
        }
        
        $stmt = $ai_pdo->prepare("
            INSERT INTO ai_database_queries (
                table_name, 
                trigger_patterns, 
                search_fields, 
                response_template, 
                priority, 
                max_results, 
                active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['table_name'],
            json_encode($input['trigger_patterns']),
            json_encode($input['search_fields']),
            $input['response_template'],
            $input['priority'] ?? 50,
            $input['max_results'] ?? 5,
            $input['active'] ?? true
        ]);
        
        $config_id = $ai_pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Table configuration created successfully',
            'config_id' => $config_id
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update table configuration
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Missing required field: id');
        }
        
        // Check if config exists
        $stmt = $ai_pdo->prepare("SELECT * FROM ai_database_queries WHERE id = ?");
        $stmt->execute([$input['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            throw new Exception('Configuration not found');
        }
        
        $updateFields = [];
        $params = [];
        
        $allowed_fields = [
            'table_name', 'trigger_patterns', 'search_fields', 
            'response_template', 'priority', 'max_results', 'active'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                if (in_array($field, ['trigger_patterns', 'search_fields'])) {
                    $params[] = json_encode($input[$field]);
                } else {
                    $params[] = $input[$field];
                }
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $params[] = $input['id'];
        
        $stmt = $ai_pdo->prepare("
            UPDATE ai_database_queries 
            SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuration updated successfully'
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete configuration
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('Missing required parameter: id');
        }
        
        $stmt = $ai_pdo->prepare("DELETE FROM ai_database_queries WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuration deleted successfully'
        ]);
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get available tables from PulseCore database
 */
function getAvailableTables($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tableName = $row[0];
            
            // Get column information
            $colStmt = $pdo->prepare("DESCRIBE `$tableName`");
            $colStmt->execute();
            $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get sample row count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$tableName`");
            $countStmt->execute();
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $tables[] = [
                'name' => $tableName,
                'columns' => array_column($columns, 'Field'),
                'row_count' => $count
            ];
        }
        
        return $tables;
        
    } catch (Exception $e) {
        error_log("Error getting tables: " . $e->getMessage());
        return [];
    }
}
?>