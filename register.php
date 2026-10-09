<?php
session_start();

$error = $_SESSION['register_error'] ?? '';
$success = $_SESSION['register_success'] ?? '';
unset($_SESSION['register_error'], $_SESSION['register_success']);

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>

<header class="site-header is-solid" id="siteHeader">
    <div class="wrap">
        <a href="index.php" class="logo">
            <svg class="wheat-mark" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M20 4v30"/>
                <path d="M20 10 L12 5 M20 10 L28 5"/>
                <path d="M20 16 L11 10 M20 16 L29 10"/>
                <path d="M20 22 L11 16 M20 22 L29 16"/>
                <path d="M20 28 L13 23 M20 28 L27 23"/>
            </svg>
            <span class="logo-text"><b>AgriMart</b><span>Field to Farm Gate</span></span>
        </a>

        <nav class="main-nav">
            <a href="index.php">Home</a>
            <a href="products.php">Products</a>
            <a href="equipment.php">Equipment</a>
            <a href="index.php#how-it-works">How It Works</a>
        </nav>

        <div class="header-actions">
            <a href="login.php" class="btn btn-light">Login</a>
            <a href="register.php" class="btn btn-solid">Join AgriMart</a>
        </div>
    </div>
</header>

<main class="auth-page">
    <section class="auth-card">
        <h1>Create your account</h1>
        <p class="auth-intro">
            Sell agricultural produce, list farm machinery, rent tools, or purchase seeds using one verified AgriMart account.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form id="registerForm" action="register_process.php" method="POST">
            <!-- FULL NAME -->
            <div class="field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required autofocus placeholder="Juan Dela Cruz">
            </div>

            <!-- EMAIL + PHONE -->
            <div class="field-row">
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="juan@example.com">
                </div>
                <div class="field">
                    <label for="phone">Mobile Number</label>
                    <input type="text" id="phone" name="phone" placeholder="09171234567">
                </div>
            </div>

            <!-- PASSWORDS -->
            <div class="field-row">
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" required placeholder="Enter password">
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required placeholder="Confirm password">
                </div>
            </div>

            <!-- SHOW PASSWORD CHECKBOX -->
            <div class="custom-checkbox-wrap" style="margin-top:-6px; margin-bottom:20px;">
                <input type="checkbox" id="showPasswordToggle" onchange="togglePasswords(this.checked)">
                <label for="showPasswordToggle">Show password</label>
            </div>

            <!-- TERMS & CONDITIONS -->
            <div class="custom-checkbox-wrap" style="margin-bottom:24px;">
                <input type="checkbox" id="terms" name="terms" value="1" required>
                <label for="terms">I agree to AgriMart Terms and Conditions</label>
            </div>

            <button type="submit" class="btn btn-solid btn-block">
                Create Account
            </button>
        </form>

        <p class="auth-switch">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    </section>
</main>

<script>
function togglePasswords(show) {
    const p1 = document.getElementById('password');
    const p2 = document.getElementById('confirm_password');
    if (p1) p1.type = show ? 'text' : 'password';
    if (p2) p2.type = show ? 'text' : 'password';
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pass !== confirm) {
        e.preventDefault();
        alert('Passwords do not match. Please verify and try again.');
        document.getElementById('confirm_password').focus();
    }
});
</script>
<script src="js/main.js"></script>
</body>
</html>
