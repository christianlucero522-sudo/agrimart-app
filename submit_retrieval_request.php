<?php
session_start();
require_once 'config.php';

/* =========================================================
   REQUIRE LOGIN
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Seller';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_products.php');
    exit;
}

$itemType = trim($_POST['item_type'] ?? 'product');
$itemId = (int)($_POST['item_id'] ?? 0);
$retrievalMsg = trim($_POST['retrieval_message'] ?? '');

$redirectUrl = ($itemType === 'equipment') ? 'my_equipment.php' : 'my_products.php';

if ($itemId <= 0 || empty($retrievalMsg)) {
    header("Location: {$redirectUrl}?appeal_error=empty");
    exit;
}

if (strlen($retrievalMsg) < 10) {
    header("Location: {$redirectUrl}?appeal_error=too_short");
    exit;
}

try {
    if ($itemType === 'equipment') {
        // Check equipment exists, belongs to user, and is admin_deactivated
        $stmt = $conn->prepare("SELECT equipment_id, equipment_name, admin_deactivated FROM equipment WHERE equipment_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $itemId, $userId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            header("Location: my_equipment.php?appeal_error=not_found");
            exit;
        }

        if (empty($item['admin_deactivated'])) {
            header("Location: my_equipment.php?appeal_error=not_admin_locked");
            exit;
        }

        $itemName = $item['equipment_name'];

        // Update equipment record with pending appeal
        $up = $conn->prepare("UPDATE equipment SET retrieval_message = ?, retrieval_status = 'pending', retrieval_requested_at = NOW() WHERE equipment_id = ? AND user_id = ?");
        $up->bind_param('sii', $retrievalMsg, $itemId, $userId);
        $up->execute();
        $up->close();

        // Notify admins
        $adminRes = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
        if ($adminRes) {
            $nTitle = "📩 Equipment Retrieval Appeal: " . $itemName;
            $nMsg = "Seller " . $userName . " has submitted a retrieval appeal for deactivated machinery \"" . $itemName . "\": \"" . $retrievalMsg . "\". Review in Admin Moderation.";
            $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
            while ($adm = $adminRes->fetch_assoc()) {
                $admId = (int)$adm['user_id'];
                $nStmt->bind_param('issi', $admId, $nTitle, $nMsg, $itemId);
                $nStmt->execute();
            }
            $nStmt->close();
        }

        header("Location: my_equipment.php?appeal_submitted=1");
        exit;

    } else {
        // Product
        $stmt = $conn->prepare("SELECT product_id, product_name, admin_deactivated FROM products WHERE product_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $itemId, $userId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            header("Location: my_products.php?appeal_error=not_found");
            exit;
        }

        if (empty($item['admin_deactivated'])) {
            header("Location: my_products.php?appeal_error=not_admin_locked");
            exit;
        }

        $itemName = $item['product_name'];

        // Update product record with pending appeal
        $up = $conn->prepare("UPDATE products SET retrieval_message = ?, retrieval_status = 'pending', retrieval_requested_at = NOW() WHERE product_id = ? AND user_id = ?");
        $up->bind_param('sii', $retrievalMsg, $itemId, $userId);
        $up->execute();
        $up->close();

        // Notify admins
        $adminRes = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
        if ($adminRes) {
            $nTitle = "📩 Product Retrieval Appeal: " . $itemName;
            $nMsg = "Seller " . $userName . " has submitted a retrieval appeal for deactivated product \"" . $itemName . "\": \"" . $retrievalMsg . "\". Review in Admin Moderation.";
            $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
            while ($adm = $adminRes->fetch_assoc()) {
                $admId = (int)$adm['user_id'];
                $nStmt->bind_param('issi', $admId, $nTitle, $nMsg, $itemId);
                $nStmt->execute();
            }
            $nStmt->close();
        }

        header("Location: my_products.php?appeal_submitted=1");
        exit;
    }
} catch (Exception $e) {
    header("Location: {$redirectUrl}?appeal_error=exception");
    exit;
}
