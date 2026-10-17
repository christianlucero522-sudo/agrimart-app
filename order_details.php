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

$error = '';
$message = '';

// Handle Order Actions (Cancellation or Status Updates by Seller / Buyer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);

    // Check Order and permissions
    $checkOrder = $conn->prepare("SELECT buyer_id, order_status FROM orders WHERE order_id = ? LIMIT 1");
    $checkOrder->bind_param('i', $orderId);
    $checkOrder->execute();
    $ordRow = $checkOrder->get_result()->fetch_assoc();
    $checkOrder->close();

    if ($ordRow) {
        $isBuyer = ((int)$ordRow['buyer_id'] === $userId);

        $chkS = $conn->prepare("SELECT order_item_id FROM order_items WHERE order_id = ? AND seller_id = ? LIMIT 1");
        $chkS->bind_param('ii', $orderId, $userId);
        $chkS->execute();
        $isSeller = ($chkS->get_result()->num_rows > 0 || $userRole === 'admin');
        $chkS->close();

        if ($action === 'cancel_order' && ($isBuyer || $isSeller) && !in_array($ordRow['order_status'], ['completed', 'cancelled'])) {
            $cancellationReason = trim($_POST['cancellation_reason'] ?? '');
            $cancelledBy = $isSeller ? 'seller' : 'buyer';

            if ($isSeller && $cancellationReason === '') {
                $error = "Cancellation failed: You must provide a valid cancellation reason.";
            } else {
                if ($cancellationReason === '') {
                    $cancellationReason = "Cancelled by customer before processing";
                }

                $conn->begin_transaction();
                try {
                    // 1. Restore product inventory for all items in this order
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

                    // 2. Update order status and record reason (total_amount remains untouched)
                    $upSql = "UPDATE orders SET order_status = 'cancelled', cancellation_reason = ?, cancelled_by = ? WHERE order_id = ?";
                    $uStmt = $conn->prepare($upSql);
                    $uStmt->bind_param('ssi', $cancellationReason, $cancelledBy, $orderId);
                    $uStmt->execute();
                    $uStmt->close();

                    // 3. Mark payment as refunded
                    $conn->query("UPDATE payments SET payment_status = 'refunded' WHERE order_id = $orderId");

                    // 4. Send Notifications
                    if ($isSeller) {
                        $notifTitle = "Order #$orderId Cancelled by Seller";
                        $notifMsg = "Your order #$orderId has been cancelled by the seller. Reason: " . $cancellationReason;
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'order', ?)");
                        $nStmt->bind_param('issi', $ordRow['buyer_id'], $notifTitle, $notifMsg, $orderId);
                        $nStmt->execute();
                        $nStmt->close();
                    } else {
                        $sList = $conn->query("SELECT DISTINCT seller_id FROM order_items WHERE order_id = $orderId");
                        while ($sRow = $sList->fetch_assoc()) {
                            $sId = (int)$sRow['seller_id'];
                            $notifTitle = "Order #$orderId Cancelled by Customer";
                            $notifMsg = "Order #$orderId was cancelled by customer. Reason: " . $cancellationReason;
                            $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'order', ?)");
                            $nStmt->bind_param('issi', $sId, $notifTitle, $notifMsg, $orderId);
                            $nStmt->execute();
                            $nStmt->close();
                        }
                    }

                    $conn->commit();

                    require_once 'mailer.php';
                    @sendOrderStatusEmail($orderId, 'cancelled', $conn, $cancellationReason);

                    header("Location: order_details.php?id=$orderId&cancelled=1");
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Unable to cancel order: " . $e->getMessage();
                }
            }
        } elseif ($action === 'update_status' && $isSeller) {
            $newStatus = trim($_POST['new_status'] ?? '');
            $allowed = ['confirmed', 'processing', 'completed'];
            if (in_array($newStatus, $allowed)) {
                $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $stmt->bind_param('si', $newStatus, $orderId);
                $stmt->execute();
                $stmt->close();

                if ($newStatus === 'completed') {
                    $conn->query("UPDATE payments SET payment_status = 'paid' WHERE order_id = $orderId");
                }

                $notifTitle = "Order #$orderId Status: " . ucfirst($newStatus);
                $notifMsg = "Your order #$orderId has been marked as '$newStatus' by the farmer/seller.";
                $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'order', ?)");
                $nStmt->bind_param('issi', $ordRow['buyer_id'], $notifTitle, $notifMsg, $orderId);
                $nStmt->execute();
                $nStmt->close();

                require_once 'mailer.php';
                @sendOrderStatusEmail($orderId, $newStatus, $conn);

                header("Location: order_details.php?id=$orderId&updated=" . urlencode($newStatus));
                exit;
            }
        }
    }
}

// Fetch Order
$orderSql = "
    SELECT 
        o.order_id,
        o.buyer_id,
        o.total_amount,
        o.shipping_address,
        o.order_status,
        o.cancellation_reason,
        o.cancelled_by,
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
$isViewerBuyer = ((int)$order['buyer_id'] === $userId);
$isViewerSeller = false;
$checkSeller = "SELECT order_item_id FROM order_items WHERE order_id = ? AND seller_id = ? LIMIT 1";
$csStmt = $conn->prepare($checkSeller);
$csStmt->bind_param('ii', $orderId, $userId);
$csStmt->execute();
$isViewerSeller = ($csStmt->get_result()->num_rows > 0 || $userRole === 'admin');
$csStmt->close();

if (!$isViewerBuyer && !$isViewerSeller && $userRole !== 'admin') {
    header('Location: orders.php');
    exit;
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

    <?php if (!empty($error)): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#7e2b1b; padding:16px 20px; margin-bottom:25px; font-weight:600; border-radius:4px;">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:16px 20px; margin-bottom:25px; font-weight:500; border-radius:4px;">
            ✓ Order #<?= (int)$order['order_id'] ?> status has been updated to <strong><?= htmlspecialchars(ucfirst($_GET['updated'])) ?></strong>.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['placed'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:16px 20px; margin-bottom:25px; font-weight:500; border-radius:4px;">
            ✓ Success! Your order #<?= (int)$order['order_id'] ?> has been placed successfully. The seller has been notified.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cancelled'])): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#7e2b1b; padding:16px 20px; margin-bottom:25px; font-weight:500; border-radius:4px;">
            ✓ Order #<?= (int)$order['order_id'] ?> has been cancelled.
        </div>
    <?php endif; ?>

    <?php if ($order['order_status'] === 'cancelled'): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; padding:20px 24px; margin-bottom:25px; border-radius:4px; color:#7e2b1b;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px; flex-wrap:wrap;">
                <span style="font-size:24px;">⚠️</span>
                <h3 style="margin:0; font-family:Georgia,serif; font-size:20px; color:#7e2b1b;">
                    Order Cancelled by <?= ucfirst($order['cancelled_by'] ?? 'Seller') ?>
                </h3>
                <span style="font-size:11px; background:#efb7aa; color:#5c1d10; padding:3px 8px; border-radius:3px; text-transform:uppercase; font-weight:700;">
                    Cancelled
                </span>
            </div>
            <div style="font-size:14px; line-height:1.5; margin-top:8px;">
                <strong>Reason for Cancellation:</strong>
                <div style="background:#fff; border:1px solid #efb7aa; padding:12px 16px; border-radius:4px; margin-top:6px; color:#122017; font-size:13.5px;">
                    <?= nl2br(htmlspecialchars($order['cancellation_reason'] ?: 'No specific explanation was provided.')) ?>
                </div>
            </div>
            <div style="font-size:12px; color:#9c3822; margin-top:12px; border-top:1px dashed #efb7aa; padding-top:8px;">
                ✓ Product quantities have been returned to active seller inventory. Original order total (₱<?= number_format((float)$order['total_amount'], 2) ?>) is preserved as historical record.
            </div>
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

            <?php if ($isViewerSeller && !in_array($order['order_status'], ['completed', 'cancelled'])): ?>
                <div class="panel" style="border:1.5px solid #768047; background:#fcfbf7;">
                    <h2 style="font-size:18px; margin-bottom:12px; color:#122017;">Farmer Order Actions</h2>
                    <p style="font-size:12.5px; color:#6b6a59; margin-bottom:15px; line-height:1.4;">
                        As the product owner, manage confirmation, dispatch, or cancellation:
                    </p>

                    <?php if ($order['order_status'] === 'pending'): ?>
                        <form action="order_details.php?id=<?= $orderId ?>" method="POST" style="margin-bottom:10px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="new_status" value="confirmed">
                            <button type="submit" class="btn btn-solid" style="width:100%; text-align:center; padding:10px; font-weight:600;">
                                ✓ Confirm Order
                            </button>
                        </form>
                    <?php elseif ($order['order_status'] === 'confirmed'): ?>
                        <form action="order_details.php?id=<?= $orderId ?>" method="POST" style="margin-bottom:10px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="new_status" value="processing">
                            <button type="submit" class="btn btn-solid" style="width:100%; text-align:center; padding:10px; font-weight:600;">
                                🚚 Ship / Process Order
                            </button>
                        </form>
                    <?php elseif ($order['order_status'] === 'processing'): ?>
                        <form action="order_details.php?id=<?= $orderId ?>" method="POST" style="margin-bottom:10px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="new_status" value="completed">
                            <button type="submit" class="btn btn-solid" style="width:100%; text-align:center; padding:10px; font-weight:600; background:#2e7d32; border-color:#2e7d32;">
                                ★ Mark as Completed
                            </button>
                        </form>
                    <?php endif; ?>

                    <button type="button" onclick="openDetailsCancelModal('seller')" class="btn btn-light" style="width:100%; text-align:center; padding:9px; border-color:#efb7aa; color:#a54129; background:#fae6df; font-weight:600; font-size:13px;">
                        ✕ Cancel Order (Provide Reason)
                    </button>
                </div>
            <?php elseif ($isViewerBuyer && $order['order_status'] === 'pending'): ?>
                <button type="button" onclick="openDetailsCancelModal('buyer')" class="btn btn-light" style="width:100%; border-color:#efb7aa; color:#a54129; background:#fae6df; padding:10px; font-weight:600;">
                    Cancel This Order
                </button>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Details Page Cancellation Reason Modal -->
<div id="detailsCancelModal" style="display:none; position:fixed; inset:0; background:rgba(18,32,23,0.7); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; max-width:520px; width:100%; border-radius:6px; box-shadow:0 15px 35px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #ded6b9;">
        <div style="background:#fae6df; padding:18px 24px; border-bottom:1px solid #efb7aa; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-family:Georgia,serif; font-size:18px; color:#7e2b1b;">
                ⚠️ Cancel Order #<?= (int)$order['order_id'] ?>
            </h3>
            <button type="button" onclick="closeDetailsCancelModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#7e2b1b;">&times;</button>
        </div>
        <form id="detailsCancelForm" action="order_details.php?id=<?= $orderId ?>" method="POST" style="padding:22px 24px;">
            <input type="hidden" name="action" value="cancel_order">
            <input type="hidden" name="cancellation_reason" id="hiddenReasonInput" value="">
            
            <p id="detailsModalIntro" style="margin:0 0 16px; font-size:13.5px; color:#555; line-height:1.5;">
                Please provide a cancellation reason. Ordered product quantities will be returned to the seller's active stock.
            </p>
            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#122017; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">
                    Select Reason <span style="color:#c53030;">*</span>
                </label>
                <select id="detailsReasonPreset" onchange="onDetailsPresetChange(this)" style="width:100%; padding:10px 12px; border:1px solid #c2bda8; background:#fbfaf6; font-size:13.5px; border-radius:4px;">
                    <option value="">-- Choose a Reason --</option>
                    <option value="Out of Stock / Damaged Harvest">Out of Stock / Damaged Harvest</option>
                    <option value="Unable to Deliver to Buyer Location">Unable to Deliver to Buyer Location</option>
                    <option value="Buyer Requested Cancellation">Buyer Requested Cancellation</option>
                    <option value="Direct Payment Proof Not Received / Invalid">Direct Payment Proof Not Received / Invalid</option>
                    <option value="Pricing / Listing Quantity Discrepancy">Pricing / Listing Quantity Discrepancy</option>
                    <option value="Other">Other (Specify below)</option>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#122017; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">
                    Detailed Explanation <span style="color:#c53030;">*</span>
                </label>
                <textarea id="detailsReasonText" rows="3" placeholder="Provide full details for cancellation..." style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #c2bda8; font-family:inherit; font-size:13px; border-radius:4px; resize:vertical;"></textarea>
                <div id="detailsModalError" style="color:#c53030; font-size:12px; display:none; margin-top:5px; font-weight:600;"></div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeDetailsCancelModal()" class="btn btn-light" style="padding:9px 18px; font-size:13px;">Keep Order</button>
                <button type="button" onclick="submitDetailsCancel()" class="btn btn-solid" style="padding:9px 20px; font-size:13px; background:#a54129; border-color:#8e341f; color:#fff;">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<script>
let cancelRole = 'seller';

function openDetailsCancelModal(role) {
    cancelRole = role;
    const modal = document.getElementById('detailsCancelModal');
    const intro = document.getElementById('detailsModalIntro');
    const preset = document.getElementById('detailsReasonPreset');
    const text = document.getElementById('detailsReasonText');
    const err = document.getElementById('detailsModalError');

    preset.value = '';
    text.value = '';
    err.style.display = 'none';

    if (role === 'buyer') {
        intro.textContent = 'Please tell us why you wish to cancel your order. The product will be returned to the farmer’s stock and your payment refunded.';
        preset.innerHTML = `
            <option value="">-- Choose a Reason --</option>
            <option value="Changed my mind / No longer needed">Changed my mind / No longer needed</option>
            <option value="Order placed by mistake">Order placed by mistake</option>
            <option value="Found alternative product">Found alternative product</option>
            <option value="Incorrect delivery address or details">Incorrect delivery address or details</option>
            <option value="Other">Other reason</option>
        `;
    } else {
        intro.textContent = 'As the seller, you must state a clear reason for cancellation. Product stock will be restored and the buyer will be notified.';
        preset.innerHTML = `
            <option value="">-- Choose a Reason --</option>
            <option value="Out of Stock / Damaged Harvest">Out of Stock / Damaged Harvest</option>
            <option value="Unable to Deliver to Buyer Location">Unable to Deliver to Buyer Location</option>
            <option value="Buyer Requested Cancellation">Buyer Requested Cancellation</option>
            <option value="Direct Payment Proof Not Received / Invalid">Direct Payment Proof Not Received / Invalid</option>
            <option value="Pricing / Listing Quantity Discrepancy">Pricing / Listing Quantity Discrepancy</option>
            <option value="Other">Other (Specify below)</option>
        `;
    }

    modal.style.display = 'flex';
}

function onDetailsPresetChange(sel) {
    const text = document.getElementById('detailsReasonText');
    if (sel.value && sel.value !== 'Other') {
        if (!text.value || text.value.trim() === '') {
            text.value = sel.value;
        }
    }
}

function closeDetailsCancelModal() {
    document.getElementById('detailsCancelModal').style.display = 'none';
}

function submitDetailsCancel() {
    const preset = document.getElementById('detailsReasonPreset').value.trim();
    const text = document.getElementById('detailsReasonText').value.trim();
    const err = document.getElementById('detailsModalError');

    if (!preset && !text) {
        err.textContent = 'Please choose a reason or enter an explanation.';
        err.style.display = 'block';
        return;
    }

    let fullReason = text;
    if (preset && preset !== 'Other' && !text.includes(preset)) {
        fullReason = preset + (text ? ': ' + text : '');
    } else if (!fullReason) {
        fullReason = preset;
    }

    if (!fullReason || fullReason.length < 3) {
        err.textContent = 'Please provide a meaningful reason (at least 3 characters).';
        err.style.display = 'block';
        return;
    }

    document.getElementById('hiddenReasonInput').value = fullReason;
    document.getElementById('detailsCancelForm').submit();
}
</script>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>© 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

<?php require_once 'rental_expiry_modal.php'; ?>
</body>
</html>
