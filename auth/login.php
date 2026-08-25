<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

loginFromRememberToken($conn);

if (isset($_SESSION['user'])) {

    $role = $_SESSION['user']['role'];

    if ($role === 'admin') {
        redirect('admin/dashboard');
    } elseif ($role === 'owner') {
        redirect('owner/dashboard');
    } elseif ($role === 'tenant') {
        redirect('tenant/dashboard');
    } else {
        redirect('/');
    }
}

$errors = [
    'email' => '',
    'password' => ''
];

$old = [
    'email' => $_SESSION['old_email'] ?? ''
];
unset($_SESSION['old_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $old['email'] = $email;

    if (empty($email)) {
        $errors['email'] = 'This field is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors['password'] = 'This field is required';
    }

    if (!array_filter($errors)) {

        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "s", $email);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user'] = $user;
            if ($remember) {

                $token = bin2hex(random_bytes(32));

                $tokenHash = hash('sha256', $token);

                $expiresAt = date(
                    'Y-m-d H:i:s',
                    time() + (30 * 24 * 60 * 60)
                );

                $sql = "UPDATE users
            SET remember_token = ?,
                remember_expires_at = ?
            WHERE id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssi",
                    $tokenHash,
                    $expiresAt,
                    $user['id']
                );

                mysqli_stmt_execute($stmt);
                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/roomfinder',
                        'secure' => false,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );
            }

            if ($user['role'] === 'admin') {
                redirect('admin/dashboard');
            } elseif ($user['role'] === 'owner') {
                redirect('owner/dashboard');
            } elseif ($user['role'] === 'tenant') {
                redirect('tenant/dashboard');
            } else {
                redirect('/');
            }

        } else {

            $_SESSION['error'] = 'Invalid email or password.';
            $_SESSION['old_email'] = $email;
            redirect('auth/login');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RoomFinder</title>
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
                        <h1>Welcome Back!</h1>
                        <p>Join RoomFinder to discover verified rooms, connect directly with property owners, and enjoy
                            a safe, hassle-free rental experience.</p>

                        <img src="../assets/images/auth-illustration.png" alt="">
                    </div>
                </div>
            </div>
            <div class="auth-right">
                <div class="auth-right-content">
                    <div class="auth-header">
                        <h1 class="form-heading">Welcome Back</h1>
                        <p class="form-subheading">Sign in to your RoomFinder account.</p>
                    </div>
                    <?= messages() ?>
                    <form class="auth-form" action="" method="POST">

                        <div class="form-group">
                            <input type="email" id="email" name="email" placeholder="Email Address"
                                value="<?php echo htmlspecialchars($old['email']) ?>" required>
                            <?php if ($errors['email'] !== ""): ?>
                                <span class="error">
                                    <?php echo htmlspecialchars($errors['email']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <input type="password" id="password" name="password" placeholder="Password" required>
                            <?php if ($errors['password'] !== ""): ?>
                                <span class="error">
                                    <?php echo htmlspecialchars($errors['password']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="login-options">
                            <label class="remember-me">
                                <input type="checkbox" name="remember" value="1">
                                <span>Remember me</span>
                            </label>

                            <a href="<?= base_url('auth/forgot-password') ?>" class="forgot-password">
                                Forgot password?
                            </a>
                        </div>

                        <button class="btn">Log in</button>
                        <div class="divider">
                            <span></span>
                            <p>or</p>
                            <span></span>
                        </div>
                        <button type="button" class="google-btn">
                            <img src="../assets/images/google-logo.svg" alt="Google" class="google-icon">
                            <span>Continue with
                                Google</span>
                        </button>
                        <p class="auth-switch">Don't have an Account? <a
                                href="<?= base_url('auth/register') ?>">Register</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>

</html>