<?php
/**
 * Debug script to check learning configuration
 */

require_once 'api/db_config.php';

try {
    echo "=== Learning Configuration Debug ===\n\n";
    
    // Check if learning_enabled setting exists
    $stmt = $ai_pdo->prepare("SELECT * FROM ai_settings WHERE setting_key = 'learning_enabled'");
    $stmt->execute();
    $learning_setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($learning_setting) {
        echo "✅ learning_enabled setting found:\n";
        echo "   Value: " . $learning_setting['setting_value'] . "\n";
        echo "   Category: " . $learning_setting['category'] . "\n";
        echo "   Description: " . $learning_setting['description'] . "\n\n";
    } else {
        echo "❌ learning_enabled setting NOT found in ai_settings table\n";
        echo "Creating learning_enabled setting...\n";
        
        $stmt = $ai_pdo->prepare("
            INSERT INTO ai_settings (setting_key, setting_value, category, description) 
            VALUES ('learning_enabled', 'true', 'general', 'Enable AI learning from conversations')
        ");
        
        if ($stmt->execute()) {
            echo "✅ learning_enabled setting created successfully\n\n";
        } else {
            echo "❌ Failed to create learning_enabled setting\n\n";
        }
    }
    
    // Check ai_learning table structure
    echo "=== ai_learning Table Structure ===\n";
    $stmt = $ai_pdo->query("DESCRIBE ai_learning");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "• " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    echo "\n";
    
    // Check current learning data
    echo "=== Current Learning Data ===\n";
    $stmt = $ai_pdo->query("SELECT COUNT(*) as count FROM ai_learning");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total learning entries: " . $count['count'] . "\n";
    
    if ($count['count'] > 0) {
        echo "\nRecent entries:\n";
        $stmt = $ai_pdo->query("SELECT * FROM ai_learning ORDER BY created_at DESC LIMIT 5");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recent as $entry) {
            echo "• ID: " . $entry['id'] . ", Pattern: " . $entry['pattern_type'] . ", Created: " . $entry['created_at'] . "\n";
        }
    } else {
        echo "No learning entries found.\n";
    }
    
    echo "\n=== All AI Settings ===\n";
    $stmt = $ai_pdo->query("SELECT setting_key, setting_value FROM ai_settings ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        echo "• " . $setting['setting_key'] . " = " . $setting['setting_value'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>