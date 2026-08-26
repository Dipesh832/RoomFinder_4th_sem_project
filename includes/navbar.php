<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar">
    <div class="navbar-container">

        <a href="dashboard.php" class="navbar-logo">
            RoomFinder
        </a>

        <div class="navbar-links">

            <a href="dashboard.php"
               class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                Home
            </a>

            <a href="rooms.php"
               class="<?php echo $currentPage === 'rooms.php' ? 'active' : ''; ?>">
                My Rooms
            </a>

            <a href="add-room.php"
               class="<?php echo $currentPage === 'add-room.php' ? 'active' : ''; ?>">
                Add Room
            </a>

            <a href="bookings.php"
               class="<?php echo $currentPage === 'bookings.php' ? 'active' : ''; ?>">
                Booking Request
            </a>

        </div>

        <div class="navbar-actions">

            <button class="navbar-icon" type="button" aria-label="Messages">
                <span>💬</span>
            </button>

            <button class="navbar-icon" type="button" aria-label="Notifications">
                <span>🔔</span>
            </button>

            <div class="navbar-profile">

                <div class="profile-avatar">
                    D
                </div>

                <span class="profile-name">
                    Dipesh Tharu
                </span>

                <span class="profile-arrow">
                    ⌄
                </span>

            </div>

        </div>

    </div>
</nav>