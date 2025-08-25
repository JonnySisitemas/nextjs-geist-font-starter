<?php
// Authentication API endpoints

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once '../core/db.php';
require_once '../core/response.php';
require_once '../core/session.php';
require_once '../core/guard.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'login':
                handleLogin($input);
                break;
            case 'register':
                handleRegister($input);
                break;
            case 'logout':
                handleLogout();
                break;
            default:
                errorResponse('Invalid action');
        }
        break;
    
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'me':
                handleGetCurrentUser();
                break;
            default:
                errorResponse('Invalid action');
        }
        break;
    
    default:
        errorResponse('Method not allowed', 405);
}

function handleLogin($input) {
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        errorResponse('Username and password are required');
    }
    
    $db = getDB();
    $user = $db->fetch(
        "SELECT * FROM users WHERE username = ? OR email = ?",
        [$input['username'], $input['username']]
    );
    
    if (!$user || !password_verify($input['password'], $user['password_hash'])) {
        errorResponse('Invalid credentials', 401);
    }
    
    if ($user['status'] === 'banned') {
        errorResponse('Account has been banned', 403);
    }
    
    login($user);
    
    successResponse('Login successful', [
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ]
    ]);
}

function handleRegister($input) {
    $errors = [];
    
    // Validation
    if (!$input || !isset($input['username']) || empty($input['username'])) {
        $errors['username'] = 'Username is required';
    }
    
    if (!isset($input['email']) || empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required';
    }
    
    if (!isset($input['password']) || strlen($input['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    if (!isset($input['role']) || !in_array($input['role'], ['seller', 'buyer'])) {
        $errors['role'] = 'Role must be either seller or buyer';
    }
    
    if (!empty($errors)) {
        validationError($errors);
    }
    
    $db = getDB();
    
    // Check if username or email already exists
    $existing = $db->fetch(
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$input['username'], $input['email']]
    );
    
    if ($existing) {
        errorResponse('Username or email already exists');
    }
    
    // Create user
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $db->query(
        "INSERT INTO users (username, email, password_hash, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $input['username'],
            $input['email'],
            $passwordHash,
            $input['role'],
            $input['first_name'] ?? null,
            $input['last_name'] ?? null,
            $input['phone'] ?? null
        ]
    );
    
    successResponse('Registration successful. Please wait for approval.');
}

function handleLogout() {
    logout();
    successResponse('Logged out successfully');
}

function handleGetCurrentUser() {
    if (!isLoggedIn()) {
        unauthorizedResponse();
    }
    
    $user = getCurrentUser();
    if (!$user) {
        unauthorizedResponse();
    }
    
    successResponse('User data retrieved', [
        'user' => $user
    ]);
}
?>
