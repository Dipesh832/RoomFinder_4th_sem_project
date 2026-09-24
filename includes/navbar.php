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
if ($role === 'admin') {
    $profileUrl = base_url('admin/dashboard');
} elseif ($role === 'owner') {
    $profileUrl = base_url('owner/dashboard');
} else {
    $profileUrl = base_url('tenant/dashboard');
}

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

/*
 * Precompute nav items for reuse in the desktop links row and the
 * mobile menu so active-state logic lives in exactly one place.
 */
$renderedNavItems = [];
foreach ($navItems as $item) {
    $href = $item['file'];
    $isActive = false;
    // Only check active state for local file links (not external or anchors)
    if (strpos($href, '#') === false && strpos($href, 'http') === false) {
        $isActive = $currentPage === basename($href);
    }
    $renderedNavItems[] = [
        'href'  => htmlspecialchars($href),
        'label' => htmlspecialchars($item['label']),
        'class' => $isActive ? 'active' : '',
    ];
}

// Auth buttons, reused in the desktop actions row and the mobile menu.
$authLinks = $isLoggedIn ? [] : [
    ['label' => 'Log in',   'href' => $loginUrl,    'class' => 'navbar-btn navbar-btn-login'],
    ['label' => 'Register', 'href' => $registerUrl, 'class' => 'navbar-btn navbar-btn-register'],
];
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
            <?php foreach ($renderedNavItems as $item): ?>
                <a href="<?= $item['href'] ?>" class="<?= $item['class'] ?>">
                    <?= $item['label'] ?>
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

                <?php foreach ($authLinks as $link): ?>
                    <a href="<?= htmlspecialchars($link['href']) ?>" class="<?= htmlspecialchars($link['class']) ?>">
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <button
            class="navbar-toggle"
            id="navbar-toggle"
            type="button"
            aria-label="Toggle navigation menu"
            aria-controls="navbar-menu"
            aria-expanded="false"
        >
            <svg class="navbar-toggle-icon navbar-toggle-open" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <line x1="3" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <line x1="3" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <svg class="navbar-toggle-icon navbar-toggle-close" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>

    </div>

    <div class="navbar-menu" id="navbar-menu">
        <?php foreach ($renderedNavItems as $item): ?>
            <a href="<?= $item['href'] ?>" class="navbar-menu-link <?= $item['class'] ?>">
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>

        <?php if (!$isLoggedIn): ?>
            <?php foreach ($authLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['href']) ?>" class="navbar-menu-link navbar-menu-auth <?= htmlspecialchars($link['class']) ?>">
                    <?= htmlspecialchars($link['label']) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</nav>

<script>
(function () {
    var profile = document.getElementById('navbar-profile');
    var dropdown = document.getElementById('profile-dropdown');
    var toggle = document.getElementById('navbar-toggle');
    var menu = document.getElementById('navbar-menu');

    function closeAll() {
        if (dropdown) dropdown.classList.remove('open');
        if (menu) menu.classList.remove('open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    // Profile dropdown
    if (profile && dropdown) {
        profile.addEventListener('click', function (e) {
            e.stopPropagation();
            if (menu && menu.classList.contains('open')) closeAll();
            dropdown.classList.toggle('open');
        });
    }

    // Mobile menu toggle
    if (toggle && menu) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (dropdown) dropdown.classList.remove('open');
        });

        // Close the menu after choosing a link.
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                closeAll();
            });
        });
    }

    // Outside click closes the dropdown and menu.
    document.addEventListener('click', function (e) {
        if (dropdown && profile && !dropdown.contains(e.target) && !profile.contains(e.target)) {
            dropdown.classList.remove('open');
        }
        if (menu && toggle && !menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.classList.remove('open');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll();
        }
    });
})();
</script>
