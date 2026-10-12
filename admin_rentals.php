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
            $conn->query("UPDATE payments SET payment_status = 'paid' WHERE booking_id = $bId");
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

// Auto-sync completed bookings payment status to paid
$conn->query("UPDATE payments p JOIN bookings b ON p.booking_id = b.booking_id SET p.payment_status = 'paid' WHERE b.status = 'completed' AND p.payment_status != 'paid'");

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
    <title>Manage Rentals — AgriMart Admin Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
</head>
<body class="admin-console">

<?php 
$activeNav = 'rentals';
include __DIR__ . '/admin_sidebar.php'; 
?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="topbar-subline">Machinery booking schedules</div>
            <h1 class="topbar-heading">Rentals & <i>bookings.</i></h1>
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
        <a href="admin_rentals.php" class="filter-pill <?= empty($statusFilter) ? 'active' : '' ?>">All Rentals</a>
        <a href="admin_rentals.php?status=pending" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="admin_rentals.php?status=confirmed" class="filter-pill <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="admin_rentals.php?status=ongoing" class="filter-pill <?= $statusFilter === 'ongoing' ? 'active' : '' ?>">Ongoing</a>
        <a href="admin_rentals.php?status=overdue" class="filter-pill <?= $statusFilter === 'overdue' ? 'active' : '' ?>" style="color:#b91c1c;">⚠️ Overdue (5% Fee)</a>
        <a href="admin_rentals.php?status=completed" class="filter-pill <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="admin_rentals.php?status=cancelled" class="filter-pill <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="transactions-card">
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
                                <strong><?= htmlspecialchars(strtoupper($r['payment_method'] ?? 'CASH')) ?></strong> (<?= ($r['status'] === 'completed') ? 'Paid' : htmlspecialchars(ucfirst($r['payment_status'] ?? 'pending')) ?>)
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
</body>
</html>
