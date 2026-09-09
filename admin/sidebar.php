<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminSidebarActive = $adminSidebarActive ?? 'dashboard';
$adminSidebarUserName = $_SESSION['user']['name'] ?? 'Admin';
$adminSidebarInitial = strtoupper(mb_substr($adminSidebarUserName, 0, 1));
?>

<aside class="admin-sidebar">

    <div class="admin-sidebar-brand">
        <div class="admin-sidebar-brand-title">RoomFinder</div>
        <div class="admin-sidebar-brand-sub">Admin Panel</div>
    </div>

    <div class="admin-sidebar-divider"></div>

    <ul class="admin-sidebar-nav">
        <li>
            <a href="<?= htmlspecialchars(base_url('admin/dashboard')) ?>" class="<?= $adminSidebarActive === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1" />
                        <rect x="14" y="3" width="7" height="5" rx="1" />
                        <rect x="14" y="12" width="7" height="9" rx="1" />
                        <rect x="3" y="16" width="7" height="5" rx="1" />
                    </svg>
                </span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="<?= htmlspecialchars(base_url('admin/users')) ?>" class="<?= $adminSidebarActive === 'users' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </span>
                Manage Users
            </a>
        </li>
        <li>
            <a href="<?= htmlspecialchars(base_url('admin/rooms')) ?>" class="<?= $adminSidebarActive === 'rooms' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 10.5L12 3L21 10.5" />
                        <path d="M5 9.5V20H19V9.5" />
                        <path d="M9 20V14H15V20" />
                    </svg>
                </span>
                Manage Rooms
            </a>
        </li>
        <li>
            <a href="<?= htmlspecialchars(base_url('admin/bookings')) ?>" class="<?= $adminSidebarActive === 'bookings' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="5" width="17" height="16" rx="2" />
                        <path d="M7 3V7" />
                        <path d="M17 3V7" />
                        <path d="M3.5 10H20.5" />
                    </svg>
                </span>
                Monitor Bookings
            </a>
        </li>
        <li>
            <a href="<?= htmlspecialchars(base_url('admin/monitor')) ?>" class="<?= $adminSidebarActive === 'monitor' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>
                </span>
                System Monitor
            </a>
        </li>
    </ul>

    <div class="admin-sidebar-footer">
        <a href="<?= htmlspecialchars(base_url('auth/logout')) ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </span>
            Logout
        </a>
    </div>

</aside>
