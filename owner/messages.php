<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = (int) ($_SESSION['user']['id'] ?? 0);

/**
 * Return a positive integer from request input, or 0 when invalid.
 */
function owner_message_id_from_input($value)
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
 * Handle owner replies before any page output so the request can always be
 * redirected safely (POST/Redirect/GET).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedRoomId = owner_message_id_from_input($_POST['room_id'] ?? null);
    $postedTenantId = owner_message_id_from_input($_POST['tenant_id'] ?? null);

    $postedConversationUrl = "owner/messages";

    if ($postedRoomId > 0 && $postedTenantId > 0) {
        $postedConversationUrl .= "?room_id=" . $postedRoomId . "&tenant_id=" . $postedTenantId;
    }

    if (!verify_csrf()) {
        $_SESSION['error'] = "Session expired. Please try again.";
        redirect($postedConversationUrl);
    }

    if ($postedRoomId <= 0 || $postedTenantId <= 0) {
        $_SESSION['error'] = "Invalid conversation.";
        redirect("owner/messages");
    }

    /*
     * Validate that this conversation belongs to the logged-in owner and that
     * the room is owned by the same account. Tenant and room context is then
     * taken from the database row, never trusted directly from the request.
     */
    $stmt = $conn->prepare("
        SELECT
            m.tenant_id,
            m.room_id,
            t.name AS tenant_name,
            r.title AS room_title,
            r.room_type
        FROM messages m
        INNER JOIN users t
            ON t.id = m.tenant_id
        INNER JOIN rooms r
            ON r.id = m.room_id
           AND r.owner_id = m.owner_id
        WHERE m.owner_id = ?
          AND m.tenant_id = ?
          AND m.room_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("iii", $ownerId, $postedTenantId, $postedRoomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $postedConversation = $result->fetch_assoc();
    $stmt->close();

    if (!$postedConversation) {
        $_SESSION['error'] = "Conversation not found.";
        redirect("owner/messages");
    }

    $tenantId = (int) $postedConversation['tenant_id'];
    $roomId = (int) $postedConversation['room_id'];
    $conversationUrl = "owner/messages?room_id=" . $roomId . "&tenant_id=" . $tenantId;

    $messageInput = $_POST['message'] ?? '';
    $message = is_string($messageInput) ? trim($messageInput) : '';

    if ($message === '') {
        $_SESSION['error'] = "Please enter a message.";
        redirect($conversationUrl);
    }

    if (mb_strlen($message, 'UTF-8') > 2000) {
        $_SESSION['error'] = "Messages must be 2000 characters or fewer.";
        redirect($conversationUrl);
    }

    $stmt = $conn->prepare("
        INSERT INTO messages (
            owner_id,
            tenant_id,
            room_id,
            message,
            sender_role,
            is_read
        ) VALUES (?, ?, ?, ?, 'owner', 0)
    ");

    $stmt->bind_param("iiis", $ownerId, $tenantId, $roomId, $message);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Message sent.";
    } else {
        $_SESSION['error'] = "Failed to send message. Please try again.";
    }

    $stmt->close();

    redirect($conversationUrl);
}

/*
 * Resolve the selected conversation from both the tenant and room IDs. Both
 * values are required because one tenant can discuss multiple rooms with the
 * same owner.
 */
$selectedRoomId = 0;
$selectedTenantId = 0;

if (isset($_GET['room_id']) || isset($_GET['tenant_id'])) {
    $selectedRoomId = owner_message_id_from_input($_GET['room_id'] ?? null);
    $selectedTenantId = owner_message_id_from_input($_GET['tenant_id'] ?? null);

    if ($selectedRoomId <= 0 || $selectedTenantId <= 0) {
        $_SESSION['error'] = "Invalid conversation.";
        redirect("owner/messages");
    }
}

$selectedConversation = null;
$threadMessages = [];

if ($selectedRoomId > 0 && $selectedTenantId > 0) {
    /*
     * Confirm the conversation belongs to the logged-in owner before it is
     * displayed, marked read, or used as a reply target.
     */
    $stmt = $conn->prepare("
        SELECT
            m.tenant_id,
            m.room_id,
            t.name AS tenant_name,
            r.title AS room_title,
            r.room_type,
            r.location
        FROM messages m
        INNER JOIN users t
            ON t.id = m.tenant_id
        INNER JOIN rooms r
            ON r.id = m.room_id
           AND r.owner_id = m.owner_id
        WHERE m.owner_id = ?
          AND m.tenant_id = ?
          AND m.room_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("iii", $ownerId, $selectedTenantId, $selectedRoomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedConversation = $result->fetch_assoc();
    $stmt->close();

    if (!$selectedConversation) {
        $_SESSION['error'] = "Conversation not found.";
        redirect("owner/messages");
    }

    $selectedTenantId = (int) $selectedConversation['tenant_id'];
    $selectedRoomId = (int) $selectedConversation['room_id'];

    /*
     * Mark only tenant-sent messages as read for this owner, tenant and room.
     */
    $stmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE owner_id = ?
          AND tenant_id = ?
          AND room_id = ?
          AND sender_role = 'tenant'
          AND is_read = 0
    ");

    $stmt->bind_param("iii", $ownerId, $selectedTenantId, $selectedRoomId);
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
        WHERE owner_id = ?
          AND tenant_id = ?
          AND room_id = ?
        ORDER BY created_at ASC, id ASC
    ");

    $stmt->bind_param("iii", $ownerId, $selectedTenantId, $selectedRoomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $threadMessages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/*
 * Load the owner's conversations, grouped by tenant and room. The latest
 * message is the row with the highest ID in each conversation.
 */
$stmt = $conn->prepare("
    SELECT
        m.id,
        m.owner_id,
        m.tenant_id,
        m.room_id,
        m.message,
        m.sender_role,
        m.is_read,
        m.created_at,
        t.name AS tenant_name,
        r.title AS room_title,
        r.room_type,
        recent.unread_count
    FROM messages m
    INNER JOIN users t
        ON t.id = m.tenant_id
    INNER JOIN rooms r
        ON r.id = m.room_id
       AND r.owner_id = m.owner_id
    INNER JOIN (
        SELECT
            tenant_id,
            room_id,
            MAX(id) AS max_id,
            SUM(CASE WHEN is_read = 0 AND sender_role = 'tenant' THEN 1 ELSE 0 END) AS unread_count
        FROM messages
        WHERE owner_id = ?
        GROUP BY tenant_id, room_id
    ) recent ON recent.max_id = m.id
    WHERE m.owner_id = ?
    ORDER BY m.created_at DESC, m.id DESC
");

$stmt->bind_param("ii", $ownerId, $ownerId);
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
                        Conversations with tenants about your rooms.
                    </p>
                </div>

            </div>

            <?= messages() ?>

            <div class="messages-layout <?= $selectedConversation ? 'has-selection' : '' ?>">

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
                                    When tenants contact you about your rooms, conversations will appear here.
                                </p>

                            </div>

                        <?php else: ?>

                            <?php foreach ($conversations as $conversation): ?>

                                <?php
                                $isActive = $selectedConversation
                                    && (int) $conversation['tenant_id'] === (int) $selectedConversation['tenant_id']
                                    && (int) $conversation['room_id'] === (int) $selectedConversation['room_id'];

                                $tenantInitial = strtoupper(mb_substr($conversation['tenant_name'], 0, 1));
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

                                <a href="messages.php?room_id=<?= (int) $conversation['room_id'] ?>&amp;tenant_id=<?= (int) $conversation['tenant_id'] ?>"
                                    class="conversation-item <?= $isActive ? 'is-active' : '' ?>">

                                    <div class="conversation-avatar">
                                        <?= htmlspecialchars($tenantInitial) ?>
                                    </div>

                                    <div class="conversation-info">

                                        <div class="conversation-topline">
                                            <span class="conversation-name">
                                                <?= htmlspecialchars($conversation['tenant_name']) ?>
                                            </span>
                                            <span class="conversation-time">
                                                <?= htmlspecialchars($timeAgo) ?>
                                            </span>
                                        </div>

                                        <div class="conversation-bottomline">
                                            <span class="conversation-preview">
                                                <?php if ($conversation['sender_role'] === 'owner'): ?>You: <?php endif; ?><?= htmlspecialchars($conversation['message']) ?>
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

                    <?php if (!$selectedConversation): ?>

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

                        <?php $tenantInitial = strtoupper(mb_substr($selectedConversation['tenant_name'], 0, 1)); ?>

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
                                    <?= htmlspecialchars($tenantInitial) ?>
                                </div>

                                <div class="thread-details">
                                    <span class="thread-name">
                                        <?= htmlspecialchars($selectedConversation['tenant_name']) ?>
                                    </span>
                                    <span class="thread-room">
                                        <?= htmlspecialchars($selectedConversation['room_title']) ?>
                                        &middot;
                                        <?= htmlspecialchars($selectedConversation['room_type']) ?>
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

                                <h2 class="thread-empty-title">No messages yet</h2>

                                <p class="thread-empty-text">
                                    Send a reply to
                                    <?= htmlspecialchars($selectedConversation['tenant_name']) ?>
                                    about
                                    <?= htmlspecialchars($selectedConversation['room_title']) ?>.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="thread-body">

                                <?php foreach ($threadMessages as $threadMessage): ?>

                                    <?php $isOwnMessage = $threadMessage['sender_role'] === 'owner'; ?>

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

                            <form action="messages.php?room_id=<?= (int) $selectedConversation['room_id'] ?>&amp;tenant_id=<?= (int) $selectedConversation['tenant_id'] ?>"
                                method="POST" class="composer-form">

                                <?= csrf_field() ?>

                                <input type="hidden" name="room_id"
                                    value="<?= (int) $selectedConversation['room_id'] ?>">
                                <input type="hidden" name="tenant_id"
                                    value="<?= (int) $selectedConversation['tenant_id'] ?>">

                                <div class="composer-field">
                                    <textarea class="composer-input" name="message" id="message" rows="1"
                                        maxlength="2000" placeholder="Write a reply..." required></textarea>
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
