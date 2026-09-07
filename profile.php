<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'User';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'User';

$message = '';
$error = '';

// Handle Profile & Address Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city_municipality'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $postal = trim($_POST['postal_code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if ($name === '') {
        $error = 'Full name cannot be empty.';
    } else {
        // Update user
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters.';
            } else {
                $hPass = password_hash($newPassword, PASSWORD_DEFAULT);
                $uStmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, password = ? WHERE user_id = ?");
                $uStmt->bind_param('sssi', $name, $phone, $hPass, $userId);
                $uStmt->execute();
                $uStmt->close();
            }
        } else {
            $uStmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
            $uStmt->bind_param('ssi', $name, $phone, $userId);
            $uStmt->execute();
            $uStmt->close();
        }

        if (empty($error)) {
            $_SESSION['full_name'] = $name;

            // Address handling
            if (!empty($barangay)) {
                $chkAddr = $conn->query("SELECT address_id FROM addresses WHERE user_id = $userId LIMIT 1");
                if ($chkAddr->num_rows > 0) {
                    $aRow = $chkAddr->fetch_assoc();
                    $aId = (int)$aRow['address_id'];
                    $aStmt = $conn->prepare("UPDATE addresses SET street = ?, barangay = ?, city_municipality = ?, province = ?, postal_code = ? WHERE address_id = ?");
                    $aStmt->bind_param('sssssi', $street, $barangay, $city, $province, $postal, $aId);
                    $aStmt->execute();
                    $aStmt->close();
                } else {
                    $aStmt = $conn->prepare("INSERT INTO addresses (user_id, street, barangay, city_municipality, province, postal_code) VALUES (?, ?, ?, ?, ?, ?)");
                    $aStmt->bind_param('isssss', $userId, $street, $barangay, $city, $province, $postal);
                    $aStmt->execute();
                    $aStmt->close();
                }
            }

            $message = 'Profile and delivery address updated successfully!';
        }
    }
}

// Fetch Current User & Address
$userSql = "
    SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.status, u.is_verified, u.id_type, u.id_number, u.id_card_image, u.created_at,
           a.street, a.barangay, a.city_municipality, a.province, a.postal_code
    FROM users u
    LEFT JOIN addresses a ON u.user_id = a.user_id
    WHERE u.user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($userSql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(800px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .form-card { background: #fff; border: 1px solid #ded6b9; padding: 40px; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; font-family: monospace; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #5e604e; margin-bottom: 8px; }
        .form-control { width: 100%; box-sizing: border-box; padding: 14px 16px; border: 1px solid #d8cba1; background: #fff; color: #141b13; font: inherit; outline: none; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width:600px) { .form-grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="site-header">
<div class="wrap">
    <a href="index.php" class="logo">
        <span class="logo-text"><b>AgriMart</b><span>Field to Farm Gate</span></span>
    </a>
    <nav class="main-nav">
        <a href="index.php">Home</a>
        <a href="products.php">Products</a>
        <a href="equipment.php">Equipment</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
    <div class="header-actions">
        <span style="color:#fff; font-size:14px; margin-right:10px;">Hi, <strong><?= htmlspecialchars($firstName) ?></strong></span>
        <a href="logout.php" class="btn btn-light">Logout</a>
    </div>
</div>
</header>

<main class="page-wrap">
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; color:var(--forest-900); font-weight:600; text-decoration:none; font-size:14px;">← Back to Dashboard</a>

    <div class="form-card">
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; display:block; margin-bottom:5px;">Personal Account</span>
        <h1 style="font-family:Georgia,serif; font-size:32px; margin:0 0 25px; color:#122017;">My Account Profile</h1>

        <?php if (!empty($message)): ?>
            <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
                ✓ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:25px; font-size:14px;">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- IDENTITY & KYC STATUS -->
        <div style="background:#f9f7f0; border:1px solid #ded6b9; padding:20px; margin-bottom:28px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
            <div>
                <span style="font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#768047; font-weight:700;">Government / Farmer ID</span>
                <div style="font-size:16px; font-weight:700; color:#122017; margin:4px 0 2px;">
                    <?= htmlspecialchars($user['id_type'] ?: 'Standard Account') ?>
                </div>
                <div style="font-size:13px; color:#686454;">
                    ID Number: <strong><?= htmlspecialchars($user['id_number'] ?: 'N/A') ?></strong>
                </div>
            </div>
            <div>
                <?php if (($user['is_verified'] ?? '') === 'verified'): ?>
                    <span style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:8px 14px; font-size:12px; font-weight:700; border-radius:3px;">
                        Verified Member
                    </span>
                <?php elseif (($user['is_verified'] ?? '') === 'rejected'): ?>
                    <span style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:8px 14px; font-size:12px; font-weight:700; border-radius:3px;">
                        Verification Rejected
                    </span>
                <?php else: ?>
                    <span style="background:#efe2b7; border:1px solid #d4c79c; color:#6e5817; padding:8px 14px; font-size:12px; font-weight:700; border-radius:3px;">
                        Verification Pending Review
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <form action="profile.php" method="POST">
            <h2 style="font-family:Georgia,serif; font-size:20px; margin:0 0 15px; border-bottom:1px solid #eee8d5; padding-bottom:8px; color:#122017;">Account Details</h2>
            
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required value="<?= htmlspecialchars($user['full_name']) ?>">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Email Address (Account ID)</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#f5f0df; cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label for="phone">Phone / Mobile Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09171234567">
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">Change Password (Leave blank to keep current)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" placeholder="Enter new password">
            </div>

            <h2 style="font-family:Georgia,serif; font-size:20px; margin:30px 0 15px; border-bottom:1px solid #eee8d5; padding-bottom:8px; color:#122017;">Default Delivery & Farm Address</h2>

            <div class="form-group">
                <label for="street">Street Address / Landmark</label>
                <input type="text" id="street" name="street" class="form-control" value="<?= htmlspecialchars($user['street'] ?? '') ?>" placeholder="e.g. Purok 4, Maharlika Highway">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="barangay">Barangay</label>
                    <input type="text" id="barangay" name="barangay" class="form-control" value="<?= htmlspecialchars($user['barangay'] ?? '') ?>" placeholder="e.g. San Jose">
                </div>
                <div class="form-group">
                    <label for="city_municipality">City / Municipality</label>
                    <input type="text" id="city_municipality" name="city_municipality" class="form-control" value="<?= htmlspecialchars($user['city_municipality'] ?? '') ?>" placeholder="e.g. Cabanatuan City">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="province">Province</label>
                    <input type="text" id="province" name="province" class="form-control" value="<?= htmlspecialchars($user['province'] ?? '') ?>" placeholder="e.g. Nueva Ecija">
                </div>
                <div class="form-group">
                    <label for="postal_code">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="e.g. 3100">
                </div>
            </div>

            <button type="submit" class="btn btn-solid" style="padding:15px 30px; font-size:13px; margin-top:10px; cursor:pointer;">
                Save Profile Changes
            </button>
        </form>
    </div>
</main>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>© 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
