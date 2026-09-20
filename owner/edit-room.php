<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = $_SESSION['user']['id'] ?? 0;

$roomId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($roomId <= 0) {
    redirect('owner/rooms');
}

/*
 * Fetch room and verify ownership.
 */
$stmt = $conn->prepare("
    SELECT id, title, description, location, price, room_type, max_occupants, facilities, image, status
    FROM rooms
    WHERE id = ? AND owner_id = ?
");
$stmt->bind_param("ii", $roomId, $ownerId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    $_SESSION['error'] = "Room not found or you do not have permission to edit it.";
    redirect('owner/rooms');
}

$uploadDir = __DIR__ . '/../assets/uploads/rooms/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$errors = [
    'title'         => '',
    'description'   => '',
    'location'      => '',
    'price'         => '',
    'room_type'     => '',
    'max_occupants' => '',
    'facilities'    => '',
    'image'         => '',
];

$old = [
    'title'         => $room['title'],
    'description'   => $room['description'],
    'location'      => $room['location'],
    'price'         => $room['price'],
    'room_type'     => $room['room_type'],
    'max_occupants' => $room['max_occupants'],
    'facilities'    => $room['facilities'] ?? '',
];

$roomTypes = [
    'Single Room',
    'Double Room',
    'Shared Room',
    '1RK',
    '1BHK',
    '2BHK',
    '3BHK',
    '2BK',
];

$legacyRoomTypeMap = [
    'single' => 'Single Room',
    'double' => 'Double Room',
    'shared' => 'Shared Room',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $roomType    = trim($_POST['room_type'] ?? '');
    $maxOccupants = trim($_POST['max_occupants'] ?? '');
    $facilities  = trim($_POST['facilities'] ?? '');

    $old['title']         = $title;
    $old['description']   = $description;
    $old['location']      = $location;
    $old['price']         = $price;
    $old['room_type']     = $roomType;
    $old['max_occupants'] = $maxOccupants;
    $old['facilities']    = $facilities;

    if (empty($title)) {
        $errors['title'] = "Room title is required";
    } elseif (strlen($title) > 150) {
        $errors['title'] = "Title must be 150 characters or less";
    }

    if (empty($description)) {
        $errors['description'] = "Description is required";
    }

    if (empty($location)) {
        $errors['location'] = "Location is required";
    }

    if (empty($price)) {
        $errors['price'] = "Price is required";
    } elseif (!is_numeric($price) || (float) $price <= 0) {
        $errors['price'] = "Price must be a number greater than 0";
    }

    if (!in_array($roomType, $roomTypes, true)) {
        $errors['room_type'] = "Please choose a valid room type.";
    }

    if ($maxOccupants === '' || filter_var($maxOccupants, FILTER_VALIDATE_INT) === false) {
        $errors['max_occupants'] = "Maximum occupants is required";
    } elseif ((int) $maxOccupants < 1 || (int) $maxOccupants > 20) {
        $errors['max_occupants'] = "Maximum occupants must be between 1 and 20";
    }

    $newImagePath = null;
    $deleteOldImage = false;

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

        $file     = $_FILES['image'];
        $tmpName  = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileErr  = $file['error'];

        if ($fileErr !== UPLOAD_ERR_OK) {
            $errors['image'] = "File upload failed. Please try again.";
        } else {

            $maxSize = 2 * 1024 * 1024;
            if ($fileSize > $maxSize) {
                $errors['image'] = "Image must be 2 MB or less.";
            }

            if ($errors['image'] === '') {

                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmpName);

                $allowedMimes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ];

                if (!in_array($mimeType, $allowedMimes, true)) {
                    $errors['image'] = "Only JPG, PNG, and WEBP images are allowed.";
                }
            }

            if ($errors['image'] === '') {

                $extMap = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                $ext        = $extMap[$mimeType];
                $uniqueName = 'room_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath   = $uploadDir . $uniqueName;

                if (move_uploaded_file($tmpName, $destPath)) {
                    $newImagePath   = 'assets/uploads/rooms/' . $uniqueName;
                    $deleteOldImage = true;
                } else {
                    $errors['image'] = "Failed to save the uploaded image.";
                }
            }
        }
    }

    if (!array_filter($errors)) {

        $finalImage = $room['image'];
        if ($newImagePath !== null) {
            $finalImage = $newImagePath;
        }

        $sql = "UPDATE rooms
                SET title = ?, description = ?, location = ?, price = ?,
                    room_type = ?, facilities = ?, image = ?, max_occupants = ?
                WHERE id = ? AND owner_id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        $facilitiesDb = $facilities !== ''
            ? implode(', ', array_values(array_filter(array_map('trim', preg_split('/[,|]/', $facilities)))))
            : null;
        $maxOccupantsDb = (int) $maxOccupants;

        mysqli_stmt_bind_param(
            $stmt,
            "sssdsssiii",
            $title,
            $description,
            $location,
            $price,
            $roomType,
            $facilitiesDb,
            $finalImage,
            $maxOccupantsDb,
            $roomId,
            $ownerId
        );

        if (mysqli_stmt_execute($stmt)) {
            $stmt->close();

            if ($deleteOldImage && $room['image'] && $room['image'] !== $finalImage) {
                $oldImagePath = __DIR__ . '/../' . $room['image'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $_SESSION['success'] = "Room updated successfully.";
            redirect('owner/rooms');
        } else {
            $stmt->close();
            if ($newImagePath !== null && file_exists($destPath)) {
                unlink($destPath);
            }
            $errors['title'] = "Something went wrong. Please try again.";
        }
    } else {
        if ($newImagePath !== null && file_exists($destPath)) {
            unlink($destPath);
        }
    }
}

$selectedRoomType = $old['room_type'];
$legacyRoomType = null;

if (!in_array($selectedRoomType, $roomTypes, true)) {
    if (isset($legacyRoomTypeMap[$selectedRoomType])) {
        $selectedRoomType = $legacyRoomTypeMap[$selectedRoomType];
    } else {
        $legacyRoomType = $old['room_type'];
        $selectedRoomType = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Room | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="owner-add-room-page">

        <section class="add-room-section">

            <div class="add-room-container">

                <div class="add-room-header">

                    <h1 class="add-room-heading">Edit Room</h1>

                    <p class="add-room-subtitle">
                        Update the details of your room listing.
                    </p>

                </div>


                <?= messages() ?>


                <form class="add-room-form" action="" method="POST" enctype="multipart/form-data" novalidate>

                    <h3 class="add-room-section-heading">Property Information</h3>

                    <!-- Title & Location (side by side) -->

                    <div class="form-row">

                        <div class="form-group">

                            <label for="title">Property Title</label>

                            <input
                                type="text"
                                id="title"
                                name="title"
                                placeholder="e.g. Cozy Single Room in Balaju"
                                value="<?= htmlspecialchars($old['title']) ?>"
                                required
                            >

                            <?php if ($errors['title'] !== ''): ?>
                                <span class="error"><?= htmlspecialchars($errors['title']) ?></span>
                            <?php endif; ?>

                        </div>

                        <div class="form-group">

                            <label for="location">Location</label>

                            <input
                                type="text"
                                id="location"
                                name="location"
                                placeholder="e.g. Balaju, Kathmandu"
                                value="<?= htmlspecialchars($old['location']) ?>"
                                required
                            >

                            <?php if ($errors['location'] !== ''): ?>
                                <span class="error"><?= htmlspecialchars($errors['location']) ?></span>
                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- Description -->

                    <div class="form-group">

                        <label for="description">Description</label>

                        <textarea
                            id="description"
                            name="description"
                            placeholder="Describe the room, amenities, surroundings..."
                            rows="4"
                            required
                        ><?= htmlspecialchars($old['description']) ?></textarea>

                        <?php if ($errors['description'] !== ''): ?>
                            <span class="error"><?= htmlspecialchars($errors['description']) ?></span>
                        <?php endif; ?>

                    </div>

                    <h3 class="add-room-section-heading">Property Details</h3>

                    <!-- Room Type & Price (side by side) -->

                    <div class="form-row">

                        <div class="form-group">

                            <label for="room_type">Room Type</label>

                            <?php if ($legacyRoomType !== null): ?>
                                <select name="room_type" id="room_type" required>
                                    <option value="" disabled selected>Current: <?= htmlspecialchars($legacyRoomType) ?></option>
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>">
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="field-hint">Your current Room Type is not in the standard list. Choose a valid option below to update it.</span>
                            <?php else: ?>
                                <select name="room_type" id="room_type" required>
                                    <option value="" <?= $selectedRoomType === '' ? 'selected' : '' ?> disabled>Select Room Type</option>
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>" <?= $selectedRoomType === $type ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <?php if ($errors['room_type'] !== ''): ?>
                                <span class="error"><?= htmlspecialchars($errors['room_type']) ?></span>
                            <?php endif; ?>

                        </div>

                        <div class="form-group">

                            <label for="price">Price (Rs. / month)</label>

                            <input
                                type="number"
                                id="price"
                                name="price"
                                placeholder="e.g. 8000"
                                min="0.01"
                                step="0.01"
                                value="<?= htmlspecialchars($old['price']) ?>"
                                required
                            >

                            <?php if ($errors['price'] !== ''): ?>
                                <span class="error"><?= htmlspecialchars($errors['price']) ?></span>
                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- Maximum Occupants -->

                    <div class="form-group">

                        <label for="max_occupants">Maximum Occupants</label>

                        <input
                            type="number"
                            id="max_occupants"
                            name="max_occupants"
                            placeholder="e.g. 5"
                            min="1"
                            max="20"
                            value="<?= htmlspecialchars($old['max_occupants']) ?>"
                            required
                        >

                        <span class="field-hint">Maximum number of people allowed to live here.</span>

                        <?php if ($errors['max_occupants'] !== ''): ?>
                            <span class="error"><?= htmlspecialchars($errors['max_occupants']) ?></span>
                        <?php endif; ?>

                    </div>

                    <h3 class="add-room-section-heading">Facilities</h3>

                    <!-- Facilities -->

                    <div class="form-group">

                        <label for="facilities">Facilities <span class="optional">(optional)</span></label>

                        <textarea
                            id="facilities"
                            name="facilities"
                            placeholder="e.g. WiFi, Attached Bathroom, Parking"
                            rows="3"
                        ><?= htmlspecialchars($old['facilities']) ?></textarea>

                        <?php if ($errors['facilities'] !== ''): ?>
                            <span class="error"><?= htmlspecialchars($errors['facilities']) ?></span>
                        <?php endif; ?>

                    </div>

                    <h3 class="add-room-section-heading">Property Image</h3>

                    <!-- Room Image -->

                    <div class="form-group">

                        <label for="image">Room Image <span class="optional">(optional)</span></label>

                        <?php if (!empty($room['image'])): ?>
                            <div class="current-image-preview">
                                <img
                                    src="<?= htmlspecialchars(base_url($room['image'])) ?>"
                                    alt="Current room image"
                                    class="current-image-thumb"
                                >
                                <span class="field-hint">Current image. Upload a new one to replace it.</span>
                            </div>
                        <?php endif; ?>

                        <input
                            type="file"
                            id="image"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <span class="field-hint">JPG, PNG, or WEBP. Max 2 MB.</span>

                        <?php if ($errors['image'] !== ''): ?>
                            <span class="error"><?= htmlspecialchars($errors['image']) ?></span>
                        <?php endif; ?>

                    </div>

                    <!-- Submit -->

                    <div class="form-actions">

                        <a href="rooms.php" class="btn-cancel">
                            Cancel
                        </a>

                        <button type="submit" class="add-room-btn">
                            Update Room
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>

</html>
