<?php
session_start();
require_once 'config.php';

// Require Admin Login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Admin';

// Key Metrics
$totalUsers = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role != 'admin'")->fetch_assoc()['c'] ?? 0);
$totalSellers = (int)($conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM products")->fetch_assoc()['c'] ?? 0);
$totalProducts = (int)($conn->query("SELECT COUNT(*) AS c FROM products WHERE status = 'active'")->fetch_assoc()['c'] ?? 0);
$totalEquipment = (int)($conn->query("SELECT COUNT(*) AS c FROM equipment WHERE status = 'active'")->fetch_assoc()['c'] ?? 0);
$totalOrders = (int)($conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'] ?? 0);
$totalSalesRevenue = (float)($conn->query("SELECT SUM(amount) AS s FROM payments WHERE payment_status = 'paid'")->fetch_assoc()['s'] ?? 0);
$activeRentals = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('confirmed', 'ongoing')")->fetch_assoc()['c'] ?? 0);

// Recent Orders
$recentOrders = $conn->query("
    SELECT o.order_id, o.total_amount, o.order_status, o.created_at, u.full_name AS buyer_name
    FROM orders o
    INNER JOIN users u ON o.buyer_id = u.user_id
    ORDER BY o.order_id DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent Rentals
$recentRentals = $conn->query("
    SELECT b.booking_id, b.start_date, b.end_date, b.total_amount, b.status, e.equipment_name, u.full_name AS renter_name
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users u ON b.renter_id = u.user_id
    ORDER BY b.booking_id DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard ? AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .admin-wrap { width: min(1300px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .admin-header { margin-bottom: 35px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; }
        .admin-header h1 { font-family: Georgia, serif; font-size: clamp(32px, 4vw, 48px); margin: 0; color: #122017; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: #fff; border: 1px solid #ded6b9; padding: 24px; }
        .stat-card .label { font-family: monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #768047; }
        .stat-card .val { font-family: Georgia, serif; font-size: 34px; font-weight: 700; color: #122017; margin: 8px 0 4px; }
        .nav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 45px; }
        .admin-nav-card { background: #0d2818; color: #f5f0df; padding: 26px; border: 1px solid #0d2818; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px; transition: transform .2s ease; }
        .admin-nav-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .admin-nav-card h3 { margin: 0 0 8px; font-family: Georgia, serif; font-size: 22px; color: #f5f0df; }
        .admin-nav-card p { margin: 0; font-size: 13px; color: #c8c6b2; line-height: 1.5; }
        .admin-nav-card .arrow { font-family: monospace; font-size: 11px; text-transform: uppercase; color: #d3b55a; margin-top: 15px; }
        .table-panel { background: #fff; border: 1px solid #ded6b9; padding: 28px; margin-bottom: 30px; }
        .table-panel h2 { font-family: Georgia, serif; font-size: 22px; margin: 0 0 20px; color: #122017; border-bottom: 1px solid #efe8d3; padding-bottom: 10px; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .admin-table th, .admin-table td { padding: 12px 14px; border-bottom: 1px solid #eee8d5; }
        .admin-table th { background: #f9f7f0; color: #5e604e; font-family: monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-ongoing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
    </style>
</head>
<body>

<header class="site-header">
<div class="wrap">
    <a href="admin_dashboard.php" class="logo">
        <span class="logo-text"><b>AgriMart Admin</b><span>System Management Console</span></span>
    </a>
    <nav class="main-nav">
        <a href="admin_dashboard.php" class="active">Dashboard</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_sellers.php">Sellers</a>
        <a href="admin_listings.php">Listings</a>
        <a href="admin_rentals.php">Rentals</a>
        <a href="admin_sales.php">Sales</a>
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
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">System Administration</span>
            <h1>Administrator Control Center</h1>
        </div>
        <div>
            <a href="admin_reports.php" class="btn btn-solid" style="margin-right:8px;">?? Generate Reports</a>
            <a href="index.php" class="btn btn-light">View Public Site</a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="stat-grid">
        <div class="stat-card">
            <span class="label">Total Users</span>
            <div class="val"><?= number_format($totalUsers) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Registered Buyers & Sellers</span>
        </div>
        <div class="stat-card">
            <span class="label">Active Seed Products</span>
            <div class="val"><?= number_format($totalProducts) ?></div>
            <span style="color:#6b6a59; font-size:12px;">In Marketplace</span>
        </div>
        <div class="stat-card">
            <span class="label">Active Equipment</span>
            <div class="val"><?= number_format($totalEquipment) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Available for Rent</span>
        </div>
        <div class="stat-card">
            <span class="label">Active Rentals</span>
            <div class="val"><?= number_format($activeRentals) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Ongoing / Confirmed</span>
        </div>
        <div class="stat-card">
            <span class="label">Paid Platform Revenue</span>
            <div class="val">?<?= number_format($totalSalesRevenue, 2) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Processed Payments</span>
        </div>
    </div>

    <!-- Admin Modules Navigation -->
    <h2 style="font-family:Georgia,serif; font-size:26px; margin:0 0 20px; color:#122017;">Management Modules</h2>
    <div class="nav-grid">
        <a href="admin_users.php" class="admin-nav-card">
            <div>
                <h3>Manage Users</h3>
                <p>View all accounts, block/unblock, activate, or update user permissions.</p>
            </div>
            <span class="arrow">Manage Accounts ?</span>
        </a>

        <a href="admin_sellers.php" class="admin-nav-card">
            <div>
                <h3>Manage Sellers</h3>
                <p>Inspect farmer and equipment owner profiles, listings, and reputation.</p>
            </div>
            <span class="arrow">View Sellers ?</span>
        </a>

        <a href="admin_listings.php" class="admin-nav-card">
            <div>
                <h3>Manage Listings</h3>
                <p>Moderate seeds, agricultural produce, and machinery catalog listings.</p>
            </div>
            <span class="arrow">Moderate Listings ?</span>
        </a>

        <a href="admin_rentals.php" class="admin-nav-card">
            <div>
                <h3>Manage Rentals</h3>
                <p>Monitor machinery rental bookings, schedules, and approval statuses.</p>
            </div>
            <span class="arrow">Monitor Rentals ?</span>
        </a>

        <a href="admin_sales.php" class="admin-nav-card">
            <div>
                <h3>Manage Sales & Orders</h3>
                <p>Track all marketplace customer orders, fulfillments, and payment receipts.</p>
            </div>
            <span class="arrow">Manage Sales ?</span>
        </a>

        <a href="admin_categories.php" class="admin-nav-card">
            <div>
                <h3>Manage Categories</h3>
                <p>Create, update, and organize categories for seeds and farm equipment.</p>
            </div>
            <span class="arrow">Organize Categories ?</span>
        </a>

        <a href="admin_reports.php" class="admin-nav-card">
            <div>
                <h3>Generate Reports</h3>
                <p>Generate printable Sales, User Activity, Rental, and Inventory reports.</p>
            </div>
            <span class="arrow">Generate Reports ?</span>
        </a>
    </div>

    <!-- Recent Platform Activity -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px;">
        <div class="table-panel">
            <h2>Recent Orders</h2>
            <?php if (empty($recentOrders)): ?>
                <p style="color:#6b6a59;">No recent orders recorded.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Buyer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td><strong>#<?= (int)$ro['order_id'] ?></strong></td>
                                <td><?= htmlspecialchars($ro['buyer_name']) ?></td>
                                <td>?<?= number_format((float)$ro['total_amount'], 2) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($ro['order_status']) ?>"><?= htmlspecialchars(ucfirst($ro['order_status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="table-panel">
            <h2>Recent Rental Bookings</h2>
            <?php if (empty($recentRentals)): ?>
                <p style="color:#6b6a59;">No recent rental bookings recorded.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Equipment</th>
                            <th>Renter</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRentals as $rb): ?>
                            <tr>
                                <td><strong>#<?= (int)$rb['booking_id'] ?></strong></td>
                                <td><?= htmlspecialchars($rb['equipment_name']) ?></td>
                                <td><?= htmlspecialchars($rb['renter_name']) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($rb['status']) ?>"><?= htmlspecialchars(ucfirst($rb['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>? 2026 AgriMart Administration. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
