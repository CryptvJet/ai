<?php
/**
 * Simple Ollama Router - Direct connection to Ollama without bridge complications
 */

class SimpleOllamaRouter {
    private $ollama_url = 'http://localhost:11434';
    
    public function routeRequest($message, $session_id, $mode = 'auto', $context = null) {
        // Check if Ollama is available
        if (!$this->isOllamaAvailable()) {
            return ['success' => false, 'error' => 'Ollama not available'];
        }
        
        // Send request to Ollama
        return $this->sendToOllama($message, $context);
    }
    
    private function isOllamaAvailable() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->ollama_url . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return !curl_error($ch) && $httpCode === 200;
    }
    
    private function sendToOllama($message, $context = null) {
        try {
            $payload = [
                'model' => 'llama3.2:3b-instruct-q4_K_M',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an AI assistant specialized in analyzing PulseCore simulations and helping with data analysis. Be helpful and conversational.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $message
                    ]
                ],
                'stream' => false
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->ollama_url . '/api/chat');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_error($ch) || $httpCode !== 200) {
                curl_close($ch);
                return ['success' => false, 'error' => 'Ollama request failed'];
            }
            
            curl_close($ch);
            $data = json_decode($response, true);
            
            if (isset($data['message']['content'])) {
                return [
                    'success' => true,
                    'response' => $data['message']['content']
                ];
            }
            
            return ['success' => false, 'error' => 'Invalid Ollama response'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>