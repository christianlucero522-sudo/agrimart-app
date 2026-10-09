<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Admin';

$message = '';
// Handle Product / Equipment Moderation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemType = trim($_POST['item_type'] ?? '');
    $itemId = (int)($_POST['item_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');

    if ($itemId > 0) {
        if ($itemType === 'product') {
            if ($action === 'toggle') {
                $conn->query("UPDATE products SET status = IF(status = 'active', 'inactive', 'active') WHERE product_id = $itemId");
                $message = "Product listing #$itemId status toggled.";
            } elseif ($action === 'delete') {
                $conn->query("DELETE FROM products WHERE product_id = $itemId");
                $message = "Product #$itemId removed from marketplace.";
            }
        } elseif ($itemType === 'equipment') {
            if ($action === 'toggle') {
                $conn->query("UPDATE equipment SET status = IF(status = 'active', 'inactive', 'active') WHERE equipment_id = $itemId");
                $message = "Equipment listing #$itemId status toggled.";
            } elseif ($action === 'delete') {
                $conn->query("DELETE FROM equipment WHERE equipment_id = $itemId");
                $message = "Equipment #$itemId removed from marketplace.";
            }
        }
    }
}

// Fetch all products
$products = $conn->query("
    SELECT p.*, c.category_name, u.full_name AS seller_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    INNER JOIN users u ON p.user_id = u.user_id
    ORDER BY p.product_id DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch all equipment
$equipment = $conn->query("
    SELECT e.*, c.category_name, u.full_name AS owner_name
    FROM equipment e
    INNER JOIN categories c ON e.category_id = c.category_id
    INNER JOIN users u ON e.user_id = u.user_id
    ORDER BY e.equipment_id DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Listings ? AgriMart Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .admin-wrap { width: min(1300px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .admin-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; }
        .admin-header h1 { font-family: Georgia, serif; font-size: clamp(30px, 4vw, 42px); margin: 0; color: #122017; }
        .table-panel { background: #fff; border: 1px solid #ded6b9; padding: 28px; margin-bottom: 35px; }
        .table-panel h2 { font-family: Georgia, serif; font-size: 22px; margin: 0 0 15px; color: #122017; border-bottom: 1px solid #eee8d5; padding-bottom: 10px; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .admin-table th, .admin-table td { padding: 12px 14px; border-bottom: 1px solid #eee8d5; }
        .admin-table th { background: #f9f7f0; color: #5e604e; font-family: monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-active, .badge-available { background: #e0edd5; color: #23581c; }
        .badge-inactive, .badge-rented { background: #efe2b7; color: #6e5817; }
        .badge-sold, .badge-maintenance { background: #f7dcd6; color: #7e2b1b; }
    </style>
</head>
<body>

<header class="site-header">
<div class="wrap">
    <a href="admin_dashboard.php" class="logo">
        <span class="logo-text"><b>AgriMart Admin</b><span>System Management Console</span></span>
    </a>
    <nav class="main-nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_sellers.php">Sellers</a>
        <a href="admin_listings.php" class="active">Listings</a>
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
    <div class="admin-header">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Catalog Moderation</span>
            <h1>Manage Products & Equipment Listings</h1>
        </div>
        <a href="admin_dashboard.php" class="btn btn-light">← Back to Dashboard</a>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:20px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Product Listings -->
    <div class="table-panel">
        <h2>Seed & Agricultural Produce Listings (<?= count($products) ?>)</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Seller</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:#6b6a59;">No products listed.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['category_name']) ?></td>
                            <td><?= htmlspecialchars($p['seller_name']) ?></td>
                            <td><strong>₱<?= number_format((float)$p['price'], 2) ?></strong> / <?= htmlspecialchars($p['unit']) ?></td>
                            <td><?= (int)$p['quantity'] ?> left</td>
                            <td><span class="badge badge-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span></td>
                            <td>
                                <form action="admin_listings.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="item_type" value="product">
                                    <input type="hidden" name="item_id" value="<?= (int)$p['product_id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button type="submit" class="btn btn-light" style="padding:4px 8px; font-size:11px;">
                                        <?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form action="admin_listings.php" method="POST" style="display:inline;" onsubmit="return confirm('Permanently remove this product?');">
                                    <input type="hidden" name="item_type" value="product">
                                    <input type="hidden" name="item_id" value="<?= (int)$p['product_id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#fae6df; color:#a54129; border-color:#efb7aa;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Equipment Listings -->
    <div class="table-panel">
        <h2>Farm Equipment & Machinery Listings (<?= count($equipment) ?>)</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Machinery</th>
                    <th>Category</th>
                    <th>Owner</th>
                    <th>Rental Rate</th>
                    <th>Availability</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipment)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:#6b6a59;">No equipment listed.</td></tr>
                <?php else: ?>
                    <?php foreach ($equipment as $e): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($e['equipment_name']) ?></strong> <?php if (!empty($e['brand'])): ?><small style="color:#777;">(<?= htmlspecialchars($e['brand']) ?>)</small><?php endif; ?></td>
                            <td><?= htmlspecialchars($e['category_name']) ?></td>
                            <td><?= htmlspecialchars($e['owner_name']) ?></td>
                            <td><strong>₱<?= number_format((float)$e['rate_price'], 2) ?></strong> / <?= htmlspecialchars($e['rate_type']) ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($e['availability']) ?>"><?= htmlspecialchars(ucfirst($e['availability'])) ?></span></td>
                            <td><span class="badge badge-<?= htmlspecialchars($e['status']) ?>"><?= htmlspecialchars(ucfirst($e['status'])) ?></span></td>
                            <td>
                                <form action="admin_listings.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="item_type" value="equipment">
                                    <input type="hidden" name="item_id" value="<?= (int)$e['equipment_id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button type="submit" class="btn btn-light" style="padding:4px 8px; font-size:11px;">
                                        <?= $e['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form action="admin_listings.php" method="POST" style="display:inline;" onsubmit="return confirm('Permanently remove this equipment?');">
                                    <input type="hidden" name="item_type" value="equipment">
                                    <input type="hidden" name="item_id" value="<?= (int)$e['equipment_id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#fae6df; color:#a54129; border-color:#efb7aa;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

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
