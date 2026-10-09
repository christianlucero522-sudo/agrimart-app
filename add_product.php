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


if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {
    header('Location: admin_dashboard.php');
    exit;
}


$userId = (int) $_SESSION['user_id'];

$fullName = $_SESSION['full_name'] ?? 'User';

$parts = explode(
    ' ',
    trim($fullName)
);

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
   GET PRODUCT CATEGORIES
========================================================= */

$categories = [];

$categorySql = "
    SELECT
        category_id,
        category_name

    FROM categories

    WHERE category_type = 'seed'

    ORDER BY category_name ASC
";


$categoryResult = $conn->query(
    $categorySql
);


if ($categoryResult) {

    while (
        $category =
            $categoryResult->fetch_assoc()
    ) {

        $categories[] = $category;

    }

}


/* =========================================================
   DEFAULT FORM VALUES
========================================================= */

$productName = '';

$categoryId = 0;

$description = '';

$price = '';

$quantity = '';

$unit = '';

$errorMessage = '';

$successMessage = '';


/* =========================================================
   PROCESS FORM
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $productName = trim(
        $_POST['product_name'] ?? ''
    );


    $categoryId = isset(
        $_POST['category_id']
    )
        ? (int) $_POST['category_id']
        : 0;


    $description = trim(
        $_POST['description'] ?? ''
    );


    $price = trim(
        $_POST['price'] ?? ''
    );


    $quantity = trim(
        $_POST['quantity'] ?? ''
    );


    $unit = trim(
        $_POST['unit'] ?? ''
    );


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (!$isVerified) {

        $errorMessage =
            'Account Verification Required: You must complete Government ID and Face Verification before listing products for sale.';

    } elseif ($productName === '') {

        $errorMessage =
            'Product name is required.';

    } elseif ($categoryId <= 0) {

        $errorMessage =
            'Please select a category.';

    } elseif (
        $price === '' ||
        !is_numeric($price) ||
        (float) $price < 0
    ) {

        $errorMessage =
            'Please enter a valid price.';

    } elseif (
        $quantity === '' ||
        !ctype_digit($quantity)
    ) {

        $errorMessage =
            'Please enter a valid quantity.';

    } elseif ((int) $quantity < 0) {

        $errorMessage =
            'Quantity cannot be negative.';

    } else {


        /* =================================================
           VERIFY CATEGORY
        ================================================= */

        $verifyCategorySql = "
            SELECT category_id
            FROM categories
            WHERE category_id = ?
              AND category_type = 'seed'
            LIMIT 1
        ";


        $verifyCategoryStmt =
            $conn->prepare(
                $verifyCategorySql
            );


        if (!$verifyCategoryStmt) {

            die(
                'Unable to verify category.'
            );

        }


        $verifyCategoryStmt->bind_param(
            'i',
            $categoryId
        );


        $verifyCategoryStmt->execute();


        $verifyCategoryResult =
            $verifyCategoryStmt
                ->get_result();


        $validCategory =
            $verifyCategoryResult
                ->fetch_assoc();


        $verifyCategoryStmt->close();


        if (!$validCategory) {

            $errorMessage =
                'Invalid product category.';

        }

    }


    /* =====================================================
       IMAGE UPLOAD
    ===================================================== */

    $imagePath = null;


    if (
        $errorMessage === '' &&
        isset($_FILES['product_image']) &&
        $_FILES['product_image']['error']
            !== UPLOAD_ERR_NO_FILE
    ) {


        if (
            $_FILES['product_image']['error']
            !== UPLOAD_ERR_OK
        ) {

            $errorMessage =
                'Unable to upload image.';

        } else {


            $allowedMimeTypes = [

                'image/jpeg' => 'jpg',

                'image/png' => 'png',

                'image/webp' => 'webp'

            ];


            $tmpName =
                $_FILES['product_image']['tmp_name'];


            $fileInfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mimeType =
                $fileInfo->file(
                    $tmpName
                );


            if (
                !isset(
                    $allowedMimeTypes[
                        $mimeType
                    ]
                )
            ) {

                $errorMessage =
                    'Only JPG, PNG, and WEBP images are allowed.';

            } elseif (
                $_FILES['product_image']['size']
                > 5 * 1024 * 1024
            ) {

                $errorMessage =
                    'Image must be 5 MB or smaller.';

            } else {


                $uploadDirectory =
                    __DIR__
                    . '/uploads/products/';


                if (
                    !is_dir(
                        $uploadDirectory
                    )
                ) {

                    mkdir(
                        $uploadDirectory,
                        0755,
                        true
                    );

                }


                $extension =
                    $allowedMimeTypes[
                        $mimeType
                    ];


                $fileName =
                    bin2hex(
                        random_bytes(16)
                    )
                    . '.'
                    . $extension;


                $destination =
                    $uploadDirectory
                    . $fileName;


                if (
                    !move_uploaded_file(
                        $tmpName,
                        $destination
                    )
                ) {

                    $errorMessage =
                        'Failed to save product image.';

                } else {


                    $imagePath =
                        'uploads/products/'
                        . $fileName;

                }

            }

        }

    }


    /* =====================================================
       INSERT PRODUCT
    ===================================================== */

    if ($errorMessage === '') {


        $priceValue =
            (float) $price;


        $quantityValue =
            (int) $quantity;


        $insertSql = "
            INSERT INTO products (
                user_id,
                category_id,
                product_name,
                description,
                price,
                quantity,
                unit,
                image_url,
                status
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'active'
            )
        ";


        $insertStmt =
            $conn->prepare(
                $insertSql
            );


        if (!$insertStmt) {

            die(
                'Unable to create product listing.'
            );

        }


        $insertStmt->bind_param(

            'iissdiss',

            $userId,
            $categoryId,
            $productName,
            $description,
            $priceValue,
            $quantityValue,
            $unit,
            $imagePath

        );


        $insertStmt->execute();


        $newProductId =
            $insertStmt->insert_id;


        $insertStmt->close();


        /* RESET FORM */

        $productName = '';

        $categoryId = 0;

        $description = '';

        $price = '';

        $quantity = '';

        $unit = '';


        $successMessage =
            'Product listing created successfully.';

    }

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
        Sell a Product — AgriMart
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <style>

        .site-header {
            background:
                var(--forest-950)
                !important;

            background-image:
                none
                !important;
        }


        .dashboard-user {

            color: white;

            font-size: 14px;
        }


        .dashboard-user strong {

            color: #d4b65a;
        }


        .product-form-page {

            min-height: 100vh;

            padding:
                120px 20px 90px;

            background:
                #f4f0df;
        }


        .product-form-wrap {

            width:
                min(900px, 100%);

            margin:
                0 auto;
        }


        .form-header {

            margin-bottom:
                35px;
        }


        .form-header .eyebrow {

            display: block;

            margin-bottom: 10px;

            color: #768047;

            font-family:
                monospace;

            font-size: 12px;

            letter-spacing: 2px;

            text-transform:
                uppercase;
        }


        .form-header h1 {

            margin:
                0 0 14px;

            color:
                #122017;

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


        .form-header p {

            max-width:
                650px;

            margin: 0;

            color:
                #6b6a59;

            line-height: 1.7;
        }


        .form-card {

            padding: 35px;

            background:
                white;

            border:
                1px solid
                #ded6b9;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 22px;
        }


        .form-group {

            display: flex;

            flex-direction: column;

            gap: 8px;
        }


        .form-group-full {
            grid-column:
                1 / -1;
        }


        .form-group label {

            color:
                #172017;

            font-size:
                12px;

            font-weight:
                600;

            letter-spacing:
                .8px;

            text-transform:
                uppercase;
        }


        .form-control {

            width: 100%;

            min-height: 48px;

            padding:
                12px 14px;

            box-sizing:
                border-box;

            border:
                1px solid
                #cfc7ab;

            background:
                #fff;

            color:
                #172017;

            font-family:
                inherit;

            font-size:
                14px;
        }


        textarea.form-control {

            min-height:
                140px;

            resize:
                vertical;
        }


        .form-control:focus {

            outline:
                2px solid
                #d4b65a;

            outline-offset:
                1px;
        }


        .file-note {

            margin-top:
                4px;

            color:
                #817c6c;

            font-size:
                11px;
        }


        .form-message {

            margin-bottom:
                25px;

            padding:
                15px 18px;

            font-size:
                14px;
        }


        .form-success {

            background:
                #e8eedf;

            border:
                1px solid
                #cbd8bd;

            color:
                #173723;
        }


        .form-error {

            background:
                #f4e4dc;

            border:
                1px solid
                #d9b8a8;

            color:
                #762f22;
        }


        .form-actions {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 12px;

            margin-top:
                30px;
        }


        .back-dashboard {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-height:
                48px;

            padding:
                0 20px;

            box-sizing:
                border-box;

            border:
                1px solid
                #172017;

            color:
                #172017;

            text-decoration:
                none;

            font-size:
                12px;

            letter-spacing:
                1px;

            text-transform:
                uppercase;
        }


        .back-dashboard:hover {

            background:
                #172017;

            color:
                #f4f0df;
        }


        @media (
            max-width: 700px
        ) {

            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .form-group-full {

                grid-column:
                    auto;
            }


            .form-card {

                padding:
                    25px;
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
     ADD PRODUCT
========================================================= -->

<main class="product-form-page">

<div class="product-form-wrap">


    <div class="form-header">


        <span class="eyebrow">
            Seller Marketplace
        </span>


        <h1>
            Sell a Product
        </h1>


        <p>

            Add seeds or agricultural products
            to the AgriMart marketplace.
            Your listing will be connected to
            your current user account.

        </p>


    </div>



    <?php if (
        $successMessage !== ''
    ): ?>


        <div
            class="
                form-message
                form-success
            "
        >

            <?= htmlspecialchars(
                $successMessage,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>


    <?php endif; ?>



    <?php if (
        $errorMessage !== ''
    ): ?>


        <div
            class="
                form-message
                form-error
            "
        >

            <?= htmlspecialchars(
                $errorMessage,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>


    <?php endif; ?>



    <?php if (!$isVerified): ?>
        <!-- VERIFICATION GATE -->
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:24px; margin-bottom:25px; border-radius:4px;">
            <div style="display:flex; align-items:flex-start; gap:14px;">
                <svg viewBox="0 0 24 24" style="width:28px; height:28px; stroke:#a54129; fill:none; stroke-width:2; flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <div>
                    <strong style="font-size:16px; display:block; margin-bottom:4px;">Seller Identity Verification Required</strong>
                    <p style="margin:0 0 12px; font-size:13px; line-height:1.5; color:#6b3527;">
                        To protect farmers, buyers, and maintain marketplace trust, all sellers must complete Government ID and live Face Biometrics verification before publishing products for sale.
                    </p>
                    <a href="profile.php#verification" class="btn btn-solid" style="padding:8px 18px; font-size:12px; background:#a54129; border-color:#a54129; color:#fff; text-decoration:none; display:inline-block;">
                        Complete Profile & Face Verification Now →
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-card">


        <form
            method="POST"
            action="add_product.php"
            enctype="multipart/form-data"
        >

            <fieldset <?= !$isVerified ? 'disabled style="opacity:0.6;"' : '' ?> style="border:none; padding:0; margin:0;">


            <div class="form-grid">


                <!-- PRODUCT NAME -->

                <div
                    class="
                        form-group
                        form-group-full
                    "
                >


                    <label
                        for="product_name"
                    >
                        Product Name
                    </label>


                    <input
                        type="text"
                        id="product_name"
                        name="product_name"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            $productName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

                        placeholder="Example: Native Tomato Seeds"

                        maxlength="150"

                        required
                    >


                </div>



                <!-- CATEGORY -->

                <div class="form-group">


                    <label
                        for="category_id"
                    >
                        Category
                    </label>


                    <select
                        id="category_id"
                        name="category_id"
                        class="form-control"
                        required
                    >


                        <option value="">
                            Select Category
                        </option>


                        <?php foreach (
                            $categories
                            as $category
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


                </div>



                <!-- UNIT -->

                <div class="form-group">


                    <label
                        for="unit"
                    >
                        Unit
                    </label>


                    <input
                        type="text"
                        id="unit"
                        name="unit"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            $unit,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

                        placeholder="pack, kg, sack, piece"

                        maxlength="50"
                    >


                </div>



                <!-- PRICE -->

                <div class="form-group">


                    <label
                        for="price"
                    >
                        Price
                    </label>


                    <input
                        type="number"
                        id="price"
                        name="price"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            $price,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

                        placeholder="0.00"

                        min="0"

                        step="0.01"

                        required
                    >


                </div>



                <!-- QUANTITY -->

                <div class="form-group">


                    <label
                        for="quantity"
                    >
                        Quantity
                    </label>


                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            $quantity,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

                        placeholder="0"

                        min="0"

                        step="1"

                        required
                    >


                </div>



                <!-- DESCRIPTION -->

                <div
                    class="
                        form-group
                        form-group-full
                    "
                >


                    <label
                        for="description"
                    >
                        Description
                    </label>


                    <textarea
                        id="description"
                        name="description"
                        class="form-control"

                        placeholder="Describe your product..."
                    ><?= htmlspecialchars(
                        $description,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>


                </div>



                <!-- IMAGE -->

                <div
                    class="
                        form-group
                        form-group-full
                    "
                >


                    <label
                        for="product_image"
                    >
                        Product Image
                    </label>


                    <input
                        type="file"
                        id="product_image"
                        name="product_image"
                        class="form-control"

                        accept="
                            image/jpeg,
                            image/png,
                            image/webp
                        "
                    >


                    <span class="file-note">

                        JPG, PNG, or WEBP.
                        Maximum file size: 5 MB.

                    </span>


                </div>


            </div>



            <!-- =================================================
                 ACTIONS
            ================================================== -->

            <div class="form-actions">


                <button
                    type="submit"
                    class="btn btn-solid"
                >
                    Create Listing
                </button>


                <a
                    href="dashboard.php"
                    class="back-dashboard"
                >
                    ← Back to Dashboard
                </a>


            </div>


            </fieldset>

        </form>


    </div>


</div>

</main>


</body>

</html>


<?php

$conn->close();

?>