<?php
/**
 * AI Database Migration Tool
 * Handles database schema changes and updates
 */

// Include database configuration
require_once 'api/db_config.php';

class AIMigrator {
    private $pdo;
    private $migrations_path;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->migrations_path = __DIR__ . '/migrations/';
        $this->createMigrationsTable();
    }
    
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ai_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT TRUE,
            error_message TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->pdo->exec($sql);
    }
    
    public function getMigrationFiles() {
        $files = glob($this->migrations_path . '*.sql');
        sort($files);
        return array_map('basename', $files);
    }
    
    public function getExecutedMigrations() {
        $stmt = $this->pdo->query("SELECT filename FROM ai_migrations WHERE success = 1");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getPendingMigrations() {
        $all = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        return array_diff($all, $executed);
    }
    
    public function runMigration($filename) {
        $filepath = $this->migrations_path . $filename;
        
        if (!file_exists($filepath)) {
            throw new Exception("Migration file not found: $filename");
        }
        
        $sql = file_get_contents($filepath);
        
        try {
            $this->pdo->beginTransaction();
            
            // Execute the migration
            $this->pdo->exec($sql);
            
            // Record the migration
            $stmt = $this->pdo->prepare("INSERT INTO ai_migrations (filename, success) VALUES (?, 1)");
            $stmt->execute([$filename]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Record the failed migration
            $stmt = $this->pdo->prepare("INSERT INTO ai_migrations (filename, success, error_message) VALUES (?, 0, ?)");
            $stmt->execute([$filename, $e->getMessage()]);
            
            throw new Exception("Migration failed: " . $e->getMessage());
        }
    }
    
    public function runAllPending() {
        $pending = $this->getPendingMigrations();
        $results = [];
        
        foreach ($pending as $migration) {
            try {
                $this->runMigration($migration);
                $results[$migration] = ['success' => true, 'message' => 'Executed successfully'];
            } catch (Exception $e) {
                $results[$migration] = ['success' => false, 'message' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    public function getMigrationStatus() {
        $all = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        
        $status = [];
        foreach ($all as $migration) {
            $status[$migration] = in_array($migration, $executed);
        }
        
        return $status;
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    try {
        $migrator = new AIMigrator($ai_pdo);
        
        $command = $argv[1] ?? 'status';
        
        switch ($command) {
            case 'status':
                echo "Migration Status:\n";
                echo "================\n";
                $status = $migrator->getMigrationStatus();
                foreach ($status as $migration => $executed) {
                    $status_text = $executed ? '[✓] EXECUTED' : '[✗] PENDING';
                    echo "$status_text $migration\n";
                }
                break;
                
            case 'run':
                $migration = $argv[2] ?? null;
                if (!$migration) {
                    echo "Usage: php migrate.php run <migration_filename>\n";
                    exit(1);
                }
                
                echo "Running migration: $migration\n";
                $migrator->runMigration($migration);
                echo "Migration completed successfully!\n";
                break;
                
            case 'migrate':
                echo "Running all pending migrations...\n";
                $results = $migrator->runAllPending();
                
                foreach ($results as $migration => $result) {
                    $status = $result['success'] ? '[✓]' : '[✗]';
                    echo "$status $migration - {$result['message']}\n";
                }
                break;
                
            case 'pending':
                $pending = $migrator->getPendingMigrations();
                if (empty($pending)) {
                    echo "No pending migrations.\n";
                } else {
                    echo "Pending migrations:\n";
                    foreach ($pending as $migration) {
                        echo "- $migration\n";
                    }
                }
                break;
                
            default:
                echo "Available commands:\n";
                echo "  status  - Show migration status\n";
                echo "  pending - Show pending migrations\n";
                echo "  migrate - Run all pending migrations\n";
                echo "  run <file> - Run specific migration\n";
                break;
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Web interface for development
if (isset($_GET['web_migrate'])) {
    header('Content-Type: application/json');
    
    try {
        $migrator = new AIMigrator($ai_pdo);
        
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                echo json_encode([
                    'success' => true,
                    'migrations' => $migrator->getMigrationStatus(),
                    'pending_count' => count($migrator->getPendingMigrations())
                ]);
                break;
                
            case 'migrate':
                $results = $migrator->runAllPending();
                echo json_encode([
                    'success' => true,
                    'results' => $results
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>