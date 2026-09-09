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
    r.description,
    r.location,
    r.price,
    r.room_type,
    r.facilities,
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

// ─── Load owners for dropdowns ──────────────────────────────────
$ownersStmt = $conn->query("SELECT id, name FROM users WHERE role = 'owner' ORDER BY name ASC");
$owners = [];
if ($ownersStmt) {
    $owners = $ownersStmt->fetch_all(MYSQLI_ASSOC);
    $ownersStmt->close();
}

$ownerIds = [];
foreach ($owners as $o) {
    $ownerIds[] = (int) $o['id'];
}

// ─── Upload directory setup ─────────────────────────────────────
$uploadDir = __DIR__ . '/../assets/uploads/rooms/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ─── Helper: process room image upload ──────────────────────────
function processRoomImageUpload() {
    global $uploadDir;

    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => ''];
    }

    $file    = $_FILES['image'];
    $tmpName = $file['tmp_name'];
    $fileErr = $file['error'];
    $fileSize = $file['size'];

    if ($fileErr !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'File upload failed. Please try again.'];
    }

    $maxSize = 2 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        return ['path' => null, 'error' => 'Image must be 2 MB or less.'];
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mimeType, $allowedMimes, true)) {
        return ['path' => null, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $ext        = $extMap[$mimeType];
    $uniqueName = 'room_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath   = $uploadDir . $uniqueName;

    if (move_uploaded_file($tmpName, $destPath)) {
        return ['path' => 'assets/uploads/rooms/' . $uniqueName, 'error' => ''];
    }

    return ['path' => null, 'error' => 'Failed to save the uploaded image.'];
}

// ─── CREATE ROOM ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_room') {
    $addOwnerId   = (int) ($_POST['owner_id'] ?? 0);
    $addTitle     = trim($_POST['title'] ?? '');
    $addDesc      = trim($_POST['description'] ?? '');
    $addLocation  = trim($_POST['location'] ?? '');
    $addPrice     = trim($_POST['price'] ?? '');
    $addRoomType  = trim($_POST['room_type'] ?? '');
    $addFacilities= trim($_POST['facilities'] ?? '');
    $addStatus    = $_POST['status'] ?? 'available';

    $addErrors = [];

    if ($addOwnerId <= 0 || !in_array($addOwnerId, $ownerIds, true)) {
        $addErrors[] = 'Please select a valid owner.';
    }

    if ($addTitle === '') {
        $addErrors[] = 'Title is required.';
    } elseif (strlen($addTitle) > 150) {
        $addErrors[] = 'Title must be 150 characters or fewer.';
    }

    if ($addDesc === '') {
        $addErrors[] = 'Description is required.';
    }

    if ($addLocation === '') {
        $addErrors[] = 'Location is required.';
    }

    if ($addPrice === '') {
        $addErrors[] = 'Price is required.';
    } elseif (!is_numeric($addPrice) || (float) $addPrice < 0) {
        $addErrors[] = 'Price must be a non-negative number.';
    }

    if ($addRoomType === '') {
        $addErrors[] = 'Room type is required.';
    }

    if (!in_array($addStatus, $allowedStatuses, true)) {
        $addStatus = 'available';
    }

    $uploadResult = ['path' => null, 'error' => ''];
    if (empty($addErrors)) {
        $uploadResult = processRoomImageUpload();
        if ($uploadResult['error'] !== '') {
            $addErrors[] = $uploadResult['error'];
        }
    }

    if (!empty($addErrors)) {
        $_SESSION['error'] = implode(' ', $addErrors);
        $_SESSION['old_add'] = [
            'owner_id'   => $addOwnerId,
            'title'      => $addTitle,
            'description'=> $addDesc,
            'location'   => $addLocation,
            'price'      => $addPrice,
            'room_type'  => $addRoomType,
            'facilities' => $addFacilities,
            'status'     => $addStatus,
        ];
        header("Location: " . base_url('admin/rooms'));
        exit;
    }

    $facilitiesDb = $addFacilities !== '' ? $addFacilities : null;
    $imageDb      = $uploadResult['path'];

    $insStmt = $conn->prepare("INSERT INTO rooms (owner_id, title, description, location, price, room_type, facilities, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insStmt->bind_param("isssdssss", $addOwnerId, $addTitle, $addDesc, $addLocation, $addPrice, $addRoomType, $facilitiesDb, $imageDb, $addStatus);

    if ($insStmt->execute()) {
        $insStmt->close();
        $_SESSION['success'] = 'Room added successfully.';
        unset($_SESSION['old_add']);
    } else {
        $insStmt->close();
        if ($imageDb !== null && file_exists(__DIR__ . '/../' . $imageDb)) {
            unlink(__DIR__ . '/../' . $imageDb);
        }
        $_SESSION['error'] = 'Something went wrong. Please try again.';
        $_SESSION['old_add'] = [
            'owner_id'   => $addOwnerId,
            'title'      => $addTitle,
            'description'=> $addDesc,
            'location'   => $addLocation,
            'price'      => $addPrice,
            'room_type'  => $addRoomType,
            'facilities' => $addFacilities,
            'status'     => $addStatus,
        ];
    }

    header("Location: " . base_url('admin/rooms'));
    exit;
}

// ─── UPDATE ROOM ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $editRoomId    = (int) ($_POST['room_id'] ?? 0);
    $editOwnerId   = (int) ($_POST['owner_id'] ?? 0);
    $editTitle     = trim($_POST['title'] ?? '');
    $editDesc      = trim($_POST['description'] ?? '');
    $editLocation  = trim($_POST['location'] ?? '');
    $editPrice     = trim($_POST['price'] ?? '');
    $editRoomType  = trim($_POST['room_type'] ?? '');
    $editFacilities= trim($_POST['facilities'] ?? '');
    $editStatus    = $_POST['status'] ?? 'available';

    $editErrors = [];

    if ($editRoomId <= 0) {
        $editErrors[] = 'Invalid room ID.';
    }

    if ($editOwnerId <= 0 || !in_array($editOwnerId, $ownerIds, true)) {
        $editErrors[] = 'Please select a valid owner.';
    }

    if ($editTitle === '') {
        $editErrors[] = 'Title is required.';
    } elseif (strlen($editTitle) > 150) {
        $editErrors[] = 'Title must be 150 characters or fewer.';
    }

    if ($editDesc === '') {
        $editErrors[] = 'Description is required.';
    }

    if ($editLocation === '') {
        $editErrors[] = 'Location is required.';
    }

    if ($editPrice === '') {
        $editErrors[] = 'Price is required.';
    } elseif (!is_numeric($editPrice) || (float) $editPrice < 0) {
        $editErrors[] = 'Price must be a non-negative number.';
    }

    if ($editRoomType === '') {
        $editErrors[] = 'Room type is required.';
    }

    if (!in_array($editStatus, $allowedStatuses, true)) {
        $editStatus = 'available';
    }

    if (!empty($editErrors)) {
        $_SESSION['error'] = implode(' ', $editErrors);
        $_SESSION['old_edit'] = [
            'id'         => $editRoomId,
            'owner_id'   => $editOwnerId,
            'title'      => $editTitle,
            'description'=> $editDesc,
            'location'   => $editLocation,
            'price'      => $editPrice,
            'room_type'  => $editRoomType,
            'facilities' => $editFacilities,
            'status'     => $editStatus,
        ];
        header("Location: " . base_url('admin/rooms'));
        exit;
    }

    $uploadResult = ['path' => null, 'error' => ''];
    $uploadResult = processRoomImageUpload();

    if ($uploadResult['error'] !== '') {
        $_SESSION['error'] = $uploadResult['error'];
        $_SESSION['old_edit'] = [
            'id'         => $editRoomId,
            'owner_id'   => $editOwnerId,
            'title'      => $editTitle,
            'description'=> $editDesc,
            'location'   => $editLocation,
            'price'      => $editPrice,
            'room_type'  => $editRoomType,
            'facilities' => $editFacilities,
            'status'     => $editStatus,
        ];
        header("Location: " . base_url('admin/rooms'));
        exit;
    }

    $facilitiesDb = $editFacilities !== '' ? $editFacilities : null;
    $newImageDb   = $uploadResult['path'];

    if ($newImageDb !== null) {
        $oldImgStmt = $conn->prepare("SELECT image FROM rooms WHERE id = ?");
        $oldImgStmt->bind_param("i", $editRoomId);
        $oldImgStmt->execute();
        $oldImgRow = $oldImgStmt->get_result()->fetch_assoc();
        $oldImgStmt->close();

        $updStmt = $conn->prepare("UPDATE rooms SET owner_id = ?, title = ?, description = ?, location = ?, price = ?, room_type = ?, facilities = ?, image = ?, status = ? WHERE id = ?");
        $updStmt->bind_param("isssdssssi", $editOwnerId, $editTitle, $editDesc, $editLocation, $editPrice, $editRoomType, $facilitiesDb, $newImageDb, $editStatus, $editRoomId);

        if ($updStmt->execute()) {
            $updStmt->close();
            if ($oldImgRow && !empty($oldImgRow['image'])) {
                $oldPath = __DIR__ . '/../' . $oldImgRow['image'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $_SESSION['success'] = 'Room updated successfully.';
            unset($_SESSION['old_edit']);
        } else {
            $updStmt->close();
            if (file_exists(__DIR__ . '/../' . $newImageDb)) {
                unlink(__DIR__ . '/../' . $newImageDb);
            }
            $_SESSION['error'] = 'Something went wrong. Please try again.';
            $_SESSION['old_edit'] = [
                'id'         => $editRoomId,
                'owner_id'   => $editOwnerId,
                'title'      => $editTitle,
                'description'=> $editDesc,
                'location'   => $editLocation,
                'price'      => $editPrice,
                'room_type'  => $editRoomType,
                'facilities' => $editFacilities,
                'status'     => $editStatus,
            ];
        }
    } else {
        $updStmt = $conn->prepare("UPDATE rooms SET owner_id = ?, title = ?, description = ?, location = ?, price = ?, room_type = ?, facilities = ?, status = ? WHERE id = ?");
        $updStmt->bind_param("isssdsssi", $editOwnerId, $editTitle, $editDesc, $editLocation, $editPrice, $editRoomType, $facilitiesDb, $editStatus, $editRoomId);

        if ($updStmt->execute()) {
            $updStmt->close();
            $_SESSION['success'] = 'Room updated successfully.';
            unset($_SESSION['old_edit']);
        } else {
            $updStmt->close();
            $_SESSION['error'] = 'Something went wrong. Please try again.';
            $_SESSION['old_edit'] = [
                'id'         => $editRoomId,
                'owner_id'   => $editOwnerId,
                'title'      => $editTitle,
                'description'=> $editDesc,
                'location'   => $editLocation,
                'price'      => $editPrice,
                'room_type'  => $editRoomType,
                'facilities' => $editFacilities,
                'status'     => $editStatus,
            ];
        }
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

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-page-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div>
                    <h1 class="admin-page-title">Manage Rooms</h1>
                    <p class="admin-page-subtitle">View and manage all rooms listed on the platform.</p>
                </div>
                <button type="button" class="admin-btn-primary" onclick="openCreateModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Room
                </button>
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
                                                <button type="button" class="admin-btn" onclick='openEditModal(<?= json_encode($room, JSON_HEX_APOS | JSON_HEX_TAG) ?>)' title="Edit Room">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
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

    <!-- Create Room Modal -->
    <?php
    $oldAdd = $_SESSION['old_add'] ?? null;
    unset($_SESSION['old_add']);
    ?>
    <div class="admin-modal-overlay" id="create-modal">
        <div class="admin-modal" style="max-width: 600px;">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Add New Room</h3>
                <button class="admin-modal-close" onclick="closeCreateModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('admin/rooms')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_room">
                <div class="admin-modal-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Owner <span class="required">*</span></label>
                        <select name="owner_id" class="admin-form-select" required>
                            <option value="">Select an owner</option>
                            <?php foreach ($owners as $owner): ?>
                                <option value="<?= (int) $owner['id'] ?>" <?= ((int) ($oldAdd['owner_id'] ?? 0) === (int) $owner['id']) ? 'selected' : '' ?>><?= htmlspecialchars($owner['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Title <span class="required">*</span></label>
                        <input type="text" name="title" class="admin-form-input" placeholder="e.g. Single Room in Balaju" maxlength="150" required value="<?= htmlspecialchars($oldAdd['title'] ?? '') ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Description <span class="required">*</span></label>
                        <textarea name="description" class="admin-form-textarea" rows="3" placeholder="Describe the room, amenities, surroundings..." required><?= htmlspecialchars($oldAdd['description'] ?? '') ?></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Location <span class="required">*</span></label>
                        <input type="text" name="location" class="admin-form-input" placeholder="e.g. Balaju, Kathmandu" required value="<?= htmlspecialchars($oldAdd['location'] ?? '') ?>">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Price (Rs. / month) <span class="required">*</span></label>
                            <input type="number" name="price" class="admin-form-input" placeholder="e.g. 8000" min="0" step="0.01" required value="<?= htmlspecialchars($oldAdd['price'] ?? '') ?>">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Room Type <span class="required">*</span></label>
                            <input type="text" name="room_type" class="admin-form-input" placeholder="e.g. Single, Double" maxlength="50" required value="<?= htmlspecialchars($oldAdd['room_type'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Facilities <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <textarea name="facilities" class="admin-form-textarea" rows="2" placeholder="e.g. WiFi, Attached Bathroom, Parking"><?= htmlspecialchars($oldAdd['facilities'] ?? '') ?></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Room Image <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <div class="admin-form-file">
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                            <div class="admin-form-hint">JPG, PNG, or WEBP. Max 2 MB.</div>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Status <span class="required">*</span></label>
                        <select name="status" class="admin-form-select" required>
                            <option value="available" <?= ($oldAdd['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="booked" <?= ($oldAdd['status'] ?? '') === 'booked' ? 'selected' : '' ?>>Booked</option>
                        </select>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="admin-btn" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="admin-btn-primary">Create Room</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <?php
    $oldEdit = $_SESSION['old_edit'] ?? null;
    unset($_SESSION['old_edit']);
    ?>
    <div class="admin-modal-overlay" id="edit-modal">
        <div class="admin-modal" style="max-width: 600px;">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Edit Room</h3>
                <button class="admin-modal-close" onclick="closeEditModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('admin/rooms')) ?>" enctype="multipart/form-data" id="edit-room-form">
                <input type="hidden" name="action" value="update_room">
                <input type="hidden" name="room_id" id="edit-room-id">
                <div class="admin-modal-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Owner <span class="required">*</span></label>
                        <select name="owner_id" id="edit-owner-id" class="admin-form-select" required>
                            <option value="">Select an owner</option>
                            <?php foreach ($owners as $owner): ?>
                                <option value="<?= (int) $owner['id'] ?>"><?= htmlspecialchars($owner['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Title <span class="required">*</span></label>
                        <input type="text" name="title" id="edit-title" class="admin-form-input" placeholder="e.g. Single Room in Balaju" maxlength="150" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Description <span class="required">*</span></label>
                        <textarea name="description" id="edit-description" class="admin-form-textarea" rows="3" placeholder="Describe the room, amenities, surroundings..." required></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Location <span class="required">*</span></label>
                        <input type="text" name="location" id="edit-location" class="admin-form-input" placeholder="e.g. Balaju, Kathmandu" required>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Price (Rs. / month) <span class="required">*</span></label>
                            <input type="number" name="price" id="edit-price" class="admin-form-input" placeholder="e.g. 8000" min="0" step="0.01" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Room Type <span class="required">*</span></label>
                            <input type="text" name="room_type" id="edit-room-type" class="admin-form-input" placeholder="e.g. Single, Double" maxlength="50" required>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Facilities <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <textarea name="facilities" id="edit-facilities" class="admin-form-textarea" rows="2" placeholder="e.g. WiFi, Attached Bathroom, Parking"></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Room Image <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
                        <div class="admin-form-file">
                            <input type="file" name="image" id="edit-image" accept="image/jpeg,image/png,image/webp">
                            <div class="admin-form-hint">JPG, PNG, or WEBP. Max 2 MB. Leave empty to keep current image.</div>
                        </div>
                        <div class="admin-form-file-preview" id="edit-image-preview" style="display:none;"></div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Status <span class="required">*</span></label>
                        <select name="status" id="edit-status" class="admin-form-select" required>
                            <option value="available">Available</option>
                            <option value="booked">Booked</option>
                        </select>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="admin-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="admin-btn-primary">Save Changes</button>
                </div>
            </form>
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

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ─── View Room Modal ──────────────────────────────────
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
        if (room.description) {
            html += '<div class="admin-modal-field"><span class="admin-modal-label">Description</span><span class="admin-modal-value">' + escapeHtml(room.description) + '</span></div>';
        }
        if (room.facilities) {
            html += '<div class="admin-modal-field"><span class="admin-modal-label">Facilities</span><span class="admin-modal-value">' + escapeHtml(room.facilities) + '</span></div>';
        }
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Status</span><span class="admin-modal-value"><span class="admin-badge ' + statusClass + '">' + escapeHtml(room.status.charAt(0).toUpperCase() + room.status.slice(1)) + '</span></span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Created</span><span class="admin-modal-value">' + escapeHtml(room.created_at) + '</span></div>';

        document.getElementById('room-modal-body').innerHTML = html;
        document.getElementById('room-modal').classList.add('open');
    }

    function closeRoomModal() {
        document.getElementById('room-modal').classList.remove('open');
    }

    // ─── Create Room Modal ────────────────────────────────
    function openCreateModal() {
        document.getElementById('create-modal').classList.add('open');
    }

    function closeCreateModal() {
        document.getElementById('create-modal').classList.remove('open');
    }

    // ─── Edit Room Modal ──────────────────────────────────
    function openEditModal(room) {
        document.getElementById('edit-room-id').value = room.id;
        document.getElementById('edit-owner-id').value = room.owner_id;
        document.getElementById('edit-title').value = room.title;
        document.getElementById('edit-description').value = room.description || '';
        document.getElementById('edit-location').value = room.location;
        document.getElementById('edit-price').value = room.price;
        document.getElementById('edit-room-type').value = room.room_type;
        document.getElementById('edit-facilities').value = room.facilities || '';
        document.getElementById('edit-status').value = room.status;

        var preview = document.getElementById('edit-image-preview');
        if (room.image) {
            preview.innerHTML = '<img src="<?= base_url('') ?>' + escapeHtml(room.image) + '" alt="Current image">';
            preview.style.display = 'block';
        } else {
            preview.innerHTML = '';
            preview.style.display = 'none';
        }

        document.getElementById('edit-image').value = '';
        document.getElementById('edit-modal').classList.add('open');
    }

    function closeEditModal() {
        document.getElementById('edit-modal').classList.remove('open');
    }

    // ─── Delete Confirmation ──────────────────────────────
    function confirmDeleteRoom(roomId, roomTitle) {
        if (confirm('Are you sure you want to delete room "' + roomTitle + '"?\n\nThis will also remove all related bookings, bookmarks, and messages. This action cannot be undone.')) {
            document.getElementById('delete-room-id').value = roomId;
            document.getElementById('delete-room-form').submit();
        }
    }

    // ─── Close modals on overlay click / Escape ───────────
    ['room-modal', 'create-modal', 'edit-modal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('room-modal').classList.remove('open');
            document.getElementById('create-modal').classList.remove('open');
            document.getElementById('edit-modal').classList.remove('open');
        }
    });

    <?php if ($oldEdit !== null): ?>
    (function() {
        var editData = <?= json_encode($oldEdit) ?>;
        document.getElementById('edit-room-id').value = editData.id;
        document.getElementById('edit-owner-id').value = editData.owner_id;
        document.getElementById('edit-title').value = editData.title;
        document.getElementById('edit-description').value = editData.description;
        document.getElementById('edit-location').value = editData.location;
        document.getElementById('edit-price').value = editData.price;
        document.getElementById('edit-room-type').value = editData.room_type;
        document.getElementById('edit-facilities').value = editData.facilities;
        document.getElementById('edit-status').value = editData.status;
        document.getElementById('edit-modal').classList.add('open');
    })();
    <?php endif; ?>
    </script>

</body>

</html>
