<?php
/**
 * Direct Ollama Status API
 * Replaces the old PC Bridge system with direct Ollama connection
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function checkOllamaStatus() {
    // Load Ollama configuration from database
    try {
        require_once __DIR__ . '/db_config.php';
        $ai_pdo->exec("USE `vemite5_pulse-core-ai`");
        
        $sql = "SELECT * FROM ollama_config WHERE id = 1";
        $stmt = $ai_pdo->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetch();
        
        if ($config) {
            $protocol = $config['protocol'] ?: 'http';
            $host = trim($config['host'] ?: 'localhost');
            $port = $config['port'] ?: 11434;
            
            // Validate host - only default to localhost if truly empty
            if (empty($host)) {
                $host = 'localhost';
            }
            
            // Remove any protocol prefix from host if accidentally included
            $host = preg_replace('/^https?:\/\//', '', $host);
            
            $ollama_url = $protocol . '://' . $host . ':' . $port;
            
            // Log the constructed URL for debugging
            error_log("Ollama URL constructed: " . $ollama_url);
        } else {
            $ollama_url = 'http://localhost:11434'; // Fallback if no config
        }
        
    } catch (Exception $e) {
        error_log("Failed to load Ollama config from database: " . $e->getMessage());
        $ollama_url = 'http://localhost:11434'; // Fallback on error
    }
    
    // Check if Ollama is running
    error_log("Attempting to connect to Ollama at: " . $ollama_url . '/api/tags');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollama_url . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("Ollama connection result - HTTP Code: $httpCode, Error: " . ($error ?: 'none'));
    
    if ($error || $httpCode !== 200) {
        return [
            'success' => false,
            'status' => 'offline',
            'error' => $error ?: "HTTP $httpCode",
            'models' => []
        ];
    }
    
    $data = json_decode($response, true);
    $models = $data['models'] ?? [];
    
    // Get the primary model (first one or the one that looks like the main model)
    $primaryModel = null;
    if (!empty($models)) {
        $primaryModel = $models[0]['name']; // Use first model as primary
        
        // Look for llama models specifically
        foreach ($models as $model) {
            if (strpos($model['name'], 'llama') !== false) {
                $primaryModel = $model['name'];
                break;
            }
        }
    }
    
    // Test response time
    $start = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollama_url . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
    $responseTime = round((microtime(true) - $start) * 1000); // ms
    
    return [
        'success' => true,
        'status' => 'online',
        'models' => $models,
        'primary_model' => $primaryModel,
        'model_count' => count($models),
        'response_time_ms' => $responseTime,
        'server_url' => $ollama_url
    ];
}

// Return the status
echo json_encode(checkOllamaStatus());
?>