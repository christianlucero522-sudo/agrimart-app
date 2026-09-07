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
// Handle Admin Order / Payment Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = trim($_POST['order_status'] ?? '');
    $newPayStatus = trim($_POST['payment_status'] ?? '');
    
    if ($orderId > 0) {
        if (!empty($newStatus)) {
            $conn->query("UPDATE orders SET order_status = '$newStatus' WHERE order_id = $orderId");
        }
        if (!empty($newPayStatus)) {
            $conn->query("UPDATE payments SET payment_status = '$newPayStatus' WHERE order_id = $orderId");
        }
        $message = "Order #$orderId records updated successfully.";
    }
}

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        o.order_id,
        o.total_amount,
        o.shipping_address,
        o.order_status,
        o.created_at,
        u.full_name AS buyer_name,
        u.phone AS buyer_phone,
        p.payment_id,
        p.payment_method,
        p.payment_status,
        p.transaction_ref,
        p.payment_proof,
        COUNT(oi.order_item_id) AS total_items
    FROM orders o
    INNER JOIN users u ON o.buyer_id = u.user_id
    LEFT JOIN payments p ON o.order_id = p.order_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $sql .= " AND o.order_status = '$statusFilter'";
}

$sql .= " GROUP BY o.order_id ORDER BY o.order_id DESC";

$orders = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sales & Transactions ? AgriMart Admin</title>
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
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-processing { background: #e2d9f3; color: #432b70; }
        .badge-completed, .badge-paid { background: #e0edd5; color: #23581c; }
        .badge-cancelled, .badge-failed, .badge-refunded { background: #f7dcd6; color: #7e2b1b; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 6px 14px; border: 1px solid #d8d0b7; background: #fff; text-decoration: none; color: #162018; font-size: 13px; }
        .filter-btn.active, .filter-btn:hover { background: var(--forest-900); color: #f5f0df; border-color: var(--forest-900); }
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
        <a href="admin_listings.php">Listings</a>
        <a href="admin_rentals.php">Rentals</a>
        <a href="admin_sales.php" class="active">Sales</a>
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
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Financial Supervision</span>
            <h1>Manage Product Orders & Transactions</h1>
        </div>
        <a href="admin_dashboard.php" class="btn btn-light">? Back to Dashboard</a>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:20px; font-weight:500;">
            ? <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="admin_sales.php" class="filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Orders</a>
        <a href="admin_sales.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="admin_sales.php?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="admin_sales.php?status=processing" class="filter-btn <?= $statusFilter === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="admin_sales.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="admin_sales.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="table-panel">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Payment Method</th>
                    <th>Payment Status</th>
                    <th>Order Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="9" style="text-align:center; padding:30px; color:#6b6a59;">No customer orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong>#<?= (int)$o['order_id'] ?></strong></td>
                            <td><?= htmlspecialchars($o['buyer_name']) ?><br><small style="color:#777;"><?= htmlspecialchars($o['buyer_phone'] ?: 'No phone') ?></small></td>
                            <td><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></td>
                            <td><strong><?= (int)$o['total_items'] ?></strong> item(s)</td>
                            <td><strong style="color:var(--forest-900);">?<?= number_format((float)$o['total_amount'], 2) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars(strtoupper($o['payment_method'] ?? 'CASH')) ?></strong>
                                <?php if (!empty($o['transaction_ref'])): ?>
                                    <br><small style="color:#777;">Ref: <?= htmlspecialchars($o['transaction_ref']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($o['payment_proof'])): ?>
                                    <br><a href="<?= htmlspecialchars($o['payment_proof']) ?>" target="_blank" style="font-size:11px; color:#768047; text-decoration:underline;">📄 Receipt</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="admin_sales.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                                    <select name="payment_status" onchange="this.form.submit()" style="padding:3px 6px; font-size:12px;">
                                        <option value="pending" <?= ($o['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="paid" <?= ($o['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="failed" <?= ($o['payment_status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                                        <option value="refunded" <?= ($o['payment_status'] ?? '') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <form action="admin_sales.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                                    <select name="order_status" onchange="this.form.submit()" style="padding:3px 6px; font-size:12px;">
                                        <option value="pending" <?= $o['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $o['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="processing" <?= $o['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="completed" <?= $o['order_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $o['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <a href="order_details.php?id=<?= (int)$o['order_id'] ?>" target="_blank" class="btn btn-light" style="padding:4px 8px; font-size:11px;">View Receipt</a>
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
        <span>? 2026 AgriMart Administration. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
