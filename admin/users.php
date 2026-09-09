<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_admin.php';

$adminSidebarActive = 'users';
$currentUserId = (int) $_SESSION['user']['id'];

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$allowedRoles = ['tenant', 'owner', 'admin'];

if ($roleFilter !== '' && !in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

$searchCondition = '';
$searchParams = [];
$searchTypes = '';

if ($search !== '') {
    $searchCondition = "WHERE (name LIKE ? OR email LIKE ?)";
    $searchParams[] = "%{$search}%";
    $searchParams[] = "%{$search}%";
    $searchTypes .= 'ss';
}

$roleCondition = '';
if ($roleFilter !== '') {
    $roleCondition = $searchCondition === '' ? "WHERE role = ?" : "AND role = ?";
    $searchParams[] = $roleFilter;
    $searchTypes .= 's';
}

$countSql = "SELECT COUNT(*) AS total FROM users {$searchCondition} {$roleCondition}";
$countStmt = $conn->prepare($countSql);
if (!empty($searchParams)) {
    $countStmt->bind_param($searchTypes, ...$searchParams);
}
$countStmt->execute();
$totalRows = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$dataSql = "SELECT id, name, email, phone, role, created_at FROM users {$searchCondition} {$roleCondition} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$dataParams = $searchParams;
$dataTypes = $searchTypes . 'ii';
$dataParams[] = $perPage;
$dataParams[] = $offset;

$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$users = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $deleteUserId = (int) ($_POST['user_id'] ?? 0);

    if ($deleteUserId > 0 && $deleteUserId !== $currentUserId) {
        $delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delStmt->bind_param("i", $deleteUserId);
        $delStmt->execute();
        $delStmt->close();

        $_SESSION['success'] = 'User deleted successfully.';
    } elseif ($deleteUserId === $currentUserId) {
        $_SESSION['error'] = 'You cannot delete your own account.';
    }

    header("Location: " . base_url('admin/users'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | RoomFinder Admin</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <div class="admin-layout">

        <?php include __DIR__ . '/sidebar.php'; ?>

        <main class="admin-main">

            <div class="admin-page-header">
                <h1 class="admin-page-title">Manage Users</h1>
                <p class="admin-page-subtitle">View and manage all registered users on the platform.</p>
            </div>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="admin-card">

                <div class="admin-toolbar">
                    <div class="admin-toolbar-left">
                        <div class="admin-search">
                            <svg class="admin-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/users')) ?>" id="search-form">
                                <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                                <?php if ($roleFilter !== ''): ?>
                                    <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="admin-filter">
                            <form method="GET" action="<?= htmlspecialchars(base_url('admin/users')) ?>" id="role-form">
                                <select name="role" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <option value="tenant" <?= $roleFilter === 'tenant' ? 'selected' : '' ?>>Tenant</option>
                                    <option value="owner" <?= $roleFilter === 'owner' ? 'selected' : '' ?>>Owner</option>
                                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <?php if ($search !== ''): ?>
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="admin-toolbar-right">
                        <span style="color: #64748b; font-size: 14px;"><?= number_format($totalRows) ?> user<?= $totalRows !== 1 ? 's' : '' ?> found</span>
                    </div>
                </div>

                <?php if (empty($users)): ?>

                    <div class="admin-empty">
                        <div class="admin-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No users found</h3>
                        <p class="admin-empty-text">
                            <?php if ($search !== '' || $roleFilter !== ''): ?>
                                No users match your current filters. Try adjusting your search or filter criteria.
                            <?php else: ?>
                                There are no registered users yet.
                            <?php endif; ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= (int) $u['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($u['name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= htmlspecialchars($u['phone']) ?></td>
                                        <td>
                                            <?php
                                            $roleBadgeClass = 'admin-badge-role';
                                            if ($u['role'] === 'owner') $roleBadgeClass = 'admin-badge-owner';
                                            elseif ($u['role'] === 'tenant') $roleBadgeClass = 'admin-badge-tenant';
                                            ?>
                                            <span class="admin-badge <?= $roleBadgeClass ?>"><?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <button type="button" class="admin-btn" onclick='openUserModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_TAG) ?>)' title="View Details">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </button>
                                                <?php if ((int) $u['id'] !== $currentUserId): ?>
                                                    <button type="button" class="admin-btn admin-btn-danger" onclick="confirmDeleteUser(<?= (int) $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name']), ENT_QUOTES) ?>')" title="Delete User">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                            <path d="M10 11v6" />
                                                            <path d="M14 11v6" />
                                                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="admin-pagination">
                            <div class="admin-pagination-info">
                                Showing <?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $totalRows) ?> of <?= number_format($totalRows) ?>
                            </div>
                            <div class="admin-pagination-links">
                                <?php
                                $paginationBase = base_url('admin/users') . '?' . http_build_query(array_filter([
                                    'search' => $search !== '' ? $search : null,
                                    'role' => $roleFilter !== '' ? $roleFilter : null,
                                ]));
                                $sep = strpos($paginationBase, '?') !== false ? '&' : '?';
                                ?>

                                <a href="<?= $paginationBase . $sep . 'page=' . max(1, $page - 1) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">&laquo;</a>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                if ($startPage > 1): ?>
                                    <a href="<?= $paginationBase . $sep . 'page=1' ?>">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="disabled">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="<?= $paginationBase . $sep . 'page=' . $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="disabled">...</span>
                                    <?php endif; ?>
                                    <a href="<?= $paginationBase . $sep . 'page=' . $totalPages ?>"><?= $totalPages ?></a>
                                <?php endif; ?>

                                <a href="<?= $paginationBase . $sep . 'page=' . min($totalPages, $page + 1) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">&raquo;</a>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </main>

    </div>

    <!-- User Detail Modal -->
    <div class="admin-modal-overlay" id="user-modal">
        <div class="admin-modal">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">User Details</h3>
                <button class="admin-modal-close" onclick="closeUserModal()" aria-label="Close">&times;</button>
            </div>
            <div class="admin-modal-body" id="user-modal-body">
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form method="POST" action="<?= htmlspecialchars(base_url('admin/users')) ?>" id="delete-user-form" style="display:none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="delete-user-id">
    </form>

    <script>
    document.getElementById('search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var input = form.querySelector('input[name="search"]');
        if (input.value.trim() === '') {
            input.removeAttribute('name');
        }
        form.submit();
    });

    function openUserModal(user) {
        var roleClass = 'admin-badge-role';
        if (user.role === 'owner') roleClass = 'admin-badge-owner';
        else if (user.role === 'tenant') roleClass = 'admin-badge-tenant';

        var html = '<div class="admin-modal-field"><span class="admin-modal-label">Name</span><span class="admin-modal-value">' + escapeHtml(user.name) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Email</span><span class="admin-modal-value">' + escapeHtml(user.email) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Phone</span><span class="admin-modal-value">' + escapeHtml(user.phone) + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Role</span><span class="admin-modal-value"><span class="admin-badge ' + roleClass + '">' + escapeHtml(user.role.charAt(0).toUpperCase() + user.role.slice(1)) + '</span></span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Registered</span><span class="admin-modal-value">' + escapeHtml(user.created_at) + '</span></div>';

        document.getElementById('user-modal-body').innerHTML = html;
        document.getElementById('user-modal').classList.add('open');
    }

    function closeUserModal() {
        document.getElementById('user-modal').classList.remove('open');
    }

    function confirmDeleteUser(userId, userName) {
        if (confirm('Are you sure you want to delete user "' + userName + '"?\n\nThis will also remove all their rooms, bookings, bookmarks, and messages. This action cannot be undone.')) {
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-user-form').submit();
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    document.getElementById('user-modal').addEventListener('click', function(e) {
        if (e.target === this) closeUserModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeUserModal();
    });
    </script>

</body>

</html>
