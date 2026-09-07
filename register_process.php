<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$idType = trim($_POST['id_type'] ?? '');
$idNumber = trim($_POST['id_number'] ?? '');
$terms = isset($_POST['terms']);

// Enforce role = 'user' for public registration (Admin accounts created by Admins only)
$role = 'user';

if (!$terms) {
    $_SESSION['register_error'] = 'You must agree to the Terms and Conditions.';
    header("Location: register.php");
    exit;
}

if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '' || $idType === '' || $idNumber === '') {
    $_SESSION['register_error'] = 'Please fill in all required fields including Valid ID details.';
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
    $_SESSION['register_error'] = 'Passwords do not match.';
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
    $_SESSION['register_error'] = 'That email address is already registered.';
    header("Location: register.php");
    exit;
}
$stmt->close();

// Handle ID Image Upload
$idImagePath = null;
if (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['id_card_image']['tmp_name'];
    $origName = basename($_FILES['id_card_image']['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    if (in_array($ext, $allowed)) {
        $filename = 'id_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $destPath = __DIR__ . '/uploads/identifications/' . $filename;
        if (move_uploaded_file($tmpName, $destPath)) {
            $idImagePath = 'uploads/identifications/' . $filename;
        }
    }
}

if (!$idImagePath) {
    $_SESSION['register_error'] = 'Please upload a clear photo or copy of your valid ID / Farmer Card.';
    header("Location: register.php");
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$status = 'active';
$isVerified = 'pending';

$insertSql = "INSERT INTO users (full_name, email, phone, password, role, id_type, id_number, id_card_image, is_verified, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);
$stmt->bind_param('ssssssssss', $fullName, $email, $phone, $hashedPassword, $role, $idType, $idNumber, $idImagePath, $isVerified, $status);

if ($stmt->execute()) {
    $newUserId = $stmt->insert_id;
    $stmt->close();

    // Notify Admin of new verification request
    $notifTitle = "New User Verification: " . $fullName;
    $notifMsg = "$fullName has registered with a $idType (No: $idNumber). Please review their valid ID in the Admin Console.";
    $adminNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) SELECT user_id, ?, ?, 'system', ? FROM users WHERE role = 'admin'");
    $adminNotif->bind_param('ssi', $notifTitle, $notifMsg, $newUserId);
    $adminNotif->execute();
    $adminNotif->close();

    // Auto-login newly registered user
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;

    header("Location: dashboard.php");
    exit;
} else {
    $_SESSION['register_error'] = 'Registration failed: ' . $conn->error;
    header("Location: register.php");
    exit;
}
