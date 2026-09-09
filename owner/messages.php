<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';

$ownerId = $_SESSION['user']['id'] ?? 0;

/*
 * Fetch all conversations for the logged-in owner.
 * Group by tenant+room, get the latest message per conversation,
 * and count unread messages per conversation.
 */

$stmt = $conn->prepare("
    SELECT
        m.id,
        m.message,
        m.is_read,
        m.created_at,
        t.id AS tenant_id,
        t.name AS tenant_name,
        r.id AS room_id,
        r.room_type,
        sub.unread_count
    FROM messages m
    INNER JOIN users t
        ON m.tenant_id = t.id
    INNER JOIN rooms r
        ON m.room_id = r.id
    INNER JOIN (
        SELECT
            MAX(id) AS max_id,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_count
        FROM messages
        WHERE owner_id = ?
        GROUP BY tenant_id, room_id
    ) sub ON m.id = sub.max_id
    WHERE m.owner_id = ?
    ORDER BY m.created_at DESC
");

$stmt->bind_param("ii", $ownerId, $ownerId);
$stmt->execute();

$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);

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
    <link rel="stylesheet" href="../assets/css/footer.css">

</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <main class="owner-messages-page">

        <section class="rooms-section">

            <div class="rooms-header">

                <div class="rooms-header-text">
                    <h1 class="rooms-heading">Messages</h1>
                    <p class="rooms-subtitle">All conversations with tenants.</p>
                </div>

            </div>

            <?php if (empty($messages)): ?>

                <div class="rooms-empty">

                    <div class="rooms-empty-icon">

                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M20 11.5C20 16.194 16.194 20 11.5 20C9.95 20 8.5 19.59 7.25 18.87L4 20L5.13 16.75C4.41 15.5 4 14.05 4 12.5C4 7.806 7.806 4 12.5 4C17.194 4 20 7.806 20 11.5Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <circle cx="8" cy="12" r="1" fill="currentColor" />
                            <circle cx="12" cy="12" r="1" fill="currentColor" />
                            <circle cx="16" cy="12" r="1" fill="currentColor" />
                        </svg>

                    </div>

                    <h2 class="rooms-empty-title">No messages yet</h2>

                    <p class="rooms-empty-text">
                        When tenants contact you about your rooms, conversations will appear here.
                    </p>

                </div>

            <?php else: ?>

                <div class="recent-messages-grid">

                    <?php foreach ($messages as $msg): ?>

                        <?php
                        $msgTenantInitial = strtoupper(mb_substr($msg['tenant_name'], 0, 1));
                        $msgTimeAgo = '';
                        $diff = time() - strtotime($msg['created_at']);
                        if ($diff < 60) {
                            $msgTimeAgo = 'Just now';
                        } elseif ($diff < 3600) {
                            $msgTimeAgo = floor($diff / 60) . ' min ago';
                        } elseif ($diff < 86400) {
                            $msgTimeAgo = floor($diff / 3600) . ' hr ago';
                        } elseif ($diff < 604800) {
                            $msgTimeAgo = floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
                        } else {
                            $msgTimeAgo = date('M j', strtotime($msg['created_at']));
                        }
                        ?>

                        <article class="recent-message-card">

                            <div class="rm-card-header">

                                <div class="rm-card-tenant">
                                    <div class="rm-card-avatar">
                                        <?= htmlspecialchars($msgTenantInitial) ?>
                                    </div>
                                    <div class="rm-card-tenant-info">
                                        <span class="rm-card-name">
                                            <?= htmlspecialchars($msg['tenant_name']) ?>
                                        </span>
                                        <span class="rm-card-room-type">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 10.5L12 3L21 10.5"/>
                                                <path d="M5 9.5V20H19V9.5"/>
                                                <path d="M9 20V14H15V20"/>
                                            </svg>
                                            <?= htmlspecialchars($msg['room_type']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="rm-card-meta">
                                    <span class="rm-card-time"><?= htmlspecialchars($msgTimeAgo) ?></span>
                                    <?php if ((int) $msg['unread_count'] > 0): ?>
                                        <span class="rm-card-unread"><?= (int) $msg['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="rm-card-message">
                                <?= htmlspecialchars($msg['message']) ?>
                            </div>

                            <div class="rm-card-action">
                                <span class="rm-card-action-text">Open Conversation</span>
                                <span class="rm-card-action-arrow">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                        <polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </span>
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
