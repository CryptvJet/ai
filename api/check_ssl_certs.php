<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

try {
    // Force switch to the correct database where the table exists
    $ai_pdo->exec("USE `vemite5_pulse-core-ai`");
    error_log("🔄 Status check switched to vemite5_pulse-core-ai database");
    
    // Get SSL configuration from database
    $sql = "SELECT * FROM ai_ssl_config WHERE id = 1";
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch();
    
    error_log("📊 Status check - Config found: " . ($config ? "YES" : "NO"));
    
    // Default values if no config exists
    if (!$config) {
        $config = [
            'enabled' => false,
            'port' => 8443,
            'cert_uploaded' => false,
            'key_uploaded' => false,
            'cert_filename' => 'server.crt',
            'key_filename' => 'server.key'
        ];
    }
    
    // Verify certificate and key content exists in database
    $certContentExists = !empty($config['cert_content']);
    $keyContentExists = !empty($config['key_content']);
    
    error_log("📄 Certificate content exists: " . ($certContentExists ? "YES" : "NO"));
    error_log("🔑 Key content exists: " . ($keyContentExists ? "YES" : "NO"));
    error_log("📊 DB cert_uploaded flag: " . ($config['cert_uploaded'] ? "YES" : "NO"));
    error_log("📊 DB key_uploaded flag: " . ($config['key_uploaded'] ? "YES" : "NO"));
    
    // Sync the uploaded flags with actual content existence
    if ($config['cert_uploaded'] && !$certContentExists) {
        error_log("❌ Cert flag says uploaded but no content - fixing");
        $updateSql = "UPDATE ai_ssl_config SET cert_uploaded = 0, cert_upload_date = NULL WHERE id = 1";
        $ai_pdo->prepare($updateSql)->execute();
        $config['cert_uploaded'] = false;
    }
    
    if (!$config['cert_uploaded'] && $certContentExists) {
        error_log("✅ Cert content exists but flag says not uploaded - fixing");
        $updateSql = "UPDATE ai_ssl_config SET cert_uploaded = 1 WHERE id = 1";
        $ai_pdo->prepare($updateSql)->execute();
        $config['cert_uploaded'] = true;
    }
    
    if ($config['key_uploaded'] && !$keyContentExists) {
        error_log("❌ Key flag says uploaded but no content - fixing");
        $updateSql = "UPDATE ai_ssl_config SET key_uploaded = 0, key_upload_date = NULL WHERE id = 1";
        $ai_pdo->prepare($updateSql)->execute();
        $config['key_uploaded'] = false;
    }
    
    if (!$config['key_uploaded'] && $keyContentExists) {
        error_log("✅ Key content exists but flag says not uploaded - fixing");
        $updateSql = "UPDATE ai_ssl_config SET key_uploaded = 1 WHERE id = 1";
        $ai_pdo->prepare($updateSql)->execute();
        $config['key_uploaded'] = true;
    }
    
    echo json_encode([
        'success' => true,
        'certificate_exists' => (bool)$config['cert_uploaded'],
        'key_exists' => (bool)$config['key_uploaded'],
        'ssl_enabled' => (bool)$config['enabled'],
        'ssl_port' => (int)$config['port'],
        'both_uploaded' => (bool)$config['cert_uploaded'] && (bool)$config['key_uploaded'],
        'config' => [
            'enabled' => (bool)$config['enabled'],
            'port' => (int)$config['port'],
            'cert_filename' => $config['cert_filename'],
            'key_filename' => $config['key_filename'],
            'cert_uploaded' => (bool)$config['cert_uploaded'],
            'key_uploaded' => (bool)$config['key_uploaded'],
            'cert_upload_date' => $config['cert_upload_date'] ?? null,
            'key_upload_date' => $config['key_upload_date'] ?? null,
            'updated_at' => $config['updated_at'] ?? null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error checking SSL certificates: ' . $e->getMessage()
    ]);
}
?>