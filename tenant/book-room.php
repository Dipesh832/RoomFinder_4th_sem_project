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
 * Helper: redirect back to the view-room detail page when applicable.
 */
function roomRedirect($roomId, $path) {
    redirect("tenant/" . $path . "?id=" . $roomId);
}

/*
 * Begin a transaction and lock the room row.
 * FOR UPDATE serialises this request against a concurrent owner
 * approval of the same room, so the availability check below cannot
 * act on a stale value. The DB-side UNIQUE index
 * (room_id, tenant_id, pending_flag) is the hard guarantee that a
 * tenant can have only one PENDING request for a given room.
 */
$conn->begin_transaction();

/*
 * Verify the room exists and is available.
 * Also retrieve owner_id to prevent self-booking.
 */
$stmt = $conn->prepare("
    SELECT id, owner_id, status
    FROM rooms
    WHERE id = ?
    LIMIT 1
    FOR UPDATE
");

$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    $conn->rollback();
    $_SESSION['error'] = "Room not found.";
    redirect("tenant/rooms");
}

if ($room['status'] !== 'available') {
    $conn->rollback();
    $_SESSION['error'] = "This room is no longer available.";
    roomRedirect($roomId, 'view-room');
}

if ((int) $room['owner_id'] === $tenantId) {
    $conn->rollback();
    $_SESSION['error'] = "You cannot book your own room.";
    roomRedirect($roomId, 'view-room');
}

/*
 * Friendly early check for a duplicate pending request.
 * The UNIQUE index is the real enforcement; this simply avoids
 * submitting an insert that the database would reject anyway.
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
    $conn->rollback();
    $_SESSION['error'] = "You already have a pending booking request for this room.";
    roomRedirect($roomId, 'view-room');
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
    $conn->commit();
    $_SESSION['success'] = "Booking request submitted. Awaiting owner approval.";
    redirect("tenant/bookings");
} else {
    $isDuplicate = ($conn->errno === 1062);
    $stmt->close();
    $conn->rollback();

    if ($isDuplicate) {
        $_SESSION['error'] = "You already have a pending booking request for this room.";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }
    roomRedirect($roomId, 'view-room');
}
