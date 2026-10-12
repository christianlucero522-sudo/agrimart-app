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

$reportType = trim($_GET['type'] ?? '');
if (empty($reportType)) {
    $reportType = (isset($_GET['tab']) && $_GET['tab'] === 'payments') ? 'sales' : 'sales';
}

// 1. Sales Report Data
$salesTotal = (float)($conn->query("SELECT SUM(amount) AS s FROM payments WHERE payment_status = 'paid'")->fetch_assoc()['s'] ?? 0);
$salesPending = (float)($conn->query("SELECT SUM(amount) AS s FROM payments WHERE payment_status = 'pending'")->fetch_assoc()['s'] ?? 0);
$ordersCount = (int)($conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'] ?? 0);
$ordersCompleted = (int)($conn->query("SELECT COUNT(*) AS c FROM orders WHERE order_status = 'completed'")->fetch_assoc()['c'] ?? 0);
$salesByMethod = $conn->query("SELECT payment_method, COUNT(*) AS count, SUM(amount) AS total FROM payments GROUP BY payment_method")->fetch_all(MYSQLI_ASSOC);
$topProducts = $conn->query("
    SELECT p.product_name, SUM(oi.quantity) AS qty_sold, SUM(oi.subtotal) AS total_sales
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    GROUP BY oi.product_id
    ORDER BY total_sales DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// 2. User Activity Report Data
$totalUsers = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role != 'admin'")->fetch_assoc()['c'] ?? 0);
$activeUsers = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 'active' AND role != 'admin'")->fetch_assoc()['c'] ?? 0);
$bannedUsers = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 'banned'")->fetch_assoc()['c'] ?? 0);
$activeBuyers = (int)($conn->query("SELECT COUNT(DISTINCT buyer_id) AS c FROM orders")->fetch_assoc()['c'] ?? 0);
$activeSellers = (int)($conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM products WHERE status NOT IN ('deleted', 'archived', '')")->fetch_assoc()['c'] ?? 0);
$activeOwners = (int)($conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM equipment WHERE status NOT IN ('deleted', 'archived', '')")->fetch_assoc()['c'] ?? 0);
$userList = $conn->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders WHERE buyer_id = u.user_id) AS orders_placed,
           (SELECT COUNT(*) FROM products WHERE user_id = u.user_id AND status NOT IN ('deleted', 'archived', '')) AS products_listed,
           (SELECT COUNT(*) FROM equipment WHERE user_id = u.user_id AND status NOT IN ('deleted', 'archived', '')) AS equipment_listed
    FROM users u
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// 3. Rental Report Data
$totalRentals = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()['c'] ?? 0);
$completedRentals = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'completed'")->fetch_assoc()['c'] ?? 0);
$ongoingRentals = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('confirmed', 'ongoing')")->fetch_assoc()['c'] ?? 0);
$rentalRevenue = (float)($conn->query("SELECT SUM(total_amount) AS s FROM bookings WHERE status IN ('confirmed', 'ongoing', 'completed')")->fetch_assoc()['s'] ?? 0);
$mostRented = $conn->query("
    SELECT e.equipment_name, e.brand, COUNT(b.booking_id) AS booking_count, SUM(b.total_amount) AS total_revenue
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    GROUP BY b.equipment_id
    ORDER BY booking_count DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// 4. Inventory Report Data
$productList = $conn->query("
    SELECT p.*, c.category_name, u.full_name AS seller_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    INNER JOIN users u ON p.user_id = u.user_id
    WHERE p.status NOT IN ('deleted', 'archived', '')
    ORDER BY p.quantity ASC
")->fetch_all(MYSQLI_ASSOC);

$equipmentList = $conn->query("
    SELECT e.*, c.category_name, u.full_name AS owner_name
    FROM equipment e
    INNER JOIN categories c ON e.category_id = c.category_id
    INNER JOIN users u ON e.user_id = u.user_id
    WHERE e.status NOT IN ('deleted', 'archived', '')
    ORDER BY e.availability ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive System Reports — AgriMart Admin Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
</head>
<body class="admin-console">

<?php 
$activeNav = 'payments';
include __DIR__ . '/admin_sidebar.php'; 
?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="topbar-subline">Analytics, settlements & executive reporting</div>
            <h1 class="topbar-heading">Payments & <i>reports.</i></h1>
        </div>
        <div class="topbar-actions">
            <button onclick="window.print()" class="btn-review-action">🖨️ Print Report</button>
            <a href="admin_dashboard.php" class="btn-review-action" style="background:#fff; color:#18241d; border:1px solid #dcd7cb;">Back to Overview</a>
        </div>
    </header>

    <!-- Report Filter Pills -->
    <div class="filter-pills-row no-print">
        <a href="admin_reports.php?type=sales" class="filter-pill <?= $reportType === 'sales' ? 'active' : '' ?>">1. Sales & Revenue</a>
        <a href="admin_reports.php?type=users" class="filter-pill <?= $reportType === 'users' ? 'active' : '' ?>">2. User Activity</a>
        <a href="admin_reports.php?type=rentals" class="filter-pill <?= $reportType === 'rentals' ? 'active' : '' ?>">3. Rental Performance</a>
        <a href="admin_reports.php?type=inventory" class="filter-pill <?= $reportType === 'inventory' ? 'active' : '' ?>">4. Inventory & Fleet</a>
    </div>

    <?php if ($reportType === 'sales'): ?>
        <!-- 1. SALES REPORT -->
        <div class="report-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2>AgriMart Marketplace Sales & Financial Report</h2>
                <span style="font-size:13px; color:#6b6a59;">Generated: <?= date('F d, Y h:i A') ?></span>
            </div>

            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="label">Total Paid Revenue</div>
                    <div class="val" style="color:var(--forest-900);">₱<?= number_format($salesTotal, 2) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Pending Payments</div>
                    <div class="val">₱<?= number_format($salesPending, 2) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Total Orders Placed</div>
                    <div class="val"><?= number_format($ordersCount) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Completed Orders</div>
                    <div class="val"><?= number_format($ordersCompleted) ?></div>
                </div>
            </div>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:25px 0 10px;">Payment Methods Breakdown</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Payment Method</th>
                        <th>Transactions Count</th>
                        <th>Gross Volume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesByMethod as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars(strtoupper($m['payment_method'])) ?></strong></td>
                            <td><?= (int)$m['count'] ?> transaction(s)</td>
                            <td><strong>₱<?= number_format((float)$m['total'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:30px 0 10px;">Top Revenue Generating Agricultural Products</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Units Sold</th>
                        <th>Gross Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $tp): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($tp['product_name']) ?></strong></td>
                            <td><?= (int)$tp['qty_sold'] ?> units</td>
                            <td><strong style="color:var(--forest-900);">₱<?= number_format((float)$tp['total_sales'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'users'): ?>
        <!-- 2. USER ACTIVITY REPORT -->
        <div class="report-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2>AgriMart User Activity & Registration Report</h2>
                <span style="font-size:13px; color:#6b6a59;">Generated: <?= date('F d, Y h:i A') ?></span>
            </div>

            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="label">Total Registered Users</div>
                    <div class="val"><?= number_format($totalUsers) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Active Accounts</div>
                    <div class="val" style="color:var(--forest-900);"><?= number_format($activeUsers) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Active Buyers</div>
                    <div class="val"><?= number_format($activeBuyers) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Active Sellers & Owners</div>
                    <div class="val"><?= number_format($activeSellers + $activeOwners) ?></div>
                </div>
            </div>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:25px 0 10px;">User Directory & Platform Engagement</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Email & Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Orders Placed</th>
                        <th>Products Listed</th>
                        <th>Equipment Listed</th>
                        <th>Member Since</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userList as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($u['email']) ?><br><small style="color:#777;"><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></small></td>
                            <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars(ucfirst($u['status'])) ?></span></td>
                            <td><?= (int)$u['orders_placed'] ?></td>
                            <td><?= (int)$u['products_listed'] ?></td>
                            <td><?= (int)$u['equipment_listed'] ?></td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'rentals'): ?>
        <!-- 3. RENTAL REPORT -->
        <div class="report-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2>AgriMart Machinery Rental Performance Report</h2>
                <span style="font-size:13px; color:#6b6a59;">Generated: <?= date('F d, Y h:i A') ?></span>
            </div>

            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="label">Total Rental Bookings</div>
                    <div class="val"><?= number_format($totalRentals) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Completed Bookings</div>
                    <div class="val"><?= number_format($completedRentals) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Active / Ongoing</div>
                    <div class="val" style="color:#1b4975;"><?= number_format($ongoingRentals) ?></div>
                </div>
                <div class="metric-box">
                    <div class="label">Gross Rental Volume</div>
                    <div class="val" style="color:var(--forest-900);">₱<?= number_format($rentalRevenue, 2) ?></div>
                </div>
            </div>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:25px 0 10px;">Most In-Demand Farm Machinery</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Equipment Name</th>
                        <th>Brand</th>
                        <th>Total Bookings</th>
                        <th>Generated Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mostRented as $mr): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($mr['equipment_name']) ?></strong></td>
                            <td><?= htmlspecialchars($mr['brand'] ?: 'Standard') ?></td>
                            <td><?= (int)$mr['booking_count'] ?> rental(s)</td>
                            <td><strong style="color:var(--forest-900);">₱<?= number_format((float)$mr['total_revenue'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'inventory'): ?>
        <!-- 4. INVENTORY REPORT -->
        <div class="report-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2>AgriMart Inventory & Equipment Fleet Report</h2>
                <span style="font-size:13px; color:#6b6a59;">Generated: <?= date('F d, Y h:i A') ?></span>
            </div>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:25px 0 10px;">Seed Products Inventory Levels (Lowest First)</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Seller</th>
                        <th>Unit Price</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productList as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['category_name']) ?></td>
                            <td><?= htmlspecialchars($p['seller_name']) ?></td>
                            <td>₱<?= number_format((float)$p['price'], 2) ?> / <?= htmlspecialchars($p['unit']) ?></td>
                            <td>
                                <strong style="<?= (int)$p['quantity'] <= 5 ? 'color:#b52b1b;' : 'color:#23581c;' ?>">
                                    <?= (int)$p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?>
                                    <?= (int)$p['quantity'] <= 5 ? ' (Low Stock Alert!)' : '' ?>
                                </strong>
                            </td>
                            <td><span class="badge badge-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="font-family:Georgia,serif; font-size:18px; margin:35px 0 10px;">Machinery & Equipment Fleet Availability</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Machinery Name</th>
                        <th>Category</th>
                        <th>Owner</th>
                        <th>Rental Rate</th>
                        <th>Availability</th>
                        <th>Listing Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipmentList as $e): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($e['equipment_name']) ?></strong> <?php if(!empty($e['brand'])): ?><small style="color:#777;">(<?= htmlspecialchars($e['brand']) ?>)</small><?php endif; ?></td>
                            <td><?= htmlspecialchars($e['category_name']) ?></td>
                            <td><?= htmlspecialchars($e['owner_name']) ?></td>
                            <td>₱<?= number_format((float)$e['rate_price'], 2) ?> / <?= htmlspecialchars($e['rate_type']) ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($e['availability']) ?>"><?= htmlspecialchars(ucfirst($e['availability'])) ?></span></td>
                            <td><span class="badge badge-<?= htmlspecialchars($e['status']) ?>"><?= htmlspecialchars(ucfirst($e['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
