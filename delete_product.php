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

/* =========================================================
   BLOCK ADMIN USER FROM SELLER ENDPOINT
========================================================= */
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* =========================================================
   REQUIRE POST METHOD
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_products.php');
    exit;
}

/* =========================================================
   GET PRODUCT ID
========================================================= */
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($productId <= 0) {
    header('Location: my_products.php?error=invalid_product');
    exit;
}

/* =========================================================
   VERIFY PRODUCT OWNERSHIP (SELLER ONLY)
========================================================= */
$stmt = $conn->prepare("SELECT product_id, product_name, image_url, quantity FROM products WHERE product_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $productId, $userId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: my_products.php?error=not_found');
    exit;
}

/* =========================================================
   REMOVE PRODUCT
========================================================= */
// 1. Clean up active cart items containing this product
$delCartStmt = $conn->prepare("DELETE FROM cart_items WHERE product_id = ?");
$delCartStmt->bind_param('i', $productId);
$delCartStmt->execute();
$delCartStmt->close();

// 2. Check if product is referenced in customer purchase history (order_items)
$orderCheck = $conn->prepare("SELECT COUNT(*) AS c FROM order_items WHERE product_id = ?");
$orderCheck->bind_param('i', $productId);
$orderCheck->execute();
$orderCount = (int)($orderCheck->get_result()->fetch_assoc()['c'] ?? 0);
$orderCheck->close();

if ($orderCount > 0) {
    // Soft-delete to preserve customer transaction history and tax invoices
    $uStmt = $conn->prepare("UPDATE products SET status = 'deleted', quantity = 0 WHERE product_id = ? AND user_id = ?");
    $uStmt->bind_param('ii', $productId, $userId);
    $uStmt->execute();
    $uStmt->close();
} else {
    // Hard-delete if no historical orders exist
    $dStmt = $conn->prepare("DELETE FROM products WHERE product_id = ? AND user_id = ?");
    $dStmt->bind_param('ii', $productId, $userId);
    $dStmt->execute();
    $dStmt->close();
}

$conn->close();

header('Location: my_products.php?deleted=1');
exit;
