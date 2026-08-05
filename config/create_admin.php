<?php
/**
 * CLI utility to seed or update the admin account.
 * For security reasons, this script can ONLY be executed via CLI.
 */

if (PHP_SAPI !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    exit("This script can only be run from the command line.");
}

require_once __DIR__ . '/db.php';

// Retrieve arguments or set defaults
$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';
$role = 'admin';

echo "=== Admin Account Seeding Utility ===\n";
echo "Username: {$username}\n";
echo "Password: " . ($argv[2] ?? 'admin123') . "\n";

try {
    $db = getDBConnection();
    
    // Hash password using the secure default (currently bcrypt)
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if user already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update existing user
        $updateStmt = $db->prepare("UPDATE users SET password_hash = ?, role = ? WHERE username = ?");
        $updateStmt->execute([$passwordHash, $role, $username]);
        echo "SUCCESS: Admin user '{$username}' already exists. Password has been updated.\n";
    } else {
        // Insert new user
        $insertStmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $insertStmt->execute([$username, $passwordHash, $role]);
        echo "SUCCESS: Admin user '{$username}' has been created.\n";
    }
    
    if ($username === 'admin' && $password === 'admin123') {
        echo "WARNING: You are using the default credentials ('admin' / 'admin123'). Make sure to update this!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
