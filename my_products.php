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
   ADMIN REDIRECT
========================================================= */

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {
    header('Location: admin_dashboard.php');
    exit;
}


/* =========================================================
   USER SESSION
========================================================= */

$userId = (int) $_SESSION['user_id'];

$fullName = $_SESSION['full_name'] ?? 'User';

$parts = explode(' ', trim($fullName));

$firstName = $parts[0] ?? 'User';

// Check User Verification Status
$uStmt = $conn->prepare("
    SELECT is_verified, face_verified
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$isVerified = ($uRow['is_verified'] ?? 'pending') === 'verified';

/* =========================================================
   GET USER PRODUCTS
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
        p.admin_deactivated,
        p.deactivation_reason,
        p.retrieval_message,
        p.retrieval_status,
        p.retrieval_requested_at,
        COALESCE(NULLIF(p.location_city, ''), a.city_municipality, 'Flora') AS location_city,
        COALESCE(NULLIF(p.location_province, ''), a.province, 'Apayao') AS location_province,
        c.category_name

    FROM products p

    INNER JOIN categories c
        ON p.category_id = c.category_id

    LEFT JOIN addresses a
        ON p.user_id = a.user_id

    WHERE p.user_id = ?
      AND p.status NOT IN ('deleted', 'archived', '')

    ORDER BY p.product_id DESC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Unable to load your product listings.');
}


$stmt->bind_param(
    'i',
    $userId
);

$stmt->execute();

$result = $stmt->get_result();


$products = [];

while ($product = $result->fetch_assoc()) {
    $products[] = $product;
}


/* =========================================================
   IMAGE HELPER
========================================================= */

function getMyProductImage($imageUrl)
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
        My Product Listings — AgriMart
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


        .dashboard-user {
            color: white;
            font-size: 14px;
        }


        .dashboard-user strong {
            color: #d4b65a;
        }


        /* =====================================================
           PAGE
        ===================================================== */

        body {
            margin: 0;

            background: #f4f0df;

            color: #162018;
        }


        .my-products-page {
            min-height: 100vh;

            padding:
                120px
                20px
                90px;
        }


        .my-products-wrap {
            width: min(1200px, 100%);

            margin: 0 auto;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-heading {
            display: flex;

            justify-content: space-between;

            align-items: flex-end;

            gap: 30px;

            margin-bottom: 40px;
        }


        .page-heading .eyebrow {
            display: block;

            margin-bottom: 10px;

            color: #768047;

            font-family: monospace;

            font-size: 12px;

            letter-spacing: 2px;

            text-transform: uppercase;
        }


        .page-heading h1 {
            margin: 0 0 12px;

            color: #122017;

            font-family:
                Georgia,
                serif;

            font-size:
                clamp(
                    38px,
                    5vw,
                    56px
                );
        }


        .page-heading p {
            max-width: 650px;

            margin: 0;

            color: #6b6a59;

            line-height: 1.7;
        }


        /* =====================================================
           MESSAGES
        ===================================================== */

        .listing-message {
            margin-bottom: 25px;

            padding: 15px 18px;

            background: #eee9d8;

            border:
                1px solid
                #d5cdb0;

            color: #173723;

            font-size: 14px;
        }


        .listing-message-success {
            background: #e8eedf;

            border-color: #cbd8bd;

            color: #173723;
        }


        .listing-message-error {
            background: #f4e4dc;

            border-color: #d9b8a8;

            color: #762f22;
        }


        /* =====================================================
           PRODUCT GRID
        ===================================================== */

        .listing-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(260px, 1fr)
                );

            gap: 24px;
        }


        /* =====================================================
           PRODUCT CARD
        ===================================================== */

        .listing-card {
            display: flex;

            flex-direction: column;

            background: white;

            border:
                1px solid
                #ded6b9;

            overflow: hidden;
        }


        .listing-image {
            position: relative;

            height: 210px;

            background: #173723;

            overflow: hidden;
        }


        .listing-image img {
            display: block;

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .listing-status {
            position: absolute;

            top: 14px;
            right: 14px;

            padding: 7px 10px;

            background: #f4f0df;

            color: #173723;

            font-family: monospace;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 1px;

            text-transform: uppercase;
        }


        .status-inactive {
            background: #ded8ca;

            color: #6c665c;
        }


        /* =====================================================
           PRODUCT INFORMATION
        ===================================================== */

        .listing-content {
            display: flex;

            flex-direction: column;

            flex: 1;

            padding: 23px;
        }


        .listing-category {
            margin-bottom: 7px;

            color: #768047;

            font-family: monospace;

            font-size: 10px;

            letter-spacing: 1.3px;

            text-transform: uppercase;
        }


        .listing-content h2 {
            margin: 0 0 10px;

            color: #172017;

            font-family:
                Georgia,
                serif;

            font-size: 23px;
        }


        .listing-description {
            margin: 0 0 20px;

            color: #74705f;

            font-size: 14px;

            line-height: 1.55;
        }


        /* =====================================================
           DETAILS
        ===================================================== */

        .listing-details {
            margin-top: auto;

            padding-top: 18px;

            border-top:
                1px solid
                #e4deca;
        }


        .listing-detail {
            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 6px 0;

            font-size: 13px;
        }


        .listing-detail span {
            color: #777363;
        }


        .listing-detail strong {
            color: #172017;
        }


        .listing-price {
            color: #173723 !important;

            font-size: 18px;
        }


        /* =====================================================
           ACTIONS
        ===================================================== */

        .listing-actions {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 8px;

            margin-top: 20px;
        }


        .listing-action {
            display: flex;

            align-items: center;

            justify-content: center;

            min-height: 42px;

            padding: 0 12px;

            box-sizing: border-box;

            border:
                1px solid
                #173723;

            background: transparent;

            color: #173723;

            text-decoration: none;

            font-family: inherit;

            font-size: 10px;

            letter-spacing: 1px;

            text-transform: uppercase;

            cursor: pointer;
        }


        .listing-action:hover {
            background: #173723;

            color: #f4f0df;
        }


        .listing-action-primary {
            background: #173723;

            color: #f4f0df;
        }


        .listing-action-primary:hover {
            background: #d4b65a;

            border-color: #d4b65a;

            color: #122017;
        }


        /* =====================================================
           ACTIVATE / DEACTIVATE
        ===================================================== */

        .status-form {
            grid-column: 1 / -1;

            margin: 0;
        }


        .status-button {
            width: 100%;
        }


        .activate-button {
            background: #d4b65a;

            border-color: #d4b65a;

            color: #122017;
        }


        .activate-button:hover {
            background: #173723;

            border-color: #173723;

            color: #f4f0df;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty-listings {
            padding: 70px 30px;

            background: white;

            border:
                1px solid
                #ded6b9;

            text-align: center;
        }


        .empty-listings h2 {
            margin: 0 0 12px;

            font-family:
                Georgia,
                serif;

            font-size: 30px;

            color: #172017;
        }


        .empty-listings p {
            max-width: 500px;

            margin: 0 auto 25px;

            color: #74705f;

            line-height: 1.6;
        }


        /* =====================================================
           BACK
        ===================================================== */

        .dashboard-back {
            display: inline-block;

            margin-top: 35px;

            color: #173723;

            font-size: 13px;

            text-decoration: none;
        }


        .dashboard-back:hover {
            text-decoration: underline;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 700px) {

            .page-heading {
                flex-direction: column;

                align-items: flex-start;
            }


            .page-heading .btn {
                width: 100%;

                text-align: center;
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


        <a
            href="dashboard.php"
            class="active"
        >
            Dashboard
        </a>

    </nav>


    <!-- ACCOUNT -->

    <div class="header-actions">

        <span class="dashboard-user">

            Hi,

            <strong>

                <?= htmlspecialchars(
                    $firstName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </span>


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
     MY PRODUCTS
========================================================= -->

<main class="my-products-page">

<div class="my-products-wrap">


    <!-- =====================================================
         PAGE HEADING
    ====================================================== -->

    <div class="page-heading">


        <div>

            <span class="eyebrow">
                Seller Marketplace
            </span>


            <h1>
                My Product Listings
            </h1>


            <p>
                View and manage the agricultural products
                you've listed on the AgriMart marketplace.
            </p>

        </div>


        <a
            href="add_product.php"
            class="btn btn-solid"
        >
            + Add Product
        </a>


    </div>

    <?php if (!$isVerified): ?>
        <!-- VERIFICATION GATE -->
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:20px 24px; margin-bottom:25px; border-radius:4px;">
            <div style="display:flex; align-items:flex-start; gap:14px;">
                <svg viewBox="0 0 24 24" style="width:26px; height:26px; stroke:#a54129; fill:none; stroke-width:2; flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <div>
                    <strong style="font-size:15px; display:block; margin-bottom:4px;">Seller Identity Verification Required</strong>
                    <p style="margin:0 0 10px; font-size:13px; line-height:1.5; color:#6b3527;">
                        To publish and activate seed or crop listings, you must complete Government ID and live Face Biometrics verification.
                    </p>
                    <a href="profile.php#verification" class="btn btn-solid" style="padding:6px 16px; font-size:12px; background:#a54129; border-color:#a54129; color:#fff; text-decoration:none; display:inline-block;">
                        Complete Profile & Face Verification Now →
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         ACTIVATED MESSAGE
    ====================================================== -->

    <?php if (
        isset($_GET['activated']) &&
        $_GET['activated'] === '1'
    ): ?>

        <div
            class="
                listing-message
                listing-message-success
            "
        >
            Product listing activated successfully.
        </div>

    <?php endif; ?>



    <!-- =====================================================
         DEACTIVATED MESSAGE
    ====================================================== -->

    <?php if (
        isset($_GET['deactivated']) &&
        $_GET['deactivated'] === '1'
    ): ?>

        <div
            class="
                listing-message
                listing-message-success
            "
        >
            Product listing deactivated successfully.
        </div>

    <?php endif; ?>



    <!-- =====================================================
         DELETED MESSAGE
    ====================================================== -->

    <?php if (
        isset($_GET['deleted']) &&
        $_GET['deleted'] === '1'
    ): ?>

        <div
            class="
                listing-message
                listing-message-success
            "
        >
            Product listing removed from your store successfully.
        </div>

    <?php endif; ?>



    <!-- =====================================================
         ERROR MESSAGE
    ====================================================== -->

    <?php if (
        isset($_GET['error'])
    ): ?>

        <div
            class="
                listing-message
                listing-message-error
            "
        >
            <?php if ($_GET['error'] === 'verification_required'): ?>
                Account Verification Required: You must complete Government ID and Face Biometrics verification before activating products for sale.
            <?php elseif ($_GET['error'] === 'admin_locked'): ?>
                🛑 <strong>Locked by Administration:</strong> This listing was deactivated by an administrator following report findings. Only an admin can reactivate it. Please submit a retrieval appeal below.
            <?php else: ?>
                Unable to update the product listing.
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['appeal_submitted']) && $_GET['appeal_submitted'] === '1'): ?>
        <div class="listing-message listing-message-success" style="background:#e8eedf; border:1px solid #cbd8bd; color:#173723;">
            📩 <strong>Retrieval Appeal Submitted!</strong> Your request and message have been sent to AgriMart Administration for review. You will receive an in-app and SMS alert once decided.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['appeal_error'])): ?>
        <div class="listing-message listing-message-error">
            <?php if ($_GET['appeal_error'] === 'too_short' || $_GET['appeal_error'] === 'empty'): ?>
                Please provide a detailed explanation (at least 10 characters) explaining why your listing should be reactivated.
            <?php else: ?>
                Unable to submit retrieval appeal. Please try again.
            <?php endif; ?>
        </div>
    <?php endif; ?>



    <?php if (!empty($products)): ?>


        <!-- =================================================
             PRODUCT GRID
        ================================================== -->

        <div class="listing-grid">


            <?php foreach ($products as $product): ?>


                <article class="listing-card">


                    <!-- IMAGE -->

                    <div class="listing-image">


                        <img
                            src="<?= htmlspecialchars(
                                getMyProductImage(
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


                        <!-- STATUS -->

                        <?php if (!empty($product['admin_deactivated'])): ?>
                            <span class="listing-status" style="background:#991b1b; color:#fff; font-weight:700;">
                                DEACTIVATED BY ADMIN
                            </span>
                        <?php else: ?>
                            <span
                                class="
                                    listing-status

                                    <?=
                                        $product['status']
                                        !== 'active'
                                            ? 'status-inactive'
                                            : ''
                                    ?>
                                "
                            >

                                <?= htmlspecialchars(
                                    strtoupper(
                                        $product['status']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>
                        <?php endif; ?>


                    </div>



                    <!-- =================================================
                         PRODUCT CONTENT
                    ================================================== -->

                    <div class="listing-content">


                        <span class="listing-category">

                            <?= htmlspecialchars(
                                $product['category_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <h2>

                            <?= htmlspecialchars(
                                $product['product_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h2>

                        <?php if (!empty($product['admin_deactivated'])): ?>
                            <div style="margin: 10px 0; padding: 12px; background: #fff5f5; border: 1px solid #fecaca; border-radius: 6px; font-size: 12.5px; text-align: left;">
                                <div style="font-weight: 700; color: #991b1b; display: flex; align-items: center; gap: 6px;">
                                    🛑 Deactivated by Administration
                                </div>
                                <?php if (!empty($product['deactivation_reason'])): ?>
                                    <div style="margin: 6px 0; color: #7f1d1d; font-size: 12px; line-height: 1.4;">
                                        <strong>Reason:</strong> <?= htmlspecialchars($product['deactivation_reason']) ?>
                                    </div>
                                <?php endif; ?>
                                <div style="color: #6b7280; font-size: 11px; margin-top: 4px;">
                                    ⚠️ Only an administrator can reactivate this listing. Submit a retrieval appeal below.
                                </div>
                                <?php if (($product['retrieval_status'] ?? '') === 'pending'): ?>
                                    <div style="margin-top: 8px; padding: 8px 10px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; color: #92400e; font-size: 12px;">
                                        <strong>⏳ Retrieval Appeal Under Review</strong>
                                        <div style="font-size: 11px; color: #78350f; margin-top: 3px;">
                                            Submitted: <?= !empty($product['retrieval_requested_at']) ? date('M d, Y h:i A', strtotime($product['retrieval_requested_at'])) : 'Recently' ?>
                                        </div>
                                        <div style="margin-top: 4px; font-style: italic; color: #451a03; background: #fff; padding: 6px; border: 1px dashed #fcd34d; font-size: 11.5px;">
                                            "<?= nl2br(htmlspecialchars($product['retrieval_message'])) ?>"
                                        </div>
                                    </div>
                                <?php elseif (($product['retrieval_status'] ?? '') === 'rejected'): ?>
                                    <div style="margin-top: 8px; padding: 8px 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; color: #991b1b; font-size: 12px;">
                                        <strong>❌ Previous Appeal Declined</strong>
                                        <div style="font-size: 11px; margin-top: 2px;">You can update and resubmit your appeal below with further details.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>



                        <?php if (
                            !empty(
                                $product['description']
                            )
                        ): ?>

                            <p class="listing-description">

                                <?= htmlspecialchars(
                                    $product['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                        <?php endif; ?>



                        <!-- =================================================
                             PRODUCT DETAILS
                        ================================================== -->

                        <div class="listing-details">


                            <!-- PRICE -->

                            <div class="listing-detail">

                                <span>
                                    Price
                                </span>


                                <strong class="listing-price">

                                    ₱<?= number_format(
                                        (float) $product['price'],
                                        2
                                    ) ?>

                                </strong>

                            </div>



                            <!-- STOCK -->

                            <div class="listing-detail">

                                <span>
                                    Stock
                                </span>


                                <?php if ((int)$product['quantity'] <= 0): ?>
                                    <strong style="color:#a54129; font-weight:700;">
                                        0 <?= htmlspecialchars($product['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?> (Out of Stock)
                                    </strong>
                                <?php elseif ((int)$product['quantity'] <= 5): ?>
                                    <strong style="color:#b26b00; font-weight:700;">
                                        <?= (int) $product['quantity'] ?> <?= htmlspecialchars($product['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?> (Low Stock)
                                    </strong>
                                <?php else: ?>
                                    <strong>
                                        <?= (int) $product['quantity'] ?> <?= htmlspecialchars($product['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                <?php endif; ?>
                            </div>

                            <!-- LOCATION -->
                            <div class="listing-detail" style="grid-column: 1 / -1;">
                                <span>
                                    Farm / Pickup Location
                                </span>
                                <strong style="font-size:12.5px; font-weight:600; color:#23581c; display:flex; align-items:center; gap:4px;">
                                    📍 <?= htmlspecialchars($product['location_city'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($product['location_province'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>

                        </div>



                        <!-- =================================================
                             ACTION BUTTONS
                        ================================================== -->

                        <div class="listing-actions">


                            <!-- VIEW -->

                            <a
                                href="product_details.php?id=<?= (int) $product['product_id'] ?>"
                                class="listing-action"
                            >
                                View
                            </a>



                            <!-- EDIT -->

                            <a
                                href="edit_product.php?id=<?= (int) $product['product_id'] ?>"
                                class="
                                    listing-action
                                    listing-action-primary
                                "
                            >
                                Edit
                            </a>



                            <!-- ACTIVATE / DEACTIVATE / RETRIEVAL APPEAL -->

                            <?php if (!empty($product['admin_deactivated'])): ?>
                                <button
                                    type="button"
                                    onclick="openRetrievalModal('product', <?= (int)$product['product_id'] ?>, '<?= addslashes(htmlspecialchars($product['product_name'])) ?>', '<?= addslashes(htmlspecialchars($product['retrieval_message'] ?? '')) ?>', '<?= $product['retrieval_status'] ?? 'none' ?>')"
                                    class="listing-action"
                                    style="background:#173723; color:#fff; font-weight:700; border:1px solid #173723;"
                                >
                                    <?= ($product['retrieval_status'] ?? '') === 'pending' ? '✏️ Appeal' : '📨 Appeal' ?>
                                </button>
                            <?php else: ?>
                                <form
                                    action="toggle_product_status.php"
                                    method="POST"
                                    class="status-form"
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to <?= $product['status'] === 'active'
                                                ? 'deactivate'
                                                : 'activate' ?> this product listing?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= (int) $product['product_id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            listing-action
                                            status-button

                                            <?=
                                                $product['status']
                                                !== 'active'
                                                    ? 'activate-button'
                                                    : ''
                                            ?>
                                        "
                                    >

                                        <?php if (
                                            $product['status']
                                            === 'active'
                                        ): ?>

                                            Deactivate

                                        <?php else: ?>

                                            Activate

                                        <?php endif; ?>

                                    </button>


                                </form>
                            <?php endif; ?>


                            <!-- REMOVE / DELETE LISTING -->

                            <form
                                action="delete_product.php"
                                method="POST"
                                class="status-form"

                                onsubmit="
                                    return confirm(
                                        'Are you sure you want to permanently remove <?= addslashes(htmlspecialchars($product['product_name'])) ?> from your product listings?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $product['product_id'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="listing-action remove-listing-button"
                                    style="background:#fae6df; color:#a54129; border:1px solid #efb7aa; font-weight:700; cursor:pointer;"
                                >

                                    <?= (int)$product['quantity'] <= 0 ? '✕ Remove (Out of Stock)' : '✕ Remove' ?>

                                </button>


                            </form>


                        </div>


                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <!-- =================================================
             EMPTY PRODUCTS
        ================================================== -->

        <div class="empty-listings">


            <h2>
                You haven't listed any products yet.
            </h2>


            <p>
                Add your first agricultural product
                and it will appear here for you to manage.
            </p>


            <a
                href="add_product.php"
                class="btn btn-solid"
            >
                Add Your First Product
            </a>


        </div>


    <?php endif; ?>



    <!-- BACK -->

    <a
        href="dashboard.php"
        class="dashboard-back"
    >
        ← Back to Dashboard
    </a>


</div>

</main>



<?php

$stmt->close();

$conn->close();

?>


<!-- RETRIEVAL APPEAL MODAL -->
<div id="retrievalModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(3px);">
    <div style="background:#fff; width:100%; max-width:540px; padding:30px; box-sizing:border-box; border:1px solid #d4c79c; box-shadow:0 14px 40px rgba(0,0,0,0.25); border-radius:6px; animation:modalPop 0.2s ease-out;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
            <div>
                <span style="font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#991b1b; font-weight:700;">Reinstatement Request</span>
                <h3 style="margin:4px 0 0; font-family:Georgia,serif; font-size:22px; color:#122017;">Send Retrieval Message to Admin</h3>
            </div>
            <button type="button" onclick="closeRetrievalModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#777; line-height:1;">&times;</button>
        </div>
        <p style="font-size:13px; color:#555; line-height:1.5; margin-bottom:18px;">
            Your listing for <strong id="modalItemName" style="color:#122017;"></strong> was deactivated by an administrator. Submit an explanation or proof of resolution below. Only an administrator can reactivate this listing.
        </p>

        <form action="submit_retrieval_request.php" method="POST">
            <input type="hidden" name="item_type" id="modalItemType" value="product">
            <input type="hidden" name="item_id" id="modalItemId" value="0">

            <div style="margin-bottom:18px;">
                <label style="display:block; font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#5e604e; margin-bottom:6px; font-weight:600;">
                    Retrieval Message & Corrective Steps *
                </label>
                <textarea name="retrieval_message" id="modalRetrievalMsg" required rows="5" minlength="10" style="width:100%; box-sizing:border-box; padding:12px; border:1px solid #d4c79c; font-family:inherit; font-size:13.5px; line-height:1.5; border-radius:3px;" placeholder="Explain why your product complies with guidelines, actions taken to address report concerns, or provide verification details..."></textarea>
                <small style="color:#777; font-size:11px; display:block; margin-top:4px;">Minimum 10 characters. Administration will review this appeal and notify you.</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRetrievalModal()" class="btn" style="background:#eee; color:#333; border:none; padding:10px 18px; cursor:pointer; font-size:13px;">Cancel</button>
                <button type="submit" class="btn btn-solid" style="background:#173723; color:#fff; border:none; padding:10px 22px; cursor:pointer; font-size:13px; font-weight:700;">
                    Submit Appeal to Admin →
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRetrievalModal(type, id, name, existingMsg, status) {
    document.getElementById('modalItemType').value = type;
    document.getElementById('modalItemId').value = id;
    document.getElementById('modalItemName').textContent = name;
    document.getElementById('modalRetrievalMsg').value = existingMsg || '';
    document.getElementById('retrievalModal').style.display = 'flex';
}
function closeRetrievalModal() {
    document.getElementById('retrievalModal').style.display = 'none';
}
// Close on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('retrievalModal');
    if (e.target === modal) {
        closeRetrievalModal();
    }
});
</script>

</body>

</html>