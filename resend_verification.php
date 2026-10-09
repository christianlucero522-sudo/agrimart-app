<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

$error = '';
$success = '';
$prefillEmail = trim($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, email_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            if ((int)$user['email_verified'] === 1) {
                $error = 'This email address is already verified. You can proceed to log in.';
            } else {
                $token = bin2hex(random_bytes(24));
                $upStmt = $conn->prepare("UPDATE users SET email_verification_token = ? WHERE user_id = ?");
                $uId = (int)$user['user_id'];
                $upStmt->bind_param('si', $token, $uId);
                $upStmt->execute();
                $upStmt->close();

                @sendVerificationEmail($email, $user['full_name'], $token);

                $success = "A fresh verification link has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox and click the link to activate your account.";
            }
        } else {
            // For security, do not disclose whether email exists
            $success = "If that email address exists in our system, a fresh verification link has been sent. Please check your inbox.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Email — AgriMart</title>
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
    <section class="auth-card" style="max-width:480px;">
        <span style="font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#768047; font-weight:700; display:block; margin-bottom:6px;">
            Account Activation
        </span>
        <h1 style="font-family:Georgia,serif; font-size:26px; color:#122017; margin:0 0 10px;">Resend Verification Link</h1>
        <p class="auth-intro">
            Enter your registered email address and we'll dispatch a fresh activation link to your inbox.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form action="resend_verification.php" method="POST">
            <div class="field">
                <label for="email">Registered Email Address</label>
                <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($prefillEmail) ?>" placeholder="juan@example.com">
            </div>

            <button type="submit" class="btn btn-solid btn-block" style="margin-top:15px; padding:14px; font-size:13px; text-transform:uppercase; letter-spacing:1px;">
                Send Verification Email →
            </button>
        </form>

        <div class="auth-footer" style="margin-top:24px; text-align:center;">
            <a href="login.php" style="color:#768047; font-size:13px; text-decoration:none;">← Back to Login</a>
        </div>
    </section>
</main>

</body>
</html>
