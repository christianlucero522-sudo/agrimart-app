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


$userId = (int) $_SESSION['user_id'];

$fullName = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? '';

$firstName = '';

if ($fullName !== '') {

    $parts = explode(
        ' ',
        trim($fullName)
    );

    $firstName = $parts[0];
}


/* =========================================================
   GET USER INFORMATION + ADDRESS
========================================================= */

$userSql = "
    SELECT
        u.full_name,
        u.email,
        u.phone,

        a.street,
        a.barangay,
        a.city_municipality,
        a.province,
        a.postal_code

    FROM users u

    LEFT JOIN addresses a
        ON u.user_id = a.user_id

    WHERE u.user_id = ?

    ORDER BY a.address_id DESC

    LIMIT 1
";


$userStmt = $conn->prepare($userSql);

if (!$userStmt) {
    die('Unable to load user information.');
}


$userStmt->bind_param(
    'i',
    $userId
);

$userStmt->execute();

$userResult = $userStmt->get_result();

$user = $userResult->fetch_assoc();


if (!$user) {
    die('User account not found.');
}


/* =========================================================
   BUILD SHIPPING ADDRESS
========================================================= */

$addressParts = [];


if (!empty($user['street'])) {
    $addressParts[] = $user['street'];
}


if (!empty($user['barangay'])) {
    $addressParts[] = $user['barangay'];
}


if (!empty($user['city_municipality'])) {
    $addressParts[] = $user['city_municipality'];
}


if (!empty($user['province'])) {
    $addressParts[] = $user['province'];
}


if (!empty($user['postal_code'])) {
    $addressParts[] = $user['postal_code'];
}


$shippingAddress = implode(
    ', ',
    $addressParts
);


/* =========================================================
   GET CART
========================================================= */

$cartSql = "
    SELECT

        ci.cart_item_id,
        ci.quantity AS cart_quantity,

        p.product_id,
        p.product_name,
        p.price,
        p.quantity AS stock_quantity,
        p.unit,
        p.image_url,
        p.user_id AS seller_id,
        seller.full_name AS seller_name,
        seller.phone AS seller_phone,

        c.category_name

    FROM cart ca

    INNER JOIN cart_items ci
        ON ca.cart_id = ci.cart_id

    INNER JOIN products p
        ON ci.product_id = p.product_id

    INNER JOIN users seller
        ON p.user_id = seller.user_id

    INNER JOIN categories c
        ON p.category_id = c.category_id

    WHERE ca.user_id = ?
      AND p.status = 'active'

    ORDER BY ci.cart_item_id DESC
";


$cartStmt = $conn->prepare($cartSql);

if (!$cartStmt) {
    die('Unable to load cart.');
}


$cartStmt->bind_param(
    'i',
    $userId
);

$cartStmt->execute();

$cartResult = $cartStmt->get_result();


/* =========================================================
   STORE CART ITEMS + TOTAL
========================================================= */

$cartItems = [];

$cartTotal = 0;

$totalQuantity = 0;


while ($item = $cartResult->fetch_assoc()) {

    $item['subtotal'] =
        (float) $item['price']
        *
        (int) $item['cart_quantity'];


    $cartTotal +=
        $item['subtotal'];


    $totalQuantity +=
        (int) $item['cart_quantity'];


    $cartItems[] = $item;
}


/* =========================================================
   EMPTY CART
========================================================= */

if (empty($cartItems)) {

    header('Location: cart.php');
    exit;

}


/* =========================================================
   IMAGE HELPER
========================================================= */

function getCheckoutImage($imageUrl)
{

    if (empty($imageUrl)) {
        return 'images/placeholder-product.svg';
    }


    if (
        strpos(
            $imageUrl,
            'assets/images/'
        ) === 0
    ) {

        return str_replace(
            'assets/images/',
            'images/',
            $imageUrl
        );

    }


    return $imageUrl;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Checkout — AgriMart
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <style>

        /* =====================================================
           HEADER
        ===================================================== */

        .site-header {
            background: var(--forest-950) !important;
            background-image: none !important;
        }


        .user-chip {

            color: var(--cream-50, #f6f1df);

            font-size: 14px;

            margin-right: 6px;

            white-space: nowrap;
        }


        .user-chip strong {
            color: var(--wheat-300, #d6b95f);
        }


        /* =====================================================
           CHECKOUT PAGE
        ===================================================== */

        .checkout-page {
            padding: 80px 0 100px;
        }


        .checkout-heading {
            margin-bottom: 45px;
        }


        .checkout-heading .eyebrow {
            color: var(--moss-500);
        }


        .checkout-heading h1 {

            margin: 10px 0 8px;

            font-size:
                clamp(38px, 5vw, 58px);
        }


        .checkout-heading p {
            color: #6b7261;
        }


        /* =====================================================
           LAYOUT
        ===================================================== */

        .checkout-layout {

            display: grid;

            grid-template-columns:
                minmax(0, 1.5fr)
                minmax(320px, .8fr);

            gap: 55px;

            align-items: start;
        }


        /* =====================================================
           PANELS
        ===================================================== */

        .checkout-panel {

            border:
                1px solid
                rgba(17, 55, 36, .15);

            padding: 32px;

            margin-bottom: 25px;
        }


        .checkout-panel-header {
            margin-bottom: 25px;
        }


        .checkout-panel-header .eyebrow {
            color: var(--moss-500);
        }


        .checkout-panel-header h2 {

            margin: 8px 0 0;

            font-size: 26px;
        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }


        .form-group {

            display: flex;

            flex-direction: column;

            gap: 8px;
        }


        .form-group-full {
            grid-column: 1 / -1;
        }


        .form-group label {

            color: var(--forest-900);

            font-size: 12px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: .8px;
        }


        .form-control {

            width: 100%;

            min-height: 48px;

            padding: 12px 14px;

            border:
                1px solid
                rgba(17, 55, 36, .3);

            background: #fff;

            color: var(--forest-900);

            font-family: inherit;

            font-size: 14px;

            box-sizing: border-box;
        }


        textarea.form-control {

            min-height: 110px;

            resize: vertical;
        }


        .form-control:focus {

            outline:
                2px solid
                var(--wheat-300);

            outline-offset: 1px;
        }


        .form-control[readonly] {
            background: #f3f0e4;
        }


        /* =====================================================
           PAYMENT
        ===================================================== */

        .payment-options {

            display: grid;

            gap: 12px;
        }


        .payment-option {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            padding: 16px;

            border:
                1px solid
                rgba(17, 55, 36, .2);

            cursor: pointer;
        }


        .payment-option:hover {
            border-color: var(--forest-900);
        }


        .payment-option input {
            margin-top: 3px;
        }


        .payment-option strong {

            display: block;

            margin-bottom: 3px;

            color: var(--forest-900);
        }


        .payment-option span {

            color: #6b7261;

            font-size: 12px;
        }


        /* =====================================================
           ORDER SUMMARY
        ===================================================== */

        .checkout-summary {

            background:
                var(--forest-900);

            color:
                var(--cream-50);

            padding: 30px;

            position: sticky;

            top: 100px;
        }


        .checkout-summary .eyebrow {
            color: var(--wheat-300);
        }


        .checkout-summary h2 {

            margin: 10px 0 28px;

            color: var(--cream-50);

            font-size: 28px;
        }


        /* =====================================================
           SUMMARY ITEM
        ===================================================== */

        .checkout-item {

            display: grid;

            grid-template-columns:
                65px
                minmax(0, 1fr);

            gap: 14px;

            padding: 15px 0;

            border-bottom:
                1px solid
                rgba(255,255,255,.12);
        }


        .checkout-item-image {

            width: 65px;

            height: 60px;

            overflow: hidden;

            background:
                var(--forest-950);
        }


        .checkout-item-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .checkout-item-name {

            margin-bottom: 4px;

            color: var(--cream-50);

            font-size: 13px;

            font-weight: 600;
        }


        .checkout-item-meta {

            color: var(--cream-100);

            font-size: 11px;
        }


        .checkout-item-price {

            margin-top: 5px;

            color: var(--wheat-300);

            font-size: 13px;

            font-weight: 700;
        }


        /* =====================================================
           TOTAL
        ===================================================== */

        .summary-row {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 12px 0;

            color: var(--cream-100);
        }


        .summary-total {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            margin-top: 15px;

            padding-top: 20px;

            border-top:
                1px solid
                rgba(255,255,255,.18);

            font-size: 21px;

            font-weight: 700;
        }


        .summary-total strong {
            color: var(--wheat-300);
        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .place-order-disabled {

            width: 100%;

            margin-top: 28px;

            opacity: .55;

            cursor: not-allowed;
        }


        .back-cart {

            display: block;

            margin-top: 16px;

            text-align: center;

            color: var(--cream-100);

            font-size: 13px;
        }


        /* =====================================================
           NOTICE
        ===================================================== */

        .checkout-notice {

            margin-top: 18px;

            padding: 14px;

            background:
                rgba(214, 185, 95, .12);

            color: var(--cream-100);

            font-size: 11px;

            line-height: 1.6;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .checkout-layout {
                grid-template-columns: 1fr;
            }


            .checkout-summary {
                position: static;
            }

        }


        @media (max-width: 600px) {

            .form-grid {
                grid-template-columns: 1fr;
            }


            .form-group-full {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header
    class="site-header"
    id="siteHeader"
>

<div class="wrap">


    <!-- LOGO -->

    <a
        href="index.php"
        class="logo"
    >

        <svg
            class="wheat-mark"
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >

            <path d="M20 4v30"/>

            <path
                d="M20 10 L12 5 M20 10 L28 5"
            />

            <path
                d="M20 16 L11 10 M20 16 L29 10"
            />

            <path
                d="M20 22 L11 16 M20 22 L29 16"
            />

            <path
                d="M20 28 L13 23 M20 28 L27 23"
            />

        </svg>


        <span class="logo-text">

            <b>
                AgriMart
            </b>

            <span>
                Field to Farm Gate
            </span>

        </span>

    </a>


    <!-- NAVIGATION -->

    <nav class="main-nav">

        <a href="index.php">
            Home
        </a>

        <a href="products.php">
            Products
        </a>

        <a href="equipment.php">
            Equipment
        </a>

        <a href="index.php#how-it-works">
            How It Works
        </a>

    </nav>


    <!-- CART -->

    <a
        href="cart.php"
        class="cart-link"
        aria-label="View cart"
    >

        <svg
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
        >

            <path
                d="M3 4h2l2.4 12.4a2 2 0 0 0 2 1.6h7.2a2 2 0 0 0 2-1.6L20 8H6"
            />

            <circle
                cx="9"
                cy="20"
                r="1.4"
            />

            <circle
                cx="17"
                cy="20"
                r="1.4"
            />

        </svg>


        <span class="cart-count">
            <?= $totalQuantity ?>
        </span>

    </a>


    <!-- ACCOUNT -->

    <div class="header-actions">


        <span class="user-chip">

            Hi,

            <strong>

                <?= htmlspecialchars(
                    $firstName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </span>


        <?php if ($role === 'admin'): ?>


            <a
                href="admin_dashboard.php"
                class="btn btn-solid"
            >
                Admin
            </a>


        <?php else: ?>


            <a
                href="dashboard.php"
                class="btn btn-solid"
            >
                Dashboard
            </a>


        <?php endif; ?>


        <a
            href="logout.php"
            class="btn btn-light"
        >
            Logout
        </a>


    </div>


</div>

</header>



<!-- =========================================================
     CHECKOUT
========================================================= -->

<main class="checkout-page">

<div class="wrap">


    <div class="checkout-heading">

        <span class="eyebrow">
            Almost There
        </span>

        <h1>
            Checkout
        </h1>

        <p>
            Review your order and confirm your
            delivery information.
        </p>

    </div>

    <?php if (isset($_SESSION['checkout_error'])): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:30px; font-size:14px;">
            <?= htmlspecialchars($_SESSION['checkout_error']) ?>
        </div>
        <?php unset($_SESSION['checkout_error']); ?>
    <?php endif; ?>

    <form action="process_checkout.php" method="POST" enctype="multipart/form-data">

    <div class="checkout-layout">


        <!-- =================================================
             LEFT
        ================================================== -->

        <div>


            <!-- =============================================
                 CONTACT INFORMATION
            ============================================== -->

            <section class="checkout-panel">


                <div class="checkout-panel-header">

                    <span class="eyebrow">
                        Step 01
                    </span>

                    <h2>
                        Contact Information
                    </h2>

                </div>


                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label>
                            Full Name
                        </label>

                        <input
                            type="text"
                            class="form-control"

                            value="<?= htmlspecialchars(
                                $user['full_name'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"

                            readonly
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            class="form-control"

                            value="<?= htmlspecialchars(
                                $user['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"

                            readonly
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="form-group form-group-full">

                        <label>
                            Phone Number
                        </label>

                        <input
                            type="text"
                            class="form-control"

                            value="<?= htmlspecialchars(
                                $user['phone'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"

                            readonly
                        >

                    </div>


                </div>


            </section>



            <!-- =============================================
                 DELIVERY INFORMATION
            ============================================== -->

            <section class="checkout-panel">


                <div class="checkout-panel-header">

                    <span class="eyebrow">
                        Step 02
                    </span>

                    <h2>
                        Delivery Information
                    </h2>

                </div>


                <div class="form-grid">


                    <div class="form-group form-group-full">

                        <label for="shipping_address">
                            Shipping Address
                        </label>

                        <textarea
                            id="shipping_address"
                            name="shipping_address"
                            class="form-control"
                            placeholder="Enter the complete delivery address..."
                        ><?= htmlspecialchars(
                            $shippingAddress,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                    </div>


                </div>


            </section>



            <!-- =============================================
                 PAYMENT
            ============================================== -->

            <section class="checkout-panel">


                <div class="checkout-panel-header">

                    <span class="eyebrow">
                        Step 03
                    </span>

                    <h2>
                        Payment Method
                    </h2>

                </div>


                <div class="payment-options">

                    <!-- COD -->
                    <label class="payment-option" style="cursor:pointer;">
                        <input
                            type="radio"
                            name="payment_method"
                            value="cash"
                            checked
                            onchange="switchPaymentInfo('cash')"
                        >
                        <div>
                            <strong>Cash on Delivery (COD)</strong>
                            <span>Pay cash directly to the seller or courier upon receiving your produce.</span>
                        </div>
                    </label>

                    <!-- GCASH -->
                    <label class="payment-option" style="cursor:pointer;">
                        <input
                            type="radio"
                            name="payment_method"
                            value="gcash"
                            onchange="switchPaymentInfo('gcash')"
                        >
                        <div>
                            <strong>GCash (Direct to Seller)</strong>
                            <span>Send GCash payment directly to the farmer/seller.</span>
                        </div>
                    </label>

                    <!-- MAYA -->
                    <label class="payment-option" style="cursor:pointer;">
                        <input
                            type="radio"
                            name="payment_method"
                            value="maya"
                            onchange="switchPaymentInfo('maya')"
                        >
                        <div>
                            <strong>Maya / E-Wallet (Direct to Seller)</strong>
                            <span>Transfer funds via Maya to the seller's mobile number.</span>
                        </div>
                    </label>

                    <!-- BANK TRANSFER -->
                    <label class="payment-option" style="cursor:pointer;">
                        <input
                            type="radio"
                            name="payment_method"
                            value="bank_transfer"
                            onchange="switchPaymentInfo('bank_transfer')"
                        >
                        <div>
                            <strong>Online Bank Transfer (Direct to Seller)</strong>
                            <span>Transfer directly to the seller's bank account (BDO, BPI, LandBank, etc.).</span>
                        </div>
                    </label>

                </div>

                <!-- PAYMENT DETAILS & BUYER BANK INFO -->
                <div id="paymentDetailsBox" style="display:none; margin-top:22px; border:1px solid #d8d0b7; background:#fff; padding:24px;">

                    <!-- SELLER RECIPIENT INFORMATION -->
                    <div style="background:#f9f7f0; border:1px solid #ded6b9; padding:16px; margin-bottom:20px;">
                        <span style="font-family:monospace; color:#768047; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:4px;">
                            🌾 Payment Recipient (Seller / Farmer)
                        </span>
                        <div style="font-size:14px; color:#122017; line-height:1.5;">
                            <?php
                            $sList = [];
                            foreach ($cartItems as $ci) {
                                if (!empty($ci['seller_name'])) {
                                    $sTxt = '<strong>' . htmlspecialchars($ci['seller_name']) . '</strong>' . (!empty($ci['seller_phone']) ? ' (Phone/GCash: ' . htmlspecialchars($ci['seller_phone']) . ')' : '');
                                    if (!in_array($sTxt, $sList)) $sList[] = $sTxt;
                                }
                            }
                            echo !empty($sList) ? implode('<br>', $sList) : '<strong>Agricultural Produce Seller</strong>';
                            ?>
                        </div>
                        <div style="font-size:12.5px; color:#686454; margin-top:6px;">
                            Total Amount to Transfer: <strong style="color:var(--forest-900); font-size:15px;">₱<?= number_format($cartTotal, 2) ?></strong>
                        </div>
                    </div>

                    <!-- BUYER BANK / ACCOUNT DETAILS FORM -->
                    <h3 style="font-family:Georgia,serif; font-size:18px; margin:0 0 14px; color:#122017;">Your Payment & Account Information</h3>
                    <p style="font-size:13px; color:#686454; margin:0 0 16px;">
                        Please provide your payment account details so the seller can easily verify and confirm your incoming transfer.
                    </p>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                        <div>
                            <label for="buyer_bank_name" style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; font-family:monospace; text-transform:uppercase; color:#5e604e;">
                                Your Bank / E-Wallet Provider *
                            </label>
                            <select id="buyer_bank_name" name="buyer_bank_name" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
                                <option value="GCash">GCash</option>
                                <option value="Maya">Maya</option>
                                <option value="LandBank">LandBank</option>
                                <option value="BDO Unibank">BDO Unibank</option>
                                <option value="BPI">BPI (Bank of the Philippine Islands)</option>
                                <option value="UnionBank">UnionBank</option>
                                <option value="Metrobank">Metrobank</option>
                                <option value="RCBC">RCBC</option>
                                <option value="Other Provider">Other Bank / E-Wallet</option>
                            </select>
                        </div>

                        <div>
                            <label for="buyer_account_name" style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; font-family:monospace; text-transform:uppercase; color:#5e604e;">
                                Your Account Holder Name *
                            </label>
                            <input
                                type="text"
                                id="buyer_account_name"
                                name="buyer_account_name"
                                placeholder="e.g. Juan Dela Cruz"
                                style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;"
                            >
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                        <div>
                            <label for="buyer_account_number" style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; font-family:monospace; text-transform:uppercase; color:#5e604e;">
                                Your Account / Mobile Number *
                            </label>
                            <input
                                type="text"
                                id="buyer_account_number"
                                name="buyer_account_number"
                                placeholder="e.g. 0917-123-4567 or Acct #"
                                style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;"
                            >
                        </div>

                        <div>
                            <label for="transaction_ref" style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; font-family:monospace; text-transform:uppercase; color:#5e604e;">
                                Transaction / Reference Number *
                            </label>
                            <input
                                type="text"
                                id="transaction_ref"
                                name="transaction_ref"
                                placeholder="e.g. Ref No. 902184712391"
                                style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="payment_proof" style="display:block; margin-bottom:6px; font-weight:700; font-size:11px; font-family:monospace; text-transform:uppercase; color:#5e604e;">
                            Upload Receipt Screenshot / Transfer Proof (Optional)
                        </label>
                        <input
                            type="file"
                            id="payment_proof"
                            name="payment_proof"
                            accept="image/*"
                            style="width:100%; padding:8px; border:1px solid #d8d0b7; background:#fff;"
                        >
                    </div>

                </div>

                <script>
                function switchPaymentInfo(method) {
                    const box = document.getElementById('paymentDetailsBox');
                    const bProvider = document.getElementById('buyer_bank_name');
                    if (method === 'cash') {
                        box.style.display = 'none';
                    } else {
                        box.style.display = 'block';
                        if (method === 'gcash') bProvider.value = 'GCash';
                        else if (method === 'maya') bProvider.value = 'Maya';
                        else if (method === 'bank_transfer') bProvider.value = 'LandBank';
                    }
                }
                </script>

            </section>

        </div>

        <!-- =================================================
             ORDER SUMMARY
        ================================================== -->

        <aside class="checkout-summary">

            <span class="eyebrow">
                Order Summary
            </span>

            <h2>
                Your Order
            </h2>

            <?php foreach ($cartItems as $item): ?>

                <div class="checkout-item">

                    <!-- IMAGE -->
                    <div class="checkout-item-image">
                        <img
                            src="<?= htmlspecialchars(
                                getCheckoutImage(
                                    $item['image_url']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            alt="<?= htmlspecialchars(
                                $item['product_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                    </div>

                    <!-- DETAILS -->
                    <div>
                        <div class="checkout-item-name">
                            <?= htmlspecialchars(
                                $item['product_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                        <div class="checkout-item-meta">
                            <?= (int) $item['cart_quantity'] ?>
                            ×
                            ₱<?= number_format(
                                (float) $item['price'],
                                2
                            ) ?>
                            <?php if (!empty($item['unit'])): ?>
                                /
                                <?= htmlspecialchars(
                                    $item['unit'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            <?php endif; ?>
                        </div>

                        <div class="checkout-item-price">
                            ₱<?= number_format(
                                (float) $item['subtotal'],
                                2
                            ) ?>
                        </div>
                    </div>

                </div>

            <?php endforeach; ?>

            <!-- ITEMS -->
            <div class="summary-row">
                <span>Items</span>
                <strong><?= $totalQuantity ?></strong>
            </div>

            <!-- PRODUCTS -->
            <div class="summary-row">
                <span>Products</span>
                <strong><?= count($cartItems) ?></strong>
            </div>

            <!-- TOTAL -->
            <div class="summary-total">
                <span>Total</span>
                <strong>
                    ₱<?= number_format(
                        $cartTotal,
                        2
                    ) ?>
                </strong>
            </div>

            <!-- PLACE ORDER SUBMIT -->
            <button
                type="submit"
                class="btn btn-solid"
                style="width: 100%; margin-top: 28px; cursor: pointer; text-align: center;"
            >
                Confirm & Place Order
            </button>

            <!-- BACK -->
            <a
                href="cart.php"
                class="back-cart"
            >
                ← Back to Cart
            </a>

            <div class="checkout-notice">
                By placing your order, you agree to AgriMart's terms of service and seller policies.
            </div>

        </aside>

    </div>

    </form>


</div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">

<div class="wrap">


    <div class="footer-bottom">


        <span>

            © 2026 AgriMart.
            All rights reserved.

        </span>


        <span>

            Digital Market Platform
            on Agricultural Products

        </span>


    </div>


</div>

</footer>


</body>

</html>


<?php

$userStmt->close();

$cartStmt->close();

$conn->close();

?>