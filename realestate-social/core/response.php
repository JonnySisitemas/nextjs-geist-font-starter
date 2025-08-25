<?php
// Response helper functions

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function successResponse($message, $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

function errorResponse($message, $status = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $status);
}

function validationError($errors) {
    jsonResponse([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $errors
    ], 422);
}

function unauthorizedResponse($message = 'Unauthorized') {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], 401);
}

function forbiddenResponse($message = 'Forbidden') {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], 403);
}

function notFoundResponse($message = 'Not found') {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], 404);
}
?>
