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
   GET USER CART
========================================================= */

$sql = "
    SELECT

        ci.cart_item_id,
        ci.quantity AS cart_quantity,

        p.product_id,
        p.product_name,
        p.description,
        p.price,
        p.quantity AS stock_quantity,
        p.unit,
        p.image_url,

        c.category_name

    FROM cart ca

    INNER JOIN cart_items ci
        ON ca.cart_id = ci.cart_id

    INNER JOIN products p
        ON ci.product_id = p.product_id

    INNER JOIN categories c
        ON p.category_id = c.category_id

    WHERE ca.user_id = ?
      AND p.status = 'active'

    ORDER BY ci.cart_item_id DESC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Unable to load cart.');
}


$stmt->bind_param(
    'i',
    $userId
);

$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   STORE CART ITEMS + CALCULATE TOTAL
========================================================= */

$cartItems = [];

$cartTotal = 0;

$totalQuantity = 0;


while ($item = $result->fetch_assoc()) {

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
   IMAGE HELPER
========================================================= */

function getCartProductImage($imageUrl)
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
        My Cart — AgriMart
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
           CART PAGE
        ===================================================== */

        .cart-page {
            padding: 80px 0 100px;
        }


        .cart-heading {
            margin-bottom: 45px;
        }


        .cart-heading .eyebrow {
            color: var(--moss-500);
        }


        .cart-heading h1 {

            margin-top: 10px;
            margin-bottom: 8px;

            font-size:
                clamp(38px, 5vw, 58px);
        }


        .cart-heading p {
            color: #6b7261;
        }


        /* =====================================================
           MESSAGE
        ===================================================== */

        .cart-message {

            margin-bottom: 25px;

            padding: 14px 18px;

            background: #e8eedf;

            border: 1px solid #cbd8bd;

            color: var(--forest-900);

            font-size: 14px;
        }


        .cart-message-error {

            background: #f4e4dc;

            border-color: #d9b8a8;

            color: #762f22;
        }


        /* =====================================================
           LAYOUT
        ===================================================== */

        .cart-layout {

            display: grid;

            grid-template-columns:
                minmax(0, 2fr)
                minmax(280px, 0.8fr);

            gap: 50px;

            align-items: start;
        }


        /* =====================================================
           CART ITEM
        ===================================================== */

        .cart-item {

            display: grid;

            grid-template-columns:
                150px
                minmax(0, 1fr);

            gap: 25px;

            padding: 25px 0;

            border-bottom:
                1px solid
                rgba(17, 55, 36, .15);
        }


        .cart-item:first-child {

            border-top:
                1px solid
                rgba(17, 55, 36, .15);
        }


        .cart-item-image {

            width: 150px;

            height: 130px;

            background: var(--forest-900);

            overflow: hidden;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .cart-item-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .cart-item-category {

            display: block;

            margin-bottom: 6px;

            color: var(--moss-500);

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 1.2px;
        }


        .cart-item h3 {

            margin: 0 0 10px;

            font-size: 22px;
        }


        .cart-item h3 a {

            color: var(--forest-900);

            text-decoration: none;
        }


        .cart-item h3 a:hover {
            text-decoration: underline;
        }


        /* =====================================================
           PRICE
        ===================================================== */

        .cart-price {

            font-weight: 700;

            color: var(--forest-900);

            margin-bottom: 12px;
        }


        .cart-price small {

            color: #6b7261;

            font-weight: 400;
        }


        /* =====================================================
           UPDATE QUANTITY
        ===================================================== */

        .cart-update-form {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 10px;

            margin: 15px 0;
        }


        .cart-update-form label {

            color: #6b7261;

            font-size: 13px;
        }


        .cart-quantity-input {

            width: 75px;

            height: 40px;

            padding: 0 10px;

            border:
                1px solid
                var(--forest-900);

            background: #fff;

            color: var(--forest-900);

            text-align: center;
        }


        .cart-quantity-input:focus {

            outline:
                2px solid
                var(--wheat-300);

            outline-offset: 1px;
        }


        .cart-update-btn,
        .cart-remove-btn {

            height: 40px;

            padding: 0 15px;

            border:
                1px solid
                var(--forest-900);

            font-family: inherit;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            cursor: pointer;
        }


        .cart-update-btn {

            background:
                var(--forest-900);

            color:
                var(--cream-50);
        }


        .cart-update-btn:hover {

            background:
                var(--wheat-300);

            color:
                var(--forest-950);
        }


        .cart-remove-btn {

            background: transparent;

            color:
                var(--forest-900);
        }


        .cart-remove-btn:hover {

            background:
                var(--forest-900);

            color:
                var(--cream-50);
        }


        /* =====================================================
           SUBTOTAL
        ===================================================== */

        .cart-subtotal {

            font-size: 14px;

            color: #6b7261;
        }


        .cart-subtotal strong {
            color: var(--forest-900);
        }


        .stock-note {

            display: block;

            margin-top: 8px;

            color: #777d70;

            font-size: 11px;
        }


        /* =====================================================
           ORDER SUMMARY
        ===================================================== */

        .cart-summary {

            background:
                var(--forest-900);

            color:
                var(--cream-50);

            padding: 30px;

            position: sticky;

            top: 100px;
        }


        .cart-summary .eyebrow {
            color: var(--wheat-300);
        }


        .cart-summary h2 {

            color:
                var(--cream-50);

            margin: 10px 0 30px;

            font-size: 28px;
        }


        .summary-row {

            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            padding: 12px 0;

            color:
                var(--cream-100);

            border-bottom:
                1px solid
                rgba(255, 255, 255, .12);
        }


        .summary-total {

            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            margin-top: 22px;

            font-size: 21px;

            font-weight: 700;
        }


        .summary-total strong {
            color: var(--wheat-300);
        }


        .checkout-btn {

            display: block;

            width: 100%;

            margin-top: 28px;

            box-sizing: border-box;

            text-align: center;

            text-decoration: none;
        }


        .continue-shopping {

            display: block;

            margin-top: 16px;

            color:
                var(--cream-100);

            text-align: center;

            font-size: 13px;
        }


        /* =====================================================
           EMPTY CART
        ===================================================== */

        .empty-cart {

            padding: 70px 30px;

            text-align: center;

            border:
                1px solid
                rgba(17, 55, 36, .15);
        }


        .empty-cart h2 {
            margin-bottom: 12px;
        }


        .empty-cart p {

            color: #6b7261;

            margin-bottom: 25px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .cart-layout {
                grid-template-columns: 1fr;
            }


            .cart-summary {
                position: static;
            }

        }


        @media (max-width: 600px) {

            .cart-item {
                grid-template-columns: 1fr;
            }


            .cart-item-image {

                width: 100%;

                height: 220px;
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
        id="cartLink"
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


        <span
            class="cart-count"
            id="cartCount"
        >
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
     CART
========================================================= -->

<main class="cart-page">

<div class="wrap">


    <div class="cart-heading">

        <span class="eyebrow">
            Your Marketplace
        </span>

        <h1>
            Shopping Cart
        </h1>

        <p>

            Review the agricultural products
            you've added to your cart.

        </p>

    </div>



    <!-- =====================================================
         SUCCESS / ERROR MESSAGES
    ====================================================== -->

    <?php if (
        isset($_GET['updated']) &&
        $_GET['updated'] === '1'
    ): ?>


        <div class="cart-message">

            Cart quantity updated successfully.

        </div>


    <?php endif; ?>



    <?php if (
        isset($_GET['removed']) &&
        $_GET['removed'] === '1'
    ): ?>


        <div class="cart-message">

            Product removed from your cart.

        </div>


    <?php endif; ?>



    <?php if (
        isset($_GET['error']) &&
        $_GET['error'] === 'out_of_stock'
    ): ?>


        <div class="cart-message cart-message-error">

            This product is currently out of stock.

        </div>


    <?php endif; ?>



    <?php if (!empty($cartItems)): ?>


        <div class="cart-layout">


            <!-- =================================================
                 CART ITEMS
            ================================================== -->

            <div class="cart-items">


                <?php foreach ($cartItems as $item): ?>


                    <article class="cart-item">


                        <!-- IMAGE -->

                        <a
                            href="product_details.php?id=<?= (int) $item['product_id'] ?>"
                            class="cart-item-image"
                        >

                            <img

                                src="<?= htmlspecialchars(
                                    getCartProductImage(
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

                        </a>



                        <!-- INFORMATION -->

                        <div class="cart-item-info">


                            <span class="cart-item-category">

                                <?= htmlspecialchars(
                                    $item['category_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>


                            <h3>

                                <a
                                    href="product_details.php?id=<?= (int) $item['product_id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $item['product_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </a>

                            </h3>



                            <!-- PRICE -->

                            <div class="cart-price">

                                ₱<?= number_format(
                                    (float) $item['price'],
                                    2
                                ) ?>


                                <?php if (
                                    !empty($item['unit'])
                                ): ?>


                                    <small>

                                        /
                                        <?= htmlspecialchars(
                                            $item['unit'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </small>


                                <?php endif; ?>


                            </div>



                            <!-- =================================
                                 UPDATE / REMOVE
                            ================================== -->

                            <form
                                action="update_cart.php"
                                method="POST"
                                class="cart-update-form"
                            >


                                <input
                                    type="hidden"
                                    name="cart_item_id"
                                    value="<?= (int) $item['cart_item_id'] ?>"
                                >


                                <label>
                                    Quantity
                                </label>


                                <input
                                    type="number"
                                    name="quantity"
                                    value="<?= (int) $item['cart_quantity'] ?>"
                                    min="1"
                                    max="<?= (int) $item['stock_quantity'] ?>"
                                    class="cart-quantity-input"
                                    required
                                >


                                <button
                                    type="submit"
                                    name="action"
                                    value="update"
                                    class="cart-update-btn"
                                >
                                    Update
                                </button>


                                <button
                                    type="submit"
                                    name="action"
                                    value="remove"
                                    class="cart-remove-btn"
                                    formnovalidate
                                >
                                    Remove
                                </button>


                            </form>



                            <!-- SUBTOTAL -->

                            <div class="cart-subtotal">

                                Subtotal:

                                <strong>

                                    ₱<?= number_format(
                                        (float) $item['subtotal'],
                                        2
                                    ) ?>

                                </strong>

                            </div>



                            <!-- AVAILABLE STOCK -->

                            <span class="stock-note">

                                <?= (int) $item['stock_quantity'] ?>

                                <?= htmlspecialchars(
                                    $item['unit'] ?? 'item',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                available

                            </span>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>



            <!-- =================================================
                 ORDER SUMMARY
            ================================================== -->

            <aside class="cart-summary">


                <span class="eyebrow">
                    Order Summary
                </span>


                <h2>
                    Your Cart
                </h2>



                <div class="summary-row">

                    <span>
                        Items
                    </span>

                    <strong id="summaryItemsCount">
                        <?= $totalQuantity ?>
                    </strong>

                </div>



                <div class="summary-row">

                    <span>
                        Products
                    </span>

                    <strong id="summaryProductsCount">
                        <?= count($cartItems) ?>
                    </strong>

                </div>



                <div class="summary-total">

                    <span>
                        Total
                    </span>

                    <strong id="cartTotalAmount">

                        ₱<?= number_format(
                            $cartTotal,
                            2
                        ) ?>

                    </strong>

                </div>



                <!-- CHECKOUT -->

                <a
                    href="checkout.php"
                    class="btn btn-solid checkout-btn"
                >
                    Checkout
                </a>



                <a
                    href="products.php"
                    class="continue-shopping"
                >
                    ← Continue Shopping
                </a>


            </aside>


        </div>


    <?php else: ?>


        <!-- =================================================
             EMPTY CART
        ================================================== -->

        <div class="empty-cart">


            <h2>
                Your cart is empty.
            </h2>


            <p>

                Browse the marketplace and add
                agricultural products to your cart.

            </p>


            <a
                href="products.php"
                class="btn btn-solid"
            >
                Browse Products
            </a>


        </div>


    <?php endif; ?>


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


<script src="js/cart.js"></script>
</body>

</html>


<?php

$stmt->close();

$conn->close();

?>