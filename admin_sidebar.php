<?php
// admin_sidebar.php - Unified Modern Navigation Sidebar for AgriMart Admin Console
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// Auto-detect or sanitize active navigation tab based on the active script file name
$scriptName = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '');

if (strpos($scriptName, 'admin_dashboard') !== false) {
    $activeNav = 'overview';
} elseif (strpos($scriptName, 'admin_listings') !== false) {
    $activeNav = 'listings';
} elseif (strpos($scriptName, 'admin_sales') !== false) {
    $activeNav = 'orders';
} elseif (strpos($scriptName, 'admin_rentals') !== false) {
    $activeNav = 'rentals';
} elseif (strpos($scriptName, 'admin_moderation') !== false) {
    $activeNav = 'moderation';
} elseif (strpos($scriptName, 'admin_reports') !== false) {
    $activeNav = 'payments';
} elseif (strpos($scriptName, 'admin_sellers') !== false) {
    $activeNav = 'sellers';
} elseif (strpos($scriptName, 'admin_users') !== false) {
    $activeNav = 'users';
} elseif (strpos($scriptName, 'admin_categories') !== false) {
    $activeNav = 'categories';
} elseif (!isset($activeNav) || empty($activeNav)) {
    $activeNav = 'overview';
}

// Calculate listing badge count (items needing approval / moderation / attention)
$badgeP = (int)($conn->query("SELECT COUNT(*) AS c FROM products WHERE (status = 'inactive' OR retrieval_status = 'pending') AND status != 'deleted'")->fetch_assoc()['c'] ?? 0);
$badgeE = (int)($conn->query("SELECT COUNT(*) AS c FROM equipment WHERE (status = 'inactive' OR retrieval_status = 'pending') AND status != 'deleted'")->fetch_assoc()['c'] ?? 0);
$listingBadge = $badgeP + $badgeE;

// Calculate moderation badge count (pending user reports and retrieval appeals)
$modCount = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
$modAppP = (int)($conn->query("SELECT COUNT(*) AS c FROM products WHERE admin_deactivated = 1 AND retrieval_status = 'pending'")->fetch_assoc()['c'] ?? 0);
$modAppE = (int)($conn->query("SELECT COUNT(*) AS c FROM equipment WHERE admin_deactivated = 1 AND retrieval_status = 'pending'")->fetch_assoc()['c'] ?? 0);
$moderationBadge = $modCount + $modAppP + $modAppE;

$adminDisplayName = $_SESSION['full_name'] ?? 'Administrator';
$adminInitial = strtoupper(substr(trim($adminDisplayName), 0, 1)) ?: 'A';
?>
<style>
/* Defensive Inline CSS for AgriMart Admin Layout & Sidebar to prevent SVG blowout or caching issues */
body.admin-console {
    margin: 0 !important;
    background: #f6f4ee !important;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif !important;
    display: block !important;
    min-height: 100vh !important;
    color: #18241d !important;
}
.admin-sidebar {
    width: 250px !important;
    min-width: 250px !important;
    max-width: 250px !important;
    background: #13382c !important;
    color: #ffffff !important;
    height: 100vh !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    padding: 24px 16px 20px !important;
    box-sizing: border-box !important;
    z-index: 1000 !important;
}
.admin-main {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    max-width: calc(100% - 250px) !important;
    padding: 32px 36px 60px !important;
    box-sizing: border-box !important;
    background: #f6f4ee !important;
    min-height: 100vh !important;
}
.sidebar-icon {
    width: 18px !important;
    height: 18px !important;
    min-width: 18px !important;
    min-height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
    stroke: #94a3b8 !important;
    fill: none !important;
    flex-shrink: 0 !important;
    display: inline-block !important;
    vertical-align: middle !important;
}
.sidebar-icon rect,
.sidebar-icon path,
.sidebar-icon circle,
.sidebar-icon line,
.sidebar-icon polyline {
    fill: none !important;
    stroke: inherit !important;
}
.sidebar-menu {
    display: flex !important;
    flex-direction: column !important;
    gap: 3px !important;
    flex: 1 !important;
    overflow-y: auto !important;
    padding-right: 2px !important;
    margin-top: 10px !important;
    margin-bottom: 10px !important;
}
.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}
.sidebar-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 9.5px 14px !important;
    border-radius: 9999px !important;
    color: #cbd5e1 !important;
    text-decoration: none !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    transition: all .15s ease !important;
}
.sidebar-item:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
}
.sidebar-item.active {
    background: #ffffff !important;
    color: #13382c !important;
    font-weight: 600 !important;
}
.sidebar-item.active .sidebar-icon {
    stroke: #13382c !important;
}
.sidebar-badge {
    background: #d97706 !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    padding: 2px 7px !important;
    border-radius: 9999px !important;
    min-width: 16px !important;
    text-align: center !important;
    line-height: 1.2 !important;
}
</style>
<aside class="admin-sidebar">
    <div class="sidebar-brand-box">
        <a href="admin_dashboard.php" class="sidebar-brand">
            <span class="brand-title">AgriMart</span>
            <span class="brand-subtitle">ADMIN CONSOLE</span>
        </a>
    </div>

    <nav class="sidebar-menu">
        <a href="admin_dashboard.php" class="sidebar-item <?= $activeNav === 'overview' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <rect x="3" y="3" width="7" height="7" fill="none"></rect>
                <rect x="14" y="3" width="7" height="7" fill="none"></rect>
                <rect x="14" y="14" width="7" height="7" fill="none"></rect>
                <rect x="3" y="14" width="7" height="7" fill="none"></rect>
            </svg>
            <span class="sidebar-label">Overview</span>
        </a>

        <a href="admin_listings.php" class="sidebar-item <?= $activeNav === 'listings' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12A10 10 0 0 1 12 2z" fill="none"></path>
                <path d="M12 6v6l4 2" fill="none"></path>
            </svg>
            <span class="sidebar-label">Listings</span>
            <?php if ($listingBadge > 0): ?>
                <span class="sidebar-badge"><?= $listingBadge ?></span>
            <?php endif; ?>
        </a>

        <a href="admin_sales.php" class="sidebar-item <?= $activeNav === 'orders' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <circle cx="9" cy="21" r="1" fill="none"></circle>
                <circle cx="20" cy="21" r="1" fill="none"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" fill="none"></path>
            </svg>
            <span class="sidebar-label">Orders</span>
        </a>

        <a href="admin_rentals.php" class="sidebar-item <?= $activeNav === 'rentals' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span class="sidebar-label">Rentals</span>
        </a>

        <a href="admin_moderation.php" class="sidebar-item <?= $activeNav === 'moderation' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="none"></path>
            </svg>
            <span class="sidebar-label">Moderation</span>
            <?php if ($moderationBadge > 0): ?>
                <span class="sidebar-badge" style="background:#b91c1c;"><?= $moderationBadge ?></span>
            <?php endif; ?>
        </a>

        <a href="admin_reports.php?tab=payments" class="sidebar-item <?= $activeNav === 'payments' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <rect x="2" y="5" width="20" height="14" rx="2" fill="none"></rect>
                <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
            <span class="sidebar-label">Payments</span>
        </a>

        <a href="admin_users.php" class="sidebar-item <?= $activeNav === 'users' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none"></path>
                <circle cx="9" cy="7" r="4" fill="none"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" fill="none"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none"></path>
            </svg>
            <span class="sidebar-label">Users</span>
        </a>

        <a href="admin_sellers.php" class="sidebar-item <?= $activeNav === 'sellers' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span class="sidebar-label">Sellers</span>
        </a>

        <a href="admin_categories.php" class="sidebar-item <?= $activeNav === 'categories' ? 'active' : '' ?>">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;fill:none!important;stroke:currentColor!important;">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" fill="none"></path>
                <line x1="7" y1="7" x2="7.01" y2="7"></line>
            </svg>
            <span class="sidebar-label">Categories</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-user-pill">
            <div class="admin-avatar-circle"><?= htmlspecialchars($adminInitial) ?></div>
            <div class="admin-user-texts">
                <div class="admin-user-name"><?= htmlspecialchars($adminDisplayName) ?></div>
                <a href="logout.php" class="admin-sign-out">Sign out</a>
            </div>
        </div>
    </div>
</aside>
