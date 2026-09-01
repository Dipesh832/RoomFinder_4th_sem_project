<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = $_SESSION['user']['id'] ?? 0;

/*
 * Fetch all bookings for rooms owned by the logged-in owner.
 *
 * The bookings table does NOT contain owner_id.
 * The relationship is: bookings.room_id -> rooms.id -> rooms.owner_id.
 * We enforce ownership server-side via rooms.owner_id = logged-in owner ID.
 */
$stmt = $conn->prepare("
    SELECT
        bookings.id,
        bookings.status,
        bookings.booking_date,
        bookings.created_at,

        rooms.id AS room_id,
        rooms.title,
        rooms.location,
        rooms.price,
        rooms.room_type,
        rooms.image,

        users.id AS tenant_id,
        users.name AS tenant_name,
        users.email AS tenant_email,
        users.phone AS tenant_phone

    FROM bookings

    INNER JOIN rooms
        ON bookings.room_id = rooms.id

    INNER JOIN users
        ON bookings.tenant_id = users.id

    WHERE rooms.owner_id = ?

    ORDER BY bookings.created_at DESC
");

$stmt->bind_param("i", $ownerId);
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

    <title>Booking Requests | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="owner-bookings-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">Booking Requests</h1>
                    <p class="rooms-subtitle">
                        Manage booking requests for your rooms.
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

                    <h2 class="rooms-empty-title">No booking requests</h2>

                    <p class="rooms-empty-text">
                        There are currently no booking requests for your rooms.
                    </p>

                </div>

            <?php else: ?>

                <div class="bookings-list">

                    <?php foreach ($bookings as $booking): ?>

                        <article class="booking-request-card">

                            <div class="booking-request-room">

                                <?php if (!empty($booking['image'])): ?>

                                    <img
                                        src="<?= htmlspecialchars(base_url($booking['image'])) ?>"
                                        alt="<?= htmlspecialchars($booking['title']) ?>"
                                        class="booking-request-image"
                                    >

                                <?php else: ?>

                                    <div class="booking-request-image booking-request-placeholder">
                                        No Image
                                    </div>

                                <?php endif; ?>

                                <div class="booking-request-room-info">

                                    <h3 class="booking-request-title">
                                        <?= htmlspecialchars($booking['title']) ?>
                                    </h3>

                                    <p class="booking-request-location">
                                        <?= htmlspecialchars($booking['location']) ?>
                                    </p>

                                    <div class="booking-request-price">
                                        Rs. <?= number_format((float) $booking['price'], 2) ?>
                                        <span>/ month</span>
                                    </div>

                                    <p class="booking-request-type">
                                        <?= htmlspecialchars($booking['room_type']) ?>
                                    </p>

                                </div>

                            </div>


                            <div class="booking-request-details">

                                <div class="booking-request-tenant">

                                    <h4 class="booking-request-section-title">Tenant</h4>

                                    <div class="booking-request-tenant-info">

                                        <p class="booking-request-tenant-name">
                                            <?= htmlspecialchars($booking['tenant_name']) ?>
                                        </p>

                                        <p class="booking-request-tenant-email">
                                            <?= htmlspecialchars($booking['tenant_email']) ?>
                                        </p>

                                        <p class="booking-request-tenant-phone">
                                            <?= htmlspecialchars($booking['tenant_phone']) ?>
                                        </p>

                                    </div>

                                </div>


                                <div class="booking-request-meta">

                                    <h4 class="booking-request-section-title">Dates</h4>

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


                                <div class="booking-request-status">

                                    <h4 class="booking-request-section-title">Status</h4>

                                    <span class="booking-status <?= htmlspecialchars($booking['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($booking['status'])) ?>
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
