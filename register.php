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

        <form id="registerForm" action="register_process.php" method="POST" enctype="multipart/form-data">
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
                    <input type="text" id="phone" name="phone" required placeholder="09171234567">
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

            <!-- IDENTITY / VALID ID VERIFICATION -->
            <div style="background:#fff; border:1px solid #d4c79c; padding:18px 20px; margin:20px 0 24px;">
                <span style="font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#768047; font-weight:700; display:block; margin-bottom:6px;">
                    Identity & Farmer Verification
                </span>
                <p style="font-size:13px; color:#686454; margin:0 0 16px; line-height:1.4;">
                    To protect our marketplace from fraudulent accounts, please provide a valid government ID or RSBSA farmer registration.
                </p>

                <div class="field-row">
                    <div class="field" style="margin-bottom:14px;">
                        <label for="id_type">Valid ID Type</label>
                        <select id="id_type" name="id_type" required>
                            <option value="">Select ID Type...</option>
                            <option value="Philippine National ID">Philippine National ID (PhilSys)</option>
                            <option value="Farmer RSBSA ID">Farmer RSBSA Registry ID</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="UMID / SSS">UMID / SSS Card</option>
                            <option value="PhilHealth ID">PhilHealth ID</option>
                            <option value="Voter's ID">Voter's ID / Certificate</option>
                            <option value="Postal ID">Postal ID</option>
                            <option value="Barangay Certificate">Barangay Certificate / Clearance</option>
                        </select>
                    </div>

                    <div class="field" style="margin-bottom:14px;">
                        <label for="id_number">ID Number</label>
                        <input type="text" id="id_number" name="id_number" required placeholder="1234-5678-9012">
                    </div>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label for="id_card_image">Upload Photo of Valid ID</label>
                    <input type="file" id="id_card_image" name="id_card_image" accept="image/*" required style="padding:10px;">
                    <span class="field-hint">Upload a clear photo (JPG, PNG, WebP) of your ID.</span>
                </div>
            </div>

            <!-- TERMS & CONDITIONS -->
            <div class="field" style="display:flex; align-items:center; gap:10px; margin-top:10px;">
                <input type="checkbox" id="terms" name="terms" value="1" required style="width:auto; cursor:pointer;">
                <label for="terms" style="margin-bottom:0; font-family:inherit; font-size:13px; letter-spacing:0; text-transform:none; color:#5e604e; cursor:pointer;">
                    I agree to AgriMart Terms and Conditions
                </label>
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

<script src="js/main.js"></script>
</body>
</html>
