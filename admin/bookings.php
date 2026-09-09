<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'bookings';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

if ($statusFilter !== '' && !in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $statusFilter = '';
}
if ($dateFilter !== '' && !in_array($dateFilter, ['all', 'upcoming', 'past'], true)) {
    $dateFilter = '';
}

// Summary stats (unfiltered)
$summarySql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
FROM bookings b";
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult->fetch_assoc();

// Build WHERE conditions for filtered list
$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(r.title LIKE ? OR tenant.name LIKE ? OR owner.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'sss';
}

if ($statusFilter !== '') {
    $conditions[] = "b.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($dateFilter === 'upcoming') {
    $conditions[] = "b.booking_date >= CURDATE()";
} elseif ($dateFilter === 'past') {
    $conditions[] = "b.booking_date < CURDATE()";
}

$whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count total filtered rows
$countSql = "SELECT COUNT(*) AS total
FROM bookings b
INNER JOIN rooms r ON b.room_id = r.id
INNER JOIN users tenant ON b.tenant_id = tenant.id
INNER JOIN users owner ON r.owner_id = owner.id
{$whereClause}";

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch booking data
$dataSql = "SELECT
    b.id AS booking_id,
    b.status,
    b.booking_date,
    b.created_at,
    r.id AS room_id,
    r.title AS room_title,
    r.location AS room_location,
    r.room_type,
    tenant.name AS tenant_name,
    tenant.email AS tenant_email,
    owner.name AS owner_name,
    owner.email AS owner_email
FROM bookings b
INNER JOIN rooms r ON b.room_id = r.id
INNER JOIN users tenant ON b.tenant_id = tenant.id
INNER JOIN users owner ON r.owner_id = owner.id
{$whereClause}
ORDER BY b.created_at DESC
LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$perPage, $offset]);
$dataTypes = $types . 'ii';

$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$bookings = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Bookings | RoomFinder Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-page-header">
                <h1 class="admin-page-title">Monitor Bookings</h1>
                <p class="admin-page-subtitle">Track and review all booking activity on the platform.</p>
            </div>

            <!-- Summary Cards -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?= (int) $summary['total'] ?></div>
                    <div class="admin-stat-label">Total Bookings</div>
                </div>
                <div class="admin-stat-card admin-stat-pending">
                    <div class="admin-stat-number"><?= (int) $summary['pending_count'] ?></div>
                    <div class="admin-stat-label">Pending</div>
                </div>
                <div class="admin-stat-card admin-stat-approved">
                    <div class="admin-stat-number"><?= (int) $summary['approved_count'] ?></div>
                    <div class="admin-stat-label">Approved</div>
                </div>
                <div class="admin-stat-card admin-stat-rejected">
                    <div class="admin-stat-number"><?= (int) $summary['rejected_count'] ?></div>
                    <div class="admin-stat-label">Rejected</div>
                </div>
            </div>

            <div class="admin-card">

                <div class="admin-toolbar">
                    <div class="admin-toolbar-left">
                        <div class="admin-search">
                            <svg class="admin-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/bookings')) ?>" id="search-form">
                                <input type="text" name="search" placeholder="Search by room, tenant, or owner..." value="<?= htmlspecialchars($search) ?>">
                                <?php if ($statusFilter !== ''): ?>
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                <?php endif; ?>
                                <?php if ($dateFilter !== ''): ?>
                                    <input type="hidden" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="admin-filter">
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/bookings')) ?>" id="status-form">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                                <?php if ($dateFilter !== ''): ?>
                                    <input type="hidden" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="admin-filter">
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/bookings')) ?>" id="date-form">
                                <select name="date" onchange="this.form.submit()">
                                    <option value="">All Dates</option>
                                    <option value="upcoming" <?= $dateFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                    <option value="past" <?= $dateFilter === 'past' ? 'selected' : '' ?>>Past</option>
                                </select>
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                                <?php if ($statusFilter !== ''): ?>
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="admin-toolbar-right">
                        <span style="color: #64748b; font-size: 14px;"><?= number_format($totalRows) ?> booking<?= $totalRows !== 1 ? 's' : '' ?> found</span>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>

                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3.5" y="5" width="17" height="16" rx="2" />
                                <path d="M7 3V7" />
                                <path d="M17 3V7" />
                                <path d="M3.5 10H20.5" />
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No bookings found</h3>
                        <p class="admin-empty-text">
                            <?php if ($search !== '' || $statusFilter !== '' || $dateFilter !== ''): ?>
                                No bookings match your current filters. Try adjusting your search or filter criteria.
                            <?php else: ?>
                                There are no bookings on the platform yet.
                            <?php endif; ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Room</th>
                                    <th>Tenant</th>
                                    <th>Owner</th>
                                    <th>Booking Date</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td><?= (int) $b['booking_id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($b['room_title']) ?></strong>
                                            <div style="color: #64748b; font-size: 12px; margin-top: 2px;"><?= htmlspecialchars($b['room_location']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($b['tenant_name']) ?></td>
                                        <td><?= htmlspecialchars($b['owner_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($b['booking_date'])) ?></td>
                                        <td>
                                            <?php
                                            $statusBadgeClass = 'admin-badge-pending';
                                            if ($b['status'] === 'approved') $statusBadgeClass = 'admin-badge-approved';
                                            elseif ($b['status'] === 'rejected') $statusBadgeClass = 'admin-badge-rejected';
                                            ?>
                                            <span class="admin-badge <?= $statusBadgeClass ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <button type="button" class="admin-btn" onclick='openBookingModal(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_TAG) ?>)' title="View Details">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="admin-pagination">
                            <div class="admin-pagination-info">
                                Showing <?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $totalRows) ?> of <?= number_format($totalRows) ?>
                            </div>
                            <div class="admin-pagination-links">
                                <?php
                                $paginationBase = base_url('admin/bookings') . '?' . http_build_query(array_filter([
                                    'search' => $search !== '' ? $search : null,
                                    'status' => $statusFilter !== '' ? $statusFilter : null,
                                    'date' => $dateFilter !== '' ? $dateFilter : null,
                                ]));
                                $sep = strpos($paginationBase, '?') !== false ? '&' : '?';
                                ?>

                                <a href="<?= $paginationBase . $sep . 'page=' . max(1, $page - 1) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">&laquo;</a>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                if ($startPage > 1): ?>
                                    <a href="<?= $paginationBase . $sep . 'page=1' ?>">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="disabled">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="<?= $paginationBase . $sep . 'page=' . $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="disabled">...</span>
                                    <?php endif; ?>
                                    <a href="<?= $paginationBase . $sep . 'page=' . $totalPages ?>"><?= $totalPages ?></a>
                                <?php endif; ?>

                                <a href="<?= $paginationBase . $sep . 'page=' . min($totalPages, $page + 1) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">&raquo;</a>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </main>

    </div>

    <!-- Booking Detail Modal -->
    <div class="admin-modal-overlay" id="booking-modal">
        <div class="admin-modal">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Booking Details</h3>
                <button class="admin-modal-close" onclick="closeBookingModal()" aria-label="Close">&times;</button>
            </div>
            <div class="admin-modal-body" id="booking-modal-body">
            </div>
        </div>
    </div>

    <script>
    document.getElementById('search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var input = form.querySelector('input[name="search"]');
        if (input.value.trim() === '') {
            input.removeAttribute('name');
        }
        form.submit();
    });

    function openBookingModal(b) {
        var statusClass = 'admin-badge-pending';
        if (b.status === 'approved') statusClass = 'admin-badge-approved';
        else if (b.status === 'rejected') statusClass = 'admin-badge-rejected';

        var html = '<div class="admin-modal-field"><span class="admin-modal-label">Booking ID</span><span class="admin-modal-value">#' + escapeHtml(String(b.booking_id)) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Status</span><span class="admin-modal-value"><span class="admin-badge ' + statusClass + '">' + escapeHtml(b.status.charAt(0).toUpperCase() + b.status.slice(1)) + '</span></span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Room Title</span><span class="admin-modal-value">' + escapeHtml(b.room_title) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Room Location</span><span class="admin-modal-value">' + escapeHtml(b.room_location) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Room Type</span><span class="admin-modal-value">' + escapeHtml(b.room_type) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Tenant Name</span><span class="admin-modal-value">' + escapeHtml(b.tenant_name) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Tenant Email</span><span class="admin-modal-value">' + escapeHtml(b.tenant_email) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Owner Name</span><span class="admin-modal-value">' + escapeHtml(b.owner_name) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Owner Email</span><span class="admin-modal-value">' + escapeHtml(b.owner_email) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Booking Date</span><span class="admin-modal-value">' + escapeHtml(b.booking_date) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Created At</span><span class="admin-modal-value">' + escapeHtml(b.created_at) + '</span></div>';

        document.getElementById('booking-modal-body').innerHTML = html;
        document.getElementById('booking-modal').classList.add('open');
    }

    function closeBookingModal() {
        document.getElementById('booking-modal').classList.remove('open');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    document.getElementById('booking-modal').addEventListener('click', function(e) {
        if (e.target === this) closeBookingModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeBookingModal();
    });
    </script>

</body>

</html>
