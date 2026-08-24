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
                        <p class="auth-switch">Don't have an Account? <span>Register</span></p>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>

</html>