<?php
/**
 * Upload SSL Private Key API
 * Handles individual SSL private key file upload
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

try {
    // Check if private key file was uploaded
    if (!isset($_FILES['private_key'])) {
        echo json_encode(['success' => false, 'error' => 'Private key file is required']);
        exit;
    }
    
    $keyFile = $_FILES['private_key'];
    
    // Validate file upload
    if ($keyFile['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Private key file upload failed']);
        exit;
    }
    
    // Validate file type
    $allowedKeyExts = ['key', 'pem'];
    $keyExt = strtolower(pathinfo($keyFile['name'], PATHINFO_EXTENSION));
    
    if (!in_array($keyExt, $allowedKeyExts)) {
        echo json_encode(['success' => false, 'error' => 'Invalid private key file type. Allowed: .key, .pem']);
        exit;
    }
    
    // Ensure certificates directory exists
    $certsDir = __DIR__ . '/../../data/pws/certs';
    if (!is_dir($certsDir)) {
        mkdir($certsDir, 0755, true);
    }
    
    // Move uploaded file to secure location
    $keyDestination = $certsDir . '/server.key';
    
    if (!move_uploaded_file($keyFile['tmp_name'], $keyDestination)) {
        throw new Exception('Failed to save private key file');
    }
    
    // Set secure file permissions (more restrictive for private key)
    chmod($keyDestination, 0600);
    
    // Update database to mark private key as uploaded
    $updateSql = "INSERT INTO ai_ssl_config (id, key_uploaded, key_upload_date) 
                  VALUES (1, 1, NOW())
                  ON DUPLICATE KEY UPDATE 
                  key_uploaded = 1, 
                  key_upload_date = NOW()";
    
    $stmt = $ai_pdo->prepare($updateSql);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL private key uploaded successfully',
        'file' => 'server.key',
        'database_updated' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to upload SSL private key',
        'message' => $e->getMessage()
    ]);
}
?>