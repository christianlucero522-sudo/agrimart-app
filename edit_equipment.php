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
$catRes = $conn->query("SELECT category_id, category_name FROM categories WHERE category_type = 'equipment' ORDER BY category_name ASC");
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

    if ($name === '' || $categoryId <= 0 || $ratePrice <= 0) {
        $error = 'Please fill in the equipment name, category, and valid rental rate.';
    } else {
        $imageUrl = $equipment['image_url'];
        if (isset($_FILES['equipment_image']) && $_FILES['equipment_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['equipment_image']['tmp_name'];
            $orig = basename($_FILES['equipment_image']['name']);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            
            if (in_array($ext, $allowed)) {
                $newFilename = 'equip_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $dest = __DIR__ . '/uploads/equipment/' . $newFilename;
                if (move_uploaded_file($tmp, $dest)) {
                    $imageUrl = 'uploads/equipment/' . $newFilename;
                }
            }
        }

        $upSql = "UPDATE equipment SET category_id = ?, equipment_name = ?, description = ?, brand = ?, model = ?, rate_type = ?, rate_price = ?, availability = ?, image_url = ? WHERE equipment_id = ?";
        $uStmt = $conn->prepare($upSql);
        $uStmt->bind_param('isssssdssi', $categoryId, $name, $description, $brand, $model, $rateType, $ratePrice, $availability, $imageUrl, $equipmentId);
        
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
    <title>Edit Equipment ? AgriMart</title>
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
    <a href="my_equipment.php" style="display:inline-block; margin-bottom:20px; color:var(--forest-900); font-weight:600; text-decoration:none; font-size:14px;">? Back to My Equipment</a>

    <div class="form-card">
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; display:block; margin-bottom:5px;">Equipment Management</span>
        <h1 style="font-family:Georgia,serif; font-size:32px; margin:0 0 25px; color:#122017;">Edit Equipment Listing</h1>

        <?php if (!empty($error)): ?>
            <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:25px; font-size:14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="edit_equipment.php?id=<?= $equipmentId ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="equipment_name">Equipment / Machine Name *</label>
                <input type="text" id="equipment_name" name="equipment_name" class="form-control" required value="<?= htmlspecialchars($equipment['equipment_name']) ?>">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['category_id'] ?>" <?= ((int)$cat['category_id'] === (int)$equipment['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="availability">Availability Status *</label>
                    <select id="availability" name="availability" class="form-control">
                        <option value="available" <?= $equipment['availability'] === 'available' ? 'selected' : '' ?>>Available for Rent</option>
                        <option value="rented" <?= $equipment['availability'] === 'rented' ? 'selected' : '' ?>>Currently Rented</option>
                        <option value="maintenance" <?= $equipment['availability'] === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="rate_type">Rate Type *</label>
                    <select id="rate_type" name="rate_type" class="form-control">
                        <option value="daily" <?= $equipment['rate_type'] === 'daily' ? 'selected' : '' ?>>Daily Rate (? / day)</option>
                        <option value="hourly" <?= $equipment['rate_type'] === 'hourly' ? 'selected' : '' ?>>Hourly Rate (? / hour)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rate_price">Rental Price (?) *</label>
                    <input type="number" step="0.01" min="1" id="rate_price" name="rate_price" class="form-control" required value="<?= (float)$equipment['rate_price'] ?>">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="brand">Brand</label>
                    <input type="text" id="brand" name="brand" class="form-control" value="<?= htmlspecialchars($equipment['brand'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="model">Model / Specs</label>
                    <input type="text" id="model" name="model" class="form-control" value="<?= htmlspecialchars($equipment['model'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="equipment_image">Replace Image (Optional)</label>
                <?php if (!empty($equipment['image_url'])): ?>
                    <div style="margin-bottom:10px;">
                        <img src="<?= htmlspecialchars($equipment['image_url']) ?>" style="width:70px; height:70px; object-fit:cover; border:1px solid #d8cba1;" alt="">
                    </div>
                <?php endif; ?>
                <input type="file" id="equipment_image" name="equipment_image" class="form-control" accept="image/*">
            </div>

            <div class="form-group">
                <label for="description">Detailed Description & Usage Guidelines</label>
                <textarea id="description" name="description" class="form-control" rows="5"><?= htmlspecialchars($equipment['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-solid" style="width:100%; padding:16px; font-size:14px; cursor:pointer;">Save Changes</button>
        </form>
    </div>
</main>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>? 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
