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

// Handle Mark All as Read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId");
    header('Location: notifications.php');
    exit;
}

// Fetch Notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY notification_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications ? AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(800px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; flex-wrap: wrap; gap: 20px; }
        .page-header h1 { font-family: Georgia, serif; font-size: clamp(32px, 4vw, 48px); margin: 0; color: #122017; }
        .notif-card { background: #fff; border: 1px solid #ded6b9; padding: 20px 24px; margin-bottom: 15px; border-left: 4px solid #768047; }
        .notif-card.unread { background: #fdfbf3; border-left-color: #b29438; }
        .notif-title { font-weight: 700; font-size: 16px; color: #122017; margin-bottom: 4px; }
        .notif-msg { color: #596054; line-height: 1.6; font-size: 14px; margin: 0 0 8px; }
        .notif-meta { font-size: 12px; color: #8c8874; display: flex; gap: 15px; align-items: center; }
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
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Alerts & Communication</span>
            <h1>In-App Notifications</h1>
        </div>
        <div>
            <a href="notifications.php?mark_all_read=1" class="btn btn-light">Mark All as Read</a>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:50px; text-align:center; color:#6b6a59;">
            <h3 style="font-family:Georgia,serif; font-size:24px; margin-bottom:10px; color:#122017;">No notifications yet</h3>
            <p>You're all caught up! Updates regarding orders, rentals, and payments will appear here.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>">
                <div class="notif-title">
                    <?= htmlspecialchars($n['title']) ?>
                    <?php if (!$n['is_read']): ?>
                        <span style="display:inline-block; font-size:10px; background:#b29438; color:#fff; padding:2px 6px; text-transform:uppercase; border-radius:2px; margin-left:6px;">New</span>
                    <?php endif; ?>
                </div>
                <p class="notif-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                <div class="notif-meta">
                    <span><?= date('M d, Y ? h:i A', strtotime($n['created_at'])) ?></span>
                    <?php if ($n['notification_type'] === 'order' && !empty($n['related_id'])): ?>
                        <a href="order_details.php?id=<?= (int)$n['related_id'] ?>" style="color:var(--forest-900); font-weight:600; text-decoration:none;">View Order #<?= (int)$n['related_id'] ?> ?</a>
                    <?php elseif ($n['notification_type'] === 'booking' && !empty($n['related_id'])): ?>
                        <a href="booking_details.php?id=<?= (int)$n['related_id'] ?>" style="color:var(--forest-900); font-weight:600; text-decoration:none;">View Booking #<?= (int)$n['related_id'] ?> ?</a>
                    <?php endif; ?>
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
