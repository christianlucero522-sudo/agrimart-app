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
    <title>Manage Sellers & Owners — AgriMart Admin Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
</head>
<body class="admin-console">

<?php 
$activeNav = 'sellers';
include __DIR__ . '/admin_sidebar.php'; 
?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="topbar-subline">Merchant supervision & vendor analytics</div>
            <h1 class="topbar-heading">Sellers & <i>owners.</i></h1>
        </div>
        <div class="topbar-actions">
            <a href="admin_dashboard.php" class="btn-review-action">Back to Overview</a>
        </div>
    </header>

    <div class="transactions-card">
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
</body>
</html>
