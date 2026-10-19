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
    <link rel="stylesheet" href="css/style.css?v=<?= file_exists(__DIR__ . '/css/style.css') ? filemtime(__DIR__ . '/css/style.css') : '2.1' ?>">
    <link rel="stylesheet" href="css/auth.css?v=<?= file_exists(__DIR__ . '/css/auth.css') ? filemtime(__DIR__ . '/css/auth.css') : '2.1' ?>">
    <style>
        /* Failsafe to guarantee exact icon sizing even if browser has older CSS cached */
        .social-icon-row { display: flex !important; justify-content: center !important; align-items: center !important; gap: 16px !important; margin: 18px 0 22px !important; }
        .btn-social-icon { width: 48px !important; height: 48px !important; min-width: 48px !important; max-width: 48px !important; border-radius: 50% !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border: 1px solid #d4c79c !important; background: #ffffff !important; box-shadow: 0 2px 6px rgba(0,0,0,0.06) !important; text-decoration: none !important; box-sizing: border-box !important; }
        .btn-social-icon svg { width: 22px !important; height: 22px !important; max-width: 22px !important; max-height: 22px !important; min-width: 22px !important; min-height: 22px !important; display: block !important; flex-shrink: 0 !important; }
        .btn-social-icon.btn-facebook { background: #1877F2 !important; border-color: #1877F2 !important; }
    </style>
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
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        inputmode="numeric" 
                        maxlength="11" 
                        placeholder="09171234567" 
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                        onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.keyCode === 8 || event.keyCode === 46"
                    >
                    <small style="display:block; font-size:11px; color:#6b6a59; margin-top:4px;">Digits only (e.g. 09171234567)</small>
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

        <!-- Social Registration Options (Bottom, Icons Only) -->
        <div class="auth-divider">
            <span>or continue to</span>
        </div>

        <div class="social-icon-row" style="display:flex; justify-content:center; align-items:center; gap:16px; margin:18px 0 22px;">
            <a href="oauth.php?provider=google&action=register" class="btn-social-icon btn-google" title="Continue with Google" aria-label="Google" style="width:48px; height:48px; min-width:48px; max-width:48px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; border:1px solid #d4c79c; background:#ffffff; box-shadow:0 2px 6px rgba(0,0,0,0.06); text-decoration:none;">
                <svg viewBox="0 0 24 24" width="22" height="22" style="width:22px !important; height:22px !important; max-width:22px; max-height:22px; display:block;"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
            </a>
            <a href="oauth.php?provider=facebook&action=register" class="btn-social-icon btn-facebook" title="Continue with Facebook" aria-label="Facebook" style="width:48px; height:48px; min-width:48px; max-width:48px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; border:1px solid #1877F2; background:#1877F2; box-shadow:0 2px 6px rgba(24,119,242,0.2); text-decoration:none;">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" style="width:22px !important; height:22px !important; max-width:22px; max-height:22px; display:block;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
        </div>

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

const phoneInput = document.getElementById('phone');
if (phoneInput) {
    phoneInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    phoneInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        this.value = text.replace(/[^0-9]/g, '').slice(0, 11);
    });
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    const phone = document.getElementById('phone').value.trim();
    if (phone !== '') {
        if (!/^[0-9]{11}$/.test(phone)) {
            e.preventDefault();
            alert('Please enter a valid 11-digit mobile number (e.g. 09171234567).');
            document.getElementById('phone').focus();
            return;
        }
    }

    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pass !== confirm) {
        e.preventDefault();
        alert('Passwords do not match. Please verify and try again.');
        document.getElementById('confirm_password').focus();
        return;
    }
});
</script>
<script src="js/main.js"></script>
</body>
</html>
