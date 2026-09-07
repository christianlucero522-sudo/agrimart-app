<?php
session_start();
require_once 'config.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
          || isset($_POST['ajax']) 
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!isset($_SESSION['user_id'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'redirect' => 'login.php', 'message' => 'Please login to manage your cart.']);
        exit;
    }
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }
    header('Location: cart.php');
    exit;
}

$cartItemId = isset($_POST['cart_item_id']) ? (int) $_POST['cart_item_id'] : 0;
$action = trim($_POST['action'] ?? '');
$inputQty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($cartItemId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid cart item.']);
        exit;
    }
    header('Location: cart.php');
    exit;
}

// Verify Item & Current Stock
$sql = "
    SELECT ci.cart_item_id, ci.cart_id, ci.quantity, p.product_id, p.product_name, p.price, p.quantity AS stock_quantity
    FROM cart_items ci
    INNER JOIN cart c ON ci.cart_id = c.cart_id
    INNER JOIN products p ON ci.product_id = p.product_id
    WHERE ci.cart_item_id = ? AND c.user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $cartItemId, $userId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Item was not found in your cart.']);
        exit;
    }
    header('Location: cart.php');
    exit;
}

$cartId = (int)$item['cart_id'];
$currentQty = (int)$item['quantity'];
$stockQty = (int)$item['stock_quantity'];
$unitPrice = (float)$item['price'];
$itemRemoved = false;
$newItemQty = $currentQty;
$message = '';

if ($action === 'remove') {
    $dStmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
    $dStmt->bind_param('i', $cartItemId);
    $dStmt->execute();
    $dStmt->close();
    $itemRemoved = true;
    $newItemQty = 0;
    $message = "Removed " . $item['product_name'] . " from cart.";
} elseif ($action === 'increase') {
    if ($currentQty < $stockQty) {
        $newItemQty = $currentQty + 1;
        $uStmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $uStmt->bind_param('ii', $newItemQty, $cartItemId);
        $uStmt->execute();
        $uStmt->close();
        $message = "Quantity increased.";
    } else {
        $message = "Maximum available stock reached (" . $stockQty . ").";
    }
} elseif ($action === 'decrease') {
    if ($currentQty > 1) {
        $newItemQty = $currentQty - 1;
        $uStmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $uStmt->bind_param('ii', $newItemQty, $cartItemId);
        $uStmt->execute();
        $uStmt->close();
        $message = "Quantity decreased.";
    } else {
        // Remove item when reduced below 1
        $dStmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
        $dStmt->bind_param('i', $cartItemId);
        $dStmt->execute();
        $dStmt->close();
        $itemRemoved = true;
        $newItemQty = 0;
        $message = "Removed " . $item['product_name'] . " from cart.";
    }
} else {
    // Action is 'update' or direct quantity input
    if ($inputQty <= 0) {
        $dStmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
        $dStmt->bind_param('i', $cartItemId);
        $dStmt->execute();
        $dStmt->close();
        $itemRemoved = true;
        $newItemQty = 0;
        $message = "Removed " . $item['product_name'] . " from cart.";
    } else {
        $newItemQty = min($inputQty, $stockQty);
        $uStmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $uStmt->bind_param('ii', $newItemQty, $cartItemId);
        $uStmt->execute();
        $uStmt->close();
        $message = "Cart updated.";
    }
}

// Calculate Total Cart Values
$calcSql = "
    SELECT 
        COALESCE(SUM(ci.quantity), 0) AS total_count,
        COUNT(ci.cart_item_id) AS total_products,
        COALESCE(SUM(ci.quantity * p.price), 0.00) AS cart_total
    FROM cart_items ci
    INNER JOIN products p ON ci.product_id = p.product_id
    WHERE ci.cart_id = ?
";
$cStmt = $conn->prepare($calcSql);
$cStmt->bind_param('i', $cartId);
$cStmt->execute();
$totals = $cStmt->get_result()->fetch_assoc();
$cStmt->close();

$totalCount = (int)($totals['total_count'] ?? 0);
$totalProducts = (int)($totals['total_products'] ?? 0);
$cartTotal = (float)($totals['cart_total'] ?? 0);
$itemSubtotal = $newItemQty * $unitPrice;

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'item_removed' => $itemRemoved,
        'cart_item_id' => $cartItemId,
        'quantity' => $newItemQty,
        'item_subtotal' => '₱' . number_format($itemSubtotal, 2),
        'cart_total' => '₱' . number_format($cartTotal, 2),
        'cart_count' => $totalCount,
        'product_count' => $totalProducts,
        'message' => $message
    ]);
    exit;
}

header('Location: cart.php');
exit;
