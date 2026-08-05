<?php
/**
 * Administrator Secure Logout Handler.
 */

// Include security and helper configs
require_once __DIR__ . '/../config/auth.php';

// Log logout event in database if session was active
if (is_logged_in()) {
    log_audit_action($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
}

// Perform session cleanup and redirect to login
logout_user();
