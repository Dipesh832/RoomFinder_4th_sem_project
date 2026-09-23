<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

/*
 * Accept POST requests only.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    redirect("tenant/rooms");
}

if (!verify_csrf()) {
    $_SESSION['error'] = "Session expired. Please try again.";
    redirect("tenant/rooms");
}

$tenantId = $_SESSION['user']['id'];

/*
 * Validate room_id input.
 */
$roomId = trim($_POST['room_id'] ?? '');

if ($roomId === '' || !is_numeric($roomId) || (int) $roomId <= 0) {
    $_SESSION['error'] = "Invalid room ID.";
    redirect("tenant/rooms");
}

$roomId = (int) $roomId;

/*
 * Helper: redirect back to the view-room detail page when applicable.
 * An optional URL fragment can be appended to land on the booking form.
 */
function roomRedirect($roomId, $path, $fragment = '') {
    $uri = "tenant/" . $path . "?id=" . $roomId;
    if ($fragment !== '') {
        $uri .= '#' . $fragment;
    }
    redirect($uri);
}

/*
 * Allowed values for the group relationship and each occupant's gender.
 */
$relationshipMap = [
    'self'       => 'Self',
    'family'     => 'Family',
    'friends'    => 'Friends',
    'couple'     => 'Couple',
    'relatives'  => 'Relatives',
    'colleagues' => 'Colleagues',
    'other'      => 'Other',
];

$genderMap = [
    'male'   => 'Male',
    'female' => 'Female',
    'other'  => 'Other',
];

/*
 * Nepal-friendly contact number check.
 * Allows digits, a leading +, and spaces/dashes/parentheses.
 * Must contain 7-15 digits and fit into VARCHAR(15) as submitted.
 */
function isValidContact($value) {
    if (strlen($value) > 15) {
        return false;
    }
    if (!preg_match('/^[0-9+\s\-()]+$/', $value)) {
        return false;
    }
    $digits = preg_replace('/[^0-9]/', '', $value);
    return strlen($digits) >= 7 && strlen($digits) <= 15;
}

/*
 * Predefined occupation categories. The tenant picks one of these; only
 * 'Other' carries free text supplied via the custom occupation field.
 */
$occupationCategories = [
    'Student',
    'Job/Employed',
    'Self-employed/Business',
    'Other',
];

/*
 * Free-text occupation check used when the tenant selects 'Other'.
 * Rejects empty input, strings over 100 characters, values with no
 * letters (pure numbers/symbols), and repeated single-character garbage.
 */
function isValidCustomOccupation($value) {
    if ($value === '') {
        return false;
    }
    if (strlen($value) > 100) {
        return false;
    }
    if (!preg_match('/[A-Za-z]/', $value)) {
        return false;
    }
    $letters = preg_replace('/[^A-Za-z]/', '', $value);
    if (strlen($letters) < 2) {
        return false;
    }
    if (preg_match('/^(.)\1*$/i', $letters)) {
        return false;
    }
    return true;
}

/*
 * Begin a transaction and lock the room row.
 * FOR UPDATE serialises this request against a concurrent owner
 * approval of the same room, so the availability check below cannot
 * act on a stale value. The DB-side UNIQUE index
 * (room_id, tenant_id, pending_flag) is the hard guarantee that a
 * tenant can have only one PENDING request for a given room.
 */
$conn->begin_transaction();

/*
 * Verify the room exists and is available.
 * Also retrieve owner_id to prevent self-booking.
 */
$stmt = $conn->prepare("
    SELECT id, owner_id, status, max_occupants
    FROM rooms
    WHERE id = ?
    LIMIT 1
    FOR UPDATE
");

$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    $conn->rollback();
    $_SESSION['error'] = "Room not found.";
    redirect("tenant/rooms");
}

if ($room['status'] !== 'available') {
    $conn->rollback();
    $_SESSION['error'] = "This room is no longer available.";
    roomRedirect($roomId, 'view-room');
}

if ((int) $room['owner_id'] === $tenantId) {
    $conn->rollback();
    $_SESSION['error'] = "You cannot book your own room.";
    roomRedirect($roomId, 'view-room');
}

/*
 * Friendly early check for a duplicate pending request.
 * The UNIQUE index is the real enforcement; this simply avoids
 * submitting an insert that the database would reject anyway.
 */
$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE room_id = ?
      AND tenant_id = ?
      AND status = 'pending'
    LIMIT 1
");

$stmt->bind_param("ii", $roomId, $tenantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    $conn->rollback();
    $_SESSION['error'] = "You already have a pending booking request for this room.";
    roomRedirect($roomId, 'view-room');
}

$stmt->close();

/*
 * Validate the group relationship.
 */
$relationship = trim($_POST['relationship'] ?? '');
$relationshipCanonical = $relationshipMap[strtolower($relationship)] ?? null;

if ($relationshipCanonical === null) {
    $conn->rollback();
    $_SESSION['error'] = "Please choose a valid relationship for the occupants.";
    roomRedirect($roomId, 'view-room', 'booking-form');
}

/*
 * Validate the relationship detail. Only "Other" carries a free-text
 * explanation; every other relationship stores NULL.
 */
$relationshipDetail = null;

if ($relationshipCanonical === 'Other') {
    $relationshipDetail = trim($_POST['relationship_detail'] ?? '');

    if ($relationshipDetail === '') {
        $conn->rollback();
        $_SESSION['booking_form_data'] = [
            'relationship'         => $relationship,
            'relationship_detail'  => $relationshipDetail,
            'number_of_people'     => trim($_POST['number_of_people'] ?? ''),
            'members'              => $_POST['members'] ?? [],
        ];
        $_SESSION['error'] = "Please specify what the relationship is.";
        roomRedirect($roomId, 'view-room', 'booking-form');
    }

    if (strlen($relationshipDetail) > 100) {
        $conn->rollback();
        $_SESSION['booking_form_data'] = [
            'relationship'         => $relationship,
            'relationship_detail'  => $relationshipDetail,
            'number_of_people'     => trim($_POST['number_of_people'] ?? ''),
            'members'              => $_POST['members'] ?? [],
        ];
        $_SESSION['error'] = "Relationship details must be 100 characters or fewer.";
        roomRedirect($roomId, 'view-room', 'booking-form');
    }
}

/*
 * Validate the declared number of people. The list of members actually
 * submitted is the final authority; this field is cross-checked below.
 */
$declaredNumber = trim($_POST['number_of_people'] ?? '');

if ($declaredNumber === '' || filter_var($declaredNumber, FILTER_VALIDATE_INT) === false) {
    $conn->rollback();
    $_SESSION['error'] = "Please enter the number of people who will live in the room.";
    roomRedirect($roomId, 'view-room', 'booking-form');
}

$declaredNumber = (int) $declaredNumber;

/*
 * The room's max_occupants (set by the owner, 1 - 20) is the hard limit.
 * This check runs on the server so a tenant cannot bypass the limit by
 * editing the HTML or bypassing the browser's max attribute.
 */
$roomMaxOccupants = max(1, (int) $room['max_occupants']);

if ($declaredNumber < 1) {
    $conn->rollback();
    $_SESSION['error'] = "Number of people must be at least 1.";
    roomRedirect($roomId, 'view-room', 'booking-form');
}

if ($declaredNumber > $roomMaxOccupants) {
    $conn->rollback();
    $_SESSION['error'] = "This property allows a maximum of " . $roomMaxOccupants . " occupants.";
    roomRedirect($roomId, 'view-room', 'booking-form');
}

$rawMembers = $_POST['members'] ?? null;

if (!is_array($rawMembers) || $rawMembers === []) {
    $conn->rollback();
    $_SESSION['error'] = "Please provide details for at least one occupant.";
    roomRedirect($roomId, 'view-room', 'booking-form');
}

if (count($rawMembers) !== $declaredNumber) {
    $conn->rollback();
    $_SESSION['error'] = "The number of people does not match the submitted occupant details.";
    roomRedirect($roomId, 'view-room', 'booking-form');
}

/*
 * Validate every submitted occupant. Nothing is saved until the whole
 * set is valid, and no incomplete occupant record is ever written.
 */
$errors = [];
$members = [];
$prefillMembers = [];

foreach ($rawMembers as $index => $raw) {
    $personNo = (int) $index + 1;

    if (!is_array($raw)) {
        $errors[] = "Occupant " . $personNo . " details are invalid.";
        continue;
    }

    $name    = trim((string) ($raw['name'] ?? ''));
    $gender  = trim((string) ($raw['gender'] ?? ''));
    $contact = trim((string) ($raw['contact_number'] ?? ''));
    $address = trim((string) ($raw['permanent_address'] ?? ''));
    $occupation       = trim((string) ($raw['occupation'] ?? ''));
    $occupationOther  = trim((string) ($raw['occupation_other'] ?? ''));

    $genderCanonical = $genderMap[strtolower($gender)] ?? null;
    $occupationCanonical = null;
    $personValid = true;

    $prefillMembers[] = [
        'name'              => $name,
        'gender'            => $gender,
        'contact_number'    => $contact,
        'occupation'        => $occupation,
        'occupation_other'  => $occupationOther,
        'permanent_address' => $address,
    ];

    if ($name === '') {
        $errors[] = "Name is required for Person " . $personNo . ".";
        $personValid = false;
    } elseif (strlen($name) > 100) {
        $errors[] = "Name for Person " . $personNo . " is too long.";
        $personValid = false;
    }

    if ($genderCanonical === null) {
        $errors[] = "Choose a valid gender for Person " . $personNo . ".";
        $personValid = false;
    }

    if ($contact === '') {
        $errors[] = "Contact number is required for Person " . $personNo . ".";
        $personValid = false;
    } elseif (!isValidContact($contact)) {
        $errors[] = "Contact number for Person " . $personNo . " is invalid.";
        $personValid = false;
    }

    if ($address === '') {
        $errors[] = "Permanent address is required for Person " . $personNo . ".";
        $personValid = false;
    } elseif (strlen($address) > 255) {
        $errors[] = "Permanent address for Person " . $personNo . " is too long.";
        $personValid = false;
    }

    if ($occupation === '') {
        $errors[] = "Occupation is required for Person " . $personNo . ".";
        $personValid = false;
    } elseif ($occupation === 'Other') {
        if ($occupationOther === '') {
            $errors[] = "Please specify the occupation for Person " . $personNo . ".";
            $personValid = false;
        } elseif (strlen($occupationOther) > 100) {
            $errors[] = "Occupation for Person " . $personNo . " must be 100 characters or fewer.";
            $personValid = false;
        } elseif (!isValidCustomOccupation($occupationOther)) {
            $errors[] = "Occupation for Person " . $personNo . " is invalid.";
            $personValid = false;
        } else {
            $occupationCanonical = $occupationOther;
        }
    } elseif (in_array($occupation, $occupationCategories, true)) {
        $occupationCanonical = $occupation;
    } else {
        $errors[] = "Please choose a valid occupation for Person " . $personNo . ".";
        $personValid = false;
    }

    if ($personValid) {
        $members[] = [
            'name'              => $name,
            'gender'            => $genderCanonical,
            'contact_number'    => $contact,
            'occupation'        => $occupationCanonical,
            'permanent_address' => $address,
        ];
    }
}

if ($errors !== []) {
    $conn->rollback();
    $_SESSION['booking_form_data'] = [
        'relationship'         => $relationship,
        'relationship_detail'  => $relationshipDetail,
        'number_of_people'     => count($rawMembers),
        'members'              => $prefillMembers,
    ];
    $_SESSION['error'] = implode(" ", $errors);
    roomRedirect($roomId, 'view-room', 'booking-form');
}

/*
 * Save the booking and every occupant in ONE transaction.
 * If any member insert fails the whole request is rolled back,
 * including the booking row itself.
 */
$duplicateKeyHit = false;

try {
    $stmt = $conn->prepare("
        INSERT INTO bookings
            (room_id, tenant_id, status, booking_date, relationship, relationship_detail)
        VALUES
            (?, ?, 'pending', CURDATE(), ?, ?)
    ");

    $stmt->bind_param("iiss", $roomId, $tenantId, $relationshipCanonical, $relationshipDetail);

    if (!$stmt->execute()) {
        $duplicateKeyHit = ($conn->errno === 1062);
        throw new Exception($stmt->error);
    }

    $bookingId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO booking_members
            (booking_id, name, gender, contact_number, occupation, permanent_address)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("isssss", $bid, $bName, $bGender, $bContact, $bOccupation, $bAddress);

    foreach ($members as $member) {
        $bid         = $bookingId;
        $bName       = $member['name'];
        $bGender     = $member['gender'];
        $bContact    = $member['contact_number'];
        $bOccupation = $member['occupation'];
        $bAddress    = $member['permanent_address'];

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();

    $_SESSION['success'] = "Booking request submitted. Awaiting owner approval.";
    redirect("tenant/bookings");
} catch (Exception $e) {
    $conn->rollback();

    if ($duplicateKeyHit) {
        $_SESSION['error'] = "You already have a pending booking request for this room.";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }
    roomRedirect($roomId, 'view-room');
}
