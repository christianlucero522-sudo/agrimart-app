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
            // When an order is marked completed, automatically mark payment as paid
            if ($newStatus === 'completed') {
                $conn->query("UPDATE payments SET payment_status = 'paid' WHERE order_id = $orderId");
            }
        }
        if (!empty($newPayStatus)) {
            $conn->query("UPDATE payments SET payment_status = '$newPayStatus' WHERE order_id = $orderId");
        }
        $message = "Order #$orderId records updated successfully.";
    }
}

// Automatically sync existing completed orders so payment status is paid
$conn->query("UPDATE payments p JOIN orders o ON p.order_id = o.order_id SET p.payment_status = 'paid' WHERE o.order_status = 'completed' AND p.payment_status != 'paid'");

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
    <title>Manage Sales & Transactions — AgriMart Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
</head>
<body class="admin-console">

<?php 
$activeNav = 'orders';
include __DIR__ . '/admin_sidebar.php'; 
?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="topbar-subline">Customer orders & transactions</div>
            <h1 class="topbar-heading">Customer <i>orders.</i></h1>
        </div>
        <div class="topbar-actions">
            <a href="admin_dashboard.php" class="btn-review-action">Back to Overview</a>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div style="background:#e6f4ea; border:1px solid #cce8d4; color:#137333; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="filter-pills-row">
        <a href="admin_sales.php" class="filter-pill <?= empty($statusFilter) ? 'active' : '' ?>">All Orders</a>
        <a href="admin_sales.php?status=pending" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="admin_sales.php?status=confirmed" class="filter-pill <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="admin_sales.php?status=processing" class="filter-pill <?= $statusFilter === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="admin_sales.php?status=completed" class="filter-pill <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="admin_sales.php?status=cancelled" class="filter-pill <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="transactions-card">
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
                    <?php foreach ($orders as $o): 
                        $isCompleted = ($o['order_status'] === 'completed');
                        $payStatus = $isCompleted ? 'paid' : strtolower($o['payment_status'] ?? 'pending');
                    ?>
                        <tr>
                            <td><strong>#<?= (int)$o['order_id'] ?></strong></td>
                            <td><?= htmlspecialchars($o['buyer_name']) ?><br><small style="color:#777;"><?= htmlspecialchars($o['buyer_phone'] ?: 'No phone') ?></small></td>
                            <td><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></td>
                            <td><strong><?= (int)$o['total_items'] ?></strong> item(s)</td>
                            <td><strong style="color:var(--forest-900);">₱<?= number_format((float)$o['total_amount'], 2) ?></strong></td>
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
                                    <select name="payment_status" onchange="this.form.submit()" style="padding:3px 6px; font-size:12px; <?= $payStatus === 'paid' ? 'font-weight:600; color:#15803d;' : '' ?>">
                                        <option value="pending" <?= $payStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="paid" <?= $payStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="failed" <?= $payStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                                        <option value="refunded" <?= $payStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <form action="admin_sales.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                                    <select name="order_status" onchange="onOrderStatusChange(this)" style="padding:3px 6px; font-size:12px; <?= $isCompleted ? 'font-weight:600; color:#15803d;' : '' ?>">
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
<script>
function onOrderStatusChange(selectEl) {
    if (selectEl.value === 'completed') {
        const row = selectEl.closest('tr');
        if (row) {
            const paySelect = row.querySelector('select[name="payment_status"]');
            if (paySelect) {
                paySelect.value = 'paid';
            }
        }
    }
    selectEl.form.submit();
}
</script>
</body>
</html>
