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

// Fetch users who list products or equipment
$sql = "
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.status,
        u.created_at,
        COUNT(DISTINCT p.product_id) AS total_products,
        COUNT(DISTINCT e.equipment_id) AS total_equipment,
        COALESCE(SUM(oi.subtotal), 0) AS total_sales_volume,
        COUNT(DISTINCT b.booking_id) AS total_rentals_received
    FROM users u
    LEFT JOIN products p ON u.user_id = p.user_id
    LEFT JOIN equipment e ON u.user_id = e.user_id
    LEFT JOIN order_items oi ON u.user_id = oi.seller_id
    LEFT JOIN bookings b ON u.user_id = b.owner_id
    WHERE p.product_id IS NOT NULL OR e.equipment_id IS NOT NULL
    GROUP BY u.user_id
    ORDER BY total_sales_volume DESC, total_products DESC
";
$sellers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sellers & Owners — AgriMart Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .admin-wrap { width: min(1300px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .admin-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; }
        .admin-header h1 { font-family: Georgia, serif; font-size: clamp(30px, 4vw, 42px); margin: 0; color: #122017; }
        .table-panel { background: #fff; border: 1px solid #ded6b9; padding: 28px; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .admin-table th, .admin-table td { padding: 12px 14px; border-bottom: 1px solid #eee8d5; }
        .admin-table th { background: #f9f7f0; color: #5e604e; font-family: monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: #e0edd5; color: #23581c; }
        .badge-inactive { background: #efe2b7; color: #6e5817; }
        .badge-banned { background: #f7dcd6; color: #7e2b1b; }
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
        <a href="admin_sellers.php" class="active">Sellers</a>
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
    <div class="admin-header">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Merchant Supervision</span>
            <h1>Manage Sellers & Equipment Owners</h1>
        </div>
        <a href="admin_dashboard.php" class="btn btn-light">← Back to Dashboard</a>
    </div>

    <div class="table-panel">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Seller / Owner</th>
                    <th>Contact Info</th>
                    <th>Seed Products</th>
                    <th>Equipment Listings</th>
                    <th>Total Sales Revenue</th>
                    <th>Rentals Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sellers)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#6b6a59;">No sellers or equipment owners registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($sellers as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['email']) ?><br><small style="color:#777;"><?= htmlspecialchars($s['phone'] ?: 'No phone') ?></small></td>
                            <td><strong><?= (int)$s['total_products'] ?></strong> product(s)</td>
                            <td><strong><?= (int)$s['total_equipment'] ?></strong> machine(s)</td>
                            <td><strong style="color:var(--forest-900);">₱<?= number_format((float)$s['total_sales_volume'], 2) ?></strong></td>
                            <td><strong><?= (int)$s['total_rentals_received'] ?></strong> rental(s)</td>
                            <td><span class="badge badge-<?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td>
                            <td>
                                <form action="admin_users.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= (int)$s['user_id'] ?>">
                                    <input type="hidden" name="action" value="set_status">
                                    <?php if ($s['status'] === 'active'): ?>
                                        <button type="submit" name="status" value="banned" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#fae6df; color:#a54129; border-color:#efb7aa;" onclick="return confirm('Suspend this seller account?');">Suspend</button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="active" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#e0edd5; color:#23581c; border-color:#c5ddb4;">Approve / Activate</button>
                                    <?php endif; ?>
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
