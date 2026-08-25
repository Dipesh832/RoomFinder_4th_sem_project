<?php

function loginFromRememberToken($conn)
{
    // User is already logged in
    if (isset($_SESSION['user'])) {
        return;
    }

    // No remember-me cookie
    if (!isset($_COOKIE['remember_token'])) {
        return;
    }

    $token = $_COOKIE['remember_token'];

    // Hash the token from the browser
    $tokenHash = hash('sha256', $token);

    $sql = "SELECT *
            FROM users
            WHERE remember_token = ?
            AND remember_expires_at > NOW()
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "s", $tokenHash);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $user = mysqli_fetch_assoc($result);

    // Invalid or expired token
    if (!$user) {
        setcookie(
            'remember_token',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/roomfinder'
            ]
        );

        return;
    }

    // Restore the PHP session
    $_SESSION['user'] = $user;
}