<?php

session_start();

require_once 'config.php';


/* =========================================================
   SESSION
========================================================= */

$isLoggedIn = isset($_SESSION['user_id']);

$fullName = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? '';

$firstName = '';

if ($fullName !== '') {
    $parts = explode(' ', trim($fullName));
    $firstName = $parts[0];
}


/* =========================================================
   PRODUCT ID
========================================================= */

$productId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($productId <= 0) {
    header('Location: products.php');
    exit;
}


/* =========================================================
   GET PRODUCT
========================================================= */

$sql = "

    SELECT

        p.product_id,
        p.product_name,
        p.description,
        p.price,
        p.quantity,
        p.unit,
        p.image_url,
        p.status,
        p.created_at,

        c.category_name,

        u.user_id AS seller_id,
        u.full_name AS seller_name

    FROM products p

    INNER JOIN categories c
        ON p.category_id = c.category_id

    INNER JOIN users u
        ON p.user_id = u.user_id

    WHERE p.product_id = ?
      AND p.status = 'active'
      AND u.status = 'active'

    LIMIT 1

";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Unable to load product.');
}


$stmt->bind_param(
    'i',
    $productId
);


$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();


if (!$product) {
    header('Location: products.php');
    exit;
}


/* =========================================================
   IMAGE HELPER
========================================================= */

function getProductImage($imageUrl)
{

    if (empty($imageUrl)) {
        return 'images/placeholder-product.svg';
    }


    if (strpos($imageUrl, 'assets/images/') === 0) {

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
        <?= htmlspecialchars(
            $product['product_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        — AgriMart
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <style>

        /* =====================================================
           NAVBAR
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
           PRODUCT DETAILS
        ===================================================== */

        .product-detail-section {
            padding: 90px 0;
        }


        .product-detail-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr);

            gap: 70px;

            align-items: start;

        }


        /* =====================================================
           IMAGE
        ===================================================== */

        .product-detail-image {

            background: var(--forest-900);

            min-height: 520px;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

        }


        .product-detail-image img {

            width: 100%;

            height: 520px;

            object-fit: cover;

        }


        /* =====================================================
           PRODUCT INFORMATION
        ===================================================== */

        .product-detail-info .eyebrow {
            color: var(--moss-500);
        }


        .product-detail-info h1 {

            margin-top: 14px;

            margin-bottom: 18px;

            font-size: clamp(
                36px,
                5vw,
                58px
            );

        }


        .seller-name {

            color: #6b7261;

            margin-bottom: 28px;

            font-size: 14px;

        }


        /* =====================================================
           PRICE
        ===================================================== */

        .detail-price {

            font-size: 32px;

            font-weight: 700;

            color: var(--forest-900);

            margin-bottom: 8px;

        }


        .detail-price small {

            font-size: 15px;

            font-weight: 400;

            color: #6b7261;

        }


        /* =====================================================
           STOCK
        ===================================================== */

        .stock-status {

            display: inline-block;

            margin: 12px 0 30px;

            padding: 8px 12px;

            background: #e8eedf;

            color: var(--forest-800);

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 1px;

        }


        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .product-description {

            border-top:
                1px solid
                rgba(17, 55, 36, .15);

            padding-top: 28px;

            color: #596054;

            line-height: 1.8;

            margin-bottom: 32px;

        }


        /* =====================================================
           SUCCESS MESSAGE
        ===================================================== */

        .success-message {

            padding: 14px 18px;

            margin-bottom: 22px;

            background: #e8eedf;

            border: 1px solid #cbd8bd;

            color: var(--forest-900);

            font-size: 14px;

        }


        /* =====================================================
           ACTION BUTTONS
        ===================================================== */

        .detail-actions {

            display: flex;

            gap: 12px;

            flex-wrap: wrap;

            align-items: center;

        }


        .btn-outline-dark {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 13px 20px;

            background: transparent;

            color: var(--forest-900);

            border: 1px solid var(--forest-900);

            text-decoration: none;

            transition:
                background 0.2s ease,
                color 0.2s ease;

        }


        .btn-outline-dark:hover {

            background: var(--forest-900);

            color: var(--cream-50);

        }


        /* =====================================================
           ADD TO CART
        ===================================================== */

        .add-cart-form {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .quantity-input {

            width: 80px;

            height: 48px;

            padding: 0 12px;

            border: 1px solid var(--forest-900);

            background: #fff;

            color: var(--forest-900);

            font-size: 15px;

            text-align: center;

        }


        .quantity-input:focus {

            outline: 2px solid var(--wheat-300);

            outline-offset: 2px;

        }


        /* =====================================================
           PRODUCT META
        ===================================================== */

        .product-meta {

            margin-top: 40px;

            border-top:
                1px solid
                rgba(17, 55, 36, .15);

            padding-top: 25px;

        }


        .product-meta p {

            margin: 8px 0;

            color: #6b7261;

            font-size: 13px;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .product-detail-grid {
                grid-template-columns: 1fr;
            }


            .product-detail-image {
                min-height: 380px;
            }


            .product-detail-image img {
                height: 380px;
            }


            .detail-actions {
                flex-direction: column;
                align-items: stretch;
            }


            .add-cart-form {
                width: 100%;
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


        <a
            href="products.php"
            class="active"
        >
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
            0
        </span>

    </a>


    <!-- ACCOUNT -->

    <div class="header-actions">


        <?php if ($isLoggedIn): ?>


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


        <?php else: ?>


            <a
                href="login.php"
                class="btn btn-light"
            >
                Login
            </a>


            <a
                href="register.php"
                class="btn btn-solid"
            >
                Join AgriMart
            </a>


        <?php endif; ?>


    </div>


</div>

</header>



<!-- =========================================================
     PRODUCT DETAILS
========================================================= -->

<section class="product-detail-section">

<div class="wrap">


    <div class="product-detail-grid">


        <!-- =================================================
             PRODUCT IMAGE
        ================================================== -->

        <div class="product-detail-image">


            <img

                src="<?= htmlspecialchars(
                    getProductImage(
                        $product['image_url']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

                alt="<?= htmlspecialchars(
                    $product['product_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

            >


        </div>



        <!-- =================================================
             PRODUCT INFORMATION
        ================================================== -->

        <div class="product-detail-info">


            <!-- SUCCESS MESSAGE -->

            <?php if (
                isset($_GET['added']) &&
                $_GET['added'] === '1'
            ): ?>


                <div class="success-message">

                    Product added to cart successfully.

                </div>


            <?php endif; ?>



            <!-- CATEGORY -->

            <span class="eyebrow">

                <?= htmlspecialchars(
                    $product['category_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>



            <!-- PRODUCT NAME -->

            <h1>

                <?= htmlspecialchars(
                    $product['product_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h1>



            <!-- SELLER -->

            <p class="seller-name">

                Sold by

                <strong>

                    <?= htmlspecialchars(
                        $product['seller_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </strong>

            </p>



            <!-- PRICE -->

            <div class="detail-price">

                ₱<?= number_format(
                    (float) $product['price'],
                    2
                ) ?>


                <?php if (
                    !empty($product['unit'])
                ): ?>


                    <small>

                        /
                        <?= htmlspecialchars(
                            $product['unit'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </small>


                <?php endif; ?>


            </div>



            <!-- STOCK -->

            <span class="stock-status">

                <?= (int) $product['quantity'] ?>

                <?= htmlspecialchars(
                    $product['unit'] ?? 'item',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                available

            </span>



            <!-- DESCRIPTION -->

            <div class="product-description">

                <?= nl2br(
                    htmlspecialchars(
                        $product['description']
                            ?? 'No description available.',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>

            </div>



            <!-- =================================================
                 ACTIONS
            ================================================== -->

            <div class="detail-actions">


                <!-- BACK -->

                <a
                    href="products.php"
                    class="btn btn-outline-dark"
                >
                    ← Back to Products
                </a>



                <!-- ADD TO CART -->

                <?php if ($isLoggedIn): ?>


                    <?php if (
                        (int) $product['quantity'] > 0
                    ): ?>


                        <form
                            action="add_to_cart.php"
                            method="POST"
                            class="add-cart-form"
                        >


                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['product_id'] ?>"
                            >


                            <input
                                type="number"
                                name="quantity"
                                value="1"
                                min="1"
                                max="<?= (int) $product['quantity'] ?>"
                                class="quantity-input"
                                required
                            >


                            <button
                                type="submit"
                                class="btn btn-solid"
                            >
                                Add to Cart
                            </button>


                        </form>


                    <?php else: ?>


                        <button
                            class="btn btn-solid"
                            disabled
                        >
                            Out of Stock
                        </button>


                    <?php endif; ?>


                <?php else: ?>


                    <a
                        href="login.php"
                        class="btn btn-solid"
                    >
                        Login to Buy
                    </a>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 PRODUCT META
            ================================================== -->

            <div class="product-meta">


                <p>

                    Product ID:

                    <?= (int) $product['product_id'] ?>

                </p>


                <p>

                    Category:

                    <?= htmlspecialchars(
                        $product['category_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>


                <p>

                    Seller:

                    <?= htmlspecialchars(
                        $product['seller_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>


            </div>


        </div>


    </div>


</div>

</section>



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