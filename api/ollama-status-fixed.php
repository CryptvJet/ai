<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function checkOllamaStatus() {
    // Use direct SSH tunnel connection instead of database config
    $ollama_url = 'http://localhost:11434';

    error_log("Attempting to connect to Ollama at: " . $ollama_url . '/api/tags');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollama_url . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

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

    $primaryModel = null;
    if (!empty($models)) {
        $primaryModel = $models[0]['name'];
        foreach ($models as $model) {
            if (strpos($model['name'], 'llama') !== false) {
                $primaryModel = $model['name'];
                break;
            }
        }
    }

    return [
        'success' => true,
        'status' => 'online',
        'models' => $models,
        'primary_model' => $primaryModel,
        'model_count' => count($models),
        'server_url' => $ollama_url
    ];
}

echo json_encode(checkOllamaStatus());
?>