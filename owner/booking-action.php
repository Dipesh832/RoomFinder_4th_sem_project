<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("owner/bookings");
}

$ownerId = $_SESSION['user']['id'];

$bookingId = trim($_POST['booking_id'] ?? '');
$action    = trim($_POST['action'] ?? '');

if ($bookingId === '' || !is_numeric($bookingId) || (int) $bookingId <= 0) {
    $_SESSION['error'] = "Invalid booking ID.";
    redirect("owner/bookings");
}

$bookingId = (int) $bookingId;

if ($action !== 'approve' && $action !== 'reject') {
    $_SESSION['error'] = "Invalid action.";
    redirect("owner/bookings");
}

/*
 * Lock the booking row and verify ownership through rooms.owner_id.
 * Using FOR UPDATE to prevent race conditions between concurrent approvals.
 */
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT bookings.id, bookings.status, bookings.room_id,
               rooms.owner_id, rooms.status AS room_status
        FROM bookings
        INNER JOIN rooms ON bookings.room_id = rooms.id
        WHERE bookings.id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    if ((int) $booking['owner_id'] !== (int) $ownerId) {
        throw new Exception("You do not have permission to modify this booking.");
    }

    if ($booking['status'] !== 'pending') {
        throw new Exception("This booking has already been " . $booking['status'] . ".");
    }

    if ($action === 'approve') {
        if ($booking['room_status'] !== 'available') {
            throw new Exception("This room is no longer available.");
        }

        /* Lock the room row to prevent concurrent approvals. */
        $stmt = $conn->prepare("
            SELECT id, status
            FROM rooms
            WHERE id = ? AND owner_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $booking['room_id'], $ownerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        $stmt->close();

        if (!$room || $room['status'] !== 'available') {
            throw new Exception("This room is no longer available.");
        }

        /* Approve the selected booking. */
        $stmt = $conn->prepare("
            UPDATE bookings SET status = 'approved' WHERE id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

        /* Mark the room as booked. */
        $stmt = $conn->prepare("
            UPDATE rooms SET status = 'booked' WHERE id = ?
        ");
        $stmt->bind_param("i", $booking['room_id']);
        $stmt->execute();
        $stmt->close();

        /* Reject all other pending bookings for the same room. */
        $stmt = $conn->prepare("
            UPDATE bookings SET status = 'rejected'
            WHERE room_id = ? AND status = 'pending' AND id != ?
        ");
        $stmt->bind_param("ii", $booking['room_id'], $bookingId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['success'] = "Booking approved successfully.";
    } else {
        /* Reject: update only this booking. */
        $stmt = $conn->prepare("
            UPDATE bookings SET status = 'rejected' WHERE id = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['success'] = "Booking rejected.";
    }
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

redirect("owner/bookings");
