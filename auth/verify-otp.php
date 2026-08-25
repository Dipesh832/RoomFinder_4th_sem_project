<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['reset_user_id'])) {
    redirect('auth/forgot-password');
}

$errors = [
    'otp' => ''
];

$otpExpiresAt = null;

$userId = $_SESSION['reset_user_id'];

$sql = "SELECT reset_expires_at
        FROM users
        WHERE id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $userId);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

if ($user && $user['reset_expires_at']) {
    $otpExpiresAt = strtotime($user['reset_expires_at']) * 1000;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp)) {

        $errors['otp'] = 'Please enter the OTP.';

    } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {

        $errors['otp'] = 'OTP must be 6 digits.';
    }

    if (!array_filter($errors)) {

        $userId = $_SESSION['reset_user_id'];

        $sql = "SELECT reset_token, reset_expires_at
                FROM users
                WHERE id = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "i", $userId);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $user = mysqli_fetch_assoc($result);

        if ($user) {

            $otpHash = hash('sha256', $otp);

            if (
                hash_equals($user['reset_token'], $otpHash) &&
                strtotime($user['reset_expires_at']) > time()
            ) {

                $_SESSION['otp_verified'] = true;

                redirect('auth/reset-password');

            } else {

                $errors['otp'] = 'Invalid or expired OTP.';
            }

        } else {

            $errors['otp'] = 'Unable to verify OTP.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify OTP - RoomFinder</title>

    <link rel="stylesheet" href="../style.css">

</head>

<body>

    <div class="auth-container">

        <div class="auth-container-content">

            <div class="auth-left">

                <div class="auth-left-content">

                    <div class="brand-logo">
                        RoomFinder
                    </div>

                    <div class="hero-content">

                        <h1>Verify Your Account</h1>

                        <p>
                            Enter the 6-digit OTP sent to your email
                            address to continue resetting your password.
                        </p>

                        <img src="../assets/images/auth-illustration.png" alt="OTP verification">

                    </div>

                </div>

            </div>

            <div class="auth-right">

                <div class="auth-right-content">

                    <div class="auth-header">

                        <h1 class="form-heading">
                            Verify OTP
                        </h1>

                        <p class="form-subheading">
                            Enter the 6-digit OTP to continue.
                        </p>

                    </div>

                    <?= messages() ?>
                    <div class="otp-timer">
                        OTP expires in:
                        <strong id="otp-countdown">10:00</strong>
                    </div>

                    <form class="auth-form" action="" method="POST">

                        <div class="form-group">

                            <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP" inputmode="numeric"
                                maxlength="6" autocomplete="one-time-code" required>

                            <?php if ($errors['otp'] !== ''): ?>

                                <span class="error">
                                    <?= htmlspecialchars($errors['otp']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <button type="submit" class="btn">
                            Verify OTP
                        </button>

                        <p class="auth-switch">

                            Didn't receive the OTP?

                            <a href="<?= base_url('auth/forgot-password') ?>">
                                Try again
                            </a>

                        </p>

                    </form>

                </div>

            </div>

        </div>

    </div>
    <script>

        const expiryTime = <?= $otpExpiresAt ?? 0 ?>;

        const countdown = document.getElementById('otp-countdown');

        const verifyButton = document.querySelector(
            'button[type="submit"]'
        );

        function updateCountdown() {

            const remaining = expiryTime - Date.now();

            if (remaining <= 0) {

                countdown.textContent = 'Expired';

                verifyButton.disabled = true;

                return;
            }

            const totalSeconds = Math.floor(
                remaining / 1000
            );

            const minutes = Math.floor(
                totalSeconds / 60
            );

            const seconds = totalSeconds % 60;

            countdown.textContent =
                String(minutes).padStart(2, '0') +
                ':' +
                String(seconds).padStart(2, '0');
        }

        updateCountdown();

        const timer = setInterval(() => {

            updateCountdown();

            if (expiryTime - Date.now() <= 0) {
                clearInterval(timer);
            }

        }, 1000);

    </script>

</body>

</html>