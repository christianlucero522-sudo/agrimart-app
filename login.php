<?php
session_start();

require_once 'config.php';

$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_success']);

$prefillEmail = trim($_GET['email'] ?? '');
if (!empty($prefillEmail)) {
    // Proactively sync verification state from Clever Cloud (e.g. if user verified on phone via Vercel)
    syncUserVerificationWithCloud($prefillEmail, $conn);
    $vCheck = $conn->prepare("SELECT email_verified FROM users WHERE email = ? LIMIT 1");
    if ($vCheck) {
        $vCheck->bind_param('s', $prefillEmail);
        $vCheck->execute();
        $vRow = $vCheck->get_result()->fetch_assoc();
        $vCheck->close();
        if ($vRow && (int)$vRow['email_verified'] === 1 && strpos($error, 'verify your email') !== false) {
            $error = '';
            $success = "Your email address is verified! Please enter your password to sign in.";
        }
    }
}

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
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Social Login Options -->
        <div class="social-auth-grid">
            <a href="oauth.php?provider=google&action=login" class="btn-social btn-google" title="Sign in with Google">
                <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                <span>Continue with Google</span>
            </a>
            <a href="oauth.php?provider=facebook&action=login" class="btn-social btn-facebook" title="Sign in with Facebook">
                <svg viewBox="0 0 24 24" fill="#ffffff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                <span>Continue with Facebook</span>
            </a>
        </div>

        <div class="auth-divider">
            <span>or sign in with email</span>
        </div>

        <form id="loginForm" action="login_process.php" method="POST">
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($prefillEmail) ?>" placeholder="yourname@domain.com">
            </div>

            <div class="field">
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <label for="password">Password</label>
                    <a href="resend_verification.php<?= $prefillEmail !== '' ? '?email=' . urlencode($prefillEmail) : '' ?>" style="font-size:11px; color:#768047; text-decoration:none; font-family:monospace; text-transform:uppercase; letter-spacing:0.5px;">Resend Verification?</a>
                </div>
                <input type="password" id="password" name="password" required placeholder="Enter password">
            </div>

            <!-- SHOW PASSWORD CHECKBOX -->
            <div class="custom-checkbox-wrap" style="margin-top:-8px; margin-bottom:24px;">
                <input type="checkbox" id="showPasswordToggle" onchange="document.getElementById('password').type = this.checked ? 'text' : 'password';">
                <label for="showPasswordToggle">Show password</label>
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
