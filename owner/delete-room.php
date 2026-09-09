<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = $_SESSION['user']['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('owner/rooms');
}

$roomId = isset($_POST['room_id']) ? (int) $_POST['room_id'] : 0;

if ($roomId <= 0) {
    $_SESSION['error'] = "Invalid room ID.";
    redirect('owner/rooms');
}

/*
 * Fetch room and verify ownership before deleting.
 */
$stmt = $conn->prepare("
    SELECT id, image
    FROM rooms
    WHERE id = ? AND owner_id = ?
");
$stmt->bind_param("ii", $roomId, $ownerId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    $_SESSION['error'] = "Room not found or you do not have permission to delete it.";
    redirect('owner/rooms');
}

/*
 * Delete the room record.
 */
$stmt = $conn->prepare("DELETE FROM rooms WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $roomId, $ownerId);

if ($stmt->execute()) {

    if (!empty($room['image'])) {
        $imagePath = __DIR__ . '/../' . $room['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $stmt->close();

    $_SESSION['success'] = "Room deleted successfully.";
} else {
    $stmt->close();
    $_SESSION['error'] = "Failed to delete room. Please try again.";
}

redirect('owner/rooms');
