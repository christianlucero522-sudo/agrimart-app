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
   BLOCK ADMIN USER ACTION
========================================================= */

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {
    header('Location: admin_dashboard.php');
    exit;
}


$userId = (int) $_SESSION['user_id'];


/* =========================================================
   REQUIRE POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: my_products.php');
    exit;

}


/* =========================================================
   GET PRODUCT ID
========================================================= */

$productId = isset($_POST['product_id'])
    ? (int) $_POST['product_id']
    : 0;


if ($productId <= 0) {

    header(
        'Location: my_products.php?error=invalid_product'
    );

    exit;

}


/* =========================================================
   GET PRODUCT + VERIFY OWNERSHIP
========================================================= */

$sql = "
    SELECT
        product_id,
        status

    FROM products

    WHERE product_id = ?
      AND user_id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);


if (!$stmt) {
    die('Unable to verify product.');
}


$stmt->bind_param(
    'ii',
    $productId,
    $userId
);


$stmt->execute();


$result = $stmt->get_result();

$product = $result->fetch_assoc();


$stmt->close();


/* =========================================================
   PRODUCT DOES NOT EXIST OR IS NOT OWNED BY USER
========================================================= */

if (!$product) {

    $conn->close();

    header(
        'Location: my_products.php?error=not_found'
    );

    exit;

}


/* =========================================================
   DETERMINE NEW STATUS
========================================================= */

if ($product['status'] === 'active') {

    $newStatus = 'inactive';
    $message = 'deactivated';

} else {

    $newStatus = 'active';
    $message = 'activated';

}


/* =========================================================
   UPDATE STATUS
========================================================= */

$updateSql = "
    UPDATE products

    SET status = ?

    WHERE product_id = ?
      AND user_id = ?
";


$updateStmt = $conn->prepare($updateSql);


if (!$updateStmt) {
    die('Unable to update product status.');
}


$updateStmt->bind_param(
    'sii',
    $newStatus,
    $productId,
    $userId
);


$updateStmt->execute();


$updateStmt->close();

$conn->close();


/* =========================================================
   REDIRECT
========================================================= */

header(
    'Location: my_products.php?'
    . $message
    . '=1'
);

exit;