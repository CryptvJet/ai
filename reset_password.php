<?php
/**
 * Password Reset Utility
 * Run this script to reset admin passwords by email
 * 
 * Usage: php reset_password.php
 */

require_once 'api/db_config.php';
require_once 'api/login.php';

echo "==================================\n";
echo "AI Admin Password Reset Utility\n";
echo "==================================\n\n";

// Get email from command line or prompt
if (isset($argv[1])) {
    $email = $argv[1];
} else {
    echo "Enter email address: ";
    $email = trim(fgets(STDIN));
}

if (empty($email)) {
    echo "Error: Email address is required\n";
    exit(1);
}

// Get new password from command line or prompt
if (isset($argv[2])) {
    $newPassword = $argv[2];
} else {
    echo "Enter new password: ";
    $newPassword = trim(fgets(STDIN));
}

if (empty($newPassword)) {
    echo "Error: New password is required\n";
    exit(1);
}

if (strlen($newPassword) < 8) {
    echo "Error: Password must be at least 8 characters long\n";
    exit(1);
}

echo "\nAttempting to reset password for: $email\n";

try {
    $auth = new AdminAuth($ai_pdo);
    $result = $auth->resetPassword($email, $newPassword);
    
    if ($result['success']) {
        echo "✅ Success: " . $result['message'] . "\n";
        echo "\nYou can now login with:\n";
        echo "Email: $email\n";
        echo "Password: $newPassword\n\n";
        echo "Access admin panel at: ai/admin/login.html\n";
    } else {
        echo "❌ Error: " . $result['error'] . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n==================================\n";
echo "Password reset completed!\n";
echo "==================================\n";
?>