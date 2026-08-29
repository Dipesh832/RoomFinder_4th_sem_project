<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$errors = [
    'email' => ''
];

$old = [
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    $old['email'] = $email;

    if (empty($email)) {

        $errors['email'] = 'This field is required';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors['email'] = 'Invalid email format';
    }

    if (!array_filter($errors)) {

        $sql = "SELECT id, email FROM users WHERE email = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "s", $email);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $user = mysqli_fetch_assoc($result);

        if ($user) {

            // Generate a 6-digit OTP
            $otp = random_int(100000, 999999);

            // Hash the OTP before storing it
            $otpHash = hash('sha256', (string) $otp);

            // OTP expires after 10 minutes
            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + (10 * 60)
            );

            $sql = "UPDATE users
            SET reset_token = ?,
                reset_expires_at = ?
            WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $otpHash,
                $expiresAt,
                $user['id']
            );

            if (mysqli_stmt_execute($stmt)) {

                // Temporary development storage
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $user['email'];

                $_SESSION['success'] = 'OTP generated successfully.';

                redirect('auth/verify-otp');

            } else {

                $_SESSION['error'] =
                    'Something went wrong. Please try again.';

                redirect('auth/forgot-password');   
            }

        } else {

            $_SESSION['error'] = 'No account found with this email address.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password - RoomFinder</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
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

                        <h1>Reset Your Password</h1>

                        <p>
                            Enter your email address and we'll help you
                            reset your RoomFinder account password.
                        </p>

                        <img src="../assets/images/auth-illustration.png" alt="Password reset">

                    </div>

                </div>

            </div>


            <div class="auth-right">

                <div class="auth-right-content">

                    <div class="auth-header">

                        <h1 class="form-heading">
                            Forgot Password?
                        </h1>

                        <p class="form-subheading">
                            Enter your email address to reset your password.
                        </p>

                    </div>

                    <?= messages() ?>

                    <?php if (isset($_SESSION['reset_otp'])): ?>

                        <div class="alert alert-success">
                            Development OTP:
                            <strong>
                                <?= htmlspecialchars($_SESSION['reset_otp']) ?>
                            </strong>
                        </div>

                        <?php unset($_SESSION['reset_otp']); ?>

                    <?php endif; ?>
                    <form class="auth-form" action="" method="POST">

                        <div class="form-group">

                            <input type="email" id="email" name="email" placeholder="Email Address" autocomplete="email"
                                value="<?= htmlspecialchars($old['email']) ?>" required>

                            <?php if ($errors['email'] !== ''): ?>

                                <span class="error">
                                    <?= htmlspecialchars($errors['email']) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <button type="submit" class="btn">
                            Send OTP
                        </button>


                        <p class="auth-switch">

                            Remember your password?

                            <a href="<?= base_url('auth/login') ?>">
                                Log in
                            </a>

                        </p>

                    </form>

                </div>

            </div>

        </div>

    </div>

</body>

</html>