<?php

    require_once __DIR__ . "/../config/config.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account-Type page</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <div class="account-type-container">
        <div class="account-type-wrapper">
            <div class="account-type-content">
                <div class="account-type-header">
                    <h1>Choose Your Account Type</h1>
                    <p>Tell us how you'll use RoomFinder.
                        You can change this later if needed.</p>

                </div>
                <div class="account-type-options">
                    <div class="account-type-card tenant">
                        <div class="icon-container">
                            <img src="../assets/icons/user-round.svg" alt="tenant">
                        </div>
                        <h2>Tenant</h2>
                        <p>Find and book verified rooms across Nepal with ease.</p>
                        <a href="<?= base_url('auth/register?role=tenant') ?>" class="account-type-btn">Continue as Tenant
                            <img src="../assets/icons/move-right.svg" alt="right-arrow"></a>
                    </div>
                    <div class="account-type-card owner">
                        <div class="icon-container">
                            <img src="../assets/icons/house.svg" alt="owner">
                        </div>
                        <h2>Owner</h2>
                        <p>List your rooms,manage bookings, and connect with tenants.</p>
                        <a href="<?= base_url('auth/register?role=owner') ?>" class="account-type-btn">Continue as Owner <img
                                src="../assets/icons/move-right.svg" alt="right-arrow"></a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</body>

</html>