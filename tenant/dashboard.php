<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';
require_once __DIR__ . '/../includes/room_search_prepare.php';

$userName = $_SESSION['user']['name'] ?? 'Tenant';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tenant Dashboard | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/tenant.css">
    <link rel="stylesheet" href="../assets/css/footer.css">

</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="tenant-dashboard">

        <?php include __DIR__ . '/../includes/hero_search.php'; ?>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>

</html>
