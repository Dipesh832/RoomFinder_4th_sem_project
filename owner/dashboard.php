<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$userName = $_SESSION['user']['name'] ?? 'Owner';
$ownerId = $_SESSION['user']['id'] ?? 0;


/*
 * Fetch active rooms count
 */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS active_rooms
    FROM rooms
    WHERE owner_id = ?
");

$stmt->bind_param("i", $ownerId);
$stmt->execute();

$result = $stmt->get_result();
$activeRooms = (int) ($result->fetch_assoc()['active_rooms'] ?? 0);

$stmt->close();


/*
 * Fetch pending booking requests
 *
 * bookings does not contain owner_id.
 * We connect bookings -> rooms using room_id.
 */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS pending_requests
    FROM bookings
    INNER JOIN rooms
        ON bookings.room_id = rooms.id
    WHERE rooms.owner_id = ?
      AND bookings.status = 'pending'
");

$stmt->bind_param("i", $ownerId);
$stmt->execute();

$result = $stmt->get_result();
$pendingRequests = (int) ($result->fetch_assoc()['pending_requests'] ?? 0);

$stmt->close();


/*
 * Fetch rooms for "My Rooms" section
 */

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        description,
        location,
        price,
        room_type,
        image,
        status,
        created_at
    FROM rooms
    WHERE owner_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $ownerId);
$stmt->execute();

$result = $stmt->get_result();
$rooms = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Owner Dashboard | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">

</head>

<body>

    <?php include '../includes/navbar.php'; ?>


    <!-- ========================================
         OWNER DASHBOARD
    ========================================= -->

    <main class="owner-dashboard">

        <!-- HERO SECTION -->

        <section class="owner-hero">

            <div class="owner-hero-container">

                <!-- Welcome Badge -->

                <div class="owner-welcome">
                    Welcome back, <?= htmlspecialchars($userName) ?>
                </div>


                <!-- Main Heading -->

                <h1 class="owner-hero-title">
                    Manage your rooms,<br>
                    bookings, and <span>tenants</span>
                </h1>


                <!-- Description -->

                <p class="owner-hero-description">
                    Everything you need to manage your properties,
                    handle bookings, and connect with tenants.
                </p>


                <!-- Action Button -->

                <div class="owner-hero-action">

                    <a href="add-room.php" class="add-room-btn">

                        <svg class="add-room-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />

                            <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>

                        Add Room

                    </a>

                </div>

            </div>

        </section>


        <!-- ========================================
             YOUR ACTIVITY SECTION
        ========================================= -->

        <section class="activity-section">

            <div class="activity-container">

                <!-- Section Header -->

                <div class="activity-header">

                    <h2 class="activity-title">
                        Your Activity
                    </h2>

                    <p class="activity-subtitle">
                        Quick overview of your recent activity
                    </p>

                </div>


                <!-- Activity Cards -->

                <div class="activity-grid">


                    <!-- ========================================
                         ACTIVE ROOMS
                    ========================================= -->

                    <a href="rooms.php" class="activity-card">

                        <div class="activity-icon">

                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                                <path d="M3 10.5L12 3L21 10.5" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />

                                <path d="M5 9.5V20H19V9.5" stroke="currentColor" stroke-width="1.8"
                                    stroke-linejoin="round" />

                                <path d="M9 20V14H15V20" fill="currentColor" />

                            </svg>

                        </div>


                        <div class="activity-content">

                            <span class="activity-number">
                                <?= $activeRooms ?>
                            </span>

                            <span class="activity-label">
                                Active Rooms
                            </span>

                        </div>


                        <div class="activity-arrow">

                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                                <path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />

                            </svg>

                        </div>

                    </a>



                    <!-- ========================================
                         BOOKING REQUESTS
                    ========================================= -->

                    <a href="bookings.php" class="activity-card">

                        <div class="activity-icon">

                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                                <rect x="3.5" y="5" width="17" height="16" rx="2" stroke="currentColor"
                                    stroke-width="1.8" />

                                <path d="M7 3V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                                <path d="M17 3V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                                <path d="M3.5 10H20.5" stroke="currentColor" stroke-width="1.8" />

                            </svg>

                        </div>


                        <div class="activity-content">

                            <span class="activity-number">
                                <?= $pendingRequests ?>
                            </span>

                            <span class="activity-label">
                                Booking Requests
                            </span>

                        </div>


                        <div class="activity-arrow">

                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                                <path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />

                            </svg>

                        </div>

                    </a>



                    <!-- ========================================
                         NEW MESSAGE
                    ========================================= -->

                    <a href="messages.php" class="activity-card">

                        <div class="activity-icon">

                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                                <path
                                    d="M20 11.5C20 16.194 16.194 20 11.5 20C9.95 20 8.5 19.59 7.25 18.87L4 20L5.13 16.75C4.41 15.5 4 14.05 4 12.5C4 7.806 7.806 4 12.5 4C17.194 4 20 7.806 20 11.5Z"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round" />

                                <circle cx="8" cy="12" r="1" fill="currentColor" />

                                <circle cx="12" cy="12" r="1" fill="currentColor" />

                                <circle cx="16" cy="12" r="1" fill="currentColor" />

                            </svg>

                        </div>


                        <div class="activity-content">

                            <span class="activity-number">
                                0
                            </span>

                            <span class="activity-label">
                                New Messages
                            </span>

                        </div>


                        <div class="activity-arrow">

                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                                <path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />

                            </svg>

                        </div>

                    </a>

                </div>

            </div>

        </section>


        <!-- ========================================
             MY ROOMS SECTION
        ========================================= -->

        <section class="my-rooms-section">

            <div class="my-rooms-container">

                <!-- Section Header -->

                <div class="my-rooms-header">

                    <h2 class="my-rooms-title">
                        My Rooms
                    </h2>

                    <p class="my-rooms-subtitle">
                        Rooms you have listed on RoomFinder
                    </p>

                </div>


                <?php if (empty($rooms)): ?>

                    <div class="my-rooms-empty">

                        <p>You haven't added any rooms yet.</p>

                    </div>

                <?php else: ?>

                    <!-- Room Cards -->

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

                                        <h3 class="room-card-title">
                                            <?= htmlspecialchars($room['title']) ?>
                                        </h3>

                                        <span class="room-status <?= $room['status'] === 'available' ? 'available' : 'booked' ?>">
                                            <?= htmlspecialchars(ucfirst($room['status'])) ?>
                                        </span>

                                    </div>

                                    <p class="room-card-location">
                                        <?= htmlspecialchars($room['location']) ?>
                                    </p>

                                    <div class="room-card-price">
                                        Rs. <?= number_format((float) $room['price'], 2) ?>
                                        <span>/ month</span>
                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <!-- View All Link -->

                <?php if (count($rooms) > 0): ?>

                    <div class="my-rooms-footer">

                        <a href="rooms.php" class="view-all-link">
                            View All My Rooms
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</body>

</html>
