<?php
/**
 * Centralized Ollama Configuration Loader
 * Reads configuration from database without hardcoding table names
 */

class OllamaConfigLoader {
    private static $config_cache = null;
    
    public static function getOllamaUrl() {
        if (self::$config_cache !== null) {
            return self::$config_cache;
        }
        
        try {
            require_once __DIR__ . '/db_config.php';
            global $ai_pdo;
            
            // Load database configuration to get the correct database and table names
            $db_config_path = __DIR__ . '/../data/pws/ai_db_config.json';
            if (!file_exists($db_config_path)) {
                throw new Exception('Database configuration file not found');
            }
            
            $db_config = json_decode(file_get_contents($db_config_path), true);
            if (!$db_config) {
                throw new Exception('Invalid database configuration');
            }
            
            $database_name = $db_config['Database'];
            $ai_pdo->exec("USE `$database_name`");
            
            // Get Ollama configuration from the configured table
            $sql = "SELECT * FROM ollama_config WHERE id = 1";
            $stmt = $ai_pdo->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch();
            
            if ($config) {
                $protocol = $config['protocol'] ?: 'http';
                $host = trim($config['host'] ?: 'localhost');
                $port = $config['port'] ?: 11434;
                
                // Validate host
                if (empty($host)) {
                    $host = 'localhost';
                }
                
                // Clean host of any protocol prefixes
                $host = preg_replace('/^https?:\/\//', '', $host);
                
                $url = $protocol . '://' . $host . ':' . $port;
                self::$config_cache = $url;
                
                error_log("Ollama Config Loaded: " . $url);
                return $url;
            } else {
                throw new Exception('No Ollama configuration found in database');
            }
            
        } catch (Exception $e) {
            error_log("Failed to load Ollama config: " . $e->getMessage());
            $fallback_url = 'http://localhost:11434';
            self::$config_cache = $fallback_url;
            return $fallback_url;
        }
    }
    
    public static function clearCache() {
        self::$config_cache = null;
    }
    
    public static function getConfig() {
        try {
            require_once __DIR__ . '/db_config.php';
            global $ai_pdo;
            
            // Load database configuration
            $db_config_path = __DIR__ . '/../data/pws/ai_db_config.json';
            if (!file_exists($db_config_path)) {
                throw new Exception('Database configuration file not found');
            }
            
            $db_config = json_decode(file_get_contents($db_config_path), true);
            if (!$db_config) {
                throw new Exception('Invalid database configuration');
            }
            
            $database_name = $db_config['Database'];
            $ai_pdo->exec("USE `$database_name`");
            
            $sql = "SELECT * FROM ollama_config WHERE id = 1";
            $stmt = $ai_pdo->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch();
            
            if ($config) {
                return [
                    'host' => $config['host'] ?? 'localhost',
                    'port' => (int)($config['port'] ?? 11434),
                    'protocol' => $config['protocol'] ?? 'http',
                    'default_model' => $config['default_model'] ?? 'llama3.2'
                ];
            }
            
            return [
                'host' => 'localhost',
                'port' => 11434,
                'protocol' => 'http',
                'default_model' => 'llama3.2'
            ];
            
        } catch (Exception $e) {
            error_log("Failed to load Ollama config: " . $e->getMessage());
            return [
                'host' => 'localhost',
                'port' => 11434,
                'protocol' => 'http',
                'default_model' => 'llama3.2'
            ];
        }
    }
}
?>