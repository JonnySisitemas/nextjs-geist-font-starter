<?php
// Users management API endpoints

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
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
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'pending':
                handleGetPendingUsers();
                break;
            case 'all':
                handleGetAllUsers();
                break;
            case 'profile':
                handleGetUserProfile();
                break;
            default:
                errorResponse('Invalid action');
        }
        break;
    
    case 'POST':
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'approve':
                handleApproveUser($input);
                break;
            case 'reject':
                handleRejectUser($input);
                break;
            case 'ban':
                handleBanUser($input);
                break;
            case 'unban':
                handleUnbanUser($input);
                break;
            case 'promote':
                handlePromoteUser($input);
                break;
            default:
                errorResponse('Invalid action');
        }
        break;
    
    case 'PUT':
        handleUpdateProfile($input);
        break;
    
    default:
        errorResponse('Method not allowed', 405);
}

function handleGetPendingUsers() {
    requireApiRole(['superuser']);
    
    $db = getDB();
    $users = $db->fetchAll(
        "SELECT id, username, email, role, first_name, last_name, phone, created_at 
         FROM users WHERE status = 'pending' ORDER BY created_at ASC"
    );
    
    successResponse('Pending users retrieved', ['users' => $users]);
}

function handleGetAllUsers() {
    requireApiRole(['superuser', 'admin']);
    
    $db = getDB();
    $users = $db->fetchAll(
        "SELECT id, username, email, role, status, first_name, last_name, phone, created_at 
         FROM users ORDER BY created_at DESC"
    );
    
    successResponse('All users retrieved', ['users' => $users]);
}

function handleGetUserProfile() {
    requireApiAuth();
    
    $userId = $_GET['id'] ?? null;
    $currentUser = getCurrentUser();
    
    // If no ID provided, return current user's profile
    if (!$userId) {
        $userId = $currentUser['id'];
    }
    
    // Check if user can view this profile
    if ($userId != $currentUser['id'] && !hasRole(['superuser', 'admin'])) {
        forbiddenResponse('Cannot view other user profiles');
    }
    
    $db = getDB();
    $user = $db->fetch(
        "SELECT id, username, email, role, status, first_name, last_name, phone, created_at 
         FROM users WHERE id = ?",
        [$userId]
    );
    
    if (!$user) {
        notFoundResponse('User not found');
    }
    
    successResponse('User profile retrieved', ['user' => $user]);
}

function handleApproveUser($input) {
    requireApiRole(['superuser']);
    
    if (!$input || !isset($input['user_id'])) {
        errorResponse('User ID is required');
    }
    
    $db = getDB();
    $result = $db->query(
        "UPDATE users SET status = 'approved' WHERE id = ? AND status = 'pending'",
        [$input['user_id']]
    );
    
    if ($result->rowCount() === 0) {
        errorResponse('User not found or already processed');
    }
    
    successResponse('User approved successfully');
}

function handleRejectUser($input) {
    requireApiRole(['superuser']);
    
    if (!$input || !isset($input['user_id'])) {
        errorResponse('User ID is required');
    }
    
    $db = getDB();
    $result = $db->query(
        "DELETE FROM users WHERE id = ? AND status = 'pending'",
        [$input['user_id']]
    );
    
    if ($result->rowCount() === 0) {
        errorResponse('User not found or already processed');
    }
    
    successResponse('User rejected and deleted successfully');
}

function handleBanUser($input) {
    requireApiRole(['superuser', 'admin']);
    
    if (!$input || !isset($input['user_id'])) {
        errorResponse('User ID is required');
    }
    
    $currentUser = getCurrentUser();
    if ($input['user_id'] == $currentUser['id']) {
        errorResponse('Cannot ban yourself');
    }
    
    $db = getDB();
    
    // Check if target user exists and is not a superuser
    $targetUser = $db->fetch("SELECT role FROM users WHERE id = ?", [$input['user_id']]);
    if (!$targetUser) {
        notFoundResponse('User not found');
    }
    
    if ($targetUser['role'] === 'superuser') {
        forbiddenResponse('Cannot ban superuser');
    }
    
    $result = $db->query(
        "UPDATE users SET status = 'banned' WHERE id = ?",
        [$input['user_id']]
    );
    
    successResponse('User banned successfully');
}

function handleUnbanUser($input) {
    requireApiRole(['superuser', 'admin']);
    
    if (!$input || !isset($input['user_id'])) {
        errorResponse('User ID is required');
    }
    
    $db = getDB();
    $result = $db->query(
        "UPDATE users SET status = 'approved' WHERE id = ? AND status = 'banned'",
        [$input['user_id']]
    );
    
    if ($result->rowCount() === 0) {
        errorResponse('User not found or not banned');
    }
    
    successResponse('User unbanned successfully');
}

function handlePromoteUser($input) {
    requireApiRole(['superuser']);
    
    if (!$input || !isset($input['user_id']) || !isset($input['role'])) {
        errorResponse('User ID and role are required');
    }
    
    $allowedRoles = ['admin', 'seller', 'buyer'];
    if (!in_array($input['role'], $allowedRoles)) {
        errorResponse('Invalid role');
    }
    
    $currentUser = getCurrentUser();
    if ($input['user_id'] == $currentUser['id']) {
        errorResponse('Cannot change your own role');
    }
    
    $db = getDB();
    $result = $db->query(
        "UPDATE users SET role = ? WHERE id = ?",
        [$input['role'], $input['user_id']]
    );
    
    successResponse('User role updated successfully');
}

function handleUpdateProfile($input) {
    requireApiAuth();
    
    if (!$input) {
        errorResponse('Profile data is required');
    }
    
    $currentUser = getCurrentUser();
    $allowedFields = ['first_name', 'last_name', 'phone'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        errorResponse('No valid fields to update');
    }
    
    $params[] = $currentUser['id'];
    
    $db = getDB();
    $db->query(
        "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
        $params
    );
    
    successResponse('Profile updated successfully');
}
?>
