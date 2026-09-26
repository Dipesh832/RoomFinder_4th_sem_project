<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

$tenantId = $_SESSION['user']['id'];

/*
 * Fetch the rooms this tenant has saved, newest save first. The join is scoped
 * to the session user id, so a tenant can only ever see their own saves.
 *
 * There is deliberately no status filter here: Browse Rooms only lists
 * available rooms, but a room can be booked after it has been saved. Such a
 * room still belongs in this list, where the card shows a "Booked" badge and
 * hides the booking action instead of pretending the room is still available.
 */
$stmt = $conn->prepare("
    SELECT
        rooms.id,
        rooms.title,
        rooms.description,
        rooms.location,
        rooms.price,
        rooms.room_type,
        rooms.facilities,
        rooms.image,
        rooms.status,
        rooms.max_occupants
    FROM bookmarks
    INNER JOIN rooms
        ON rooms.id = bookmarks.room_id
    WHERE bookmarks.user_id = ?
    ORDER BY bookmarks.created_at DESC
");

$stmt->bind_param("i", $tenantId);
$stmt->execute();

$result = $stmt->get_result();
$rooms = $result->fetch_all(MYSQLI_ASSOC);
$roomCount = count($rooms);

$stmt->close();

/*
 * Pending booking room IDs, the same set tenant/rooms.php uses, so a saved
 * room the tenant already asked for shows "Booking Requested".
 */
$pendingRoomIds = [];

$stmt = $conn->prepare("
    SELECT room_id
    FROM bookings
    WHERE tenant_id = ?
      AND status = 'pending'
");

$stmt->bind_param("i", $tenantId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $pendingRoomIds[(int) $row['room_id']] = true;
}

$stmt->close();

/*
 * Every room on this page is saved by definition, so the saved state is derived
 * from the result set rather than re-queried. That keeps the shape of
 * $savedRoomIds identical to the other listing page, so the shared card does
 * not need to know which page it is rendering on.
 */
$savedRoomIds = [];

foreach ($rooms as $savedRoom) {
    $savedRoomIds[(int) $savedRoom['id']] = true;
}

/*
 * The heart returns the tenant to this list, and there are no filters to carry
 * back, so no extra hidden fields are passed to the card.
 */
$roomCardFavRedirect = 'saved';
$roomCardFavFields = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Saved Rooms | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/tenant.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="tenant-saved-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">Saved Rooms</h1>
                    <p class="rooms-subtitle">
                        Rooms you saved for later. Tap the heart to remove one.
                    </p>
                    <p class="rooms-result-count">
                        <?= $roomCount ?> <?= $roomCount === 1 ? 'room' : 'rooms' ?> saved
                    </p>
                </div>

            </div>

            <?php /*
             * Rendered before the results because bookmark-room.php reports the
             * outcome of a save/unsave through the session.
             */ ?>
            <?= messages() ?>

            <?php if (empty($rooms)): ?>

                <div class="rooms-empty">

                    <div class="rooms-empty-icon">

                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M12 21.4C10.9 20.3 2.1 14.9 2.1 9C2.1 5.5 4.7 2.8 7.9 2.8C9.8 2.8 11.3 3.8 12 5.2C12.7 3.8 14.2 2.8 16.1 2.8C19.3 2.8 21.9 5.5 21.9 9C21.9 14.9 13.1 20.3 12 21.4Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>

                    </div>

                    <h2 class="rooms-empty-title">No saved rooms yet</h2>

                    <p class="rooms-empty-text">
                        Tap the heart on any room to keep it here for later.
                    </p>

                    <a href="rooms.php" class="add-room-btn">
                        <svg class="add-room-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        Browse Rooms
                    </a>

                </div>

            <?php else: ?>

                <?php include __DIR__ . '/../includes/room_card.php'; ?>

            <?php endif; ?>

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
