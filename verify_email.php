<?php
session_start();
require_once 'config.php';

$token = trim($_GET['token'] ?? '');
$success = false;
$message = '';
$userEmail = '';

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE email_verification_token = ? AND email_verified = 0 LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $uId = (int)$user['user_id'];
        $userEmail = $user['email'];
        $upStmt = $conn->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE user_id = ?");
        $upStmt->bind_param('i', $uId);
        if ($upStmt->execute()) {
            $success = true;
            $message = "Congratulations, " . htmlspecialchars($user['full_name']) . "! Your email has been verified successfully. You can now log in to AgriMart.";
        } else {
            $message = "Database error while verifying email. Please try again.";
        }
        $upStmt->close();
    } else {
        $message = "This verification link is invalid or has already been used.";
    }
} else {
    $message = "No verification token was provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification — AgriMart</title>
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
        </nav>

        <div class="header-actions">
            <a href="login.php" class="btn btn-light">Login</a>
        </div>
    </div>
</header>

<main class="auth-page">
    <section class="auth-card" style="text-align:center; max-width:540px;">
        <?php if ($success): ?>
            <div style="width:64px; height:64px; background:#e0edd5; border:2px solid #768047; border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; color:#23581c; font-size:28px;">
                ✓
            </div>
            <h1 style="font-family:Georgia,serif; font-size:26px; color:#122017; margin-bottom:12px;">Email Verified!</h1>
            <p style="font-size:14.5px; line-height:1.6; color:#2d382e; margin-bottom:28px;">
                <?= $message ?>
            </p>
            <a href="login.php?email=<?= urlencode($userEmail) ?>" class="btn btn-solid" style="display:inline-block; width:100%; box-sizing:border-box; padding:14px; font-size:14px; text-transform:uppercase; letter-spacing:1px;">
                Proceed to Login →
            </a>
        <?php else: ?>
            <div style="width:64px; height:64px; background:#fae6df; border:2px solid #efb7aa; border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; color:#a54129; font-size:26px;">
                ✕
            </div>
            <h1 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin-bottom:12px;">Verification Failed</h1>
            <p style="font-size:14.5px; line-height:1.6; color:#6b6a59; margin-bottom:28px;">
                <?= $message ?>
            </p>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <a href="resend_verification.php" class="btn btn-solid" style="display:block; padding:14px; font-size:13px; text-transform:uppercase;">
                    Resend Verification Link
                </a>
                <a href="login.php" class="btn btn-light" style="display:block; padding:14px; font-size:13px;">
                    Back to Login
                </a>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
