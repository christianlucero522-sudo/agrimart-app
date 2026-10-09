<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentAdminId = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Admin';

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // 1. Create New Administrator (Admin-Only feature)
    if ($action === 'create_admin') {
        $admName = trim($_POST['adm_name'] ?? '');
        $admEmail = trim($_POST['adm_email'] ?? '');
        $admPhone = trim($_POST['adm_phone'] ?? '');
        $admPass = $_POST['adm_password'] ?? '';

        if ($admName === '' || $admEmail === '' || strlen($admPass) < 8) {
            $error = 'Please provide full name, valid email, and password of at least 8 characters.';
        } else {
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $chk->bind_param('s', $admEmail);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'An account with that email already exists.';
            } else {
                $hPass = password_hash($admPass, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, is_verified, status) VALUES (?, ?, ?, ?, 'admin', 'verified', 'active')");
                $ins->bind_param('ssss', $admName, $admEmail, $admPhone, $hPass);
                if ($ins->execute()) {
                    $message = "New Administrator account for '$admName' successfully created!";
                } else {
                    $error = "Failed to create administrator: " . $conn->error;
                }
                $ins->close();
            }
            $chk->close();
        }
    }

    // 2. User Status & Role changes
    elseif ($action === 'set_status') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'active');
        if ($targetId > 0 && $targetId !== $currentAdminId && in_array($newStatus, ['active', 'inactive', 'banned'])) {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param('si', $newStatus, $targetId);
            $stmt->execute();
            $stmt->close();
            $message = "User status updated to " . ucfirst($newStatus) . ".";
        }
    }

    // 3. User ID & Face Verification Approval / Rejection
    elseif ($action === 'set_verification') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newVer = trim($_POST['verification_status'] ?? 'verified');
        $faceVer = trim($_POST['face_verification_status'] ?? $newVer);
        if ($targetId > 0 && in_array($newVer, ['pending', 'verified', 'rejected'])) {
            $stmt = $conn->prepare("UPDATE users SET is_verified = ?, face_verified = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $newVer, $faceVer, $targetId);
            $stmt->execute();
            $stmt->close();

            // Notify user
            $vTitle = "Identity & Face Verification: " . ucfirst($newVer);
            $vMsg = ($newVer === 'verified') 
                ? "Your identity, valid ID, and face biometric photo have been approved! You are now a verified member of AgriMart."
                : "Your identity verification status was updated to '$newVer'. Please check your profile.";
            $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
            $nStmt->bind_param('issi', $targetId, $vTitle, $vMsg, $targetId);
            $nStmt->execute();
            $nStmt->close();

            // Send Gmail notification to user
            require_once 'mailer.php';
            @sendVerificationStatusEmail($targetId, $newVer, $conn);

            $message = "User ID & Face verification status updated to " . ucfirst($newVer) . ".";
        }
    }
}

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$verFilter = trim($_GET['verification'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR id_number LIKE ?)";
    $sParam = "%$search%";
    $params[] = $sParam; $params[] = $sParam; $params[] = $sParam; $params[] = $sParam;
    $types .= "ssss";
}
if (!empty($roleFilter)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}
if (!empty($statusFilter)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if (!empty($verFilter)) {
    $sql .= " AND is_verified = ?";
    $params[] = $verFilter;
    $types .= "s";
}

$sql .= " ORDER BY user_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users & Verification — AgriMart Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">

<header class="site-header admin-header-nav">
<div class="wrap">
    <a href="admin_dashboard.php" class="logo">
        <span class="logo-text"><b>AgriMart Admin</b><span>System Management Console</span></span>
    </a>
    <nav class="main-nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_users.php" class="active">Users</a>
        <a href="admin_sellers.php">Sellers</a>
        <a href="admin_listings.php">Listings</a>
        <a href="admin_rentals.php">Rentals</a>
        <a href="admin_sales.php">Sales</a>
        <a href="admin_moderation.php">Reports & Moderation</a>
        <a href="admin_reports.php">Reports</a>
    </nav>
    <div class="header-actions">
        <span style="color:#fff; font-size:14px; margin-right:10px;">Admin: <strong><?= htmlspecialchars($firstName) ?></strong></span>
        <a href="logout.php" class="btn btn-light">Logout</a>
    </div>
</div>
</header>

<main class="admin-wrap">
    <div class="admin-page-head">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Account Administration & KYC</span>
            <h1>Manage Users & Identity Verification</h1>
        </div>
        <div>
            <button type="button" class="btn btn-solid" onclick="document.getElementById('createAdminModal').style.display='flex'" style="margin-right:8px;">
                + Create New Admin
            </button>
            <a href="admin_dashboard.php" class="btn btn-light">← Dashboard</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:20px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:20px; font-size:14px;">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2>Registered Users (<?= count($users) ?>)</h2>
            <form action="admin_users.php" method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search name, email, ID..." value="<?= htmlspecialchars($search) ?>" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                <select name="role" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                    <option value="">All Roles</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <select name="verification" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                    <option value="">All ID Status</option>
                    <option value="verified" <?= $verFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="pending" <?= $verFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="rejected" <?= $verFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-solid" style="padding:8px 14px; font-size:12px;">Filter</button>
                <a href="admin_users.php" class="btn btn-light" style="padding:8px 14px; font-size:12px;">Reset</a>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>User / Contact</th>
                    <th>Role</th>
                    <th>Valid ID Document</th>
                    <th>Face Biometrics</th>
                    <th>ID & Face Verification</th>
                    <th>Account Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#6b6a59;">No user accounts found matching your search.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong>#<?= (int)$u['user_id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                                <small style="color:#666;"><?= htmlspecialchars($u['email']) ?></small><br>
                                <small style="color:#666;"><?= htmlspecialchars($u['phone'] ?: 'No phone') ?></small>
                                <div style="margin-top:4px;">
                                    <?php if ((int)($u['email_verified'] ?? 1) === 1): ?>
                                        <span style="background:#e0edd5; color:#23581c; font-size:10px; padding:1px 5px; border-radius:2px; font-weight:700;">Email Verified</span>
                                    <?php else: ?>
                                        <span style="background:#fae6df; color:#a54129; font-size:10px; padding:1px 5px; border-radius:2px; font-weight:700;">Email Unverified</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $u['role'] === 'admin' ? 'active' : 'inactive' ?>"><?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($u['id_type'])): ?>
                                    <strong><?= htmlspecialchars($u['id_type']) ?></strong><br>
                                    <small style="color:#777;">No: <?= htmlspecialchars($u['id_number'] ?? 'N/A') ?></small><br>
                                <?php endif; ?>
                                <?php if (!empty($u['id_card_image'])): ?>
                                    <a href="<?= htmlspecialchars($u['id_card_image']) ?>" target="_blank" class="btn btn-light" style="padding:2px 8px; font-size:11px; margin-top:4px; display:inline-block;">
                                        🔍 View ID Card
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999; font-size:11px;">No ID upload</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($u['face_image'])): ?>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <img src="<?= htmlspecialchars($u['face_image']) ?>" alt="Face Selfie" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #768047;">
                                        <a href="<?= htmlspecialchars($u['face_image']) ?>" target="_blank" class="btn btn-light" style="padding:2px 8px; font-size:11px;">
                                            🔍 View Face
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#999; font-size:11px;">No face capture</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="admin_users.php" method="POST" style="display:flex; flex-direction:column; gap:4px;">
                                    <input type="hidden" name="action" value="set_verification">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:4px;">
                                        <span style="font-size:11px; color:#555;">ID Status:</span>
                                        <select name="verification_status" onchange="this.form.submit()" style="padding:2px 4px; font-size:11px;">
                                            <option value="pending" <?= ($u['is_verified'] ?? '') === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                            <option value="verified" <?= ($u['is_verified'] ?? '') === 'verified' ? 'selected' : '' ?>>✓ Verified</option>
                                            <option value="rejected" <?= ($u['is_verified'] ?? '') === 'rejected' ? 'selected' : '' ?>>✕ Rejected</option>
                                        </select>
                                    </div>

                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:4px;">
                                        <span style="font-size:11px; color:#555;">Face:</span>
                                        <span style="font-size:11px; font-weight:700; color:<?= ($u['face_verified'] ?? '') === 'verified' ? '#23581c' : (($u['face_verified'] ?? '') === 'rejected' ? '#a54129' : '#6e5817') ?>;">
                                            <?= ucfirst($u['face_verified'] ?? 'pending') ?>
                                        </span>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars(ucfirst($u['status'])) ?></span>
                            </td>
                            <td>
                                <?php if ((int)$u['user_id'] !== $currentAdminId): ?>
                                    <form action="admin_users.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button type="submit" name="status" value="banned" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#fae6df; color:#a54129; border-color:#efb7aa;" onclick="return confirm('Block/suspend this user?');">Block</button>
                                        <?php else: ?>
                                            <button type="submit" name="status" value="active" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#e0edd5; color:#23581c; border-color:#c5ddb4;">Unblock</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- CREATE ADMIN MODAL (Admin-only creation) -->
<div id="createAdminModal" class="admin-modal">
    <div class="admin-modal-box">
        <button type="button" class="admin-modal-close" onclick="document.getElementById('createAdminModal').style.display='none'">&times;</button>
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:11px; display:block; margin-bottom:4px;">Administrator Provisioning</span>
        <h2 style="font-family:Georgia,serif; font-size:24px; margin:0 0 18px; color:#122017;">Create New System Administrator</h2>
        <p style="font-size:13px; color:#686454; margin:0 0 20px;">
            This form creates an authorized System Administrator with full access to management modules and financial reports.
        </p>

        <form action="admin_users.php" method="POST">
            <input type="hidden" name="action" value="create_admin">

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Admin Full Name *</label>
                <input type="text" name="adm_name" required placeholder="Full Name" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Admin Email Address *</label>
                <input type="email" name="adm_email" required placeholder="admin@domain.com" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Phone Number</label>
                <input type="text" name="adm_phone" placeholder="09171234567" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Password *</label>
                <input type="password" name="adm_password" minlength="8" required placeholder="Enter password" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
            </div>

            <button type="submit" class="btn btn-solid" style="width:100%; padding:14px; font-size:13px; cursor:pointer;">
                Confirm & Create Admin Account
            </button>
        </form>
    </div>
</div>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>© 2026 AgriMart Administration. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
