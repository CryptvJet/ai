<?php
/**
 * Fixed Ollama Configuration Loader
 * Handles database connection locally instead of relying on globals
 */
class OllamaConfigLoader {
    private static $config_cache = null;
    
    private static function getDatabase() {
        $DB_HOST = 'localhost';
        $DB_NAME = 'vemite5_pulse-core-ai';
        $DB_USER = 'vemite5_p-core';
        $DB_PASS = 'l%tN!^6^u4=2';
        
        return new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    
    public static function getOllamaUrl() {
        if (self::$config_cache !== null) {
            return self::$config_cache;
        }
        
        try {
            $pdo = self::getDatabase();
            $sql = "SELECT * FROM ollama_config WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch();
            
            if ($config) {
                $protocol = $config['protocol'] ?: 'http';
                $host = trim($config['host'] ?: 'localhost');
                $port = $config['port'] ?: 11434;
                
                if (empty($host)) {
                    $host = 'localhost';
                }
                
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
}
?>