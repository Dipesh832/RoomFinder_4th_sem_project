<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

/*
 * Accept POST requests only.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    redirect("tenant/rooms");
}

$tenantId = $_SESSION['user']['id'];

/*
 * Validate room_id input.
 */
$roomId = trim($_POST['room_id'] ?? '');

if ($roomId === '' || !is_numeric($roomId) || (int) $roomId <= 0) {
    $_SESSION['error'] = "Invalid room ID.";
    redirect("tenant/rooms");
}

$roomId = (int) $roomId;

/*
 * Verify the room exists and is available.
 * Also retrieve owner_id to prevent self-booking.
 */
$stmt = $conn->prepare("
    SELECT id, owner_id, status
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

if ($room['status'] !== 'available') {
    $_SESSION['error'] = "This room is no longer available.";
    redirect("tenant/rooms");
}

if ((int) $room['owner_id'] === $tenantId) {
    $_SESSION['error'] = "You cannot book your own room.";
    redirect("tenant/rooms");
}

/*
 * Prevent duplicate pending requests.
 */
$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE room_id = ?
      AND tenant_id = ?
      AND status = 'pending'
    LIMIT 1
");

$stmt->bind_param("ii", $roomId, $tenantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    $_SESSION['error'] = "You already have a pending booking request for this room.";
    redirect("tenant/rooms");
}

$stmt->close();

/*
 * Insert the booking request.
 */
$stmt = $conn->prepare("
    INSERT INTO bookings
        (room_id, tenant_id, status, booking_date)
    VALUES
        (?, ?, 'pending', CURDATE())
");

$stmt->bind_param("ii", $roomId, $tenantId);

if ($stmt->execute()) {
    $stmt->close();
    $_SESSION['success'] = "Booking request submitted. Awaiting owner approval.";
    redirect("tenant/bookings");
} else {
    $stmt->close();
    $_SESSION['error'] = "Something went wrong. Please try again.";
    redirect("tenant/rooms");
}
