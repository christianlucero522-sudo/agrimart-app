<?php

session_start();

require_once 'config.php';


/* =========================================================
   SESSION
========================================================= */

$isLoggedIn = isset($_SESSION['user_id']);

$userId = $isLoggedIn
    ? (int) $_SESSION['user_id']
    : 0;

$fullName = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? '';

$firstName = '';

if ($fullName !== '') {

    $parts = explode(' ', trim($fullName));

    $firstName = $parts[0];
}


/* =========================================================
   GET EQUIPMENT ID
========================================================= */

$equipmentId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($equipmentId <= 0) {

    header('Location: equipment.php');
    exit;
}


// Fetch Renter Address for Nearest Location Indicator
$userCity = '';
$userProvince = '';
if ($isLoggedIn) {
    $uAddrStmt = $conn->prepare("SELECT city_municipality, province FROM addresses WHERE user_id = ? LIMIT 1");
    if ($uAddrStmt) {
        $uAddrStmt->bind_param('i', $userId);
        $uAddrStmt->execute();
        $uAddrRow = $uAddrStmt->get_result()->fetch_assoc();
        if ($uAddrRow) {
            $userCity = trim($uAddrRow['city_municipality'] ?? '');
            $userProvince = trim($uAddrRow['province'] ?? '');
        }
        $uAddrStmt->close();
    }
}

/* =========================================================
   GET EQUIPMENT
========================================================= */

$sql = "
    SELECT
        e.equipment_id,
        e.user_id AS owner_id,
        e.equipment_name,
        e.description,
        e.brand,
        e.model,
        e.rate_type,
        e.rate_price,
        e.availability,
        e.image_url,
        e.status,
        COALESCE(NULLIF(e.location_city, ''), a.city_municipality, 'San Fernando') AS location_city,
        COALESCE(NULLIF(e.location_province, ''), a.province, 'Pampanga') AS location_province,
        a.barangay AS owner_barangay,
        c.category_id,
        c.category_name,
        u.full_name AS owner_name,
        u.phone AS owner_phone
    FROM equipment e
    INNER JOIN categories c ON e.category_id = c.category_id
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN addresses a ON u.user_id = a.user_id
    WHERE e.equipment_id = ?
      AND e.status = 'active'
      AND c.category_type = 'equipment'
      AND u.status = 'active'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Unable to load equipment.');
}

$stmt->bind_param(
    'i',
    $equipmentId
);

$stmt->execute();
$result = $stmt->get_result();
$equipment = $result->fetch_assoc();

if (!$equipment) {
    $stmt->close();
    $conn->close();
    header('Location: equipment.php');
    exit;
}

$isNearestCity = !empty($userCity) && (stripos($equipment['location_city'], $userCity) !== false || stripos($userCity, $equipment['location_city']) !== false);
$isNearbyProv = !empty($userProvince) && (stripos($equipment['location_province'], $userProvince) !== false);

// Fetch Reviews for this equipment
$reviewsSql = "
    SELECT r.review_id, r.rating, r.review_text, r.created_at, u.full_name AS reviewer_name
    FROM reviews r
    INNER JOIN users u ON r.reviewer_id = u.user_id
    WHERE r.equipment_id = ?
    ORDER BY r.review_id DESC
";
$rStmt = $conn->prepare($reviewsSql);
$rStmt->bind_param('i', $equipmentId);
$rStmt->execute();
$reviewsResult = $rStmt->get_result();
$equipmentReviews = [];
$totalScore = 0;
while ($row = $reviewsResult->fetch_assoc()) {
    $equipmentReviews[] = $row;
    $totalScore += (int)$row['rating'];
}
$rStmt->close();

$reviewCount = count($equipmentReviews);
$avgRating = $reviewCount > 0 ? round($totalScore / $reviewCount, 1) : 0;

/* =========================================================
   IMAGE HELPER
========================================================= */

function getEquipmentDetailImage($imageUrl)
{

    if (empty($imageUrl)) {
        return 'images/placeholder-equipment.svg';
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


/* =========================================================
   RATE LABEL
========================================================= */

$rateLabel = '';

switch ($equipment['rate_type']) {

    case 'hourly':
        $rateLabel = 'hour';
        break;

    case 'daily':
        $rateLabel = 'day';
        break;

    default:
        $rateLabel = $equipment['rate_type'];
        break;
}


/* =========================================================
   AVAILABILITY
========================================================= */

$isAvailable =
    $equipment['availability'] === 'available';


/* =========================================================
   OWNER CHECK
========================================================= */

$isOwner =
    $isLoggedIn &&
    $userId === (int) $equipment['owner_id'];

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
            $equipment['equipment_name'],
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
           HEADER
        ===================================================== */

        .site-header {
            background: var(--forest-950) !important;
            background-image: none !important;
        }


        .user-chip {

            color:
                var(--cream-50, #f6f1df);

            font-size: 14px;

            margin-right: 6px;

            white-space: nowrap;
        }


        .user-chip strong {
            color: var(--wheat-300, #d6b95f);
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .equipment-detail-page {
            padding: 70px 0 100px;
        }


        .back-link {

            display: inline-block;

            margin-bottom: 30px;

            color: var(--forest-900);

            font-size: 13px;

            font-weight: 600;

            text-decoration: none;
        }


        .back-link:hover {
            text-decoration: underline;
        }


        /* =====================================================
           DETAIL LAYOUT
        ===================================================== */

        .equipment-detail-layout {

            display: grid;

            grid-template-columns:
                minmax(0, 1.1fr)
                minmax(360px, .9fr);

            gap: 55px;

            align-items: start;
        }


        /* =====================================================
           IMAGE
        ===================================================== */

        .equipment-detail-image {

            position: relative;

            min-height: 520px;

            background: var(--forest-900);

            overflow: hidden;
        }


        .equipment-detail-image img {

            display: block;

            width: 100%;

            height: 520px;

            object-fit: cover;
        }


        .category-badge {

            position: absolute;

            top: 20px;

            left: 20px;

            padding: 9px 13px;

            background: var(--wheat-300);

            color: var(--forest-950);

            font-size: 10px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 1.1px;
        }


        .availability-badge {

            position: absolute;

            top: 20px;

            right: 20px;

            padding: 9px 13px;

            font-size: 10px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 1.1px;
        }


        .availability-available {

            background: #e8eedf;

            color: var(--forest-900);
        }


        .availability-rented {

            background: #efe2b7;

            color: #6e5817;
        }


        .availability-maintenance {

            background: #f1deda;

            color: #7b3429;
        }


        /* =====================================================
           INFORMATION
        ===================================================== */

        .equipment-detail-info {
            padding-top: 10px;
        }


        .equipment-detail-info .eyebrow {
            color: var(--moss-500);
        }


        .equipment-detail-info h1 {

            margin: 10px 0 14px;

            color: var(--forest-900);

            font-size:
                clamp(36px, 5vw, 54px);

            line-height: 1.05;
        }


        .brand-model {

            margin-bottom: 25px;

            color: #6b7261;

            font-size: 15px;
        }


        /* =====================================================
           RATE
        ===================================================== */

        .rental-rate {

            margin: 25px 0;

            padding: 24px 0;

            border-top:
                1px solid
                rgba(17,55,36,.15);

            border-bottom:
                1px solid
                rgba(17,55,36,.15);
        }


        .rate-label {

            display: block;

            margin-bottom: 5px;

            color: #6b7261;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;
        }


        .rate-price {

            color: var(--forest-900);

            font-size: 34px;

            font-weight: 700;
        }


        .rate-price small {

            color: #6b7261;

            font-size: 14px;

            font-weight: 400;
        }


        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .description-section {
            margin-top: 28px;
        }


        .description-section h3 {

            margin-bottom: 10px;

            color: var(--forest-900);

            font-size: 17px;
        }


        .description-section p {

            color: #62695e;

            font-size: 14px;

            line-height: 1.8;
        }


        /* =====================================================
           EQUIPMENT INFORMATION
        ===================================================== */

        .equipment-meta {

            margin-top: 30px;

            border-top:
                1px solid
                rgba(17,55,36,.15);
        }


        .meta-row {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 14px 0;

            border-bottom:
                1px solid
                rgba(17,55,36,.10);

            font-size: 13px;
        }


        .meta-row span {
            color: #6b7261;
        }


        .meta-row strong {

            color: var(--forest-900);

            text-align: right;
        }


        /* =====================================================
           OWNER
        ===================================================== */

        .owner-box {

            margin-top: 30px;

            padding: 22px;

            background: #f1eee2;
        }


        .owner-box .eyebrow {

            display: block;

            margin-bottom: 7px;
        }


        .owner-box strong {

            display: block;

            color: var(--forest-900);

            font-size: 18px;
        }


        /* =====================================================
           RENT ACTION
        ===================================================== */

        .rent-area {
            margin-top: 30px;
        }


        .rent-btn {

            display: block;

            width: 100%;

            box-sizing: border-box;

            text-align: center;

            text-decoration: none;
        }


        .rent-disabled {

            width: 100%;

            opacity: .55;

            cursor: not-allowed;
        }


        .rent-message {

            margin-top: 12px;

            color: #6b7261;

            font-size: 12px;

            line-height: 1.6;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .equipment-detail-layout {
                grid-template-columns: 1fr;
            }

            .equipment-detail-image,
            .equipment-detail-image img {
                min-height: 400px;
                height: 400px;
            }

        }


        @media (max-width: 600px) {

            .equipment-detail-page {
                padding-top: 40px;
            }


            .equipment-detail-image,
            .equipment-detail-image img {
                min-height: 300px;
                height: 300px;
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

        <a
            href="equipment.php"
            class="active"
        >
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
     EQUIPMENT DETAILS
========================================================= -->

<main class="equipment-detail-page">

<div class="wrap">


    <!-- BACK -->

    <a
        href="equipment.php"
        class="back-link"
    >
        ← Back to Equipment
    </a>



    <div class="equipment-detail-layout">


        <!-- =================================================
             IMAGE
        ================================================== -->

        <div class="equipment-detail-image">


            <img

                src="<?= htmlspecialchars(
                    getEquipmentDetailImage(
                        $equipment['image_url']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

                alt="<?= htmlspecialchars(
                    $equipment['equipment_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

            >


            <!-- CATEGORY -->

            <span class="category-badge">

                <?= htmlspecialchars(
                    $equipment['category_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>


            <!-- AVAILABILITY -->

            <span
                class="availability-badge availability-<?= htmlspecialchars(
                    $equipment['availability'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

                <?= htmlspecialchars(
                    ucfirst(
                        $equipment['availability']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>


        </div>



        <!-- =================================================
             INFORMATION
        ================================================== -->

        <div class="equipment-detail-info">

            <!-- REVIEWED ALERT -->
            <?php if (isset($_GET['reviewed']) && $_GET['reviewed'] === '1'): ?>
                <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:14px 18px; margin-bottom:20px; border-radius:2px; font-weight:500;">
                    ✓ Thank you! Your review and equipment rating have been posted.
                </div>
            <?php endif; ?>

            <span class="eyebrow">
                Equipment Rental
            </span>

            <h1>
                <?= htmlspecialchars(
                    $equipment['equipment_name'],
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
                    <a href="#equipmentReviews" style="color:#768047; font-size:13.5px; text-decoration:underline;">(<?= $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?>)</a>
                <?php else: ?>
                    <span style="color:#b5b3a2; font-size:16px;">★★★★★</span>
                    <span style="color:#6b6959; font-size:13px;">No reviews yet</span>
                    <a href="add_review.php?equipment_id=<?= (int)$equipment['equipment_id'] ?>" style="color:#768047; font-size:13px; text-decoration:underline; margin-left:4px;">Be the first to review</a>
                <?php endif; ?>
            </div>




            <!-- BRAND / MODEL -->

            <?php if (
                !empty($equipment['brand']) ||
                !empty($equipment['model'])
            ): ?>


                <div class="brand-model">


                    <?php if (
                        !empty($equipment['brand'])
                    ): ?>

                        <?= htmlspecialchars(
                            $equipment['brand'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php endif; ?>


                    <?php if (
                        !empty($equipment['brand']) &&
                        !empty($equipment['model'])
                    ): ?>

                        ·

                    <?php endif; ?>


                    <?php if (
                        !empty($equipment['model'])
                    ): ?>

                        <?= htmlspecialchars(
                            $equipment['model'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php endif; ?>


                </div>


            <?php endif; ?>



            <!-- =================================================
                 RENTAL RATE
            ================================================== -->

            <div class="rental-rate">


                <span class="rate-label">
                    Rental Rate
                </span>


                <div class="rate-price">

                    ₱<?= number_format(
                        (float) $equipment['rate_price'],
                        2
                    ) ?>

                    <small>

                        / <?= htmlspecialchars(
                            $rateLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </small>

                </div>


            </div>



            <!-- =================================================
                 DESCRIPTION
            ================================================== -->

            <div class="description-section">


                <h3>
                    About this equipment
                </h3>


                <p>

                    <?= nl2br(
                        htmlspecialchars(
                            $equipment['description']
                                ?? 'No description available.',
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>

                </p>


            </div>



            <!-- =================================================
                 INFORMATION
            ================================================== -->

            <div class="equipment-meta">


                <div class="meta-row">

                    <span>
                        Category
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $equipment['category_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div class="meta-row">

                    <span>
                        Brand
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $equipment['brand']
                                ?: 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div class="meta-row">

                    <span>
                        Model
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $equipment['model']
                                ?: 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div class="meta-row">

                    <span>
                        Rate Type
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            ucfirst(
                                $equipment['rate_type']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div class="meta-row">
                    <span>
                        Availability
                    </span>
                    <strong>
                        <?= htmlspecialchars(
                            ucfirst(
                                $equipment['availability']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

                <!-- LOCATION METADATA ROW -->
                <div class="meta-row">
                    <span>
                        📍 Location
                    </span>
                    <strong>
                        <?= htmlspecialchars($equipment['location_city'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($equipment['location_province'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($isNearestCity): ?>
                            <span style="background:#d7ecc7; color:#1b4f15; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; margin-left:6px; display:inline-flex; align-items:center;">
                                🎯 Nearest in your City
                            </span>
                        <?php elseif ($isNearbyProv): ?>
                            <span style="background:#eef4ea; color:#3b6e31; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; margin-left:6px;">
                                📍 In your Province
                            </span>
                        <?php endif; ?>
                    </strong>
                </div>

            </div>

            <!-- NEAREST LOCATION PROXIMITY CARD -->
            <?php if ($isNearestCity): ?>
                <div style="background:#e8eedf; border:1px solid #cbd8bd; padding:14px 18px; margin-bottom:20px; border-radius:3px; font-size:13.5px; color:#122017; line-height:1.5;">
                    🎯 <strong>Nearest Available Equipment:</strong> This machinery is located directly in <strong><?= htmlspecialchars($equipment['location_city']) ?></strong>. Booking local equipment saves transport time and hauling costs.
                </div>
            <?php endif; ?>



            <!-- =================================================
                 OWNER
            ================================================== -->

            <div class="owner-box">


                <span class="eyebrow">
                    Equipment Owner
                </span>


                <strong>

                    <?= htmlspecialchars(
                        $equipment['owner_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </strong>


            </div>



            <!-- =================================================
                 RENT ACTION
            ================================================== -->

            <div class="rent-area">


                <?php if ($isOwner): ?>


                    <button
                        type="button"
                        class="btn btn-dark rent-disabled"
                        disabled
                    >
                        Your Equipment
                    </button>


                    <p class="rent-message">

                        This equipment listing belongs
                        to your account.

                    </p>


                <?php elseif (!$isAvailable): ?>


                    <button
                        type="button"
                        class="btn btn-dark rent-disabled"
                        disabled
                    >
                        Currently Unavailable
                    </button>


                    <p class="rent-message">

                        This equipment cannot currently
                        be booked for rental.

                    </p>


                <?php elseif (!$isLoggedIn): ?>


                    <a
                        href="login.php"
                        class="btn btn-dark rent-btn"
                    >
                        Login to Rent
                    </a>


                    <p class="rent-message">

                        Sign in to your AgriMart account
                        before making a rental booking.

                    </p>


                <?php else: ?>

                    <?php if (isset($_SESSION['booking_error'])): ?>
                        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:12px; margin-bottom:15px; font-size:13px;">
                            <?= htmlspecialchars($_SESSION['booking_error']) ?>
                        </div>
                        <?php unset($_SESSION['booking_error']); ?>
                    <?php endif; ?>

                    <button
                        type="button"
                        class="btn btn-solid rent-btn"
                        onclick="document.getElementById('bookingModal').style.display='flex'"
                    >
                        Book This Equipment
                    </button>

                    <p class="rent-message">
                        Select rental dates and delivery details to request a booking from <?= htmlspecialchars($equipment['owner_name']) ?>.
                    </p>

                    <!-- BOOKING MODAL -->
                    <div id="bookingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:9999; justify-content:center; align-items:center; padding:20px; box-sizing:border-box;">
                        <div style="background:#f5f0df; width:min(580px, 100%); max-height:90vh; overflow-y:auto; padding:35px; border:1px solid #ded6b9; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
                            <button type="button" onclick="document.getElementById('bookingModal').style.display='none'" style="position:absolute; top:15px; right:15px; background:none; border:none; font-size:22px; cursor:pointer; color:#122017;">&times;</button>
                            
                            <span style="font-family:monospace; color:#768047; font-size:11px; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:5px;">Rental Application</span>
                            <h2 style="font-family:Georgia,serif; font-size:26px; margin:0 0 20px; color:#122017;">Book <?= htmlspecialchars($equipment['equipment_name']) ?></h2>

                            <form action="process_booking.php" method="POST" id="bookingForm" enctype="multipart/form-data">
                                <input type="hidden" name="equipment_id" value="<?= (int)$equipment['equipment_id'] ?>">
                                <input type="hidden" id="ratePrice" value="<?= (float)$equipment['rate_price'] ?>">
                                <input type="hidden" id="rateType" value="<?= htmlspecialchars($equipment['rate_type']) ?>">

                                <?php $isHourly = in_array(strtolower($equipment['rate_type']), ['hourly', 'hour']); ?>

                                <?php if ($isHourly): ?>
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                                        <div>
                                            <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Rental Date</label>
                                            <input type="date" name="start_date" id="startDate" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;" onchange="calculateRentalTotal()">
                                            <input type="hidden" name="end_date" id="endDate" value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div>
                                            <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Rental Duration (Hours)</label>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <input type="number" name="rental_hours" id="rentalHours" required min="1" max="168" value="1" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;" oninput="calculateRentalTotal()" onchange="calculateRentalTotal()">
                                                <span style="font-size:13px; font-weight:600; color:#596054;">Hour(s)</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                                        <div>
                                            <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Start Date</label>
                                            <input type="date" name="start_date" id="startDate" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;" onchange="handleStartDateChange()">
                                        </div>
                                        <div>
                                            <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Return Date</label>
                                            <input type="date" name="end_date" id="endDate" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;" onchange="calculateRentalTotal()">
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Pickup Location / Farm Area</label>
                                    <input type="text" name="pickup_location" required value="<?= htmlspecialchars($equipment['location_city'] . ', ' . $equipment['location_province']) ?>" placeholder="e.g. Brgy. San Jose, Farm Gate 3..." style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Dropoff / Return Location</label>
                                    <input type="text" name="dropoff_location" required placeholder="e.g. Same as pickup / Equipment Depot..." style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:8px; color:#596054;">Payment Method</label>
                                    <div style="display:grid; gap:8px;">
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                                            <input type="radio" name="payment_method" value="cash" checked onchange="switchBookingPayment('cash')"> Cash on Pickup / Machine Handover
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                                            <input type="radio" name="payment_method" value="gcash" onchange="switchBookingPayment('gcash')"> GCash (Direct to Equipment Owner)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                                            <input type="radio" name="payment_method" value="maya" onchange="switchBookingPayment('maya')"> Maya (Direct to Equipment Owner)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                                            <input type="radio" name="payment_method" value="bank_transfer" onchange="switchBookingPayment('bank_transfer')"> Bank Transfer (Direct to Equipment Owner)
                                        </label>
                                    </div>
                                </div>

                                <!-- Dynamic Booking Payment Instructions -->
                                <div id="bPaymentBox" style="display:none; margin-bottom:15px; padding:18px; background:#fff; border:1px solid #ded6b9; font-size:13px;">
                                    <div style="background:#f9f7f0; border:1px solid #d8d0b7; padding:12px; margin-bottom:14px;">
                                        <span style="font-family:monospace; color:#768047; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:4px;">
                                            🚜 Payment Recipient (Owner)
                                        </span>
                                        <div>Owner Name: <strong><?= htmlspecialchars($equipment['owner_name']) ?></strong></div>
                                        <div>Owner Contact / Phone: <strong><?= htmlspecialchars($equipment['owner_phone'] ?: 'N/A') ?></strong></div>
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Your Bank / E-Wallet Provider *</label>
                                        <select id="buyer_bank_name" name="buyer_bank_name" style="width:100%; padding:8px; border:1px solid #d8d0b7; background:#fff;">
                                            <option value="GCash">GCash</option>
                                            <option value="Maya">Maya</option>
                                            <option value="LandBank">LandBank</option>
                                            <option value="BDO Unibank">BDO Unibank</option>
                                            <option value="BPI">BPI</option>
                                            <option value="UnionBank">UnionBank</option>
                                            <option value="Other Provider">Other Bank / E-Wallet</option>
                                        </select>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                                        <div>
                                            <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Your Account Name *</label>
                                            <input type="text" name="buyer_account_name" placeholder="e.g. Juan Dela Cruz" style="width:100%; padding:8px; border:1px solid #d8d0b7; background:#fff;">
                                        </div>
                                        <div>
                                            <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Your Account / Mobile # *</label>
                                            <input type="text" name="buyer_account_number" placeholder="e.g. 0917-123-4567" style="width:100%; padding:8px; border:1px solid #d8d0b7; background:#fff;">
                                        </div>
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Transaction Ref Number</label>
                                        <input type="text" name="transaction_ref" placeholder="e.g. Ref No. 902184712391" style="width:100%; padding:8px; border:1px solid #d8d0b7; background:#fff;">
                                    </div>

                                    <div>
                                        <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Upload Receipt Screenshot (Optional)</label>
                                        <input type="file" name="payment_proof" accept="image/*" style="width:100%; font-size:12px;">
                                    </div>
                                </div>

                                <script>
                                function switchBookingPayment(method) {
                                    const box = document.getElementById('bPaymentBox');
                                    const bProv = document.getElementById('buyer_bank_name');
                                    if (method === 'cash') {
                                        box.style.display = 'none';
                                    } else {
                                        box.style.display = 'block';
                                        if (method === 'gcash') bProv.value = 'GCash';
                                        else if (method === 'maya') bProv.value = 'Maya';
                                        else if (method === 'bank_transfer') bProv.value = 'LandBank';
                                    }
                                }
                                </script>

                                <div style="background:#fff; border:1px solid #ded6b9; padding:15px; margin:20px 0;">
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                                        <span>Rate:</span>
                                        <strong>₱<?= number_format((float)$equipment['rate_price'], 2) ?> / <?= htmlspecialchars($rateLabel) ?></strong>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                                        <span>Estimated Duration:</span>
                                        <strong id="calcDuration"><?= $isHourly ? '1 hour' : '1 day' ?></strong>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
                                        <span>Rental Subtotal:</span>
                                        <strong id="calcSubtotal">₱<?= number_format((float)$equipment['rate_price'], 2) ?></strong>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:8px; color:#854d0e;">
                                        <span>Refundable Security Deposit (20%):</span>
                                        <strong id="calcDeposit">₱<?= number_format((float)$equipment['rate_price'] * 0.20, 2) ?></strong>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:700; border-top:1px solid #eee; padding-top:8px; color:var(--forest-900);">
                                        <span>Total Amount Payable:</span>
                                        <strong id="calcTotal">₱<?= number_format((float)$equipment['rate_price'] * 1.20, 2) ?></strong>
                                    </div>
                                    <div style="font-size:11px; color:#777; margin-top:6px; line-height:1.4;">
                                        * Note: Includes a 20% refundable security deposit for equipment damage or loss protection, returned upon equipment inspection.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-solid" style="width:100%; padding:14px; cursor:pointer; font-size:13px;">Confirm Booking Request</button>
                            </form>
                        </div>
                    </div>

                    <script>
                    function handleStartDateChange() {
                        const startInput = document.getElementById('startDate');
                        const endInput = document.getElementById('endDate');
                        if (!startInput || !startInput.value) return;

                        const d = new Date(startInput.value + 'T00:00:00');
                        d.setDate(d.getDate() + 1);
                        const yyyy = d.getFullYear();
                        const mm = String(d.getMonth() + 1).padStart(2, '0');
                        const dd = String(d.getDate()).padStart(2, '0');
                        const minEnd = `${yyyy}-${mm}-${dd}`;

                        if (endInput) {
                            endInput.min = minEnd;
                            if (!endInput.value || endInput.value < minEnd) {
                                endInput.value = minEnd;
                            }
                        }
                        calculateRentalTotal();
                    }

                    function calculateRentalTotal() {
                        const rate = parseFloat(document.getElementById('ratePrice').value) || 0;
                        const rateType = (document.getElementById('rateType').value || 'daily').toLowerCase();
                        const isHourly = (rateType === 'hourly' || rateType === 'hour');

                        let subtotal = 0;
                        let durationText = '';

                        if (isHourly) {
                            const hoursInput = document.getElementById('rentalHours');
                            let hours = parseInt(hoursInput ? hoursInput.value : 1) || 1;
                            if (hours < 1) hours = 1;
                            if (hoursInput) hoursInput.value = hours;

                            subtotal = rate * hours;
                            durationText = hours + (hours === 1 ? " hour" : " hours");

                            const start = document.getElementById('startDate').value;
                            if (start) {
                                const d1 = new Date(start + 'T00:00:00');
                                const extraDays = Math.floor(hours / 24);
                                d1.setDate(d1.getDate() + extraDays);
                                const yyyy = d1.getFullYear();
                                const mm = String(d1.getMonth() + 1).padStart(2, '0');
                                const dd = String(d1.getDate()).padStart(2, '0');
                                const endInput = document.getElementById('endDate');
                                if (endInput) endInput.value = `${yyyy}-${mm}-${dd}`;
                            }
                        } else {
                            const start = document.getElementById('startDate').value;
                            const end = document.getElementById('endDate').value;
                            if (!start || !end) return;

                            const d1 = new Date(start + 'T00:00:00');
                            const d2 = new Date(end + 'T00:00:00');

                            if (d2 <= d1) {
                                const nextDay = new Date(d1);
                                nextDay.setDate(nextDay.getDate() + 1);
                                const yyyy = nextDay.getFullYear();
                                const mm = String(nextDay.getMonth() + 1).padStart(2, '0');
                                const dd = String(nextDay.getDate()).padStart(2, '0');
                                const endInput = document.getElementById('endDate');
                                if (endInput) endInput.value = `${yyyy}-${mm}-${dd}`;
                            }

                            const finalEnd = new Date(document.getElementById('endDate').value + 'T00:00:00');
                            const diffTime = Math.max(0, finalEnd - d1);
                            const diffDays = Math.max(1, Math.round(diffTime / (1000 * 60 * 60 * 24)));

                            subtotal = rate * diffDays;
                            durationText = diffDays + (diffDays === 1 ? " day" : " days");
                        }

                        let deposit = subtotal * 0.20;
                        let total = subtotal + deposit;

                        const durationElem = document.getElementById('calcDuration');
                        const subtotalElem = document.getElementById('calcSubtotal');
                        const depositElem = document.getElementById('calcDeposit');
                        const totalElem = document.getElementById('calcTotal');

                        if (durationElem) durationElem.innerText = durationText;
                        if (subtotalElem) subtotalElem.innerText = "₱" + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        if (depositElem) depositElem.innerText = "₱" + deposit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        if (totalElem) totalElem.innerText = "₱" + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                    </script>

                <?php endif; ?>

                <!-- SECONDARY ACTIONS (RATE & REVIEW + REPORT) -->
                <div style="display:flex; gap:10px; margin-top:20px; flex-wrap:wrap;">
                    <a href="add_review.php?equipment_id=<?= (int)$equipment['equipment_id'] ?>" class="btn btn-outline-dark" style="padding:10px 18px; font-size:11px; text-decoration:none;">
                        ⭐ Rate & Review
                    </a>
                    <button type="button" class="btn btn-outline-dark" onclick="openReportModal()" style="padding:10px 18px; font-size:11px; color:#8c2e1b; border-color:#d9a99f; cursor:pointer;">
                        🚩 Report Machinery
                    </button>
                </div>

            </div>

        </div>

    </div>

    <!-- =========================================================
         RENTER REVIEWS & RATINGS SECTION
    ========================================================= -->
    <section id="equipmentReviews" style="margin-top:60px; border-top:1px solid #ded6b9; padding-top:45px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; margin-bottom:28px;">
            <div>
                <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Verified Renter Feedback</span>
                <h2 style="font-family:Georgia,serif; font-size:clamp(26px, 3.5vw, 34px); color:#122017; margin:6px 0 0;">Machinery Reviews & Ratings</h2>
            </div>
            <div>
                <a href="add_review.php?equipment_id=<?= (int)$equipment['equipment_id'] ?>" class="btn btn-solid" style="padding:12px 22px; font-size:12px; text-decoration:none;">
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
                    Verified renter reviews for <strong><?= htmlspecialchars($equipment['equipment_name']) ?></strong> from farm operators and agricultural renters across AgriMart.
                </p>
                <p style="margin:0; font-size:13px; color:#768047;">
                    Have you rented this equipment? Share your feedback to help the agricultural community!
                </p>
            </div>
        </div>

        <!-- Reviews List -->
        <?php if ($reviewCount > 0): ?>
            <div style="display:grid; gap:16px;">
                <?php foreach ($equipmentReviews as $rev): ?>
                    <div style="background:#fff; border:1px solid #ded6b9; padding:22px 26px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:10px;">
                            <div>
                                <strong style="color:#122017; font-size:15px;"><?= htmlspecialchars($rev['reviewer_name']) ?></strong>
                                <span style="font-size:11px; color:#23581c; margin-left:8px; background:#e0edd5; padding:2px 8px; border-radius:2px; font-weight:600;">✓ Verified Renter</span>
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
                <p style="margin:0 0 16px; font-size:15px; color:#5e604e;">There are no reviews for this machinery yet.</p>
                <a href="add_review.php?equipment_id=<?= (int)$equipment['equipment_id'] ?>" class="btn btn-solid" style="padding:12px 24px; font-size:12px; text-decoration:none;">
                    Leave the First Review
                </a>
            </div>
        <?php endif; ?>
    </section>

</div>

</main>

<!-- =========================================================
     REPORT EQUIPMENT MODAL
========================================================= -->
<div id="reportModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:9999; justify-content:center; align-items:center; padding:20px; box-sizing:border-box;">
    <div style="background:#f5f0df; width:min(520px, 100%); padding:35px; border:1px solid #ded6b9; box-shadow:0 16px 40px rgba(0,0,0,0.3); position:relative; box-sizing:border-box;">
        <button type="button" onclick="closeReportModal()" style="position:absolute; top:15px; right:15px; background:none; border:none; font-size:24px; cursor:pointer; color:#122017;">&times;</button>
        <span style="font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#8c2e1b; font-weight:700; display:block; margin-bottom:6px;">Safety & Moderation</span>
        <h2 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin:0 0 12px;">Report Equipment Listing</h2>
        <p style="font-size:13.5px; color:#5e604e; line-height:1.5; margin-bottom:18px;">
            Help keep AgriMart safe and trustworthy. If this equipment rental violates safety or marketplace policies, submit a report for immediate moderator review.
        </p>

        <form id="reportForm" onsubmit="submitReport(event)">
            <input type="hidden" name="report_type" value="equipment">
            <input type="hidden" name="item_id" value="<?= (int)$equipment['equipment_id'] ?>">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#5e604e; margin-bottom:6px; font-weight:600;">Reason for Report *</label>
                <select name="reason" required style="width:100%; padding:12px; border:1px solid #d4c79c; background:#fff; font-size:14px;">
                    <option value="fake_product">Fake / Inaccurate Machinery Details</option>
                    <option value="misleading">Misleading Rental Rates or Location</option>
                    <option value="scam_fraud">Suspected Fraud or Fake Owner</option>
                    <option value="poor_quality">Inoperable / Unsafe Equipment</option>
                    <option value="prohibited_item">Stolen or Unauthorized Machinery</option>
                    <option value="other">Other Marketplace Violation</option>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#5e604e; margin-bottom:6px; font-weight:600;">Explanation / Details *</label>
                <textarea name="description" required rows="4" style="width:100%; box-sizing:border-box; padding:12px; border:1px solid #d4c79c; background:#fff; font-size:14px;" placeholder="Please explain why you are reporting this equipment..."></textarea>
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


</body>

</html>


<?php

$stmt->close();

$conn->close();

?>
