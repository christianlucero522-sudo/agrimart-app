<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'User';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'User';

// Check User Verification Status & Saved Address
$uStmt = $conn->prepare("
    SELECT u.is_verified, u.face_verified, a.city_municipality, a.province
    FROM users u
    LEFT JOIN addresses a ON u.user_id = a.user_id
    WHERE u.user_id = ?
    LIMIT 1
");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$isVerified = ($uRow['is_verified'] ?? 'pending') === 'verified';
$userCity = $uRow['city_municipality'] ?? '';
$userProvince = $uRow['province'] ?? '';

// Fetch Equipment Categories
$catRes = $conn->query("SELECT category_id, category_name FROM categories WHERE category_type = 'equipment' ORDER BY category_name ASC");
$categories = [];
while ($c = $catRes->fetch_assoc()) {
    $categories[] = $c;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isVerified) {
        $error = 'Account Verification Required: You must complete Government ID and Face Verification before listing equipment for rent.';
    } else {
        $name = trim($_POST['equipment_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $rateType = trim($_POST['rate_type'] ?? 'daily');
        $ratePrice = (float)($_POST['rate_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $locationCity = trim($_POST['location_city'] ?? $userCity);
        $locationProvince = trim($_POST['location_province'] ?? $userProvince);

        if ($name === '' || $categoryId <= 0 || $ratePrice <= 0) {
            $error = 'Please fill in the equipment name, category, and valid rental rate.';
        } else {
            // Enforce rate type policy based on category
            $catCheckStmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ? LIMIT 1");
            $catCheckStmt->bind_param('i', $categoryId);
            $catCheckStmt->execute();
            $catRow = $catCheckStmt->get_result()->fetch_assoc();
            $catCheckStmt->close();

            $catNameLower = strtolower($catRow['category_name'] ?? '');
            $hourlyKeywords = ['pump', 'sprayer', 'irrigation', 'cutter', 'chainsaw', 'blower', 'mist'];
            $isHourlyAllowed = false;
            foreach ($hourlyKeywords as $kw) {
                if (strpos($catNameLower, $kw) !== false) {
                    $isHourlyAllowed = true;
                    break;
                }
            }

            $rateType = $isHourlyAllowed ? 'hourly' : 'daily';
            // Image Upload
            $imageUrl = 'images/placeholder-equipment.svg';
            if (isset($_FILES['equipment_image']) && $_FILES['equipment_image']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['equipment_image']['tmp_name'];
                $orig = basename($_FILES['equipment_image']['name']);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/uploads/equipment/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                    $newFilename = 'equip_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($tmp, $uploadDir . $newFilename)) {
                        $imageUrl = 'uploads/equipment/' . $newFilename;
                    }
                }
            }

            $sql = "INSERT INTO equipment (user_id, category_id, equipment_name, description, brand, model, rate_type, rate_price, availability, image_url, location_city, location_province, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iisssssdsss', $userId, $categoryId, $name, $description, $brand, $model, $rateType, $ratePrice, $imageUrl, $locationCity, $locationProvince);
            
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: my_equipment.php?added=1');
                exit;
            } else {
                $error = 'Error saving equipment listing: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Equipment for Rent — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(840px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .form-card { background: #fff; border: 1px solid #ded6b9; padding: 40px; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; font-family: monospace; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #5e604e; margin-bottom: 8px; font-weight: 700; }
        .form-control { width: 100%; box-sizing: border-box; padding: 13px 16px; border: 1px solid #d8cba1; background: #fff; color: #141b13; font: inherit; outline: none; }
        .form-control:focus { border-color: #2e5927; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .rate-combo-group { display: flex; align-items: stretch; border: 1px solid #d8cba1; background: #fff; }
        .rate-combo-group:focus-within { border-color: #2e5927; }
        .rate-combo-prefix { display: flex; align-items: center; background: #f4f0e6; padding: 0 14px; font-weight: 700; color: #122017; font-size: 15px; border-right: 1px solid #d8cba1; user-select: none; }
        .rate-combo-input { border: none !important; border-radius: 0 !important; flex: 1; min-width: 0; padding: 13px 14px !important; font: inherit; outline: none; background: transparent; }
        .rate-combo-unit { display: flex; align-items: center; background: #f4f0e6; padding: 0 16px; font-weight: 700; color: #122017; font-size: 14px; border-left: 1px solid #d8cba1; user-select: none; white-space: nowrap; }
        @media(max-width:650px) { .form-grid-2 { grid-template-columns: 1fr; } }
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
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <a href="my_equipment.php" style="color:var(--forest-900); font-weight:600; text-decoration:none; font-size:14px;">← Back to My Equipment</a>
        <a href="dashboard.php" style="color:#686454; font-size:13px; text-decoration:none;">Dashboard Overview</a>
    </div>

    <div class="form-card">
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; display:block; margin-bottom:5px;">Equipment Fleet Management</span>
        <h1 style="font-family:Georgia,serif; font-size:30px; margin:0 0 25px; color:#122017;">List Farm Equipment for Rental</h1>

        <?php if (!$isVerified): ?>
            <!-- VERIFICATION GATE -->
            <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:24px; margin-bottom:25px; border-radius:4px;">
                <div style="display:flex; align-items:flex-start; gap:12px;">
                    <svg viewBox="0 0 24 24" style="width:28px; height:28px; stroke:#a54129; fill:none; stroke-width:2; flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <div>
                        <strong style="font-size:16px; display:block; margin-bottom:4px;">Equipment Owner Verification Required</strong>
                        <p style="margin:0 0 12px; font-size:13px; line-height:1.5;">
                            To ensure high machinery standards, renter security, and secure booking deposits, all equipment owners must have their Government ID and live Face Biometrics verified by AgriMart administration.
                        </p>
                        <a href="profile.php#verification" class="btn btn-solid" style="padding:8px 18px; font-size:12px; background:#a54129; border-color:#a54129;">
                            Complete Profile & Face Verification Now →
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:25px; font-size:14px;">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="add_equipment.php" method="POST" enctype="multipart/form-data">
            <fieldset <?= !$isVerified ? 'disabled style="opacity:0.6;"' : '' ?> style="border:none; padding:0; margin:0;">
                
                <div class="form-group">
                    <label for="equipment_name">Equipment / Machine Name</label>
                    <input type="text" id="equipment_name" name="equipment_name" class="form-control" required placeholder="e.g. Kubota 4WD 34HP Tractor with Rotavator">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-control" required onchange="handleCategoryChange(this)">
                            <option value="">-- Select Equipment Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['category_id'] ?>" data-name="<?= htmlspecialchars($cat['category_name']) ?>">
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rate_price">Rental Rate</label>
                        <div class="rate-combo-group">
                            <span class="rate-combo-prefix">₱</span>
                            <input type="number" step="0.01" min="1" id="rate_price" name="rate_price" class="form-control rate-combo-input" required placeholder="3500.00">
                            <select id="rate_type" name="rate_type" class="rate-combo-unit" style="border:none; outline:none; background:#f4f0e6; cursor:pointer; font-family:inherit; font-size:13px; font-weight:700; color:#122017; padding:0 12px;" onchange="handleRateTypeChange(this.value)">
                                <option value="daily">/ Per Day</option>
                                <option value="hourly">/ Per Hour</option>
                            </select>
                        </div>
                        <small style="color:#5e604e; font-size:11px; margin-top:5px; display:block;">🛡️ <strong>Damage & Loss Protection:</strong> A 20% refundable deposit is automatically added during booking to protect you against machinery damage or loss.</small>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="brand">Brand / Manufacturer</label>
                        <input type="text" id="brand" name="brand" class="form-control" placeholder="e.g. Kubota, Yanmar, Robin, Honda">
                    </div>

                    <div class="form-group">
                        <label for="model">Model / Specifications</label>
                        <input type="text" id="model" name="model" class="form-control" placeholder="e.g. L3408 4WD, 34 HP Diesel">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="location_city">Pickup City / Municipality</label>
                        <input type="text" id="location_city" name="location_city" class="form-control" required value="<?= htmlspecialchars($userCity) ?>" placeholder="e.g. Cabanatuan City">
                        <small style="color:#777; font-size:11px; margin-top:2px; display:block;">Used to match nearby renters seeking equipment.</small>
                    </div>

                    <div class="form-group">
                        <label for="location_province">Pickup Province</label>
                        <input type="text" id="location_province" name="location_province" class="form-control" required value="<?= htmlspecialchars($userProvince) ?>" placeholder="e.g. Nueva Ecija">
                    </div>
                </div>

                <div class="form-group">
                    <label for="equipment_image">Upload Machinery Image</label>
                    <input type="file" id="equipment_image" name="equipment_image" class="form-control" accept="image/*" style="padding:9px;">
                </div>

                <div class="form-group">
                    <label for="description">Detailed Description & Usage Terms</label>
                    <textarea id="description" name="description" class="form-control" rows="5" placeholder="Specify machine condition, operator inclusion (if any), fuel requirements, pickup/delivery arrangements..."></textarea>
                </div>

                <button type="submit" class="btn btn-solid" style="width:100%; padding:15px; font-size:14px; cursor:pointer;">
                    Publish Equipment Listing
                </button>
            </fieldset>
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
// Dynamic Category Rate Automatic Assignment
function handleCategoryChange(selectElement) {
    if (!selectElement) return;
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const catName = (selectedOption ? (selectedOption.getAttribute('data-name') || selectedOption.text || '') : '').toLowerCase();
    const rateTypeSelect = document.getElementById('rate_type');
    const ratePriceInput = document.getElementById('rate_price');

    if (!catName || selectElement.value === '') {
        return;
    }

    // Small power implements & tools that are rented by the hour
    const smallHourlyKeywords = ['pump', 'sprayer', 'irrigation', 'cutter', 'chainsaw', 'blower', 'mist'];

    let isHourly = false;
    for (let kw of smallHourlyKeywords) {
        if (catName.includes(kw)) {
            isHourly = true;
            break;
        }
    }

    if (isHourly) {
        if (rateTypeSelect) rateTypeSelect.value = 'hourly';
        if (ratePriceInput && (!ratePriceInput.value || ratePriceInput.value === '3500.00')) ratePriceInput.placeholder = '250.00';
    } else {
        if (rateTypeSelect) rateTypeSelect.value = 'daily';
        if (ratePriceInput && (!ratePriceInput.value || ratePriceInput.value === '250.00')) ratePriceInput.placeholder = '3500.00';
    }
}

function handleRateTypeChange(type) {
    const ratePriceInput = document.getElementById('rate_price');
    if (!ratePriceInput) return;
    if (type === 'hourly') {
        ratePriceInput.placeholder = '250.00';
    } else {
        ratePriceInput.placeholder = '3500.00';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const catSelect = document.getElementById('category_id');
    if (catSelect && catSelect.value) {
        handleCategoryChange(catSelect);
    }
});
</script>

</body>
</html>
