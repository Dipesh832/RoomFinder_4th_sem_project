<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'rooms';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$allowedStatuses = ['available', 'booked'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$typeStmt = $conn->query("SELECT DISTINCT room_type FROM rooms WHERE room_type != '' ORDER BY room_type ASC");
$roomTypes = [];
if ($typeStmt) {
    while ($row = $typeStmt->fetch_assoc()) {
        $roomTypes[] = $row['room_type'];
    }
    $typeStmt->close();
}

if ($typeFilter !== '' && !in_array($typeFilter, $roomTypes, true)) {
    $typeFilter = '';
}

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(r.title LIKE ? OR r.location LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'ss';
}

if ($statusFilter !== '') {
    $conditions[] = "r.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($typeFilter !== '') {
    $conditions[] = "r.room_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$countSql = "SELECT COUNT(*) AS total FROM rooms r {$whereClause}";
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

$dataSql = "SELECT
    r.id,
    r.title,
    r.location,
    r.price,
    r.room_type,
    r.image,
    r.status,
    r.created_at,
    u.name AS owner_name,
    u.id AS owner_id
FROM rooms r
INNER JOIN users u ON r.owner_id = u.id
{$whereClause}
ORDER BY r.created_at DESC
LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$perPage, $offset]);
$dataTypes = $types . 'ii';

$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$rooms = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_room') {
    $deleteRoomId = (int) ($_POST['room_id'] ?? 0);

    if ($deleteRoomId > 0) {
        $imgStmt = $conn->prepare("SELECT image FROM rooms WHERE id = ?");
        $imgStmt->bind_param("i", $deleteRoomId);
        $imgStmt->execute();
        $roomImage = $imgStmt->get_result()->fetch_assoc();
        $imgStmt->close();

        $delStmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $delStmt->bind_param("i", $deleteRoomId);
        $delStmt->execute();
        $delStmt->close();

        if ($roomImage && !empty($roomImage['image'])) {
            $imagePath = __DIR__ . '/../' . $roomImage['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $_SESSION['success'] = 'Room deleted successfully.';
    }

    header("Location: " . base_url('admin/rooms'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms | RoomFinder Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-page-header">
                <h1 class="admin-page-title">Manage Rooms</h1>
                <p class="admin-page-subtitle">View and manage all rooms listed on the platform.</p>
            </div>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="admin-card">

                <div class="admin-toolbar">
                    <div class="admin-toolbar-left">
                        <div class="admin-search">
                            <svg class="admin-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/rooms')) ?>" id="search-form">
                                <input type="text" name="search" placeholder="Search by title or location..." value="<?= htmlspecialchars($search) ?>">
                                <?php if ($statusFilter !== ''): ?>
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                <?php endif; ?>
                                <?php if ($typeFilter !== ''): ?>
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="admin-filter">
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/rooms')) ?>" id="status-form">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="booked" <?= $statusFilter === 'booked' ? 'selected' : '' ?>>Booked</option>
                                </select>
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                                <?php if ($typeFilter !== ''): ?>
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php if (!empty($roomTypes)): ?>
                            <div class="admin-filter">
                                <form method="GET" action="<?= htmlspecialchars(base_url('admin/rooms')) ?>" id="type-form">
                                    <select name="type" onchange="this.form.submit()">
                                        <option value="">All Types</option>
                                        <?php foreach ($roomTypes as $rt): ?>
                                            <option value="<?= htmlspecialchars($rt) ?>" <?= $typeFilter === $rt ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($rt)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($search !== ''): ?>
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <?php endif; ?>
                                    <?php if ($statusFilter !== ''): ?>
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="admin-toolbar-right">
                        <span style="color: #64748b; font-size: 14px;"><?= number_format($totalRows) ?> room<?= $totalRows !== 1 ? 's' : '' ?> found</span>
                    </div>
                </div>

                <?php if (empty($rooms)): ?>

                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 10.5L12 3L21 10.5" />
                                <path d="M5 9.5V20H19V9.5" />
                                <path d="M9 20V14H15V20" />
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No rooms found</h3>
                        <p class="admin-empty-text">
                            <?php if ($search !== '' || $statusFilter !== '' || $typeFilter !== ''): ?>
                                No rooms match your current filters. Try adjusting your search or filter criteria.
                            <?php else: ?>
                                There are no rooms listed on the platform yet.
                            <?php endif; ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Owner</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td>
                                            <div class="admin-room-thumb">
                                                <?php if (!empty($room['image'])): ?>
                                                    <img src="<?= htmlspecialchars(base_url($room['image'])) ?>" alt="<?= htmlspecialchars($room['title']) ?>">
                                                <?php else: ?>
                                                    <span class="admin-room-thumb-placeholder">No Image</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($room['title']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($room['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($room['location']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($room['room_type'])) ?></td>
                                        <td>Rs. <?= number_format((float) $room['price'], 2) ?></td>
                                        <td>
                                            <?php
                                            $statusBadge = $room['status'] === 'available' ? 'admin-badge-available' : 'admin-badge-booked';
                                            ?>
                                            <span class="admin-badge <?= $statusBadge ?>"><?= htmlspecialchars(ucfirst($room['status'])) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($room['created_at'])) ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <button type="button" class="admin-btn" onclick='openRoomModal(<?= json_encode($room, JSON_HEX_APOS | JSON_HEX_TAG) ?>)' title="View Details">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </button>
                                                <button type="button" class="admin-btn admin-btn-danger" onclick="confirmDeleteRoom(<?= (int) $room['id'] ?>, '<?= htmlspecialchars(addslashes($room['title']), ENT_QUOTES) ?>')" title="Delete Room">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
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
                                $paginationBase = base_url('admin/rooms') . '?' . http_build_query(array_filter([
                                    'search' => $search !== '' ? $search : null,
                                    'status' => $statusFilter !== '' ? $statusFilter : null,
                                    'type' => $typeFilter !== '' ? $typeFilter : null,
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

    <!-- Room Detail Modal -->
    <div class="admin-modal-overlay" id="room-modal">
        <div class="admin-modal">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Room Details</h3>
                <button class="admin-modal-close" onclick="closeRoomModal()" aria-label="Close">&times;</button>
            </div>
            <div class="admin-modal-body" id="room-modal-body">
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form method="POST" action="<?= htmlspecialchars(base_url('admin/rooms')) ?>" id="delete-room-form" style="display:none;">
        <input type="hidden" name="action" value="delete_room">
        <input type="hidden" name="room_id" id="delete-room-id">
    </form>

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

    function openRoomModal(room) {
        var statusClass = room.status === 'available' ? 'admin-badge-available' : 'admin-badge-booked';
        var imageUrl = room.image ? '<?= base_url('') ?>' + escapeHtml(room.image) : '';
        var imageHtml = imageUrl
            ? '<img src="' + imageUrl + '" style="width:100%;height:180px;object-fit:cover;border-radius:10px;margin-bottom:16px;" alt="' + escapeHtml(room.title) + '">'
            : '<div style="width:100%;height:120px;background:#f1f5f9;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#94a3b8;margin-bottom:16px;">No Image</div>';

        var html = imageHtml;
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Title</span><span class="admin-modal-value">' + escapeHtml(room.title) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Owner</span><span class="admin-modal-value">' + escapeHtml(room.owner_name) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Location</span><span class="admin-modal-value">' + escapeHtml(room.location) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Room Type</span><span class="admin-modal-value">' + escapeHtml(room.room_type.charAt(0).toUpperCase() + room.room_type.slice(1)) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Price</span><span class="admin-modal-value">Rs. ' + escapeHtml(room.price) + ' / month</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Status</span><span class="admin-modal-value"><span class="admin-badge ' + statusClass + '">' + escapeHtml(room.status.charAt(0).toUpperCase() + room.status.slice(1)) + '</span></span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Created</span><span class="admin-modal-value">' + escapeHtml(room.created_at) + '</span></div>';

        document.getElementById('room-modal-body').innerHTML = html;
        document.getElementById('room-modal').classList.add('open');
    }

    function closeRoomModal() {
        document.getElementById('room-modal').classList.remove('open');
    }

    function confirmDeleteRoom(roomId, roomTitle) {
        if (confirm('Are you sure you want to delete room "' + roomTitle + '"?\n\nThis will also remove all related bookings, bookmarks, and messages. This action cannot be undone.')) {
            document.getElementById('delete-room-id').value = roomId;
            document.getElementById('delete-room-form').submit();
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    document.getElementById('room-modal').addEventListener('click', function(e) {
        if (e.target === this) closeRoomModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRoomModal();
    });
    </script>

</body>

</html>
