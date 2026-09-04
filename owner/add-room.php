<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = $_SESSION['user']['id'] ?? 0;

$uploadDir = __DIR__ . '/../assets/uploads/rooms/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$errors = [
    'title'       => '',
    'description' => '',
    'location'    => '',
    'price'       => '',
    'room_type'   => '',
    'facilities'  => '',
    'image'       => '',
];

$old = [
    'title'       => '',
    'description' => '',
    'location'    => '',
    'price'       => '',
    'room_type'   => '',
    'facilities'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $roomType    = trim($_POST['room_type'] ?? '');
    $facilities  = trim($_POST['facilities'] ?? '');

    $old['title']       = $title;
    $old['description'] = $description;
    $old['location']    = $location;
    $old['price']       = $price;
    $old['room_type']   = $roomType;
    $old['facilities']  = $facilities;

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

    if (empty($roomType)) {
        $errors['room_type'] = "Room type is required";
    }

    $uploadedFilePath = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

        $file     = $_FILES['image'];
        $tmpName  = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileErr  = $file['error'];

        if ($fileErr !== UPLOAD_ERR_OK) {
            $errors['image'] = "File upload failed. Please try again.";
        } else {

            $maxSize = 5 * 1024 * 1024;
            if ($fileSize > $maxSize) {
                $errors['image'] = "Image must be 5 MB or less.";
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
                    $uploadedFilePath = 'assets/uploads/rooms/' . $uniqueName;
                } else {
                    $errors['image'] = "Failed to save the uploaded image.";
                }
            }
        }
    }

    if (!array_filter($errors)) {

        $sql = "INSERT INTO rooms
                (owner_id, title, description, location, price, room_type, facilities, image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        $facilitiesDb = $facilities !== '' ? $facilities : null;
        $imageDb       = $uploadedFilePath;
        $status        = 'available';

        mysqli_stmt_bind_param(
            $stmt,
            "isssdssss",
            $ownerId,
            $title,
            $description,
            $location,
            $price,
            $roomType,
            $facilitiesDb,
            $imageDb,
            $status
        );

        if (mysqli_stmt_execute($stmt)) {
            $stmt->close();
            $_SESSION['success'] = "Room added successfully.";
            redirect("owner/rooms");
        } else {
            $stmt->close();
            if ($uploadedFilePath !== null && file_exists(__DIR__ . '/../' . $uploadedFilePath)) {
                unlink(__DIR__ . '/../' . $uploadedFilePath);
            }
            $errors['title'] = "Something went wrong. Please try again.";
        }
    } else {
        if ($uploadedFilePath !== null && file_exists(__DIR__ . '/../' . $uploadedFilePath)) {
            unlink(__DIR__ . '/../' . $uploadedFilePath);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Room | RoomFinder</title>

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

                    <h1 class="add-room-heading">Add New Room</h1>

                    <p class="add-room-subtitle">
                        List a new room on RoomFinder.
                    </p>

                </div>


                <?= messages() ?>


                <form class="add-room-form" action="" method="POST" enctype="multipart/form-data" novalidate>


                    <!-- Room Title -->

                    <div class="form-group">

                        <label for="title">Room Title</label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            placeholder="e.g. Single Room in Balaju"
                            value="<?= htmlspecialchars($old['title']) ?>"
                            required
                        >

                        <?php if ($errors['title'] !== ''): ?>
                            <span class="error"><?= htmlspecialchars($errors['title']) ?></span>
                        <?php endif; ?>

                    </div>


                    <!-- Description -->

                    <div class="form-group">

                        <label for="description">Description</label>

                        <textarea
                            id="description"
                            name="description"
                            placeholder="Describe the room, amenities, surroundings..."
                            rows="5"
                            required
                        ><?= htmlspecialchars($old['description']) ?></textarea>

                        <?php if ($errors['description'] !== ''): ?>
                            <span class="error"><?= htmlspecialchars($errors['description']) ?></span>
                        <?php endif; ?>

                    </div>


                    <!-- Location -->

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


                    <!-- Price & Room Type (side by side) -->

                    <div class="form-row">

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

                        <div class="form-group">

                            <label for="room_type">Room Type</label>

                            <input
                                type="text"
                                id="room_type"
                                name="room_type"
                                placeholder="e.g. Single, Double, Studio"
                                value="<?= htmlspecialchars($old['room_type']) ?>"
                                required
                            >

                            <?php if ($errors['room_type'] !== ''): ?>
                                <span class="error"><?= htmlspecialchars($errors['room_type']) ?></span>
                            <?php endif; ?>

                        </div>

                    </div>


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


                    <!-- Room Image -->

                    <div class="form-group">

                        <label for="image">Room Image <span class="optional">(optional)</span></label>

                        <input
                            type="file"
                            id="image"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <span class="field-hint">JPG, PNG, or WEBP. Max 5 MB.</span>

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
                            Add Room
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>

</html>
