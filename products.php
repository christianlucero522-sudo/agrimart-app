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
   GET SEARCH / FILTER VALUES
========================================================= */

$search = trim($_GET['search'] ?? '');

$categoryId = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;


/* =========================================================
   GET PRODUCT CATEGORIES
========================================================= */

$categories = [];

$categoryQuery = "
    SELECT
        category_id,
        category_name
    FROM categories
    WHERE category_type = 'seed'
    ORDER BY category_name ASC
";

$categoryResult = $conn->query($categoryQuery);

if ($categoryResult) {

    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }

}


/* =========================================================
   BUILD PRODUCT QUERY
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

        c.category_name,

        u.full_name AS seller_name

    FROM products p

    INNER JOIN categories c
        ON p.category_id = c.category_id

    INNER JOIN users u
        ON p.user_id = u.user_id

    WHERE p.status = 'active'
      AND c.category_type = 'seed'
      AND u.status = 'active'

";


$params = [];
$types = '';


/* SEARCH */

if ($search !== '') {

    $sql .= "
        AND (
            p.product_name LIKE ?
            OR p.description LIKE ?
            OR u.full_name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'sss';

}


/* CATEGORY FILTER */

if ($categoryId > 0) {

    $sql .= "
        AND p.category_id = ?
    ";

    $params[] = $categoryId;

    $types .= 'i';

}


$sql .= "
    ORDER BY p.created_at DESC
";


/* =========================================================
   PREPARE QUERY
========================================================= */

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        'Unable to load products.'
    );

}


/* BIND PARAMETERS IF THERE ARE FILTERS */

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}


$stmt->execute();

$productResult = $stmt->get_result();


/* =========================================================
   IMAGE HELPER
========================================================= */

function getProductImage($imageUrl)
{

    if (empty($imageUrl)) {

        return 'images/placeholder-product.svg';

    }


    /*
     * Some of the old demo data used:
     * assets/images/filename.svg
     *
     * Our current project folder already contains:
     * images/filename.svg
     */

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
        Products — AgriMart
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


        .product-description {

            color: #6b7261;

            font-size: 13px;

            line-height: 1.6;

            display: -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;

        }


        .product-count {

            margin-bottom: 24px;

            color: #6b7261;

            font-size: 14px;

        }


        .clear-filter {

            display: inline-flex;

            align-items: center;

            padding: 12px 18px;

            color: var(--forest-800);

            font-size: 13px;

        }
        .site-header {
    background: var(--forest-950) !important;
    background-image: none !important;
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
     PAGE HERO
========================================================= -->

<section class="page-hero">

<div class="wrap">

    <span class="eyebrow">
        AgriMart Marketplace
    </span>


    <h1>
        Agricultural Products
    </h1>


    <p>

        Browse agricultural products offered by
        AgriMart sellers.

    </p>

</div>

</section>



<!-- =========================================================
     PRODUCTS
========================================================= -->

<section id="catalog">

<div class="wrap">


    <!-- FILTER -->

    <div class="filter-bar">

        <form
            method="GET"
            action="products.php#catalog"
        >


            <input
                type="text"
                name="search"
                placeholder="Search products..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <select name="category" onchange="this.form.submit()">

                <option value="0">
                    All Categories
                </option>


                <?php foreach ($categories as $category): ?>


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


            <button
                type="submit"
                class="btn btn-dark"
            >
                Search
            </button>


            <?php if (
                $search !== '' ||
                $categoryId > 0
            ): ?>


                <a
                    href="products.php#catalog"
                    class="clear-filter"
                >
                    Clear filters
                </a>


            <?php endif; ?>


        </form>

    </div>



    <!-- PRODUCT COUNT -->

    <p class="product-count">

        <?= $productResult->num_rows ?>

        <?=

            $productResult->num_rows === 1
                ? 'product found'
                : 'products found'

        ?>

    </p>



    <!-- PRODUCT GRID -->

    <?php if ($productResult->num_rows > 0): ?>


        <div class="grid">


            <?php while (
                $product = $productResult->fetch_assoc()
            ): ?>


                <div class="card">


                    <!-- IMAGE -->

                    <div class="card-media">


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


                        <span class="card-tag">

                            <?= htmlspecialchars(
                                $product['category_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                    </div>



                    <!-- BODY -->

                    <div class="card-body">


                        <span class="card-cat">

                            Seller:

                            <?= htmlspecialchars(
                                $product['seller_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <h3>

                            <?= htmlspecialchars(
                                $product['product_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h3>


                        <p class="product-description">

                            <?= htmlspecialchars(
                                $product['description']
                                    ?? 'No description available.',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>


                        <?php 
                        $stockQty = (int)$product['quantity'];
                        $unitName = htmlspecialchars($product['unit'] ?? 'item', ENT_QUOTES, 'UTF-8');
                        ?>

                        <!-- STOCK LESSENING INDICATOR -->
                        <div style="margin-bottom:12px;">
                            <?php if ($stockQty > 10): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#23581c; background:#e0edd5; padding:3px 9px; border-radius:3px; font-weight:600;">
                                    ✅ <?= $stockQty ?> <?= $unitName ?> in stock
                                </span>
                            <?php elseif ($stockQty > 0): ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#854d0e; background:#fef7e6; border:1px solid #f2dfa8; padding:3px 9px; border-radius:3px; font-weight:700;">
                                    🔥 Only <?= $stockQty ?> <?= $unitName ?> left!
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#a54129; background:#fae6df; border:1px solid #efb7aa; padding:3px 9px; border-radius:3px; font-weight:700;">
                                    ⛔ Out of Stock
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-foot">

                            <span class="price">
                                ₱<?= number_format((float) $product['price'], 2) ?>
                                <?php if (!empty($product['unit'])): ?>
                                    <small>/ <?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </span>

                            <?php if ($stockQty > 0): ?>
                                <a
                                    href="product_details.php?id=<?= (int) $product['product_id'] ?>"
                                    class="btn btn-dark"
                                >
                                    View Details
                                </a>
                            <?php else: ?>
                                <button
                                    type="button"
                                    class="btn btn-dark"
                                    onclick="showOutOfStockPopup('<?= htmlspecialchars(addslashes($product['product_name']), ENT_QUOTES, 'UTF-8') ?>')"
                                    style="background:#8c8874; border-color:#8c8874; cursor:pointer;"
                                >
                                    Out of Stock
                                </button>
                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">
            No products found.
        </div>

    <?php endif; ?>

</div>

</section>

<!-- =====================================================
     OUT OF STOCK POPUP MODAL
====================================================== -->
<div id="outOfStockModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:9999; justify-content:center; align-items:center; padding:20px; box-sizing:border-box;">
    <div style="background:#f5f0df; width:min(480px, 100%); padding:35px; border:1px solid #ded6b9; position:relative; box-shadow:0 15px 35px rgba(0,0,0,0.25); text-align:center; border-radius:4px;">
        <div style="width:60px; height:60px; border-radius:50%; background:#fae6df; color:#a54129; display:flex; align-items:center; justify-content:center; font-size:28px; margin:0 auto 18px;">⚠️</div>
        <h2 style="font-family:Georgia,serif; font-size:26px; color:#122017; margin:0 0 10px;">Out of Stock</h2>
        <p id="outOfStockMsg" style="color:#596054; line-height:1.6; font-size:15px; margin-bottom:25px;">
            We're sorry, this harvest crop is currently sold out and out of stock. Please check back later or explore other fresh listings from local farmers.
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
</script>



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
                products and farm equipment.

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


<script src="js/cart.js"></script>
</body>

</html>

<?php

$stmt->close();
$conn->close();

?>