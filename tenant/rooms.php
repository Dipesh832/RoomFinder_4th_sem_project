<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

$userName = $_SESSION['user']['name'] ?? 'Tenant';
$tenantId = $_SESSION['user']['id'] ?? 0;

/*
 * Shared search/filter GET parameter sanitization (see
 * includes/room_search_prepare.php). This page consumes the parameters
 * for the filtered results.
 */
require_once __DIR__ . '/../includes/room_search_prepare.php';

$conditions = ["status = 'available'"];
$params = [];
$bindTypes = '';

if ($searchLocation !== '') {
    $conditions[] = 'location LIKE ?';
    $params[] = '%' . $searchLocation . '%';
    $bindTypes .= 's';
}

if ($searchCategory !== '') {
    $conditions[] = 'category = ?';
    $params[] = $searchCategory;
    $bindTypes .= 's';
}

if ($searchType !== '') {
    $conditions[] = 'room_type = ?';
    $params[] = $searchType;
    $bindTypes .= 's';
}

if ($searchMaxPrice !== '') {
    $conditions[] = 'price <= ?';
    $params[] = (float) $searchMaxPrice;
    $bindTypes .= 'd';
}

$whereClause = implode(' AND ', $conditions);

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
        max_occupants,
        created_at
    FROM rooms
    WHERE {$whereClause}
    ORDER BY created_at DESC
");

if ($bindTypes !== '') {
    $stmt->bind_param($bindTypes, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();
$rooms = $result->fetch_all(MYSQLI_ASSOC);
$roomCount = count($rooms);
$hasActiveFilters = $searchLocation !== ''
    || $searchCategory !== ''
    || $searchType !== ''
    || $searchMaxPrice !== '';

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

/*
 * Fetch the logged-in tenant's saved room IDs once, so each room card can
 * render the correct favourite state without a per-room query. The saved
 * state is read from the database only, never from client-side input.
 */
$savedRoomIds = [];

if ($tenantId > 0) {
    $stmt = $conn->prepare("
        SELECT room_id
        FROM bookmarks
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $savedRoomIds[(int) $row['room_id']] = true;
    }

    $stmt->close();
}

/*
 * Carry the active filters through the save/unsave round trip so the tenant
 * lands back on the same result list. Only the known search parameters are
 * echoed, and the values are the already-sanitized ones from
 * includes/room_search_prepare.php. The card form repeats them as individual
 * hidden fields, which bookmark-room.php allowlists on the way back.
 */
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

            <div class="rooms-header rooms-header-with-filter">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">Browse Rooms</h1>
                    <p class="rooms-subtitle">Find available rooms listed on RoomFinder.</p>
                    <p class="rooms-result-count">
                        <?= $roomCount ?> <?= $roomCount === 1 ? 'room' : 'rooms' ?> found
                    </p>
                </div>

            </div>

            <?= messages() ?>

            <div class="tenant-search-panel tenant-rooms-filter-panel">
                <form class="tenant-search-form tenant-rooms-filter-form" action="<?= htmlspecialchars($searchFormAction) ?>" method="GET"
                    aria-label="Search rooms" novalidate>

                    <div class="tenant-search-field tenant-search-location">
                        <label for="browse-search-location">Location</label>
                        <input type="text" id="browse-search-location" name="location" placeholder="Search by location"
                            maxlength="255" value="<?= htmlspecialchars($searchLocation) ?>">
                    </div>

                    <div class="tenant-search-field tenant-search-category">
                        <label for="browse-search-category">Category</label>
                        <select id="browse-search-category" name="category">
                            <option value="" <?= $searchCategory === '' ? 'selected' : '' ?>>All Categories</option>
                            <?php foreach ($searchCategories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= $searchCategory === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tenant-search-field tenant-search-type">
                        <label for="browse-search-type">Type</label>
                        <select id="browse-search-type" name="type">
                            <option value="" <?= $searchType === '' ? 'selected' : '' ?>>All Types</option>
                            <?php foreach ($searchAllTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $searchType === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tenant-search-field tenant-search-price">
                        <label for="browse-search-max-price">Maximum Price</label>
                        <input type="number" id="browse-search-max-price" name="max_price" placeholder="e.g. 15000" min="0.01"
                            step="0.01" inputmode="decimal" value="<?= htmlspecialchars($searchMaxPrice) ?>">
                    </div>

                    <div class="tenant-search-action">
                        <button type="submit" class="tenant-search-btn">
                            <svg class="tenant-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                                <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" />
                            </svg>
                            Search
                        </button>
                    </div>

                </form>

                <div class="tenant-search-clear">
                    <a href="<?= htmlspecialchars($clearFiltersUrl) ?>" class="tenant-search-clear-link">
                        <svg class="tenant-search-clear-icon" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                            <path d="M9 9L15 15M15 9L9 15" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" />
                        </svg>
                        Clear Filters
                    </a>
                </div>
            </div>

            <script>
                (function () {

                    var categorySelect = document.getElementById('browse-search-category');
                    var typeSelect = document.getElementById('browse-search-type');

                    var typeMaps = <?= json_encode($searchTypesByCategory) ?>;
                    var allTypes = <?= json_encode($searchAllTypes) ?>;

                    function typesFor(category) {
                        return Object.prototype.hasOwnProperty.call(typeMaps, category)
                            ? typeMaps[category]
                            : allTypes;
                    }

                    function buildTypeOptions(category) {
                        var types = typesFor(category);

                        typeSelect.innerHTML = '';

                        var allTypesOption = document.createElement('option');
                        allTypesOption.value = '';
                        allTypesOption.textContent = 'All Types';
                        typeSelect.appendChild(allTypesOption);

                        types.forEach(function (type) {
                            var option = document.createElement('option');
                            option.value = type;
                            option.textContent = type;
                            typeSelect.appendChild(option);
                        });
                    }

                    function onCategoryChange() {
                        var previousType = typeSelect.value;
                        var category = categorySelect.value;

                        buildTypeOptions(category);

                        if (previousType !== '' && typesFor(category).indexOf(previousType) !== -1) {
                            typeSelect.value = previousType;
                        }
                    }

                    categorySelect.addEventListener('change', onCategoryChange);

                    var initialCategory = <?= json_encode($searchCategory) ?>;
                    var initialType = <?= json_encode($searchType) ?>;

                    buildTypeOptions(initialCategory);

                    if (initialType !== '' && typesFor(initialCategory).indexOf(initialType) !== -1) {
                        typeSelect.value = initialType;
                    }
                })();
            </script>

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

                    <h2 class="rooms-empty-title">
                        <?= $hasActiveFilters ? 'No rooms match your search.' : 'No rooms available at the moment.' ?>
                    </h2>

                    <p class="rooms-empty-text">
                        <?php if ($hasActiveFilters): ?>
                            Try changing your location, category, type, or maximum price.
                        <?php else: ?>
                            There are currently no available rooms to browse.
                        <?php endif; ?>
                    </p>

                </div>

            <?php else: ?>

                <div class="my-rooms-grid">

                    <?php foreach ($rooms as $room): ?>

                        <article class="room-card">

                            <div class="room-card-media">

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

                                <?php $isRoomSaved = isset($savedRoomIds[(int) $room['id']]); ?>

                                <div class="room-card-media-bar">

                                    <span class="room-status available">
                                        Available
                                    </span>

                                    <form action="bookmark-room.php" method="POST" class="room-card-fav-form">
                                        <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
                                        <input type="hidden" name="redirect" value="rooms">
                                        <input type="hidden" name="location"
                                            value="<?= htmlspecialchars($searchLocation) ?>">
                                        <input type="hidden" name="category"
                                            value="<?= htmlspecialchars($searchCategory) ?>">
                                        <input type="hidden" name="type"
                                            value="<?= htmlspecialchars($searchType) ?>">
                                        <input type="hidden" name="max_price"
                                            value="<?= htmlspecialchars($searchMaxPrice) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                            class="room-card-fav-btn <?= $isRoomSaved ? 'is-saved' : '' ?>"
                                            aria-pressed="<?= $isRoomSaved ? 'true' : 'false' ?>"
                                            aria-label="<?= $isRoomSaved ? 'Remove saved room' : 'Save room' ?>"
                                            title="<?= $isRoomSaved ? 'Remove saved room' : 'Save room' ?>">
                                            <?php /*
                                             * One heart geometry for both states: the same path is
                                             * filled when saved and left open when not, so the
                                             * icon never changes shape. currentColor keeps the
                                             * heart red on the white button and white on the
                                             * red one. The button carries the accessible
                                             * label, so the SVG itself is hidden from
                                             * assistive technology.
                                             */ ?>
                                            <svg class="room-card-fav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path
                                                    d="M12 21.4C10.9 20.3 2.1 14.9 2.1 9C2.1 5.5 4.7 2.8 7.9 2.8C9.8 2.8 11.3 3.8 12 5.2C12.7 3.8 14.2 2.8 16.1 2.8C19.3 2.8 21.9 5.5 21.9 9C21.9 14.9 13.1 20.3 12 21.4Z"
                                                    fill="<?= $isRoomSaved ? 'currentColor' : 'none' ?>"
                                                    stroke="currentColor"
                                                    stroke-width="2.2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </button>
                                    </form>

                                </div>

                            </div>


                            <div class="room-card-content">

                                <div class="room-card-top">

                                    <h2 class="room-card-title">
                                        <?= htmlspecialchars($room['title']) ?>
                                    </h2>

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

                                <p class="room-card-max-occupants">
                                    Maximum occupants: <?= (int) $room['max_occupants'] ?>
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
                                        <a href="view-room.php?id=<?= (int) $room['id'] ?>#booking-form" class="room-card-btn room-card-btn-booking">
                                            Request Booking
                                        </a>
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
