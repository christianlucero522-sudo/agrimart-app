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

$parts = explode(' ', trim($fullName));

$firstName = $parts[0] ?? 'User';


/* =========================================================
   GET PRODUCT ID
========================================================= */

$productId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($productId <= 0) {
    header('Location: my_products.php');
    exit;
}


/* =========================================================
   GET PRODUCT
   IMPORTANT: VERIFY OWNERSHIP
========================================================= */

$productSql = "
    SELECT
        product_id,
        category_id,
        product_name,
        description,
        price,
        quantity,
        unit,
        image_url,
        status

    FROM products

    WHERE product_id = ?
      AND user_id = ?

    LIMIT 1
";


$productStmt = $conn->prepare($productSql);


if (!$productStmt) {
    die('Unable to load product.');
}


$productStmt->bind_param(
    'ii',
    $productId,
    $userId
);


$productStmt->execute();

$productResult = $productStmt->get_result();

$product = $productResult->fetch_assoc();


$productStmt->close();


/* =========================================================
   PRODUCT NOT FOUND / NOT OWNER
========================================================= */

if (!$product) {
    header('Location: my_products.php?error=not_found');
    exit;
}


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


$categoryResult = $conn->query($categorySql);


if ($categoryResult) {

    while (
        $category =
            $categoryResult->fetch_assoc()
    ) {

        $categories[] = $category;

    }

}


/* =========================================================
   FORM VALUES
========================================================= */

$productName =
    $product['product_name'];

$categoryId =
    (int) $product['category_id'];

$description =
    $product['description'] ?? '';

$price =
    $product['price'];

$quantity =
    $product['quantity'];

$unit =
    $product['unit'] ?? '';

$currentImage =
    $product['image_url'] ?? '';

$errorMessage = '';

$successMessage = '';


/* =========================================================
   PROCESS UPDATE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =====================================================
       GET FORM DATA
    ===================================================== */

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

    if ($productName === '') {

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

    }


    /* =====================================================
       VERIFY CATEGORY
    ===================================================== */

    if ($errorMessage === '') {


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
            die('Unable to verify category.');
        }


        $verifyCategoryStmt->bind_param(
            'i',
            $categoryId
        );


        $verifyCategoryStmt->execute();


        $verifyCategoryResult =
            $verifyCategoryStmt->get_result();


        $validCategory =
            $verifyCategoryResult->fetch_assoc();


        $verifyCategoryStmt->close();


        if (!$validCategory) {

            $errorMessage =
                'Invalid product category.';

        }

    }


    /* =====================================================
       IMAGE
    ===================================================== */

    $newImagePath = $currentImage;


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
                    $allowedMimeTypes[$mimeType]
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
                        'Failed to save the new image.';

                } else {


                    $newImagePath =
                        'uploads/products/'
                        . $fileName;

                }

            }

        }

    }


    /* =====================================================
       UPDATE PRODUCT
    ===================================================== */

    if ($errorMessage === '') {


        $priceValue =
            (float) $price;


        $quantityValue =
            (int) $quantity;


        $updateSql = "
            UPDATE products

            SET
                category_id = ?,
                product_name = ?,
                description = ?,
                price = ?,
                quantity = ?,
                unit = ?,
                image_url = ?

            WHERE product_id = ?
              AND user_id = ?
        ";


        $updateStmt =
            $conn->prepare(
                $updateSql
            );


        if (!$updateStmt) {
            die('Unable to update product.');
        }


        $updateStmt->bind_param(

            'issdissii',

            $categoryId,
            $productName,
            $description,
            $priceValue,
            $quantityValue,
            $unit,
            $newImagePath,
            $productId,
            $userId

        );


        $updateStmt->execute();


        $updateStmt->close();


        $currentImage =
            $newImagePath;


        $successMessage =
            'Product updated successfully.';

    }

}


/* =========================================================
   IMAGE HELPER
========================================================= */

function getEditProductImage($imageUrl)
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
    Edit Product — AgriMart
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


    /* =====================================================
       PAGE
    ===================================================== */

    body {

        margin: 0;

        background: #f4f0df;

        color: #162018;
    }


    .edit-product-page {

        min-height: 100vh;

        padding:
            120px 20px 90px;
    }


    .edit-product-wrap {

        width:
            min(900px, 100%);

        margin:
            0 auto;
    }


    /* =====================================================
       HEADING
    ===================================================== */

    .page-heading {
        margin-bottom: 35px;
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

        margin:
            0 0 14px;

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
       FORM
    ===================================================== */

    .form-card {

        padding: 35px;

        background: white;

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
        grid-column: 1 / -1;
    }


    .form-group label {

        color: #172017;

        font-size: 12px;

        font-weight: 600;

        letter-spacing: .8px;

        text-transform: uppercase;
    }


    .form-control {

        width: 100%;

        min-height: 48px;

        padding: 12px 14px;

        box-sizing: border-box;

        border:
            1px solid
            #cfc7ab;

        background: white;

        color: #172017;

        font-family: inherit;

        font-size: 14px;
    }


    textarea.form-control {

        min-height: 140px;

        resize: vertical;
    }


    .form-control:focus {

        outline:
            2px solid
            #d4b65a;

        outline-offset: 1px;
    }


    /* =====================================================
       CURRENT IMAGE
    ===================================================== */

    .current-image {

        width: 220px;

        height: 160px;

        margin-bottom: 12px;

        background: #173723;

        overflow: hidden;
    }


    .current-image img {

        width: 100%;

        height: 100%;

        object-fit: cover;
    }


    .image-note {

        color: #817c6c;

        font-size: 11px;

        line-height: 1.5;
    }


    /* =====================================================
       MESSAGE
    ===================================================== */

    .form-message {

        margin-bottom: 25px;

        padding: 15px 18px;

        font-size: 14px;
    }


    .form-success {

        background: #e8eedf;

        border:
            1px solid
            #cbd8bd;

        color: #173723;
    }


    .form-error {

        background: #f4e4dc;

        border:
            1px solid
            #d9b8a8;

        color: #762f22;
    }


    /* =====================================================
       ACTIONS
    ===================================================== */

    .form-actions {

        display: flex;

        flex-wrap: wrap;

        align-items: center;

        gap: 12px;

        margin-top: 30px;
    }


    .secondary-action {

        display: inline-flex;

        align-items: center;

        justify-content: center;

        min-height: 48px;

        padding: 0 20px;

        box-sizing: border-box;

        border:
            1px solid
            #173723;

        color: #173723;

        text-decoration: none;

        font-size: 11px;

        letter-spacing: 1px;

        text-transform: uppercase;
    }


    .secondary-action:hover {

        background: #173723;

        color: #f4f0df;
    }


    /* =====================================================
       RESPONSIVE
    ===================================================== */

    @media (max-width: 700px) {

        .form-grid {
            grid-template-columns: 1fr;
        }


        .form-group-full {
            grid-column: auto;
        }


        .form-card {
            padding: 25px;
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
     EDIT PRODUCT
========================================================= -->

<main class="edit-product-page">

<div class="edit-product-wrap">


    <div class="page-heading">

        <span class="eyebrow">
            Seller Marketplace
        </span>


        <h1>
            Edit Product
        </h1>


        <p>

            Update your product information,
            stock, price, description, or image.

        </p>

    </div>



    <!-- SUCCESS -->

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



    <!-- ERROR -->

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



    <div class="form-card">


        <form
            method="POST"
            action="edit_product.php?id=<?= $productId ?>"
            enctype="multipart/form-data"
        >


            <div class="form-grid">


                <!-- PRODUCT NAME -->

                <div
                    class="
                        form-group
                        form-group-full
                    "
                >

                    <label for="product_name">
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

                        maxlength="150"

                        required
                    >

                </div>



                <!-- CATEGORY -->

                <div class="form-group">

                    <label for="category_id">
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

                    <label for="unit">
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

                        maxlength="50"

                        placeholder="pack, kg, sack, piece"
                    >

                </div>



                <!-- PRICE -->

                <div class="form-group">

                    <label for="price">
                        Price
                    </label>


                    <input
                        type="number"
                        id="price"
                        name="price"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            (string) $price,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

                        min="0"

                        step="0.01"

                        required
                    >

                </div>



                <!-- QUANTITY -->

                <div class="form-group">

                    <label for="quantity">
                        Quantity
                    </label>


                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            (string) $quantity,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

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

                    <label for="description">
                        Description
                    </label>


                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
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

                    <label>
                        Current Image
                    </label>


                    <div class="current-image">

                        <img

                            src="<?= htmlspecialchars(
                                getEditProductImage(
                                    $currentImage
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"

                            alt="<?= htmlspecialchars(
                                $productName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"

                        >

                    </div>


                    <label for="product_image">
                        Replace Image
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


                    <span class="image-note">

                        Leave this empty to keep the
                        current image. JPG, PNG, or WEBP
                        only. Maximum size: 5 MB.

                    </span>

                </div>


            </div>



            <!-- ACTIONS -->

            <div class="form-actions">


                <button
                    type="submit"
                    class="btn btn-solid"
                >
                    Save Changes
                </button>


                <a
                    href="product_details.php?id=<?= $productId ?>"
                    class="secondary-action"
                >
                    View Product
                </a>


                <a
                    href="my_products.php"
                    class="secondary-action"
                >
                    ← My Products
                </a>


            </div>


        </form>


    </div>


</div>

</main>


</body>

</html>


<?php

$conn->close();

?>