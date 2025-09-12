<?php
/**
 * Upload SSL Certificates API
 * Handles SSL certificate and key file uploads
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

try {
    // Check if files were uploaded
    if (!isset($_FILES['certificate']) || !isset($_FILES['private_key'])) {
        echo json_encode(['success' => false, 'error' => 'Both certificate and private key files are required']);
        exit;
    }
    
    $certFile = $_FILES['certificate'];
    $keyFile = $_FILES['private_key'];
    
    // Validate file uploads
    if ($certFile['error'] !== UPLOAD_ERR_OK || $keyFile['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
        exit;
    }
    
    // Validate file types (basic check)
    $allowedCertExts = ['crt', 'pem', 'cert'];
    $allowedKeyExts = ['key', 'pem'];
    
    $certExt = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
    $keyExt = strtolower(pathinfo($keyFile['name'], PATHINFO_EXTENSION));
    
    if (!in_array($certExt, $allowedCertExts) || !in_array($keyExt, $allowedKeyExts)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file types']);
        exit;
    }
    
    // Ensure certificates directory exists
    $certsDir = __DIR__ . '/../../data/pws/certs';
    if (!is_dir($certsDir)) {
        mkdir($certsDir, 0755, true);
    }
    
    // Move uploaded files to secure location
    $certDestination = $certsDir . '/server.crt';
    $keyDestination = $certsDir . '/server.key';
    
    if (!move_uploaded_file($certFile['tmp_name'], $certDestination)) {
        throw new Exception('Failed to save certificate file');
    }
    
    if (!move_uploaded_file($keyFile['tmp_name'], $keyDestination)) {
        // Clean up certificate file if key upload failed
        unlink($certDestination);
        throw new Exception('Failed to save private key file');
    }
    
    // Set secure file permissions
    chmod($certDestination, 0644);
    chmod($keyDestination, 0600); // Private key should be more restrictive
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL certificates uploaded successfully',
        'files' => [
            'certificate' => 'server.crt',
            'private_key' => 'server.key'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to upload SSL certificates',
        'message' => $e->getMessage()
    ]);
}
?>