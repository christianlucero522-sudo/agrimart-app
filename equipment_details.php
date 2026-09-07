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

        c.category_id,
        c.category_name,

        u.full_name AS owner_name,
        u.phone AS owner_phone

    FROM equipment e

    INNER JOIN categories c
        ON e.category_id = c.category_id

    INNER JOIN users u
        ON e.user_id = u.user_id

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


            </div>



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

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Start Date</label>
                                        <input type="date" name="start_date" id="startDate" required min="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;" onchange="calculateRentalTotal()">
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">End Date</label>
                                        <input type="date" name="end_date" id="endDate" required min="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;" onchange="calculateRentalTotal()">
                                    </div>
                                </div>

                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:5px; color:#596054;">Pickup Location / Farm Area</label>
                                    <input type="text" name="pickup_location" required placeholder="e.g. Brgy. San Jose, Farm Gate 3..." style="width:100%; padding:10px; border:1px solid #d8d0b7; background:#fff;">
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
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:8px;">
                                        <span>Estimated Duration:</span>
                                        <strong id="calcDuration">1 day</strong>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:700; border-top:1px solid #eee; padding-top:8px; color:var(--forest-900);">
                                        <span>Total Amount:</span>
                                        <strong id="calcTotal">₱<?= number_format((float)$equipment['rate_price'], 2) ?></strong>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-solid" style="width:100%; padding:14px; cursor:pointer; font-size:13px;">Confirm Booking Request</button>
                            </form>
                        </div>
                    </div>

                    <script>
                    function calculateRentalTotal() {
                        const start = document.getElementById('startDate').value;
                        const end = document.getElementById('endDate').value;
                        const rate = parseFloat(document.getElementById('ratePrice').value) || 0;
                        const rateType = document.getElementById('rateType').value;
                        
                        if (!start || !end) return;
                        
                        const d1 = new Date(start);
                        const d2 = new Date(end);
                        
                        if (d2 < d1) {
                            document.getElementById('endDate').value = start;
                        }
                        
                        const diffTime = Math.abs(new Date(document.getElementById('endDate').value) - d1);
                        const diffDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1);
                        
                        let total = rate * diffDays;
                        document.getElementById('calcDuration').innerText = diffDays + " day(s)";
                        document.getElementById('calcTotal').innerText = "₱" + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                    </script>

                <?php endif; ?>


            </div>


        </div>


    </div>


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

$stmt->close();

$conn->close();

?>
