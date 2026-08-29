<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (
    !isset($_SESSION['reset_user_id']) ||
    !isset($_SESSION['otp_verified']) ||
    $_SESSION['otp_verified'] !== true
) {
    redirect('auth/forgot-password');
}

$errors = [
    'password' => '',
    'confirmpassword' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirmpassword = $_POST['confirmpassword'] ?? '';

    if (empty($password)) {

        $errors['password'] = 'This field is required';

    } elseif (strlen($password) < 8) {

        $errors['password'] =
            'Password must be at least 8 characters';
    }

    if (empty($confirmpassword)) {

        $errors['confirmpassword'] =
            'This field is required';

    } elseif ($password !== $confirmpassword) {

        $errors['confirmpassword'] =
            'Passwords do not match';
    }

    if (!array_filter($errors)) {

        $userId = $_SESSION['reset_user_id'];

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "UPDATE users
                SET password = ?,
                    reset_token = NULL,
                    reset_expires_at = NULL
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $hashedPassword,
            $userId
        );

        if (mysqli_stmt_execute($stmt)) {

            // Remove password reset session data
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);
            unset($_SESSION['reset_otp']);

            $_SESSION['success'] =
                'Password reset successfully. Please login.';

            redirect('auth/login');

        } else {

            $_SESSION['error'] =
                'Unable to reset password. Please try again.';

            redirect('auth/reset-password');
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password - RoomFinder</title>

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

                        <h1>Create a New Password</h1>

                        <p>
                            Choose a strong password to protect
                            your RoomFinder account.
                        </p>

                        <img src="../assets/images/auth-illustration.png" alt="Reset password">

                    </div>

                </div>

            </div>

            <div class="auth-right">

                <div class="auth-right-content">

                    <div class="auth-header">

                        <h1 class="form-heading">
                            Reset Password
                        </h1>

                        <p class="form-subheading">
                            Enter your new password below.
                        </p>

                    </div>

                    <?= messages() ?>

                    <form class="auth-form" action="" method="POST">

                        <div class="form-group">

                            <input type="password" id="password" name="password" placeholder="New Password"
                                autocomplete="new-password" required>

                            <?php if ($errors['password'] !== ''): ?>

                                <span class="error">
                                    <?= htmlspecialchars($errors['password']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="form-group">

                            <input type="password" id="confirmpassword" name="confirmpassword"
                                placeholder="Confirm New Password" autocomplete="new-password" required>

                            <?php if ($errors['confirmpassword'] !== ''): ?>

                                <span class="error">
                                    <?= htmlspecialchars($errors['confirmpassword']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <button type="submit" class="btn">
                            Reset Password
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</body>

</html>