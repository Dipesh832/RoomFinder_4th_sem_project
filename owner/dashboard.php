<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';
?>
<?php

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - RoomFinder</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <h1>Owner Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?></p>
</body>

</html>