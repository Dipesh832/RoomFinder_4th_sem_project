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

    <style>
        /* ========================================
           OWNER DASHBOARD
        ======================================== */

        .owner-dashboard {
            min-height: calc(100vh - 72px);
            background: #ECFDF5;
        }

        /* ========================================
           HERO SECTION
        ======================================== */

        .owner-hero {
            padding: 70px 20px 65px;
            text-align: center;
        }

        .owner-hero-container {
            max-width: 850px;
            margin: 0 auto;
        }

        /* Welcome Badge */

        .owner-welcome {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 10px 18px;

            background: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 999px;

            color: #059669;
            font-size: 15px;
            font-weight: 600;

            margin-bottom: 24px;
        }

        /* Heading */

        .owner-hero-title {
            margin: 0;

            color: #111827;

            font-size: clamp(38px, 5vw, 58px);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -1.5px;
        }

        .owner-hero-title span {
            color: #10B981;
        }

        /* Description */

        .owner-hero-description {
            max-width: 620px;
            margin: 22px auto 0;

            color: #64748b;

            font-size: 18px;
            line-height: 1.7;
        }

        /* Button */

        .owner-hero-action {
            margin-top: 32px;
        }

        .add-room-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 13px 22px;

            background: #10B981;
            color: #ffffff;

            border: none;
            border-radius: 8px;

            font-size: 15px;
            font-weight: 600;

            text-decoration: none;

            cursor: pointer;

            transition:
                background-color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .add-room-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.20);
        }

        .add-room-btn:active {
            transform: translateY(0);
        }

        /* Plus Icon */

        .add-room-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 768px) {

            .owner-hero {
                padding: 55px 20px 50px;
            }

            .owner-welcome {
                font-size: 14px;
                padding: 9px 16px;
                margin-bottom: 20px;
            }

            .owner-hero-title {
                font-size: 40px;
                letter-spacing: -1px;
            }

            .owner-hero-description {
                font-size: 16px;
                line-height: 1.6;
                margin-top: 18px;
            }

            .owner-hero-action {
                margin-top: 28px;
            }
        }

        @media (max-width: 480px) {

            .owner-hero {
                padding: 45px 16px 45px;
            }

            .owner-hero-title {
                font-size: 34px;
            }

            .owner-hero-description {
                font-size: 15px;
            }

            .add-room-btn {
                width: 100%;
                max-width: 220px;
            }
        }
    </style>
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