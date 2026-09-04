<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$userName = $_SESSION['user']['name'] ?? 'Owner';
$ownerId = $_SESSION['user']['id'] ?? 0;

/*
 * Fetch only rooms belonging to the logged-in owner.
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

    <title>My Rooms | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="owner-rooms-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">My Rooms</h1>
                    <p class="rooms-subtitle">Manage the rooms you have listed on RoomFinder.</p>
                </div>

                <a href="add-room.php" class="add-room-btn">
                    <svg class="add-room-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true">
                        <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    Add Room
                </a>

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

                    <h2 class="rooms-empty-title">No rooms listed yet</h2>

                    <p class="rooms-empty-text">
                        You haven't added any rooms yet.
                        Start by adding your first room.
                    </p>

                    <a href="add-room.php" class="add-room-btn">
                        <svg class="add-room-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        Add Your First Room
                    </a>

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

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
