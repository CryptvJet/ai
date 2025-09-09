<?php
/**
 * AI Settings API
 * Handles loading and saving AI configuration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

class AISettings {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAllSettings() {
        $stmt = $this->pdo->query("
            SELECT setting_key, setting_value, category, description 
            FROM ai_settings 
            ORDER BY category, setting_key
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $organized = [];
        foreach ($settings as $setting) {
            $organized[$setting['setting_key']] = $setting['setting_value'];
        }
        
        return $organized;
    }
    
    public function getSetting($key) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : null;
    }
    
    public function setSetting($key, $value, $category = 'general', $description = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_settings (setting_key, setting_value, category, description) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([$key, $value, $category, $description]);
    }
    
    public function updateMultiple($settings) {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($settings as $key => $value) {
                $this->setSetting($key, $value);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getSettingsByCategory() {
        $stmt = $this->pdo->query("
            SELECT setting_key, setting_value, category, description 
            FROM ai_settings 
            ORDER BY category, setting_key
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $categorized = [];
        foreach ($settings as $setting) {
            $categorized[$setting['category']][$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'description' => $setting['description']
            ];
        }
        
        return $categorized;
    }
}

// Handle the request
try {
    $settings = new AISettings($ai_pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['category'])) {
            $result = $settings->getSettingsByCategory();
        } else {
            $result = $settings->getAllSettings();
        }
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        if (isset($input['setting_key']) && isset($input['setting_value'])) {
            // Single setting update
            $settings->setSetting(
                $input['setting_key'],
                $input['setting_value'],
                $input['category'] ?? 'general',
                $input['description'] ?? null
            );
            
        } elseif (isset($input['settings']) && is_array($input['settings'])) {
            // Multiple settings update
            $settings->updateMultiple($input['settings']);
            
        } else {
            throw new Exception('Invalid request format');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
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
?>