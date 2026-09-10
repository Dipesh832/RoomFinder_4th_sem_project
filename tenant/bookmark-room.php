<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    redirect("tenant/rooms");
}

$tenantId = $_SESSION['user']['id'];

$roomId = trim($_POST['room_id'] ?? '');

if ($roomId === '' || !is_numeric($roomId) || (int) $roomId <= 0) {
    $_SESSION['error'] = "Invalid room ID.";
    redirect("tenant/rooms");
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
    redirect("tenant/rooms");
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
        WHERE id = ?
    ");
    $stmt->bind_param("i", $existing['id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Room removed from saved list.";
} else {
    $stmt = $conn->prepare("
        INSERT INTO bookmarks (user_id, room_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $tenantId, $roomId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Room saved successfully.";
}

if (($_POST['redirect'] ?? '') === 'saved') {
    redirect("tenant/saved");
} else {
    redirect("tenant/view-room?id=" . $roomId);
}
