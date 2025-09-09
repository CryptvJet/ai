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

// Admin credentials - in production, store these securely hashed in database
$ADMIN_CREDENTIALS = [
    'admin' => password_hash('PulseCore2024!', PASSWORD_DEFAULT),
    'zin' => password_hash('ZinAdmin123!', PASSWORD_DEFAULT)
];

class AdminAuth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function login($username, $password) {
        global $ADMIN_CREDENTIALS;
        
        // Check credentials
        if (!isset($ADMIN_CREDENTIALS[$username])) {
            return [
                'success' => false,
                'error' => 'Invalid username or password'
            ];
        }
        
        if (!password_verify($password, $ADMIN_CREDENTIALS[$username])) {
            return [
                'success' => false,
                'error' => 'Invalid username or password'
            ];
        }
        
        // Generate session token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (4 * 60 * 60)); // 4 hours
        
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
                WHERE token = ? AND expires_at > NOW()
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
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        exit;
    }
    
    $auth = new AdminAuth($ai_pdo);
    $result = $auth->login($input['username'], $input['password']);
    
    echo json_encode($result);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
}
?>