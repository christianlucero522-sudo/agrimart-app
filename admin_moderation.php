<?php
session_start();
require_once 'config.php';
require_once 'sms_helper.php';

// Require Admin Access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentAdminId = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Admin';

$message = '';
$error = '';

// Handle Moderation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $reportId = (int)($_POST['report_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    $itemType = trim($_POST['item_type'] ?? '');
    $itemId = (int)($_POST['item_id'] ?? 0);

    // 1. Direct Retrieval Approval from Appeals Section or Table
    if ($action === 'approve_retrieval' && $itemId > 0) {
        $conn->begin_transaction();
        try {
            $sellerId = 0;
            $itemName = '';
            $sellerPhone = '';

            if ($itemType === 'product') {
                $pRes = $conn->query("SELECT p.product_name, p.user_id, u.phone FROM products p INNER JOIN users u ON p.user_id = u.user_id WHERE p.product_id = $itemId LIMIT 1");
                if ($pRow = $pRes->fetch_assoc()) {
                    $sellerId = (int)$pRow['user_id'];
                    $itemName = $pRow['product_name'];
                    $sellerPhone = $pRow['phone'] ?? '';
                }
                $conn->query("UPDATE products SET status = 'active', admin_deactivated = 0, retrieval_status = 'approved', deactivation_reason = NULL WHERE product_id = $itemId");
            } elseif ($itemType === 'equipment') {
                $eRes = $conn->query("SELECT e.equipment_name, e.user_id, u.phone FROM equipment e INNER JOIN users u ON e.user_id = u.user_id WHERE e.equipment_id = $itemId LIMIT 1");
                if ($eRow = $eRes->fetch_assoc()) {
                    $sellerId = (int)$eRow['user_id'];
                    $itemName = $eRow['equipment_name'];
                    $sellerPhone = $eRow['phone'] ?? '';
                }
                $conn->query("UPDATE equipment SET status = 'active', admin_deactivated = 0, retrieval_status = 'approved', deactivation_reason = NULL WHERE equipment_id = $itemId");
            }

            if ($sellerId > 0) {
                // In-app notification to seller
                $notifTitle = "✅ Listing Reinstated: " . $itemName;
                $notifMsg = "Great news! AgriMart Administration has approved your retrieval appeal for \"" . $itemName . "\". Your listing has been reactivated and is now visible in the marketplace again.";
                $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                $nStmt->bind_param('issi', $sellerId, $notifTitle, $notifMsg, $itemId);
                $nStmt->execute();
                $nStmt->close();

                // SMS Alert to seller
                if (!empty($sellerPhone)) {
                    $smsMsg = "AgriMart Notice: Your retrieval appeal for '$itemName' has been APPROVED by administration! Your listing is now active and public again.";
                    sendSmsNotification($conn, $sellerId, $sellerPhone, $smsMsg);
                }
            }

            // If report_id was provided, update report
            if ($reportId > 0) {
                $uStmt = $conn->prepare("UPDATE reports SET status = 'action_taken', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Reinstated] Retrieval appeal approved by admin.'), resolved_by = ? WHERE report_id = ?");
                $uStmt->bind_param('ii', $currentAdminId, $reportId);
                $uStmt->execute();
                $uStmt->close();
            }

            $conn->commit();
            $message = "Listing #$itemId ($itemName) has been APPROVED and REACTIVATED! The seller has been notified via In-App and SMS.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to approve retrieval: " . $e->getMessage();
        }
    }

    // 2. Direct Retrieval Rejection
    elseif ($action === 'reject_retrieval' && $itemId > 0) {
        $conn->begin_transaction();
        try {
            $sellerId = 0;
            $itemName = '';
            $sellerPhone = '';
            $rejNote = !empty($adminNotes) ? $adminNotes : 'The submitted retrieval appeal does not satisfy marketplace guidelines.';

            if ($itemType === 'product') {
                $pRes = $conn->query("SELECT p.product_name, p.user_id, u.phone FROM products p INNER JOIN users u ON p.user_id = u.user_id WHERE p.product_id = $itemId LIMIT 1");
                if ($pRow = $pRes->fetch_assoc()) {
                    $sellerId = (int)$pRow['user_id'];
                    $itemName = $pRow['product_name'];
                    $sellerPhone = $pRow['phone'] ?? '';
                }
                $conn->query("UPDATE products SET retrieval_status = 'rejected' WHERE product_id = $itemId");
            } elseif ($itemType === 'equipment') {
                $eRes = $conn->query("SELECT e.equipment_name, e.user_id, u.phone FROM equipment e INNER JOIN users u ON e.user_id = u.user_id WHERE e.equipment_id = $itemId LIMIT 1");
                if ($eRow = $eRes->fetch_assoc()) {
                    $sellerId = (int)$eRow['user_id'];
                    $itemName = $eRow['equipment_name'];
                    $sellerPhone = $eRow['phone'] ?? '';
                }
                $conn->query("UPDATE equipment SET retrieval_status = 'rejected' WHERE equipment_id = $itemId");
            }

            if ($sellerId > 0) {
                // In-app notification to seller
                $notifTitle = "❌ Listing Retrieval Appeal Declined: " . $itemName;
                $notifMsg = "Your retrieval appeal for \"" . $itemName . "\" was reviewed and declined by AgriMart Administration. Reason: " . $rejNote . ". Please review our listing policies.";
                $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                $nStmt->bind_param('issi', $sellerId, $notifTitle, $notifMsg, $itemId);
                $nStmt->execute();
                $nStmt->close();

                // SMS Alert to seller
                if (!empty($sellerPhone)) {
                    $smsMsg = "AgriMart Notice: Your retrieval appeal for '$itemName' was declined by administration. Reason: $rejNote. Check My Products for details.";
                    sendSmsNotification($conn, $sellerId, $sellerPhone, $smsMsg);
                }
            }

            $conn->commit();
            $message = "Retrieval appeal for Listing #$itemId has been declined. The seller has been notified.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to reject retrieval: " . $e->getMessage();
        }
    }

    // 3. Actions linked to a specific report
    elseif ($reportId > 0) {
        // Fetch report details
        $rQuery = $conn->prepare("
            SELECT r.*, 
                   u.full_name AS reported_user_name, u.email AS reported_user_email, u.status AS reported_user_status, u.phone AS reported_user_phone,
                   p.product_name, p.status AS product_status, p.admin_deactivated AS product_admin_deactivated,
                   e.equipment_name, e.status AS equipment_status, e.admin_deactivated AS equipment_admin_deactivated
            FROM reports r
            LEFT JOIN users u ON r.reported_user_id = u.user_id
            LEFT JOIN products p ON r.product_id = p.product_id
            LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
            WHERE r.report_id = ?
            LIMIT 1
        ");
        $rQuery->bind_param('i', $reportId);
        $rQuery->execute();
        $report = $rQuery->get_result()->fetch_assoc();
        $rQuery->close();

        if ($report) {
            $reportedUserId = (int)$report['reported_user_id'];
            $productId = !empty($report['product_id']) ? (int)$report['product_id'] : null;
            $equipmentId = !empty($report['equipment_id']) ? (int)$report['equipment_id'] : null;
            $sellerPhone = trim($report['reported_user_phone'] ?? '');

            // Action: Suspend / Ban Seller Account
            if ($action === 'suspend_seller') {
                if ($reportedUserId > 0 && $reportedUserId !== $currentAdminId) {
                    $conn->begin_transaction();
                    try {
                        // Ban user account
                        $bStmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE user_id = ?");
                        $bStmt->bind_param('i', $reportedUserId);
                        $bStmt->execute();
                        $bStmt->close();

                        // Deactivate all listings by this seller and lock with admin_deactivated
                        $reasonMsg = 'Seller account suspended by admin due to report findings.';
                        $conn->query("UPDATE products SET status = 'inactive', admin_deactivated = 1, deactivation_reason = '$reasonMsg' WHERE user_id = $reportedUserId");
                        $conn->query("UPDATE equipment SET status = 'inactive', admin_deactivated = 1, deactivation_reason = '$reasonMsg' WHERE user_id = $reportedUserId");

                        // Update report
                        $repNotes = !empty($adminNotes) ? $adminNotes : 'Seller account suspended and all listings deactivated due to policy violations.';
                        $uStmt = $conn->prepare("UPDATE reports SET status = 'action_taken', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                        $uStmt->bind_param('sii', $repNotes, $currentAdminId, $reportId);
                        $uStmt->execute();
                        $uStmt->close();

                        // Send notification to reporter
                        $nMsg = "Your report #$reportId has been reviewed. Administration has taken enforcement action and suspended the offending account.";
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, 'Report Resolved - Action Taken', ?, 'system', ?)");
                        $nStmt->bind_param('isi', $report['reporter_id'], $nMsg, $reportId);
                        $nStmt->execute();
                        $nStmt->close();

                        // Send notification to seller
                        $sNotifTitle = "🚨 Account Suspended & Listings Deactivated";
                        $sNotifMsg = "Your AgriMart seller account has been suspended by administration due to policy violations. All your active listings have been deactivated. Contact administration to appeal.";
                        $snStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                        $snStmt->bind_param('issi', $reportedUserId, $sNotifTitle, $sNotifMsg, $reportId);
                        $snStmt->execute();
                        $snStmt->close();

                        if (!empty($sellerPhone)) {
                            $smsText = "AgriMart Alert: Your seller account has been SUSPENDED and listings deactivated due to report findings. Contact support to appeal.";
                            sendSmsNotification($conn, $reportedUserId, $sellerPhone, $smsText);
                        }

                        $conn->commit();
                        $message = "Seller account #$reportedUserId (" . htmlspecialchars($report['reported_user_name'] ?? 'User') . ") has been SUSPENDED and all listings deactivated.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Failed to suspend account: " . $e->getMessage();
                    }
                }
            }

            // Action: Deactivate Specific Listing
            elseif ($action === 'deactivate_listing') {
                $conn->begin_transaction();
                try {
                    $repReasonText = ucwords(str_replace('_', ' ', $report['reason'] ?? 'Violation'));
                    $deactReason = !empty($adminNotes) ? $adminNotes : "Deactivated by administration due to report findings ($repReasonText).";
                    $targetItemName = '';

                    if ($productId) {
                        $targetItemName = $report['product_name'] ?? 'Product';
                        $pUp = $conn->prepare("UPDATE products SET status = 'inactive', admin_deactivated = 1, deactivation_reason = ?, retrieval_status = 'none' WHERE product_id = ?");
                        $pUp->bind_param('si', $deactReason, $productId);
                        $pUp->execute();
                        $pUp->close();

                        // Notify seller
                        $notifTitle = "🛑 Product Listing Deactivated by Admin: " . $targetItemName;
                        $notifMsg = "Your product listing \"" . $targetItemName . "\" has been deactivated by AgriMart Administration following a report investigation (Reason: " . $deactReason . "). Only an administrator can reactivate this listing. If you have resolved the issue, you may submit a retrieval appeal message directly in \"My Products\".";
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                        $nStmt->bind_param('issi', $reportedUserId, $notifTitle, $notifMsg, $productId);
                        $nStmt->execute();
                        $nStmt->close();

                        // SMS Alert to seller
                        if (!empty($sellerPhone)) {
                            $smsMsg = "AgriMart Alert: Your product '$targetItemName' was deactivated by administration ($deactReason). Only an admin can reactivate it. Submit a retrieval request in My Products to appeal.";
                            sendSmsNotification($conn, $reportedUserId, $sellerPhone, $smsMsg);
                        }
                    } elseif ($equipmentId) {
                        $targetItemName = $report['equipment_name'] ?? 'Equipment';
                        $eUp = $conn->prepare("UPDATE equipment SET status = 'inactive', admin_deactivated = 1, deactivation_reason = ?, retrieval_status = 'none' WHERE equipment_id = ?");
                        $eUp->bind_param('si', $deactReason, $equipmentId);
                        $eUp->execute();
                        $eUp->close();

                        // Notify seller/owner
                        $notifTitle = "🛑 Equipment Listing Deactivated by Admin: " . $targetItemName;
                        $notifMsg = "Your equipment listing \"" . $targetItemName . "\" has been deactivated by AgriMart Administration following a report investigation (Reason: " . $deactReason . "). Only an administrator can reactivate this listing. You can submit a retrieval appeal in \"My Equipment\".";
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                        $nStmt->bind_param('issi', $reportedUserId, $notifTitle, $notifMsg, $equipmentId);
                        $nStmt->execute();
                        $nStmt->close();

                        // SMS Alert to owner
                        if (!empty($sellerPhone)) {
                            $smsMsg = "AgriMart Alert: Your equipment '$targetItemName' was deactivated by administration ($deactReason). Only an admin can reactivate it. Submit a retrieval request in My Equipment to appeal.";
                            sendSmsNotification($conn, $reportedUserId, $sellerPhone, $smsMsg);
                        }
                    }

                    $repNotes = !empty($adminNotes) ? $adminNotes : 'Listing deactivated by administration due to report findings. Notification and SMS sent to seller.';
                    $uStmt = $conn->prepare("UPDATE reports SET status = 'action_taken', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                    $uStmt->bind_param('sii', $repNotes, $currentAdminId, $reportId);
                    $uStmt->execute();
                    $uStmt->close();

                    $conn->commit();
                    $message = "Listing \"" . htmlspecialchars($targetItemName) . "\" has been deactivated and locked by Admin. The seller has been notified via In-App Notification and SMS.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to deactivate listing: " . $e->getMessage();
                }
            }

            // Action: Reactivate Listing directly from Report
            elseif ($action === 'reactivate_listing') {
                $conn->begin_transaction();
                try {
                    $targetItemName = '';
                    if ($productId) {
                        $targetItemName = $report['product_name'] ?? 'Product';
                        $conn->query("UPDATE products SET status = 'active', admin_deactivated = 0, retrieval_status = 'approved', deactivation_reason = NULL WHERE product_id = $productId");
                        $nTitle = "✅ Product Listing Reactivated: " . $targetItemName;
                        $nMsg = "Your product listing \"" . $targetItemName . "\" has been reactivated by AgriMart Administration and is now active for buyers on the marketplace.";
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                        $nStmt->bind_param('issi', $reportedUserId, $nTitle, $nMsg, $productId);
                        $nStmt->execute();
                        $nStmt->close();
                    } elseif ($equipmentId) {
                        $targetItemName = $report['equipment_name'] ?? 'Equipment';
                        $conn->query("UPDATE equipment SET status = 'active', admin_deactivated = 0, retrieval_status = 'approved', deactivation_reason = NULL WHERE equipment_id = $equipmentId");
                        $nTitle = "✅ Equipment Listing Reactivated: " . $targetItemName;
                        $nMsg = "Your equipment listing \"" . $targetItemName . "\" has been reactivated by AgriMart Administration and is now available for rent.";
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                        $nStmt->bind_param('issi', $reportedUserId, $nTitle, $nMsg, $equipmentId);
                        $nStmt->execute();
                        $nStmt->close();
                    }

                    if (!empty($sellerPhone)) {
                        $smsMsg = "AgriMart Notice: Your listing '$targetItemName' has been reactivated by administration and is now live on the platform.";
                        sendSmsNotification($conn, $reportedUserId, $sellerPhone, $smsMsg);
                    }

                    $conn->commit();
                    $message = "Listing \"" . htmlspecialchars($targetItemName) . "\" has been reactivated by Admin. The seller was notified.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to reactivate listing: " . $e->getMessage();
                }
            }

            // Action: Dismiss Report
            elseif ($action === 'dismiss_report') {
                $repNotes = !empty($adminNotes) ? $adminNotes : 'Report reviewed and dismissed. No violation detected.';
                $uStmt = $conn->prepare("UPDATE reports SET status = 'dismissed', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                $uStmt->bind_param('sii', $repNotes, $currentAdminId, $reportId);
                $uStmt->execute();
                $uStmt->close();
                $message = "Report #$reportId has been dismissed.";
            }

            // Action: Mark as Reviewed (Pending further decision)
            elseif ($action === 'mark_reviewed') {
                $uStmt = $conn->prepare("UPDATE reports SET status = 'reviewed', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                $uStmt->bind_param('sii', $adminNotes, $currentAdminId, $reportId);
                $uStmt->execute();
                $uStmt->close();
                $message = "Report #$reportId marked as under review.";
            }

            // Action: Reactivate / Unban Seller Account
            elseif ($action === 'reactivate_seller') {
                if ($reportedUserId > 0) {
                    $conn->query("UPDATE users SET status = 'active' WHERE user_id = $reportedUserId");
                    $message = "Seller account #$reportedUserId (" . htmlspecialchars($report['reported_user_name'] ?? 'User') . ") has been reactivated.";
                }
            }
        }
    }
}

// Fetch Metrics
$totalReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports")->fetch_assoc()['c'] ?? 0);
$pendingReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
$actionReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'action_taken'")->fetch_assoc()['c'] ?? 0);
$dismissedReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'dismissed'")->fetch_assoc()['c'] ?? 0);

// Fetch Pending Retrieval Appeals across Products and Equipment
$pendingAppeals = [];
$pAppRes = $conn->query("
    SELECT p.product_id AS item_id, 'product' AS item_type, p.product_name AS item_name, p.price AS item_price,
           p.deactivation_reason, p.retrieval_message, p.retrieval_requested_at,
           u.user_id AS seller_id, u.full_name AS seller_name, u.email AS seller_email, u.phone AS seller_phone
    FROM products p
    INNER JOIN users u ON p.user_id = u.user_id
    WHERE p.admin_deactivated = 1 AND p.retrieval_status = 'pending'
    ORDER BY p.retrieval_requested_at DESC
");
if ($pAppRes) {
    while ($row = $pAppRes->fetch_assoc()) {
        $pendingAppeals[] = $row;
    }
}

$eAppRes = $conn->query("
    SELECT e.equipment_id AS item_id, 'equipment' AS item_type, e.equipment_name AS item_name, e.rate_price AS item_price,
           e.deactivation_reason, e.retrieval_message, e.retrieval_requested_at,
           u.user_id AS seller_id, u.full_name AS seller_name, u.email AS seller_email, u.phone AS seller_phone
    FROM equipment e
    INNER JOIN users u ON e.user_id = u.user_id
    WHERE e.admin_deactivated = 1 AND e.retrieval_status = 'pending'
    ORDER BY e.retrieval_requested_at DESC
");
if ($eAppRes) {
    while ($row = $eAppRes->fetch_assoc()) {
        $pendingAppeals[] = $row;
    }
}
$pendingAppealsCount = count($pendingAppeals);

// Filters
$statusFilter = trim($_GET['status'] ?? '');
$reasonFilter = trim($_GET['reason'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.*,
           rep.full_name AS reporter_name, rep.email AS reporter_email,
           tgt.full_name AS reported_name, tgt.email AS reported_email, tgt.status AS reported_status,
           p.product_name, p.status AS product_status, p.price AS product_price,
           p.admin_deactivated AS product_admin_deactivated, p.deactivation_reason AS product_deact_reason,
           p.retrieval_message AS product_retrieval_msg, p.retrieval_status AS product_retrieval_status,
           p.retrieval_requested_at AS product_retrieval_date,
           e.equipment_name, e.status AS equipment_status, e.rate_price AS equipment_rate,
           e.admin_deactivated AS equipment_admin_deactivated, e.deactivation_reason AS equipment_deact_reason,
           e.retrieval_message AS equipment_retrieval_msg, e.retrieval_status AS equipment_retrieval_status,
           e.retrieval_requested_at AS equipment_retrieval_date,
           adm.full_name AS resolver_name
    FROM reports r
    INNER JOIN users rep ON r.reporter_id = rep.user_id
    INNER JOIN users tgt ON r.reported_user_id = tgt.user_id
    LEFT JOIN products p ON r.product_id = p.product_id
    LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN users adm ON r.resolved_by = adm.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if (!empty($reasonFilter)) {
    $sql .= " AND r.reason = ?";
    $params[] = $reasonFilter;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND (rep.full_name LIKE ? OR tgt.full_name LIKE ? OR p.product_name LIKE ? OR e.equipment_name LIKE ? OR r.description LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= "sssss";
}

$sql .= " ORDER BY (r.status = 'pending') DESC, r.report_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
    <style>
        .report-badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .reason-fake_product { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .reason-misleading { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .reason-scam_fraud { background: #ffe4e6; color: #881337; border: 1px solid #fecdd3; }
        .reason-prohibited_item { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .reason-poor_quality { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .reason-other { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .stat-card-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .stat-card-badge.alert { background: #ef4444; color: #fff; }
        .admin-action-btn { padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; }
        .btn-ban { background: #b91c1c; color: #fff; border-color: #991b1b; }
        .btn-ban:hover { background: #991b1b; }
        .btn-deact { background: #c2410c; color: #fff; border-color: #9a3412; }
        .btn-deact:hover { background: #9a3412; }
        .btn-dismiss { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
        .btn-dismiss:hover { background: #e5e7eb; }
        .btn-reactivate { background: #15803d; color: #fff; border-color: #166534; }
        .btn-reactivate:hover { background: #166534; }
    </style>
</head>
<body class="admin-console">

<?php 
$activeNav = 'moderation';
include __DIR__ . '/admin_sidebar.php'; 
?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="topbar-subline">Marketplace trust, safety & retrieval appeals</div>
            <h1 class="topbar-heading">Reports & <i>moderation.</i></h1>
        </div>
        <div class="topbar-actions">
            <a href="admin_listings.php" class="btn-review-action">Manage Listings</a>
            <a href="admin_dashboard.php" class="btn-review-action" style="background:#fff; color:#18241d; border:1px solid #dcd7cb;">Back to Overview</a>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:16px 20px; margin-bottom:25px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:16px 20px; margin-bottom:25px; font-size:14px;">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Metrics Cards -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="stat-card" style="<?= $pendingReports > 0 ? 'border: 2px solid #ef4444; background: #fffbfb;' : '' ?>">
            <span class="label" style="color: <?= $pendingReports > 0 ? '#b91c1c' : '#768047' ?>;">Pending Reports</span>
            <div class="val" style="color: <?= $pendingReports > 0 ? '#b91c1c' : '#122017' ?>;"><?= number_format($pendingReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Awaiting investigation</span>
        </div>
        <div class="stat-card" style="<?= $pendingAppealsCount > 0 ? 'border: 2px solid #f59e0b; background: #fffdf5;' : '' ?>">
            <span class="label" style="color: <?= $pendingAppealsCount > 0 ? '#b45309' : '#768047' ?>;">Retrieval Appeals</span>
            <div class="val" style="color: <?= $pendingAppealsCount > 0 ? '#b45309' : '#122017' ?>;"><?= number_format($pendingAppealsCount) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Sellers requesting reactivation</span>
        </div>
        <div class="stat-card">
            <span class="label">Action Taken / Locked</span>
            <div class="val" style="color: #15803d;"><?= number_format($actionReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Violations penalized</span>
        </div>
        <div class="stat-card">
            <span class="label">Dismissed Reports</span>
            <div class="val" style="color: #6b7280;"><?= number_format($dismissedReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Non-viable / false flags</span>
        </div>
        <div class="stat-card">
            <span class="label">Total Reports Filed</span>
            <div class="val"><?= number_format($totalReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Lifetime user submissions</span>
        </div>
    </div>

    <!-- Seller Retrieval Requests (Reinstatement Appeals) Section -->
    <div class="admin-panel" style="<?= $pendingAppealsCount > 0 ? 'border: 2px solid #f59e0b; background:#fff;' : 'background:#fff;' ?> margin-bottom:30px;">
        <div class="admin-panel-head" style="background:<?= $pendingAppealsCount > 0 ? '#fffbeb;' : '#f9f7f0;' ?> border-bottom:1px solid #eedda2;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:20px;">📩</span>
                <div>
                    <h2 style="margin:0; font-size:18px; color:#78350f;">Seller Listing Retrieval Appeals (<?= $pendingAppealsCount ?> Pending)</h2>
                    <span style="font-size:12px; color:#92400e;">When an admin deactivates a listing, sellers can submit a retrieval explanation to request reactivation.</span>
                </div>
            </div>
        </div>

        <?php if ($pendingAppealsCount === 0): ?>
            <div style="padding:25px; text-align:center; color:#6b6a59; font-size:13px;">
                ✓ No pending seller retrieval requests. All deactivated listings are either resolved or have not requested appeal.
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Item Details</th>
                        <th>Seller Contact</th>
                        <th>Deactivation Reason</th>
                        <th>Seller Retrieval Message</th>
                        <th>Requested Date</th>
                        <th>Admin Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingAppeals as $ap): ?>
                        <tr style="background:#fffdf7;">
                            <td>
                                <span style="font-size:10px; text-transform:uppercase; font-family:monospace; font-weight:700; color:<?= $ap['item_type'] === 'product' ? '#2e7d32' : '#1e40af' ?>;">
                                    <?= $ap['item_type'] === 'product' ? '🌱 Product' : '🚜 Equipment' ?> #<?= (int)$ap['item_id'] ?>
                                </span><br>
                                <strong>
                                    <a href="<?= $ap['item_type'] === 'product' ? 'product_details.php?id=' : 'equipment_details.php?id=' ?><?= (int)$ap['item_id'] ?>" target="_blank" style="color:#122017; text-decoration:underline;">
                                        <?= htmlspecialchars($ap['item_name']) ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ap['seller_name']) ?></strong><br>
                                <small style="color:#666;"><?= htmlspecialchars($ap['seller_email']) ?></small><br>
                                <small style="color:#15803d; font-weight:600;">📱 <?= htmlspecialchars($ap['seller_phone'] ?: 'No phone') ?></small>
                            </td>
                            <td style="max-width:220px;">
                                <div style="font-size:12px; color:#b91c1c; background:#fef2f2; padding:6px 8px; border:1px solid #fecaca;">
                                    <?= htmlspecialchars($ap['deactivation_reason'] ?: 'Admin Moderation Deactivation') ?>
                                </div>
                            </td>
                            <td style="max-width:320px;">
                                <div style="background:#fffbeb; border:1px solid #fef3c7; color:#78350f; padding:8px 10px; font-size:13px; line-height:1.45; border-left:3px solid #f59e0b;">
                                    "<?= nl2br(htmlspecialchars($ap['retrieval_message'])) ?>"
                                </div>
                            </td>
                            <td>
                                <small style="color:#777; font-size:12px;">
                                    <?= !empty($ap['retrieval_requested_at']) ? date('M d, Y', strtotime($ap['retrieval_requested_at'])) : 'Recent' ?><br>
                                    <?= !empty($ap['retrieval_requested_at']) ? date('h:i A', strtotime($ap['retrieval_requested_at'])) : '' ?>
                                </small>
                            </td>
                            <td style="min-width:180px;">
                                <div style="display:flex; flex-direction:column; gap:6px;">
                                    <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Approve this retrieval appeal and REACTIVATE \'<?= addslashes($ap['item_name']) ?>\' on AgriMart?');">
                                        <input type="hidden" name="action" value="approve_retrieval">
                                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($ap['item_type']) ?>">
                                        <input type="hidden" name="item_id" value="<?= (int)$ap['item_id'] ?>">
                                        <button type="submit" class="admin-action-btn btn-reactivate" style="width:100%;">
                                            ✓ Approve & Reactivate
                                        </button>
                                    </form>
                                    <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Decline this retrieval appeal?');">
                                        <input type="hidden" name="action" value="reject_retrieval">
                                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($ap['item_type']) ?>">
                                        <input type="hidden" name="item_id" value="<?= (int)$ap['item_id'] ?>">
                                        <input type="text" name="admin_notes" placeholder="Reason for decline..." style="width:100%; box-sizing:border-box; padding:4px 6px; font-size:11px; margin-bottom:3px; border:1px solid #ded6b9;">
                                        <button type="submit" class="admin-action-btn btn-ban" style="width:100%;">
                                            ✕ Decline Appeal
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Reports Table Panel -->
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2>Reported Listings & Accounts (<?= count($reports) ?>)</h2>
            <form action="admin_moderation.php" method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search product, seller, reporter..." value="<?= htmlspecialchars($search) ?>" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                <select name="status" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>⏳ Pending Review</option>
                    <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>🔍 Under Review</option>
                    <option value="action_taken" <?= $statusFilter === 'action_taken' ? 'selected' : '' ?>>🚨 Action Taken (Suspended/Removed)</option>
                    <option value="dismissed" <?= $statusFilter === 'dismissed' ? 'selected' : '' ?>>✓ Dismissed</option>
                </select>
                <select name="reason" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                    <option value="">All Reasons</option>
                    <option value="fake_product" <?= $reasonFilter === 'fake_product' ? 'selected' : '' ?>>Fake / Counterfeit Product</option>
                    <option value="misleading" <?= $reasonFilter === 'misleading' ? 'selected' : '' ?>>Misleading Description / Specs</option>
                    <option value="scam_fraud" <?= $reasonFilter === 'scam_fraud' ? 'selected' : '' ?>>Scam / Fraudulent Activity</option>
                    <option value="prohibited_item" <?= $reasonFilter === 'prohibited_item' ? 'selected' : '' ?>>Prohibited Item</option>
                    <option value="poor_quality" <?= $reasonFilter === 'poor_quality' ? 'selected' : '' ?>>Poor Quality / Damaged</option>
                    <option value="other" <?= $reasonFilter === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
                <button type="submit" class="btn btn-solid" style="padding:8px 14px; font-size:12px;">Filter</button>
                <a href="admin_moderation.php" class="btn btn-light" style="padding:8px 14px; font-size:12px;">Reset</a>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Reported Item & Seller</th>
                    <th>Reason & Evidence</th>
                    <th>Reporter</th>
                    <th>Current Status</th>
                    <th>Admin Moderation Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px; color:#6b6a59;">
                            ✓ No reports match your criteria. The marketplace is running smoothly.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr style="<?= $r['status'] === 'pending' ? 'background:#fffcf7;' : '' ?>">
                            <td>
                                <strong>#<?= (int)$r['report_id'] ?></strong><br>
                                <small style="color:#777;"><?= date('M d, Y', strtotime($r['created_at'])) ?><br><?= date('h:i A', strtotime($r['created_at'])) ?></small>
                            </td>

                            <!-- Reported Item & Seller -->
                            <td>
                                <?php if (!empty($r['product_name'])): ?>
                                    <span style="font-size:11px; text-transform:uppercase; font-family:monospace; color:#2e7d32; font-weight:700;">🌱 Seed Product</span><br>
                                    <strong><a href="product_details.php?id=<?= (int)$r['product_id'] ?>" target="_blank" style="color:#122017; text-decoration:underline;"><?= htmlspecialchars($r['product_name']) ?></a></strong>
                                    <div style="font-size:12px; color:#666; margin-top:3px;">
                                        Listing Status: <span class="badge badge-<?= htmlspecialchars($r['product_status'] ?? 'inactive') ?>"><?= htmlspecialchars(ucfirst($r['product_status'] ?? 'N/A')) ?></span>
                                    </div>
                                    <?php if (!empty($r['product_admin_deactivated'])): ?>
                                        <div style="margin-top:6px; padding:6px 8px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:11px; color:#991b1b;">
                                            <div style="font-weight:700;">🛑 Deactivated by Admin</div>
                                            <?php if (!empty($r['product_deact_reason'])): ?>
                                                <div style="color:#7f1d1d; margin-top:2px;">Reason: <?= htmlspecialchars($r['product_deact_reason']) ?></div>
                                            <?php endif; ?>
                                            <?php if (($r['product_retrieval_status'] ?? '') === 'pending'): ?>
                                                <div style="margin-top:4px; padding:3px 6px; background:#fffbeb; border:1px solid #fde68a; border-radius:4px; color:#92400e; font-weight:600;">
                                                    📩 Appeal Pending Review
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif (!empty($r['equipment_name'])): ?>
                                    <span style="font-size:11px; text-transform:uppercase; font-family:monospace; color:#1e40af; font-weight:700;">🚜 Machinery</span><br>
                                    <strong><a href="equipment_details.php?id=<?= (int)$r['equipment_id'] ?>" target="_blank" style="color:#122017; text-decoration:underline;"><?= htmlspecialchars($r['equipment_name']) ?></a></strong>
                                    <div style="font-size:12px; color:#666; margin-top:3px;">
                                        Listing Status: <span class="badge badge-<?= htmlspecialchars($r['equipment_status'] ?? 'inactive') ?>"><?= htmlspecialchars(ucfirst($r['equipment_status'] ?? 'N/A')) ?></span>
                                    </div>
                                    <?php if (!empty($r['equipment_admin_deactivated'])): ?>
                                        <div style="margin-top:6px; padding:6px 8px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:11px; color:#991b1b;">
                                            <div style="font-weight:700;">🛑 Deactivated by Admin</div>
                                            <?php if (!empty($r['equipment_deact_reason'])): ?>
                                                <div style="color:#7f1d1d; margin-top:2px;">Reason: <?= htmlspecialchars($r['equipment_deact_reason']) ?></div>
                                            <?php endif; ?>
                                            <?php if (($r['equipment_retrieval_status'] ?? '') === 'pending'): ?>
                                                <div style="margin-top:4px; padding:3px 6px; background:#fffbeb; border:1px solid #fde68a; border-radius:4px; color:#92400e; font-weight:600;">
                                                    📩 Appeal Pending Review
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size:11px; text-transform:uppercase; font-family:monospace; color:#854d0e; font-weight:700;">👤 User Account</span>
                                <?php endif; ?>

                                <div style="margin-top:8px; padding-top:6px; border-top:1px dashed #ded6b9; font-size:12px;">
                                    Seller: <strong><?= htmlspecialchars($r['reported_name']) ?></strong><br>
                                    <small style="color:#666;"><?= htmlspecialchars($r['reported_email']) ?></small><br>
                                    Account Status: 
                                    <span class="badge badge-<?= htmlspecialchars($r['reported_status']) ?>" style="font-size:10px;">
                                        <?= htmlspecialchars(ucfirst($r['reported_status'])) ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Reason & Evidence -->
                            <td style="max-width:320px;">
                                <span class="report-badge reason-<?= htmlspecialchars($r['reason']) ?>">
                                    <?= ucwords(str_replace('_', ' ', $r['reason'])) ?>
                                </span>
                                <p style="margin:8px 0; font-size:13px; line-height:1.5; color:#374151; background:#f9f7f0; padding:10px; border:1px solid #ede7d5;">
                                    "<?= nl2br(htmlspecialchars($r['description'])) ?>"
                                </p>
                                <?php if (!empty($r['admin_notes'])): ?>
                                    <div style="font-size:12px; color:#0d2818; background:#e8eedf; padding:6px 8px; border-left:3px solid #23581c; margin-top:5px;">
                                        <strong>Admin Note:</strong> <?= htmlspecialchars($r['admin_notes']) ?>
                                        <?php if (!empty($r['resolver_name'])): ?>
                                             <span style="color:#666;">(by <?= htmlspecialchars($r['resolver_name']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Reporter -->
                            <td>
                                <strong><?= htmlspecialchars($r['reporter_name']) ?></strong><br>
                                <small style="color:#666;"><?= htmlspecialchars($r['reporter_email']) ?></small>
                            </td>

                            <!-- Current Status -->
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <span class="badge badge-pending" style="background:#fef08a; color:#854d0e; font-weight:700;">⏳ Pending</span>
                                <?php elseif ($r['status'] === 'action_taken'): ?>
                                    <span class="badge badge-active" style="background:#fee2e2; color:#991b1b; font-weight:700;">🚨 Action Taken</span>
                                <?php elseif ($r['status'] === 'reviewed'): ?>
                                    <span class="badge" style="background:#dbeafe; color:#1e40af; font-weight:700;">🔍 In Review</span>
                                <?php elseif ($r['status'] === 'dismissed'): ?>
                                    <span class="badge" style="background:#f3f4f6; color:#4b5563;">✓ Dismissed</span>
                                <?php endif; ?>
                            </td>

                            <!-- Moderation Actions -->
                            <td style="min-width:180px;">
                                <div style="display:flex; flex-direction:column; gap:6px;">

                                    <!-- 1. Suspend Seller Account Action -->
                                    <?php if ($r['reported_status'] !== 'banned'): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to SUSPEND seller \'<?= addslashes($r['reported_name']) ?>\'?\n\nThis will ban their login and deactivate all their products and machinery from AgriMart.');">
                                            <input type="hidden" name="action" value="suspend_seller">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-ban" style="width:100%;">
                                                🚨 Suspend Seller Account
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Reactivate and unban this seller account?');">
                                            <input type="hidden" name="action" value="reactivate_seller">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-reactivate" style="width:100%;">
                                                🔓 Reactivate Seller
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- 2. Deactivate / Reactivate Listing -->
                                    <?php 
                                    $isProdActive = !empty($r['product_name']) && $r['product_status'] === 'active';
                                    $isEquipActive = !empty($r['equipment_name']) && $r['equipment_status'] === 'active';
                                    $isProdAdminLocked = !empty($r['product_name']) && !empty($r['product_admin_deactivated']);
                                    $isEquipAdminLocked = !empty($r['equipment_name']) && !empty($r['equipment_admin_deactivated']);
                                    ?>
                                    <?php if ($isProdActive || $isEquipActive): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Deactivate and lock this listing from public display? The seller will receive in-app and SMS notifications, and only an admin can reactivate it.');">
                                            <input type="hidden" name="action" value="deactivate_listing">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-deact" style="width:100%;">
                                                🛑 Deactivate & Lock Listing
                                            </button>
                                        </form>
                                    <?php elseif ($isProdAdminLocked || $isEquipAdminLocked): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Reactivate this listing? It will become active and visible again, and the seller will be notified.');">
                                            <input type="hidden" name="action" value="reactivate_listing">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-reactivate" style="width:100%;">
                                                🔓 Reactivate Listing
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- 3. Dismiss Report -->
                                    <?php if ($r['status'] !== 'dismissed'): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Dismiss this report as not viable or resolved?');">
                                            <input type="hidden" name="action" value="dismiss_report">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-dismiss" style="width:100%;">
                                                ✓ Dismiss Report
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>
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
