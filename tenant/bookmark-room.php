<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

/*
 * Build the "back to Browse Rooms" target. Only the known search filters are
 * carried back, and only as query string values, so an arbitrary redirect
 * target can never be supplied by the client.
 */
$browseRoomsTarget = function () {
    $returnParams = [];

    foreach (['location', 'category', 'type', 'max_price'] as $filterKey) {
        $filterValue = $_POST[$filterKey] ?? '';

        if (is_string($filterValue) && trim($filterValue) !== '') {
            $returnParams[$filterKey] = $filterValue;
        }
    }

    return "tenant/rooms" . ($returnParams ? '?' . http_build_query($returnParams) : '');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    redirect("tenant/rooms");
}

if (!verify_csrf()) {
    $_SESSION['error'] = "Session expired. Please try again.";
    redirect($browseRoomsTarget());
}

$tenantId = $_SESSION['user']['id'];

$roomId = trim($_POST['room_id'] ?? '');

if ($roomId === '' || !is_numeric($roomId) || (int) $roomId <= 0) {
    $_SESSION['error'] = "Invalid room ID.";
    redirect($browseRoomsTarget());
}

$roomId = (int) $roomId;

$stmt = $conn->prepare("
    SELECT id
    FROM rooms
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    $_SESSION['error'] = "Room not found.";
    redirect($browseRoomsTarget());
}

$stmt = $conn->prepare("
    SELECT id
    FROM bookmarks
    WHERE user_id = ? AND room_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $tenantId, $roomId);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

if ($existing) {
    $stmt = $conn->prepare("
        DELETE FROM bookmarks
        WHERE user_id = ? AND room_id = ?
    ");
    $stmt->bind_param("ii", $tenantId, $roomId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Room removed from saved list.";
} else {
    /*
     * INSERT IGNORE lets the UNIQUE (user_id, room_id) constraint be the
     * real duplicate guard, so a repeated submit cannot create a second row.
     */
    $stmt = $conn->prepare("
        INSERT IGNORE INTO bookmarks (user_id, room_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $tenantId, $roomId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Room saved successfully.";
}

/*
 * Send the tenant back to the page they submitted from: the Saved list, the
 * Browse Rooms grid, or the room detail page.
 */
$redirectTarget = $_POST['redirect'] ?? '';
$redirectTarget = is_string($redirectTarget) ? $redirectTarget : '';

if ($redirectTarget === 'saved') {
    redirect("tenant/saved");
}

if ($redirectTarget === 'rooms') {
    redirect($browseRoomsTarget());
}

redirect("tenant/view-room?id=" . $roomId);
