<?php
/**
 * Upload SSL Certificates API
 * Handles SSL certificate and key file uploads and updates database
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
    $uploadType = $_POST['upload_type'] ?? 'both';
    
    // Check if files were uploaded based on upload type
    if ($uploadType === 'certificate_only') {
        if (!isset($_FILES['certificate'])) {
            echo json_encode(['success' => false, 'error' => 'Certificate file is required']);
            exit;
        }
        $certFile = $_FILES['certificate'];
        if ($certFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Certificate file upload failed']);
            exit;
        }
    } elseif ($uploadType === 'key_only') {
        if (!isset($_FILES['private_key'])) {
            echo json_encode(['success' => false, 'error' => 'Private key file is required']);
            exit;
        }
        $keyFile = $_FILES['private_key'];
        if ($keyFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Private key file upload failed']);
            exit;
        }
    } else {
        // Original behavior - both files required
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
    }
    
    $certContent = null;
    $keyContent = null;
    $certFileName = null;
    $keyFileName = null;
    
    // Process certificate if needed
    if ($uploadType === 'certificate_only' || $uploadType === 'both') {
        $allowedCertExts = ['crt', 'pem', 'cert'];
        $certExt = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
        
        if (!in_array($certExt, $allowedCertExts)) {
            echo json_encode(['success' => false, 'error' => 'Invalid certificate file type. Allowed: .crt, .pem, .cert']);
            exit;
        }
        
        $certContent = file_get_contents($certFile['tmp_name']);
        if ($certContent === false) {
            throw new Exception('Failed to read certificate file');
        }
        
        if (strpos($certContent, '-----BEGIN CERTIFICATE-----') === false) {
            throw new Exception('Invalid certificate format - must contain BEGIN CERTIFICATE');
        }
        
        $certFileName = $certFile['name'];
    }
    
    // Process private key if needed
    if ($uploadType === 'key_only' || $uploadType === 'both') {
        $allowedKeyExts = ['key', 'pem'];
        $keyExt = strtolower(pathinfo($keyFile['name'], PATHINFO_EXTENSION));
        
        if (!in_array($keyExt, $allowedKeyExts)) {
            echo json_encode(['success' => false, 'error' => 'Invalid private key file type. Allowed: .key, .pem']);
            exit;
        }
        
        $keyContent = file_get_contents($keyFile['tmp_name']);
        if ($keyContent === false) {
            throw new Exception('Failed to read private key file');
        }
        
        if (strpos($keyContent, '-----BEGIN PRIVATE KEY-----') === false && 
            strpos($keyContent, '-----BEGIN RSA PRIVATE KEY-----') === false) {
            throw new Exception('Invalid private key format - must contain BEGIN PRIVATE KEY');
        }
        
        $keyFileName = $keyFile['name'];
    }
    
    // Update database based on upload type
    if ($uploadType === 'certificate_only') {
        $updateSql = "INSERT INTO ai_ssl_config (id, cert_uploaded, cert_upload_date, cert_content, cert_filename) 
                      VALUES (1, 1, NOW(), :cert_content, :cert_filename)
                      ON DUPLICATE KEY UPDATE 
                      cert_uploaded = 1, 
                      cert_upload_date = NOW(),
                      cert_content = :cert_content,
                      cert_filename = :cert_filename";
        
        $stmt = $ai_pdo->prepare($updateSql);
        $stmt->execute([
            ':cert_content' => $certContent,
            ':cert_filename' => $certFileName
        ]);
        
    } elseif ($uploadType === 'key_only') {
        $updateSql = "INSERT INTO ai_ssl_config (id, key_uploaded, key_upload_date, key_content, key_filename) 
                      VALUES (1, 1, NOW(), :key_content, :key_filename)
                      ON DUPLICATE KEY UPDATE 
                      key_uploaded = 1, 
                      key_upload_date = NOW(),
                      key_content = :key_content,
                      key_filename = :key_filename";
        
        $stmt = $ai_pdo->prepare($updateSql);
        $stmt->execute([
            ':key_content' => $keyContent,
            ':key_filename' => $keyFileName
        ]);
        
    } else {
        // Both files
        $updateSql = "INSERT INTO ai_ssl_config (id, cert_uploaded, key_uploaded, cert_upload_date, key_upload_date, cert_content, key_content, cert_filename, key_filename) 
                      VALUES (1, 1, 1, NOW(), NOW(), :cert_content, :key_content, :cert_filename, :key_filename)
                      ON DUPLICATE KEY UPDATE 
                      cert_uploaded = 1, 
                      key_uploaded = 1, 
                      cert_upload_date = NOW(), 
                      key_upload_date = NOW(),
                      cert_content = :cert_content,
                      key_content = :key_content,
                      cert_filename = :cert_filename,
                      key_filename = :key_filename";
        
        $stmt = $ai_pdo->prepare($updateSql);
        $stmt->execute([
            ':cert_content' => $certContent,
            ':key_content' => $keyContent,
            ':cert_filename' => $certFileName,
            ':key_filename' => $keyFileName
        ]);
    }
    
    // Customize response based on upload type
    $message = 'SSL files uploaded successfully';
    $files = [];
    
    if ($uploadType === 'certificate_only') {
        $message = 'SSL certificate uploaded successfully';
        $files['certificate'] = $certFileName;
    } elseif ($uploadType === 'key_only') {
        $message = 'SSL private key uploaded successfully';
        $files['private_key'] = $keyFileName;
    } else {
        $message = 'SSL certificates uploaded successfully';
        $files = [
            'certificate' => $certFileName,
            'private_key' => $keyFileName
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'files' => $files,
        'upload_type' => $uploadType,
        'database_updated' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to upload SSL certificates',
        'message' => $e->getMessage()
    ]);
}
?>