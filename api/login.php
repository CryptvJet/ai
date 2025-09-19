<?php
/**
 * Admin Login API
 * Handles admin authentication and session management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_config.php';

// Admin credentials are now stored in database table: ai_admin_users

class AdminAuth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function login($username, $password) {
        // Get user from database
        try {
            $stmt = $this->pdo->prepare("
                SELECT username, password_hash, is_active 
                FROM ai_admin_users 
                WHERE username = ? AND is_active = TRUE
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                error_log("LOGIN DEBUG: No user found for username: $username");
                return [
                    'success' => false,
                    'error' => 'Invalid username or password'
                ];
            }
            
            error_log("LOGIN DEBUG: Found user: {$user['username']}, checking password...");
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                error_log("LOGIN DEBUG: Password verification failed for user: $username");
                error_log("LOGIN DEBUG: Password hash in DB: " . substr($user['password_hash'], 0, 20) . "...");
                return [
                    'success' => false,
                    'error' => 'Invalid username or password'
                ];
            }
            
            error_log("LOGIN DEBUG: Password verification successful for user: $username");
            
            // Update last login time
            $stmt = $this->pdo->prepare("
                UPDATE ai_admin_users 
                SET last_login = CURRENT_TIMESTAMP 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            
        } catch (Exception $e) {
            error_log('Database error during login: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Authentication system temporarily unavailable'
            ];
        }
        
        // Generate session token
        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + (4 * 60 * 60)); // 4 hours UTC
        
        // Store session in database
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_admin_sessions (username, token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    token = VALUES(token),
                    expires_at = VALUES(expires_at),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    last_activity = NOW()
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt->execute([$username, $token, $expires, $ip, $userAgent]);
            
            // Log successful login
            $this->logActivity($username, 'login_success', $ip);
            
            return [
                'success' => true,
                'token' => $token,
                'expires' => strtotime($expires) * 1000, // JavaScript timestamp
                'username' => $username
            ];
            
        } catch (Exception $e) {
            error_log('Admin login error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Login system temporarily unavailable'
            ];
        }
    }
    
    public function validateSession($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT username, expires_at 
                FROM ai_admin_sessions 
                WHERE token = ? AND expires_at > UTC_TIMESTAMP()
            ");
            $stmt->execute([$token]);
            $session = $stmt->fetch();
            
            if ($session) {
                // Update last activity
                $stmt = $this->pdo->prepare("
                    UPDATE ai_admin_sessions 
                    SET last_activity = NOW() 
                    WHERE token = ?
                ");
                $stmt->execute([$token]);
                
                return [
                    'success' => true,
                    'username' => $session['username'],
                    'expires' => strtotime($session['expires_at']) * 1000
                ];
            }
            
            return ['success' => false, 'error' => 'Invalid or expired session'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Session validation failed'];
        }
    }
    
    public function logout($token) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM ai_admin_sessions WHERE token = ?");
            $stmt->execute([$token]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Logout failed'];
        }
    }
    
    public function requestPasswordReset($email) {
        try {
            // Find user by email
            $stmt = $this->pdo->prepare("
                SELECT username, email 
                FROM ai_admin_users 
                WHERE email = ? AND is_active = TRUE
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Don't reveal if email exists for security
                return [
                    'success' => true,
                    'message' => 'If an account with that email exists, a reset link has been sent.'
                ];
            }
            
            // Generate secure reset token
            $resetToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutes
            
            // Store reset token in database
            $stmt = $this->pdo->prepare("
                UPDATE ai_admin_users 
                SET reset_token = ?, reset_token_expires = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE email = ?
            ");
            
            if ($stmt->execute([$resetToken, $expires, $email])) {
                // Send reset email
                $this->sendResetEmail($email, $user['username'], $resetToken);
                
                // Log the reset request
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $this->logActivity($user['username'], 'password_reset_requested', $ip);
                
                return [
                    'success' => true,
                    'message' => 'If an account with that email exists, a reset link has been sent.'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to generate reset token'
                ];
            }
            
        } catch (Exception $e) {
            error_log('Password reset request error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Password reset system temporarily unavailable'
            ];
        }
    }
    
    public function resetPasswordWithToken($token, $newPassword) {
        try {
            // Find user with valid reset token
            $stmt = $this->pdo->prepare("
                SELECT username, email, reset_token_expires 
                FROM ai_admin_users 
                WHERE reset_token = ? AND is_active = TRUE
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Invalid or expired reset token'
                ];
            }
            
            // Check if token is expired
            if (strtotime($user['reset_token_expires']) < time()) {
                return [
                    'success' => false,
                    'error' => 'Reset token has expired. Please request a new one.'
                ];
            }
            
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $stmt = $this->pdo->prepare("
                UPDATE ai_admin_users 
                SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = CURRENT_TIMESTAMP 
                WHERE reset_token = ?
            ");
            
            if ($stmt->execute([$hashedPassword, $token])) {
                // Log the password reset completion
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $this->logActivity($user['username'], 'password_reset_completed', $ip);
                
                return [
                    'success' => true,
                    'message' => 'Password reset successfully! You can now login with your new password.'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to update password'
                ];
            }
            
        } catch (Exception $e) {
            error_log('Password reset completion error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Password reset system temporarily unavailable'
            ];
        }
    }
    
    private function sendResetEmail($email, $username, $resetToken) {
        // Get the site URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetUrl = "$protocol://$host/ai/admin/reset-password.html?token=$resetToken";
        
        $subject = "Admin Password Reset Request";
        $message = "
Hello $username,

You have requested a password reset for your admin account.

Click the link below to reset your password:
$resetUrl

This link will expire in 30 minutes for security.

If you did not request this reset, please ignore this email.

Best regards,
AI Admin System
        ";
        
        $headers = [
            'From: noreply@' . ($host ?: 'localhost'),
            'Reply-To: noreply@' . ($host ?: 'localhost'),
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        // Send email
        if (mail($email, $subject, $message, implode("\r\n", $headers))) {
            error_log("Password reset email sent to: $email");
        } else {
            error_log("Failed to send password reset email to: $email");
        }
    }
    
    private function logActivity($username, $action, $ip) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_admin_logs (username, action, ip_address, timestamp) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $action, $ip]);
        } catch (Exception $e) {
            // Log errors but don't fail the main operation
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }
    
    $auth = new AdminAuth($ai_pdo);
    
    // Check if this is a password reset request
    if (isset($input['action']) && $input['action'] === 'request_reset') {
        if (!isset($input['email'])) {
            echo json_encode(['success' => false, 'error' => 'Email address required']);
            exit;
        }
        
        $result = $auth->requestPasswordReset($input['email']);
        echo json_encode($result);
        
    } elseif (isset($input['action']) && $input['action'] === 'reset_password') {
        if (!isset($input['token']) || !isset($input['new_password'])) {
            echo json_encode(['success' => false, 'error' => 'Reset token and new password required']);
            exit;
        }
        
        $result = $auth->resetPasswordWithToken($input['token'], $input['new_password']);
        echo json_encode($result);
        
    } else {
        // Regular login request
        if (!isset($input['username']) || !isset($input['password'])) {
            echo json_encode(['success' => false, 'error' => 'Username and password required']);
            exit;
        }
        
        $result = $auth->login($input['username'], $input['password']);
        echo json_encode($result);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>