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

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        b.booking_id,
        b.equipment_id,
        b.booking_date,
        b.start_date,
        b.end_date,
        b.total_amount,
        b.status,
        b.pickup_location,
        b.dropoff_location,
        e.equipment_name,
        e.brand,
        e.model,
        e.image_url,
        u.full_name AS owner_name,
        u.phone AS owner_phone,
        p.payment_method,
        p.payment_status
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users u ON b.owner_id = u.user_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE b.renter_id = ?
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

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rentals ? AgriMart</title>
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
        .booking-card { background: #fff; border: 1px solid #ded6b9; padding: 24px; margin-bottom: 20px; display: grid; grid-template-columns: 80px 1fr auto; gap: 20px; align-items: center; }
        .booking-img { width: 80px; height: 80px; object-fit: cover; background: var(--forest-950); }
        .meta-row { display: flex; gap: 18px; flex-wrap: wrap; font-size: 13px; color: #6b6a59; margin-bottom: 8px; }
        .meta-row strong { color: #122017; }
        .badge { display: inline-block; padding: 5px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-ongoing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .booking-total { font-size: 22px; font-weight: 700; color: var(--forest-900); margin-top: 6px; }
        @media(max-width:768px) { .booking-card { grid-template-columns: 1fr; } }
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
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Machinery & Tools</span>
            <h1>My Equipment Rentals</h1>
        </div>
        <a href="equipment.php" class="btn btn-solid">Browse Machinery</a>
    </div>

    <div class="filter-bar">
        <a href="bookings.php" class="filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Rentals</a>
        <a href="bookings.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="bookings.php?status=confirmed" class="filter-btn <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="bookings.php?status=ongoing" class="filter-btn <?= $statusFilter === 'ongoing' ? 'active' : '' ?>">Ongoing</a>
        <a href="bookings.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="bookings.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <?php if (empty($bookings)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:50px; text-align:center;">
            <h3 style="font-family:Georgia,serif; font-size:24px; margin-bottom:10px;">No rental bookings found</h3>
            <p style="color:#6b6a59; margin-bottom:25px;">You haven't requested any equipment rentals matching this filter.</p>
            <a href="equipment.php" class="btn btn-solid">Browse Available Equipment</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $b): ?>
            <div class="booking-card">
                <img src="<?= htmlspecialchars($b['image_url'] ?: 'images/placeholder-equipment.svg') ?>" class="booking-img" alt="">
                <div>
                    <div style="font-family:Georgia,serif; font-size:20px; font-weight:700; color:#122017; margin-bottom:4px;">
                        <?= htmlspecialchars($b['equipment_name']) ?>
                        <?php if (!empty($b['brand'])): ?><small style="font-family:inherit; font-size:14px; color:#6b6a59;">(<?= htmlspecialchars($b['brand']) ?>)</small><?php endif; ?>
                    </div>
                    <div class="meta-row">
                        <span>Booking <strong>#<?= (int)$b['booking_id'] ?></strong></span>
                        <span>Schedule: <strong><?= date('M d, Y', strtotime($b['start_date'])) ?> to <?= date('M d, Y', strtotime($b['end_date'])) ?></strong></span>
                        <span>Owner: <strong><?= htmlspecialchars($b['owner_name']) ?> (<?= htmlspecialchars($b['owner_phone'] ?: 'No phone') ?>)</strong></span>
                        <span>Payment: <strong><?= htmlspecialchars(strtoupper($b['payment_method'] ?? 'CASH')) ?> (<?= htmlspecialchars(ucfirst($b['payment_status'] ?? 'pending')) ?>)</strong></span>
                    </div>
                    <span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span>
                    <div class="booking-total">?<?= number_format((float)$b['total_amount'], 2) ?></div>
                </div>
                <div>
                    <a href="booking_details.php?id=<?= (int)$b['booking_id'] ?>" class="btn btn-dark">View Details ?</a>
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
