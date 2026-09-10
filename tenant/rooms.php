<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

$userName = $_SESSION['user']['name'] ?? 'Tenant';
$tenantId = $_SESSION['user']['id'] ?? 0;

/*
 * Fetch all currently available rooms.
 */
$stmt = $conn->prepare("
    SELECT
        id,
        title,
        description,
        location,
        price,
        room_type,
        facilities,
        image,
        status,
        created_at
    FROM rooms
    WHERE status = 'available'
    ORDER BY created_at DESC
");

$stmt->execute();

$result = $stmt->get_result();
$rooms = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

/*
 * Fetch the logged-in tenant's pending booking room IDs once, so each
 * room card can show the correct booking state without a per-room query.
 */
$pendingRoomIds = [];

if ($tenantId > 0) {
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
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Rooms | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/tenant.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="tenant-rooms-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">Browse Rooms</h1>
                    <p class="rooms-subtitle">Find available rooms listed on RoomFinder.</p>
                </div>

            </div>


            <?php if (empty($rooms)): ?>

                <div class="rooms-empty">

                    <div class="rooms-empty-icon">

                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 10.5L12 3L21 10.5" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M5 9.5V20H19V9.5" stroke="currentColor" stroke-width="1.8"
                                stroke-linejoin="round" />
                            <path d="M9 20V14H15V20" fill="currentColor" />
                        </svg>

                    </div>

                    <h2 class="rooms-empty-title">No rooms available</h2>

                    <p class="rooms-empty-text">
                        There are currently no available rooms to browse.
                    </p>

                </div>

            <?php else: ?>

                <div class="my-rooms-grid">

                    <?php foreach ($rooms as $room): ?>

                        <article class="room-card">

                            <?php if (!empty($room['image'])): ?>

                                <img
                                    src="<?= htmlspecialchars(base_url($room['image'])) ?>"
                                    alt="<?= htmlspecialchars($room['title']) ?>"
                                    class="room-card-image"
                                >

                            <?php else: ?>

                                <div class="room-card-image room-card-placeholder">
                                    No Image
                                </div>

                            <?php endif; ?>


                            <div class="room-card-content">

                                <div class="room-card-top">

                                    <h2 class="room-card-title">
                                        <?= htmlspecialchars($room['title']) ?>
                                    </h2>

                                    <span class="room-status available">
                                        Available
                                    </span>

                                </div>

                                <p class="room-card-location">
                                    <?= htmlspecialchars($room['location']) ?>
                                </p>

                                <div class="room-card-price">
                                    Rs. <?= number_format((float) $room['price'], 2) ?>
                                    <span>/ month</span>
                                </div>

                                <p class="room-card-type">
                                    <?= htmlspecialchars($room['room_type']) ?>
                                </p>

                                <?php
                                $tenantFacilitiesItems = array_values(
                                    array_filter(
                                        array_map('trim', preg_split('/[,|]/', $room['facilities'] ?? ''))
                                    )
                                );
                                ?>
                                <?php if (!empty($tenantFacilitiesItems)): ?>
                                    <p class="room-card-facilities">
                                        <?= htmlspecialchars(implode(' • ', $tenantFacilitiesItems)) ?>
                                    </p>
                                <?php endif; ?>

                                <p class="room-card-description">
                                    <?= htmlspecialchars($room['description']) ?>
                                </p>

                                <div class="room-card-actions">
                                    <a href="view-room.php?id=<?= (int) $room['id'] ?>" class="room-card-btn room-card-btn-view">
                                        View Details
                                    </a>
                                    <?php if (isset($pendingRoomIds[(int) $room['id']])): ?>
                                        <div class="room-card-btn room-card-btn-pending" role="status">
                                            Booking Requested
                                        </div>
                                    <?php else: ?>
                                        <form action="<?= base_url('tenant/book-room') ?>" method="POST" class="room-card-booking-form">
                                            <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
                                            <button type="submit" class="room-card-btn room-card-btn-booking">
                                                Request Booking
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
