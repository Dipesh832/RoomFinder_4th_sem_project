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
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="owner-page">

        <section class="rooms-section">

            <div class="section-header">

                <div>
                    <h1>My Rooms</h1>
                    <p>Manage the rooms you have listed on RoomFinder.</p>
                </div>

                <a href="add-room.php" class="btn btn-primary">
                    + Add Room
                </a>

            </div>


            <?php if (empty($rooms)): ?>

                <div class="empty-state">

                    <h2>No rooms listed yet</h2>

                    <p>
                        You haven't added any rooms yet.
                        Start by adding your first room.
                    </p>

                    <a href="add-room.php" class="btn btn-primary">
                        + Add Your First Room
                    </a>

                </div>

            <?php else: ?>

                <div class="rooms-grid">

                    <?php foreach ($rooms as $room): ?>

                        <article class="room-card">

                            <?php if (!empty($room['image'])): ?>

                                <img
                                    src="<?= htmlspecialchars($room['image']) ?>"
                                    alt="<?= htmlspecialchars($room['title']) ?>"
                                    class="room-card-image"
                                >

                            <?php else: ?>

                                <div class="room-card-image room-card-image-placeholder">
                                    No Image
                                </div>

                            <?php endif; ?>


                            <div class="room-card-content">

                                <div class="room-card-top">

                                    <h2>
                                        <?= htmlspecialchars($room['title']) ?>
                                    </h2>

                                    <span class="room-status <?= $room['status'] === 'available' ? 'available' : 'booked' ?>">
                                        <?= htmlspecialchars(ucfirst($room['status'])) ?>
                                    </span>

                                </div>


                                <p class="room-location">
                                    <?= htmlspecialchars($room['location']) ?>
                                </p>


                                <p class="room-type">
                                    <?= htmlspecialchars($room['room_type']) ?>
                                </p>


                                <div class="room-price">
                                    Rs. <?= number_format((float) $room['price'], 2) ?>
                                    <span>/ month</span>
                                </div>


                                <p class="room-description">
                                    <?= htmlspecialchars($room['description']) ?>
                                </p>


                                <div class="room-card-actions">

                                    <a
                                        href="view-room.php?id=<?= (int) $room['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="edit-room.php?id=<?= (int) $room['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        Edit
                                    </a>

                                    <button
                                        type="button"
                                        class="btn btn-danger"
                                        onclick="confirmDelete(<?= (int) $room['id'] ?>)"
                                    >
                                        Delete
                                    </button>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <script>
        function confirmDelete(roomId) {
            const confirmed = confirm(
                'Are you sure you want to delete this room?'
            );

            if (confirmed) {
                window.location.href = 'delete-room.php?id=' + roomId;
            }
        }
    </script>

</body>
</html>