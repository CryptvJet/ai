<?php
/**
 * Simple connection test page for debugging web app database connectivity
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #0a0e1a; color: #e2e8f0; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background: #10b981; color: white; }
        .error { background: #ef4444; color: white; }
        .warning { background: #f59e0b; color: white; }
        pre { background: #1e293b; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Database Connection Test</h1>
    
    <?php
    require_once 'api/db_config.php';
    
    echo "<h2>Configuration Status</h2>";
    $config = loadDatabaseConfig();
    
    echo "<div class='test-result warning'>";
    echo "<strong>Database Configuration:</strong><br>";
    echo "PulseCore Host: " . $config['pulse_host'] . "<br>";
    echo "PulseCore User: " . $config['pulse_user'] . "<br>";
    echo "PulseCore DB: " . $config['pulse_db'] . "<br>";
    echo "AI Host: " . $config['ai_host'] . "<br>";
    echo "AI User: " . $config['ai_user'] . "<br>";
    echo "AI DB: " . $config['ai_db'] . "<br>";
    echo "</div>";
    
    echo "<h2>Connection Tests</h2>";
    
    // Test PulseCore Stats API
    echo "<h3>📊 PulseCore Stats API Test</h3>";
    try {
        $api_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/pulsecore-stats.php';
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        $result = file_get_contents($api_url, false, $context);
        $data = json_decode($result, true);
        
        if ($data && $data['success']) {
            echo "<div class='test-result success'>";
            echo "✅ PulseCore Stats API: SUCCESS<br>";
            echo "Total Climaxes: " . ($data['data']['total_climaxes'] ?? 'N/A') . "<br>";
            echo "Total Novas: " . ($data['data']['total_novas'] ?? 'N/A') . "<br>";
            echo "</div>";
        } else {
            echo "<div class='test-result error'>";
            echo "❌ PulseCore Stats API: FAILED<br>";
            echo "Error: " . ($data['error'] ?? 'Unknown error') . "<br>";
            echo "</div>";
        }
        
        echo "<pre>API Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        
    } catch (Exception $e) {
        echo "<div class='test-result error'>";
        echo "❌ PulseCore Stats API: EXCEPTION<br>";
        echo "Error: " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    
    // Test PulseCore Status API
    echo "<h3>🔗 PulseCore Status API Test</h3>";
    try {
        $api_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/pulsecore-status.php';
        $result = file_get_contents($api_url, false, $context);
        $data = json_decode($result, true);
        
        if ($data && $data['success']) {
            echo "<div class='test-result success'>";
            echo "✅ PulseCore Status API: SUCCESS<br>";
            echo "Status: " . ($data['status'] ?? 'N/A') . "<br>";
            echo "Total Novas: " . ($data['data']['total_novas'] ?? 'N/A') . "<br>";
            echo "</div>";
        } else {
            echo "<div class='test-result error'>";
            echo "❌ PulseCore Status API: FAILED<br>";
            echo "Error: " . ($data['error'] ?? 'Unknown error') . "<br>";
            echo "</div>";
        }
        
        echo "<pre>Status API Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        
    } catch (Exception $e) {
        echo "<div class='test-result error'>";
        echo "❌ PulseCore Status API: EXCEPTION<br>";
        echo "Error: " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    
    // Test Ollama Status API
    echo "<h3>🤖 Ollama AI Status Test</h3>";
    try {
        $api_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/ollama-status.php';
        $result = file_get_contents($api_url, false, $context);
        $data = json_decode($result, true);
        
        if ($data && $data['success']) {
            echo "<div class='test-result success'>";
            echo "✅ Ollama AI: SUCCESS<br>";
            echo "Model: " . ($data['data']['model'] ?? 'N/A') . "<br>";
            echo "Response Time: " . ($data['data']['response_time'] ?? 'N/A') . "ms<br>";
            echo "</div>";
        } else {
            echo "<div class='test-result error'>";
            echo "❌ Ollama AI: FAILED<br>";
            echo "Error: " . ($data['error'] ?? 'Unknown error') . "<br>";
            echo "</div>";
        }
        
        echo "<pre>Ollama API Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        
    } catch (Exception $e) {
        echo "<div class='test-result error'>";
        echo "❌ Ollama AI: EXCEPTION<br>";  
        echo "Error: " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    ?>
    
    <h2>Next Steps</h2>
    <div class='test-result warning'>
        <strong>To fix connection issues:</strong><br>
        1. Configure database credentials in desktop app admin panel<br>
        2. Save configurations - this creates files in /data/pws/<br>
        3. Refresh this test page to see updated results<br>
    </div>
    
</body>
</html>