<?php
/**
 * Debug Settings Management API
 * Handles application-wide debug configuration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../db_config.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';
    
    switch ($action) {
        case 'get':
            // Get all debug settings
            $stmt = $ai_pdo->query("
                SELECT setting_key, setting_value, setting_type, description, updated_at
                FROM debug_settings 
                ORDER BY setting_key
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert values to proper types
            $formattedSettings = [];
            foreach ($settings as $setting) {
                $value = $setting['setting_value'];
                
                switch ($setting['setting_type']) {
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'boolean':
                        $value = $value === 'true' || $value === '1';
                        break;
                    case 'json':
                        $value = json_decode($value, true) ?? $value;
                        break;
                    case 'string':
                    default:
                        // Keep as string
                        break;
                }
                
                $formattedSettings[$setting['setting_key']] = [
                    'value' => $value,
                    'type' => $setting['setting_type'],
                    'description' => $setting['description'],
                    'updated_at' => $setting['updated_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $formattedSettings,
                'count' => count($formattedSettings)
            ]);
            break;
            
        case 'update':
            // Update debug settings
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['settings'])) {
                throw new Exception('No settings data provided');
            }
            
            $updated = 0;
            $ai_pdo->beginTransaction();
            
            foreach ($input['settings'] as $key => $data) {
                // Validate setting exists
                $checkStmt = $ai_pdo->prepare("SELECT setting_type FROM debug_settings WHERE setting_key = ?");
                $checkStmt->execute([$key]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing) {
                    continue; // Skip unknown settings
                }
                
                $value = $data['value'];
                $type = $existing['setting_type'];
                
                // Validate and convert value based on type
                switch ($type) {
                    case 'integer':
                        if (!is_numeric($value)) {
                            throw new Exception("Invalid integer value for $key");
                        }
                        $value = (string)(int)$value;
                        break;
                        
                    case 'boolean':
                        $value = $value ? 'true' : 'false';
                        break;
                        
                    case 'json':
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                        // Validate JSON
                        json_decode($value);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception("Invalid JSON value for $key");
                        }
                        break;
                        
                    case 'string':
                    default:
                        $value = (string)$value;
                        break;
                }
                
                // Update setting
                $updateStmt = $ai_pdo->prepare("
                    UPDATE debug_settings 
                    SET setting_value = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE setting_key = ?
                ");
                $updateStmt->execute([$value, $key]);
                $updated++;
            }
            
            $ai_pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Updated $updated settings successfully",
                'updated_count' => $updated
            ]);
            break;
            
        case 'reset':
            // Reset to default values
            $defaultSettings = [
                'router_monitor_interval' => '30000',
                'max_debug_logs' => '1000',
                'auto_clear_logs' => 'true',
                'log_levels' => '["INFO","SUCCESS","ERROR","WARNING"]',
                'debug_enabled' => 'true',
                'auto_scroll_enabled' => 'true',
                'timestamp_format' => 'HH:MM:SS',
                'max_logs_display' => '500'
            ];
            
            $ai_pdo->beginTransaction();
            $reset = 0;
            
            foreach ($defaultSettings as $key => $value) {
                $stmt = $ai_pdo->prepare("
                    UPDATE debug_settings 
                    SET setting_value = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE setting_key = ?
                ");
                if ($stmt->execute([$value, $key])) {
                    $reset++;
                }
            }
            
            $ai_pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Reset $reset settings to defaults",
                'reset_count' => $reset
            ]);
            break;
            
        case 'get_single':
            // Get a single setting value
            $key = $_GET['key'] ?? '';
            if (!$key) {
                throw new Exception('Setting key required');
            }
            
            $stmt = $ai_pdo->prepare("
                SELECT setting_value, setting_type 
                FROM debug_settings 
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$setting) {
                throw new Exception('Setting not found');
            }
            
            $value = $setting['setting_value'];
            switch ($setting['setting_type']) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = $value === 'true' || $value === '1';
                    break;
                case 'json':
                    $value = json_decode($value, true) ?? $value;
                    break;
            }
            
            echo json_encode([
                'success' => true,
                'key' => $key,
                'value' => $value,
                'type' => $setting['setting_type']
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if (isset($ai_pdo) && $ai_pdo->inTransaction()) {
        $ai_pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>