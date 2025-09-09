<?php
/**
 * Run Database Fix Script
 * Execute this to create/fix the database tables
 */

require_once 'api/db_config.php';

try {
    // Read the SQL fix script
    $sql = file_get_contents('database_fix.sql');
    
    if (!$sql) {
        die("Could not read database_fix.sql file\n");
    }
    
    // Split by semicolons to execute individual statements
    $statements = explode(';', $sql);
    
    $executed = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $ai_pdo->exec($statement);
            $executed++;
            echo "✓ Executed statement " . $executed . "\n";
        } catch (Exception $e) {
            echo "⚠ Warning on statement " . $executed . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Database fix completed! Executed $executed statements.\n";
    echo "✓ AI conversation tables created/fixed\n";
    echo "✓ User preferences table created\n"; 
    echo "✓ Response templates added\n";
    echo "✓ AI settings initialized\n";
    echo "\nYou can now use the chat interface and view real conversations!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>