<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = $_SESSION['user']['id'] ?? 0;
$roomId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($roomId <= 0) {
    header('Location: rooms.php');
    exit;
}

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
    WHERE id = ? AND owner_id = ?
");

$stmt->bind_param("ii", $roomId, $ownerId);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    header('Location: rooms.php');
    exit;
}

$facilitiesList = array_values(
    array_filter(
        array_map('trim', preg_split('/[,|]/', $room['facilities'] ?? ''))
    )
);

$pageCreated = date('F j, Y', strtotime($room['created_at']));
$pagePrice   = number_format((float) $room['price'], 2);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($room['title']) ?> | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="owner-view-room-page">

        <section class="view-room-section">

            <div class="view-room-header">
                <a href="rooms.php" class="view-room-back">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    Back to My Rooms
                </a>
                <h1 class="view-room-title"><?= htmlspecialchars($room['title']) ?></h1>
            </div>

            <div class="view-room-grid">

                <div class="view-room-main">

                    <?php if (!empty($room['image'])): ?>
                        <div class="view-room-image-wrap">
                            <img
                                src="<?= htmlspecialchars(base_url($room['image'])) ?>"
                                alt="<?= htmlspecialchars($room['title']) ?>"
                                class="view-room-image"
                            >
                        </div>
                    <?php else: ?>
                        <div class="view-room-image-wrap view-room-no-image">
                            No Image Available
                        </div>
                    <?php endif; ?>

                    <div class="view-room-details">

                        <div class="view-room-meta">
                            <span class="room-status <?= $room['status'] === 'available' ? 'available' : 'booked' ?>">
                                <?= htmlspecialchars(ucfirst($room['status'])) ?>
                            </span>
                            <span class="view-room-date">Listed <?= $pageCreated ?></span>
                        </div>

                        <div class="view-room-price">
                            Rs. <?= $pagePrice ?>
                            <span>/ month</span>
                        </div>

                        <div class="view-room-info-row">
                            <span class="view-room-info-label">Location</span>
                            <span class="view-room-info-value"><?= htmlspecialchars($room['location']) ?></span>
                        </div>

                        <div class="view-room-info-row">
                            <span class="view-room-info-label">Room Type</span>
                            <span class="view-room-info-value"><?= htmlspecialchars($room['room_type']) ?></span>
                        </div>

                        <?php if (!empty($room['description'])): ?>
                            <div class="view-room-info-block">
                                <span class="view-room-info-label">Description</span>
                                <p class="view-room-info-text"><?= nl2br(htmlspecialchars($room['description'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($facilitiesList)): ?>
                            <div class="view-room-info-block">
                                <span class="view-room-info-label">Facilities</span>
                                <div class="view-room-facilities">
                                    <?php foreach ($facilitiesList as $facility): ?>
                                        <span class="view-room-facility-tag"><?= htmlspecialchars($facility) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>

                <div class="view-room-sidebar">

                    <div class="view-room-actions-card">
                        <h3 class="view-room-actions-title">Actions</h3>
                        <a href="edit-room.php?id=<?= (int) $room['id'] ?>" class="view-room-action-btn view-room-action-edit">
                            Edit Room
                        </a>
                        <form action="delete-room.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this room?');">
                            <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
                            <button type="submit" class="view-room-action-btn view-room-action-delete">
                                Delete Room
                            </button>
                        </form>
                    </div>

                </div>

            </div>

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
