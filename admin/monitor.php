<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'monitor';

// ─── Overview Statistics ──────────────────────────────────────
$totalUsers = $totalRooms = $totalBookings = $totalMessages = 0;

$stmt = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($stmt) {
    $totalUsers = (int) $stmt->fetch_assoc()['total'];
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS total FROM rooms");
if ($stmt) {
    $totalRooms = (int) $stmt->fetch_assoc()['total'];
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS total FROM bookings");
if ($stmt) {
    $totalBookings = (int) $stmt->fetch_assoc()['total'];
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS total FROM messages");
if ($stmt) {
    $totalMessages = (int) $stmt->fetch_assoc()['total'];
    $stmt->close();
}

// ─── Recent Bookings (5) ─────────────────────────────────────
$recentBookings = [];
$stmt = $conn->query("
    SELECT b.id, b.status, b.booking_date, b.created_at,
           rm.title AS room_title,
           t.name AS tenant_name,
           o.name AS owner_name
    FROM bookings b
    INNER JOIN rooms rm ON b.room_id = rm.id
    INNER JOIN users t ON b.tenant_id = t.id
    INNER JOIN users o ON rm.owner_id = o.id
    ORDER BY b.created_at DESC LIMIT 5
");
if ($stmt) {
    $recentBookings = $stmt->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Recent Activity (combined, top 6) ───────────────────────
$activity = [];

$stmt = $conn->query("SELECT 'user' AS type, name AS title, created_at FROM users ORDER BY created_at DESC LIMIT 3");
if ($stmt) {
    $activity = array_merge($activity, $stmt->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

$stmt = $conn->query("SELECT 'room' AS type, title, created_at FROM rooms ORDER BY created_at DESC LIMIT 3");
if ($stmt) {
    $activity = array_merge($activity, $stmt->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

$stmt = $conn->query("SELECT 'booking' AS type, CONCAT('Booking #', id) AS title, created_at FROM bookings ORDER BY created_at DESC LIMIT 3");
if ($stmt) {
    $activity = array_merge($activity, $stmt->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

usort($activity, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$activity = array_slice($activity, 0, 6);

// ─── Recent Users (5) ────────────────────────────────────────
$recentUsers = [];
$stmt = $conn->query("SELECT id, name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
if ($stmt) {
    $recentUsers = $stmt->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Recent Rooms (5) ────────────────────────────────────────
$recentRooms = [];
$stmt = $conn->query("
    SELECT r.id, r.title, r.status,
           u.name AS owner_name
    FROM rooms r
    INNER JOIN users u ON r.owner_id = u.id
    ORDER BY r.created_at DESC LIMIT 5
");
if ($stmt) {
    $recentRooms = $stmt->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor | RoomFinder</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <!-- Page Header -->
            <div class="admin-page-header">
                <h1 class="admin-page-title">System Monitor</h1>
                <p class="admin-page-subtitle">Monitor recent system activity and important platform data.</p>
            </div>

            <!-- Overview Statistics -->
            <div class="monitor-overview">
                <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="monitor-stat">
                    <span class="monitor-stat-number"><?= number_format($totalUsers) ?></span>
                    <span class="monitor-stat-label">Total Users</span>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="monitor-stat">
                    <span class="monitor-stat-number"><?= number_format($totalRooms) ?></span>
                    <span class="monitor-stat-label">Total Rooms</span>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="monitor-stat">
                    <span class="monitor-stat-number"><?= number_format($totalBookings) ?></span>
                    <span class="monitor-stat-label">Total Bookings</span>
                </a>
                <div class="monitor-stat">
                    <span class="monitor-stat-number"><?= number_format($totalMessages) ?></span>
                    <span class="monitor-stat-label">Total Messages</span>
                </div>
            </div>

            <!-- Two-Column Layout -->
            <div class="monitor-grid">

                <!-- Main Column -->
                <div class="monitor-main-col">

                    <!-- Recent Bookings -->
                    <div class="monitor-card">
                        <div class="monitor-card-header">
                            <h2 class="monitor-card-title">Recent Bookings</h2>
                            <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="admin-btn">View All</a>
                        </div>
                        <?php if (empty($recentBookings)): ?>
                            <div class="monitor-empty">No bookings yet.</div>
                        <?php else: ?>
                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Tenant</th>
                                            <th>Owner</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBookings as $b): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($b['room_title']) ?></strong></td>
                                                <td><?= htmlspecialchars($b['tenant_name']) ?></td>
                                                <td><?= htmlspecialchars($b['owner_name']) ?></td>
                                                <td>
                                                    <span class="admin-badge admin-badge-<?= $b['status'] ?>">
                                                        <?= htmlspecialchars(ucfirst($b['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j', strtotime($b['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Activity -->
                    <div class="monitor-card">
                        <div class="monitor-card-header">
                            <h2 class="monitor-card-title">Recent Activity</h2>
                        </div>
                        <?php if (empty($activity)): ?>
                            <div class="monitor-empty">No recent activity.</div>
                        <?php else: ?>
                            <div class="monitor-timeline">
                                <?php foreach ($activity as $a): ?>
                                    <div class="monitor-timeline-item">
                                        <span class="monitor-timeline-dot monitor-timeline-dot-<?= $a['type'] ?>"></span>
                                        <div class="monitor-timeline-content">
                                            <span class="monitor-timeline-text">
                                                <?php if ($a['type'] === 'user'): ?>
                                                    New user registered: <strong><?= htmlspecialchars($a['title']) ?></strong>
                                                <?php elseif ($a['type'] === 'room'): ?>
                                                    New room listed: <strong><?= htmlspecialchars($a['title']) ?></strong>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($a['title']) ?> created
                                                <?php endif; ?>
                                            </span>
                                            <span class="monitor-timeline-time"><?= date('M j, g:i A', strtotime($a['created_at'])) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Sidebar Column -->
                <div class="monitor-side-col">

                    <!-- Recent Users -->
                    <div class="monitor-card">
                        <div class="monitor-card-header">
                            <h2 class="monitor-card-title">Recent Users</h2>
                            <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="admin-btn">View All</a>
                        </div>
                        <?php if (empty($recentUsers)): ?>
                            <div class="monitor-empty">No users yet.</div>
                        <?php else: ?>
                            <div class="monitor-list">
                                <?php foreach ($recentUsers as $u): ?>
                                    <div class="monitor-list-item">
                                        <div class="monitor-list-info">
                                            <span class="monitor-list-name"><?= htmlspecialchars($u['name']) ?></span>
                                            <span class="monitor-list-meta"><?= date('M j, Y', strtotime($u['created_at'])) ?></span>
                                        </div>
                                        <span class="admin-badge admin-badge-<?= $u['role'] === 'owner' ? 'owner' : ($u['role'] === 'admin' ? 'role' : 'tenant') ?>">
                                            <?= htmlspecialchars(ucfirst($u['role'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Rooms -->
                    <div class="monitor-card">
                        <div class="monitor-card-header">
                            <h2 class="monitor-card-title">Recent Rooms</h2>
                            <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="admin-btn">View All</a>
                        </div>
                        <?php if (empty($recentRooms)): ?>
                            <div class="monitor-empty">No rooms yet.</div>
                        <?php else: ?>
                            <div class="monitor-list">
                                <?php foreach ($recentRooms as $r): ?>
                                    <div class="monitor-list-item">
                                        <div class="monitor-list-info">
                                            <span class="monitor-list-name"><?= htmlspecialchars($r['title']) ?></span>
                                            <span class="monitor-list-meta"><?= htmlspecialchars($r['owner_name']) ?></span>
                                        </div>
                                        <span class="admin-badge <?= $r['status'] === 'available' ? 'admin-badge-available' : 'admin-badge-booked' ?>">
                                            <?= htmlspecialchars(ucfirst($r['status'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="monitor-card">
                        <div class="monitor-card-header">
                            <h2 class="monitor-card-title">Quick Actions</h2>
                        </div>
                        <div class="monitor-quick-actions">
                            <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="monitor-quick-link">
                                Manage Users
                                <span class="monitor-quick-arrow">&rsaquo;</span>
                            </a>
                            <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="monitor-quick-link">
                                Manage Rooms
                                <span class="monitor-quick-arrow">&rsaquo;</span>
                            </a>
                            <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="monitor-quick-link">
                                View Bookings
                                <span class="monitor-quick-arrow">&rsaquo;</span>
                            </a>
                        </div>
                    </div>

                </div>

            </div>

        </main>

    </div>

</body>

</html>
