<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';
$fullName = $_SESSION['full_name'] ?? 'User';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'User';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

// Handle Order Cancellation (if buyer cancels pending order)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $cancelSql = "SELECT order_id, order_status FROM orders WHERE order_id = ? AND buyer_id = ? AND order_status = 'pending' LIMIT 1";
    $cStmt = $conn->prepare($cancelSql);
    $cStmt->bind_param('ii', $orderId, $userId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    
    if ($cRes->num_rows === 1) {
        $conn->begin_transaction();
        try {
            // Restore inventory
            $itemSql = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
            $iStmt = $conn->prepare($itemSql);
            $iStmt->bind_param('i', $orderId);
            $iStmt->execute();
            $itemsRes = $iStmt->get_result();
            while ($it = $itemsRes->fetch_assoc()) {
                $restSql = "UPDATE products SET quantity = quantity + ?, status = 'active' WHERE product_id = ?";
                $rStmt = $conn->prepare($restSql);
                $rStmt->bind_param('ii', $it['quantity'], $it['product_id']);
                $rStmt->execute();
                $rStmt->close();
            }
            $iStmt->close();

            // Update order status
            $upSql = "UPDATE orders SET order_status = 'cancelled' WHERE order_id = ?";
            $uStmt = $conn->prepare($upSql);
            $uStmt->bind_param('i', $orderId);
            $uStmt->execute();
            $uStmt->close();

            // Update payment status
            $upPay = "UPDATE payments SET payment_status = 'refunded' WHERE order_id = ?";
            $pStmt = $conn->prepare($upPay);
            $pStmt->bind_param('i', $orderId);
            $pStmt->execute();
            $pStmt->close();

            $conn->commit();
            header("Location: order_details.php?id=$orderId&cancelled=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Unable to cancel order.";
        }
    }
    $cStmt->close();
}

// Fetch Order
$orderSql = "
    SELECT 
        o.order_id,
        o.buyer_id,
        o.total_amount,
        o.shipping_address,
        o.order_status,
        o.created_at,
        o.updated_at,
        u.full_name AS buyer_name,
        u.email AS buyer_email,
        u.phone AS buyer_phone,
        p.payment_id,
        p.payment_method,
        p.buyer_bank_name,
        p.buyer_account_name,
        p.buyer_account_number,
        p.amount AS payment_amount,
        p.payment_status,
        p.transaction_ref,
        p.payment_proof,
        p.paid_at
    FROM orders o
    INNER JOIN users u ON o.buyer_id = u.user_id
    LEFT JOIN payments p ON o.order_id = p.order_id
    WHERE o.order_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($orderSql);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$orderResult = $stmt->get_result();
$order = $orderResult->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Security: User must be buyer, or admin, or one of the sellers
if ($userRole !== 'admin' && (int)$order['buyer_id'] !== $userId) {
    // Check if seller in order items
    $checkSeller = "SELECT order_item_id FROM order_items WHERE order_id = ? AND seller_id = ? LIMIT 1";
    $csStmt = $conn->prepare($checkSeller);
    $csStmt->bind_param('ii', $orderId, $userId);
    $csStmt->execute();
    if ($csStmt->get_result()->num_rows === 0) {
        header('Location: orders.php');
        exit;
    }
    $csStmt->close();
}

// Fetch Order Items
$itemsSql = "
    SELECT 
        oi.order_item_id,
        oi.product_id,
        oi.quantity,
        oi.price,
        oi.subtotal,
        p.product_name,
        p.unit,
        p.image_url,
        u.full_name AS seller_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN users u ON oi.seller_id = u.user_id
    WHERE oi.order_id = ?
";
$itemStmt = $conn->prepare($itemsSql);
$itemStmt->bind_param('i', $orderId);
$itemStmt->execute();
$itemsResult = $itemStmt->get_result();
$orderItems = [];
while ($row = $itemsResult->fetch_assoc()) {
    $orderItems[] = $row;
}
$itemStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= (int)$order['order_id'] ?> — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1100px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--forest-900); font-weight: 600; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        .order-layout { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(320px, 1fr); gap: 40px; }
        .panel { background: #fff; border: 1px solid #ded6b9; padding: 28px; margin-bottom: 25px; }
        .panel h2 { font-family: Georgia, serif; font-size: 22px; margin: 0 0 18px; color: #122017; border-bottom: 1px solid #efe8d3; padding-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; gap: 15px; margin-bottom: 12px; font-size: 14px; }
        .info-row span { color: #6b6a59; }
        .info-row strong { color: #122017; text-align: right; }
        .item-row { display: grid; grid-template-columns: 60px 1fr auto; gap: 15px; padding: 14px 0; border-bottom: 1px solid #eee8d5; align-items: center; }
        .item-img { width: 60px; height: 60px; object-fit: cover; background: var(--forest-950); }
        .badge { display: inline-block; padding: 5px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-processing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .timeline { display: flex; justify-content: space-between; margin-top: 20px; position: relative; }
        .timeline-step { text-align: center; font-size: 12px; font-weight: 600; color: #888; flex: 1; position: relative; }
        .timeline-step.active { color: var(--forest-900); font-weight: 700; }
        .timeline-step::before { content: ''; display: block; width: 14px; height: 14px; background: #ddd; border-radius: 50%; margin: 0 auto 8px; }
        .timeline-step.active::before { background: #415535; border: 2px solid #b29438; }
        @media(max-width:800px) { .order-layout { grid-template-columns: 1fr; } }
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
    <a href="orders.php" class="back-link">← Back to My Orders</a>

    <?php if (isset($_GET['placed'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:16px 20px; margin-bottom:25px; font-weight:500;">
            ✓ Success! Your order #<?= (int)$order['order_id'] ?> has been placed successfully. The seller has been notified.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cancelled'])): ?>
        <div style="background:#f7dcd6; border:1px solid #efb7aa; color:#7e2b1b; padding:16px 20px; margin-bottom:25px; font-weight:500;">
            ✓ Order #<?= (int)$order['order_id'] ?> has been cancelled.
        </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:30px; flex-wrap:wrap; gap:15px;">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Order Summary</span>
            <h1 style="font-family:Georgia,serif; font-size:36px; margin:5px 0 0; color:#122017;">Order #<?= (int)$order['order_id'] ?></h1>
        </div>
        <div>
            <span class="badge badge-<?= htmlspecialchars($order['order_status']) ?>" style="font-size:14px; padding:8px 16px;">
                Status: <?= htmlspecialchars(ucfirst($order['order_status'])) ?>
            </span>
        </div>
    </div>

    <!-- Order Timeline -->
    <div class="panel">
        <h2>Fulfillment Progress</h2>
        <div class="timeline">
            <div class="timeline-step <?= in_array($order['order_status'], ['pending', 'confirmed', 'processing', 'completed']) ? 'active' : '' ?>">Pending</div>
            <div class="timeline-step <?= in_array($order['order_status'], ['confirmed', 'processing', 'completed']) ? 'active' : '' ?>">Confirmed</div>
            <div class="timeline-step <?= in_array($order['order_status'], ['processing', 'completed']) ? 'active' : '' ?>">Processing / Shipped</div>
            <div class="timeline-step <?= $order['order_status'] === 'completed' ? 'active' : '' ?>">Completed</div>
        </div>
    </div>

    <div class="order-layout">
        <!-- Left: Items -->
        <div>
            <div class="panel">
                <h2>Items Ordered (<?= count($orderItems) ?>)</h2>
                <?php foreach ($orderItems as $item): ?>
                    <div class="item-row">
                        <img src="<?= htmlspecialchars($item['image_url'] ?: 'images/placeholder-product.svg') ?>" class="item-img" alt="">
                        <div>
                            <div style="font-weight:600; color:#122017; font-size:15px;"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div style="color:#6b6a59; font-size:12px; margin-top:3px;">
                                Seller: <strong><?= htmlspecialchars($item['seller_name'] ?? 'AgriMart Farmer') ?></strong> |
                                Qty: <strong><?= (int)$item['quantity'] ?> <?= htmlspecialchars($item['unit'] ?? '') ?></strong> × ₱<?= number_format((float)$item['price'], 2) ?>
                            </div>
                        </div>
                        <div style="font-weight:700; color:var(--forest-900); font-size:16px;">
                            ₱<?= number_format((float)$item['subtotal'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:15px; border-top:2px solid #122017; font-size:20px; font-weight:700;">
                    <span>Grand Total</span>
                    <span style="color:var(--forest-900);">₱<?= number_format((float)$order['total_amount'], 2) ?></span>
                </div>
            </div>

            <?php if ($order['order_status'] === 'completed'): ?>
                <div class="panel" style="background:#f9f7f0;">
                    <h2>Share Your Feedback</h2>
                    <p style="color:#6b6a59; font-size:14px; margin-bottom:15px;">How was your purchase? Leave a review to help other farmers in the marketplace.</p>
                    <a href="add_review.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-solid">★ Write a Product Review</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Shipping & Payment -->
        <div>
            <div class="panel">
                <h2>Delivery Information</h2>
                <div class="info-row">
                    <span>Recipient</span>
                    <strong><?= htmlspecialchars($order['buyer_name']) ?></strong>
                </div>
                <div class="info-row">
                    <span>Contact Phone</span>
                    <strong><?= htmlspecialchars($order['buyer_phone'] ?: 'None provided') ?></strong>
                </div>
                <div class="info-row">
                    <span>Email</span>
                    <strong><?= htmlspecialchars($order['buyer_email']) ?></strong>
                </div>
                <div style="margin-top:15px; padding-top:12px; border-top:1px solid #eee8d5;">
                    <span style="display:block; color:#6b6a59; font-size:12px; text-transform:uppercase; margin-bottom:6px; font-weight:600;">Shipping Address</span>
                    <div style="color:#122017; line-height:1.5; font-size:14px; background:#f9f7f0; padding:12px; border:1px solid #ded6b9;">
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h2>Payment Details</h2>
                <div class="info-row">
                    <span>Method</span>
                    <strong><?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'CASH')) ?></strong>
                </div>
                <?php if (!empty($order['buyer_bank_name'])): ?>
                    <div class="info-row">
                        <span>Buyer Account</span>
                        <strong><?= htmlspecialchars($order['buyer_bank_name']) ?> — <?= htmlspecialchars($order['buyer_account_name'] ?? '') ?> (<?= htmlspecialchars($order['buyer_account_number'] ?? '') ?>)</strong>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span>Payment Status</span>
                    <strong><?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'pending')) ?></strong>
                </div>
                <?php if (!empty($order['transaction_ref'])): ?>
                    <div class="info-row">
                        <span>Ref Number</span>
                        <strong><?= htmlspecialchars($order['transaction_ref']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['payment_proof'])): ?>
                    <div class="info-row">
                        <span>Receipt Proof</span>
                        <strong>
                            <a href="<?= htmlspecialchars($order['payment_proof']) ?>" target="_blank" style="color:#768047; text-decoration:underline;">
                                📄 View Receipt
                            </a>
                        </strong>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span>Order Date</span>
                    <strong><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></strong>
                </div>
            </div>

            <?php if ($order['order_status'] === 'pending' && (int)$order['buyer_id'] === $userId): ?>
                <form action="order_details.php?id=<?= $orderId ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? Item quantities will be returned to stock.');">
                    <input type="hidden" name="action" value="cancel_order">
                    <button type="submit" class="btn btn-light" style="width:100%; border-color:#efb7aa; color:#a54129; background:#fae6df;">
                        Cancel This Order
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
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
