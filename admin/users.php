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

// ─── CREATE USER ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $createName     = trim($_POST['name'] ?? '');
    $createEmail    = trim($_POST['email'] ?? '');
    $createPhone    = trim($_POST['phone'] ?? '');
    $createPassword = $_POST['password'] ?? '';
    $createRole     = $_POST['role'] ?? '';

    $createErrors = [];

    if ($createName === '') {
        $createErrors[] = 'Name is required.';
    } elseif (strlen($createName) > 100) {
        $createErrors[] = 'Name must be 100 characters or fewer.';
    }

    if ($createEmail === '') {
        $createErrors[] = 'Email is required.';
    } elseif (!filter_var($createEmail, FILTER_VALIDATE_EMAIL)) {
        $createErrors[] = 'Email is not valid.';
    } else {
        $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $emailCheck->bind_param("s", $createEmail);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            $createErrors[] = 'A user with this email already exists.';
        }
        $emailCheck->close();
    }

    if ($createPhone === '') {
        $createErrors[] = 'Phone is required.';
    }

    if ($createPassword === '') {
        $createErrors[] = 'Password is required.';
    } elseif (strlen($createPassword) < 6) {
        $createErrors[] = 'Password must be at least 6 characters.';
    }

    if (!in_array($createRole, $allowedRoles, true)) {
        $createErrors[] = 'Invalid role selected.';
    }

    if (!empty($createErrors)) {
        $_SESSION['error'] = implode(' ', $createErrors);
        $_SESSION['old_create'] = [
            'name'  => $createName,
            'email' => $createEmail,
            'phone' => $createPhone,
            'role'  => $createRole,
        ];
        header("Location: " . base_url('admin/users'));
        exit;
    }

    $hashedPassword = password_hash($createPassword, PASSWORD_DEFAULT);
    $insStmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $insStmt->bind_param("sssss", $createName, $createEmail, $createPhone, $hashedPassword, $createRole);
    $insStmt->execute();
    $insStmt->close();

    $_SESSION['success'] = 'User "' . $createName . '" created successfully.';
    unset($_SESSION['old_create']);
    header("Location: " . base_url('admin/users'));
    exit;
}

// ─── UPDATE USER ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $editUserId   = (int) ($_POST['user_id'] ?? 0);
    $editName     = trim($_POST['name'] ?? '');
    $editEmail    = trim($_POST['email'] ?? '');
    $editPhone    = trim($_POST['phone'] ?? '');
    $editPassword = $_POST['password'] ?? '';
    $editRole     = $_POST['role'] ?? '';

    $editErrors = [];

    if ($editUserId <= 0) {
        $editErrors[] = 'Invalid user ID.';
    }

    if ($editName === '') {
        $editErrors[] = 'Name is required.';
    } elseif (strlen($editName) > 100) {
        $editErrors[] = 'Name must be 100 characters or fewer.';
    }

    if ($editEmail === '') {
        $editErrors[] = 'Email is required.';
    } elseif (!filter_var($editEmail, FILTER_VALIDATE_EMAIL)) {
        $editErrors[] = 'Email is not valid.';
    } else {
        $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $emailCheck->bind_param("si", $editEmail, $editUserId);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            $editErrors[] = 'A user with this email already exists.';
        }
        $emailCheck->close();
    }

    if ($editPhone === '') {
        $editErrors[] = 'Phone is required.';
    }

    if ($editPassword !== '' && strlen($editPassword) < 6) {
        $editErrors[] = 'New password must be at least 6 characters.';
    }

    if (!in_array($editRole, $allowedRoles, true)) {
        $editErrors[] = 'Invalid role selected.';
    }

    if (!empty($editErrors)) {
        $_SESSION['error'] = implode(' ', $editErrors);
        $_SESSION['old_edit'] = [
            'id'    => $editUserId,
            'name'  => $editName,
            'email' => $editEmail,
            'phone' => $editPhone,
            'role'  => $editRole,
        ];
        header("Location: " . base_url('admin/users'));
        exit;
    }

    if ($editPassword !== '') {
        $hashedPassword = password_hash($editPassword, PASSWORD_DEFAULT);
        $updStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ?, role = ? WHERE id = ?");
        $updStmt->bind_param("sssssi", $editName, $editEmail, $editPhone, $hashedPassword, $editRole, $editUserId);
    } else {
        $updStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
        $updStmt->bind_param("ssssi", $editName, $editEmail, $editPhone, $editRole, $editUserId);
    }
    $updStmt->execute();
    $updStmt->close();

    $_SESSION['success'] = 'User updated successfully.';
    unset($_SESSION['old_edit']);
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

            <div class="admin-page-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div>
                    <h1 class="admin-page-title">Manage Users</h1>
                    <p class="admin-page-subtitle">View and manage all registered users on the platform.</p>
                </div>
                <button type="button" class="admin-btn-primary" onclick="openCreateModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add User
                </button>
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
                                                <button type="button" class="admin-btn" onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_TAG) ?>)' title="Edit User">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
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

    <!-- Create User Modal -->
    <?php
    $oldCreate = $_SESSION['old_create'] ?? null;
    unset($_SESSION['old_create']);
    ?>
    <div class="admin-modal-overlay" id="create-modal">
        <div class="admin-modal">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Add New User</h3>
                <button class="admin-modal-close" onclick="closeCreateModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('admin/users')) ?>">
                <input type="hidden" name="action" value="create_user">
                <div class="admin-modal-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Name <span class="required">*</span></label>
                        <input type="text" name="name" class="admin-form-input" placeholder="Enter full name" maxlength="100" required value="<?= htmlspecialchars($oldCreate['name'] ?? '') ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="admin-form-input" placeholder="user@example.com" required value="<?= htmlspecialchars($oldCreate['email'] ?? '') ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Phone <span class="required">*</span></label>
                        <input type="text" name="phone" class="admin-form-input" placeholder="Enter phone number" required value="<?= htmlspecialchars($oldCreate['phone'] ?? '') ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" class="admin-form-input" placeholder="Minimum 6 characters" minlength="6" required>
                        <div class="admin-form-hint">Password will be securely hashed before storage.</div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Role <span class="required">*</span></label>
                        <select name="role" class="admin-form-select" required>
                            <option value="tenant" <?= ($oldCreate['role'] ?? '') === 'tenant' ? 'selected' : '' ?>>Tenant</option>
                            <option value="owner" <?= ($oldCreate['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner</option>
                            <option value="admin" <?= ($oldCreate['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="admin-btn" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="admin-btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <?php
    $oldEdit = $_SESSION['old_edit'] ?? null;
    unset($_SESSION['old_edit']);
    ?>
    <div class="admin-modal-overlay" id="edit-modal">
        <div class="admin-modal">
            <div class="admin-modal-header">
                <h3 class="admin-modal-title">Edit User</h3>
                <button class="admin-modal-close" onclick="closeEditModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('admin/users')) ?>" id="edit-user-form">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="admin-modal-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Name <span class="required">*</span></label>
                        <input type="text" name="name" id="edit-name" class="admin-form-input" placeholder="Enter full name" maxlength="100" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="edit-email" class="admin-form-input" placeholder="user@example.com" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Phone <span class="required">*</span></label>
                        <input type="text" name="phone" id="edit-phone" class="admin-form-input" placeholder="Enter phone number" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">New Password</label>
                        <input type="password" name="password" id="edit-password" class="admin-form-input" placeholder="Leave blank to keep current password" minlength="6">
                        <div class="admin-form-hint">Leave empty to keep the existing password.</div>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Role <span class="required">*</span></label>
                        <select name="role" id="edit-role" class="admin-form-select" required>
                            <option value="tenant">Tenant</option>
                            <option value="owner">Owner</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="admin-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="admin-btn-primary">Save Changes</button>
                </div>
            </form>
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

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ─── View User Modal ──────────────────────────────────
    function openUserModal(user) {
        var roleClass = 'admin-badge-role';
        if (user.role === 'owner') roleClass = 'admin-badge-owner';
        else if (user.role === 'tenant') roleClass = 'admin-badge-tenant';

        var html = '<div class="admin-modal-field"><span class="admin-modal-label">ID</span><span class="admin-modal-value">' + user.id + '</span></div>';
        html += '<div class="admin-modal-field"><span class="admin-modal-label">Name</span><span class="admin-modal-value">' + escapeHtml(user.name) + '</span></div>';
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

    // ─── Create User Modal ────────────────────────────────
    function openCreateModal() {
        document.getElementById('create-modal').classList.add('open');
    }

    function closeCreateModal() {
        document.getElementById('create-modal').classList.remove('open');
    }

    // ─── Edit User Modal ──────────────────────────────────
    function openEditModal(user) {
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-name').value = user.name;
        document.getElementById('edit-email').value = user.email;
        document.getElementById('edit-phone').value = user.phone;
        document.getElementById('edit-role').value = user.role;
        document.getElementById('edit-password').value = '';
        document.getElementById('edit-modal').classList.add('open');
    }

    function closeEditModal() {
        document.getElementById('edit-modal').classList.remove('open');
    }

    // ─── Delete Confirmation ──────────────────────────────
    function confirmDeleteUser(userId, userName) {
        if (confirm('Are you sure you want to delete user "' + userName + '"?\n\nThis will also remove all their rooms, bookings, bookmarks, and messages. This action cannot be undone.')) {
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-user-form').submit();
        }
    }

    // ─── Close modals on overlay click / Escape ───────────
    ['user-modal', 'create-modal', 'edit-modal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('user-modal').classList.remove('open');
            document.getElementById('create-modal').classList.remove('open');
            document.getElementById('edit-modal').classList.remove('open');
        }
    });

    <?php if ($oldEdit !== null): ?>
    (function() {
        var editData = <?= json_encode($oldEdit) ?>;
        document.getElementById('edit-user-id').value = editData.id;
        document.getElementById('edit-name').value = editData.name;
        document.getElementById('edit-email').value = editData.email;
        document.getElementById('edit-phone').value = editData.phone;
        document.getElementById('edit-role').value = editData.role;
        document.getElementById('edit-modal').classList.add('open');
    })();
    <?php endif; ?>
    </script>

</body>

</html>
