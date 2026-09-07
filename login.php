<?php
session_start();

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — AgriMart</title>
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
        <h1>Welcome Back</h1>
        <p class="auth-intro">
            Sign in to manage your marketplace listings, view orders, and book farm machinery.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form id="loginForm" action="login_process.php" method="POST">
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus placeholder="yourname@domain.com">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-solid btn-block">
                Sign In
            </button>
        </form>

        <p class="auth-switch">
            Don't have an account? <a href="register.php">Create Account</a>
        </p>
    </section>
</main>

<script src="js/main.js"></script>
</body>
</html>
