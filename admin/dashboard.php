<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'dashboard';

$totalUsers = 0;
$totalRooms = 0;
$totalOwners = 0;
$totalTenants = 0;

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM users");
if ($stmt) {
    $totalUsers = (int) $stmt->fetch_assoc()['cnt'];
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM rooms");
if ($stmt) {
    $totalRooms = (int) $stmt->fetch_assoc()['cnt'];
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'owner'");
if ($stmt) {
    $totalOwners = (int) $stmt->fetch_assoc()['cnt'];
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'tenant'");
if ($stmt) {
    $totalTenants = (int) $stmt->fetch_assoc()['cnt'];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | RoomFinder</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-page-header">
                <h1 class="admin-page-title">Admin Dashboard</h1>
                <p class="admin-page-subtitle">Overview of your RoomFinder platform.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px;">
                <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-decoration: none; transition: box-shadow 0.15s ease;">
                    <div style="color: #10b981; font-size: 32px; font-weight: 800;"><?= $totalUsers ?></div>
                    <div style="color: #64748b; font-size: 15px; font-weight: 500; margin-top: 4px;">Total Users</div>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-decoration: none; transition: box-shadow 0.15s ease;">
                    <div style="color: #10b981; font-size: 32px; font-weight: 800;"><?= $totalRooms ?></div>
                    <div style="color: #64748b; font-size: 15px; font-weight: 500; margin-top: 4px;">Total Rooms</div>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=owner') ?>" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-decoration: none; transition: box-shadow 0.15s ease;">
                    <div style="color: #b45309; font-size: 32px; font-weight: 800;"><?= $totalOwners ?></div>
                    <div style="color: #64748b; font-size: 15px; font-weight: 500; margin-top: 4px;">Owners</div>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=tenant') ?>" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-decoration: none; transition: box-shadow 0.15s ease;">
                    <div style="color: #059669; font-size: 32px; font-weight: 800;"><?= $totalTenants ?></div>
                    <div style="color: #64748b; font-size: 15px; font-weight: 500; margin-top: 4px;">Tenants</div>
                </a>
            </div>

        </main>

    </div>

</body>

</html>
