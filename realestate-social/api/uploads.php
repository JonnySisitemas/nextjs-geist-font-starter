<?php
// Image upload API endpoints

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
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

switch ($method) {
    case 'POST':
        handleImageUpload();
        break;
    
    case 'DELETE':
        handleImageDelete();
        break;
    
    default:
        errorResponse('Method not allowed', 405);
}

function handleImageUpload() {
    requireApiAuth();
    
    if (!canCreatePosts()) {
        forbiddenResponse('Only approved sellers can upload images');
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        errorResponse('No image uploaded or upload error');
    }
    
    $postId = $_POST['post_id'] ?? null;
    if (!$postId) {
        errorResponse('Post ID is required');
    }
    
    // Verify post ownership
    $db = getDB();
    $post = $db->fetch("SELECT user_id FROM posts WHERE id = ?", [$postId]);
    
    if (!$post) {
        notFoundResponse('Post not found');
    }
    
    $currentUser = getCurrentUser();
    if (!canEditPost($post)) {
        forbiddenResponse('Cannot upload images to this post');
    }
    
    $config = require '../config/config.php';
    $file = $_FILES['image'];
    
    // Validate file
    if ($file['size'] > $config['max_file_size']) {
        errorResponse('File too large. Maximum size is ' . ($config['max_file_size'] / 1024 / 1024) . 'MB');
    }
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    if (!in_array($extension, $config['allowed_extensions'])) {
        errorResponse('Invalid file type. Allowed: ' . implode(', ', $config['allowed_extensions']));
    }
    
    // Validate image
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        errorResponse('Invalid image file');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = $config['upload_path'];
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        errorResponse('Failed to save image');
    }
    
    // Check if this should be the primary image
    $isPrimary = $_POST['is_primary'] ?? false;
    
    // If setting as primary, remove primary flag from other images
    if ($isPrimary) {
        $db->query(
            "UPDATE post_images SET is_primary = 0 WHERE post_id = ?",
            [$postId]
        );
    } else {
        // If no primary image exists, make this one primary
        $existingPrimary = $db->fetch(
            "SELECT id FROM post_images WHERE post_id = ? AND is_primary = 1",
            [$postId]
        );
        
        if (!$existingPrimary) {
            $isPrimary = true;
        }
    }
    
    // Save image info to database
    $db->query("
        INSERT INTO post_images (post_id, filename, original_name, file_size, is_primary)
        VALUES (?, ?, ?, ?, ?)
    ", [
        $postId,
        $filename,
        $file['name'],
        $file['size'],
        $isPrimary ? 1 : 0
    ]);
    
    $imageId = $db->lastInsertId();
    
    successResponse('Image uploaded successfully', [
        'image_id' => $imageId,
        'filename' => $filename,
        'url' => '/uploads/' . $filename,
        'is_primary' => $isPrimary
    ]);
}

function handleImageDelete() {
    requireApiAuth();
    
    $imageId = $_GET['id'] ?? null;
    if (!$imageId) {
        errorResponse('Image ID is required');
    }
    
    $db = getDB();
    
    // Get image info with post ownership
    $image = $db->fetch("
        SELECT pi.*, p.user_id
        FROM post_images pi
        JOIN posts p ON pi.post_id = p.id
        WHERE pi.id = ?
    ", [$imageId]);
    
    if (!$image) {
        notFoundResponse('Image not found');
    }
    
    // Check permissions
    if (!canEditPost(['user_id' => $image['user_id']])) {
        forbiddenResponse('Cannot delete this image');
    }
    
    $config = require '../config/config.php';
    $filepath = $config['upload_path'] . $image['filename'];
    
    // Delete file from filesystem
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Delete from database
    $db->query("DELETE FROM post_images WHERE id = ?", [$imageId]);
    
    // If this was the primary image, set another image as primary
    if ($image['is_primary']) {
        $db->query("
            UPDATE post_images 
            SET is_primary = 1 
            WHERE post_id = ? 
            ORDER BY id ASC 
            LIMIT 1
        ", [$image['post_id']]);
    }
    
    successResponse('Image deleted successfully');
}

// Helper function to serve images (optional, can be handled by web server)
function serveImage($filename) {
    $config = require '../config/config.php';
    $filepath = $config['upload_path'] . $filename;
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        exit;
    }
    
    $mimeType = mime_content_type($filepath);
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// If accessing image directly
if (isset($_GET['serve']) && isset($_GET['filename'])) {
    serveImage($_GET['filename']);
}
?>
