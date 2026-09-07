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

$message = '';
// Handle Owner Booking Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking_status') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $allowed = ['confirmed', 'ongoing', 'completed', 'cancelled'];
    
    if ($bookingId > 0 && in_array($newStatus, $allowed)) {
        // Verify owner owns this booking
        $check = "SELECT b.booking_id, b.renter_id, b.equipment_id, e.equipment_name FROM bookings b INNER JOIN equipment e ON b.equipment_id = e.equipment_id WHERE b.booking_id = ? AND b.owner_id = ? LIMIT 1";
        $cStmt = $conn->prepare($check);
        $cStmt->bind_param('ii', $bookingId, $userId);
        $cStmt->execute();
        $booking = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();

        if ($booking) {
            $renterId = (int)$booking['renter_id'];
            $equipId = (int)$booking['equipment_id'];

            $upSql = "UPDATE bookings SET status = ? WHERE booking_id = ?";
            $uStmt = $conn->prepare($upSql);
            $uStmt->bind_param('si', $newStatus, $bookingId);
            $uStmt->execute();
            $uStmt->close();

            // Update equipment availability
            if ($newStatus === 'confirmed' || $newStatus === 'ongoing') {
                $conn->query("UPDATE equipment SET availability = 'rented' WHERE equipment_id = $equipId");
            } elseif ($newStatus === 'completed' || $newStatus === 'cancelled') {
                $conn->query("UPDATE equipment SET availability = 'available' WHERE equipment_id = $equipId");
            }

            // If completed, update payment
            if ($newStatus === 'completed') {
                $conn->query("UPDATE payments SET payment_status = 'paid', paid_at = NOW() WHERE booking_id = $bookingId AND payment_status = 'pending'");
            }

            // Notify Renter
            $title = "Rental Request #$bookingId " . ucfirst($newStatus);
            $msg = "The equipment owner has updated your booking for " . $booking['equipment_name'] . " to '" . ucfirst($newStatus) . "'.";
            $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
            $nStmt->bind_param('issi', $renterId, $title, $msg, $bookingId);
            $nStmt->execute();
            $nStmt->close();

            $message = "Booking #$bookingId status updated to " . ucfirst($newStatus) . ".";
        }
    }
}

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        b.booking_id,
        b.booking_date,
        b.start_date,
        b.end_date,
        b.total_amount,
        b.status,
        b.pickup_location,
        b.dropoff_location,
        e.equipment_name,
        e.brand,
        e.image_url,
        u.full_name AS renter_name,
        u.phone AS renter_phone,
        u.email AS renter_email,
        p.payment_method,
        p.payment_status
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users u ON b.renter_id = u.user_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE b.owner_id = ?
";

if (!empty($statusFilter)) {
    $sql .= " AND b.status = ?";
}

$sql .= " ORDER BY b.booking_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($statusFilter)) {
    $stmt->bind_param('is', $userId, $statusFilter);
} else {
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Booking Requests ? AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1200px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; flex-wrap: wrap; gap: 20px; }
        .page-header h1 { font-family: Georgia, serif; font-size: clamp(32px, 4vw, 48px); margin: 0; color: #122017; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; }
        .filter-btn { padding: 8px 16px; border: 1px solid #d8d0b7; background: #fff; text-decoration: none; color: #162018; font-size: 13px; font-weight: 500; }
        .filter-btn.active, .filter-btn:hover { background: var(--forest-900); color: #f5f0df; border-color: var(--forest-900); }
        .req-card { background: #fff; border: 1px solid #ded6b9; padding: 24px; margin-bottom: 20px; }
        .req-grid { display: grid; grid-template-columns: 1fr auto; gap: 25px; align-items: center; }
        .meta-row { display: flex; gap: 18px; flex-wrap: wrap; font-size: 13px; color: #6b6a59; margin-bottom: 8px; }
        .meta-row strong { color: #122017; }
        .badge { display: inline-block; padding: 5px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-ongoing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .req-total { font-size: 22px; font-weight: 700; color: var(--forest-900); margin-top: 6px; }
        .action-form { display: flex; gap: 8px; align-items: center; margin-top: 15px; }
        .action-select { padding: 8px 12px; border: 1px solid #d8d0b7; background: #f5f0df; font-size: 13px; outline: none; }
        @media(max-width:768px) { .req-grid { grid-template-columns: 1fr; } }
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
    <div class="page-header">
        <div>
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Machinery Management</span>
            <h1>Rental Booking Requests</h1>
        </div>
        <div>
            <a href="my_equipment.php" class="btn btn-light" style="margin-right:8px;">My Equipment</a>
            <a href="add_equipment.php" class="btn btn-solid">+ List Equipment</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
            ? <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="rental_requests.php" class="filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Requests</a>
        <a href="rental_requests.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="rental_requests.php?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="rental_requests.php?status=ongoing" class="filter-btn <?= $statusFilter === 'ongoing' ? 'active' : '' ?>">Ongoing</a>
        <a href="rental_requests.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="rental_requests.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <?php if (empty($requests)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:50px; text-align:center;">
            <h3 style="font-family:Georgia,serif; font-size:24px; margin-bottom:10px;">No rental requests found</h3>
            <p style="color:#6b6a59; margin-bottom:25px;">When users request to rent your machinery, their requests will appear here for approval.</p>
            <a href="my_equipment.php" class="btn btn-solid">Manage Equipment Listings</a>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $r): ?>
            <div class="req-card">
                <div class="req-grid">
                    <div>
                        <div style="font-family:Georgia,serif; font-size:20px; font-weight:700; color:#122017; margin-bottom:6px;">
                            <?= htmlspecialchars($r['equipment_name']) ?>
                            <?php if (!empty($r['brand'])): ?><small style="font-family:inherit; font-size:14px; color:#6b6a59;">(<?= htmlspecialchars($r['brand']) ?>)</small><?php endif; ?>
                        </div>
                        <div class="meta-row">
                            <span>Booking <strong>#<?= (int)$r['booking_id'] ?></strong></span>
                            <span>Renter: <strong><?= htmlspecialchars($r['renter_name']) ?> (<?= htmlspecialchars($r['renter_phone'] ?: $r['renter_email']) ?>)</strong></span>
                            <span>Duration: <strong><?= date('M d, Y', strtotime($r['start_date'])) ?> to <?= date('M d, Y', strtotime($r['end_date'])) ?></strong></span>
                            <span>Payment: <strong><?= htmlspecialchars(strtoupper($r['payment_method'] ?? 'CASH')) ?> (<?= htmlspecialchars(ucfirst($r['payment_status'] ?? 'pending')) ?>)</strong></span>
                        </div>
                        <span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                        <div class="req-total">?<?= number_format((float)$r['total_amount'], 2) ?></div>
                        <div style="font-size:13px; color:#596054; margin-top:8px;">
                            <strong>Pickup:</strong> <?= htmlspecialchars($r['pickup_location']) ?> | <strong>Dropoff:</strong> <?= htmlspecialchars($r['dropoff_location']) ?>
                        </div>
                    </div>
                    <div>
                        <a href="booking_details.php?id=<?= (int)$r['booking_id'] ?>" class="btn btn-dark" style="margin-bottom:10px; display:inline-block;">View Booking ?</a>
                        
                        <?php if ($r['status'] !== 'completed' && $r['status'] !== 'cancelled'): ?>
                            <form action="rental_requests.php" method="POST" class="action-form">
                                <input type="hidden" name="action" value="update_booking_status">
                                <input type="hidden" name="booking_id" value="<?= (int)$r['booking_id'] ?>">
                                <select name="new_status" class="action-select">
                                    <option value="confirmed" <?= $r['status'] === 'confirmed' ? 'selected' : '' ?>>Approve / Confirm</option>
                                    <option value="ongoing" <?= $r['status'] === 'ongoing' ? 'selected' : '' ?>>Mark Ongoing</option>
                                    <option value="completed">Mark Returned & Completed</option>
                                    <option value="cancelled">Reject / Cancel</option>
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
        <span>? 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
