<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Seller';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Seller';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowedStatuses = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

    if ($action === 'update_status' && $orderId > 0 && in_array($newStatus, $allowedStatuses)) {
        // Verify this order belongs to this seller
        $chk = $conn->prepare("SELECT order_item_id FROM order_items WHERE order_id = ? AND seller_id = ? LIMIT 1");
        $chk->bind_param('ii', $orderId, $userId);
        $chk->execute();
        $isMyOrder = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($isMyOrder) {
            $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
            $stmt->bind_param('si', $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();

            // Notify buyer
            $bStmt = $conn->prepare("SELECT buyer_id FROM orders WHERE order_id = ? LIMIT 1");
            $bStmt->bind_param('i', $orderId);
            $bStmt->execute();
            $buyerId = (int)($bStmt->get_result()->fetch_assoc()['buyer_id'] ?? 0);
            $bStmt->close();

            $notifTitle = "Order #$orderId Status: " . ucfirst($newStatus);
            $notifMsg = "Your order #$orderId has been marked as '$newStatus' by the farmer/seller.";
            $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'order', ?)");
            $nStmt->bind_param('issi', $buyerId, $notifTitle, $notifMsg, $orderId);
            $nStmt->execute();
            $nStmt->close();

            // Send Gmail notification to buyer
            require_once 'mailer.php';
            @sendOrderStatusEmail($orderId, $newStatus, $conn);

            $message = "Order #$orderId has been updated to " . ucfirst($newStatus) . ".";
        }
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
        p.payment_method,
        p.buyer_bank_name,
        p.buyer_account_name,
        p.buyer_account_number,
        p.payment_status,
        p.transaction_ref,
        p.payment_proof,
        SUM(oi.quantity) AS seller_items_count,
        SUM(oi.subtotal) AS seller_subtotal
    FROM orders o
    INNER JOIN order_items oi ON o.order_id = oi.order_id
    INNER JOIN users u ON o.buyer_id = u.user_id
    LEFT JOIN payments p ON o.order_id = p.order_id
    WHERE oi.seller_id = ?
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

$sales = [];
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Sales & Orders — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1200px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; border-bottom: 1px solid #d8d0b7; padding-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-family: Georgia, serif; font-size: 32px; margin: 5px 0 0; color: #122017; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; border: 1px solid #d8d0b7; background: #fff; color: #596054; text-decoration: none; font-size: 13px; font-weight: 500; }
        .filter-btn.active, .filter-btn:hover { background: #768047; color: #fff; border-color: #768047; }
        .sale-card { background: #fff; border: 1px solid #ded6b9; padding: 25px; margin-bottom: 20px; }
        .sale-card-grid { display: grid; grid-template-columns: 1fr auto; gap: 25px; align-items: center; }
        .sale-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 12px; font-size: 13px; color: #6b6a59; }
        .sale-meta strong { color: #122017; }
        .badge { display: inline-block; padding: 5px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-processing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .sale-total { font-size: 22px; font-weight: 700; color: var(--forest-900); margin-top: 8px; }
        .action-form { display: flex; gap: 8px; align-items: center; margin-top: 15px; }
        .action-select { padding: 8px 12px; border: 1px solid #d8d0b7; background: #f5f0df; font-size: 13px; outline: none; }
        @media(max-width:768px) { .sale-card-grid { grid-template-columns: 1fr; } }
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
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Seller Operations</span>
            <h1>Customer Orders (Sales)</h1>
        </div>
        <div>
            <a href="my_products.php" class="btn btn-light" style="margin-right:8px;">My Products</a>
            <a href="add_product.php" class="btn btn-solid">+ Add Product</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="seller_orders.php" class="filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Sales</a>
        <a href="seller_orders.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="seller_orders.php?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="seller_orders.php?status=processing" class="filter-btn <?= $statusFilter === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="seller_orders.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="seller_orders.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <?php if (empty($sales)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:50px; text-align:center;">
            <h3 style="font-family:Georgia,serif; font-size:24px; margin-bottom:10px;">No sales orders yet</h3>
            <p style="color:#6b6a59; margin-bottom:25px;">When buyers place orders on your seeds or products, they will appear here for processing.</p>
            <a href="my_products.php" class="btn btn-solid">Check My Product Listings</a>
        </div>
    <?php else: ?>
        <?php foreach ($sales as $s): ?>
            <div class="sale-card">
                <div class="sale-card-grid">
                    <div>
                        <div class="sale-meta">
                            <span>Order <strong>#<?= (int)$s['order_id'] ?></strong></span>
                            <span>Customer: <strong><?= htmlspecialchars($s['buyer_name']) ?> (<?= htmlspecialchars($s['buyer_phone'] ?: 'No phone') ?>)</strong></span>
                            <span>Placed: <strong><?= date('M d, Y h:i A', strtotime($s['created_at'])) ?></strong></span>
                            <span>Payment: <strong><?= htmlspecialchars(strtoupper($s['payment_method'] ?? 'CASH')) ?> (<?= htmlspecialchars(ucfirst($s['payment_status'] ?? 'pending')) ?>)</strong></span>
                        </div>
                        <?php if (!empty($s['buyer_bank_name'])): ?>
                            <div style="background:#f9f7f0; border:1px solid #d8d0b7; padding:10px 14px; margin-bottom:12px; font-size:12.5px; color:#444;">
                                <strong>Buyer Direct Payment Account:</strong> <?= htmlspecialchars($s['buyer_bank_name']) ?> — <?= htmlspecialchars($s['buyer_account_name'] ?? '') ?> (<?= htmlspecialchars($s['buyer_account_number'] ?? '') ?>)
                                <?php if (!empty($s['transaction_ref'])): ?>
                                    | Ref: <strong><?= htmlspecialchars($s['transaction_ref']) ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($s['payment_proof'])): ?>
                                    | <a href="<?= htmlspecialchars($s['payment_proof']) ?>" target="_blank" style="color:#768047; font-weight:700; text-decoration:underline;">📄 View Receipt Proof</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <span class="badge badge-<?= htmlspecialchars($s['order_status']) ?>"><?= htmlspecialchars(ucfirst($s['order_status'])) ?></span>
                        <div class="sale-total">
                            Your Revenue: ₱<?= number_format((float)$s['seller_subtotal'], 2) ?> 
                            <small style="font-size:13px; font-weight:400; color:#6b6a59;">(<?= (int)$s['seller_items_count'] ?> items in this order)</small>
                        </div>
                        <div style="font-size:13px; color:#596054; margin-top:8px;">
                            <strong>Delivery Address:</strong> <?= htmlspecialchars($s['shipping_address']) ?>
                        </div>
                    </div>
                    <div>
                        <a href="order_details.php?id=<?= (int)$s['order_id'] ?>" class="btn btn-dark" style="margin-bottom:10px; display:inline-block;">View Details →</a>
                        
                        <?php if ($s['order_status'] !== 'completed' && $s['order_status'] !== 'cancelled'): ?>
                            <form action="seller_orders.php" method="POST" class="action-form">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= (int)$s['order_id'] ?>">
                                <select name="new_status" class="action-select">
                                    <option value="confirmed" <?= $s['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirm Order</option>
                                    <option value="processing" <?= $s['order_status'] === 'processing' ? 'selected' : '' ?>>Processing / Ship</option>
                                    <option value="completed">Mark Completed</option>
                                    <option value="cancelled">Cancel Order</option>
                                </select>
                                <button type="submit" class="btn btn-solid" style="padding:8px 14px; font-size:12px;">Update</button>
                            </form>
                        <?php endif; ?>
                    </div>
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
