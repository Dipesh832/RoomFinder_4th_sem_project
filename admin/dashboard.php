<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'dashboard';

$pendingBookingsCount = 0;
$approvedBookingsCount = 0;
$rejectedBookingsCount = 0;
$availableRoomsCount = 0;
$totalOwners = 0;
$totalTenants = 0;
$recentPendingBookings = [];

$stmt = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
        COALESCE(SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_count,
        COALESCE(SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_count
    FROM bookings b
");
if ($stmt) {
    $bookingSummary = $stmt->fetch_assoc();
    if ($bookingSummary) {
        $pendingBookingsCount = (int) $bookingSummary['pending_count'];
        $approvedBookingsCount = (int) $bookingSummary['approved_count'];
        $rejectedBookingsCount = (int) $bookingSummary['rejected_count'];
    }
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE status = 'available'");
if ($stmt) {
    $availableRooms = $stmt->fetch_assoc();
    if ($availableRooms) {
        $availableRoomsCount = (int) $availableRooms['cnt'];
    }
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'owner'");
if ($stmt) {
    $ownerCount = $stmt->fetch_assoc();
    if ($ownerCount) {
        $totalOwners = (int) $ownerCount['cnt'];
    }
    $stmt->close();
}

$stmt = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'tenant'");
if ($stmt) {
    $tenantCount = $stmt->fetch_assoc();
    if ($tenantCount) {
        $totalTenants = (int) $tenantCount['cnt'];
    }
    $stmt->close();
}

$stmt = $conn->query("
    SELECT b.id, b.booking_date, b.created_at,
           rm.title AS room_title,
           rm.location AS room_location,
           t.name AS tenant_name,
           o.name AS owner_name
    FROM bookings b
    INNER JOIN rooms rm ON b.room_id = rm.id
    INNER JOIN users t ON b.tenant_id = t.id
    INNER JOIN users o ON rm.owner_id = o.id
    WHERE b.status = 'pending'
    ORDER BY b.created_at DESC
    LIMIT 5
");
if ($stmt) {
    $recentPendingBookings = $stmt->fetch_all(MYSQLI_ASSOC);
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

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-page-header">
                <h1 class="admin-page-title">Admin Dashboard</h1>
                <p class="admin-page-subtitle">Overview of your RoomFinder platform.</p>
            </div>

            <div class="admin-stats">
                <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=pending') ?>"
                    class="admin-stat-card admin-stat-pending dashboard-stat-link">
                    <div class="admin-stat-number"><?= number_format($pendingBookingsCount) ?></div>
                    <div class="admin-stat-label">Pending Bookings</div>
                    <div class="dashboard-stat-hint">Awaiting approval</div>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/rooms') . '?status=available') ?>"
                    class="admin-stat-card admin-stat-approved dashboard-stat-link">
                    <div class="admin-stat-number"><?= number_format($availableRoomsCount) ?></div>
                    <div class="admin-stat-label">Available Rooms</div>
                    <div class="dashboard-stat-hint">Open listings</div>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=owner') ?>"
                    class="admin-stat-card admin-stat-owner dashboard-stat-link">
                    <div class="admin-stat-number"><?= number_format($totalOwners) ?></div>
                    <div class="admin-stat-label">Owners</div>
                    <div class="dashboard-stat-hint">Registered owners</div>
                </a>
                <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=tenant') ?>"
                    class="admin-stat-card admin-stat-tenant dashboard-stat-link">
                    <div class="admin-stat-number"><?= number_format($totalTenants) ?></div>
                    <div class="admin-stat-label">Tenants</div>
                    <div class="dashboard-stat-hint">Registered tenants</div>
                </a>
            </div>

            <div class="dashboard-grid">

                <div class="dashboard-main-col">

                    <div class="admin-card">
                        <div class="admin-toolbar">
                            <div>
                                <h2 class="dashboard-card-title">Pending Booking Requests</h2>
                                <p class="dashboard-card-subtitle">Latest requests waiting for approval.</p>
                            </div>
                            <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=pending') ?>"
                                class="admin-btn">View All Pending</a>
                        </div>

                        <?php if (empty($recentPendingBookings)): ?>
                            <div class="admin-empty">
                                <div class="admin-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3.5" y="5" width="17" height="16" rx="2" />
                                        <path d="M7 3V7" />
                                        <path d="M17 3V7" />
                                        <path d="M3.5 10H20.5" />
                                    </svg>
                                </div>
                                <h3 class="admin-empty-title">No pending booking requests</h3>
                                <p class="admin-empty-text">New requests will appear here once tenants submit bookings.</p>
                            </div>
                        <?php else: ?>
                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Tenant</th>
                                            <th>Owner</th>
                                            <th>Booking Date</th>
                                            <th>Requested</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPendingBookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($booking['room_title']) ?></strong>
                                                    <?php if (!empty($booking['room_location'])): ?>
                                                        <div class="dashboard-table-meta">
                                                            <?= htmlspecialchars($booking['room_location']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($booking['tenant_name']) ?></td>
                                                <td><?= htmlspecialchars($booking['owner_name']) ?></td>
                                                <td><?= date('M j, Y', strtotime($booking['booking_date'])) ?></td>
                                                <td><?= date('M j, Y', strtotime($booking['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="dashboard-side-col">

                    <div class="admin-card">
                        <div class="admin-toolbar">
                            <h2 class="dashboard-card-title">Booking Status</h2>
                        </div>
                        <div class="monitor-quick-actions">
                            <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=pending') ?>"
                                class="monitor-quick-link">
                                Pending
                                <span class="admin-badge admin-badge-pending"><?= number_format($pendingBookingsCount) ?></span>
                            </a>
                            <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=approved') ?>"
                                class="monitor-quick-link">
                                Approved
                                <span class="admin-badge admin-badge-approved"><?= number_format($approvedBookingsCount) ?></span>
                            </a>
                            <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=rejected') ?>"
                                class="monitor-quick-link">
                                Rejected
                                <span class="admin-badge admin-badge-rejected"><?= number_format($rejectedBookingsCount) ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="admin-toolbar">
                            <h2 class="dashboard-card-title">Quick Actions</h2>
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
                            <a href="<?= htmlspecialchars(base_url('admin/monitor')) ?>" class="monitor-quick-link">
                                System Monitor
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
