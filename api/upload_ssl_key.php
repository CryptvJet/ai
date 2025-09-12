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
    
    // Read private key file content
    $keyContent = file_get_contents($keyFile['tmp_name']);
    if ($keyContent === false) {
        throw new Exception('Failed to read private key file');
    }
    
    // Validate private key content (basic check)
    if (strpos($keyContent, '-----BEGIN PRIVATE KEY-----') === false && 
        strpos($keyContent, '-----BEGIN RSA PRIVATE KEY-----') === false) {
        throw new Exception('Invalid private key format - must contain BEGIN PRIVATE KEY');
    }
    
    // Save private key content to database
    $updateSql = "INSERT INTO ai_ssl_config (id, key_uploaded, key_upload_date, key_content, key_filename) 
                  VALUES (1, 1, NOW(), :key_content, :key_filename)
                  ON DUPLICATE KEY UPDATE 
                  key_uploaded = 1, 
                  key_upload_date = NOW(),
                  key_content = :key_content,
                  key_filename = :key_filename";
    
    $stmt = $pulse_pdo->prepare($updateSql);
    $stmt->execute([
        ':key_content' => $keyContent,
        ':key_filename' => $keyFile['name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL private key uploaded successfully',
        'file' => $keyFile['name'],
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