<?php
/**
 * Authentication and Security Helper functions.
 * Handles secure session initialization, privilege verification, and CSRF protection.
 */

// Prevent direct access to config files if accessed via web server
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Start a secure session if one does not already exist
if (session_status() === PHP_SESSION_NONE) {
    // Determine if connection is secure HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    session_start([
        'cookie_lifetime' => 0,          // Expire when browser closes
        'cookie_path'     => '/',
        'cookie_secure'   => $isSecure,   // True if HTTPS
        'cookie_httponly' => true,        // Prevent JavaScript access to session cookie
        'cookie_samesite' => 'Lax'        // Mitigate CSRF
    ]);
}

/**
 * Log in a user securely and regenerate the session ID to prevent fixation.
 * 
 * @param int $userId
 * @param string $username
 * @param string $role
 */
function login_user(int $userId, string $username, string $role): void {
    session_regenerate_id(true); // Regenerate session ID and delete old session file
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['logged_in_time'] = time();
}

/**
 * Check if a user session is active.
 * 
 * @return bool
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if the active session belongs to an admin.
 * 
 * @return bool
 */
function is_admin(): bool {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

/**
 * Require admin access for the page. Redirects to login page if unauthorized.
 */
function require_admin(): void {
    if (!is_admin()) {
        header("Location: /admin/login.php");
        exit();
    }
}

/**
 * Log out user by clearing and destroying the session.
 */
function logout_user(): void {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
    header("Location: /admin/login.php");
    exit();
}

/**
 * Generate a cryptographically secure CSRF token and store it in session.
 * 
 * @return string
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify if the provided CSRF token matches the session token.
 * Uses a timing-attack resistant comparison.
 * 
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log administrative and security events.
 * 
 * @param int|null $userId
 * @param string $action
 * @param string $entity
 * @param int|null $entityId
 */
function log_audit_action(?int $userId, string $action, string $entity, ?int $entityId = null): void {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $entity, $entityId]);
    } catch (Exception $e) {
        error_log("Audit logging failed: " . $e->getMessage());
    }
}


