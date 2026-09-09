<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'monitor';

// ─── Statistics Queries ───────────────────────────────────────
// Users breakdown
$totalUsers = $totalOwners = $totalTenants = $totalAdmins = 0;
$stmt = $conn->query("SELECT COUNT(*) AS total, SUM(role='owner') AS owners, SUM(role='tenant') AS tenants, SUM(role='admin') AS admins FROM users");
if ($stmt) {
    $row = $stmt->fetch_assoc();
    $totalUsers  = (int)($row['total']  ?? 0);
    $totalOwners = (int)($row['owners'] ?? 0);
    $totalTenants= (int)($row['tenants']?? 0);
    $totalAdmins = (int)($row['admins'] ?? 0);
    $stmt->close();
}

// Rooms breakdown
$totalRooms = $availableRooms = $bookedRooms = 0;
$stmt = $conn->query("SELECT COUNT(*) AS total, SUM(status='available') AS available, SUM(status='booked') AS booked FROM rooms");
if ($stmt) {
    $row = $stmt->fetch_assoc();
    $totalRooms    = (int)($row['total']    ?? 0);
    $availableRooms= (int)($row['available']?? 0);
    $bookedRooms   = (int)($row['booked']   ?? 0);
    $stmt->close();
}

// Bookings breakdown
$totalBookings = $pendingBookings = $approvedBookings = $rejectedBookings = 0;
$stmt = $conn->query("SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status='approved') AS approved, SUM(status='rejected') AS rejected FROM bookings");
if ($stmt) {
    $row = $stmt->fetch_assoc();
    $totalBookings   = (int)($row['total']   ?? 0);
    $pendingBookings = (int)($row['pending'] ?? 0);
    $approvedBookings= (int)($row['approved']?? 0);
    $rejectedBookings= (int)($row['rejected']?? 0);
    $stmt->close();
}

// Messages (optional but straightforward)
$totalMessages = $unreadMessages = 0;
$stmt = $conn->query("SELECT COUNT(*) AS total, SUM(is_read=0) AS unread FROM messages");
if ($stmt) {
    $row = $stmt->fetch_assoc();
    $totalMessages = (int)($row['total']   ?? 0);
    $unreadMessages= (int)($row['unread']  ?? 0);
    $stmt->close();
}

// ─── Recent Users (5) ─────────────────────────────────────────
$recentUsers = [];
$stmt = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
if ($stmt) {
    $recentUsers = $stmt->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Recent Rooms (5) ─────────────────────────────────────────
$recentRooms = [];
$stmt = $conn->query("
    SELECT r.id, r.title, r.location, r.price, r.image, r.status, r.created_at,
           u.name AS owner_name
    FROM rooms r
    INNER JOIN users u ON r.owner_id = u.id
    ORDER BY r.created_at DESC LIMIT 5
");
if ($stmt) {
    $recentRooms = $stmt->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ─── Recent Bookings (5) ──────────────────────────────────────
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

// ─── Recent Activity (combined from users, rooms, bookings) ───
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

// Sort combined activity by created_at descending and take top 6
usort($activity, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$activity = array_slice($activity, 0, 6);
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

            <div class="admin-page-header">
                <h1 class="admin-page-title">System Monitor</h1>
                <p class="admin-page-subtitle">Real-time overview of the entire RoomFinder platform.</p>
            </div>

            <!-- ─── Users Overview ──────────────────────────── -->
            <div class="monitor-section">
                <h2 class="monitor-section-title">Users</h2>
                <div class="admin-stats">
                    <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="admin-stat-card">
                        <div class="admin-stat-number"><?= $totalUsers ?></div>
                        <div class="admin-stat-label">Total Users</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=owner') ?>" class="admin-stat-card admin-stat-owner">
                        <div class="admin-stat-number"><?= $totalOwners ?></div>
                        <div class="admin-stat-label">Owners</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=tenant') ?>" class="admin-stat-card admin-stat-tenant">
                        <div class="admin-stat-number"><?= $totalTenants ?></div>
                        <div class="admin-stat-label">Tenants</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/users') . '?role=admin') ?>" class="admin-stat-card admin-stat-admin">
                        <div class="admin-stat-number"><?= $totalAdmins ?></div>
                        <div class="admin-stat-label">Admins</div>
                    </a>
                </div>
            </div>

            <!-- ─── Rooms Overview ──────────────────────────── -->
            <div class="monitor-section">
                <h2 class="monitor-section-title">Rooms</h2>
                <div class="admin-stats">
                    <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="admin-stat-card">
                        <div class="admin-stat-number"><?= $totalRooms ?></div>
                        <div class="admin-stat-label">Total Rooms</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/rooms') . '?status=available') ?>" class="admin-stat-card admin-stat-approved">
                        <div class="admin-stat-number"><?= $availableRooms ?></div>
                        <div class="admin-stat-label">Available</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/rooms') . '?status=booked') ?>" class="admin-stat-card admin-stat-rejected">
                        <div class="admin-stat-number"><?= $bookedRooms ?></div>
                        <div class="admin-stat-label">Booked</div>
                    </a>
                </div>
            </div>

            <!-- ─── Bookings Overview ───────────────────────── -->
            <div class="monitor-section">
                <h2 class="monitor-section-title">Bookings</h2>
                <div class="admin-stats">
                    <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="admin-stat-card">
                        <div class="admin-stat-number"><?= $totalBookings ?></div>
                        <div class="admin-stat-label">Total Bookings</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=pending') ?>" class="admin-stat-card admin-stat-pending">
                        <div class="admin-stat-number"><?= $pendingBookings ?></div>
                        <div class="admin-stat-label">Pending</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=approved') ?>" class="admin-stat-card admin-stat-approved">
                        <div class="admin-stat-number"><?= $approvedBookings ?></div>
                        <div class="admin-stat-label">Approved</div>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/bookings') . '?status=rejected') ?>" class="admin-stat-card admin-stat-rejected">
                        <div class="admin-stat-number"><?= $rejectedBookings ?></div>
                        <div class="admin-stat-label">Rejected</div>
                    </a>
                </div>
            </div>

            <!-- ─── Messages Overview ───────────────────────── -->
            <div class="monitor-section">
                <h2 class="monitor-section-title">Messages</h2>
                <div class="admin-stats">
                    <div class="admin-stat-card">
                        <div class="admin-stat-number"><?= $totalMessages ?></div>
                        <div class="admin-stat-label">Total Messages</div>
                    </div>
                    <div class="admin-stat-card admin-stat-pending">
                        <div class="admin-stat-number"><?= $unreadMessages ?></div>
                        <div class="admin-stat-label">Unread</div>
                    </div>
                </div>
            </div>

            <!-- ─── Recent Users ────────────────────────────── -->
            <div class="monitor-section">
                <div class="monitor-section-header">
                    <h2 class="monitor-section-title">Recent Users</h2>
                    <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="admin-btn">View All Users</a>
                </div>
                <?php if (empty($recentUsers)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No users yet</h3>
                        <p class="admin-empty-text">No users have registered on the platform yet.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $u): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'admin-badge-role';
                                            if ($u['role'] === 'owner') $badgeClass = 'admin-badge-owner';
                                            elseif ($u['role'] === 'tenant') $badgeClass = 'admin-badge-tenant';
                                            ?>
                                            <span class="admin-badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ─── Recent Rooms ────────────────────────────── -->
            <div class="monitor-section">
                <div class="monitor-section-header">
                    <h2 class="monitor-section-title">Recent Rooms</h2>
                    <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="admin-btn">View All Rooms</a>
                </div>
                <?php if (empty($recentRooms)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No rooms yet</h3>
                        <p class="admin-empty-text">No rooms have been listed on the platform yet.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Owner</th>
                                    <th>Location</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRooms as $r): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if (!empty($r['image'])): ?>
                                                    <div class="admin-room-thumb">
                                                        <img src="<?= htmlspecialchars('../' . $r['image']) ?>" alt="<?= htmlspecialchars($r['title']) ?>">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="admin-room-thumb admin-room-thumb-placeholder">No<br>Image</div>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($r['title']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($r['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($r['location']) ?></td>
                                        <td>Rs. <?= number_format((float)$r['price']) ?></td>
                                        <td>
                                            <span class="admin-badge <?= $r['status'] === 'available' ? 'admin-badge-available' : 'admin-badge-booked' ?>">
                                                <?= htmlspecialchars(ucfirst($r['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ─── Recent Bookings ─────────────────────────── -->
            <div class="monitor-section">
                <div class="monitor-section-header">
                    <h2 class="monitor-section-title">Recent Bookings</h2>
                    <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="admin-btn">View All Bookings</a>
                </div>
                <?php if (empty($recentBookings)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No bookings yet</h3>
                        <p class="admin-empty-text">No bookings have been made on the platform yet.</p>
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
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($b['room_title']) ?></strong></td>
                                        <td><?= htmlspecialchars($b['tenant_name']) ?></td>
                                        <td><?= htmlspecialchars($b['owner_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
                                        <td>
                                            <span class="admin-badge admin-badge-<?= $b['status'] ?>">
                                                <?= htmlspecialchars(ucfirst($b['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ─── Recent Activity ─────────────────────────── -->
            <div class="monitor-section">
                <h2 class="monitor-section-title">Recent Activity</h2>
                <?php if (empty($activity)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No activity yet</h3>
                        <p class="admin-empty-text">No recent activity to display.</p>
                    </div>
                <?php else: ?>
                    <div class="monitor-activity-list">
                        <?php foreach ($activity as $a): ?>
                            <div class="monitor-activity-item">
                                <span class="monitor-activity-dot monitor-activity-dot-<?= $a['type'] ?>"></span>
                                <div class="monitor-activity-content">
                                    <span class="monitor-activity-text">
                                        <?php if ($a['type'] === 'user'): ?>
                                            New user registered: <strong><?= htmlspecialchars($a['title']) ?></strong>
                                        <?php elseif ($a['type'] === 'room'): ?>
                                            New room added: <strong><?= htmlspecialchars($a['title']) ?></strong>
                                        <?php else: ?>
                                            <?= htmlspecialchars($a['title']) ?> created
                                        <?php endif; ?>
                                    </span>
                                    <span class="monitor-activity-time"><?= date('M j, g:i A', strtotime($a['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ─── Quick Actions ───────────────────────────── -->
            <div class="monitor-section">
                <h2 class="monitor-section-title">Quick Actions</h2>
                <div class="monitor-actions">
                    <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="monitor-action-card">
                        <span class="monitor-action-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </span>
                        <span class="monitor-action-label">Manage Users</span>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="monitor-action-card">
                        <span class="monitor-action-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </span>
                        <span class="monitor-action-label">Manage Rooms</span>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="monitor-action-card">
                        <span class="monitor-action-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </span>
                        <span class="monitor-action-label">Monitor Bookings</span>
                    </a>
                </div>
            </div>

        </main>

    </div>

</body>

</html>
