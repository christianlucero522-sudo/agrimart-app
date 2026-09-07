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

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bookingId <= 0) {
    header('Location: bookings.php');
    exit;
}

// Handle Cancel Booking by Renter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $cancelSql = "SELECT booking_id, equipment_id FROM bookings WHERE booking_id = ? AND renter_id = ? AND status = 'pending' LIMIT 1";
    $cStmt = $conn->prepare($cancelSql);
    $cStmt->bind_param('ii', $bookingId, $userId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    
    if ($cRes->num_rows === 1) {
        $row = $cRes->fetch_assoc();
        $upSql = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?";
        $uStmt = $conn->prepare($upSql);
        $uStmt->bind_param('i', $bookingId);
        $uStmt->execute();
        $uStmt->close();

        // Update payment
        $conn->query("UPDATE payments SET payment_status = 'refunded' WHERE booking_id = $bookingId");

        // Make equipment available again
        $conn->query("UPDATE equipment SET availability = 'available' WHERE equipment_id = " . (int)$row['equipment_id']);

        header("Location: booking_details.php?id=$bookingId&cancelled=1");
        exit;
    }
    $cStmt->close();
}

// Fetch Booking
$sql = "
    SELECT 
        b.booking_id,
        b.equipment_id,
        b.renter_id,
        b.owner_id,
        b.pickup_location,
        b.dropoff_location,
        b.booking_date,
        b.start_date,
        b.end_date,
        b.total_amount,
        b.status,
        b.created_at,
        b.updated_at,
        e.equipment_name,
        e.description,
        e.brand,
        e.model,
        e.rate_type,
        e.rate_price,
        e.image_url,
        renter.full_name AS renter_name,
        renter.email AS renter_email,
        renter.phone AS renter_phone,
        owner.full_name AS owner_name,
        owner.email AS owner_email,
        owner.phone AS owner_phone,
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
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users renter ON b.renter_id = renter.user_id
    INNER JOIN users owner ON b.owner_id = owner.user_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE b.booking_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header('Location: bookings.php');
    exit;
}

// Security: User must be renter, or owner, or admin
if ($userRole !== 'admin' && (int)$booking['renter_id'] !== $userId && (int)$booking['owner_id'] !== $userId) {
    header('Location: bookings.php');
    exit;
}

$isRenter = ($userId === (int)$booking['renter_id']);
$isOwner = ($userId === (int)$booking['owner_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?= (int)$booking['booking_id'] ?> ? AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1100px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--forest-900); font-weight: 600; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        .booking-layout { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(320px, 1fr); gap: 40px; }
        .panel { background: #fff; border: 1px solid #ded6b9; padding: 28px; margin-bottom: 25px; }
        .panel h2 { font-family: Georgia, serif; font-size: 22px; margin: 0 0 18px; color: #122017; border-bottom: 1px solid #efe8d3; padding-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; gap: 15px; margin-bottom: 12px; font-size: 14px; }
        .info-row span { color: #6b6a59; }
        .info-row strong { color: #122017; text-align: right; }
        .badge { display: inline-block; padding: 5px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .badge-pending { background: #efe2b7; color: #6e5817; }
        .badge-confirmed { background: #d0e3f5; color: #1b4975; }
        .badge-ongoing { background: #e2d9f3; color: #432b70; }
        .badge-completed { background: #e0edd5; color: #23581c; }
        .badge-cancelled { background: #f7dcd6; color: #7e2b1b; }
        .timeline { display: flex; justify-content: space-between; margin-top: 20px; position: relative; }
        .timeline-step { text-align: center; font-size: 12px; font-weight: 600; color: #888; flex: 1; }
        .timeline-step.active { color: var(--forest-900); font-weight: 700; }
        .timeline-step::before { content: ''; display: block; width: 14px; height: 14px; background: #ddd; border-radius: 50%; margin: 0 auto 8px; }
        .timeline-step.active::before { background: #415535; border: 2px solid #b29438; }
        @media(max-width:800px) { .booking-layout { grid-template-columns: 1fr; } }
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
    <a href="<?= $isOwner ? 'rental_requests.php' : 'bookings.php' ?>" class="back-link">? Back to <?= $isOwner ? 'Rental Requests' : 'My Rentals' ?></a>

    <?php if (isset($_GET['booked'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:16px 20px; margin-bottom:25px; font-weight:500;">
            ? Success! Your rental booking request #<?= (int)$booking['booking_id'] ?> has been submitted. The equipment owner has been notified.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cancelled'])): ?>
        <div style="background:#f7dcd6; border:1px solid #efb7aa; color:#7e2b1b; padding:16px 20px; margin-bottom:25px; font-weight:500;">
            ? Booking #<?= (int)$booking['booking_id'] ?> has been cancelled.
        </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:30px; flex-wrap:wrap; gap:15px;">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Rental Agreement</span>
            <h1 style="font-family:Georgia,serif; font-size:36px; margin:5px 0 0; color:#122017;">Booking #<?= (int)$booking['booking_id'] ?></h1>
        </div>
        <div>
            <span class="badge badge-<?= htmlspecialchars($booking['status']) ?>" style="font-size:14px; padding:8px 16px;">
                Status: <?= htmlspecialchars(ucfirst($booking['status'])) ?>
            </span>
        </div>
    </div>

    <!-- Timeline -->
    <div class="panel">
        <h2>Rental Schedule Progression</h2>
        <div class="timeline">
            <div class="timeline-step <?= in_array($booking['status'], ['pending', 'confirmed', 'ongoing', 'completed']) ? 'active' : '' ?>">Pending Review</div>
            <div class="timeline-step <?= in_array($booking['status'], ['confirmed', 'ongoing', 'completed']) ? 'active' : '' ?>">Approved / Confirmed</div>
            <div class="timeline-step <?= in_array($booking['status'], ['ongoing', 'completed']) ? 'active' : '' ?>">Ongoing Rental</div>
            <div class="timeline-step <?= $booking['status'] === 'completed' ? 'active' : '' ?>">Returned & Completed</div>
        </div>
    </div>

    <div class="booking-layout">
        <!-- Left: Equipment & Locations -->
        <div>
            <div class="panel">
                <h2>Equipment Information</h2>
                <div style="display:flex; gap:20px; align-items:center; margin-bottom:20px;">
                    <img src="<?= htmlspecialchars($booking['image_url'] ?: 'images/placeholder-equipment.svg') ?>" style="width:100px; height:100px; object-fit:cover; background:var(--forest-950);" alt="">
                    <div>
                        <h3 style="font-family:Georgia,serif; font-size:22px; margin:0 0 5px; color:#122017;"><?= htmlspecialchars($booking['equipment_name']) ?></h3>
                        <p style="color:#6b6a59; margin:0; font-size:14px;">
                            Brand: <strong><?= htmlspecialchars($booking['brand'] ?: 'Standard') ?></strong> | 
                            Model: <strong><?= htmlspecialchars($booking['model'] ?: 'Standard') ?></strong>
                        </p>
                        <p style="color:var(--forest-900); font-weight:700; margin:5px 0 0; font-size:16px;">
                            ?<?= number_format((float)$booking['rate_price'], 2) ?> / <?= htmlspecialchars($booking['rate_type']) ?>
                        </p>
                    </div>
                </div>

                <div class="info-row">
                    <span>Rental Period</span>
                    <strong><?= date('F d, Y', strtotime($booking['start_date'])) ?> &mdash; <?= date('F d, Y', strtotime($booking['end_date'])) ?></strong>
                </div>
                <div class="info-row">
                    <span>Pickup Location</span>
                    <strong><?= htmlspecialchars($booking['pickup_location']) ?></strong>
                </div>
                <div class="info-row">
                    <span>Dropoff / Return Location</span>
                    <strong><?= htmlspecialchars($booking['dropoff_location']) ?></strong>
                </div>
                <div class="info-row" style="margin-top:15px; padding-top:15px; border-top:2px solid #122017; font-size:18px;">
                    <span>Total Rental Amount</span>
                    <strong style="color:var(--forest-900);">?<?= number_format((float)$booking['total_amount'], 2) ?></strong>
                </div>
            </div>

            <?php if ($booking['status'] === 'completed' && $isRenter): ?>
                <div class="panel" style="background:#f9f7f0;">
                    <h2>Equipment Performance Feedback</h2>
                    <p style="color:#6b6a59; font-size:14px; margin-bottom:15px;">How was the machinery performance? Leave a rating for this equipment.</p>
                    <a href="add_review.php?booking_id=<?= (int)$booking['booking_id'] ?>" class="btn btn-solid">? Write an Equipment Review</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Parties & Payment -->
        <div>
            <div class="panel">
                <h2>Equipment Owner</h2>
                <div class="info-row">
                    <span>Owner Name</span>
                    <strong><?= htmlspecialchars($booking['owner_name']) ?></strong>
                </div>
                <div class="info-row">
                    <span>Phone</span>
                    <strong><?= htmlspecialchars($booking['owner_phone'] ?: 'None provided') ?></strong>
                </div>
                <div class="info-row">
                    <span>Email</span>
                    <strong><?= htmlspecialchars($booking['owner_email']) ?></strong>
                </div>
            </div>

            <div class="panel">
                <h2>Renter Details</h2>
                <div class="info-row">
                    <span>Renter Name</span>
                    <strong><?= htmlspecialchars($booking['renter_name']) ?></strong>
                </div>
                <div class="info-row">
                    <span>Phone</span>
                    <strong><?= htmlspecialchars($booking['renter_phone'] ?: 'None provided') ?></strong>
                </div>
                <div class="info-row">
                    <span>Email</span>
                    <strong><?= htmlspecialchars($booking['renter_email']) ?></strong>
                </div>
            </div>

            <div class="panel">
                <h2>Payment Information</h2>
                <div class="info-row">
                    <span>Payment Method</span>
                    <strong><?= htmlspecialchars(strtoupper($booking['payment_method'] ?? 'CASH')) ?></strong>
                </div>
                <?php if (!empty($booking['buyer_bank_name'])): ?>
                    <div class="info-row">
                        <span>Renter Account</span>
                        <strong><?= htmlspecialchars($booking['buyer_bank_name']) ?> — <?= htmlspecialchars($booking['buyer_account_name'] ?? '') ?> (<?= htmlspecialchars($booking['buyer_account_number'] ?? '') ?>)</strong>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span>Payment Status</span>
                    <strong><?= htmlspecialchars(ucfirst($booking['payment_status'] ?? 'pending')) ?></strong>
                </div>
                <?php if (!empty($booking['transaction_ref'])): ?>
                    <div class="info-row">
                        <span>Transaction Ref</span>
                        <strong><?= htmlspecialchars($booking['transaction_ref']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($booking['payment_proof'])): ?>
                    <div class="info-row">
                        <span>Receipt Proof</span>
                        <strong>
                            <a href="<?= htmlspecialchars($booking['payment_proof']) ?>" target="_blank" style="color:#768047; text-decoration:underline;">
                                📄 View Receipt
                            </a>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($booking['status'] === 'pending' && $isRenter): ?>
                <form action="booking_details.php?id=<?= $bookingId ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking request?');">
                    <input type="hidden" name="action" value="cancel_booking">
                    <button type="submit" class="btn btn-light" style="width:100%; border-color:#efb7aa; color:#a54129; background:#fae6df;">
                        Cancel Booking Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
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
