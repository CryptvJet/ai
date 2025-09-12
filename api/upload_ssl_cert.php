<?php
/**
 * Upload SSL Certificate API
 * Handles individual SSL certificate file upload
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
    // Check if certificate file was uploaded
    if (!isset($_FILES['certificate'])) {
        echo json_encode(['success' => false, 'error' => 'Certificate file is required']);
        exit;
    }
    
    $certFile = $_FILES['certificate'];
    
    // Validate file upload
    if ($certFile['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Certificate file upload failed']);
        exit;
    }
    
    // Validate file type
    $allowedCertExts = ['crt', 'pem', 'cert'];
    $certExt = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
    
    if (!in_array($certExt, $allowedCertExts)) {
        echo json_encode(['success' => false, 'error' => 'Invalid certificate file type. Allowed: .crt, .pem, .cert']);
        exit;
    }
    
    // Read certificate file content
    $certContent = file_get_contents($certFile['tmp_name']);
    if ($certContent === false) {
        throw new Exception('Failed to read certificate file');
    }
    
    // Validate certificate content (basic check)
    if (strpos($certContent, '-----BEGIN CERTIFICATE-----') === false) {
        throw new Exception('Invalid certificate format - must contain BEGIN CERTIFICATE');
    }
    
    // Save certificate content to database
    $updateSql = "INSERT INTO ai_ssl_config (id, cert_uploaded, cert_upload_date, cert_content, cert_filename) 
                  VALUES (1, 1, NOW(), :cert_content, :cert_filename)
                  ON DUPLICATE KEY UPDATE 
                  cert_uploaded = 1, 
                  cert_upload_date = NOW(),
                  cert_content = :cert_content,
                  cert_filename = :cert_filename";
    
    $stmt = $pulse_pdo->prepare($updateSql);
    $stmt->execute([
        ':cert_content' => $certContent,
        ':cert_filename' => $certFile['name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL certificate uploaded successfully',
        'file' => $certFile['name'],
        'database_updated' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to upload SSL certificate',
        'message' => $e->getMessage()
    ]);
}
?>