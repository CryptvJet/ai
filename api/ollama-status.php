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
    // Load bridge configuration to connect to PC where Ollama is running
    $configPath = __DIR__ . '/../data/pws/bridge_config.json';
    
    if (file_exists($configPath)) {
        $configData = file_get_contents($configPath);
        $bridgeConfig = json_decode($configData, true);
        
        if ($bridgeConfig && isset($bridgeConfig['Host'])) {
            $protocol = strtolower($bridgeConfig['ConnectionType']) === 'https' ? 'https' : 'http';
            $bridge_url = $protocol . '://' . $bridgeConfig['Host'] . ':' . $bridgeConfig['Port'];
            $ollama_url = $bridge_url . '/ollama'; // Bridge should proxy Ollama requests
        } else {
            $ollama_url = 'http://localhost:11434'; // Fallback
        }
    } else {
        $ollama_url = 'http://localhost:11434'; // Fallback
    }
    
    // Check if Ollama is running
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollama_url . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
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