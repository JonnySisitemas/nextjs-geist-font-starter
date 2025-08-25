<?php
// Messages API endpoints

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
                handleGetMessages();
                break;
            case 'conversation':
                handleGetConversation();
                break;
            case 'unread':
                handleGetUnreadCount();
                break;
            default:
                errorResponse('Invalid action');
        }
        break;
    
    case 'POST':
        handleSendMessage($input);
        break;
    
    case 'PUT':
        handleMarkAsRead();
        break;
    
    case 'DELETE':
        handleDeleteMessage();
        break;
    
    default:
        errorResponse('Method not allowed', 405);
}

function handleGetMessages() {
    requireApiAuth();
    
    if (!canSendMessages()) {
        forbiddenResponse('Only approved buyers and sellers can access messages');
    }
    
    $currentUser = getCurrentUser();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    // Get conversations (latest message from each conversation)
    $conversations = $db->fetchAll("
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_id = ? THEN receiver.username 
                ELSE sender.username 
            END as other_user,
            CASE 
                WHEN m.sender_id = ? THEN receiver.first_name 
                ELSE sender.first_name 
            END as other_first_name,
            CASE 
                WHEN m.sender_id = ? THEN receiver.last_name 
                ELSE sender.last_name 
            END as other_last_name,
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            p.title as post_title
        FROM messages m
        INNER JOIN (
            SELECT 
                LEAST(sender_id, receiver_id) as user1,
                GREATEST(sender_id, receiver_id) as user2,
                MAX(created_at) as latest_message
            FROM messages 
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY user1, user2
        ) latest ON (
            (LEAST(m.sender_id, m.receiver_id) = latest.user1 AND 
             GREATEST(m.sender_id, m.receiver_id) = latest.user2 AND 
             m.created_at = latest.latest_message)
        )
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        LEFT JOIN posts p ON m.post_id = p.id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ", [
        $currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id'],
        $currentUser['id'], $currentUser['id'],
        $currentUser['id'], $currentUser['id'],
        $limit, $offset
    ]);
    
    successResponse('Conversations retrieved', ['conversations' => $conversations]);
}

function handleGetConversation() {
    requireApiAuth();
    
    if (!canSendMessages()) {
        forbiddenResponse('Only approved buyers and sellers can access messages');
    }
    
    $otherUserId = $_GET['user_id'] ?? null;
    if (!$otherUserId) {
        errorResponse('User ID is required');
    }
    
    $currentUser = getCurrentUser();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $db = getDB();
    
    // Get messages between current user and other user
    $messages = $db->fetchAll("
        SELECT 
            m.*,
            sender.username as sender_username,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            p.title as post_title
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        LEFT JOIN posts p ON m.post_id = p.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ", [
        $currentUser['id'], $otherUserId,
        $otherUserId, $currentUser['id'],
        $limit, $offset
    ]);
    
    // Mark messages as read
    $db->query("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ", [$otherUserId, $currentUser['id']]);
    
    successResponse('Conversation retrieved', ['messages' => array_reverse($messages)]);
}

function handleGetUnreadCount() {
    requireApiAuth();
    
    if (!canSendMessages()) {
        forbiddenResponse('Only approved buyers and sellers can access messages');
    }
    
    $currentUser = getCurrentUser();
    $db = getDB();
    
    $count = $db->fetch("
        SELECT COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0
    ", [$currentUser['id']])['unread_count'];
    
    successResponse('Unread count retrieved', ['unread_count' => $count]);
}

function handleSendMessage($input) {
    requireApiAuth();
    
    if (!canSendMessages()) {
        forbiddenResponse('Only approved buyers and sellers can send messages');
    }
    
    $errors = [];
    
    // Validation
    if (!$input || !isset($input['receiver_id']) || empty($input['receiver_id'])) {
        $errors['receiver_id'] = 'Receiver ID is required';
    }
    
    if (!isset($input['message']) || empty(trim($input['message']))) {
        $errors['message'] = 'Message content is required';
    }
    
    if (!empty($errors)) {
        validationError($errors);
    }
    
    $currentUser = getCurrentUser();
    
    // Cannot send message to yourself
    if ($input['receiver_id'] == $currentUser['id']) {
        errorResponse('Cannot send message to yourself');
    }
    
    $db = getDB();
    
    // Verify receiver exists and is approved
    $receiver = $db->fetch(
        "SELECT id, role, status FROM users WHERE id = ?",
        [$input['receiver_id']]
    );
    
    if (!$receiver) {
        notFoundResponse('Receiver not found');
    }
    
    if ($receiver['status'] !== 'approved') {
        errorResponse('Cannot send message to non-approved user');
    }
    
    // Verify post exists if post_id is provided
    $postId = $input['post_id'] ?? null;
    if ($postId) {
        $post = $db->fetch("SELECT id FROM posts WHERE id = ? AND status = 'active'", [$postId]);
        if (!$post) {
            errorResponse('Post not found or inactive');
        }
    }
    
    // Send message
    $db->query("
        INSERT INTO messages (sender_id, receiver_id, post_id, subject, message)
        VALUES (?, ?, ?, ?, ?)
    ", [
        $currentUser['id'],
        $input['receiver_id'],
        $postId,
        $input['subject'] ?? null,
        trim($input['message'])
    ]);
    
    $messageId = $db->lastInsertId();
    
    successResponse('Message sent successfully', ['message_id' => $messageId]);
}

function handleMarkAsRead() {
    requireApiAuth();
    
    $messageId = $_GET['id'] ?? null;
    if (!$messageId) {
        errorResponse('Message ID is required');
    }
    
    $currentUser = getCurrentUser();
    $db = getDB();
    
    // Mark message as read (only if current user is the receiver)
    $result = $db->query("
        UPDATE messages 
        SET is_read = 1 
        WHERE id = ? AND receiver_id = ?
    ", [$messageId, $currentUser['id']]);
    
    if ($result->rowCount() === 0) {
        errorResponse('Message not found or not authorized');
    }
    
    successResponse('Message marked as read');
}

function handleDeleteMessage() {
    requireApiAuth();
    
    $messageId = $_GET['id'] ?? null;
    if (!$messageId) {
        errorResponse('Message ID is required');
    }
    
    $currentUser = getCurrentUser();
    $db = getDB();
    
    // Get message to check ownership
    $message = $db->fetch("SELECT * FROM messages WHERE id = ?", [$messageId]);
    
    if (!$message) {
        notFoundResponse('Message not found');
    }
    
    // Only sender can delete their own messages, or admins can delete any
    if ($message['sender_id'] != $currentUser['id'] && !hasRole(['superuser', 'admin'])) {
        forbiddenResponse('Cannot delete this message');
    }
    
    $db->query("DELETE FROM messages WHERE id = ?", [$messageId]);
    
    successResponse('Message deleted successfully');
}
?>
