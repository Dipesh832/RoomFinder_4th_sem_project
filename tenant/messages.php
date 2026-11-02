<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_tenant.php';

$tenantId = (int) ($_SESSION['user']['id'] ?? 0);

/**
 * Return a positive integer from request input, or 0 when invalid.
 */
function message_room_id_from_input($value)
{
    if (is_int($value)) {
        return $value > 0 ? $value : 0;
    }

    if (!is_string($value)) {
        return 0;
    }

    $value = trim($value);

    if ($value === '' || !ctype_digit($value)) {
        return 0;
    }

    $value = (int) $value;

    return $value > 0 ? $value : 0;
}

/*
 * Handle message submission before any page output so the request can always
 * be redirected safely (POST/Redirect/GET).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedRoomId = message_room_id_from_input($_POST['room_id'] ?? null);

    if (!verify_csrf()) {
        $_SESSION['error'] = "Session expired. Please try again.";
        redirect($postedRoomId > 0
            ? "tenant/messages?room_id=" . $postedRoomId
            : "tenant/messages");
    }

    if ($postedRoomId <= 0) {
        $_SESSION['error'] = "Invalid room ID.";
        redirect("tenant/messages");
    }

    /*
     * The owner is always derived from the selected room. Never accept an
     * owner_id from the client.
     */
    $stmt = $conn->prepare("
        SELECT
            r.id,
            r.title,
            r.room_type,
            r.location,
            r.owner_id,
            u.name AS owner_name
        FROM rooms r
        INNER JOIN users u
            ON u.id = r.owner_id
        WHERE r.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $postedRoomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $postedRoom = $result->fetch_assoc();
    $stmt->close();

    if (!$postedRoom) {
        $_SESSION['error'] = "Room not found.";
        redirect("tenant/messages");
    }

    if ((int) $postedRoom['owner_id'] === $tenantId) {
        $_SESSION['error'] = "You cannot message your own room.";
        redirect("tenant/view-room?id=" . $postedRoomId);
    }

    $message = trim((string) ($_POST['message'] ?? ''));

    if ($message === '') {
        $_SESSION['error'] = "Please enter a message.";
        redirect("tenant/messages?room_id=" . $postedRoomId);
    }

    if (mb_strlen($message, 'UTF-8') > 2000) {
        $_SESSION['error'] = "Messages must be 2000 characters or fewer.";
        redirect("tenant/messages?room_id=" . $postedRoomId);
    }

    $ownerId = (int) $postedRoom['owner_id'];

    $stmt = $conn->prepare("
        INSERT INTO messages (
            owner_id,
            tenant_id,
            room_id,
            message,
            sender_role,
            is_read
        ) VALUES (?, ?, ?, ?, 'tenant', 0)
    ");

    $stmt->bind_param("iiis", $ownerId, $tenantId, $postedRoomId, $message);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Message sent.";
    } else {
        $_SESSION['error'] = "Failed to send message. Please try again.";
    }

    $stmt->close();

    redirect("tenant/messages?room_id=" . $postedRoomId);
}

/*
 * Resolve the selected conversation from the room ID. The owner is always
 * read from the room row.
 */
$selectedRoomId = 0;

if (isset($_GET['room_id'])) {
    $selectedRoomId = message_room_id_from_input($_GET['room_id']);

    if ($selectedRoomId <= 0) {
        $_SESSION['error'] = "Invalid room ID.";
        redirect("tenant/messages");
    }
}

$selectedRoom = null;
$selectedOwnerId = 0;
$threadMessages = [];

if ($selectedRoomId > 0) {
    $stmt = $conn->prepare("
        SELECT
            r.id,
            r.title,
            r.room_type,
            r.location,
            r.owner_id,
            u.name AS owner_name
        FROM rooms r
        INNER JOIN users u
            ON u.id = r.owner_id
        WHERE r.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $selectedRoomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedRoom = $result->fetch_assoc();
    $stmt->close();

    if (!$selectedRoom) {
        $_SESSION['error'] = "Room not found.";
        redirect("tenant/messages");
    }

    if ((int) $selectedRoom['owner_id'] === $tenantId) {
        $_SESSION['error'] = "You cannot message your own room.";
        redirect("tenant/view-room?id=" . $selectedRoomId);
    }

    $selectedOwnerId = (int) $selectedRoom['owner_id'];

    /*
     * Mark only owner-sent messages as read for this tenant, owner and room.
     */
    $stmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE tenant_id = ?
          AND owner_id = ?
          AND room_id = ?
          AND sender_role = 'owner'
          AND is_read = 0
    ");

    $stmt->bind_param("iii", $tenantId, $selectedOwnerId, $selectedRoomId);
    $stmt->execute();
    $stmt->close();

    /*
     * Load the conversation oldest to newest.
     */
    $stmt = $conn->prepare("
        SELECT
            id,
            message,
            sender_role,
            is_read,
            created_at
        FROM messages
        WHERE tenant_id = ?
          AND owner_id = ?
          AND room_id = ?
        ORDER BY created_at ASC, id ASC
    ");

    $stmt->bind_param("iii", $tenantId, $selectedOwnerId, $selectedRoomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $threadMessages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/*
 * Load the tenant's conversations, grouped by room owner and room. The latest
 * message is the row with the highest ID in each conversation.
 */
$stmt = $conn->prepare("
    SELECT
        m.id,
        m.owner_id,
        m.room_id,
        m.message,
        m.sender_role,
        m.is_read,
        m.created_at,
        r.title AS room_title,
        r.room_type,
        o.name AS owner_name,
        recent.unread_count
    FROM messages m
    INNER JOIN rooms r
        ON r.id = m.room_id
    INNER JOIN users o
        ON o.id = m.owner_id
    INNER JOIN (
        SELECT
            owner_id,
            room_id,
            MAX(id) AS max_id,
            SUM(CASE WHEN is_read = 0 AND sender_role = 'owner' THEN 1 ELSE 0 END) AS unread_count
        FROM messages
        WHERE tenant_id = ?
        GROUP BY owner_id, room_id
    ) recent ON recent.max_id = m.id
    WHERE m.tenant_id = ?
    ORDER BY m.created_at DESC, m.id DESC
");

$stmt->bind_param("ii", $tenantId, $tenantId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = $result->fetch_all(MYSQLI_ASSOC);
$conversationCount = count($conversations);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Messages | RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/owner.css">
    <link rel="stylesheet" href="../assets/css/tenant.css">
    <link rel="stylesheet" href="../assets/css/messages.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="messages-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">Messages</h1>
                    <p class="rooms-subtitle">
                        Conversations with room owners.
                    </p>
                </div>

            </div>

            <?= messages() ?>

            <div class="messages-layout <?= $selectedRoom ? 'has-selection' : '' ?>">

                <aside class="conversation-list">

                    <div class="conversation-list-header">
                        <h2 class="conversation-list-title">Conversations</h2>
                        <span class="conversation-list-count">
                            <?= $conversationCount ?> <?= $conversationCount === 1 ? 'conversation' : 'conversations' ?>
                        </span>
                    </div>

                    <div class="conversation-list-items">

                        <?php if (empty($conversations)): ?>

                            <div class="messages-empty">

                                <div class="messages-empty-icon">

                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path
                                            d="M20 11.5C20 16.194 16.194 20 11.5 20C9.95 20 8.5 19.59 7.25 18.87L4 20L5.13 16.75C4.41 15.5 4 14.05 4 12.5C4 7.806 7.806 4 12.5 4C17.194 4 20 7.806 20 11.5Z"
                                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <circle cx="8" cy="12" r="1" fill="currentColor" />
                                        <circle cx="12" cy="12" r="1" fill="currentColor" />
                                        <circle cx="16" cy="12" r="1" fill="currentColor" />
                                    </svg>

                                </div>

                                <h3 class="messages-empty-title">No messages yet</h3>

                                <p class="messages-empty-text">
                                    When you contact a room owner, the conversation will appear here.
                                </p>

                            </div>

                        <?php else: ?>

                            <?php foreach ($conversations as $conversation): ?>

                                <?php
                                $isActive = $selectedRoom
                                    && (int) $conversation['owner_id'] === (int) $selectedRoom['owner_id']
                                    && (int) $conversation['room_id'] === (int) $selectedRoom['id'];

                                $ownerInitial = strtoupper(mb_substr($conversation['owner_name'], 0, 1));
                                $conversationTime = time() - strtotime($conversation['created_at']);

                                if ($conversationTime < 60) {
                                    $timeAgo = 'Just now';
                                } elseif ($conversationTime < 3600) {
                                    $timeAgo = floor($conversationTime / 60) . ' min ago';
                                } elseif ($conversationTime < 86400) {
                                    $timeAgo = floor($conversationTime / 3600) . ' hr ago';
                                } elseif ($conversationTime < 604800) {
                                    $days = floor($conversationTime / 86400);
                                    $timeAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                                } else {
                                    $timeAgo = date('M j', strtotime($conversation['created_at']));
                                }
                                ?>

                                <a href="messages.php?room_id=<?= (int) $conversation['room_id'] ?>"
                                    class="conversation-item <?= $isActive ? 'is-active' : '' ?>">

                                    <div class="conversation-avatar">
                                        <?= htmlspecialchars($ownerInitial) ?>
                                    </div>

                                    <div class="conversation-info">

                                        <div class="conversation-topline">
                                            <span class="conversation-name">
                                                <?= htmlspecialchars($conversation['owner_name']) ?>
                                            </span>
                                            <span class="conversation-time">
                                                <?= htmlspecialchars($timeAgo) ?>
                                            </span>
                                        </div>

                                        <div class="conversation-bottomline">
                                            <span class="conversation-preview">
                                                <?php if ($conversation['sender_role'] === 'tenant'): ?>You: <?php endif; ?><?= htmlspecialchars($conversation['message']) ?>
                                            </span>

                                            <?php if ((int) $conversation['unread_count'] > 0): ?>
                                                <span class="conversation-unread">
                                                    <?= (int) $conversation['unread_count'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <span class="conversation-room">
                                            <?= htmlspecialchars($conversation['room_title']) ?>
                                            &middot;
                                            <?= htmlspecialchars($conversation['room_type']) ?>
                                        </span>

                                    </div>

                                </a>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </aside>

                <section class="conversation-thread">

                    <?php if (!$selectedRoom): ?>

                        <div class="thread-empty">

                            <div class="thread-empty-icon">

                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path
                                        d="M20 11.5C20 16.194 16.194 20 11.5 20C9.95 20 8.5 19.59 7.25 18.87L4 20L5.13 16.75C4.41 15.5 4 14.05 4 12.5C4 7.806 7.806 4 12.5 4C17.194 4 20 7.806 20 11.5Z"
                                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <circle cx="8" cy="12" r="1" fill="currentColor" />
                                    <circle cx="12" cy="12" r="1" fill="currentColor" />
                                    <circle cx="16" cy="12" r="1" fill="currentColor" />
                                </svg>

                            </div>

                            <h2 class="thread-empty-title">Select a conversation</h2>

                            <p class="thread-empty-text">
                                Choose a conversation from the list to read and reply.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php $ownerInitial = strtoupper(mb_substr($selectedRoom['owner_name'], 0, 1)); ?>

                        <div class="thread-header">

                            <a href="messages.php" class="thread-back" aria-label="Back to conversations">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                    aria-hidden="true">
                                    <line x1="19" y1="12" x2="5" y2="12" />
                                    <polyline points="12 19 5 12 12 5" />
                                </svg>
                                Back
                            </a>

                            <div class="thread-participant">

                                <div class="thread-avatar">
                                    <?= htmlspecialchars($ownerInitial) ?>
                                </div>

                                <div class="thread-details">
                                    <span class="thread-name">
                                        <?= htmlspecialchars($selectedRoom['owner_name']) ?>
                                    </span>
                                    <span class="thread-room">
                                        <?= htmlspecialchars($selectedRoom['room_title']) ?>
                                        &middot;
                                        <?= htmlspecialchars($selectedRoom['room_type']) ?>
                                    </span>
                                </div>

                            </div>

                        </div>

                        <?php if (empty($threadMessages)): ?>

                            <div class="thread-empty">

                                <div class="thread-empty-icon">

                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path
                                            d="M20 11.5C20 16.194 16.194 20 11.5 20C9.95 20 8.5 19.59 7.25 18.87L4 20L5.13 16.75C4.41 15.5 4 14.05 4 12.5C4 7.806 7.806 4 12.5 4C17.194 4 20 7.806 20 11.5Z"
                                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <circle cx="8" cy="12" r="1" fill="currentColor" />
                                        <circle cx="12" cy="12" r="1" fill="currentColor" />
                                        <circle cx="16" cy="12" r="1" fill="currentColor" />
                                    </svg>

                                </div>

                                <h2 class="thread-empty-title">Start the conversation</h2>

                                <p class="thread-empty-text">
                                    Send a message to
                                    <?= htmlspecialchars($selectedRoom['owner_name']) ?>
                                    about
                                    <?= htmlspecialchars($selectedRoom['room_title']) ?>.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="thread-body">

                                <?php foreach ($threadMessages as $threadMessage): ?>

                                    <?php $isOwnMessage = $threadMessage['sender_role'] === 'tenant'; ?>

                                    <div class="message-row <?= $isOwnMessage ? 'is-own' : '' ?>">

                                        <div class="message-bubble <?= $isOwnMessage ? 'is-own' : '' ?>">

                                            <p class="message-text"><?= htmlspecialchars($threadMessage['message']) ?></p>

                                        </div>

                                        <div class="message-meta">
                                            <span class="message-time">
                                                <?= htmlspecialchars(date('M j, g:i A', strtotime($threadMessage['created_at']))) ?>
                                            </span>
                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                        <div class="thread-composer">

                            <form action="messages.php?room_id=<?= (int) $selectedRoom['id'] ?>" method="POST"
                                class="composer-form">

                                <?= csrf_field() ?>

                                <input type="hidden" name="room_id" value="<?= (int) $selectedRoom['id'] ?>">

                                <div class="composer-field">
                                    <textarea class="composer-input" name="message" id="message" rows="1"
                                        maxlength="2000" placeholder="Write a message..." required></textarea>
                                    <span class="composer-hint">Maximum 2000 characters.</span>
                                </div>

                                <button type="submit" class="composer-submit">Send</button>

                            </form>

                        </div>

                    <?php endif; ?>

                </section>

            </div>

        </section>

    </main>

    <?php include '../includes/footer.php'; ?>

</body>

</html>
