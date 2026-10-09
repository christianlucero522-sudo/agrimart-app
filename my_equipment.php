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

$sql = "
    SELECT 
        e.equipment_id,
        e.equipment_name,
        e.description,
        e.brand,
        e.model,
        e.rate_type,
        e.rate_price,
        e.availability,
        e.image_url,
        e.status,
        c.category_name,
        (SELECT COUNT(*) FROM bookings WHERE equipment_id = e.equipment_id) AS total_bookings
    FROM equipment e
    INNER JOIN categories c ON e.category_id = c.category_id
    WHERE e.user_id = ? AND e.status != 'deleted' AND e.status != 'archived'
    ORDER BY e.equipment_id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$equipmentList = [];
while ($row = $result->fetch_assoc()) {
    $equipmentList[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Equipment Listings — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1200px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; flex-wrap: wrap; gap: 20px; }
        .page-header h1 { font-family: Georgia, serif; font-size: clamp(32px, 4vw, 48px); margin: 0; color: #122017; }
        .equip-card { background: #fff; border: 1px solid #ded6b9; padding: 24px; margin-bottom: 20px; display: grid; grid-template-columns: 100px 1fr auto; gap: 25px; align-items: center; }
        .equip-img { width: 100px; height: 100px; object-fit: cover; background: var(--forest-950); }
        .meta-row { display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: #6b6a59; margin-bottom: 8px; }
        .meta-row strong { color: #122017; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-available { background: #e0edd5; color: #23581c; }
        .badge-rented { background: #efe2b7; color: #6e5817; }
        .badge-maintenance { background: #f7dcd6; color: #7e2b1b; }
        .badge-active { background: #d0e3f5; color: #1b4975; }
        .badge-inactive { background: #eee; color: #666; }
        .actions-group { display: flex; gap: 8px; flex-wrap: wrap; }
        @media(max-width:768px) { .equip-card { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="site-header is-solid" id="siteHeader">
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
    <div class="page-header">
        <div>
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Owner Fleet</span>
            <h1>My Equipment Listings</h1>
        </div>
        <div>
            <a href="rental_requests.php" class="btn btn-light" style="margin-right:8px;">View Rental Requests</a>
            <a href="add_equipment.php" class="btn btn-solid">+ List New Equipment</a>
        </div>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
            ✓ Equipment listing created successfully and is now active for rental!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
            ✓ Equipment listing updated successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['toggled'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
            ✓ Listing status toggled successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:25px; font-weight:500;">
            ✓ Equipment listing removed successfully.
        </div>
    <?php endif; ?>

    <?php if (empty($equipmentList)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:50px; text-align:center;">
            <h3 style="font-family:Georgia,serif; font-size:24px; margin-bottom:10px;">No equipment listings yet</h3>
            <p style="color:#6b6a59; margin-bottom:25px;">You haven't listed any farm machinery or tools for rent yet.</p>
            <a href="add_equipment.php" class="btn btn-solid">List Your First Equipment</a>
        </div>
    <?php else: ?>
        <?php foreach ($equipmentList as $item): ?>
            <div class="equip-card">
                <img src="<?= htmlspecialchars($item['image_url'] ?: 'images/placeholder-equipment.svg') ?>" class="equip-img" alt="">
                <div>
                    <div style="font-family:Georgia,serif; font-size:22px; font-weight:700; color:#122017; margin-bottom:5px;">
                        <?= htmlspecialchars($item['equipment_name']) ?>
                    </div>
                    <div class="meta-row">
                        <span>Category: <strong><?= htmlspecialchars($item['category_name']) ?></strong></span>
                        <?php if (!empty($item['brand'])): ?><span>Brand: <strong><?= htmlspecialchars($item['brand']) ?></strong></span><?php endif; ?>
                        <?php if (!empty($item['model'])): ?><span>Model: <strong><?= htmlspecialchars($item['model']) ?></strong></span><?php endif; ?>
                        <span>Total Bookings: <strong><?= (int)$item['total_bookings'] ?></strong></span>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                        <span class="badge badge-<?= htmlspecialchars($item['availability']) ?>">
                            <?= htmlspecialchars(ucfirst($item['availability'])) ?>
                        </span>
                        <span class="badge badge-<?= htmlspecialchars($item['status']) ?>">
                            <?= htmlspecialchars(ucfirst($item['status'])) ?>
                        </span>
                        <span style="font-weight:700; font-size:17px; color:var(--forest-900); margin-left:10px;">
                            ₱<?= number_format((float)$item['rate_price'], 2) ?> / <?= htmlspecialchars($item['rate_type']) ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="actions-group">
                        <a href="equipment_details.php?id=<?= (int)$item['equipment_id'] ?>" class="btn btn-light" target="_blank">View</a>
                        <a href="edit_equipment.php?id=<?= (int)$item['equipment_id'] ?>" class="btn btn-light">Edit</a>
                        <form action="toggle_equipment_status.php" method="POST" style="display:inline;">
                            <input type="hidden" name="equipment_id" value="<?= (int)$item['equipment_id'] ?>">
                            <button type="submit" class="btn btn-dark" style="font-size:12px; padding:10px 14px;">
                                <?= $item['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <form action="delete_equipment.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently remove <?= addslashes(htmlspecialchars($item['equipment_name'])) ?> from your equipment listings?');">
                            <input type="hidden" name="equipment_id" value="<?= (int)$item['equipment_id'] ?>">
                            <button type="submit" class="btn btn-light" style="font-size:12px; padding:10px 14px; background:#fae6df; color:#a54129; border:1px solid #efb7aa; font-weight:700; cursor:pointer;">
                                ✕ Remove
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>&copy; 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
