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

// Fetch Reviews for this product
$reviewsSql = "
    SELECT r.review_id, r.rating, r.review_text, r.created_at, u.full_name AS reviewer_name
    FROM reviews r
    INNER JOIN users u ON r.reviewer_id = u.user_id
    WHERE r.product_id = ?
    ORDER BY r.review_id DESC
";
$rStmt = $conn->prepare($reviewsSql);
$rStmt->bind_param('i', $productId);
$rStmt->execute();
$reviewsResult = $rStmt->get_result();
$productReviews = [];
$totalScore = 0;
while ($row = $reviewsResult->fetch_assoc()) {
    $productReviews[] = $row;
    $totalScore += (int)$row['rating'];
}
$rStmt->close();

$reviewCount = count($productReviews);
$avgRating = $reviewCount > 0 ? round($totalScore / $reviewCount, 1) : 0;

function getProductImage($imageUrl) {
    if (empty($imageUrl)) {
        return 'images/placeholder-product.svg';
    }
    if (strpos($imageUrl, 'assets/images/') === 0) {
        return str_replace('assets/images/', 'images/', $imageUrl);
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


            <!-- SUCCESS / REVIEWED MESSAGE -->
            <?php if (isset($_GET['reviewed']) && $_GET['reviewed'] === '1'): ?>
                <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:14px 18px; margin-bottom:20px; border-radius:2px; font-weight:500;">
                    ✓ Thank you! Your review and rating have been posted.
                </div>
            <?php endif; ?>

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

            <!-- RATING SUMMARY -->
            <div style="display:flex; align-items:center; gap:8px; margin:-8px 0 16px; flex-wrap:wrap;">
                <?php if ($reviewCount > 0): ?>
                    <span style="color:#d4b65a; font-size:18px; letter-spacing:1px;">
                        <?= str_repeat('★', (int)round($avgRating)) . str_repeat('☆', 5 - (int)round($avgRating)) ?>
                    </span>
                    <strong style="color:#122017; font-size:15px;"><?= number_format($avgRating, 1) ?></strong>
                    <a href="#customerReviews" style="color:#768047; font-size:13.5px; text-decoration:underline;">(<?= $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?>)</a>
                <?php else: ?>
                    <span style="color:#b5b3a2; font-size:16px;">★★★★★</span>
                    <span style="color:#6b6959; font-size:13px;">No reviews yet</span>
                    <a href="add_review.php?product_id=<?= (int)$product['product_id'] ?>" style="color:#768047; font-size:13px; text-decoration:underline; margin-left:4px;">Be the first to review</a>
                <?php endif; ?>
            </div>




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



            <!-- STOCK LESSENING INDICATOR -->
            <?php 
            $pStock = (int)$product['quantity'];
            $pUnit = htmlspecialchars($product['unit'] ?? 'item', ENT_QUOTES, 'UTF-8');
            ?>

            <div style="margin-bottom:20px;">
                <?php if ($pStock > 10): ?>
                    <span class="stock-status" style="background:#e0edd5; color:#23581c; font-weight:600; padding:6px 14px; border-radius:3px; display:inline-flex; align-items:center; gap:5px;">
                        ✅ <?= $pStock ?> <?= $pUnit ?> available in stock
                    </span>
                <?php elseif ($pStock > 0): ?>
                    <div style="background:#fef7e6; border:1px solid #f2dfa8; color:#854d0e; padding:10px 16px; border-radius:3px; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:8px;">
                        <span style="font-size:18px;">🔥</span>
                        <span>Lessening Stock: Only <strong><?= $pStock ?> <?= $pUnit ?></strong> remaining!</span>
                    </div>
                <?php else: ?>
                    <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:12px 18px; border-radius:3px; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:8px;">
                        <span style="font-size:20px;">⛔</span>
                        <span>Currently Out of Stock &mdash; This product is temporarily sold out.</span>
                    </div>
                <?php endif; ?>
            </div>

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

                    <?php if ($pStock > 0): ?>

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
                                max="<?= $pStock ?>"
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
                            type="button"
                            class="btn btn-solid"
                            onclick="showOutOfStockPopup('<?= htmlspecialchars(addslashes($product['product_name']), ENT_QUOTES, 'UTF-8') ?>')"
                            style="background:#8c8874; border-color:#8c8874; cursor:pointer;"
                        >
                            ⛔ Out of Stock
                        </button>

                    <?php endif; ?>

                <?php else: ?>

                    <?php if ($pStock > 0): ?>
                        <a
                            href="login.php"
                            class="btn btn-solid"
                        >
                            Login to Buy
                        </a>
                    <?php else: ?>
                        <button
                            type="button"
                            class="btn btn-solid"
                            onclick="showOutOfStockPopup('<?= htmlspecialchars(addslashes($product['product_name']), ENT_QUOTES, 'UTF-8') ?>')"
                            style="background:#8c8874; border-color:#8c8874; cursor:pointer;"
                        >
                            ⛔ Out of Stock
                        </button>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- SECONDARY ACTIONS -->
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="add_review.php?product_id=<?= (int)$product['product_id'] ?>" class="btn btn-outline-dark" style="padding:10px 18px; font-size:11px; text-decoration:none;">
                        ⭐ Rate & Review
                    </a>
                    <button type="button" class="btn btn-outline-dark" onclick="openReportModal()" style="padding:10px 18px; font-size:11px; color:#8c2e1b; border-color:#d9a99f; cursor:pointer;">
                        🚩 Report Listing
                    </button>
                </div>

            </div>

            <!-- =================================================
                 PRODUCT META
            ================================================== -->
            <div class="product-meta">
                <p>
                    Product ID: <?= (int) $product['product_id'] ?>
                </p>
                <p>
                    Category: <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p>
                    Seller: <?= htmlspecialchars($product['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

        </div>

    </div>

    <!-- =========================================================
         CUSTOMER REVIEWS & RATINGS SECTION
    ========================================================= -->
    <section id="customerReviews" style="margin-top:60px; border-top:1px solid #ded6b9; padding-top:45px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; margin-bottom:28px;">
            <div>
                <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Marketplace Feedback</span>
                <h2 style="font-family:Georgia,serif; font-size:clamp(26px, 3.5vw, 34px); color:#122017; margin:6px 0 0;">Customer Reviews & Ratings</h2>
            </div>
            <div>
                <a href="add_review.php?product_id=<?= (int)$product['product_id'] ?>" class="btn btn-solid" style="padding:12px 22px; font-size:12px; text-decoration:none;">
                    ⭐ Write a Review
                </a>
            </div>
        </div>

        <!-- Rating Summary Box -->
        <div style="background:#fff; border:1px solid #ded6b9; padding:28px; margin-bottom:28px; display:flex; align-items:center; gap:35px; flex-wrap:wrap;">
            <div style="text-align:center; min-width:140px;">
                <div style="font-family:Georgia,serif; font-size:46px; font-weight:700; color:#122017; line-height:1;">
                    <?= $reviewCount > 0 ? number_format($avgRating, 1) : '0.0' ?>
                </div>
                <div style="color:#d4b65a; font-size:18px; margin:6px 0 4px; letter-spacing:2px;">
                    <?= str_repeat('★', (int)round($avgRating)) . str_repeat('☆', 5 - (int)round($avgRating)) ?>
                </div>
                <div style="font-size:12px; color:#7d7967; font-family:monospace; text-transform:uppercase;">
                    Based on <?= $reviewCount ?> <?= $reviewCount === 1 ? 'Review' : 'Reviews' ?>
                </div>
            </div>
            <div style="flex:1; min-width:240px; border-left:1px solid #efe8d3; padding-left:28px;">
                <p style="margin:0 0 8px; font-size:14px; color:#2d382e;">
                    Verified reviews for <strong><?= htmlspecialchars($product['product_name']) ?></strong> from local buyers across AgriMart.
                </p>
                <p style="margin:0; font-size:13px; color:#768047;">
                    Have you purchased this agricultural product? Share your experience to guide fellow farmers!
                </p>
            </div>
        </div>

        <!-- Reviews List -->
        <?php if ($reviewCount > 0): ?>
            <div style="display:grid; gap:16px;">
                <?php foreach ($productReviews as $rev): ?>
                    <div style="background:#fff; border:1px solid #ded6b9; padding:22px 26px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:10px;">
                            <div>
                                <strong style="color:#122017; font-size:15px;"><?= htmlspecialchars($rev['reviewer_name']) ?></strong>
                                <span style="font-size:11px; color:#23581c; margin-left:8px; background:#e0edd5; padding:2px 8px; border-radius:2px; font-weight:600;">✓ Verified Buyer</span>
                            </div>
                            <span style="font-size:12px; color:#888; font-family:monospace;">
                                <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                            </span>
                        </div>
                        <div style="color:#d4b65a; font-size:15px; margin-bottom:8px; letter-spacing:1.5px;">
                            <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                        </div>
                        <p style="margin:0; font-size:14px; color:#3d483e; line-height:1.6;">
                            <?= nl2br(htmlspecialchars($rev['review_text'] ?: 'No written comment provided.')) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background:#fff; border:1px solid #ded6b9; padding:35px; text-align:center;">
                <p style="margin:0 0 16px; font-size:15px; color:#5e604e;">There are no reviews for this product yet.</p>
                <a href="add_review.php?product_id=<?= (int)$product['product_id'] ?>" class="btn btn-solid" style="padding:12px 24px; font-size:12px; text-decoration:none;">
                    Leave the First Review
                </a>
            </div>
        <?php endif; ?>
    </section>

</div>

</section>

<!-- =========================================================
     REPORT LISTING MODAL
========================================================= -->
<div id="reportModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:9999; justify-content:center; align-items:center; padding:20px; box-sizing:border-box;">
    <div style="background:#f5f0df; width:min(520px, 100%); padding:35px; border:1px solid #ded6b9; box-shadow:0 16px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeReportModal()" style="position:absolute; top:15px; right:15px; background:none; border:none; font-size:24px; cursor:pointer; color:#122017;">&times;</button>
        <span style="font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#8c2e1b; font-weight:700; display:block; margin-bottom:6px;">Safety & Moderation</span>
        <h2 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin:0 0 12px;">Report Product Listing</h2>
        <p style="font-size:13.5px; color:#5e604e; line-height:1.5; margin-bottom:18px;">
            Help keep AgriMart safe and trustworthy. If this listing violates agricultural marketplace guidelines, submit a report for immediate administrator review.
        </p>

        <form id="reportForm" onsubmit="submitReport(event)">
            <input type="hidden" name="report_type" value="product">
            <input type="hidden" name="item_id" value="<?= (int)$product['product_id'] ?>">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#5e604e; margin-bottom:6px; font-weight:600;">Reason for Report *</label>
                <select name="reason" required style="width:100%; padding:12px; border:1px solid #d4c79c; background:#fff; font-size:14px;">
                    <option value="fake_product">Fake / Inaccurate Produce Details</option>
                    <option value="misleading">Misleading Pricing or Quantity</option>
                    <option value="scam_fraud">Suspected Scam or Fraudulent Seller</option>
                    <option value="prohibited_item">Prohibited / Restricted Agricultural Chemical or Item</option>
                    <option value="poor_quality">Damaged / Substandard Crop Quality</option>
                    <option value="other">Other Marketplace Violation</option>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#5e604e; margin-bottom:6px; font-weight:600;">Explanation / Details *</label>
                <textarea name="description" required rows="4" style="width:100%; box-sizing:border-box; padding:12px; border:1px solid #d4c79c; background:#fff; font-size:14px;" placeholder="Please explain why you are reporting this listing..."></textarea>
            </div>

            <div id="reportFeedback" style="display:none; padding:12px; margin-bottom:16px; font-size:13.5px;"></div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeReportModal()" class="btn btn-outline-dark" style="padding:10px 20px;">Cancel</button>
                <button type="submit" id="reportSubmitBtn" class="btn" style="background:#8c2e1b; color:#fff !important; border:none; padding:10px 22px; cursor:pointer;">Submit Report</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReportModal() {
    <?php if (!$isLoggedIn): ?>
        window.location.href = 'login.php';
        return;
    <?php endif; ?>
    document.getElementById('reportModal').style.display = 'flex';
}
function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}
function submitReport(e) {
    e.preventDefault();
    const form = document.getElementById('reportForm');
    const btn = document.getElementById('reportSubmitBtn');
    const fb = document.getElementById('reportFeedback');
    const fd = new FormData(form);

    btn.disabled = true;
    btn.textContent = 'Submitting...';

    fetch('report_listing.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        fb.style.display = 'block';
        if (data.success) {
            fb.style.background = '#e0edd5';
            fb.style.color = '#23581c';
            fb.style.border = '1px solid #c5ddb4';
            fb.textContent = '✓ ' + data.message;
            form.reset();
            setTimeout(() => { closeReportModal(); fb.style.display = 'none'; btn.disabled = false; btn.textContent = 'Submit Report'; }, 2200);
        } else {
            fb.style.background = '#fae6df';
            fb.style.color = '#a54129';
            fb.style.border = '1px solid #efb7aa';
            fb.textContent = '✕ ' + data.message;
            btn.disabled = false;
            btn.textContent = 'Submit Report';
        }
    })
    .catch(err => {
        fb.style.display = 'block';
        fb.style.background = '#fae6df';
        fb.style.color = '#a54129';
        fb.textContent = '✕ Error submitting report. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Submit Report';
    });
}
</script>






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


<!-- =====================================================
     OUT OF STOCK POPUP MODAL
====================================================== -->
<div id="outOfStockModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:9999; justify-content:center; align-items:center; padding:20px; box-sizing:border-box;">
    <div style="background:#f5f0df; width:min(480px, 100%); padding:35px; border:1px solid #ded6b9; position:relative; box-shadow:0 15px 35px rgba(0,0,0,0.25); text-align:center; border-radius:4px;">
        <div style="width:60px; height:60px; border-radius:50%; background:#fae6df; color:#a54129; display:flex; align-items:center; justify-content:center; font-size:28px; margin:0 auto 18px;">⚠️</div>
        <h2 style="font-family:Georgia,serif; font-size:26px; color:#122017; margin:0 0 10px;">Out of Stock</h2>
        <p id="outOfStockMsg" style="color:#596054; line-height:1.6; font-size:15px; margin-bottom:25px;">
            We're sorry, <strong><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></strong> is currently sold out and out of stock. Please check back later or explore other harvest products from local farmers.
        </p>
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <button type="button" class="btn btn-light" onclick="closeOutOfStockPopup()" style="padding:12px 24px; cursor:pointer;">Close</button>
            <a href="products.php" class="btn btn-solid" style="padding:12px 24px; text-decoration:none;">Browse Other Products</a>
        </div>
    </div>
</div>

<script>
function showOutOfStockPopup(prodName) {
    if (prodName) {
        document.getElementById('outOfStockMsg').innerHTML = "We're sorry, <strong>" + prodName + "</strong> is currently sold out and out of stock. Please check back soon or explore other fresh harvest crops.";
    }
    document.getElementById('outOfStockModal').style.display = 'flex';
}
function closeOutOfStockPopup() {
    document.getElementById('outOfStockModal').style.display = 'none';
}

window.addEventListener('DOMContentLoaded', () => {
    <?php if ($pStock <= 0 && isset($_GET['error']) && in_array($_GET['error'], ['stock', 'out_of_stock'])): ?>
        showOutOfStockPopup('<?= htmlspecialchars(addslashes($product['product_name']), ENT_QUOTES, 'UTF-8') ?>');
    <?php endif; ?>
});
</script>

<script src="js/cart.js"></script>
</body>

</html>


<?php

$stmt->close();

$conn->close();

?>