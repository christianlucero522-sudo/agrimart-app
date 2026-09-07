<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

$shippingAddress = trim($_POST['shipping_address'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');
$buyerBank = trim($_POST['buyer_bank_name'] ?? '');
$buyerAccountName = trim($_POST['buyer_account_name'] ?? '');
$buyerAccountNumber = trim($_POST['buyer_account_number'] ?? '');
$transactionRef = trim($_POST['transaction_ref'] ?? '');

$allowedMethods = ['cash', 'gcash', 'maya', 'bank_transfer'];
if (!in_array($paymentMethod, $allowedMethods)) {
    $paymentMethod = 'cash';
}

if ($shippingAddress === '') {
    $_SESSION['checkout_error'] = 'Please provide a complete shipping address.';
    header('Location: checkout.php');
    exit;
}

// Handle Payment Proof Receipt Upload
$paymentProofPath = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['payment_proof']['tmp_name'];
    $origName = basename($_FILES['payment_proof']['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    if (in_array($ext, $allowed)) {
        $filename = 'pay_order_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $destPath = __DIR__ . '/uploads/payments/' . $filename;
        if (move_uploaded_file($tmpName, $destPath)) {
            $paymentProofPath = 'uploads/payments/' . $filename;
        }
    }
}

$conn->begin_transaction();

try {
    // 1. Fetch Cart and Cart Items
    $cartSql = "
        SELECT 
            ci.cart_item_id,
            ci.quantity AS cart_quantity,
            p.product_id,
            p.product_name,
            p.price,
            p.quantity AS stock_quantity,
            p.user_id AS seller_id
        FROM cart ca
        INNER JOIN cart_items ci ON ca.cart_id = ci.cart_id
        INNER JOIN products p ON ci.product_id = p.product_id
        WHERE ca.user_id = ? AND p.status = 'active'
        FOR UPDATE
    ";
    
    $cartStmt = $conn->prepare($cartSql);
    $cartStmt->bind_param('i', $userId);
    $cartStmt->execute();
    $cartResult = $cartStmt->get_result();

    $cartItems = [];
    $totalAmount = 0.0;

    while ($row = $cartResult->fetch_assoc()) {
        if ((int)$row['cart_quantity'] > (int)$row['stock_quantity']) {
            throw new Exception("Product '" . $row['product_name'] . "' only has " . $row['stock_quantity'] . " in stock.");
        }
        $subtotal = (float)$row['price'] * (int)$row['cart_quantity'];
        $totalAmount += $subtotal;
        $row['subtotal'] = $subtotal;
        $cartItems[] = $row;
    }
    $cartStmt->close();

    if (empty($cartItems)) {
        throw new Exception('Your shopping cart is empty.');
    }

    // 2. Create Order
    $orderSql = "INSERT INTO orders (buyer_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, 'pending')";
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->bind_param('ids', $userId, $totalAmount, $shippingAddress);
    $orderStmt->execute();
    $orderId = $conn->insert_id;
    $orderStmt->close();

    // 3. Insert Order Items & Deduct Stock & Notify Sellers
    $itemSql = "INSERT INTO order_items (order_id, product_id, seller_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
    $itemStmt = $conn->prepare($itemSql);

    $stockSql = "UPDATE products SET quantity = quantity - ?, status = IF(quantity - ? <= 0, 'sold', 'active') WHERE product_id = ?";
    $stockStmt = $conn->prepare($stockSql);

    $notifSql = "INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'order', ?)";
    $notifStmt = $conn->prepare($notifSql);

    $sellersNotified = [];

    foreach ($cartItems as $item) {
        $pId = (int)$item['product_id'];
        $sId = (int)$item['seller_id'];
        $qty = (int)$item['cart_quantity'];
        $prc = (float)$item['price'];
        $sub = (float)$item['subtotal'];

        $itemStmt->bind_param('iiiidd', $orderId, $pId, $sId, $qty, $prc, $sub);
        $itemStmt->execute();

        $stockStmt->bind_param('iii', $qty, $qty, $pId);
        $stockStmt->execute();

        if (!in_array($sId, $sellersNotified) && $sId !== $userId) {
            $sellerTitle = "New Order #$orderId Received";
            $sellerMsg = "A customer has placed an order for your harvest crop. Please review buyer payment and process delivery.";
            $notifStmt->bind_param('issi', $sId, $sellerTitle, $sellerMsg, $orderId);
            $notifStmt->execute();
            $sellersNotified[] = $sId;
        }
    }
    $itemStmt->close();
    $stockStmt->close();

    // 4. Create Payment Record with Buyer Bank Details
    $paymentStatus = ($paymentMethod === 'cash') ? 'pending' : 'paid';
    $paidAt = ($paymentMethod === 'cash') ? null : date('Y-m-d H:i:s');
    
    $paySql = "INSERT INTO payments (user_id, order_id, payment_method, buyer_bank_name, buyer_account_name, buyer_account_number, amount, payment_status, transaction_ref, payment_proof, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $payStmt = $conn->prepare($paySql);
    $payStmt->bind_param('iissssdsdss', $userId, $orderId, $paymentMethod, $buyerBank, $buyerAccountName, $buyerAccountNumber, $totalAmount, $paymentStatus, $transactionRef, $paymentProofPath, $paidAt);
    $payStmt->execute();
    $payStmt->close();

    // 5. Notify Buyer
    $buyerTitle = "Order #$orderId Placed Successfully";
    $buyerMsg = "Thank you for your order! Total amount: ₱" . number_format($totalAmount, 2) . ". Payment to seller via " . strtoupper(str_replace('_', ' ', $paymentMethod));
    $notifStmt->bind_param('issi', $userId, $buyerTitle, $buyerMsg, $orderId);
    $notifStmt->execute();
    $notifStmt->close();

    // 6. Clear Cart
    $clearCartSql = "DELETE ci FROM cart_items ci INNER JOIN cart ca ON ci.cart_id = ca.cart_id WHERE ca.user_id = ?";
    $clearStmt = $conn->prepare($clearCartSql);
    $clearStmt->bind_param('i', $userId);
    $clearStmt->execute();
    $clearStmt->close();

    $conn->commit();

    // 7. Send Gmail / Email Notifications
    require_once 'mailer.php';
    @sendOrderPlacedEmails($orderId, $conn);

    header("Location: order_details.php?id=$orderId&placed=1");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['checkout_error'] = $e->getMessage();
    header('Location: checkout.php');
    exit;
}
