<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . "/../config/database.php";

$role = $_GET["role"] ?? "";

if ($role !== "owner" && $role !== "tenant") {
    header("Location:account-type.php");
    exit;
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

            echo "Registration successful.";

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
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <h1>Create an Account</h1>

    <form action="" method="POST">
        <div>
            <input type="text" id="name" name="name" placeholder="Full Name" required>
        </div>
        <div>
            <input type="email" id="email" name="email" placeholder="Email Address" required>
        </div>
        <div>
            <input type="tel" id="phone" name="phone" placeholder="Phone Number" required>
        </div>
        <div>
            <input type="password" id="password" name="password" placeholder="Password" required>
        </div>
        <div>
            <input type="password" id="confirmpassword" name="confirmpassword" placeholder="Confirm Password" required>
        </div>
        <div class="terms">
            <input type="checkbox" id="terms" name="terms" required>
            <label for="terms"> I agree to the Terms & Conditions and Privacy Policy.
            </label>
        </div>
        <button>Create Account</button>
    </form>
</body>

</html>