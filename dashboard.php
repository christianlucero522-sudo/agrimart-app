<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'User';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'User';
$initial = strtoupper(substr($firstName, 0, 1));

// Fetch User Verification & Profile Info
$uStmt = $conn->prepare("SELECT email, phone, role, is_verified, id_type, created_at FROM users WHERE user_id = ? LIMIT 1");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$userInfo = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$email = $userInfo['email'] ?? '';
$isVerified = $userInfo['is_verified'] ?? 'pending';

// Real-time Metrics
$cartCount = (int)($conn->query("SELECT COALESCE(SUM(quantity), 0) AS c FROM cart_items ci INNER JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = $userId")->fetch_assoc()['c'] ?? 0);
$ordersCount = (int)($conn->query("SELECT COUNT(*) AS c FROM orders WHERE buyer_id = $userId")->fetch_assoc()['c'] ?? 0);
$rentalsCount = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE renter_id = $userId")->fetch_assoc()['c'] ?? 0);
$productsCount = (int)($conn->query("SELECT COUNT(*) AS c FROM products WHERE user_id = $userId")->fetch_assoc()['c'] ?? 0);
$equipCount = (int)($conn->query("SELECT COUNT(*) AS c FROM equipment WHERE user_id = $userId")->fetch_assoc()['c'] ?? 0);
$salesCount = (int)($conn->query("SELECT COUNT(DISTINCT oi.order_id) AS c FROM order_items oi WHERE oi.seller_id = $userId")->fetch_assoc()['c'] ?? 0);
$unreadNotifs = (int)($conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id = $userId AND is_read = 0")->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body class="dashboard-body">

<!-- =========================================================
     TOPBAR
========================================================= -->
<header class="dash-topbar">
    <div class="dash-topbar-left">
        <button type="button" class="dash-menu-toggle" id="toggleSidebar" aria-label="Toggle Navigation Menu">
            <svg viewBox="0 0 24 24" style="width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:2;"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="index.php" class="dash-brand">
            <svg class="wheat-mark" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" style="width:28px; height:28px;" aria-hidden="true">
                <path d="M20 4v30"/>
                <path d="M20 10 L12 5 M20 10 L28 5"/>
                <path d="M20 16 L11 10 M20 16 L29 10"/>
                <path d="M20 22 L11 16 M20 22 L29 16"/>
                <path d="M20 28 L13 23 M20 28 L27 23"/>
            </svg>
            <div>
                <b>AgriMart</b>
                <span>Dashboard</span>
            </div>
        </a>
    </div>

    <div class="dash-topbar-right">
        <!-- Cart -->
        <a href="cart.php" class="dash-icon-btn" title="View Cart">
            <svg class="dash-icon-svg" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span>Cart</span>
            <?php if ($cartCount > 0): ?>
                <span class="dash-badge-count" id="cartCount"><?= $cartCount ?></span>
            <?php else: ?>
                <span class="dash-badge-count" id="cartCount" style="display:none;">0</span>
            <?php endif; ?>
        </a>

        <!-- Notifications -->
        <a href="notifications.php" class="dash-icon-btn" title="Notifications">
            <svg class="dash-icon-svg" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Alerts</span>
            <?php if ($unreadNotifs > 0): ?>
                <span class="dash-badge-count"><?= $unreadNotifs ?></span>
            <?php endif; ?>
        </a>

        <!-- YouTube-Style Profile Menu Trigger -->
        <div style="position:relative;">
            <button type="button" class="dash-user-pill-btn" id="userMenuBtn" aria-expanded="false">
                <div class="dash-user-avatar"><?= $initial ?></div>
                <span><?= htmlspecialchars($firstName) ?></span>
                <svg viewBox="0 0 24 24" style="width:12px; height:12px; stroke:currentColor; fill:none; stroke-width:2;"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <!-- YOUTUBE-STYLE DROPDOWN POPUP MENU (Clean, No Emojis) -->
            <div class="yt-dropdown-menu" id="userDropdownMenu">
                <!-- Header -->
                <div class="yt-menu-header">
                    <div class="yt-menu-header-avatar"><?= $initial ?></div>
                    <div class="yt-menu-header-info">
                        <div class="name"><?= htmlspecialchars($fullName) ?></div>
                        <div class="email"><?= htmlspecialchars($email) ?></div>
                    </div>
                </div>

                <!-- Section: Buy & Marketplace -->
                <div class="yt-menu-section">
                    <span class="yt-menu-section-title">Buy & Marketplace</span>
                    <a href="products.php" class="yt-menu-item">
                        <span>Buy Products</span>
                    </a>
                    <a href="equipment.php" class="yt-menu-item">
                        <span>Rent Machinery</span>
                    </a>
                    <a href="cart.php" class="yt-menu-item">
                        <span>Shopping Cart</span>
                        <?php if ($cartCount > 0): ?>
                            <span class="yt-menu-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="orders.php" class="yt-menu-item">
                        <span>My Placed Orders</span>
                        <?php if ($ordersCount > 0): ?>
                            <span class="yt-menu-badge"><?= $ordersCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="bookings.php" class="yt-menu-item">
                        <span>Equipment Rentals</span>
                    </a>
                </div>

                <!-- Section: Sell & Fleet Studio -->
                <div class="yt-menu-section">
                    <span class="yt-menu-section-title">Seller & Fleet Studio</span>
                    <a href="add_product.php" class="yt-menu-item">
                        <span>Sell a Product</span>
                    </a>
                    <a href="my_products.php" class="yt-menu-item">
                        <span>My Product Listings</span>
                    </a>
                    <a href="seller_orders.php" class="yt-menu-item">
                        <span>Customer Sales</span>
                        <?php if ($salesCount > 0): ?>
                            <span class="yt-menu-badge"><?= $salesCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="add_equipment.php" class="yt-menu-item">
                        <span>List Equipment</span>
                    </a>
                    <a href="my_equipment.php" class="yt-menu-item">
                        <span>My Fleet</span>
                    </a>
                    <a href="rental_requests.php" class="yt-menu-item">
                        <span>Rental Requests</span>
                    </a>
                </div>

                <!-- Section: Account & Notifications -->
                <div class="yt-menu-section">
                    <span class="yt-menu-section-title">Account & Settings</span>
                    <a href="notifications.php" class="yt-menu-item">
                        <span>Notifications</span>
                        <?php if ($unreadNotifs > 0): ?>
                            <span class="yt-menu-badge"><?= $unreadNotifs ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="yt-menu-item">
                        <span>My Profile & Address</span>
                    </a>
                    <a href="reviews.php" class="yt-menu-item">
                        <span>Ratings & Reviews</span>
                    </a>
                </div>

                <!-- Sign Out -->
                <div class="yt-menu-section">
                    <a href="logout.php" class="yt-menu-item" style="color:#ef9a9a;">
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- =========================================================
     COLLAPSIBLE SIDEBAR MENU (Clean, No Emojis)
========================================================= -->
<aside class="dash-sidebar" id="dashSidebar">
    <!-- Marketplace Main -->
    <div class="dash-nav-section">
        <span class="dash-nav-heading">Marketplace</span>
        <a href="index.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span class="dash-nav-text">Home Feed</span>
        </a>
        <a href="products.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <span class="dash-nav-text">Buy Products</span>
        </a>
        <a href="equipment.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <span class="dash-nav-text">Rent Equipment</span>
        </a>
        <a href="cart.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span class="dash-nav-text">Shopping Cart</span>
            <?php if ($cartCount > 0): ?>
                <span class="dash-nav-badge"><?= $cartCount ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            <span class="dash-nav-text">My Placed Orders</span>
            <?php if ($ordersCount > 0): ?>
                <span class="dash-nav-badge"><?= $ordersCount ?></span>
            <?php endif; ?>
        </a>
        <a href="bookings.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="dash-nav-text">My Equipment Rentals</span>
            <?php if ($rentalsCount > 0): ?>
                <span class="dash-nav-badge"><?= $rentalsCount ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Seller & Fleet Studio -->
    <div class="dash-nav-section">
        <span class="dash-nav-heading">Seller & Fleet Studio</span>
        <a href="add_product.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span class="dash-nav-text">Sell a Product</span>
        </a>
        <a href="my_products.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span class="dash-nav-text">My Product Listings</span>
            <?php if ($productsCount > 0): ?>
                <span class="dash-nav-badge"><?= $productsCount ?></span>
            <?php endif; ?>
        </a>
        <a href="seller_orders.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span class="dash-nav-text">Customer Sales Orders</span>
            <?php if ($salesCount > 0): ?>
                <span class="dash-nav-badge"><?= $salesCount ?></span>
            <?php endif; ?>
        </a>
        <a href="add_equipment.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            <span class="dash-nav-text">List Equipment</span>
        </a>
        <a href="my_equipment.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span class="dash-nav-text">My Equipment Fleet</span>
            <?php if ($equipCount > 0): ?>
                <span class="dash-nav-badge"><?= $equipCount ?></span>
            <?php endif; ?>
        </a>
        <a href="rental_requests.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            <span class="dash-nav-text">Rental Requests</span>
        </a>
    </div>

    <!-- Account & Settings -->
    <div class="dash-nav-section">
        <span class="dash-nav-heading">Account & System</span>
        <a href="profile.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="dash-nav-text">My Profile & Address</span>
        </a>
        <a href="reviews.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span class="dash-nav-text">Reviews & Ratings</span>
        </a>
        <a href="notifications.php" class="dash-nav-link">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="dash-nav-text">Notifications</span>
            <?php if ($unreadNotifs > 0): ?>
                <span class="dash-nav-badge"><?= $unreadNotifs ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php" class="dash-nav-link" style="color:#ef9a9a;">
            <svg class="dash-nav-icon-svg" viewBox="0 0 24 24" style="stroke:#ef9a9a;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="dash-nav-text">Sign Out</span>
        </a>
    </div>
</aside>

<!-- =========================================================
     MAIN CONTENT & DIRECT CLEAN SECTIONS (NO ACCORDIONS)
========================================================= -->
<main class="dash-main-content">
    <div class="dash-content-wrap">

        <!-- Welcome Banner -->
        <div class="dash-hero-banner">
            <div>
                <span style="font-family:monospace; color:var(--wheat-300, #d6b95f); font-size:11px; text-transform:uppercase; letter-spacing:2px;">
                    Digital Agricultural Gateway
                </span>
                <h1>Welcome back, <?= htmlspecialchars($fullName) ?>!</h1>
                <p>Manage your harvest sales, equipment bookings, orders, and customer inquiries from your command center.</p>
            </div>
            <div>
                <?php if ($isVerified === 'verified'): ?>
                    <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:10px 16px; font-weight:700; font-size:13px; border-radius:4px; display:inline-flex; align-items:center; gap:6px;">
                        Verified AgriMart Member
                    </div>
                <?php elseif ($isVerified === 'pending'): ?>
                    <div style="background:#efe2b7; border:1px solid #d4c79c; color:#6e5817; padding:10px 16px; font-weight:600; font-size:13px; border-radius:4px; display:inline-flex; align-items:center; gap:6px;">
                        Valid ID Under Review
                    </div>
                <?php else: ?>
                    <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:10px 16px; font-weight:600; font-size:13px; border-radius:4px; display:inline-flex; align-items:center; gap:6px;">
                        Verification Required
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Metrics Overview -->
        <div class="dash-metrics-grid">
            <div class="dash-metric-card">
                <div class="dash-metric-icon">
                    <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
                <div class="dash-metric-info">
                    <span class="label">Orders Placed</span>
                    <div class="val"><?= $ordersCount ?></div>
                </div>
            </div>

            <div class="dash-metric-card">
                <div class="dash-metric-icon">
                    <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                </div>
                <div class="dash-metric-info">
                    <span class="label">Machinery Rentals</span>
                    <div class="val"><?= $rentalsCount ?></div>
                </div>
            </div>

            <div class="dash-metric-card">
                <div class="dash-metric-icon">
                    <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                </div>
                <div class="dash-metric-info">
                    <span class="label">Product Listings</span>
                    <div class="val"><?= $productsCount ?></div>
                </div>
            </div>

            <div class="dash-metric-card">
                <div class="dash-metric-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="dash-metric-info">
                    <span class="label">Customer Sales</span>
                    <div class="val"><?= $salesCount ?></div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: BUY & MARKETPLACE (Clean direct view, no dropdown accordions) -->
        <section class="dash-section">
            <div class="dash-section-header">
                <h2>Buy & Marketplace</h2>
                <p>Browse produce listings, rent agricultural machinery, and track your purchase orders.</p>
            </div>
            <div class="dash-cards-grid">
                <a href="products.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">01</span>
                        <h3>Buy Agricultural Products</h3>
                        <p>Browse seeds, fertilizer, and agricultural crops posted by verified farmers.</p>
                    </div>
                    <span class="card-arrow">Browse Catalog →</span>
                </a>

                <a href="equipment.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">02</span>
                        <h3>Rent Heavy Machinery & Tools</h3>
                        <p>Hire tractors, harvesters, irrigation pumps, and rotavators with daily rates.</p>
                    </div>
                    <span class="card-arrow">Browse Machinery →</span>
                </a>

                <a href="orders.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">03</span>
                        <h3>Track My Placed Orders</h3>
                        <p>Review fulfillment status, shipment progress, and electronic receipts for your purchases.</p>
                    </div>
                    <span class="card-arrow">View Orders →</span>
                </a>

                <a href="bookings.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">04</span>
                        <h3>My Equipment Bookings</h3>
                        <p>Monitor rental schedules, pickup/dropoff details, and owner contact information.</p>
                    </div>
                    <span class="card-arrow">View Bookings →</span>
                </a>
            </div>
        </section>

        <!-- SECTION 2: SELLER & FLEET STUDIO -->
        <section class="dash-section">
            <div class="dash-section-header">
                <h2>Seller & Fleet Studio</h2>
                <p>Manage your harvest listings, fulfill customer purchase orders, and manage machinery rentals.</p>
            </div>
            <div class="dash-cards-grid">
                <a href="add_product.php" class="dash-feature-card" style="border-left: 3px solid #768047;">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">05</span>
                        <h3>List Seed / Produce for Sale</h3>
                        <p>Upload new harvest crops, set prices per kilogram, and specify available stock.</p>
                    </div>
                    <span class="card-arrow">Add Product Listing →</span>
                </a>

                <a href="seller_orders.php" class="dash-feature-card" style="border-left: 3px solid #768047;">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">06</span>
                        <h3>Customer Orders (Sales)</h3>
                        <p>Process incoming buyer orders on your seeds, confirm orders, and mark them shipped.</p>
                    </div>
                    <span class="card-arrow">Manage Customer Orders →</span>
                </a>

                <a href="add_equipment.php" class="dash-feature-card" style="border-left: 3px solid #768047;">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">07</span>
                        <h3>List Machinery for Rent</h3>
                        <p>Put your idle tractors, tillers, and threshers to work by offering them for rental.</p>
                    </div>
                    <span class="card-arrow">List New Machinery →</span>
                </a>

                <a href="rental_requests.php" class="dash-feature-card" style="border-left: 3px solid #768047;">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">08</span>
                        <h3>Machinery Rental Requests</h3>
                        <p>Approve incoming booking requests, schedule rental dates, and manage machine dispatch.</p>
                    </div>
                    <span class="card-arrow">View Rental Requests →</span>
                </a>
            </div>
        </section>

        <!-- SECTION 3: ACCOUNT & MANAGEMENT -->
        <section class="dash-section">
            <div class="dash-section-header">
                <h2>Account & Management</h2>
                <p>Update your personal information, address, security credentials, and star ratings.</p>
            </div>
            <div class="dash-cards-grid">
                <a href="profile.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">09</span>
                        <h3>Personal Profile & Delivery Address</h3>
                        <p>Update your contact phone, delivery address, barangay, and account password.</p>
                    </div>
                    <span class="card-arrow">Edit Profile →</span>
                </a>

                <a href="reviews.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">10</span>
                        <h3>Reviews & Star Ratings</h3>
                        <p>Read customer reviews on your harvest and check ratings on machinery you've rented.</p>
                    </div>
                    <span class="card-arrow">View Ratings →</span>
                </a>

                <a href="notifications.php" class="dash-feature-card">
                    <div>
                        <span style="font-family:monospace; font-size:11px; color:#768047; font-weight:700;">11</span>
                        <h3>Notifications & Alerts</h3>
                        <p>View system updates, payment confirmations, and booking status alerts.</p>
                    </div>
                    <span class="card-arrow">Check Notifications →</span>
                </a>
            </div>
        </section>

    </div>
</main>

<script>
// Sidebar Toggle
const toggleBtn = document.getElementById('toggleSidebar');
if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
        if (window.innerWidth > 900) {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('agrimart_sidebar_collapsed', document.body.classList.contains('sidebar-collapsed'));
        } else {
            document.body.classList.toggle('sidebar-open');
        }
    });

    if (window.innerWidth > 900 && localStorage.getItem('agrimart_sidebar_collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
}

// Top-Right Profile Dropdown Menu
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdownMenu = document.getElementById('userDropdownMenu');

if (userMenuBtn && userDropdownMenu) {
    userMenuBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdownMenu.classList.toggle('show');
        userMenuBtn.setAttribute('aria-expanded', userDropdownMenu.classList.contains('show'));
    });

    document.addEventListener('click', function(e) {
        if (!userDropdownMenu.contains(e.target) && e.target !== userMenuBtn) {
            userDropdownMenu.classList.remove('show');
            userMenuBtn.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>

</body>
</html>
