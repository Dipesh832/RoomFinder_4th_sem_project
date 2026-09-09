<?php

require_once __DIR__ . "/config/config.php";

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
        $allowedSections = ['owner', 'tenant', 'admin'];
        $parts = explode('/', $uri);
        if (count($parts) === 2 && in_array($parts[0], $allowedSections, true)) {
            $file = __DIR__ . '/' . $parts[0] . '/' . $parts[1] . '.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                http_response_code(404);
                echo "404 - Page Not Found";
            }
        } else {
            http_response_code(404);
            echo "404 - Page Not Found";
        }
        break;
}