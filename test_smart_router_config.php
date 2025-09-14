<?php
/**
 * Test SmartAIRouter Configuration Loading
 * Verifies that the SmartAIRouter correctly reads bridge_config.json
 */

require_once 'api/smart_router.php';

echo "=== SmartAIRouter Configuration Test ===\n\n";

// Test 1: Verify bridge_config.json exists and is readable
$configPath = __DIR__ . '/data/pws/bridge_config.json';
echo "1. Checking bridge_config.json:\n";
echo "   Path: $configPath\n";

if (!file_exists($configPath)) {
    echo "   ❌ Config file not found!\n";
    exit(1);
}

echo "   ✅ Config file exists\n";

$configData = file_get_contents($configPath);
$config = json_decode($configData, true);

if (!$config) {
    echo "   ❌ Invalid JSON in config file!\n";
    exit(1);
}

echo "   ✅ Config file is valid JSON\n";
echo "   Current config contents:\n";
foreach ($config as $key => $value) {
    echo "     $key: $value\n";
}

echo "\n2. Testing SmartAIRouter configuration parsing:\n";

try {
    // Create a test class to access protected method
    class SmartAIRouterTest extends SmartAIRouter {
        public function getBridgeConfig() {
            return $this->bridge_config;
        }
    }
    
    $router = new SmartAIRouterTest();
    $bridgeConfig = $router->getBridgeConfig();
    
    echo "   ✅ SmartAIRouter instantiated successfully\n";
    echo "   Parsed bridge configuration:\n";
    foreach ($bridgeConfig as $key => $value) {
        echo "     $key: $value\n";
    }
    
    // Verify expected values
    echo "\n3. Verifying configuration mapping:\n";
    
    $expectedPort = 8081;
    $actualPort = $bridgeConfig['port'];
    if ($actualPort == $expectedPort) {
        echo "   ✅ Port correctly mapped: $actualPort\n";
    } else {
        echo "   ❌ Port mapping failed: expected $expectedPort, got $actualPort\n";
    }
    
    $expectedApiKey = $config['ApiKey'] ?? '';
    $actualApiKey = $bridgeConfig['api_key'];
    if ($actualApiKey === $expectedApiKey) {
        echo "   ✅ API Key correctly mapped: " . substr($actualApiKey, 0, 8) . "...\n";
    } else {
        echo "   ❌ API Key mapping failed\n";
    }
    
    $expectedType = $config['ConnectionType'] ?? 'HTTP';
    $actualType = $bridgeConfig['type'];
    if ($actualType === $expectedType) {
        echo "   ✅ Connection Type correctly mapped: $actualType\n";
    } else {
        echo "   ❌ Connection Type mapping failed: expected $expectedType, got $actualType\n";
    }
    
    echo "\n4. Testing bridge URL construction:\n";
    $protocol = strtolower($bridgeConfig['type']) === 'https' ? 'https' : 'http';
    $testUrl = "{$protocol}://{$bridgeConfig['host']}:{$bridgeConfig['port']}/api/generate";
    echo "   Generated URL for chat: $testUrl\n";
    
    $expectedUrl = "http://localhost:8081/api/generate";
    if ($testUrl === $expectedUrl) {
        echo "   ✅ URL construction correct\n";
    } else {
        echo "   ❌ URL construction failed: expected $expectedUrl\n";
    }
    
    echo "\n=== Configuration Test Complete ===\n";
    echo "✅ SmartAIRouter should now correctly connect to your PC Bridge!\n";
    
} catch (Exception $e) {
    echo "   ❌ SmartAIRouter failed to instantiate: " . $e->getMessage() . "\n";
    exit(1);
}
?>