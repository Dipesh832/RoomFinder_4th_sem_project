<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/require_owner.php';
?>
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

// Make sure the user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

$userName = $_SESSION['user']['name'] ?? 'Owner';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Owner Dashboard | RoomFinder</title>

    <link rel="stylesheet" href="../style.css">

    
</head>

<body>

    <?php include '../includes/navbar.php'; ?>


    <!-- ========================================
         OWNER DASHBOARD
    ========================================= -->

    <main class="owner-dashboard">

        <!-- HERO SECTION -->

        <section class="owner-hero">

            <div class="owner-hero-container">

                <!-- Welcome Badge -->

                <div class="owner-welcome">
                    Welcome back, <?= htmlspecialchars($userName) ?>
                </div>


                <!-- Main Heading -->

                <h1 class="owner-hero-title">
                    Manage your rooms,<br>
                    bookings, and <span>tenants</span>
                </h1>


                <!-- Description -->

                <p class="owner-hero-description">
                    Everything you need to manage your properties,
                    handle bookings, and connect with tenants.
                </p>


                <!-- Action Button -->

                <div class="owner-hero-action">

                    <a href="add-room.php" class="add-room-btn">

                        <svg
                            class="add-room-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 5V19"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />

                            <path
                                d="M5 12H19"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />
                        </svg>

                        Add Room

                    </a>

                </div>

            </div>

        </section>


        <!--
        ========================================
        NEXT DASHBOARD SECTION
        ========================================

        We will add the dashboard statistics/cards
        here next.

        Example:

        - Total Rooms
        - Available Rooms
        - Booked Rooms
        - Pending Requests

        ========================================
        -->


    </main>

</body>
</html>