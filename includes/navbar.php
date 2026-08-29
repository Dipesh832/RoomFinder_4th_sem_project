<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$phpSelf = $_SERVER['PHP_SELF'];

$isLoggedIn = isset($_SESSION['user']);
$role = $isLoggedIn ? ($_SESSION['user']['role'] ?? '') : '';
$userName = $isLoggedIn ? ($_SESSION['user']['name'] ?? 'User') : '';
$userInitial = strtoupper(substr($userName, 0, 1));

// Detect context from current path
$isOwner = strpos($phpSelf, '/owner/') !== false;
$isTenant = strpos($phpSelf, '/tenant/') !== false;
$isLanding = !$isOwner && !$isTenant;

// Base path prefix for links
$basePath = ($isOwner || $isTenant) ? '../' : '';

// Auth URLs (always absolute via base_url)
$loginUrl    = base_url('auth/login');
$registerUrl = base_url('auth/account-type');
$logoutUrl   = base_url('auth/logout');
$profileUrl  = base_url($role === 'owner' ? 'owner/dashboard' : 'tenant/dashboard');

// Build nav items based on context
$navItems = [];

if ($isOwner) {
    $navItems = [
        ['label' => 'Home',            'file' => 'dashboard.php'],
        ['label' => 'My Rooms',        'file' => 'rooms.php'],
        ['label' => 'Add Room',        'file' => 'add-room.php'],
        ['label' => 'Booking Request', 'file' => 'bookings.php'],
    ];
} elseif ($isTenant) {
    $navItems = [
        ['label' => 'Home',          'file' => 'dashboard.php'],
        ['label' => 'Browse Rooms',  'file' => 'rooms.php'],
        ['label' => 'My Bookings',   'file' => 'bookings.php'],
        ['label' => 'Saved',         'file' => 'saved.php'],
    ];
} else {
    $navItems = [
        ['label' => 'Home',           'file' => base_url('')],
        ['label' => 'Browse Rooms',   'file' => base_url('rooms')],
        ['label' => 'How It Works',   'file' => '#how-it-works'],
        ['label' => 'About',          'file' => '#about'],
        ['label' => 'Contact',        'file' => '#contact'],
    ];
}
?>

<nav class="navbar">
    <div class="navbar-container">

        <?php if ($isOwner): ?>
            <a href="dashboard.php" class="navbar-logo">RoomFinder</a>
        <?php elseif ($isTenant): ?>
            <a href="dashboard.php" class="navbar-logo">RoomFinder</a>
        <?php else: ?>
            <a href="<?= base_url('') ?>" class="navbar-logo">RoomFinder</a>
        <?php endif; ?>

        <div class="navbar-links">
            <?php foreach ($navItems as $item): ?>
                <?php
                    $isActive = false;
                    $href = $item['file'];
                    // Only check active state for local file links (not external or anchors)
                    if (strpos($href, '#') === false && strpos($href, 'http') === false) {
                        $isActive = $currentPage === basename($href);
                    }
                ?>
                <a href="<?= htmlspecialchars($href) ?>"
                   class="<?= $isActive ? 'active' : '' ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="navbar-actions">

            <?php if ($isLoggedIn): ?>

                <?php if ($isOwner || $isTenant): ?>
                    <button class="navbar-icon" type="button" aria-label="Messages">
                        <span>&#128172;</span>
                    </button>
                <?php endif; ?>

                <div class="navbar-profile" id="navbar-profile">
                    <div class="profile-avatar">
                        <?= htmlspecialchars($userInitial) ?>
                    </div>
                    <span class="profile-name">
                        <?= htmlspecialchars($userName) ?>
                    </span>
                    <span class="profile-arrow">&#8964;</span>

                    <div class="profile-dropdown" id="profile-dropdown">
                        <a href="<?= htmlspecialchars($profileUrl) ?>" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Profile
                        </a>
                        <a href="<?= htmlspecialchars($logoutUrl) ?>" class="dropdown-item dropdown-item-logout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Log out
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <a href="<?= htmlspecialchars($loginUrl) ?>" class="navbar-btn navbar-btn-login">Log in</a>
                <a href="<?= htmlspecialchars($registerUrl) ?>" class="navbar-btn navbar-btn-register">Register</a>

            <?php endif; ?>

        </div>

    </div>
</nav>

<script>
(function() {
    var profile = document.getElementById('navbar-profile');
    var dropdown = document.getElementById('profile-dropdown');
    if (!profile || !dropdown) return;

    profile.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });

    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && !profile.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdown.classList.remove('open');
        }
    });
})();
</script>
