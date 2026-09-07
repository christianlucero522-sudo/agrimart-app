<?php
session_start();
require_once 'config.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
          || isset($_POST['ajax']) 
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!isset($_SESSION['user_id'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'redirect' => 'login.php', 'message' => 'Please sign in to add items to your cart.']);
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
    header('Location: products.php');
    exit;
}

$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;

if ($productId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }
    header('Location: products.php');
    exit;
}

if ($quantity <= 0) {
    $quantity = 1;
}

// Check Product
$productSql = "SELECT product_id, product_name, price, quantity, status FROM products WHERE product_id = ? AND status = 'active' LIMIT 1";
$productStmt = $conn->prepare($productSql);
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if (!$product) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Product is unavailable or out of stock.']);
        exit;
    }
    header('Location: products.php');
    exit;
}

$availableStock = (int) $product['quantity'];
if ($availableStock <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sorry, this product is currently out of stock.']);
        exit;
    }
    header("Location: product_details.php?id=$productId&error=stock");
    exit;
}

if ($quantity > $availableStock) {
    $quantity = $availableStock;
}

// Get or Create Cart
$cartStmt = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ? LIMIT 1");
$cartStmt->bind_param('i', $userId);
$cartStmt->execute();
$cart = $cartStmt->get_result()->fetch_assoc();
$cartStmt->close();

if (!$cart) {
    $createStmt = $conn->prepare("INSERT INTO cart (user_id) VALUES (?)");
    $createStmt->bind_param('i', $userId);
    $createStmt->execute();
    $cartId = (int) $createStmt->insert_id;
    $createStmt->close();
} else {
    $cartId = (int) $cart['cart_id'];
}

// Check Existing Cart Item
$itemStmt = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1");
$itemStmt->bind_param('ii', $cartId, $productId);
$itemStmt->execute();
$existingItem = $itemStmt->get_result()->fetch_assoc();
$itemStmt->close();

if ($existingItem) {
    $newQuantity = (int) $existingItem['quantity'] + $quantity;
    if ($newQuantity > $availableStock) {
        $newQuantity = $availableStock;
    }
    $cartItemId = (int) $existingItem['cart_item_id'];
    $uStmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
    $uStmt->bind_param('ii', $newQuantity, $cartItemId);
    $uStmt->execute();
    $uStmt->close();
} else {
    $iStmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
    $iStmt->bind_param('iii', $cartId, $productId, $quantity);
    $iStmt->execute();
    $iStmt->close();
}

// Calculate Total Cart Count
$totRes = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS c FROM cart_items WHERE cart_id = $cartId");
$totalCount = (int)($totRes->fetch_assoc()['c'] ?? 0);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cart_count' => $totalCount,
        'product_name' => $product['product_name'],
        'message' => 'Added ' . htmlspecialchars($product['product_name']) . ' to cart!'
    ]);
    exit;
}

header("Location: product_details.php?id=$productId&added=1");
exit;
