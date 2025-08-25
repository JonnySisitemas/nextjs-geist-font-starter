<?php
// Authorization and access control functions

function requireAuth() {
    if (!checkSessionTimeout()) {
        header('Location: /login');
        exit;
    }
    
    if (!isLoggedIn()) {
        header('Location: /login');
        exit;
    }
    
    if (!isApproved()) {
        header('Location: /pending');
        exit;
    }
}

function requireRole($roles) {
    requireAuth();
    
    if (!hasRole($roles)) {
        http_response_code(403);
        echo "Access denied. Insufficient permissions.";
        exit;
    }
}

function requireApiAuth() {
    if (!checkSessionTimeout()) {
        unauthorizedResponse('Session expired');
    }
    
    if (!isLoggedIn()) {
        unauthorizedResponse('Authentication required');
    }
    
    if (!isApproved()) {
        forbiddenResponse('Account not approved');
    }
}

function requireApiRole($roles) {
    requireApiAuth();
    
    if (!hasRole($roles)) {
        forbiddenResponse('Insufficient permissions');
    }
}

function canEditPost($post) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    // Superuser and admin can edit any post
    if (hasRole(['superuser', 'admin'])) {
        return true;
    }
    
    // Users can edit their own posts
    return $post['user_id'] == $user['id'];
}

function canDeletePost($post) {
    return canEditPost($post);
}

function canManageUsers() {
    return hasRole(['superuser', 'admin']);
}

function canApproveUsers() {
    return hasRole(['superuser']);
}

function canBanUsers() {
    return hasRole(['superuser', 'admin']);
}

function canCreatePosts() {
    return hasRole(['seller']) && isApproved();
}

function canSendMessages() {
    return hasRole(['buyer', 'seller']) && isApproved();
}
?>
