<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';
$fullName = $_SESSION['full_name'] ?? 'User';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'User';

$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($equipmentId <= 0) {
    header('Location: my_equipment.php');
    exit;
}

// Fetch equipment and verify ownership
$sql = "SELECT * FROM equipment WHERE equipment_id = ? " . ($userRole === 'admin' ? "" : "AND user_id = $userId") . " LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $equipmentId);
$stmt->execute();
$equipment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$equipment) {
    header('Location: my_equipment.php');
    exit;
}

// Fetch Equipment Categories
$catRes = $conn->query("SELECT category_id, category_name, COALESCE(rental_rate_type, 'daily') AS rental_rate_type FROM categories WHERE category_type = 'equipment' ORDER BY category_name ASC");
$categories = [];
while ($c = $catRes->fetch_assoc()) {
    $categories[] = $c;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['equipment_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $rateType = trim($_POST['rate_type'] ?? 'daily');
    $ratePrice = (float)($_POST['rate_price'] ?? 0);
    $availability = trim($_POST['availability'] ?? 'available');
    $description = trim($_POST['description'] ?? '');
    $locationCity = trim($_POST['location_city'] ?? '');
    $locationProvince = trim($_POST['location_province'] ?? '');

    if ($name === '' || $categoryId <= 0 || $ratePrice <= 0) {
        $error = 'Please fill in the equipment name, category, and valid rental rate.';
    } else {
        // Enforce rate type policy strictly based on category
        $catCheckStmt = $conn->prepare("SELECT category_name, COALESCE(rental_rate_type, 'daily') AS rental_rate_type FROM categories WHERE category_id = ? LIMIT 1");
        $catCheckStmt->bind_param('i', $categoryId);
        $catCheckStmt->execute();
        $catRow = $catCheckStmt->get_result()->fetch_assoc();
        $catCheckStmt->close();

        $rateType = ($catRow['rental_rate_type'] ?? '') === 'hourly' ? 'hourly' : 'daily';
        $imageUrl = $equipment['image_url'];
        if (isset($_FILES['equipment_image']) && $_FILES['equipment_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = saveUploadedImage($_FILES['equipment_image'], 'equipment');
            if ($uploadResult['success'] && !empty($uploadResult['path'])) {
                $imageUrl = $uploadResult['path'];
            }
        }

        $upSql = "UPDATE equipment SET category_id = ?, equipment_name = ?, description = ?, brand = ?, model = ?, rate_type = ?, rate_price = ?, availability = ?, image_url = ?, location_city = ?, location_province = ? WHERE equipment_id = ?";
        $uStmt = $conn->prepare($upSql);
        $uStmt->bind_param('isssssdssssi', $categoryId, $name, $description, $brand, $model, $rateType, $ratePrice, $availability, $imageUrl, $locationCity, $locationProvince, $equipmentId);
        
        if ($uStmt->execute()) {
            $uStmt->close();
            header('Location: my_equipment.php?updated=1');
            exit;
        } else {
            $error = 'Error updating equipment listing: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Equipment — AgriMart</title>
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
        <h1 style="font-family:Georgia,serif; font-size:30px; margin:0 0 25px; color:#122017;">Edit Equipment Listing</h1>

        <?php if (!empty($error)): ?>
            <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:25px; font-size:14px;">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="edit_equipment.php?id=<?= $equipmentId ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="equipment_name">Equipment / Machine Name</label>
                <input type="text" id="equipment_name" name="equipment_name" class="form-control" required value="<?= htmlspecialchars($equipment['equipment_name']) ?>">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control" required onchange="handleCategoryChange(this)">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['category_id'] ?>" data-name="<?= htmlspecialchars($cat['category_name']) ?>" data-rate-type="<?= htmlspecialchars($cat['rental_rate_type']) ?>" <?= ((int)$cat['category_id'] === (int)$equipment['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?> (<?= $cat['rental_rate_type'] === 'hourly' ? 'Per Hour' : 'Per Day' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rate_price">Rental Rate</label>
                    <?php
                        $currentRateType = strtolower($equipment['rate_type'] ?? 'daily');
                        if (!in_array($currentRateType, ['daily', 'hourly'])) {
                            $currentRateType = 'daily';
                        }
                        $currentRateUnitText = ($currentRateType === 'hourly') ? '/ Per Hour' : '/ Per Day';
                    ?>
                    <div class="rate-combo-group">
                        <span class="rate-combo-prefix">₱</span>
                        <input type="number" step="0.01" min="1" id="rate_price" name="rate_price" class="form-control rate-combo-input" required value="<?= (float)$equipment['rate_price'] ?>" placeholder="<?= $currentRateType === 'hourly' ? '250.00' : '3500.00' ?>">
                        <div class="rate-combo-unit" style="background:#ede8d3; color:#122017; font-family:inherit; font-size:13px; font-weight:700; padding:0 14px; border-left:1px solid #d8cba1; user-select:none; display:flex; align-items:center; gap:5px;" title="Fixed rental rate basis for this category">
                            <span id="rate_unit_text"><?= $currentRateUnitText ?></span>
                            <span style="font-size:11px; opacity:0.6;">🔒</span>
                        </div>
                        <input type="hidden" id="rate_type" name="rate_type" value="<?= htmlspecialchars($currentRateType) ?>">
                    </div>
                    <small style="color:#5e604e; font-size:11px; margin-top:5px; display:block;">🛡️ <strong>Damage & Loss Protection:</strong> A 20% refundable deposit is automatically added during booking to protect you against machinery damage or loss.</small>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="availability">Availability Status</label>
                    <select id="availability" name="availability" class="form-control">
                        <option value="available" <?= $equipment['availability'] === 'available' ? 'selected' : '' ?>>Available for Rent</option>
                        <option value="rented" <?= $equipment['availability'] === 'rented' ? 'selected' : '' ?>>Currently Rented</option>
                        <option value="maintenance" <?= $equipment['availability'] === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="brand">Brand</label>
                    <input type="text" id="brand" name="brand" class="form-control" value="<?= htmlspecialchars($equipment['brand'] ?? '') ?>" placeholder="e.g. Kubota, Yanmar">
                </div>
            </div>

            <div class="form-group">
                <label for="model">Model / Specifications</label>
                <input type="text" id="model" name="model" class="form-control" value="<?= htmlspecialchars($equipment['model'] ?? '') ?>" placeholder="e.g. L3408 4WD, 34 HP Diesel">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="location_city">Pickup City / Municipality</label>
                    <input type="text" id="location_city" name="location_city" class="form-control" value="<?= htmlspecialchars($equipment['location_city'] ?? '') ?>" placeholder="e.g. Cabanatuan City">
                </div>

                <div class="form-group">
                    <label for="location_province">Pickup Province</label>
                    <input type="text" id="location_province" name="location_province" class="form-control" value="<?= htmlspecialchars($equipment['location_province'] ?? '') ?>" placeholder="e.g. Nueva Ecija">
                </div>
            </div>

            <div class="form-group">
                <label for="equipment_image">Replace Image (Optional)</label>
                <?php if (!empty($equipment['image_url'])): ?>
                    <div style="margin-bottom:10px;">
                        <img src="<?= htmlspecialchars($equipment['image_url']) ?>" style="width:70px; height:70px; object-fit:cover; border:1px solid #d8cba1;" alt="">
                    </div>
                <?php endif; ?>
                <input type="file" id="equipment_image" name="equipment_image" class="form-control" accept="image/*" style="padding:9px;">
            </div>

            <div class="form-group">
                <label for="description">Detailed Description & Usage Guidelines</label>
                <textarea id="description" name="description" class="form-control" rows="5"><?= htmlspecialchars($equipment['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-solid" style="width:100%; padding:15px; font-size:14px; cursor:pointer;">
                Save Changes
            </button>
        </form>

        <form action="delete_equipment.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently remove <?= addslashes(htmlspecialchars($equipment['equipment_name'])) ?> from your equipment listings?');" style="margin-top:15px;">
            <input type="hidden" name="equipment_id" value="<?= (int)$equipmentId ?>">
            <button type="submit" class="btn btn-light" style="width:100%; padding:12px; font-size:13px; background:#fae6df; color:#a54129; border:1px solid #efb7aa; font-weight:700; cursor:pointer;">
                ✕ Remove / Delete This Listing
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
// Dynamic Category Rate Assignment & Unit Locking
function handleCategoryChange(selectElement) {
    if (!selectElement) return;
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    if (!selectedOption || selectElement.value === '') return;

    const rateType = selectedOption.getAttribute('data-rate-type') || 'daily';
    const rateTypeHidden = document.getElementById('rate_type');
    const rateUnitText = document.getElementById('rate_unit_text');
    const ratePriceInput = document.getElementById('rate_price');

    if (rateType === 'hourly') {
        if (rateTypeHidden) rateTypeHidden.value = 'hourly';
        if (rateUnitText) rateUnitText.textContent = '/ Per Hour';
        if (ratePriceInput && !ratePriceInput.value) ratePriceInput.placeholder = '250.00';
    } else {
        if (rateTypeHidden) rateTypeHidden.value = 'daily';
        if (rateUnitText) rateUnitText.textContent = '/ Per Day';
        if (ratePriceInput && !ratePriceInput.value) ratePriceInput.placeholder = '3500.00';
    }
}
</script>

</body>
</html>
