<?php
session_start();
require_once 'config.php';
require_once 'mailer.php';

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

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    // 1. Profile & Address Update
    if ($action === 'update_profile') {
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
                if (!empty($barangay) || !empty($city) || !empty($province)) {
                    $chkAddr = $conn->query("SELECT address_id FROM addresses WHERE user_id = $userId LIMIT 1");
                    if ($chkAddr && $chkAddr->num_rows > 0) {
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

    // 2. Identity & Face Biometrics Verification Submission
    elseif ($action === 'submit_verification') {
        $idType = trim($_POST['id_type'] ?? '');
        $idNumber = trim($_POST['id_number'] ?? '');
        $faceBase64 = $_POST['face_base64'] ?? '';
        
        $hasIdUpdate = false;
        $hasFaceUpdate = false;

        // Handle Valid ID Upload
        $idImagePath = null;
        if (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['id_card_image']['tmp_name'];
            $origName = basename($_FILES['id_card_image']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($ext, $allowed)) {
                $filename = 'id_' . $userId . '_' . time() . '.' . $ext;
                $destDir = __DIR__ . '/uploads/identifications/';
                if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
                if (move_uploaded_file($tmpName, $destDir . $filename)) {
                    $idImagePath = 'uploads/identifications/' . $filename;
                    $hasIdUpdate = true;
                }
            }
        }

        // Handle Face Selfie (Base64 from live webcam OR file upload)
        $faceImagePath = null;
        if (!empty($faceBase64) && strpos($faceBase64, 'data:image') === 0) {
            $parts = explode(',', $faceBase64);
            if (count($parts) === 2) {
                $decoded = base64_decode($parts[1]);
                if ($decoded) {
                    $destDir = __DIR__ . '/uploads/faces/';
                    if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
                    $filename = 'face_' . $userId . '_' . time() . '.jpg';
                    if (file_put_contents($destDir . $filename, $decoded)) {
                        $faceImagePath = 'uploads/faces/' . $filename;
                        $hasFaceUpdate = true;
                    }
                }
            }
        } elseif (isset($_FILES['face_image_file']) && $_FILES['face_image_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['face_image_file']['tmp_name'];
            $origName = basename($_FILES['face_image_file']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed)) {
                $filename = 'face_' . $userId . '_' . time() . '.' . $ext;
                $destDir = __DIR__ . '/uploads/faces/';
                if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
                if (move_uploaded_file($tmpName, $destDir . $filename)) {
                    $faceImagePath = 'uploads/faces/' . $filename;
                    $hasFaceUpdate = true;
                }
            }
        }

        // Update database
        if (!empty($idType) || !empty($idNumber) || $idImagePath !== null) {
            if ($idImagePath !== null) {
                $uStmt = $conn->prepare("UPDATE users SET id_type = ?, id_number = ?, id_card_image = ?, is_verified = 'pending' WHERE user_id = ?");
                $uStmt->bind_param('sssi', $idType, $idNumber, $idImagePath, $userId);
            } else {
                $uStmt = $conn->prepare("UPDATE users SET id_type = ?, id_number = ? WHERE user_id = ?");
                $uStmt->bind_param('ssi', $idType, $idNumber, $userId);
            }
            $uStmt->execute();
            $uStmt->close();
            $hasIdUpdate = true;
        }

        if ($faceImagePath !== null) {
            $fStmt = $conn->prepare("UPDATE users SET face_image = ?, face_verified = 'pending' WHERE user_id = ?");
            $fStmt->bind_param('si', $faceImagePath, $userId);
            $fStmt->execute();
            $fStmt->close();
            $hasFaceUpdate = true;
        }

        if ($hasIdUpdate || $hasFaceUpdate) {
            // Notify Admins
            $adminNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) SELECT user_id, 'KYC Verification Submitted', ?, 'system', ? FROM users WHERE role = 'admin'");
            $nMsg = "$fullName has updated their identification documents / face verification for review.";
            $adminNotif->bind_param('si', $nMsg, $userId);
            $adminNotif->execute();
            $adminNotif->close();

            $message = 'Verification documents and Face biometrics submitted successfully! Our administrators will review your credentials.';
        } else {
            $error = 'Please provide an ID document or take a live face selfie to submit verification.';
        }
    }
}

// Fetch Current User & Address
$userSql = "
    SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.status, u.is_verified, u.id_type, u.id_number, u.id_card_image,
           u.face_image, u.face_verified, u.email_verified, u.created_at,
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

$isEmailVer = (int)($user['email_verified'] ?? 1);
$isIdVer = $user['is_verified'] ?? 'pending';
$isFaceVer = $user['face_verified'] ?? 'pending';
$isFullyVerified = ($isEmailVer === 1 && $isIdVer === 'verified' && $isFaceVer === 'verified');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Verification — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(880px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .form-card { background: #fff; border: 1px solid #ded6b9; padding: 35px 40px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-family: monospace; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #5e604e; margin-bottom: 8px; font-weight: 700; }
        .form-control { width: 100%; box-sizing: border-box; padding: 12px 15px; border: 1px solid #d8cba1; background: #fff; color: #141b13; font: inherit; outline: none; border-radius: 2px; }
        .form-control:focus { border-color: #2e5927; outline: none; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Verification Stepper Tracker */
        .kyc-tracker { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kyc-step-card { background: #fbf9f2; border: 1px solid #ded6b9; padding: 16px 18px; border-radius: 4px; display: flex; flex-direction: column; justify-content: space-between; }
        .kyc-step-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .kyc-step-title { font-size: 13px; font-weight: 700; color: #1f2e1a; }
        .kyc-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 3px; }
        .kyc-badge-verified { background: #e0edd5; color: #23581c; border: 1px solid #c5ddb4; }
        .kyc-badge-pending { background: #efe2b7; color: #6e5817; border: 1px solid #d4c79c; }
        .kyc-badge-rejected { background: #fae6df; color: #a54129; border: 1px solid #efb7aa; }
        .kyc-badge-missing { background: #eee; color: #666; border: 1px solid #ccc; }

        /* Camera Biometrics Container */
        .camera-box { background: #162018; border-radius: 6px; padding: 20px; text-align: center; color: #fff; margin-top: 15px; border: 1px solid #2d3b2a; }
        .video-container { position: relative; width: 100%; max-width: 380px; margin: 0 auto; border-radius: 6px; overflow: hidden; background: #0b110c; min-height: 220px; display: flex; align-items: center; justify-content: center; }
        #cameraVideo { width: 100%; height: auto; display: none; transform: scaleX(-1); }
        #capturedPreview { width: 100%; height: auto; display: none; transform: scaleX(-1); border: 2px solid #768047; }
        .cam-overlay-guide { position: absolute; border: 2px dashed rgba(255,255,255,0.6); border-radius: 50%; width: 180px; height: 230px; pointer-events: none; display: none; }
        .cam-controls { margin-top: 15px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        
        @media(max-width:650px) { .form-grid-2 { grid-template-columns: 1fr; } .page-wrap { padding-top: 90px; } }
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
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <a href="dashboard.php" style="color:var(--forest-900); font-weight:600; text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px;">
            ← Back to Command Dashboard
        </a>
        <span style="font-size:12px; color:#686454;">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
    </div>

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

    <!-- =========================================================
         SECTION 1: IDENTITY & FACE VERIFICATION (KYC)
    ========================================================= -->
    <div class="form-card" id="verification">
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; display:block; margin-bottom:5px;">Trust & Security Protocol</span>
        <h2 style="font-family:Georgia,serif; font-size:26px; margin:0 0 10px; color:#122017;">Account & Face Verification</h2>
        <p style="font-size:13px; color:#686454; margin:0 0 25px; line-height:1.5;">
            Verified accounts gain access to create product harvest listings, list machinery for rent, and enjoy high trust ratings across AgriMart.
        </p>

        <!-- 3-Step Verification Monitor -->
        <div class="kyc-tracker">
            <!-- 1. Email Verification -->
            <div class="kyc-step-card">
                <div>
                    <div class="kyc-step-head">
                        <span class="kyc-step-title">1. Email Activation</span>
                        <?php if ($isEmailVer === 1): ?>
                            <span class="kyc-badge kyc-badge-verified">✓ Verified</span>
                        <?php else: ?>
                            <span class="kyc-badge kyc-badge-pending">⏳ Unverified</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px; color:#686454;">
                        <?= htmlspecialchars($user['email']) ?>
                    </div>
                </div>
                <?php if ($isEmailVer !== 1): ?>
                    <div style="margin-top:10px;">
                        <a href="resend_verification.php?email=<?= urlencode($user['email']) ?>" class="btn btn-light" style="padding:4px 10px; font-size:11px; width:100%; box-sizing:border-box; text-align:center;">Resend Activation Link</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. Valid ID Verification -->
            <div class="kyc-step-card">
                <div>
                    <div class="kyc-step-head">
                        <span class="kyc-step-title">2. Government / Farmer ID</span>
                        <?php if ($isIdVer === 'verified'): ?>
                            <span class="kyc-badge kyc-badge-verified">✓ Approved</span>
                        <?php elseif ($isIdVer === 'pending'): ?>
                            <span class="kyc-badge kyc-badge-pending">⏳ Under Review</span>
                        <?php elseif ($isIdVer === 'rejected'): ?>
                            <span class="kyc-badge kyc-badge-rejected">✕ Rejected</span>
                        <?php else: ?>
                            <span class="kyc-badge kyc-badge-missing">Not Submitted</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px; color:#686454;">
                        Type: <strong><?= htmlspecialchars($user['id_type'] ?: 'Not provided') ?></strong><br>
                        No: <strong><?= htmlspecialchars($user['id_number'] ?: 'N/A') ?></strong>
                    </div>
                </div>
                <?php if (!empty($user['id_card_image'])): ?>
                    <div style="margin-top:10px;">
                        <a href="<?= htmlspecialchars($user['id_card_image']) ?>" target="_blank" style="font-size:11px; color:#2e5927; font-weight:600; text-decoration:underline;">
                            🔍 View Uploaded ID Document
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. Face Biometrics Verification -->
            <div class="kyc-step-card">
                <div>
                    <div class="kyc-step-head">
                        <span class="kyc-step-title">3. Face Verification</span>
                        <?php if ($isFaceVer === 'verified'): ?>
                            <span class="kyc-badge kyc-badge-verified">✓ Verified</span>
                        <?php elseif ($isFaceVer === 'pending'): ?>
                            <span class="kyc-badge kyc-badge-pending">⏳ In Review</span>
                        <?php elseif ($isFaceVer === 'rejected'): ?>
                            <span class="kyc-badge kyc-badge-rejected">✕ Rejected</span>
                        <?php else: ?>
                            <span class="kyc-badge kyc-badge-missing">Not Captured</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px; color:#686454;">
                        Live face selfie comparison for fraud prevention and seller integrity.
                    </div>
                </div>
                <?php if (!empty($user['face_image'])): ?>
                    <div style="margin-top:10px;">
                        <a href="<?= htmlspecialchars($user['face_image']) ?>" target="_blank" style="font-size:11px; color:#2e5927; font-weight:600; text-decoration:underline;">
                            🔍 View Stored Face Selfie
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KYC Upload & Live Camera Form -->
        <?php if (!$isFullyVerified): ?>
            <form action="profile.php" method="POST" enctype="multipart/form-data" id="kycForm" style="border-top:1px solid #eee8d5; padding-top:20px; margin-top:10px;">
                <input type="hidden" name="action" value="submit_verification">
                <input type="hidden" name="face_base64" id="face_base64">

                <h3 style="font-family:Georgia,serif; font-size:18px; margin:0 0 15px; color:#122017;">Update Verification Documents & Face Biometrics</h3>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="id_type">Identification Type *</label>
                        <select name="id_type" id="id_type" class="form-control" required>
                            <option value="">-- Select Valid ID / Document --</option>
                            <option value="PhilSys National ID" <?= ($user['id_type'] ?? '') === 'PhilSys National ID' ? 'selected' : '' ?>>PhilSys National ID</option>
                            <option value="Farmer RSBSA Registration ID" <?= ($user['id_type'] ?? '') === 'Farmer RSBSA Registration ID' ? 'selected' : '' ?>>Farmer RSBSA Registration ID</option>
                            <option value="Driver's License" <?= ($user['id_type'] ?? '') === "Driver's License" ? 'selected' : '' ?>>Driver's License</option>
                            <option value="Philippine Passport" <?= ($user['id_type'] ?? '') === 'Philippine Passport' ? 'selected' : '' ?>>Philippine Passport</option>
                            <option value="UMID / SSS ID" <?= ($user['id_type'] ?? '') === 'UMID / SSS ID' ? 'selected' : '' ?>>UMID / SSS ID</option>
                            <option value="Postal ID" <?= ($user['id_type'] ?? '') === 'Postal ID' ? 'selected' : '' ?>>Postal ID</option>
                            <option value="Barangay Certificate with Photo" <?= ($user['id_type'] ?? '') === 'Barangay Certificate with Photo' ? 'selected' : '' ?>>Barangay Certificate with Photo</option>
                            <option value="Voter's ID / Certification" <?= ($user['id_type'] ?? '') === "Voter's ID / Certification" ? 'selected' : '' ?>>Voter's ID / Certification</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_number">ID / Registration Number *</label>
                        <input type="text" id="id_number" name="id_number" class="form-control" required value="<?= htmlspecialchars($user['id_number'] ?? '') ?>" placeholder="e.g. 1234-5678-9012">
                    </div>
                </div>

                <div class="form-group">
                    <label for="id_card_image">Upload Clear Photo of ID Card (Front)</label>
                    <input type="file" id="id_card_image" name="id_card_image" accept="image/*,.pdf" class="form-control" style="padding:9px;">
                    <small style="display:block; color:#777; margin-top:4px; font-size:11px;">Supported: JPG, PNG, WEBP, PDF (Max 8MB)</small>
                </div>

                <!-- LIVE FACE SELFIE MODULE -->
                <div style="margin-top:25px; border-top:1px dashed #dcd3b8; padding-top:20px;">
                    <label style="display:block; font-family:monospace; font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#2e5927; font-weight:700; margin-bottom:8px;">
                        Face Verification (Live Camera or Photo Upload)
                    </label>
                    <p style="font-size:12px; color:#686454; margin:0 0 15px;">
                        Align your face in good lighting and capture a clear selfie, or choose a portrait photo from your device.
                    </p>

                    <div class="camera-box">
                        <div class="video-container" id="videoHolder">
                            <div id="camPlaceholder" style="color:#a5a99d; font-size:13px; padding:20px;">
                                <svg viewBox="0 0 24 24" style="width:48px; height:48px; stroke:#768047; fill:none; stroke-width:1.5; margin-bottom:10px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                <div>Click <strong>"Start Live Camera"</strong> below or select a file</div>
                            </div>
                            <video id="cameraVideo" autoplay playsinline></video>
                            <img id="capturedPreview" alt="Face Selfie Preview">
                            <div class="cam-overlay-guide" id="faceGuide"></div>
                        </div>

                        <canvas id="hiddenCanvas" style="display:none;"></canvas>

                        <div class="cam-controls">
                            <button type="button" class="btn btn-solid" id="startCamBtn" style="padding:8px 16px; font-size:12px;">
                                📷 Start Live Camera
                            </button>
                            <button type="button" class="btn btn-solid" id="snapBtn" style="padding:8px 16px; font-size:12px; display:none; background:#768047; border-color:#768047;">
                                📸 Capture Selfie
                            </button>
                            <button type="button" class="btn btn-light" id="retakeBtn" style="padding:8px 16px; font-size:12px; display:none;">
                                🔄 Retake Photo
                            </button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label for="face_image_file">Or Upload a Portrait Face Selfie File</label>
                        <input type="file" id="face_image_file" name="face_image_file" accept="image/*" class="form-control" style="padding:9px;">
                    </div>
                </div>

                <div style="margin-top:25px;">
                    <button type="submit" class="btn btn-solid" style="padding:14px 28px; font-size:13px; cursor:pointer;">
                        Submit Verification Credentials
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:18px; border-radius:4px; margin-top:15px; font-size:14px;">
                <strong>Congratulations!</strong> Your AgriMart account is fully verified with activated email, authenticated Government ID, and registered face biometrics.
            </div>
        <?php endif; ?>
    </div>

    <!-- =========================================================
         SECTION 2: ACCOUNT PROFILE & DELIVERY ADDRESS
    ========================================================= -->
    <div class="form-card">
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; display:block; margin-bottom:5px;">Personal Details</span>
        <h2 style="font-family:Georgia,serif; font-size:26px; margin:0 0 25px; color:#122017;">Account & Address Information</h2>

        <form action="profile.php" method="POST">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required value="<?= htmlspecialchars($user['full_name']) ?>">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label>Email Address (Account ID)</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#f5f0df; cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label for="phone">Phone / Mobile Number (SMS Alerts)</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09171234567">
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">Change Password (Leave blank to keep current)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" placeholder="Enter new password">
            </div>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:30px 0 15px; border-bottom:1px solid #eee8d5; padding-bottom:8px; color:#122017;">Default Delivery & Farm Location</h3>

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
                    <label for="city_municipality">City / Municipality (Used for Nearest Rentals)</label>
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

            <button type="submit" class="btn btn-solid" style="padding:14px 28px; font-size:13px; margin-top:10px; cursor:pointer;">
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

<script>
// Live Camera Biometrics Script
let videoStream = null;
const startCamBtn = document.getElementById('startCamBtn');
const snapBtn = document.getElementById('snapBtn');
const retakeBtn = document.getElementById('retakeBtn');
const cameraVideo = document.getElementById('cameraVideo');
const capturedPreview = document.getElementById('capturedPreview');
const camPlaceholder = document.getElementById('camPlaceholder');
const faceGuide = document.getElementById('faceGuide');
const hiddenCanvas = document.getElementById('hiddenCanvas');
const faceBase64Input = document.getElementById('face_base64');
const faceFileInput = document.getElementById('face_image_file');

if (startCamBtn) {
    startCamBtn.addEventListener('click', async function() {
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                audio: false
            });
            cameraVideo.srcObject = videoStream;
            cameraVideo.style.display = 'block';
            camPlaceholder.style.display = 'none';
            capturedPreview.style.display = 'none';
            faceGuide.style.display = 'block';
            startCamBtn.style.display = 'none';
            snapBtn.style.display = 'inline-block';
            retakeBtn.style.display = 'none';
        } catch (err) {
            alert('Unable to access your camera: ' + err.message + '\nYou can upload a selfie photo instead.');
        }
    });

    snapBtn.addEventListener('click', function() {
        if (!cameraVideo.videoWidth) return;
        hiddenCanvas.width = cameraVideo.videoWidth;
        hiddenCanvas.height = cameraVideo.videoHeight;
        const ctx = hiddenCanvas.getContext('2d');
        // Mirror horizontally
        ctx.translate(hiddenCanvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(cameraVideo, 0, 0, hiddenCanvas.width, hiddenCanvas.height);
        
        const dataUrl = hiddenCanvas.toDataURL('image/jpeg', 0.9);
        faceBase64Input.value = dataUrl;
        
        capturedPreview.src = dataUrl;
        capturedPreview.style.display = 'block';
        cameraVideo.style.display = 'none';
        faceGuide.style.display = 'none';
        snapBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-block';

        // Stop camera stream
        if (videoStream) {
            videoStream.getTracks().forEach(t => t.stop());
            videoStream = null;
        }
    });

    retakeBtn.addEventListener('click', function() {
        faceBase64Input.value = '';
        startCamBtn.click();
    });

    if (faceFileInput) {
        faceFileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    capturedPreview.src = e.target.result;
                    capturedPreview.style.display = 'block';
                    cameraVideo.style.display = 'none';
                    camPlaceholder.style.display = 'none';
                    faceGuide.style.display = 'none';
                    snapBtn.style.display = 'none';
                    retakeBtn.style.display = 'none';
                    startCamBtn.style.display = 'inline-block';
                    if (videoStream) {
                        videoStream.getTracks().forEach(t => t.stop());
                        videoStream = null;
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
}
</script>

</body>
</html>
