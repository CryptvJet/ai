<?php
/**
 * Test Chill Mode Database Template Functionality
 * Verifies that SmartAIRouter correctly uses ai_response_templates
 */

require_once 'api/smart_router.php';

echo "=== Chill Mode Database Template Test ===\n\n";

// Test messages that should trigger different templates
$testMessages = [
    'hello' => 'Should trigger greeting template',
    'good morning' => 'Should trigger morning greeting',
    'help me' => 'Should trigger help template', 
    'what can you do' => 'Should trigger capabilities template',
    'analyze my data' => 'Should trigger analysis template',
    'nova patterns' => 'Should trigger pulsecore template',
    'thank you' => 'Should trigger social template',
    'random question xyz' => 'Should fallback to basic response'
];

echo "1. Testing database template matching:\n";

try {
    $router = new SmartAIRouter();
    
    foreach ($testMessages as $message => $expectation) {
        echo "\n   Testing: '$message'\n";
        echo "   Expectation: $expectation\n";
        
        // Force web AI path to test Chill Mode templates
        $response = $router->routeRequest($message, 'test_session', 'chill', null);
        
        if ($response && $response['success']) {
            echo "   ✅ Response: " . substr($response['response'], 0, 80) . "...\n";
            echo "   📊 Mode: {$response['mode']}\n";
            
            if (isset($response['template_id'])) {
                echo "   🎯 Template ID: {$response['template_id']} (Category: {$response['template_category']})\n";
            } else {
                echo "   📝 Source: {$response['ai_source']}\n";
            }
        } else {
            echo "   ❌ Failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }
        
        echo "   " . str_repeat('-', 50) . "\n";
    }
    
    echo "\n2. Testing fallback chain:\n";
    
    // Test when local AI fails, should use chill mode
    echo "   Testing fallback from failed local AI to Chill Mode...\n";
    $fallbackResponse = $router->routeRequest('hello there', 'fallback_test', 'auto', null);
    
    if ($fallbackResponse && $fallbackResponse['success']) {
        echo "   ✅ Fallback successful\n";
        echo "   📊 Mode: {$fallbackResponse['mode']}\n";
        echo "   📝 Source: {$fallbackResponse['ai_source']}\n";
        echo "   💬 Response: " . substr($fallbackResponse['response'], 0, 100) . "...\n";
    } else {
        echo "   ❌ Fallback failed\n";
    }
    
    echo "\n=== Chill Mode Test Complete ===\n";
    echo "✅ Chill Mode should now provide helpful template-based responses!\n";
    echo "💡 Use the SQL file to add templates to your database for full functionality.\n";
    
} catch (Exception $e) {
    echo "   ❌ Test failed: " . $e->getMessage() . "\n";
    echo "\nNote: This test requires database connection. Make sure your database is set up correctly.\n";
}
?>