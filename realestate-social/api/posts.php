<?php
// Posts management API endpoints

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
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                handleGetPosts();
                break;
            case 'detail':
                handleGetPostDetail();
                break;
            case 'my':
                handleGetMyPosts();
                break;
            default:
                errorResponse('Invalid action');
        }
        break;
    
    case 'POST':
        handleCreatePost($input);
        break;
    
    case 'PUT':
        handleUpdatePost($input);
        break;
    
    case 'DELETE':
        handleDeletePost();
        break;
    
    default:
        errorResponse('Method not allowed', 405);
}

function handleGetPosts() {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $filters = [];
    $params = [];
    
    // Build filters
    if (!empty($_GET['city'])) {
        $filters[] = "p.city LIKE ?";
        $params[] = '%' . $_GET['city'] . '%';
    }
    
    if (!empty($_GET['property_type'])) {
        $filters[] = "p.property_type = ?";
        $params[] = $_GET['property_type'];
    }
    
    if (!empty($_GET['min_price'])) {
        $filters[] = "p.price >= ?";
        $params[] = floatval($_GET['min_price']);
    }
    
    if (!empty($_GET['max_price'])) {
        $filters[] = "p.price <= ?";
        $params[] = floatval($_GET['max_price']);
    }
    
    if (!empty($_GET['bedrooms'])) {
        $filters[] = "p.bedrooms >= ?";
        $params[] = intval($_GET['bedrooms']);
    }
    
    $whereClause = "p.status = 'active'";
    if (!empty($filters)) {
        $whereClause .= " AND " . implode(" AND ", $filters);
    }
    
    $db = getDB();
    
    // Get total count
    $totalQuery = "SELECT COUNT(*) as total FROM posts p WHERE $whereClause";
    $total = $db->fetch($totalQuery, $params)['total'];
    
    // Get posts with user info and primary image
    $postsQuery = "
        SELECT 
            p.*,
            u.username,
            u.first_name,
            u.last_name,
            pi.filename as primary_image
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN post_images pi ON p.id = pi.post_id AND pi.is_primary = 1
        WHERE $whereClause
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $posts = $db->fetchAll($postsQuery, $params);
    
    successResponse('Posts retrieved', [
        'posts' => $posts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetPostDetail() {
    $postId = $_GET['id'] ?? null;
    if (!$postId) {
        errorResponse('Post ID is required');
    }
    
    $db = getDB();
    
    // Get post with user info
    $post = $db->fetch("
        SELECT 
            p.*,
            u.username,
            u.first_name,
            u.last_name,
            u.phone,
            u.email
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.status = 'active'
    ", [$postId]);
    
    if (!$post) {
        notFoundResponse('Post not found');
    }
    
    // Get all images for this post
    $images = $db->fetchAll("
        SELECT filename, original_name, is_primary
        FROM post_images
        WHERE post_id = ?
        ORDER BY is_primary DESC, id ASC
    ", [$postId]);
    
    $post['images'] = $images;
    
    successResponse('Post detail retrieved', ['post' => $post]);
}

function handleGetMyPosts() {
    requireApiAuth();
    
    $currentUser = getCurrentUser();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    // Get total count
    $total = $db->fetch(
        "SELECT COUNT(*) as total FROM posts WHERE user_id = ?",
        [$currentUser['id']]
    )['total'];
    
    // Get user's posts
    $posts = $db->fetchAll("
        SELECT 
            p.*,
            pi.filename as primary_image
        FROM posts p
        LEFT JOIN post_images pi ON p.id = pi.post_id AND pi.is_primary = 1
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ", [$currentUser['id'], $limit, $offset]);
    
    successResponse('My posts retrieved', [
        'posts' => $posts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleCreatePost($input) {
    requireApiAuth();
    
    if (!canCreatePosts()) {
        forbiddenResponse('Only approved sellers can create posts');
    }
    
    $errors = [];
    
    // Validation
    if (!$input || !isset($input['title']) || empty($input['title'])) {
        $errors['title'] = 'Title is required';
    }
    
    if (!isset($input['description']) || empty($input['description'])) {
        $errors['description'] = 'Description is required';
    }
    
    if (!isset($input['price']) || !is_numeric($input['price']) || $input['price'] <= 0) {
        $errors['price'] = 'Valid price is required';
    }
    
    if (!isset($input['property_type']) || !in_array($input['property_type'], ['house', 'apartment', 'condo', 'land', 'commercial'])) {
        $errors['property_type'] = 'Valid property type is required';
    }
    
    if (!empty($errors)) {
        validationError($errors);
    }
    
    $currentUser = getCurrentUser();
    $db = getDB();
    
    // Create post
    $db->query("
        INSERT INTO posts (
            user_id, title, description, price, property_type, 
            bedrooms, bathrooms, area_sqm, address, city, state, country,
            latitude, longitude
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $currentUser['id'],
        $input['title'],
        $input['description'],
        $input['price'],
        $input['property_type'],
        $input['bedrooms'] ?? null,
        $input['bathrooms'] ?? null,
        $input['area_sqm'] ?? null,
        $input['address'] ?? null,
        $input['city'] ?? null,
        $input['state'] ?? null,
        $input['country'] ?? null,
        $input['latitude'] ?? null,
        $input['longitude'] ?? null
    ]);
    
    $postId = $db->lastInsertId();
    
    successResponse('Post created successfully', ['post_id' => $postId]);
}

function handleUpdatePost($input) {
    requireApiAuth();
    
    $postId = $_GET['id'] ?? null;
    if (!$postId) {
        errorResponse('Post ID is required');
    }
    
    $db = getDB();
    $post = $db->fetch("SELECT * FROM posts WHERE id = ?", [$postId]);
    
    if (!$post) {
        notFoundResponse('Post not found');
    }
    
    if (!canEditPost($post)) {
        forbiddenResponse('Cannot edit this post');
    }
    
    if (!$input) {
        errorResponse('Post data is required');
    }
    
    $allowedFields = [
        'title', 'description', 'price', 'property_type',
        'bedrooms', 'bathrooms', 'area_sqm', 'address',
        'city', 'state', 'country', 'latitude', 'longitude', 'status'
    ];
    
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
    
    $params[] = $postId;
    
    $db->query(
        "UPDATE posts SET " . implode(', ', $updates) . " WHERE id = ?",
        $params
    );
    
    successResponse('Post updated successfully');
}

function handleDeletePost() {
    requireApiAuth();
    
    $postId = $_GET['id'] ?? null;
    if (!$postId) {
        errorResponse('Post ID is required');
    }
    
    $db = getDB();
    $post = $db->fetch("SELECT * FROM posts WHERE id = ?", [$postId]);
    
    if (!$post) {
        notFoundResponse('Post not found');
    }
    
    if (!canDeletePost($post)) {
        forbiddenResponse('Cannot delete this post');
    }
    
    // Delete post (images will be deleted by CASCADE)
    $db->query("DELETE FROM posts WHERE id = ?", [$postId]);
    
    successResponse('Post deleted successfully');
}
?>
