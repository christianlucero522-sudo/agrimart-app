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
$role = $_SESSION['role'] ?? 'user';

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        o.order_id,
        o.total_amount,
        o.shipping_address,
        o.order_status,
        o.created_at,
        p.payment_method,
        p.payment_status,
        COUNT(oi.order_item_id) AS total_items
    FROM orders o
    LEFT JOIN payments p ON o.order_id = p.order_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.buyer_id = ?
";

if (!empty($statusFilter)) {
    $sql .= " AND o.order_status = ?";
}

$sql .= " GROUP BY o.order_id ORDER BY o.order_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($statusFilter)) {
    $stmt->bind_param('is', $userId, $statusFilter);
} else {
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1200px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; flex-wrap: wrap; gap: 20px; }
        .page-header h1 { font-family: Georgia, serif; font-size: clamp(32px, 4vw, 48px); margin: 0; color: #122017; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; }
        .filter-btn { padding: 8px 16px; border: 1px solid #d8d0b7; background: #fff; text-decoration: none; color: #162018; font-size: 13px; font-weight: 500; border-radius: 3px; }
        .filter-btn.active, .filter-btn:hover { background: var(--forest-900); color: #f5f0df; border-color: var(--forest-900); }
        .order-card { background: #fff; border: 1px solid #ded6b9; padding: 24px; margin-bottom: 20px; display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: center; border-radius: 4px; }
        .order-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 12px; font-size: 13px; color: #6b6a59; }
        .order-meta strong { color: #122017; }
        .badge { display: inline-block; padding: 5px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-radius: 3px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-processing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .order-total { font-size: 22px; font-weight: 700; color: var(--forest-900); margin-top: 8px; }
        @media(max-width:768px) { .order-card { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="site-header" id="siteHeader">
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
        <a href="logout.php" class="btn btn-light" style="background:#fff; color:#122017; font-weight:600; border:none; padding:8px 16px; border-radius:3px;">Logout</a>
    </div>
</div>
</header>

<main class="page-wrap">
    <div class="page-header">
        <div>
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px; font-weight:700;">Marketplace Activity</span>
            <h1>My Placed Orders</h1>
        </div>
        <a href="products.php" class="btn btn-solid">Continue Shopping</a>
    </div>

    <div class="filter-bar">
        <a href="orders.php" class="filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Orders</a>
        <a href="orders.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="orders.php?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="orders.php?status=processing" class="filter-btn <?= $statusFilter === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="orders.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="orders.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <?php if (empty($orders)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:50px; text-align:center; border-radius:4px;">
            <h3 style="font-family:Georgia,serif; font-size:24px; margin-bottom:10px;">No orders found</h3>
            <p style="color:#6b6a59; margin-bottom:25px;">You haven't placed any orders matching this status.</p>
            <a href="products.php" class="btn btn-solid">Browse Products</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $o): ?>
            <div class="order-card">
                <div>
                    <div class="order-meta">
                        <span>Date: <strong><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></strong></span>
                        <span>Payment: <strong><?= htmlspecialchars(strtoupper($o['payment_method'] ?? 'N/A')) ?> (<?= htmlspecialchars(ucfirst($o['payment_status'] ?? 'pending')) ?>)</strong></span>
                        <span>Items: <strong><?= (int)$o['total_items'] ?> item(s)</strong></span>
                    </div>
                    <span class="badge badge-<?= htmlspecialchars($o['order_status']) ?>"><?= htmlspecialchars(ucfirst($o['order_status'])) ?></span>
                    <div class="order-total">₱<?= number_format((float)$o['total_amount'], 2) ?></div>
                </div>
                <div>
                    <a href="order_details.php?id=<?= (int)$o['order_id'] ?>" class="btn btn-dark">View Details →</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>© 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
