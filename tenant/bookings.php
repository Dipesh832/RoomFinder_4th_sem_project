<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

$tenantId = $_SESSION['user']['id'];

/*
 * Fetch all bookings belonging to the logged-in tenant.
 */
$stmt = $conn->prepare("
    SELECT
        bookings.id,
        bookings.room_id,
        bookings.status,
        bookings.booking_date,
        bookings.created_at,
        rooms.title,
        rooms.location,
        rooms.price,
        rooms.room_type,
        rooms.image
    FROM bookings
    INNER JOIN rooms
        ON bookings.room_id = rooms.id
    WHERE bookings.tenant_id = ?
    ORDER BY bookings.created_at DESC
");

$stmt->bind_param("i", $tenantId);
$stmt->execute();

$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Bookings | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/tenant.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="tenant-bookings-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">My Bookings</h1>
                    <p class="rooms-subtitle">
                        View your booking requests and their current status.
                    </p>
                </div>

            </div>


            <?php if (empty($bookings)): ?>

                <div class="rooms-empty">

                    <div class="rooms-empty-icon">

                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3.5" y="5" width="17" height="16" rx="2" stroke="currentColor"
                                stroke-width="1.8" />
                            <path d="M7 3V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M17 3V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M3.5 10H20.5" stroke="currentColor" stroke-width="1.8" />
                        </svg>

                    </div>

                    <h2 class="rooms-empty-title">No bookings yet</h2>

                    <p class="rooms-empty-text">
                        You haven't submitted any booking requests yet.
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

                <div class="my-rooms-grid">

                    <?php foreach ($bookings as $booking): ?>

                        <article class="room-card">

                            <?php if (!empty($booking['image'])): ?>

                                <img
                                    src="<?= htmlspecialchars(base_url($booking['image'])) ?>"
                                    alt="<?= htmlspecialchars($booking['title']) ?>"
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
                                        <?= htmlspecialchars($booking['title']) ?>
                                    </h2>

                                    <span class="booking-status <?= htmlspecialchars($booking['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                    </span>

                                </div>

                                <p class="room-card-location">
                                    <?= htmlspecialchars($booking['location']) ?>
                                </p>

                                <div class="room-card-price">
                                    Rs. <?= number_format((float) $booking['price'], 2) ?>
                                    <span>/ month</span>
                                </div>

                                <p class="room-card-type">
                                    <?= htmlspecialchars($booking['room_type']) ?>
                                </p>

                                <div class="booking-dates">
                                    <span class="booking-date-item">
                                        <strong>Booked:</strong>
                                        <?= date('M j, Y', strtotime($booking['booking_date'])) ?>
                                    </span>
                                    <span class="booking-date-item">
                                        <strong>Requested:</strong>
                                        <?= date('M j, Y', strtotime($booking['created_at'])) ?>
                                    </span>
                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>
