<?php
// Session management functions

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    return $db->fetch(
        "SELECT id, username, email, role, status, created_at FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

function login($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['status'] = $user['status'];
    $_SESSION['login_time'] = time();
}

function logout() {
    session_unset();
    session_destroy();
}

function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

function isApproved() {
    return isLoggedIn() && $_SESSION['status'] === 'approved';
}

function checkSessionTimeout() {
    $config = require '../config/config.php';
    
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $config['session_lifetime']) {
            logout();
            return false;
        }
    }
    
    return true;
}
?>
