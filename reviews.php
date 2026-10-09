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

// 1. Reviews written by user
$myReviewsSql = "
    SELECT r.*, p.product_name, e.equipment_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.product_id
    LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
    WHERE r.reviewer_id = ?
    ORDER BY r.review_id DESC
";
$mStmt = $conn->prepare($myReviewsSql);
$mStmt->bind_param('i', $userId);
$mStmt->execute();
$myReviews = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$mStmt->close();

// 2. Reviews received by user on their products / equipment
$recvSql = "
    SELECT r.*, p.product_name, e.equipment_name, u.full_name AS reviewer_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.product_id
    LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN users u ON r.reviewer_id = u.user_id
    WHERE p.user_id = ? OR e.user_id = ?
    ORDER BY r.review_id DESC
";
$rStmt = $conn->prepare($recvSql);
$rStmt->bind_param('ii', $userId, $userId);
$rStmt->execute();
$receivedReviews = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer & Seller Reviews — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(1000px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { margin-bottom: 35px; }
        .page-header h1 { font-family: Georgia, serif; font-size: clamp(32px, 4vw, 48px); margin: 0; color: #122017; }
        .review-card { background: #fff; border: 1px solid #ded6b9; padding: 24px; margin-bottom: 18px; }
        .star-box { color: #d4b65a; font-size: 16px; font-weight: 700; margin-bottom: 6px; }
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
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; color:var(--forest-900); font-weight:600; text-decoration:none; font-size:14px;">← Back to Dashboard</a>

    <div class="page-header">
        <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Marketplace Reputation</span>
        <h1>Ratings & Reviews</h1>
    </div>

    <?php if (isset($_GET['submitted'])): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:25px; font-weight:500;">
            ✓ Thank you! Your review and star rating has been posted.
        </div>
    <?php endif; ?>

    <h2 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin:0 0 15px; border-bottom:1px solid #d8d0b7; padding-bottom:8px;">
        Reviews on My Listings (<?= count($receivedReviews) ?>)
    </h2>

    <?php if (empty($receivedReviews)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:30px; margin-bottom:40px; text-align:center; color:#6b6a59;">
            No reviews received on your product or equipment listings yet.
        </div>
    <?php else: ?>
        <?php foreach ($receivedReviews as $rev): ?>
            <div class="review-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <div class="star-box"><?= str_repeat('★', (int)$rev['rating']) ?> (<?= (int)$rev['rating'] ?>/5)</div>
                    <span style="font-size:12px; color:#888;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                </div>
                <div style="font-weight:600; color:#122017; font-size:15px; margin-bottom:6px;">
                    Item: <?= htmlspecialchars($rev['product_name'] ?: ($rev['equipment_name'] ?: 'Agricultural Item')) ?>
                </div>
                <p style="color:#4a5043; margin:0 0 10px; line-height:1.6; font-size:14px;">
                    <?= nl2br(htmlspecialchars($rev['review_text'] ?: 'No comment provided.')) ?>
                </p>
                <div style="font-size:12px; color:#777;">
                    Reviewed by: <strong><?= htmlspecialchars($rev['reviewer_name'] ?? 'Verified Buyer') ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin:40px 0 15px; border-bottom:1px solid #d8d0b7; padding-bottom:8px;">
        Reviews Written by Me (<?= count($myReviews) ?>)
    </h2>

    <?php if (empty($myReviews)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:30px; text-align:center; color:#6b6a59;">
            You haven't written any product or equipment reviews yet.
        </div>
    <?php else: ?>
        <?php foreach ($myReviews as $rev): ?>
            <div class="review-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <div class="star-box"><?= str_repeat('★', (int)$rev['rating']) ?> (<?= (int)$rev['rating'] ?>/5)</div>
                    <span style="font-size:12px; color:#888;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                </div>
                <div style="font-weight:600; color:#122017; font-size:15px; margin-bottom:6px;">
                    <?= htmlspecialchars($rev['product_name'] ?: ($rev['equipment_name'] ?: 'Item')) ?>
                </div>
                <p style="color:#4a5043; margin:0; line-height:1.6; font-size:14px;">
                    <?= nl2br(htmlspecialchars($rev['review_text'] ?: 'No comment provided.')) ?>
                </p>
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
