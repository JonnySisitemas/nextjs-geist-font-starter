<?php
// Configuration file for the real estate social network

return [
    // Database configuration
    'db_host' => 'localhost',
    'db_name' => 'realestate_social',
    'db_user' => 'root',
    'db_pass' => '',
    
    // Application settings
    'app_name' => 'Real Estate Social Network',
    'app_url' => 'http://localhost:8000',
    'upload_path' => '../uploads/',
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif'],
    
    // Security settings
    'session_lifetime' => 3600, // 1 hour
    'password_min_length' => 6,
    
    // User roles
    'roles' => [
        'superuser' => 'superuser',
        'admin' => 'admin',
        'seller' => 'seller',
        'buyer' => 'buyer'
    ],
    
    // User states
    'user_states' => [
        'pending' => 'pending',
        'approved' => 'approved',
        'banned' => 'banned'
    ]
];
?>
