<?php
// Main router for the real estate social network
session_start();

// Include core files
require_once '../core/db.php';
require_once '../core/response.php';
require_once '../core/session.php';
require_once '../core/guard.php';

// Simple router
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/realestate-social/public', '', $path);

// Remove query parameters
$path = strtok($path, '?');

// Route handling
switch ($path) {
    case '/':
    case '/index.php':
        if (isLoggedIn()) {
            include '../views/feed.html';
        } else {
            include '../views/login.html';
        }
        break;
    
    case '/login':
        include '../views/login.html';
        break;
    
    case '/register':
        include '../views/register.html';
        break;
    
    case '/feed':
        requireAuth();
        include '../views/feed.html';
        break;
    
    case '/post':
        requireAuth();
        include '../views/post_form.html';
        break;
    
    case '/admin':
        requireRole(['superuser', 'admin']);
        include '../views/admin_dashboard.html';
        break;
    
    default:
        http_response_code(404);
        echo "Page not found";
        break;
}
?>
