<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/config.php";

$role = $_GET["role"] ?? "";

if ($role !== "owner" && $role !== "tenant") {
    redirect("auth/account-type");
}

$errors = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'confirmpassword' => '',
    'terms' => ''
];

$old = [
    'name' => '',
    'email' => '',
    'phone' => ''
];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmpassword = $_POST["confirmpassword"] ?? "";

    $old['name'] = $name;
    $old['email'] = $email;
    $old['phone'] = $phone;

    if (empty($name)) {
        $errors['name'] = "This field is required";
    }

    if (empty($email)) {
        $errors['email'] = "This field is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    if (empty($phone)) {
        $errors['phone'] = "This field is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = "Phone number must be 10 digits";
    }
    if (empty($password)) {
        $errors['password'] = "This field is required";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    }
    if (empty($confirmpassword)) {
        $errors['confirmpassword'] = "This field is required.";
    } elseif ($password !== $confirmpassword) {
        $errors['confirmpassword'] = "Passwords do not match.";
    }
    if (!isset($_POST['terms'])) {
        $errors['terms'] = "You must agree to the Terms & Conditions.";
    }

    if (!array_filter($errors)) {

        $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "s", $email);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {

            $errors['email'] = "This email is already registered.";

        }
    }
    if (!array_filter($errors)) {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, phone, role, password)
            VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $name,
            $email,
            $phone,
            $role,
            $hashedPassword
        );

        if (mysqli_stmt_execute($stmt)) {

            $_SESSION['success'] = "Registration successful. Please login.";
            redirect("auth/login");

        } else {

            echo "Registration failed: " . mysqli_stmt_error($stmt);
        }
    }
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - RoomFinder</title>
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
                        <h1>Find Your Perfect Room</h1>
                        <p>Join RoomFinder to discover verified rooms, connect directly with property owners, and enjoy
                            a
                            safe, hassle-free rental experience.</p>

                        <img src="../assets/images/auth-illustration.png" alt="">
                    </div>
                </div>
            </div>
            <div class="auth-right">
                <div class="auth-right-content">
                    <div class="auth-header">
                        <h1 class="form-heading">Create an Account</h1>
                        <p class="form-subheading">Join RoomFinder and find your perfect room today.</p>
                    </div>
                    <form class="auth-form" action="" method="POST">
                        <div class="form-group">
                            <input type="text" id="name" name="name" placeholder="Full Name"
                                value="<?php echo htmlspecialchars($old['name']) ?>" required>
                            <?php if ($errors['name'] !== ""): ?>
                                <span class="error">
                                    <?php echo htmlspecialchars($errors['name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
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
                            <input type="tel" id="phone" name="phone" placeholder="Phone Number"
                                value="<?php echo htmlspecialchars($old['phone']) ?>" required>
                            <?php if ($errors['phone'] !== ""): ?>
                                <span class="error">
                                    <?php echo htmlspecialchars($errors['phone']) ?>
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
                        <div class="form-group">
                            <input type="password" id="confirmpassword" name="confirmpassword"
                                placeholder="Confirm Password" required>
                            <?php if ($errors['confirmpassword'] !== ""): ?>
                                <span class="error">
                                    <?php echo htmlspecialchars($errors['confirmpassword']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms"> I agree to the <span>Terms & Conditions</span> and <span>Privacy
                                    Policy</span>.
                            </label>
                            <?php if ($errors['terms'] !== ""): ?>
                                <span class="error">
                                    <?= htmlspecialchars($errors['terms']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <button class="btn">Create Account</button>
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
                        <p class="auth-switch">Already have an Account? <a href="<?= base_url('auth/login') ?>">Log
                                in</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>


</body>

</html>