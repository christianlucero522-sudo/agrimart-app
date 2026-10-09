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

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$equipmentId = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;

$targetTitle = 'Review & Rating';
$targetType = 'general';
$returnUrl = 'reviews.php?submitted=1';

if ($orderId > 0) {
    // Get product from order
    $oRes = $conn->query("SELECT oi.product_id, p.product_name FROM order_items oi INNER JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = $orderId LIMIT 1");
    if ($oRow = $oRes->fetch_assoc()) {
        $productId = (int)$oRow['product_id'];
        $targetTitle = "Review Product: " . $oRow['product_name'];
        $targetType = 'order';
        $returnUrl = "order_details.php?id=$orderId&reviewed=1";
    }
} elseif ($bookingId > 0) {
    $bRes = $conn->query("SELECT b.equipment_id, e.equipment_name FROM bookings b INNER JOIN equipment e ON b.equipment_id = e.equipment_id WHERE b.booking_id = $bookingId LIMIT 1");
    if ($bRow = $bRes->fetch_assoc()) {
        $equipmentId = (int)$bRow['equipment_id'];
        $targetTitle = "Review Equipment: " . $bRow['equipment_name'];
        $targetType = 'booking';
        $returnUrl = "booking_details.php?id=$bookingId&reviewed=1";
    }
} elseif ($productId > 0) {
    $pRes = $conn->query("SELECT product_name FROM products WHERE product_id = $productId LIMIT 1");
    if ($pRow = $pRes->fetch_assoc()) {
        $targetTitle = "Review Product: " . $pRow['product_name'];
        $targetType = 'product';
        $returnUrl = "product_details.php?id=$productId&reviewed=1";
    }
} elseif ($equipmentId > 0) {
    $eRes = $conn->query("SELECT equipment_name FROM equipment WHERE equipment_id = $equipmentId LIMIT 1");
    if ($eRow = $eRes->fetch_assoc()) {
        $targetTitle = "Review Equipment: " . $eRow['equipment_name'];
        $targetType = 'equipment';
        $returnUrl = "equipment_details.php?id=$equipmentId&reviewed=1";
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 5);
    $reviewText = trim($_POST['review_text'] ?? '');
    $pId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $eId = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $oId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $bId = !empty($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;

    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a valid star rating (1 to 5).';
    } else {
        $sql = "INSERT INTO reviews (reviewer_id, product_id, equipment_id, order_id, booking_id, rating, review_text) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiiiiis', $userId, $pId, $eId, $oId, $bId, $rating, $reviewText);
        if ($stmt->execute()) {
            $stmt->close();
            if ($pId) {
                header("Location: product_details.php?id=$pId&reviewed=1");
            } elseif ($eId) {
                header("Location: equipment_details.php?id=$eId&reviewed=1");
            } elseif ($oId) {
                header("Location: order_details.php?id=$oId&reviewed=1");
            } elseif ($bId) {
                header("Location: booking_details.php?id=$bId&reviewed=1");
            } else {
                header('Location: reviews.php?submitted=1');
            }
            exit;
        } else {
            $error = 'Error saving review: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Review — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; }
        .site-header { background: var(--forest-950) !important; }
        .page-wrap { width: min(700px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .form-card { background: #fff; border: 1px solid #ded6b9; padding: 40px; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; font-family: monospace; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #5e604e; margin-bottom: 8px; }
        .form-control { width: 100%; box-sizing: border-box; padding: 14px 16px; border: 1px solid #d8cba1; background: #fff; color: #141b13; font: inherit; outline: none; }
        .star-rating { display: flex; gap: 15px; margin-top: 8px; }
        .star-label { font-size: 24px; cursor: pointer; color: #d4b65a; }
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
    <a href="reviews.php" style="display:inline-block; margin-bottom:20px; color:var(--forest-900); font-weight:600; text-decoration:none; font-size:14px;">← Back to Reviews</a>

    <div class="form-card">
        <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px; display:block; margin-bottom:5px;">Community Feedback</span>
        <h1 style="font-family:Georgia,serif; font-size:30px; margin:0 0 25px; color:#122017;"><?= htmlspecialchars($targetTitle) ?></h1>

        <?php if (!empty($error)): ?>
            <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:25px; font-size:14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="add_review.php" method="POST">
            <input type="hidden" name="order_id" value="<?= $orderId > 0 ? $orderId : '' ?>">
            <input type="hidden" name="booking_id" value="<?= $bookingId > 0 ? $bookingId : '' ?>">
            <input type="hidden" name="product_id" value="<?= $productId > 0 ? $productId : '' ?>">
            <input type="hidden" name="equipment_id" value="<?= $equipmentId > 0 ? $equipmentId : '' ?>">

            <div class="form-group">
                <label>Star Rating (1 to 5 Stars) *</label>
                <div class="star-rating">
                    <?php for($i = 5; $i >= 1; $i--): ?>
                        <label class="star-label" style="display:flex; align-items:center; gap:5px; font-size:15px;">
                            <input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                            <?= str_repeat('★', $i) ?> (<?= $i ?>)
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="review_text">Review Comments & Experience</label>
                <textarea id="review_text" name="review_text" class="form-control" rows="5" placeholder="Describe the quality of the seeds or machinery condition, seller responsiveness, etc."></textarea>
            </div>

            <button type="submit" class="btn btn-solid" style="width:100%; padding:16px; font-size:14px; cursor:pointer;">Submit Review</button>
        </form>
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
