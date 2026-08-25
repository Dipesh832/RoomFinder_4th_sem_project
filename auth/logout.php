<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user'])) {

    $userId = $_SESSION['user']['id'];

    $sql = "UPDATE users
            SET remember_token = NULL,
                remember_expires_at = NULL
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "i", $userId);

    mysqli_stmt_execute($stmt);
}

/* Delete Remember Me cookie */
setcookie(
    'remember_token',
    '',
    [
        'expires' => time() - 3600,
        'path' => '/roomfinder',
        'httponly' => true,
        'samesite' => 'Lax'
    ]
);

/* Destroy session */
$_SESSION = [];

session_destroy();

redirect('auth/login');