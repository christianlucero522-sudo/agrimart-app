<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$terms = isset($_POST['terms']);

// Enforce role = 'user' for public registration
$role = 'user';

if (!$terms) {
    $_SESSION['register_error'] = 'You must agree to the AgriMart Terms and Conditions to proceed.';
    header("Location: register.php");
    exit;
}

if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
    $_SESSION['register_error'] = 'Please fill in all required fields.';
    header("Location: register.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'Please enter a valid email address.';
    header("Location: register.php");
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['register_error'] = 'Password must be at least 8 characters long.';
    header("Location: register.php");
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['register_error'] = 'Passwords do not match. Please verify and try again.';
    header("Location: register.php");
    exit;
}

// Check existing email
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $stmt->close();
    $_SESSION['register_error'] = 'That email address is already registered. Please log in or use a different email.';
    header("Location: register.php");
    exit;
}
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$status = 'active';
$isVerified = 'pending';
$faceVerified = 'pending';
$emailVerified = 0;
$emailVerificationToken = bin2hex(random_bytes(24));

$insertSql = "INSERT INTO users (full_name, email, phone, password, role, is_verified, face_verified, status, email_verified, email_verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);
$stmt->bind_param('ssssssssis', $fullName, $email, $phone, $hashedPassword, $role, $isVerified, $faceVerified, $status, $emailVerified, $emailVerificationToken);

if ($stmt->execute()) {
    $newUserId = $stmt->insert_id;
    $stmt->close();

    // Send email verification link
    @sendVerificationEmail($email, $fullName, $emailVerificationToken);

    // Notify Admin of new registration
    $notifTitle = "New User Registered: " . $fullName;
    $notifMsg = "$fullName ($email) has created an account on AgriMart.";
    $adminNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) SELECT user_id, ?, ?, 'system', ? FROM users WHERE role = 'admin'");
    if ($adminNotif) {
        $adminNotif->bind_param('ssi', $notifTitle, $notifMsg, $newUserId);
        $adminNotif->execute();
        $adminNotif->close();
    }

    $_SESSION['login_success'] = "Account created successfully! We sent a verification link to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox and click the verification link to activate your account.";
    header("Location: login.php?email=" . urlencode($email));
    exit;
} else {
    $_SESSION['register_error'] = 'Registration failed: ' . $conn->error;
    header("Location: register.php");
    exit;
}
