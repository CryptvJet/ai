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
    // Get SSL configuration from database
    $sql = "SELECT * FROM ai_ssl_config WHERE id = 1";
    $stmt = $ai_pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch();
    
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
    
    // If database says files are uploaded but content is missing, update database
    if ($config['cert_uploaded'] && !$certContentExists) {
        $updateSql = "UPDATE ai_ssl_config SET cert_uploaded = 0, cert_upload_date = NULL WHERE id = 1";
        $ai_pdo->prepare($updateSql)->execute();
        $config['cert_uploaded'] = false;
    }
    
    if ($config['key_uploaded'] && !$keyContentExists) {
        $updateSql = "UPDATE ai_ssl_config SET key_uploaded = 0, key_upload_date = NULL WHERE id = 1";
        $ai_pdo->prepare($updateSql)->execute();
        $config['key_uploaded'] = false;
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