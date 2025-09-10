<?php
/**
 * Simple test to check if Ollama is running locally
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function testOllamaConnection() {
    $ollama_url = 'http://localhost:11434/api/tags';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollama_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'Connection error: ' . $error,
            'ollama_running' => false
        ];
    }
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $models = [];
        
        if (isset($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as $model) {
                $models[] = [
                    'name' => $model['name'],
                    'size' => $model['size'] ?? 0,
                    'modified' => $model['modified_at'] ?? ''
                ];
            }
        }
        
        return [
            'success' => true,
            'ollama_running' => true,
            'models' => $models,
            'model_count' => count($models),
            'url' => $ollama_url
        ];
    } else {
        return [
            'success' => false,
            'error' => 'HTTP ' . $http_code,
            'ollama_running' => false
        ];
    }
}

function testOllamaChat($message = "Hello, are you working?") {
    $ollama_url = 'http://localhost:11434/api/generate';
    
    // Use first available model or default
    $connection_test = testOllamaConnection();
    if (!$connection_test['success'] || empty($connection_test['models'])) {
        return [
            'success' => false,
            'error' => 'No models available for chat test'
        ];
    }
    
    $model = $connection_test['models'][0]['name']; // Use first available model
    
    $request_data = [
        'model' => $model,
        'prompt' => $message,
        'stream' => false
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollama_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'Chat error: ' . $error
        ];
    }
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'model_used' => $model,
            'prompt' => $message,
            'response' => $data['response'] ?? 'No response',
            'done' => $data['done'] ?? false
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Chat HTTP ' . $http_code,
            'response' => $response
        ];
    }
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'connection';
    
    if ($action === 'connection') {
        echo json_encode(testOllamaConnection());
    } elseif ($action === 'chat') {
        $message = $_GET['message'] ?? 'Hello, are you working?';
        echo json_encode(testOllamaChat($message));
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Only GET requests supported']);
}
?>