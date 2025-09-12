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
    
    // Ensure certificates directory exists
    $certsDir = __DIR__ . '/../../data/pws/certs';
    if (!is_dir($certsDir)) {
        mkdir($certsDir, 0755, true);
    }
    
    // Move uploaded file to secure location
    $certDestination = $certsDir . '/server.crt';
    
    if (!move_uploaded_file($certFile['tmp_name'], $certDestination)) {
        throw new Exception('Failed to save certificate file');
    }
    
    // Set secure file permissions
    chmod($certDestination, 0644);
    
    // Update database to mark certificate as uploaded
    $updateSql = "INSERT INTO ai_ssl_config (id, cert_uploaded, cert_upload_date) 
                  VALUES (1, 1, NOW())
                  ON DUPLICATE KEY UPDATE 
                  cert_uploaded = 1, 
                  cert_upload_date = NOW()";
    
    $stmt = $pulse_pdo->prepare($updateSql);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL certificate uploaded successfully',
        'file' => 'server.crt',
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