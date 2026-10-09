<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a report.']);
    exit;
}

$reporterId = (int)$_SESSION['user_id'];
$reportType = trim($_POST['report_type'] ?? 'product');
$itemId = (int)($_POST['item_id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'other');
$description = trim($_POST['description'] ?? '');

$allowedReasons = ['fake_product', 'misleading', 'scam_fraud', 'prohibited_item', 'poor_quality', 'other'];
if (!in_array($reason, $allowedReasons)) {
    $reason = 'other';
}

if ($itemId <= 0 || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Please provide all required details and explanation for your report.']);
    exit;
}

$reportedUserId = 0;
$productId = null;
$equipmentId = null;

if ($reportType === 'product') {
    $productId = $itemId;
    $pStmt = $conn->prepare("SELECT user_id, product_name FROM products WHERE product_id = ? LIMIT 1");
    $pStmt->bind_param('i', $productId);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    if ($pRow = $pRes->fetch_assoc()) {
        $reportedUserId = (int)$pRow['user_id'];
    }
    $pStmt->close();
} elseif ($reportType === 'equipment') {
    $equipmentId = $itemId;
    $eStmt = $conn->prepare("SELECT user_id, equipment_name FROM equipment WHERE equipment_id = ? LIMIT 1");
    $eStmt->bind_param('i', $equipmentId);
    $eStmt->execute();
    $eRes = $eStmt->get_result();
    if ($eRow = $eRes->fetch_assoc()) {
        $reportedUserId = (int)$eRow['user_id'];
    }
    $eStmt->close();
} elseif ($reportType === 'user') {
    $reportedUserId = $itemId;
}

if ($reportedUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'The item or account you are attempting to report does not exist.']);
    exit;
}

if ($reportedUserId === $reporterId) {
    echo json_encode(['success' => false, 'message' => 'You cannot report your own listing or account.']);
    exit;
}

// Check for recent duplicate pending report from the same reporter on the same item
$dupSql = "SELECT report_id FROM reports WHERE reporter_id = ? AND status = 'pending' AND ";
if ($productId !== null) {
    $dupSql .= "product_id = ?";
    $dStmt = $conn->prepare($dupSql);
    $dStmt->bind_param('ii', $reporterId, $productId);
} elseif ($equipmentId !== null) {
    $dupSql .= "equipment_id = ?";
    $dStmt = $conn->prepare($dupSql);
    $dStmt->bind_param('ii', $reporterId, $equipmentId);
} else {
    $dupSql .= "reported_user_id = ? AND product_id IS NULL AND equipment_id IS NULL";
    $dStmt = $conn->prepare($dupSql);
    $dStmt->bind_param('ii', $reporterId, $reportedUserId);
}
$dStmt->execute();
if ($dStmt->get_result()->num_rows > 0) {
    $dStmt->close();
    echo json_encode(['success' => false, 'message' => 'You have already submitted a pending report for this item. Our administrators are currently reviewing it.']);
    exit;
}
$dStmt->close();

// Insert report
$ins = $conn->prepare("INSERT INTO reports (reporter_id, reported_user_id, product_id, equipment_id, report_type, reason, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
$ins->bind_param('iiiisss', $reporterId, $reportedUserId, $productId, $equipmentId, $reportType, $reason, $description);

if ($ins->execute()) {
    $reportId = $ins->insert_id;
    $ins->close();

    // Create system notification for admin (all admins or system log)
    $notifTitle = "New Listing Report #" . $reportId;
    $notifMsg = "A user reported a $reportType listing (Reason: " . ucwords(str_replace('_', ' ', $reason)) . "). Review required.";
    $adminRes = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
    if ($adminRes) {
        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
        while ($adm = $adminRes->fetch_assoc()) {
            $admId = (int)$adm['user_id'];
            $nStmt->bind_param('issi', $admId, $notifTitle, $notifMsg, $reportId);
            $nStmt->execute();
        }
        $nStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Your report has been received and submitted to AgriMart Administration for investigation. Thank you for keeping our marketplace safe.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record report: ' . $conn->error]);
}
