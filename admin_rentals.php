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

// Automatically check and dispatch mobile SMS alerts for nearing rentals & overdue penalties
@checkAndSendRentalExpiryAlerts($conn);

$message = '';
// Handle Admin status overrides
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $bId = (int)$_POST['booking_id'];
    $newStatus = trim($_POST['status'] ?? '');
    $allowed = ['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'];
    if ($bId > 0 && in_array($newStatus, $allowed)) {
        if ($newStatus === 'completed') {
            notifyRentalCompleted($conn, $bId);
            $message = "Booking #$bId marked as Completed & Returned. Automatic SMS & in-app notifications sent.";
        } else {
            $conn->query("UPDATE bookings SET status = '$newStatus' WHERE booking_id = $bId");
            if ($newStatus === 'cancelled') {
                $conn->query("UPDATE payments SET payment_status = 'refunded' WHERE booking_id = $bId");
            }
            $message = "Booking #$bId status updated to " . ucfirst($newStatus) . ".";
        }
    }
}

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        b.*,
        e.equipment_name,
        e.brand,
        renter.full_name AS renter_name,
        renter.phone AS renter_phone,
        owner.full_name AS owner_name,
        owner.phone AS owner_phone,
        p.payment_method,
        p.payment_status,
        p.transaction_ref,
        p.payment_proof
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users renter ON b.renter_id = renter.user_id
    INNER JOIN users owner ON b.owner_id = owner.user_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE 1=1
";

if (!empty($statusFilter)) {
    if ($statusFilter === 'overdue') {
        $sql .= " AND b.status IN ('confirmed', 'ongoing') AND b.end_date < CURDATE()";
    } else {
        $sql .= " AND b.status = '$statusFilter'";
    }
}

$sql .= " ORDER BY b.booking_id DESC";

$rentals = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rentals — AgriMart Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .admin-wrap { width: min(1300px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .admin-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; }
        .admin-header h1 { font-family: Georgia, serif; font-size: clamp(30px, 4vw, 42px); margin: 0; color: #122017; }
        .table-panel { background: #fff; border: 1px solid #ded6b9; padding: 28px; border-radius: 4px; overflow-x: auto; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; min-width: 900px; }
        .admin-table th, .admin-table td { padding: 12px 14px; border-bottom: 1px solid #eee8d5; vertical-align: top; }
        .admin-table th { background: #f9f7f0; color: #5e604e; font-family: monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 3px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-ongoing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .badge-overdue { background: #fee2e2; color: #b91c1c; border: 1px solid #f87171; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 6px 14px; border: 1px solid #d8d0b7; background: #fff; text-decoration: none; color: #162018; font-size: 13px; border-radius: 3px; }
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
        <a href="admin_rentals.php" class="active">Rentals</a>
        <a href="admin_sales.php">Sales</a>
        <a href="admin_moderation.php">Reports & Moderation</a>
        <a href="admin_reports.php">Reports</a>
    </nav>
    <div class="header-actions">
        <span style="color:#fff; font-size:14px; margin-right:10px;">Admin: <strong><?= htmlspecialchars($firstName) ?></strong></span>
        <a href="logout.php" class="btn btn-light" style="background:#fff; color:#122017; font-weight:600; border:none; padding:8px 16px; border-radius:3px;">Logout</a>
    </div>
</div>
</header>

<main class="admin-wrap">
    <div class="admin-header">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; font-weight:700;">Machinery Booking Logs</span>
            <h1>Manage Equipment Rentals & Schedules</h1>
        </div>
        <a href="admin_dashboard.php" class="btn btn-light" style="background:#fff; color:#122017; border:1px solid #ded6b9; padding:8px 16px; text-decoration:none; border-radius:3px; font-size:13px;">← Back to Dashboard</a>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:20px; font-weight:500; border-radius:4px;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="admin_rentals.php" class="filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Rentals</a>
        <a href="admin_rentals.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="admin_rentals.php?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="admin_rentals.php?status=ongoing" class="filter-btn <?= $statusFilter === 'ongoing' ? 'active' : '' ?>">Ongoing</a>
        <a href="admin_rentals.php?status=overdue" class="filter-btn <?= $statusFilter === 'overdue' ? 'active' : '' ?>" style="color:#b91c1c;">⚠️ Overdue (5% Fee)</a>
        <a href="admin_rentals.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="admin_rentals.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="table-panel">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Equipment</th>
                    <th>Renter</th>
                    <th>Owner</th>
                    <th>Rental Schedule</th>
                    <th>Amount & Penalty</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentals)): ?>
                    <tr><td colspan="9" style="text-align:center; padding:30px; color:#6b6a59;">No rental bookings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rentals as $r): ?>
                        <?php
                        $todayTimestamp = strtotime(date('Y-m-d'));
                        $endTimestamp = strtotime($r['end_date']);
                        $isOverdue = in_array($r['status'], ['confirmed', 'ongoing']) && ($todayTimestamp > $endTimestamp);
                        $penaltyInfo = calculateRentalLatePenalty($r['total_amount'], $r['security_deposit'] ?? 0, $r['start_date'], $r['end_date'], $r['actual_return_date'] ?? null);
                        ?>
                        <tr style="<?= $isOverdue ? 'background:#fff8f8;' : '' ?>">
                            <td>
                                <strong>#<?= (int)$r['booking_id'] ?></strong>
                                <br><a href="booking_details.php?id=<?= (int)$r['booking_id'] ?>" target="_blank" style="font-size:11px; color:#768047; text-decoration:underline;">View Details →</a>
                            </td>
                            <td><strong><?= htmlspecialchars($r['equipment_name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['renter_name']) ?><br><small style="color:#777;"><?= htmlspecialchars($r['renter_phone'] ?: 'No phone') ?></small></td>
                            <td><?= htmlspecialchars($r['owner_name']) ?><br><small style="color:#777;"><?= htmlspecialchars($r['owner_phone'] ?: 'No phone') ?></small></td>
                            <td>
                                <?= (!empty($r['rental_hours']) && (int)$r['rental_hours'] > 0) ? (date('M d, Y', strtotime($r['start_date'])) . ' (' . (int)$r['rental_hours'] . ' ' . ((int)$r['rental_hours'] === 1 ? 'Hour' : 'Hours') . ')') : (date('M d, Y', strtotime($r['start_date'])) . ' to ' . date('M d, Y', strtotime($r['end_date']))) ?>
                                <?php if ($isOverdue): ?>
                                    <br><strong style="color:#b91c1c; font-size:11px;">⚠️ <?= $penaltyInfo['days_late'] ?> Day(s) Overdue</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--forest-900);">₱<?= number_format((float)$r['total_amount'], 2) ?></strong>
                                <?php if ($isOverdue): ?>
                                    <br><small style="color:#b91c1c; font-weight:700;">+₱<?= number_format($penaltyInfo['total_penalty'], 2) ?> (5%/day penalty)</small>
                                <?php elseif ($r['status'] === 'completed' && (float)($r['late_penalty'] ?? 0) > 0): ?>
                                    <br><small style="color:#b91c1c;">Penalty paid: ₱<?= number_format((float)$r['late_penalty'], 2) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars(strtoupper($r['payment_method'] ?? 'CASH')) ?></strong> (<?= htmlspecialchars(ucfirst($r['payment_status'] ?? 'pending')) ?>)
                                <?php if (!empty($r['transaction_ref'])): ?>
                                    <br><small style="color:#777;">Ref: <?= htmlspecialchars($r['transaction_ref']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($r['payment_proof'])): ?>
                                    <br><a href="<?= htmlspecialchars($r['payment_proof']) ?>" target="_blank" style="font-size:11px; color:#768047; text-decoration:underline;">📄 Receipt</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                                <?php if ($isOverdue): ?>
                                    <br><span class="badge badge-overdue" style="margin-top:4px;">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="admin_rentals.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['booking_id'] ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding:4px 6px; font-size:12px; border-radius:3px;">
                                        <option value="pending" <?= $r['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $r['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="ongoing" <?= $r['status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                        <option value="completed" <?= $r['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $r['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
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
