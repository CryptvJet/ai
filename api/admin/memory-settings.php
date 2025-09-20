<?php
/**
 * Memory Management Settings API
 * Handles CRUD operations for conversation memory and threshold settings
 */

require_once '../db_config.php';

header('Content-Type: application/json');

// Handle CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle GET requests - retrieve memory settings
 */
function handleGet($action) {
    global $ai_pdo;
    
    if ($action === 'all') {
        // Get all memory-related settings
        $stmt = $ai_pdo->prepare("
            SELECT setting_key, setting_value, setting_type, description, updated_at 
            FROM ai_settings 
            WHERE category = 'limits' 
            ORDER BY setting_key
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format settings for easier frontend consumption
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting['setting_key']] = [
                'value' => castSettingValue($setting['setting_value'], $setting['setting_type']),
                'type' => $setting['setting_type'],
                'description' => $setting['description'],
                'updated_at' => $setting['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $formatted,
            'count' => count($settings)
        ]);
        
    } else {
        // Get specific setting
        $key = $_GET['key'] ?? '';
        if (empty($key)) {
            throw new Exception('Setting key required', 400);
        }
        
        $stmt = $ai_pdo->prepare("
            SELECT setting_value, setting_type, description 
            FROM ai_settings 
            WHERE setting_key = ? AND category = 'limits'
        ");
        $stmt->execute([$key]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$setting) {
            throw new Exception('Setting not found', 404);
        }
        
        echo json_encode([
            'success' => true,
            'setting' => [
                'key' => $key,
                'value' => castSettingValue($setting['setting_value'], $setting['setting_type']),
                'type' => $setting['setting_type'],
                'description' => $setting['description']
            ]
        ]);
    }
}

/**
 * Handle POST requests - create or update multiple settings
 */
function handlePost() {
    global $ai_pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['settings'])) {
        throw new Exception('Invalid input data', 400);
    }
    
    $settings = $input['settings'];
    $updated = [];
    $errors = [];
    
    $ai_pdo->beginTransaction();
    
    try {
        foreach ($settings as $key => $data) {
            $value = $data['value'] ?? $data;
            $type = $data['type'] ?? 'string';
            $description = $data['description'] ?? '';
            
            // Validate setting values
            $validationError = validateMemorySetting($key, $value);
            if ($validationError) {
                $errors[$key] = $validationError;
                continue;
            }
            
            // Upsert setting
            $stmt = $ai_pdo->prepare("
                INSERT INTO ai_settings (setting_key, setting_value, category, setting_type, description) 
                VALUES (?, ?, 'limits', ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type),
                    description = VALUES(description),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$key, (string)$value, $type, $description]);
            $updated[$key] = $value;
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation errors: ' . json_encode($errors), 400);
        }
        
        $ai_pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully',
            'updated' => $updated,
            'count' => count($updated)
        ]);
        
    } catch (Exception $e) {
        $ai_pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle PUT requests - update single setting
 */
function handlePut() {
    global $ai_pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['key']) || !isset($input['value'])) {
        throw new Exception('Key and value required', 400);
    }
    
    $key = $input['key'];
    $value = $input['value'];
    $type = $input['type'] ?? 'string';
    
    // Validate setting value
    $validationError = validateMemorySetting($key, $value);
    if ($validationError) {
        throw new Exception($validationError, 400);
    }
    
    $stmt = $ai_pdo->prepare("
        UPDATE ai_settings 
        SET setting_value = ?, setting_type = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE setting_key = ? AND category = 'limits'
    ");
    
    $result = $stmt->execute([(string)$value, $type, $key]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Setting not found or no changes made', 404);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Setting updated successfully',
        'setting' => ['key' => $key, 'value' => $value]
    ]);
}

/**
 * Validate memory setting values
 */
function validateMemorySetting($key, $value) {
    switch ($key) {
        case 'conversation_memory_limit_mb':
            if (!is_numeric($value) || $value < 1 || $value > 20) {
                return 'Memory limit must be between 1 and 20 MB';
            }
            break;
            
        case 'threshold_warning_level':
            if (!is_numeric($value) || $value < 70 || $value > 95) {
                return 'Warning threshold must be between 70% and 95%';
            }
            break;
            
        case 'threshold_critical_level':
            if (!is_numeric($value) || $value < 95 || $value > 99) {
                return 'Critical threshold must be between 95% and 99%';
            }
            break;
            
        case 'default_refresh_option':
            if (!in_array($value, ['full', 'partial', 'zip'])) {
                return 'Default refresh option must be full, partial, or zip';
            }
            break;
            
        case 'compression_ratio':
            if (!is_numeric($value) || $value < 5 || $value > 25) {
                return 'Compression ratio must be between 5% and 25%';
            }
            break;
            
        case 'auto_refresh_enabled':
        case 'memory_monitoring_enabled':
        case 'threshold_animation_enabled':
            if (!in_array(strtolower((string)$value), ['true', 'false', '1', '0'])) {
                return 'Boolean setting must be true or false';
            }
            break;
    }
    
    return null; // No validation error
}

/**
 * Cast setting value to appropriate type
 */
function castSettingValue($value, $type) {
    switch ($type) {
        case 'integer':
            return (int)$value;
        case 'boolean':
            return in_array(strtolower($value), ['true', '1', 'on', 'yes']);
        case 'json':
            return json_decode($value, true);
        default:
            return $value;
    }
}

/**
 * Log admin activity
 */
function logAdminActivity($action, $details) {
    global $ai_pdo;
    
    try {
        $stmt = $ai_pdo->prepare("
            INSERT INTO admin_activity_log (action, details, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $action,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail - don't break the main operation
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}
?>