<?php
/**
 * Debug Console API - Real-time SmartAIRouter debugging
 * Integrates with existing admin panel debug console
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

try {
    $action = $_GET['action'] ?? 'logs';
    
    switch ($action) {
        case 'logs':
            // Get recent debug logs for admin console
            $limit = $_GET['limit'] ?? 50;
            $since = $_GET['since'] ?? null;
            $level = $_GET['level'] ?? null;
            $source = $_GET['source'] ?? 'SmartAIRouter';
            
            $whereClause = "WHERE source = ?";
            $params = [$source];
            
            if ($since) {
                $whereClause .= " AND created_at > ?";
                $params[] = $since;
            }
            
            if ($level) {
                $whereClause .= " AND level = ?";
                $params[] = $level;
            }
            
            $stmt = $ai_pdo->prepare("
                SELECT id, timestamp, level, source, message, data, request_id, created_at
                FROM debug_logs 
                $whereClause
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            
            $params[] = (int)$limit;
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format logs for admin console
            $formattedLogs = [];
            foreach ($logs as $log) {
                $formattedLogs[] = [
                    'id' => $log['id'],
                    'timestamp' => $log['timestamp'],
                    'level' => $log['level'],
                    'source' => $log['source'],
                    'message' => $log['message'],
                    'data' => $log['data'] ? json_decode($log['data'], true) : null,
                    'request_id' => $log['request_id'],
                    'created_at' => $log['created_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $formattedLogs,
                'count' => count($formattedLogs),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'live':
            // Get live debug stream (for real-time updates)
            $lastId = $_GET['last_id'] ?? 0;
            $source = $_GET['source'] ?? 'SmartAIRouter';
            
            $stmt = $ai_pdo->prepare("
                SELECT id, timestamp, level, source, message, data, request_id, created_at
                FROM debug_logs 
                WHERE source = ? AND id > ?
                ORDER BY created_at ASC
                LIMIT 100
            ");
            
            $stmt->execute([$source, (int)$lastId]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formattedLogs = [];
            $maxId = $lastId;
            
            foreach ($logs as $log) {
                $formattedLogs[] = [
                    'id' => $log['id'],
                    'timestamp' => $log['timestamp'],
                    'level' => $log['level'],
                    'source' => $log['source'],
                    'message' => $log['message'],
                    'data' => $log['data'] ? json_decode($log['data'], true) : null,
                    'request_id' => $log['request_id'],
                    'created_at' => $log['created_at']
                ];
                $maxId = max($maxId, $log['id']);
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $formattedLogs,
                'count' => count($formattedLogs),
                'last_id' => $maxId,
                'has_new' => count($formattedLogs) > 0
            ]);
            break;
            
        case 'clear':
            // Clear debug logs (admin function)
            $source = $_POST['source'] ?? 'SmartAIRouter';
            
            $stmt = $ai_pdo->prepare("DELETE FROM debug_logs WHERE source = ?");
            $stmt->execute([$source]);
            
            echo json_encode([
                'success' => true,
                'message' => "Cleared debug logs for $source",
                'deleted_count' => $stmt->rowCount()
            ]);
            break;
            
        case 'stats':
            // Get debug log statistics
            $source = $_GET['source'] ?? 'SmartAIRouter';
            
            $stmt = $ai_pdo->prepare("
                SELECT 
                    COUNT(*) as total_logs,
                    SUM(CASE WHEN level = 'ERROR' THEN 1 ELSE 0 END) as error_count,
                    SUM(CASE WHEN level = 'SUCCESS' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN level = 'INFO' THEN 1 ELSE 0 END) as info_count,
                    MIN(created_at) as oldest_log,
                    MAX(created_at) as newest_log
                FROM debug_logs 
                WHERE source = ?
            ");
            
            $stmt->execute([$source]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'source' => $source
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>