<?php

session_start();

require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);

$fullName = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? '';

$firstName = '';

if ($fullName !== '') {
    $parts = explode(' ', trim($fullName));
    $firstName = $parts[0];
}


/* =========================================================
   FEATURED MARKETPLACE DATA
========================================================= */

$featuredProducts = [];
$featuredEquipment = [];

$productSql = "
    SELECT
        p.product_id,
        p.product_name,
        p.price,
        p.quantity,
        p.unit,
        p.image_url,
        c.category_name,
        u.full_name AS seller_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    INNER JOIN users u ON p.user_id = u.user_id
    WHERE p.status = 'active'
      AND c.category_type = 'seed'
      AND u.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 3
";

if ($result = $conn->query($productSql)) {
    while ($row = $result->fetch_assoc()) {
        $featuredProducts[] = $row;
    }
}

$equipmentSql = "
    SELECT
        e.equipment_id,
        e.equipment_name,
        e.brand,
        e.model,
        e.rate_type,
        e.rate_price,
        e.availability,
        e.image_url,
        c.category_name,
        u.full_name AS owner_name
    FROM equipment e
    INNER JOIN categories c ON e.category_id = c.category_id
    INNER JOIN users u ON e.user_id = u.user_id
    WHERE e.status = 'active'
      AND c.category_type = 'equipment'
      AND u.status = 'active'
    ORDER BY e.created_at DESC
    LIMIT 3
";

if ($result = $conn->query($equipmentSql)) {
    while ($row = $result->fetch_assoc()) {
        $featuredEquipment[] = $row;
    }
}

function indexProductImage($imageUrl)
{
    if (empty($imageUrl)) {
        return 'images/placeholder-product.svg';
    }

    if (strpos($imageUrl, 'assets/images/') === 0) {
        return str_replace('assets/images/', 'images/', $imageUrl);
    }

    return $imageUrl;
}

function indexEquipmentImage($imageUrl)
{
    if (empty($imageUrl)) {
        return 'images/placeholder-equipment.svg';
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
        AgriMart — Digital Market Platform on Agricultural Products
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <style>

        .user-chip {
            color: var(--cream-50, #f6f1df);
            font-size: 14px;
            margin-right: 6px;
            white-space: nowrap;
        }

        .user-chip strong {
            color: var(--wheat-300, #d6b95f);
        }


        .card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
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

        <a
            href="index.php"
            class="active"
        >
            Home
        </a>

        <a href="products.php">
            Products
        </a>

        <a href="equipment.php">
            Equipment
        </a>

        <a href="#how-it-works">
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
                    <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>
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
     HERO
========================================================= -->

<section class="hero">

<div class="hero-inner">

    <div class="hero-eyebrow">

        <span class="rule"></span>

        <span class="eyebrow">
            AgriMart · Digital Market Platform
        </span>

    </div>


    <h1>

        The harvest is only as good as the hands that

        <em>
            reach it.
        </em>

    </h1>


    <p class="hero-sub">

        AgriMart connects agricultural sellers and buyers
        in one marketplace — buy and sell agricultural
        products, list farm equipment, or rent machinery
        using one account.

    </p>


    <div class="hero-cta">

        <a
            href="products.php"
            class="btn btn-solid"
        >
            Browse Marketplace
        </a>


        <?php if (!$isLoggedIn): ?>

            <a
                href="register.php"
                class="btn btn-light"
            >
                Join AgriMart
            </a>

        <?php else: ?>

            <a
                href="dashboard.php"
                class="btn btn-light"
            >
                My Dashboard
            </a>

        <?php endif; ?>

    </div>

</div>


<div class="hero-index">

    <a href="products.php">

        <b>01</b>
        Products

    </a>

    <div class="track"></div>

    <a href="equipment.php">

        <b>02</b>
        Equipment

    </a>

    <div class="track"></div>

    <a href="#how-it-works">

        <b>03</b>
        How It Works

    </a>

</div>


<svg
    class="hero-mark"
    viewBox="0 0 200 200"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
>

    <path
        d="M100 20v150"
        stroke="currentColor"
        fill="none"
        stroke-width="1.2"
    />

    <path
        d="M100 45 L65 25 M100 45 L135 25"
        stroke="currentColor"
        fill="none"
        stroke-width="1.2"
    />

    <path
        d="M100 70 L60 48 M100 70 L140 48"
        stroke="currentColor"
        fill="none"
        stroke-width="1.2"
    />

    <path
        d="M100 95 L58 72 M100 95 L142 72"
        stroke="currentColor"
        fill="none"
        stroke-width="1.2"
    />

    <path
        d="M100 120 L62 98 M100 120 L138 98"
        stroke="currentColor"
        fill="none"
        stroke-width="1.2"
    />

    <path
        d="M100 145 L68 125 M100 145 L132 125"
        stroke="currentColor"
        fill="none"
        stroke-width="1.2"
    />

</svg>


<span class="scroll-cue">
    Scroll to explore
</span>

</section>



<!-- =========================================================
     HUB
========================================================= -->

<section
    id="hub"
    style="padding-top:76px"
>

<div class="wrap">


    <div class="section-head">

        <div>

            <span
                class="eyebrow"
                style="color:var(--moss-500)"
            >
                Start here
            </span>

            <h2>
                What are you looking for?
            </h2>

        </div>


        <p>

            Browse agricultural products or find farm
            equipment available for sale or rental.

        </p>

    </div>


    <div class="hub-choice">


        <a
            href="products.php"
            class="hub-card"
        >

            <span class="num">
                01
            </span>


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
                    d="M20 18 L11 12 M20 18 L29 12"
                />

                <path
                    d="M20 26 L13 21 M20 26 L27 21"
                />

            </svg>


            <h3>
                Seeds & Products
            </h3>


            <p>

                Browse agricultural products offered
                directly by AgriMart users.

            </p>


            <span class="hub-cta">
                Browse Products →
            </span>

        </a>


        <a
            href="equipment.php"
            class="hub-card hub-card-dark"
        >

            <span class="num">
                02
            </span>


            <svg
                class="wheat-mark"
                viewBox="0 0 40 40"
                xmlns="http://www.w3.org/2000/svg"
            >

                <rect
                    x="9"
                    y="15"
                    width="22"
                    height="12"
                    rx="2"
                />

                <circle
                    cx="14"
                    cy="30"
                    r="5"
                />

                <circle
                    cx="26"
                    cy="30"
                    r="5"
                />

            </svg>


            <h3>
                Equipment
            </h3>


            <p>

                Buy or rent agricultural machinery
                and tools available on AgriMart.

            </p>


            <span class="hub-cta">
                Browse Equipment →
            </span>

        </a>


    </div>

</div>

</section>


<div class="grain-divider">

<span class="line"></span>

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
        d="M20 18 L11 12 M20 18 L29 12"
    />

    <path
        d="M20 26 L13 21 M20 26 L27 21"
    />

</svg>

<span class="line"></span>

</div>



<!-- =========================================================
     PRODUCTS
========================================================= -->

<section id="products">

<div class="wrap">


    <div class="section-head">

        <div>

            <span
                class="eyebrow"
                style="color:var(--moss-500)"
            >
                Fresh listings
            </span>

            <h2>
                From the marketplace
            </h2>

        </div>


        <a
            href="products.php"
            class="btn btn-dark"
        >
            View All Products
        </a>

    </div>


    <div class="grid">

        <?php foreach ($featuredProducts as $product): ?>

            <div class="card">

                <div class="card-media">
                    <img
                        src="<?= htmlspecialchars(indexProductImage($product['image_url']), ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <span class="card-tag">
                        <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="card-body">
                    <span class="card-cat">
                        <?= htmlspecialchars($product['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <h3>
                        <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p class="card-meta">
                        <?= (int) $product['quantity'] ?>
                        <?= htmlspecialchars($product['unit'] ?? 'item', ENT_QUOTES, 'UTF-8') ?>
                        available
                    </p>

                    <div class="card-foot">
                        <span class="price">
                            ₱<?= number_format((float) $product['price'], 2) ?>
                            <?php if (!empty($product['unit'])): ?>
                                <small>/ <?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </span>

                        <a
                            href="product_details.php?id=<?= (int) $product['product_id'] ?>"
                            class="btn btn-dark"
                        >
                            View
                        </a>
                    </div>
                </div>

            </div>

        <?php endforeach; ?>

    </div>


</div>

</section>



<!-- =========================================================
     EQUIPMENT
========================================================= -->

<section
    class="section-dark"
    id="equipment"
>

<div class="wrap">


    <div class="section-head">

        <div>

            <span class="eyebrow">
                Ready to work
            </span>

            <h2>
                Equipment marketplace
            </h2>

        </div>


        <a
            href="equipment.php"
            class="btn btn-light"
        >
            View All Equipment
        </a>

    </div>


    <div class="grid">

        <?php foreach ($featuredEquipment as $equipment): ?>

            <div class="card">

                <div class="card-media">
                    <img
                        src="<?= htmlspecialchars(indexEquipmentImage($equipment['image_url']), ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($equipment['equipment_name'], ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <span class="card-tag">
                        <?= htmlspecialchars($equipment['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="card-body">
                    <span class="card-cat">
                        <?= htmlspecialchars($equipment['owner_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <h3>
                        <?= htmlspecialchars($equipment['equipment_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p class="card-meta">
                        <?= htmlspecialchars(trim(($equipment['brand'] ?? '') . ' ' . ($equipment['model'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($equipment['availability'])): ?>
                            · <?= htmlspecialchars(ucfirst($equipment['availability']), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </p>

                    <div class="card-foot">
                        <span class="price">
                            ₱<?= number_format((float) $equipment['rate_price'], 2) ?>
                            <?php if (!empty($equipment['rate_type'])): ?>
                                <small>/ <?= htmlspecialchars($equipment['rate_type'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </span>

                        <a
                            href="equipment_details.php?id=<?= (int) $equipment['equipment_id'] ?>"
                            class="btn btn-light"
                        >
                            View
                        </a>
                    </div>
                </div>

            </div>

        <?php endforeach; ?>

    </div>


</div>

</section>



<!-- =========================================================
     HOW IT WORKS
========================================================= -->

<section
    id="how-it-works"
    style="background:var(--forest-900)"
>

<div class="wrap">


    <div
        class="section-head"
        style="color:var(--cream-50)"
    >

        <div>

            <span
                class="eyebrow"
                style="color:var(--wheat-300)"
            >
                Simple marketplace flow
            </span>

            <h2
                style="color:var(--cream-50)"
            >
                How AgriMart works
            </h2>

        </div>


        <p
            style="color:var(--cream-100)"
        >

            One account lets you buy,
            sell, list equipment and
            make rental bookings.

        </p>

    </div>


    <div class="steps">


        <div class="step">

            <span class="num">
                01
            </span>

            <h3>
                Create an account
            </h3>

            <p>

                Register once and use your AgriMart
                account for all marketplace activities.

            </p>

        </div>


        <div class="step">

            <span class="num">
                02
            </span>

            <h3>
                Buy, sell or rent
            </h3>

            <p>

                Browse products, list your own products,
                or rent and list agricultural equipment.

            </p>

        </div>


        <div class="step">

            <span class="num">
                03
            </span>

            <h3>
                Complete the transaction
            </h3>

            <p>

                Orders and bookings are recorded,
                payments are tracked and completed
                transactions can be reviewed.

            </p>

        </div>


    </div>

</div>

</section>



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
                products, equipment sales and equipment
                rentals.

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


<script src="js/data.js"></script>
<script src="js/main.js"></script>


</body>

</html>