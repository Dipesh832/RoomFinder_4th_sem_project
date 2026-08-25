<?php

require_once __DIR__ . "/../config/config.php";

$uri = trim($_GET['uri'] ?? '', '/');

switch ($uri) {

    case 'auth/account-type':
        require_once __DIR__ . '/auth/account-type.php';
        break;

    case 'auth/register':
        require_once __DIR__ . '/auth/register.php';
        break;

    case 'auth/login':
        require_once __DIR__ . '/auth/login.php';
        break;

    default:
        http_response_code(404);
        echo "404 - Page Not Found";
        break;
}