<?php
session_start();
require_once 'config.php';

// Require Admin Access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentAdminId = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Admin';

$message = '';
$error = '';

// Handle Moderation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $reportId = (int)($_POST['report_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($reportId > 0) {
        // Fetch report details
        $rQuery = $conn->prepare("
            SELECT r.*, 
                   u.full_name AS reported_user_name, u.email AS reported_user_email, u.status AS reported_user_status,
                   p.product_name, p.status AS product_status,
                   e.equipment_name, e.status AS equipment_status
            FROM reports r
            LEFT JOIN users u ON r.reported_user_id = u.user_id
            LEFT JOIN products p ON r.product_id = p.product_id
            LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
            WHERE r.report_id = ?
            LIMIT 1
        ");
        $rQuery->bind_param('i', $reportId);
        $rQuery->execute();
        $report = $rQuery->get_result()->fetch_assoc();
        $rQuery->close();

        if ($report) {
            $reportedUserId = (int)$report['reported_user_id'];
            $productId = !empty($report['product_id']) ? (int)$report['product_id'] : null;
            $equipmentId = !empty($report['equipment_id']) ? (int)$report['equipment_id'] : null;

            // 1. Suspend / Ban Seller Account
            if ($action === 'suspend_seller') {
                if ($reportedUserId > 0 && $reportedUserId !== $currentAdminId) {
                    $conn->begin_transaction();
                    try {
                        // Ban user account
                        $bStmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE user_id = ?");
                        $bStmt->bind_param('i', $reportedUserId);
                        $bStmt->execute();
                        $bStmt->close();

                        // Deactivate all listings by this seller
                        $conn->query("UPDATE products SET status = 'inactive' WHERE user_id = $reportedUserId");
                        $conn->query("UPDATE equipment SET status = 'inactive' WHERE user_id = $reportedUserId");

                        // Update report
                        $repNotes = !empty($adminNotes) ? $adminNotes : 'Seller account suspended and all listings deactivated due to policy violations.';
                        $uStmt = $conn->prepare("UPDATE reports SET status = 'action_taken', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                        $uStmt->bind_param('sii', $repNotes, $currentAdminId, $reportId);
                        $uStmt->execute();
                        $uStmt->close();

                        // Send notification to reporter
                        $nMsg = "Your report #$reportId has been reviewed. Administration has taken enforcement action and suspended the offending account.";
                        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, 'Report Resolved - Action Taken', ?, 'system', ?)");
                        $nStmt->bind_param('isi', $report['reporter_id'], $nMsg, $reportId);
                        $nStmt->execute();
                        $nStmt->close();

                        $conn->commit();
                        $message = "Seller account #$reportedUserId (" . htmlspecialchars($report['reported_user_name'] ?? 'User') . ") has been SUSPENDED and all listings deactivated.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Failed to suspend account: " . $e->getMessage();
                    }
                }
            }

            // 2. Deactivate Specific Listing
            elseif ($action === 'deactivate_listing') {
                $conn->begin_transaction();
                try {
                    if ($productId) {
                        $conn->query("UPDATE products SET status = 'inactive' WHERE product_id = $productId");
                    } elseif ($equipmentId) {
                        $conn->query("UPDATE equipment SET status = 'inactive' WHERE equipment_id = $equipmentId");
                    }

                    $repNotes = !empty($adminNotes) ? $adminNotes : 'Listing deactivated by administration due to report findings.';
                    $uStmt = $conn->prepare("UPDATE reports SET status = 'action_taken', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                    $uStmt->bind_param('sii', $repNotes, $currentAdminId, $reportId);
                    $uStmt->execute();
                    $uStmt->close();

                    $conn->commit();
                    $message = "Listing has been successfully deactivated from the public marketplace.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to deactivate listing: " . $e->getMessage();
                }
            }

            // 3. Dismiss Report
            elseif ($action === 'dismiss_report') {
                $repNotes = !empty($adminNotes) ? $adminNotes : 'Report reviewed and dismissed. No violation detected.';
                $uStmt = $conn->prepare("UPDATE reports SET status = 'dismissed', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                $uStmt->bind_param('sii', $repNotes, $currentAdminId, $reportId);
                $uStmt->execute();
                $uStmt->close();
                $message = "Report #$reportId has been dismissed.";
            }

            // 4. Mark as Reviewed (Pending further decision)
            elseif ($action === 'mark_reviewed') {
                $uStmt = $conn->prepare("UPDATE reports SET status = 'reviewed', admin_notes = ?, resolved_by = ? WHERE report_id = ?");
                $uStmt->bind_param('sii', $adminNotes, $currentAdminId, $reportId);
                $uStmt->execute();
                $uStmt->close();
                $message = "Report #$reportId marked as under review.";
            }

            // 5. Reactivate / Unban Seller Account
            elseif ($action === 'reactivate_seller') {
                if ($reportedUserId > 0) {
                    $conn->query("UPDATE users SET status = 'active' WHERE user_id = $reportedUserId");
                    $message = "Seller account #$reportedUserId (" . htmlspecialchars($report['reported_user_name'] ?? 'User') . ") has been reactivated.";
                }
            }
        }
    }
}

// Fetch Metrics
$totalReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports")->fetch_assoc()['c'] ?? 0);
$pendingReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);
$actionReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'action_taken'")->fetch_assoc()['c'] ?? 0);
$dismissedReports = (int)($conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'dismissed'")->fetch_assoc()['c'] ?? 0);

// Filters
$statusFilter = trim($_GET['status'] ?? '');
$reasonFilter = trim($_GET['reason'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.*,
           rep.full_name AS reporter_name, rep.email AS reporter_email,
           tgt.full_name AS reported_name, tgt.email AS reported_email, tgt.status AS reported_status,
           p.product_name, p.status AS product_status, p.price AS product_price,
           e.equipment_name, e.status AS equipment_status, e.rate_price AS equipment_rate,
           adm.full_name AS resolver_name
    FROM reports r
    INNER JOIN users rep ON r.reporter_id = rep.user_id
    INNER JOIN users tgt ON r.reported_user_id = tgt.user_id
    LEFT JOIN products p ON r.product_id = p.product_id
    LEFT JOIN equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN users adm ON r.resolved_by = adm.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if (!empty($reasonFilter)) {
    $sql .= " AND r.reason = ?";
    $params[] = $reasonFilter;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND (rep.full_name LIKE ? OR tgt.full_name LIKE ? OR p.product_name LIKE ? OR e.equipment_name LIKE ? OR r.description LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= "sssss";
}

$sql .= " ORDER BY (r.status = 'pending') DESC, r.report_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Moderation & Account Suspension — AgriMart Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .report-badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .reason-fake_product { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .reason-misleading { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .reason-scam_fraud { background: #ffe4e6; color: #881337; border: 1px solid #fecdd3; }
        .reason-prohibited_item { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .reason-poor_quality { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .reason-other { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .stat-card-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .stat-card-badge.alert { background: #ef4444; color: #fff; }
        .admin-action-btn { padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; }
        .btn-ban { background: #b91c1c; color: #fff; border-color: #991b1b; }
        .btn-ban:hover { background: #991b1b; }
        .btn-deact { background: #c2410c; color: #fff; border-color: #9a3412; }
        .btn-deact:hover { background: #9a3412; }
        .btn-dismiss { background: #f3f4f6; color: #374151; border-color: #d1d5db; }
        .btn-dismiss:hover { background: #e5e7eb; }
        .btn-reactivate { background: #15803d; color: #fff; border-color: #166534; }
        .btn-reactivate:hover { background: #166534; }
    </style>
</head>
<body class="admin-body">

<header class="site-header admin-header-nav">
<div class="wrap">
    <a href="admin_dashboard.php" class="logo">
        <span class="logo-text"><b>AgriMart Admin</b><span>System Management Console</span></span>
    </a>
    <nav class="main-nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_sellers.php">Sellers</a>
        <a href="admin_listings.php">Listings</a>
        <a href="admin_rentals.php">Rentals</a>
        <a href="admin_sales.php">Sales</a>
        <a href="admin_moderation.php" class="active">
            Reports & Moderation
            <?php if ($pendingReports > 0): ?>
                <span style="background:#ef4444; color:#fff; font-size:11px; padding:2px 6px; border-radius:10px; margin-left:4px;"><?= $pendingReports ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_reports.php">Reports</a>
    </nav>
    <div class="header-actions">
        <span style="color:#fff; font-size:14px; margin-right:10px;">Admin: <strong><?= htmlspecialchars($firstName) ?></strong></span>
        <a href="logout.php" class="btn btn-light">Logout</a>
    </div>
</div>
</header>

<main class="admin-wrap">
    <div class="admin-page-head">
        <div>
            <span style="font-family:monospace; color:#768047; text-transform:uppercase; letter-spacing:2px; font-size:12px;">Marketplace Trust & Safety</span>
            <h1>User Reports & Seller Account Suspension</h1>
        </div>
        <div>
            <a href="admin_dashboard.php" class="btn btn-light">← Dashboard</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:16px 20px; margin-bottom:25px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:16px 20px; margin-bottom:25px; font-size:14px;">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Metrics Cards -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="stat-card" style="<?= $pendingReports > 0 ? 'border: 2px solid #ef4444; background: #fffbfb;' : '' ?>">
            <span class="label" style="color: <?= $pendingReports > 0 ? '#b91c1c' : '#768047' ?>;">Pending Investigation</span>
            <div class="val" style="color: <?= $pendingReports > 0 ? '#b91c1c' : '#122017' ?>;"><?= number_format($pendingReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Awaiting administrator action</span>
        </div>
        <div class="stat-card">
            <span class="label">Action Taken / Suspended</span>
            <div class="val" style="color: #15803d;"><?= number_format($actionReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Violations penalized</span>
        </div>
        <div class="stat-card">
            <span class="label">Dismissed Reports</span>
            <div class="val" style="color: #6b7280;"><?= number_format($dismissedReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Non-viable / false flags</span>
        </div>
        <div class="stat-card">
            <span class="label">Total Reports Filed</span>
            <div class="val"><?= number_format($totalReports) ?></div>
            <span style="color:#6b6a59; font-size:12px;">Lifetime user submissions</span>
        </div>
    </div>

    <!-- Reports Table Panel -->
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2>Reported Listings & Accounts (<?= count($reports) ?>)</h2>
            <form action="admin_moderation.php" method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search product, seller, reporter..." value="<?= htmlspecialchars($search) ?>" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                <select name="status" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>⏳ Pending Review</option>
                    <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>🔍 Under Review</option>
                    <option value="action_taken" <?= $statusFilter === 'action_taken' ? 'selected' : '' ?>>🚨 Action Taken (Suspended/Removed)</option>
                    <option value="dismissed" <?= $statusFilter === 'dismissed' ? 'selected' : '' ?>>✓ Dismissed</option>
                </select>
                <select name="reason" style="padding:8px 12px; border:1px solid #d8d0b7; background:#fff; font-size:13px;">
                    <option value="">All Reasons</option>
                    <option value="fake_product" <?= $reasonFilter === 'fake_product' ? 'selected' : '' ?>>Fake / Counterfeit Product</option>
                    <option value="misleading" <?= $reasonFilter === 'misleading' ? 'selected' : '' ?>>Misleading Description / Specs</option>
                    <option value="scam_fraud" <?= $reasonFilter === 'scam_fraud' ? 'selected' : '' ?>>Scam / Fraudulent Activity</option>
                    <option value="prohibited_item" <?= $reasonFilter === 'prohibited_item' ? 'selected' : '' ?>>Prohibited Item</option>
                    <option value="poor_quality" <?= $reasonFilter === 'poor_quality' ? 'selected' : '' ?>>Poor Quality / Damaged</option>
                    <option value="other" <?= $reasonFilter === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
                <button type="submit" class="btn btn-solid" style="padding:8px 14px; font-size:12px;">Filter</button>
                <a href="admin_moderation.php" class="btn btn-light" style="padding:8px 14px; font-size:12px;">Reset</a>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Reported Item & Seller</th>
                    <th>Reason & Evidence</th>
                    <th>Reporter</th>
                    <th>Current Status</th>
                    <th>Admin Moderation Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px; color:#6b6a59;">
                            ✓ No reports match your criteria. The marketplace is running smoothly.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr style="<?= $r['status'] === 'pending' ? 'background:#fffcf7;' : '' ?>">
                            <td>
                                <strong>#<?= (int)$r['report_id'] ?></strong><br>
                                <small style="color:#777;"><?= date('M d, Y', strtotime($r['created_at'])) ?><br><?= date('h:i A', strtotime($r['created_at'])) ?></small>
                            </td>

                            <!-- Reported Item & Seller -->
                            <td>
                                <?php if (!empty($r['product_name'])): ?>
                                    <span style="font-size:11px; text-transform:uppercase; font-family:monospace; color:#2e7d32; font-weight:700;">🌱 Seed Product</span><br>
                                    <strong><a href="product_details.php?id=<?= (int)$r['product_id'] ?>" target="_blank" style="color:#122017; text-decoration:underline;"><?= htmlspecialchars($r['product_name']) ?></a></strong>
                                    <div style="font-size:12px; color:#666; margin-top:3px;">
                                        Listing Status: <span class="badge badge-<?= htmlspecialchars($r['product_status'] ?? 'inactive') ?>"><?= htmlspecialchars(ucfirst($r['product_status'] ?? 'N/A')) ?></span>
                                    </div>
                                <?php elseif (!empty($r['equipment_name'])): ?>
                                    <span style="font-size:11px; text-transform:uppercase; font-family:monospace; color:#1e40af; font-weight:700;">🚜 Machinery</span><br>
                                    <strong><a href="equipment_details.php?id=<?= (int)$r['equipment_id'] ?>" target="_blank" style="color:#122017; text-decoration:underline;"><?= htmlspecialchars($r['equipment_name']) ?></a></strong>
                                    <div style="font-size:12px; color:#666; margin-top:3px;">
                                        Listing Status: <span class="badge badge-<?= htmlspecialchars($r['equipment_status'] ?? 'inactive') ?>"><?= htmlspecialchars(ucfirst($r['equipment_status'] ?? 'N/A')) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:11px; text-transform:uppercase; font-family:monospace; color:#854d0e; font-weight:700;">👤 User Account</span>
                                <?php endif; ?>

                                <div style="margin-top:8px; padding-top:6px; border-top:1px dashed #ded6b9; font-size:12px;">
                                    Seller: <strong><?= htmlspecialchars($r['reported_name']) ?></strong><br>
                                    <small style="color:#666;"><?= htmlspecialchars($r['reported_email']) ?></small><br>
                                    Account Status: 
                                    <span class="badge badge-<?= htmlspecialchars($r['reported_status']) ?>" style="font-size:10px;">
                                        <?= htmlspecialchars(ucfirst($r['reported_status'])) ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Reason & Evidence -->
                            <td style="max-width:320px;">
                                <span class="report-badge reason-<?= htmlspecialchars($r['reason']) ?>">
                                    <?= ucwords(str_replace('_', ' ', $r['reason'])) ?>
                                </span>
                                <p style="margin:8px 0; font-size:13px; line-height:1.5; color:#374151; background:#f9f7f0; padding:10px; border:1px solid #ede7d5;">
                                    "<?= nl2br(htmlspecialchars($r['description'])) ?>"
                                </p>
                                <?php if (!empty($r['admin_notes'])): ?>
                                    <div style="font-size:12px; color:#0d2818; background:#e8eedf; padding:6px 8px; border-left:3px solid #23581c; margin-top:5px;">
                                        <strong>Admin Note:</strong> <?= htmlspecialchars($r['admin_notes']) ?>
                                        <?php if (!empty($r['resolver_name'])): ?>
                                            <span style="color:#666;">(by <?= htmlspecialchars($r['resolver_name']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Reporter -->
                            <td>
                                <strong><?= htmlspecialchars($r['reporter_name']) ?></strong><br>
                                <small style="color:#666;"><?= htmlspecialchars($r['reporter_email']) ?></small>
                            </td>

                            <!-- Current Status -->
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <span class="badge badge-pending" style="background:#fef08a; color:#854d0e; font-weight:700;">⏳ Pending</span>
                                <?php elseif ($r['status'] === 'action_taken'): ?>
                                    <span class="badge badge-active" style="background:#fee2e2; color:#991b1b; font-weight:700;">🚨 Action Taken</span>
                                <?php elseif ($r['status'] === 'reviewed'): ?>
                                    <span class="badge" style="background:#dbeafe; color:#1e40af; font-weight:700;">🔍 In Review</span>
                                <?php elseif ($r['status'] === 'dismissed'): ?>
                                    <span class="badge" style="background:#f3f4f6; color:#4b5563;">✓ Dismissed</span>
                                <?php endif; ?>
                            </td>

                            <!-- Moderation Actions -->
                            <td style="min-width:180px;">
                                <div style="display:flex; flex-direction:column; gap:6px;">

                                    <!-- 1. Suspend Seller Account Action -->
                                    <?php if ($r['reported_status'] !== 'banned'): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to SUSPEND seller \'<?= addslashes($r['reported_name']) ?>\'?\n\nThis will ban their login and deactivate all their products and machinery from AgriMart.');">
                                            <input type="hidden" name="action" value="suspend_seller">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-ban" style="width:100%;">
                                                🚨 Suspend Seller Account
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Reactivate and unban this seller account?');">
                                            <input type="hidden" name="action" value="reactivate_seller">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-reactivate" style="width:100%;">
                                                🔓 Reactivate Seller
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- 2. Deactivate Specific Listing -->
                                    <?php if ((!empty($r['product_name']) && $r['product_status'] === 'active') || (!empty($r['equipment_name']) && $r['equipment_status'] === 'active')): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Remove / deactivate this listing from public display?');">
                                            <input type="hidden" name="action" value="deactivate_listing">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-deact" style="width:100%;">
                                                🛑 Remove Listing
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- 3. Dismiss Report -->
                                    <?php if ($r['status'] !== 'dismissed'): ?>
                                        <form action="admin_moderation.php" method="POST" onsubmit="return confirm('Dismiss this report as not viable or resolved?');">
                                            <input type="hidden" name="action" value="dismiss_report">
                                            <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                                            <button type="submit" class="admin-action-btn btn-dismiss" style="width:100%;">
                                                ✓ Dismiss Report
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>© 2026 AgriMart Administration. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

</body>
</html>
