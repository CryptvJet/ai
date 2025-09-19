<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getOllamaUrl() {
    try {
        require_once 'db_config.php';
        global $ai_pdo;
        
        // Check if $ai_pdo exists and is connected
        if (!isset($ai_pdo) || !$ai_pdo) {
            error_log("Ollama config error: AI database connection not available");
            throw new Exception("AI database connection not available");
        }
        
        // Test the connection
        $ai_pdo->query("SELECT 1");
        
        $stmt = $ai_pdo->prepare("SELECT protocol, host, port FROM ollama_config WHERE id = 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            $protocol = $config['protocol'] ?: 'http';
            $host = trim($config['host'] ?: 'localhost');
            $port = $config['port'] ?: 11434;
            
            // Clean up host (remove any existing protocol)
            $host = preg_replace('/^https?:\/\//', '', $host);
            $url = $protocol . '://' . $host . ':' . $port;
            
            error_log("Ollama config from database: $url");
            return $url;
        } else {
            error_log("Ollama config error: No config found in ollama_config table");
        }
    } catch (PDOException $e) {
        error_log("Ollama config database error: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Ollama config error: " . $e->getMessage());
    }
    
    // Only use fallback if database read completely fails
    error_log("Ollama config: Using fallback URL due to database read failure");
    return 'http://localhost:11434';
}

function checkOllamaStatus() {
    $ollama_url = getOllamaUrl();
    
    error_log("Attempting to connect to Ollama at: " . $ollama_url . '/api/tags');
    
    try {
        // Initialize cURL with proper error handling
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("Failed to initialize cURL");
        }
        
        curl_setopt($ch, CURLOPT_URL, $ollama_url . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("cURL execution failed: " . $error);
            return [
                'success' => false,
                'status' => 'offline',
                'error' => 'Connection failed: ' . $error,
                'models' => []
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Ollama connection result - HTTP Code: $httpCode");
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'status' => 'offline',
                'error' => "HTTP $httpCode",
                'models' => []
            ];
        }
        
        // Validate and parse JSON response
        if (empty($response)) {
            return [
                'success' => false,
                'status' => 'offline',
                'error' => 'Empty response from server',
                'models' => []
            ];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'status' => 'offline',
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'models' => []
            ];
        }
        
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
        
    } catch (Exception $e) {
        error_log("Ollama status check exception: " . $e->getMessage());
        return [
            'success' => false,
            'status' => 'offline',
            'error' => 'Connection error: ' . $e->getMessage(),
            'models' => []
        ];
    }
}

echo json_encode(checkOllamaStatus());
?>