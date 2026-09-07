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

    $parts = explode(
        ' ',
        trim($fullName)
    );

    $firstName = $parts[0];
}


/* =========================================================
   SEARCH / FILTER VALUES
========================================================= */

$search = trim(
    $_GET['search'] ?? ''
);

$categoryId = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$availability = trim(
    $_GET['availability'] ?? ''
);


/* =========================================================
   GET EQUIPMENT CATEGORIES
========================================================= */

$categories = [];

$categorySql = "
    SELECT
        category_id,
        category_name

    FROM categories

    WHERE category_type = 'equipment'

    ORDER BY category_name ASC
";


$categoryResult = $conn->query(
    $categorySql
);


if ($categoryResult) {

    while (
        $category = $categoryResult->fetch_assoc()
    ) {

        $categories[] = $category;

    }

}


/* =========================================================
   BUILD EQUIPMENT QUERY
========================================================= */

$sql = "

    SELECT

        e.equipment_id,
        e.equipment_name,
        e.description,
        e.brand,
        e.model,
        e.rate_type,
        e.rate_price,
        e.availability,
        e.image_url,
        e.status,

        c.category_name,

        u.full_name AS owner_name

    FROM equipment e

    INNER JOIN categories c
        ON e.category_id = c.category_id

    INNER JOIN users u
        ON e.user_id = u.user_id

    WHERE e.status = 'active'
      AND c.category_type = 'equipment'
      AND u.status = 'active'

";


$params = [];
$types = '';


/* =========================================================
   SEARCH FILTER
========================================================= */

if ($search !== '') {

    $sql .= "
        AND (
            e.equipment_name LIKE ?
            OR e.description LIKE ?
            OR e.brand LIKE ?
            OR e.model LIKE ?
            OR u.full_name LIKE ?
        )
    ";


    $searchValue =
        '%' . $search . '%';


    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'sssss';

}


/* =========================================================
   CATEGORY FILTER
========================================================= */

if ($categoryId > 0) {

    $sql .= "
        AND e.category_id = ?
    ";


    $params[] = $categoryId;

    $types .= 'i';

}


/* =========================================================
   AVAILABILITY FILTER
========================================================= */

$allowedAvailability = [
    'available',
    'rented',
    'maintenance'
];


if (
    in_array(
        $availability,
        $allowedAvailability,
        true
    )
) {

    $sql .= "
        AND e.availability = ?
    ";


    $params[] = $availability;

    $types .= 's';

}


/* =========================================================
   ORDER
========================================================= */

$sql .= "
    ORDER BY e.created_at DESC
";


/* =========================================================
   PREPARE QUERY
========================================================= */

$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        'Unable to load equipment.'
    );

}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}


$stmt->execute();

$equipmentResult =
    $stmt->get_result();


/* =========================================================
   IMAGE HELPER
========================================================= */

function getEquipmentImage($imageUrl)
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
        Equipment — AgriMart
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

            color:
                var(--wheat-300, #d6b95f);
        }


        /* =====================================================
           EQUIPMENT PAGE HERO
        ===================================================== */

        .page-hero {

            background:
                var(--forest-950);

            color:
                var(--cream-50);

            padding:
                110px 0 85px;
        }


        .page-hero .eyebrow {

            color:
                var(--wheat-300);
        }


        .page-hero h1 {

            color:
                var(--cream-50);

            margin:
                12px 0 18px;

            font-size:
                clamp(
                    42px,
                    6vw,
                    68px
                );
        }


        .page-hero p {

            max-width: 620px;

            color:
                var(--cream-100);

            font-size: 17px;

            line-height: 1.7;
        }


        /* =====================================================
           MARKETPLACE
        ===================================================== */

        .equipment-marketplace {

            padding:
                80px 0 100px;
        }


        /* =====================================================
           FILTERS
        ===================================================== */

        .filter-bar {

            margin-bottom: 38px;

            padding-bottom: 32px;

            border-bottom:
                1px solid
                rgba(17,55,36,.15);
        }


        .filter-bar form {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 12px;
        }


        .filter-bar input,
        .filter-bar select {

            min-height: 50px;

            padding:
                0 16px;

            border:
                1px solid
                rgba(17,55,36,.25);

            background:
                #ffffff;

            color:
                var(--forest-900);

            font-family:
                inherit;

            font-size:
                14px;
        }


        .filter-bar input {

            width:
                min(280px, 100%);
        }


        .filter-bar select {

            min-width:
                190px;
        }


        .filter-bar input:focus,
        .filter-bar select:focus {

            outline:
                2px solid
                var(--wheat-300);

            outline-offset:
                1px;
        }


        .clear-filter {

            color:
                var(--forest-900);

            font-size:
                12px;

            text-decoration:
                none;
        }


        /* =====================================================
           COUNT
        ===================================================== */

        .equipment-count {

            margin-bottom:
                26px;

            color:
                #687063;

            font-size:
                14px;
        }


        /* =====================================================
           GRID
        ===================================================== */

        .equipment-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap:
                26px;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .equipment-card {

            background:
                #fff;

            border:
                1px solid
                rgba(17,55,36,.15);

            overflow:
                hidden;

            display:
                flex;

            flex-direction:
                column;
        }


        .equipment-card-media {

            position:
                relative;

            height:
                260px;

            background:
                var(--forest-900);

            overflow:
                hidden;
        }


        .equipment-card-media img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;
        }


        .equipment-tag {

            position:
                absolute;

            top:
                16px;

            left:
                16px;

            padding:
                8px 12px;

            background:
                var(--wheat-300);

            color:
                var(--forest-950);

            font-size:
                10px;

            text-transform:
                uppercase;

            letter-spacing:
                1.2px;
        }


        .availability-tag {

            position:
                absolute;

            top:
                16px;

            right:
                16px;

            padding:
                8px 12px;

            font-size:
                10px;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        .availability-available {

            background:
                #e8eedf;

            color:
                var(--forest-900);
        }


        .availability-rented {

            background:
                #efe2b7;

            color:
                #6e5817;
        }


        .availability-maintenance {

            background:
                #f1deda;

            color:
                #7b3429;
        }


        /* =====================================================
           CARD BODY
        ===================================================== */

        .equipment-card-body {

            display:
                flex;

            flex-direction:
                column;

            flex: 1;

            padding:
                24px;
        }


        .equipment-owner {

            margin-bottom:
                8px;

            color:
                var(--moss-500);

            font-size:
                11px;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        .equipment-card h3 {

            margin:
                0 0 10px;

            font-size:
                25px;

            line-height:
                1.2;
        }


        .equipment-brand {

            margin-bottom:
                15px;

            color:
                #687063;

            font-size:
                13px;
        }


        .equipment-description {

            margin-bottom:
                22px;

            color:
                #687063;

            font-size:
                13px;

            line-height:
                1.7;

            display:
                -webkit-box;

            -webkit-line-clamp:
                3;

            -webkit-box-orient:
                vertical;

            overflow:
                hidden;
        }


        /* =====================================================
           CARD FOOTER
        ===================================================== */

        .equipment-card-footer {

            margin-top:
                auto;

            padding-top:
                20px;

            border-top:
                1px solid
                rgba(17,55,36,.12);

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;
        }


        .equipment-price {

            color:
                var(--forest-900);

            font-size:
                22px;

            font-weight:
                700;
        }


        .equipment-price small {

            display:
                block;

            margin-top:
                3px;

            color:
                #687063;

            font-size:
                11px;

            font-weight:
                400;

            text-transform:
                uppercase;

            letter-spacing:
                .7px;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-equipment {

            padding:
                70px 30px;

            border:
                1px solid
                rgba(17,55,36,.15);

            text-align:
                center;
        }


        .empty-equipment h2 {

            margin-bottom:
                12px;
        }


        .empty-equipment p {

            color:
                #687063;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (
            max-width: 1050px
        ) {

            .equipment-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

        }


        @media (
            max-width: 700px
        ) {

            .equipment-grid {

                grid-template-columns:
                    1fr;
            }


            .filter-bar form {

                align-items:
                    stretch;

                flex-direction:
                    column;
            }


            .filter-bar input,
            .filter-bar select {

                width:
                    100%;

                box-sizing:
                    border-box;
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
     PAGE HERO
========================================================= -->

<section class="page-hero">

<div class="wrap">


    <span class="eyebrow">
        AgriMart Equipment
    </span>


    <h1>
        Farm Equipment
    </h1>


    <p>

        Browse agricultural machinery and tools
        available for rental from AgriMart equipment
        owners.

    </p>


</div>

</section>



<!-- =========================================================
     MARKETPLACE
========================================================= -->

<main class="equipment-marketplace" id="catalog">

<div class="wrap">


    <!-- =====================================================
         FILTER BAR
    ====================================================== -->

    <div class="filter-bar">


        <form
            method="GET"
            action="equipment.php#catalog"
        >


            <!-- SEARCH -->

            <input

                type="text"

                name="search"

                placeholder="Search equipment..."

                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

            >


            <!-- CATEGORY -->

            <select name="category" onchange="this.form.submit()">

                <option value="0">
                    All Categories
                </option>


                <?php foreach (
                    $categories as $category
                ): ?>


                    <option

                        value="<?= (int) $category['category_id'] ?>"

                        <?=

                            $categoryId ===
                            (int) $category['category_id']

                                ? 'selected'

                                : ''

                        ?>

                    >

                        <?= htmlspecialchars(
                            $category['category_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>


                <?php endforeach; ?>


            </select>


            <!-- AVAILABILITY -->

            <select name="availability" onchange="this.form.submit()">

                <option value="">
                    All Status
                </option>


                <option

                    value="available"

                    <?=

                        $availability === 'available'
                            ? 'selected'
                            : ''

                    ?>

                >
                    Available
                </option>


                <option

                    value="rented"

                    <?=

                        $availability === 'rented'
                            ? 'selected'
                            : ''

                    ?>

                >
                    Rented
                </option>


                <option

                    value="maintenance"

                    <?=

                        $availability === 'maintenance'
                            ? 'selected'
                            : ''

                    ?>

                >
                    Maintenance
                </option>


            </select>


            <!-- SEARCH BUTTON -->

            <button
                type="submit"
                class="btn btn-dark"
            >
                Search
            </button>



            <?php if (
                $search !== '' ||
                $categoryId > 0 ||
                $availability !== ''
            ): ?>


                <a
                    href="equipment.php#catalog"
                    class="clear-filter"
                >
                    Clear Filters
                </a>


            <?php endif; ?>


        </form>


    </div>



    <!-- =====================================================
         EQUIPMENT COUNT
    ====================================================== -->

    <p class="equipment-count">

        <?= $equipmentResult->num_rows ?>

        <?=

            $equipmentResult->num_rows === 1

                ? 'equipment listing found'

                : 'equipment listings found'

        ?>

    </p>



    <!-- =====================================================
         EQUIPMENT GRID
    ====================================================== -->

    <?php if (
        $equipmentResult->num_rows > 0
    ): ?>


        <div class="equipment-grid">


            <?php while (
                $equipment =
                    $equipmentResult->fetch_assoc()
            ): ?>


                <article class="equipment-card">


                    <!-- =====================================
                         IMAGE
                    ====================================== -->

                    <div class="equipment-card-media">


                        <img

                            src="<?= htmlspecialchars(
                                getEquipmentImage(
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



                        <span class="equipment-tag">

                            <?= htmlspecialchars(
                                $equipment['category_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>



                        <span
                            class="availability-tag availability-<?= htmlspecialchars(
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



                    <!-- =====================================
                         BODY
                    ====================================== -->

                    <div class="equipment-card-body">


                        <span class="equipment-owner">

                            Owner:

                            <?= htmlspecialchars(
                                $equipment['owner_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>



                        <h3>

                            <?= htmlspecialchars(
                                $equipment['equipment_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h3>



                        <!-- BRAND / MODEL -->

                        <?php if (
                            !empty($equipment['brand']) ||
                            !empty($equipment['model'])
                        ): ?>


                            <div class="equipment-brand">


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



                        <!-- DESCRIPTION -->

                        <div class="equipment-description">

                            <?= htmlspecialchars(
                                $equipment['description']
                                    ?? 'No description available.',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>



                        <!-- =================================
                             FOOTER
                        ================================== -->

                        <div class="equipment-card-footer">


                            <div class="equipment-price">

                                ₱<?= number_format(
                                    (float) $equipment['rate_price'],
                                    2
                                ) ?>


                                <small>

                                    per
                                    <?= htmlspecialchars(
                                        $equipment['rate_type'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>

                            </div>



                            <a
                                href="equipment_details.php?id=<?= (int) $equipment['equipment_id'] ?>"
                                class="btn btn-dark"
                            >
                                View
                            </a>


                        </div>


                    </div>


                </article>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <div class="empty-equipment">


            <h2>
                No equipment found.
            </h2>


            <p>

                There are currently no active equipment
                listings matching your filters.

            </p>


        </div>


    <?php endif; ?>


</div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">

<div class="wrap">


    <div class="footer-top">


        <div class="footer-brand">


            <a
                href="index.php"
                class="logo"
            >

                <svg
                    class="wheat-mark"
                    viewBox="0 0 40 40"
                    xmlns="http://www.w3.org/2000/svg"
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


            <p>

                A digital marketplace for agricultural
                products and farm equipment rentals.

            </p>


        </div>



        <div class="footer-col">

            <h4>
                Marketplace
            </h4>

            <a href="products.php">
                Browse Products
            </a>

            <a href="equipment.php">
                Browse Equipment
            </a>

        </div>



        <div class="footer-col">

            <h4>
                Account
            </h4>


            <?php if ($isLoggedIn): ?>

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a href="logout.php">
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php">
                    Login
                </a>

                <a href="register.php">
                    Create Account
                </a>

            <?php endif; ?>


        </div>



        <div class="footer-col">

            <h4>
                Support
            </h4>

            <a href="#">
                Help Center
            </a>

            <a href="#">
                Contact Us
            </a>

            <a href="#">
                Terms &amp; Privacy
            </a>

        </div>


    </div>



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