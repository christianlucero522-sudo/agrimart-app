<?php
session_start();
require_once 'config.php';
require_once 'sms_helper.php';

// Require Admin Login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminFullName = $_SESSION['full_name'] ?? 'Administrator';
$message = '';
$messageType = 'success';

// Handle Moderation & Management Actions (Deactivate, Activate, Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itemType = trim($_POST['item_type'] ?? '');
    $itemId = (int)($_POST['item_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');
    $note = trim($_POST['admin_note'] ?? '');

    if ($itemId > 0) {
        if ($itemType === 'product') {
            $pRes = $conn->query("SELECT p.product_name, p.status, p.user_id, u.phone FROM products p INNER JOIN users u ON p.user_id = u.user_id WHERE p.product_id = $itemId LIMIT 1");
            $pRow = $pRes ? $pRes->fetch_assoc() : null;

            if ($pRow) {
                if ($action === 'deactivate') {
                    $reason = !empty($note) ? $note : 'Deactivated by administrator';
                    $escapedReason = $conn->real_escape_string($reason);
                    $conn->query("UPDATE products SET status = 'inactive', admin_deactivated = 1, retrieval_status = 'rejected', deactivation_reason = '$escapedReason' WHERE product_id = $itemId");

                    $nTitle = "⚠️ Product Listing Deactivated: " . $pRow['product_name'];
                    $nMsg = "Your product listing \"" . $pRow['product_name'] . "\" has been deactivated by administration. Only an administrator can reactivate this listing upon review or retrieval request.";
                    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                    $nStmt->bind_param('issi', $pRow['user_id'], $nTitle, $nMsg, $itemId);
                    $nStmt->execute();
                    $nStmt->close();

                    if (!empty($pRow['phone'])) {
                        sendSmsNotification($conn, $pRow['user_id'], $pRow['phone'], "AgriMart Alert: Your product '{$pRow['product_name']}' was deactivated by admin. Contact admin or submit retrieval request to reactivate.");
                    }
                    $message = "Product #$itemId ('{$pRow['product_name']}') deactivated. Seller notified.";
                    $messageType = 'warning';
                } elseif ($action === 'activate' || $action === 'approve') {
                    $conn->query("UPDATE products SET status = 'active', admin_deactivated = 0, retrieval_status = 'approved', deactivation_reason = NULL WHERE product_id = $itemId");

                    $nTitle = "✅ Product Listing Reactivated: " . $pRow['product_name'];
                    $nMsg = "Your product listing \"" . $pRow['product_name'] . "\" has been reactivated by administration and is now live on the marketplace.";
                    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                    $nStmt->bind_param('issi', $pRow['user_id'], $nTitle, $nMsg, $itemId);
                    $nStmt->execute();
                    $nStmt->close();

                    if (!empty($pRow['phone'])) {
                        sendSmsNotification($conn, $pRow['user_id'], $pRow['phone'], "AgriMart Notice: Your product '{$pRow['product_name']}' has been reactivated and is now live.");
                    }
                    $message = "Product #$itemId ('{$pRow['product_name']}') reactivated and published live.";
                    $messageType = 'success';
                } elseif ($action === 'delete') {
                    $cRes = $conn->query("SELECT COUNT(*) AS c FROM order_items WHERE product_id = $itemId");
                    $c = $cRes ? (int)($cRes->fetch_assoc()['c'] ?? 0) : 0;
                    $conn->query("DELETE FROM cart_items WHERE product_id = $itemId");
                    if ($c > 0) {
                        $conn->query("UPDATE products SET status = 'deleted', quantity = 0 WHERE product_id = $itemId");
                    } else {
                        $conn->query("DELETE FROM products WHERE product_id = $itemId");
                    }
                    $message = "Product #$itemId removed from marketplace.";
                }
            }
        } elseif ($itemType === 'equipment') {
            $eRes = $conn->query("SELECT e.equipment_name, e.status, e.user_id, u.phone FROM equipment e INNER JOIN users u ON e.user_id = u.user_id WHERE e.equipment_id = $itemId LIMIT 1");
            $eRow = $eRes ? $eRes->fetch_assoc() : null;

            if ($eRow) {
                if ($action === 'deactivate') {
                    $reason = !empty($note) ? $note : 'Deactivated by administrator';
                    $escapedReason = $conn->real_escape_string($reason);
                    $conn->query("UPDATE equipment SET status = 'inactive', availability = 'maintenance', admin_deactivated = 1, retrieval_status = 'rejected', deactivation_reason = '$escapedReason' WHERE equipment_id = $itemId");

                    $nTitle = "⚠️ Machinery Listing Deactivated: " . $eRow['equipment_name'];
                    $nMsg = "Your machinery listing \"" . $eRow['equipment_name'] . "\" has been deactivated by administration. Only an administrator can reactivate this listing.";
                    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                    $nStmt->bind_param('issi', $eRow['user_id'], $nTitle, $nMsg, $itemId);
                    $nStmt->execute();
                    $nStmt->close();

                    if (!empty($eRow['phone'])) {
                        sendSmsNotification($conn, $eRow['user_id'], $eRow['phone'], "AgriMart Alert: Your machinery '{$eRow['equipment_name']}' was deactivated by admin.");
                    }
                    $message = "Equipment #$itemId ('{$eRow['equipment_name']}') deactivated. Owner notified.";
                    $messageType = 'warning';
                } elseif ($action === 'activate' || $action === 'approve') {
                    $conn->query("UPDATE equipment SET status = 'active', availability = 'available', admin_deactivated = 0, retrieval_status = 'approved', deactivation_reason = NULL WHERE equipment_id = $itemId");

                    $nTitle = "✅ Machinery Listing Reactivated: " . $eRow['equipment_name'];
                    $nMsg = "Your machinery listing \"" . $eRow['equipment_name'] . "\" has been reactivated by administration and is now live for rental.";
                    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'system', ?)");
                    $nStmt->bind_param('issi', $eRow['user_id'], $nTitle, $nMsg, $itemId);
                    $nStmt->execute();
                    $nStmt->close();

                    if (!empty($eRow['phone'])) {
                        sendSmsNotification($conn, $eRow['user_id'], $eRow['phone'], "AgriMart Notice: Your machinery '{$eRow['equipment_name']}' has been reactivated and is now live.");
                    }
                    $message = "Equipment #$itemId ('{$eRow['equipment_name']}') reactivated and is available for rental.";
                    $messageType = 'success';
                } elseif ($action === 'delete') {
                    $bRes = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE equipment_id = $itemId");
                    $c = $bRes ? (int)($bRes->fetch_assoc()['c'] ?? 0) : 0;
                    if ($c > 0) {
                        $conn->query("UPDATE equipment SET status = 'deleted', availability = 'maintenance' WHERE equipment_id = $itemId");
                    } else {
                        $conn->query("DELETE FROM equipment WHERE equipment_id = $itemId");
                    }
                    $message = "Equipment #$itemId removed from marketplace.";
                }
            }
        }
    }
}

// Fetch All Produce & Equipment Listings
$productList = [];
$equipmentList = [];

// 1. Fetch Products
$pRes = $conn->query("
    SELECT 
        p.product_id AS id,
        'product' AS item_type,
        p.product_name AS name,
        p.price,
        p.quantity,
        p.unit,
        p.image_url,
        p.status,
        p.admin_deactivated,
        p.retrieval_status,
        p.deactivation_reason,
        COALESCE(NULLIF(p.location_city, ''), a.city_municipality, 'Flora') AS location_city,
        COALESCE(NULLIF(p.location_province, ''), a.province, 'Apayao') AS location_province,
        c.category_name,
        u.full_name AS seller_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    INNER JOIN users u ON p.user_id = u.user_id
    LEFT JOIN addresses a ON p.user_id = a.user_id
    WHERE p.status NOT IN ('deleted', 'archived')
    ORDER BY p.product_id DESC
");
if ($pRes) {
    while ($r = $pRes->fetch_assoc()) {
        $productList[] = $r;
    }
}

// 2. Fetch Equipment
$eRes = $conn->query("
    SELECT 
        e.equipment_id AS id,
        'equipment' AS item_type,
        e.equipment_name AS name,
        e.rate_price AS price,
        1 AS quantity,
        COALESCE(e.rate_type, 'daily') AS unit,
        e.image_url,
        e.status,
        e.availability,
        e.admin_deactivated,
        e.retrieval_status,
        e.deactivation_reason,
        COALESCE(NULLIF(e.location_city, ''), a.city_municipality, 'Flora') AS location_city,
        COALESCE(NULLIF(e.location_province, ''), a.province, 'Apayao') AS location_province,
        c.category_name,
        u.full_name AS seller_name
    FROM equipment e
    INNER JOIN categories c ON e.category_id = c.category_id
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN addresses a ON e.user_id = a.user_id
    WHERE e.status NOT IN ('deleted', 'archived')
    ORDER BY e.equipment_id DESC
");
if ($eRes) {
    while ($r = $eRes->fetch_assoc()) {
        $equipmentList[] = $r;
    }
}

$activeNav = 'listings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Listings — AgriMart Admin Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
    <style>
        .notice-banner {
            background: #e6f4ea;
            border: 1px solid #cce8d4;
            color: #137333;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13.5px;
            font-weight: 500;
        }
        .notice-banner.warning {
            background: #fef3c7;
            border-color: #fde68a;
            color: #92400e;
        }
    </style>
</head>
<body class="admin-console">

    <!-- Reusable Admin Sidebar -->
    <?php include __DIR__ . '/admin_sidebar.php'; ?>

    <!-- Main Workspace -->
    <main class="admin-main">

        <?php if (!empty($message)): ?>
            <div class="notice-banner <?= $messageType === 'warning' ? 'warning' : '' ?>">
                <?= $messageType === 'warning' ? '⚠️' : '✓' ?> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Header Topbar -->
        <header class="admin-topbar">
            <div>
                <div class="topbar-subline">Marketplace Catalog</div>
                <h1 class="topbar-heading">Manage Products &amp; Equipment <i>Listings</i></h1>
            </div>

            <div class="topbar-actions">
                <div class="search-input-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px!important;height:16px!important;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="listingSearchInput" class="search-bar-input" placeholder="Search by title, category, seller or owner...">
                </div>
            </div>
        </header>

        <!-- GROUP 1: Seed & Agricultural Produce Listings -->
        <div class="listings-group-card">
            <div class="group-card-head">
                <h2 class="group-title">Seed &amp; Agricultural Produce Listings (<?= count($productList) ?>)</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="listings-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">PRODUCT</th>
                            <th style="width: 18%;">CATEGORY</th>
                            <th style="width: 14%;">SELLER</th>
                            <th style="width: 16%;">PRICE</th>
                            <th style="width: 8%;">STOCK</th>
                            <th style="width: 6%;">STATUS</th>
                            <th style="width: 10%; text-align: right;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productList)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:32px; color:#71717a;">
                                    No agricultural produce listings found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productList as $p): 
                                $isActive = ($p['status'] === 'active' && (int)$p['admin_deactivated'] === 0);
                                $pStatus = $isActive ? 'ACTIVE' : 'INACTIVE';
                                $pBadgeClass = $isActive ? 'active' : 'inactive';
                                $pUnitLabel = !empty($p['unit']) ? ' / ' . htmlspecialchars($p['unit']) : '';
                                $pPriceFormatted = '<strong>₱' . number_format((float)$p['price'], 2) . '</strong>' . $pUnitLabel;
                                $pStockText = (int)$p['quantity'] . ' left';
                                $pSearchText = strtolower(htmlspecialchars($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['seller_name'] ?? '') . ' ' . ($p['location_city'] ?? '') . ' ' . ($p['location_province'] ?? '')));
                            ?>
                                <tr class="listing-row-item" data-search="<?= $pSearchText ?>">
                                    <td>
                                        <span class="item-name-bold"><?= htmlspecialchars($p['name']) ?></span>
                                        <div style="font-size:11.5px; color:#52525b; margin-top:3px; display:flex; align-items:center; gap:3px;">
                                            📍 <?= htmlspecialchars($p['location_city']) ?>, <?= htmlspecialchars($p['location_province']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($p['category_name'] ?: 'General Agriculture') ?></td>
                                    <td><?= htmlspecialchars($p['seller_name'] ?: 'Farmer') ?></td>
                                    <td><?= $pPriceFormatted ?></td>
                                    <td><?= htmlspecialchars($pStockText) ?></td>
                                    <td>
                                        <span class="status-pill-badge <?= $pBadgeClass ?>"><?= $pStatus ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                            <?php if ($isActive): ?>
                                                <form action="admin_listings.php" method="POST" style="margin:0;" onsubmit="return confirm('Deactivate this listing from marketplace?');">
                                                    <input type="hidden" name="item_type" value="product">
                                                    <input type="hidden" name="item_id" value="<?= (int)$p['id'] ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="btn-deact-action">DEACTIVATE</button>
                                                </form>
                                            <?php else: ?>
                                                <form action="admin_listings.php" method="POST" style="margin:0;" onsubmit="return confirm('Reactivate this listing and publish it live?');">
                                                    <input type="hidden" name="item_type" value="product">
                                                    <input type="hidden" name="item_id" value="<?= (int)$p['id'] ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn-activate-action">ACTIVATE</button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="admin_listings.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                                <input type="hidden" name="item_type" value="product">
                                                <input type="hidden" name="item_id" value="<?= (int)$p['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn-delete-action">DELETE</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- GROUP 2: Farm Equipment & Machinery Listings -->
        <div class="listings-group-card">
            <div class="group-card-head">
                <h2 class="group-title">Farm Equipment &amp; Machinery Listings (<?= count($equipmentList) ?>)</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="listings-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">MACHINERY</th>
                            <th style="width: 18%;">CATEGORY</th>
                            <th style="width: 14%;">OWNER</th>
                            <th style="width: 14%;">RENTAL RATE</th>
                            <th style="width: 10%;">AVAILABILITY</th>
                            <th style="width: 6%;">STATUS</th>
                            <th style="width: 10%; text-align: right;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($equipmentList)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:32px; color:#71717a;">
                                    No farm equipment listings found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($equipmentList as $e): 
                                $isActive = ($e['status'] === 'active' && (int)$e['admin_deactivated'] === 0);
                                $eStatus = $isActive ? 'ACTIVE' : 'INACTIVE';
                                $eBadgeClass = $isActive ? 'active' : 'inactive';
                                $eRateLabel = !empty($e['unit']) ? ' / ' . htmlspecialchars($e['unit']) : ' / day';
                                $ePriceFormatted = '<strong>₱' . number_format((float)$e['price'], 2) . '</strong>' . $eRateLabel;
                                $avail = strtolower($e['availability'] ?? 'available');
                                $availBadgeClass = ($avail === 'rented') ? 'rented' : (($avail === 'maintenance') ? 'maintenance' : 'available');
                                $eSearchText = strtolower(htmlspecialchars($e['name'] . ' ' . ($e['category_name'] ?? '') . ' ' . ($e['seller_name'] ?? '') . ' ' . ($e['location_city'] ?? '') . ' ' . ($e['location_province'] ?? '')));
                            ?>
                                <tr class="listing-row-item" data-search="<?= $eSearchText ?>">
                                    <td>
                                        <span class="item-name-bold"><?= htmlspecialchars($e['name']) ?></span>
                                        <div style="font-size:11.5px; color:#52525b; margin-top:3px; display:flex; align-items:center; gap:3px;">
                                            📍 <?= htmlspecialchars($e['location_city']) ?>, <?= htmlspecialchars($e['location_province']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($e['category_name'] ?: 'Machinery & Tools') ?></td>
                                    <td><?= htmlspecialchars($e['seller_name'] ?: 'Owner') ?></td>
                                    <td><?= $ePriceFormatted ?></td>
                                    <td>
                                        <span class="status-pill-badge <?= $availBadgeClass ?>"><?= strtoupper(htmlspecialchars($e['availability'] ?? 'AVAILABLE')) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-pill-badge <?= $eBadgeClass ?>"><?= $eStatus ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                            <?php if ($isActive): ?>
                                                <form action="admin_listings.php" method="POST" style="margin:0;" onsubmit="return confirm('Deactivate this machinery listing from marketplace?');">
                                                    <input type="hidden" name="item_type" value="equipment">
                                                    <input type="hidden" name="item_id" value="<?= (int)$e['id'] ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="btn-deact-action">DEACTIVATE</button>
                                                </form>
                                            <?php else: ?>
                                                <form action="admin_listings.php" method="POST" style="margin:0;" onsubmit="return confirm('Reactivate this machinery listing and publish it live?');">
                                                    <input type="hidden" name="item_type" value="equipment">
                                                    <input type="hidden" name="item_id" value="<?= (int)$e['id'] ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn-activate-action">ACTIVATE</button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="admin_listings.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                                <input type="hidden" name="item_type" value="equipment">
                                                <input type="hidden" name="item_id" value="<?= (int)$e['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn-delete-action">DELETE</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Live search filter across all grouped table rows
        const searchInput = document.getElementById('listingSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.listing-row-item');
                rows.forEach(row => {
                    const searchData = (row.getAttribute('data-search') || '').toLowerCase();
                    if (searchData.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    </script>

</body>
</html>
