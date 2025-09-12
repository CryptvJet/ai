<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Check if SSL certificate files exist
    $certsDir = dirname(__DIR__) . '/data/pws/certs';
    $certFile = $certsDir . '/server.crt';
    $keyFile = $certsDir . '/server.key';
    
    $certExists = file_exists($certFile);
    $keyExists = file_exists($keyFile);
    
    // Load SSL configuration to check if it's enabled
    $configFile = dirname(__DIR__) . '/data/pws/ssl_config.json';
    $sslEnabled = false;
    $sslPort = 8443;
    
    if (file_exists($configFile)) {
        $configData = file_get_contents($configFile);
        $config = json_decode($configData, true);
        if ($config) {
            $sslEnabled = $config['enabled'] ?? false;
            $sslPort = $config['port'] ?? 8443;
        }
    }
    
    echo json_encode([
        'success' => true,
        'certificate_exists' => $certExists,
        'key_exists' => $keyExists,
        'ssl_enabled' => $sslEnabled,
        'ssl_port' => $sslPort,
        'both_uploaded' => $certExists && $keyExists
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error checking SSL certificates: ' . $e->getMessage()
    ]);
}
?>