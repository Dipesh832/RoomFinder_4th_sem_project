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
    'title'         => '',
    'description'   => '',
    'location'      => '',
    'price'         => '',
    'room_type'     => '',
    'max_occupants' => '',
    'facilities'    => '',
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

    $uploadedFilePath = null;

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
                    $uploadedFilePath = 'assets/uploads/rooms/' . $uniqueName;
                } else {
                    $errors['image'] = "Failed to save the uploaded image.";
                }
            }
        }
    }

    if (!array_filter($errors)) {

        $sql = "INSERT INTO rooms
                (owner_id, title, description, location, price, room_type, max_occupants, facilities, image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        $facilitiesDb = $facilities !== ''
            ? implode(', ', array_values(array_filter(array_map('trim', preg_split('/[,|]/', $facilities)))))
            : null;
        $imageDb       = $uploadedFilePath;
        $status        = 'available';
        $maxOccupantsDb = (int) $maxOccupants;

        mysqli_stmt_bind_param(
            $stmt,
            "isssdsisss",
            $ownerId,
            $title,
            $description,
            $location,
            $price,
            $roomType,
            $maxOccupantsDb,
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

                <a href="rooms.php" class="add-room-back">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to My Rooms
                </a>


                <?= messages() ?>


                <form class="add-room-form" action="" method="POST" enctype="multipart/form-data" novalidate>

                    <h1 class="add-room-form-title">Add Property</h1>

                    <p class="add-room-form-subtitle">
                        Give tenants all the details they need to decide if this is the right room.
                    </p>

                    <!-- ========== Property Information ========== -->

                    <div class="add-room-section-box">

                        <h3 class="add-room-section-box-title">Property Information</h3>

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

                    </div>

                    <!-- ========== Property Details ========== -->

                    <div class="add-room-section-box">

                        <h3 class="add-room-section-box-title">Property Details</h3>

                        <div class="form-row">

                            <div class="form-group">

                                <label for="room_type">Room Type</label>

                                <select name="room_type" id="room_type" required>
                                    <option value="" <?= $old['room_type'] === '' ? 'selected' : '' ?> disabled>Select Room Type</option>
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>" <?= $old['room_type'] === $type ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

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

                    </div>

                    <!-- ========== Facilities ========== -->

                    <div class="add-room-section-box">

                        <h3 class="add-room-section-box-title">Facilities</h3>

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

                    </div>

                    <!-- ========== Property Image ========== -->

                    <div class="add-room-section-box">

                        <h3 class="add-room-section-box-title">Property Image</h3>

                        <div class="form-group">

                            <label for="image">Room Image <span class="optional">(optional)</span></label>

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

                    </div>

                    <!-- ========== Actions ========== -->

                    <div class="add-room-actions">

                        <a href="rooms.php" class="add-room-cancel">
                            Cancel
                        </a>

                        <button type="submit" class="add-room-submit">
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
