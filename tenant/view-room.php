<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

$tenantId = $_SESSION['user']['id'] ?? 0;
$roomId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
        max_occupants,
        created_at
    FROM rooms
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    header('Location: rooms.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT id
    FROM bookmarks
    WHERE user_id = ? AND room_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $tenantId, $roomId);
$stmt->execute();
$result = $stmt->get_result();
$isBookmarked = $result->num_rows > 0;
$stmt->close();

$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE room_id = ? AND tenant_id = ? AND status = 'pending'
    LIMIT 1
");

$stmt->bind_param("ii", $roomId, $tenantId);
$stmt->execute();
$result = $stmt->get_result();
$hasPendingBooking = $result->num_rows > 0;
$stmt->close();

/*
 * If a previous submission failed server-side, book-room.php stashes the
 * entered data in the session. Restore it so the tenant does not have to
 * retype everything, then clear it.
 */
$roomMaxOccupants = max(1, (int) $room['max_occupants']);

$formNumber = 1;
$formRelationship = '';
$formRelationshipDetail = '';
$formMembers = [];
$showBookingForm = false;

if (isset($_SESSION['booking_form_data']) && is_array($_SESSION['booking_form_data'])) {
    $formData = $_SESSION['booking_form_data'];
    unset($_SESSION['booking_form_data']);

    $showBookingForm = true;
    $formRelationship = trim((string) ($formData['relationship'] ?? ''));
    $formRelationshipDetail = trim((string) ($formData['relationship_detail'] ?? ''));

    if (isset($formData['number_of_people']) && (int) $formData['number_of_people'] > 0) {
        $formNumber = min((int) $formData['number_of_people'], $roomMaxOccupants);
    }

    if (isset($formData['members']) && is_array($formData['members'])) {
        $formMembers = $formData['members'];
    }
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
    <link rel="stylesheet" href="../assets/css/tenant.css">
    <link rel="stylesheet" href="../assets/css/footer.css">

    <style>
        #booking-form .booking-form-error {
            display: none;

            margin-top: 6px;

            color: #dc2626;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
        }

        #booking-form .booking-form-error.is-visible {
            display: block;
        }

        #booking-form .booking-form-group input.has-error,
        #booking-form .booking-form-group select.has-error {
            border-color: #dc2626;
            background-color: #fff1f2;
        }

        #booking-form .occupant-occupation-other {
            margin-top: 12px;
        }

        #booking-form .occupant-occupation-other.is-hidden {
            display: none;
        }
    </style>
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
                    Back to Browse Rooms
                </a>
                <h1 class="view-room-title"><?= htmlspecialchars($room['title']) ?></h1>
            </div>

            <?= messages() ?>

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

                        <div class="view-room-info-row">
                            <span class="view-room-info-label">Maximum Occupants</span>
                            <span class="view-room-info-value"><?= $roomMaxOccupants ?></span>
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

                        <form action="bookmark-room.php" method="POST">
                            <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
                            <input type="hidden" name="redirect" value="view-room">
                            <?= csrf_field() ?>
                            <button type="submit" class="view-room-action-btn <?= $isBookmarked ? 'view-room-action-bookmarked' : 'view-room-action-bookmark' ?>">
                                <?= $isBookmarked ? '&#9829; Saved' : '&#9825; Save Room' ?>
                            </button>
                        </form>

                        <?php if ($room['status'] === 'available' && !$hasPendingBooking): ?>
                            <button type="button" id="booking-form-trigger" class="view-room-action-btn view-room-action-booking">
                                Request Booking
                            </button>
                        <?php elseif ($hasPendingBooking): ?>
                            <div class="view-room-action-btn view-room-action-pending">
                                Booking Requested
                            </div>
                        <?php else: ?>
                            <div class="view-room-action-btn view-room-action-unavailable">
                                Not Available
                            </div>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>

        <?php if ($room['status'] === 'available' && !$hasPendingBooking): ?>

            <section id="booking-form" class="booking-form-section <?= $showBookingForm ? '' : 'is-hidden' ?>">

                <div class="booking-form-card">

                    <h2 class="booking-form-title">Request Booking</h2>
                    <p class="booking-form-subtitle">Tell the owner how many people will live in this room and who they are.</p>

                    <form action="<?= base_url('tenant/book-room') ?>" method="POST" novalidate>

                        <?= csrf_field() ?>

                        <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">

                        <div class="booking-form-row">

                            <div class="booking-form-group">
                                <label for="relationship">Relationship</label>
                                <select name="relationship" id="relationship" required>
                                    <option value="" <?= $formRelationship === '' ? 'selected' : '' ?> disabled>Choose relationship</option>
                                    <?php foreach (['Self', 'Family', 'Friends', 'Couple', 'Relatives', 'Colleagues', 'Other'] as $rel): ?>
                                        <option value="<?= htmlspecialchars($rel) ?>" <?= $formRelationship === $rel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <span class="booking-form-error" id="relationship-error"></span>

                                <div id="relationship-detail-group" class="relationship-detail-group <?= $formRelationship === 'Other' ? '' : 'is-hidden' ?>">
                                    <label for="relationship_detail">Please specify</label>
                                    <input
                                        type="text"
                                        name="relationship_detail"
                                        id="relationship_detail"
                                        maxlength="100"
                                        placeholder="e.g. classmates, roommates, cousins"
                                        value="<?= htmlspecialchars($formRelationshipDetail) ?>"
                                        <?= $formRelationship === 'Other' ? 'required' : '' ?>
                                    >
                                    <span class="booking-form-error" id="relationship-detail-error"></span>
                                </div>
                            </div>

                            <div class="booking-form-group">
                                <label for="occupant-count">Number of people</label>
                                <input type="number" name="number_of_people" id="occupant-count" min="1" max="<?= $roomMaxOccupants ?>" value="<?= (int) $formNumber ?>" required>
                                <span class="booking-form-error" id="occupant-count-error"></span>
                            </div>

                        </div>

                        <h3 class="booking-occupants-heading">People living in the room</h3>
                        <div id="occupant-list" class="occupant-list"></div>

                        <div class="booking-form-actions">
                            <button type="submit" class="view-room-action-btn view-room-action-booking">
                                Submit Booking Request
                            </button>
                        </div>

                    </form>

                </div>

            </section>

        <?php endif; ?>

    </main>

    <script id="booking-form-config" type="application/json">
        <?=
        json_encode([
            'number'  => (int) $formNumber,
            'max'     => $roomMaxOccupants,
            'members' => $formMembers,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
    </script>

    <script>
        (function () {
            var section = document.getElementById('booking-form');
            if (!section) {
                return;
            }

            var trigger = document.getElementById('booking-form-trigger');
            if (trigger) {
                trigger.addEventListener('click', function () {
                    section.classList.toggle('is-hidden');
                    if (!section.classList.contains('is-hidden')) {
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }

            var configEl = document.getElementById('booking-form-config');
            var config = configEl ? JSON.parse(configEl.textContent) : { number: 1, max: <?= $roomMaxOccupants ?>, members: [] };

            var countInput = document.getElementById('occupant-count');
            var listEl = document.getElementById('occupant-list');
            if (!countInput || !listEl) {
                return;
            }

            var GENDERS = ['Male', 'Female', 'Other'];
            var OCCUPATIONS = ['Student', 'Job/Employed', 'Self-employed/Business', 'Other'];
            var MAX = config.max || <?= $roomMaxOccupants ?>;

            function esc(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function personSectionHTML(data, personNo) {
                data = data || {};
                var name = esc(data.name || '');
                var contact = esc(data.contact_number || '');
                var address = esc(data.permanent_address || '');
                var selectedGender = data.gender || '';
                var selectedOccupation = data.occupation || '';
                var occupationOther = esc(data.occupation_other || '');
                var isOtherOccupation = selectedOccupation === 'Other';
                var fieldIndex = personNo - 1;

                var genderOptions = GENDERS.map(function (g) {
                    var selected = g === selectedGender ? ' selected' : '';
                    return '<option value="' + g + '"' + selected + '>' + g + '</option>';
                }).join('');

                var occupationOptions = OCCUPATIONS.map(function (o) {
                    var selected = o === selectedOccupation ? ' selected' : '';
                    return '<option value="' + esc(o) + '"' + selected + '>' + esc(o) + '</option>';
                }).join('');

                return '' +
                    '<div class="occupant-section">' +
                        '<h4 class="occupant-section-title">Person ' + personNo + '</h4>' +
                        '<div class="occupant-fields">' +
                            '<div class="booking-form-group">' +
                                '<label for="member-' + fieldIndex + '-name">Full name</label>' +
                                '<input type="text" name="members[' + fieldIndex + '][name]" id="member-' + fieldIndex + '-name" value="' + name + '" maxlength="100" required>' +
                                '<span class="booking-form-error" id="member-' + fieldIndex + '-name-error"></span>' +
                            '</div>' +
                            '<div class="booking-form-group">' +
                                '<label for="member-' + fieldIndex + '-gender">Gender</label>' +
                                '<select name="members[' + fieldIndex + '][gender]" id="member-' + fieldIndex + '-gender" required>' +
                                    '<option value="" disabled' + (selectedGender === '' ? ' selected' : '') + '>Select gender</option>' +
                                    genderOptions +
                                '</select>' +
                                '<span class="booking-form-error" id="member-' + fieldIndex + '-gender-error"></span>' +
                            '</div>' +
                            '<div class="booking-form-group">' +
                                '<label for="member-' + fieldIndex + '-contact">Contact number</label>' +
                                '<input type="text" name="members[' + fieldIndex + '][contact_number]" id="member-' + fieldIndex + '-contact" value="' + contact + '" maxlength="15" required>' +
                                '<span class="booking-form-error" id="member-' + fieldIndex + '-contact-error"></span>' +
                            '</div>' +
                            '<div class="booking-form-group">' +
                                '<label for="member-' + fieldIndex + '-occupation">Occupation</label>' +
                                '<select name="members[' + fieldIndex + '][occupation]" id="member-' + fieldIndex + '-occupation" required>' +
                                    '<option value="" disabled' + (selectedOccupation === '' ? ' selected' : '') + '>Select Occupation</option>' +
                                    occupationOptions +
                                '</select>' +
                                '<span class="booking-form-error" id="member-' + fieldIndex + '-occupation-error"></span>' +
                                '<div class="occupant-occupation-other' + (isOtherOccupation ? '' : ' is-hidden') + '">' +
                                    '<label for="member-' + fieldIndex + '-occupation-other">Specify occupation</label>' +
                                    '<input type="text" name="members[' + fieldIndex + '][occupation_other]" id="member-' + fieldIndex + '-occupation-other" value="' + occupationOther + '" maxlength="100" placeholder="e.g. Teacher, Freelancer"' + (isOtherOccupation ? ' required' : '') + '>' +
                                    '<span class="booking-form-error" id="member-' + fieldIndex + '-occupation-other-error"></span>' +
                                '</div>' +
                            '</div>' +
                            '<div class="booking-form-group">' +
                                '<label for="member-' + fieldIndex + '-address">Permanent address</label>' +
                                '<input type="text" name="members[' + fieldIndex + '][permanent_address]" id="member-' + fieldIndex + '-address" value="' + address + '" maxlength="255" required>' +
                                '<span class="booking-form-error" id="member-' + fieldIndex + '-address-error"></span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }

            var renderTimer = null;

            var relationshipSelect = document.getElementById('relationship');
            var detailGroup = document.getElementById('relationship-detail-group');
            var detailInput = detailGroup ? detailGroup.querySelector('input[name="relationship_detail"]') : null;

            var MEMBER_SUFFIX = { name: 'name', gender: 'gender', contact_number: 'contact', occupation: 'occupation', occupation_other: 'occupation-other', permanent_address: 'address' };
            var RELATIONSHIP_VALUES = ['Self', 'Family', 'Friends', 'Couple', 'Relatives', 'Colleagues', 'Other'];

            function memberFieldKey(name) {
                var match = String(name).match(/^members\[(\d+)\]\[([^\]]+)\]$/);
                return match ? match[2] : null;
            }

            function memberFieldIndex(name) {
                var match = String(name).match(/^members\[(\d+)\]/);
                return match ? parseInt(match[1], 10) : -1;
            }

            function showError(errorId, control, message) {
                var span = errorId ? document.getElementById(errorId) : null;
                if (message) {
                    if (span) {
                        span.textContent = message;
                        span.classList.add('is-visible');
                    }
                    if (control) {
                        control.classList.add('has-error');
                    }
                } else {
                    if (span) {
                        span.textContent = '';
                        span.classList.remove('is-visible');
                    }
                    if (control) {
                        control.classList.remove('has-error');
                    }
                }
            }

            function collectMemberData() {
                var data = [];
                var sections = listEl.querySelectorAll('.occupant-section');
                var i;
                for (i = 0; i < sections.length; i++) {
                    var entry = { name: '', gender: '', contact_number: '', occupation: '', occupation_other: '', permanent_address: '' };
                    var controls = sections[i].querySelectorAll('input[name], select[name]');
                    var j;
                    for (j = 0; j < controls.length; j++) {
                        var key = memberFieldKey(controls[j].getAttribute('name'));
                        if (entry.hasOwnProperty(key)) {
                            entry[key] = controls[j].value;
                        }
                    }
                    data.push(entry);
                }
                return data;
            }

            function renderPeople(preserveData) {
                var count = parseInt(countInput.value, 10);
                var i;

                // Only rebuild the occupant list for a committed, valid number.
                // Empty or mid-edit values are ignored so the user can freely
                // clear the field and retype without the input being rewritten.
                if (isNaN(count) || count < 1 || count > MAX) {
                    return;
                }

                if (preserveData) {
                    config.members = collectMemberData();
                }

                listEl.innerHTML = '';
                for (i = 1; i <= count; i++) {
                    listEl.insertAdjacentHTML('beforeend', personSectionHTML(config.members[i - 1] || null, i));
                }
            }

            function isValidName(value) {
                var trimmed = value.trim();
                if (!trimmed) {
                    return { valid: false, message: 'Name is required.' };
                }
                if (trimmed.length > 100) {
                    return { valid: false, message: 'Name must be 100 characters or fewer.' };
                }
                if (!/[\p{L}]/u.test(trimmed) || !/^[\p{L}\s.'’\-]+$/u.test(trimmed)) {
                    return { valid: false, message: 'Name must contain letters and spaces only.' };
                }
                return { valid: true, message: '' };
            }

            function isValidContactNumber(value) {
                var trimmed = value.trim();
                var plusCount = (trimmed.match(/\+/g) || []).length;
                if (!trimmed || (plusCount > 0 && trimmed.charAt(0) !== '+') || plusCount > 1) {
                    return { valid: false, message: 'Enter a valid contact number.' };
                }
                if (!/^[0-9+\s\-()]+$/.test(trimmed)) {
                    return { valid: false, message: 'Enter a valid contact number.' };
                }
                var digits = trimmed.replace(/[^0-9]/g, '');
                if (digits.length < 7 || digits.length > 15) {
                    return { valid: false, message: 'Enter a valid contact number.' };
                }
                return { valid: true, message: '' };
            }

            function isValidAddress(value) {
                var trimmed = value.trim();
                if (!trimmed || trimmed.length > 255) {
                    return { valid: false, message: 'Please enter a valid permanent address.' };
                }
                var letters = trimmed.match(/[a-zA-Z]/g) || [];
                if (letters.length < 3) {
                    return { valid: false, message: 'Please enter a valid permanent address.' };
                }
                var first = trimmed.charAt(0).toLowerCase();
                var sameChar = true;
                var i;
                for (i = 1; i < trimmed.length; i++) {
                    if (trimmed.charAt(i).toLowerCase() !== first) {
                        sameChar = false;
                        break;
                    }
                }
                if (sameChar) {
                    return { valid: false, message: 'Please enter a valid permanent address.' };
                }
                return { valid: true, message: '' };
            }

            function isValidCustomOccupation(value) {
                var trimmed = String(value || '').trim();
                if (!trimmed) {
                    return { valid: false, message: 'Please specify the occupation.' };
                }
                if (trimmed.length > 100) {
                    return { valid: false, message: 'Occupation must be 100 characters or fewer.' };
                }
                var letters = trimmed.match(/[a-zA-Z]/g) || [];
                if (letters.length < 2) {
                    return { valid: false, message: 'Please enter a valid occupation.' };
                }
                var blob = letters.join('').toLowerCase();
                if (/^(.)\1*$/.test(blob)) {
                    return { valid: false, message: 'Please enter a valid occupation.' };
                }
                return { valid: true, message: '' };
            }

            function isValidOccupation(value) {
                if (!value) {
                    return { valid: false, message: 'Occupation is required.' };
                }
                if (OCCUPATIONS.indexOf(value) === -1) {
                    return { valid: false, message: 'Please choose a valid occupation.' };
                }
                return { valid: true, message: '' };
            }

            function memberErrorId(fieldIndex, fieldKey) {
                return 'member-' + fieldIndex + '-' + (MEMBER_SUFFIX[fieldKey] || fieldKey) + '-error';
            }

            function memberControl(fieldIndex, fieldKey) {
                return document.getElementById('member-' + fieldIndex + '-' + (MEMBER_SUFFIX[fieldKey] || fieldKey));
            }

            function syncOccupationOther(fieldIndex) {
                var select = memberControl(fieldIndex, 'occupation');
                var input = memberControl(fieldIndex, 'occupation_other');
                if (!select || !input) {
                    return;
                }
                var group = input.closest('.occupant-occupation-other');
                var isOther = select.value === 'Other';

                if (group) {
                    group.classList.toggle('is-hidden', !isOther);
                }

                if (isOther) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                    input.value = '';
                    showError(memberErrorId(fieldIndex, 'occupation_other'), input, '');
                    if (config.members[fieldIndex]) {
                        config.members[fieldIndex].occupation_other = '';
                    }
                }
            }

            function memberFieldResult(fieldIndex, fieldKey, value) {
                if (fieldKey === 'name') {
                    return isValidName(value);
                }
                if (fieldKey === 'gender') {
                    return GENDERS.indexOf(value) === -1
                        ? { valid: false, message: 'Please select a valid gender.' }
                        : { valid: true, message: '' };
                }
                if (fieldKey === 'contact_number') {
                    return isValidContactNumber(value);
                }
                if (fieldKey === 'occupation') {
                    return isValidOccupation(value);
                }
                if (fieldKey === 'occupation_other') {
                    var occupationSelect = memberControl(fieldIndex, 'occupation');
                    if (!occupationSelect || occupationSelect.value !== 'Other') {
                        return { valid: true, message: '' };
                    }
                    return isValidCustomOccupation(value);
                }
                if (fieldKey === 'permanent_address') {
                    return isValidAddress(value);
                }
                return { valid: true, message: '' };
            }

            function validateMemberField(fieldIndex, fieldKey, value) {
                var result = memberFieldResult(fieldIndex, fieldKey, value);
                showError(
                    memberErrorId(fieldIndex, fieldKey),
                    memberControl(fieldIndex, fieldKey),
                    result.valid ? '' : 'Member ' + (fieldIndex + 1) + ': ' + result.message
                );
                return result.valid ? null : memberControl(fieldIndex, fieldKey);
            }

            function validateMember(fieldIndex, data) {
                var keys = ['name', 'gender', 'contact_number', 'occupation', 'occupation_other', 'permanent_address'];
                var i, firstInvalid = null;
                for (i = 0; i < keys.length; i++) {
                    if (validateMemberField(fieldIndex, keys[i], data[keys[i]])) {
                        if (!firstInvalid) {
                            firstInvalid = memberControl(fieldIndex, keys[i]);
                        }
                    }
                }
                return firstInvalid;
            }

            function validateRelationship() {
                var valid = RELATIONSHIP_VALUES.indexOf(relationshipSelect.value) !== -1;
                showError('relationship-error', relationshipSelect, valid ? '' : 'Please select a valid relationship.');
                return valid ? null : relationshipSelect;
            }

            function validateRelationshipDetail() {
                var isOther = relationshipSelect.value === 'Other';
                var value = detailInput ? detailInput.value.trim() : '';

                if (isOther && !value) {
                    showError('relationship-detail-error', detailInput, 'Please provide relationship details.');
                    return detailInput;
                }
                if (value.length > 100) {
                    showError('relationship-detail-error', detailInput, 'Relationship details must be 100 characters or fewer.');
                    return detailInput;
                }
                showError('relationship-detail-error', detailInput, '');
                return null;
            }

            function validateCount() {
                var value = countInput.value.trim();
                var count = parseInt(value, 10);

                if (value === '' || !/^\d+$/.test(value) || count < 1 || count > 20) {
                    showError('occupant-count-error', countInput, 'Number of people must be between 1 and 20.');
                    return countInput;
                }
                if (count > MAX) {
                    showError('occupant-count-error', countInput, 'This room allows a maximum of ' + MAX + ' occupants.');
                    return countInput;
                }
                showError('occupant-count-error', countInput, '');
                return null;
            }

            function validateAll() {
                var firstInvalid = validateRelationship();
                if (!firstInvalid) {
                    firstInvalid = validateRelationshipDetail();
                }
                if (!firstInvalid) {
                    firstInvalid = validateCount();
                }

                var count = parseInt(countInput.value, 10);
                var i;
                for (i = 0; i < count; i++) {
                    var memberInvalid = validateMember(i, config.members[i] || { name: '', gender: '', contact_number: '', occupation: '', occupation_other: '', permanent_address: '' });
                    if (!firstInvalid && memberInvalid) {
                        firstInvalid = memberInvalid;
                    }
                }
                return firstInvalid;
            }

            countInput.addEventListener('change', function () {
                clearTimeout(renderTimer);
                renderPeople();
                validateCount();
            });

            countInput.addEventListener('input', function () {
                clearTimeout(renderTimer);
                renderTimer = setTimeout(function () {
                    renderPeople();
                    validateCount();
                }, 300);
            });

            listEl.addEventListener('input', function (event) {
                var control = event.target;
                if (!control || !control.name) {
                    return;
                }
                var fieldIndex = memberFieldIndex(control.name);
                var fieldKey = memberFieldKey(control.name);
                if (fieldIndex < 0 || !fieldKey || !MEMBER_SUFFIX[fieldKey]) {
                    return;
                }
                if (!config.members[fieldIndex]) {
                    config.members[fieldIndex] = { name: '', gender: '', contact_number: '', occupation: '', occupation_other: '', permanent_address: '' };
                }
                config.members[fieldIndex][fieldKey] = control.value;
                validateMemberField(fieldIndex, fieldKey, control.value);
                if (fieldKey === 'occupation') {
                    syncOccupationOther(fieldIndex);
                }
            });

            listEl.addEventListener('change', function (event) {
                var control = event.target;
                if (!control || !control.name) {
                    return;
                }
                var fieldIndex = memberFieldIndex(control.name);
                var fieldKey = memberFieldKey(control.name);
                if (fieldIndex < 0 || !fieldKey || !MEMBER_SUFFIX[fieldKey]) {
                    return;
                }
                if (!config.members[fieldIndex]) {
                    config.members[fieldIndex] = { name: '', gender: '', contact_number: '', occupation: '', occupation_other: '', permanent_address: '' };
                }
                config.members[fieldIndex][fieldKey] = control.value;
                validateMemberField(fieldIndex, fieldKey, control.value);
                if (fieldKey === 'occupation') {
                    syncOccupationOther(fieldIndex);
                }
            });

            listEl.addEventListener('blur', function (event) {
                var control = event.target;
                if (!control || !control.name) {
                    return;
                }
                var fieldIndex = memberFieldIndex(control.name);
                var fieldKey = memberFieldKey(control.name);
                if (fieldIndex < 0 || !fieldKey || !MEMBER_SUFFIX[fieldKey]) {
                    return;
                }
                if (!config.members[fieldIndex]) {
                    config.members[fieldIndex] = { name: '', gender: '', contact_number: '', occupation: '', occupation_other: '', permanent_address: '' };
                }
                config.members[fieldIndex][fieldKey] = control.value;
                validateMemberField(fieldIndex, fieldKey, control.value);
                if (fieldKey === 'occupation') {
                    syncOccupationOther(fieldIndex);
                }
            }, true);

            if (relationshipSelect) {
                relationshipSelect.addEventListener('change', function () {
                    validateRelationship();
                });
            }

            if (detailInput) {
                detailInput.addEventListener('input', function () {
                    validateRelationshipDetail();
                });
                detailInput.addEventListener('blur', function () {
                    validateRelationshipDetail();
                });
            }

            renderPeople();

            var bookingFormEl = section.querySelector('form');
            if (bookingFormEl) {
                bookingFormEl.addEventListener('submit', function (event) {
                    clearTimeout(renderTimer);
                    renderPeople(true);
                    var firstInvalid = validateAll();
                    if (firstInvalid) {
                        event.preventDefault();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus({ preventScroll: true });
                    }
                });
            }

            if (window.location.hash === '#booking-form') {
                section.classList.remove('is-hidden');
                setTimeout(function () {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 60);
            }
        })();
    </script>

    <script>
        (function () {
            var relationshipSelect = document.getElementById('relationship');
            var detailGroup = document.getElementById('relationship-detail-group');
            var detailInput = detailGroup ? detailGroup.querySelector('input[name="relationship_detail"]') : null;

            if (!relationshipSelect || !detailGroup || !detailInput) {
                return;
            }

            function syncRelationshipDetail() {
                var isOther = relationshipSelect.value === 'Other';

                detailGroup.classList.toggle('is-hidden', !isOther);

                if (isOther) {
                    detailInput.setAttribute('required', 'required');
                } else {
                    detailInput.removeAttribute('required');
                }
            }

            relationshipSelect.addEventListener('change', syncRelationshipDetail);
            syncRelationshipDetail();
        })();
    </script>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
