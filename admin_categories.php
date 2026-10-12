<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'Admin';

$message = '';
$error = '';

// Handle Category Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_category') {
        $name = trim($_POST['category_name'] ?? '');
        $type = trim($_POST['category_type'] ?? 'seed');
        $desc = trim($_POST['description'] ?? '');

        // Map 'product' to 'seed' if submitted
        if ($type === 'product') {
            $type = 'seed';
        }

        if ($name === '' || !in_array($type, ['seed', 'equipment'])) {
            $error = 'Please provide a valid category name and select a valid category type.';
        } else {
            // Check for duplicate category name within same type
            $dupStmt = $conn->prepare("SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) AND category_type = ? LIMIT 1");
            $dupStmt->bind_param('ss', $name, $type);
            $dupStmt->execute();
            if ($dupStmt->get_result()->fetch_assoc()) {
                $error = "A category named \"$name\" already exists under this type.";
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (category_name, category_type, description) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $name, $type, $desc);
                if ($stmt->execute()) {
                    $message = "Category \"$name\" has been successfully created.";
                } else {
                    $error = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
            $dupStmt->close();
        }
    } elseif ($_POST['action'] === 'edit_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $type = trim($_POST['category_type'] ?? 'seed');
        $desc = trim($_POST['description'] ?? '');

        if ($type === 'product') {
            $type = 'seed';
        }

        if ($catId <= 0 || $name === '' || !in_array($type, ['seed', 'equipment'])) {
            $error = 'Please provide a valid category name and type.';
        } else {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, category_type = ?, description = ? WHERE category_id = ?");
            $stmt->bind_param('sssi', $name, $type, $desc, $catId);
            if ($stmt->execute()) {
                $message = "Category \"$name\" updated successfully.";
            } else {
                $error = "Database error updating category: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        // Check if items attached
        $pCount = (int)($conn->query("SELECT COUNT(*) AS c FROM products WHERE category_id = $catId")->fetch_assoc()['c'] ?? 0);
        $eCount = (int)($conn->query("SELECT COUNT(*) AS c FROM equipment WHERE category_id = $catId")->fetch_assoc()['c'] ?? 0);
        if ($pCount > 0 || $eCount > 0) {
            $error = "Cannot delete category: $pCount product(s) and $eCount equipment listing(s) are attached to it.";
        } else {
            $conn->query("DELETE FROM categories WHERE category_id = $catId");
            $message = "Category deleted successfully.";
        }
    }
}

// Fetch all categories with product and equipment counts
$sql = "
    SELECT 
        c.*,
        COUNT(DISTINCT p.product_id) AS product_count,
        COUNT(DISTINCT e.equipment_id) AS equipment_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    LEFT JOIN equipment e ON c.category_id = e.category_id
    GROUP BY c.category_id
    ORDER BY c.category_type ASC, c.category_name ASC
";
$categories = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$totalCats = count($categories);
$seedCats = 0;
$equipCats = 0;
foreach ($categories as $cat) {
    if ($cat['category_type'] === 'seed') {
        $seedCats++;
    } else {
        $equipCats++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories — AgriMart Admin Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin_modern.css?v=<?= time() ?>">
    <style>
        /* Modern Category Management Layout */
        .category-add-card {
            background: #ffffff !important;
            border: 1px solid #eae5db !important;
            border-radius: 16px !important;
            padding: 24px 28px 28px !important;
            box-shadow: 0 2px 10px rgba(19, 56, 44, 0.03) !important;
            margin-bottom: 28px !important;
            box-sizing: border-box !important;
        }

        .category-card-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0ebe1;
        }

        .card-icon-bubble {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #eef5f1;
            color: #13382c;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .category-card-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 20px;
            font-weight: 700;
            color: #18241d;
            margin: 0 0 4px 0;
            line-height: 1.2;
        }

        .category-card-subtitle {
            font-size: 13px;
            color: #71717a;
            margin: 0;
        }

        /* Form Grid */
        .category-form-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
            margin-bottom: 18px;
        }

        @media (max-width: 860px) {
            .category-form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        .form-group-modern {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label-modern {
            font-size: 12.5px;
            font-weight: 600;
            color: #27272a;
            letter-spacing: 0.2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-label-modern .req {
            color: #dc2626;
            font-weight: 700;
        }

        .form-label-modern .opt {
            color: #a1a1aa;
            font-weight: 400;
            font-size: 11.5px;
            margin-left: 4px;
        }

        .form-input-modern,
        .form-select-modern,
        .form-textarea-modern {
            width: 100% !important;
            box-sizing: border-box !important;
            background: #ffffff !important;
            border: 1px solid #d4cebe !important;
            border-radius: 10px !important;
            padding: 11px 14px !important;
            font-size: 14px !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            color: #18241d !important;
            transition: all 0.2s ease !important;
            outline: none !important;
        }

        .form-input-modern:focus,
        .form-select-modern:focus,
        .form-textarea-modern:focus {
            border-color: #13382c !important;
            box-shadow: 0 0 0 3px rgba(19, 56, 44, 0.1) !important;
            background: #ffffff !important;
        }

        .form-textarea-modern {
            resize: vertical !important;
            min-height: 70px !important;
            line-height: 1.5 !important;
        }

        .form-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px dashed #eae5db;
        }

        .btn-create-category {
            background: #13382c !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 12px 24px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 2px 6px rgba(19, 56, 44, 0.15) !important;
            transition: all 0.2s ease !important;
        }

        .btn-create-category:hover {
            background: #1c4e3e !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 10px rgba(19, 56, 44, 0.2) !important;
        }

        .form-helper-note {
            font-size: 12.5px;
            color: #71717a;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Existing Categories Card */
        .category-list-card {
            background: #ffffff !important;
            border: 1px solid #eae5db !important;
            border-radius: 16px !important;
            padding: 24px 28px 28px !important;
            box-shadow: 0 2px 10px rgba(19, 56, 44, 0.03) !important;
            box-sizing: border-box !important;
        }

        .list-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .list-card-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 20px;
            font-weight: 700;
            color: #18241d;
            margin: 0 0 4px 0;
        }

        .list-filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-pill-btn {
            background: #f4efe6;
            color: #555;
            border: 1px solid transparent;
            padding: 7px 14px;
            border-radius: 9999px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-pill-btn:hover {
            background: #ebe4d8;
            color: #18241d;
        }

        .filter-pill-btn.active {
            background: #13382c;
            color: #ffffff;
        }

        .category-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .category-table th {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: #71717a;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 1px solid #eee8dc;
            background: #faf8f5;
        }

        .category-table td {
            font-size: 13.5px;
            color: #18241d;
            padding: 14px 16px;
            border-bottom: 1px solid #f4efe6;
            vertical-align: middle;
        }

        .category-table tr:hover td {
            background: #fdfbf7;
        }

        .category-table tr:last-child td {
            border-bottom: none;
        }

        /* Type Badges */
        .type-badge-seed {
            background: #e6f4ea;
            color: #137333;
            border: 1px solid #cce8d4;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.3px;
        }

        .type-badge-equip {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.3px;
        }

        .btn-edit-action {
            background: #ffffff;
            color: #13382c;
            border: 1px solid #c8d8ce;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit-action:hover {
            background: #eef5f1;
            border-color: #13382c;
        }

        .btn-delete-action {
            background: #fae6df;
            color: #a54129;
            border: 1px solid #efb7aa;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-delete-action:hover {
            background: #f5d0c7;
        }

        .btn-delete-action:disabled {
            background: #f4f4f5;
            color: #a1a1aa;
            border-color: #e4e4e7;
            cursor: not-allowed;
        }

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

        .notice-banner.error {
            background: #fef2f2;
            border-color: #fee2e2;
            color: #991b1b;
        }

        /* Modal for editing category */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(18, 36, 29, 0.45);
            backdrop-filter: blur(3px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            width: min(520px, 94vw);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 26px 28px;
            border: 1px solid #eae5db;
        }
    </style>
</head>
<body class="admin-console">

    <!-- Reusable Admin Sidebar -->
    <?php 
    $activeNav = 'categories';
    include __DIR__ . '/admin_sidebar.php'; 
    ?>

    <!-- Main Workspace -->
    <main class="admin-main">

        <!-- Top Header -->
        <header class="admin-topbar">
            <div>
                <div class="topbar-subline">Taxonomy &amp; Marketplace Catalog Management</div>
                <h1 class="topbar-heading">Marketplace <i>Categories</i></h1>
            </div>
            <div class="topbar-actions">
                <a href="admin_listings.php" class="btn-review-action">Manage Live Listings</a>
                <a href="admin_dashboard.php" class="btn-review-action" style="background:#fff; color:#18241d; border:1px solid #dcd7cb;">Back to Overview</a>
            </div>
        </header>

        <!-- Feedback Alerts -->
        <?php if (!empty($message)): ?>
            <div class="notice-banner">
                ✓ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="notice-banner error">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: ADD NEW CATEGORY (Positioned at TOP) -->
        <div class="category-add-card">
            <div class="category-card-head">
                <div class="card-icon-bubble">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <h2 class="category-card-title">Add New Category</h2>
                    <p class="category-card-subtitle">Define a new category with its name, type, and description for marketplace sellers and renters.</p>
                </div>
            </div>

            <form action="admin_categories.php" method="POST">
                <input type="hidden" name="action" value="add_category">

                <div class="category-form-grid">
                    <!-- Category Name -->
                    <div class="form-group-modern">
                        <label for="category_name" class="form-label-modern">
                            Category Name <span class="req">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="category_name" 
                            name="category_name" 
                            class="form-input-modern" 
                            required 
                            placeholder="e.g. Certified Seed Grain, Organic Fertilizer, Tractors..."
                            autocomplete="off"
                        >
                    </div>

                    <!-- Category Type -->
                    <div class="form-group-modern">
                        <label for="category_type" class="form-label-modern">
                            Category Type <span class="req">*</span>
                        </label>
                        <select id="category_type" name="category_type" class="form-select-modern" required>
                            <option value="seed">🌾 Produce / Seed / Crop</option>
                            <option value="equipment">🚜 Machinery / Equipment / Tool</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group-modern" style="margin-bottom: 6px;">
                    <label for="description" class="form-label-modern">
                        Description <span class="opt">(Brief summary of items classified under this category)</span>
                    </label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-textarea-modern" 
                        rows="2" 
                        placeholder="Explain what products or machinery belong here (e.g. High-yield certified grains, vegetable seeds, and field legumes)..."
                    ></textarea>
                </div>

                <!-- Form Action Buttons -->
                <div class="form-actions-row">
                    <button type="submit" class="btn-create-category">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Create Category</span>
                    </button>
                    <div class="form-helper-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#13382c;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span>New categories are immediately active and accessible when sellers list items.</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- SECTION 2: EXISTING MARKETPLACE CATEGORIES (Positioned BELOW) -->
        <div class="category-list-card">
            <div class="list-card-header">
                <div>
                    <h2 class="list-card-title">Existing Marketplace Categories (<?= $totalCats ?>)</h2>
                    <p class="category-card-subtitle">All active categories currently configured in the marketplace catalog.</p>
                </div>

                <!-- Filter Pills -->
                <div class="list-filter-bar">
                    <button type="button" class="filter-pill-btn active" data-type="all">
                        All (<?= $totalCats ?>)
                    </button>
                    <button type="button" class="filter-pill-btn" data-type="seed">
                        🌾 Produce &amp; Seeds (<?= $seedCats ?>)
                    </button>
                    <button type="button" class="filter-pill-btn" data-type="equipment">
                        🚜 Machinery &amp; Equipment (<?= $equipCats ?>)
                    </button>
                </div>
            </div>

            <!-- Categories Table -->
            <div style="overflow-x: auto;">
                <table class="category-table" id="categoriesTable">
                    <thead>
                        <tr>
                            <th style="width: 32%;">Category Name &amp; Description</th>
                            <th style="width: 22%;">Classification Type</th>
                            <th style="width: 26%;">Attached Catalog Items</th>
                            <th style="width: 20%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 36px; color: #71717a;">
                                    No categories found. Use the form above to add your first category.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $c): 
                                $isSeed = ($c['category_type'] === 'seed');
                                $attachedCount = $isSeed ? (int)$c['product_count'] : (int)$c['equipment_count'];
                                $attachedLabel = $isSeed ? ($attachedCount . ' product' . ($attachedCount === 1 ? '' : 's')) : ($attachedCount . ' machinery item' . ($attachedCount === 1 ? '' : 's'));
                            ?>
                                <tr class="cat-row" data-type="<?= htmlspecialchars($c['category_type']) ?>">
                                    <td>
                                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                                            <span style="font-size: 18px; line-height: 1.2; padding-top: 2px;">
                                                <?= $isSeed ? '🌾' : '🚜' ?>
                                            </span>
                                            <div>
                                                <strong style="color: #18241d; font-size: 14.5px; display: block; margin-bottom: 2px;">
                                                    <?= htmlspecialchars($c['category_name']) ?>
                                                </strong>
                                                <?php if (!empty($c['description'])): ?>
                                                    <div style="color: #71717a; font-size: 12.5px; line-height: 1.4;">
                                                        <?= htmlspecialchars($c['description']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="color: #a1a1aa; font-size: 12px; font-style: italic;">
                                                        No description provided
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isSeed): ?>
                                            <span class="type-badge-seed">
                                                🌾 Produce / Seed
                                            </span>
                                        <?php else: ?>
                                            <span class="type-badge-equip">
                                                🚜 Machinery / Tool
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attachedCount > 0): ?>
                                            <span style="font-weight: 600; color: #18241d;">
                                                <?= $attachedLabel ?>
                                            </span>
                                            <span style="display: block; font-size: 11.5px; color: #d97706; margin-top: 2px;">
                                                Active in catalog
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #71717a; font-size: 13px;">
                                                0 items attached
                                            </span>
                                            <span style="display: block; font-size: 11px; color: #15803d; margin-top: 2px;">
                                                Safe to remove
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                                            <!-- Edit Button -->
                                            <button 
                                                type="button" 
                                                class="btn-edit-action"
                                                onclick="openEditModal(<?= (int)$c['category_id'] ?>, '<?= htmlspecialchars(addslashes($c['category_name'])) ?>', '<?= htmlspecialchars($c['category_type']) ?>', '<?= htmlspecialchars(addslashes($c['description'] ?? '')) ?>')"
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                                <span>Edit</span>
                                            </button>

                                            <!-- Delete Form -->
                                            <form action="admin_categories.php" method="POST" onsubmit="return confirm('Delete category &quot;<?= htmlspecialchars(addslashes($c['category_name'])) ?>&quot;?');" style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>">
                                                <button 
                                                    type="submit" 
                                                    class="btn-delete-action" 
                                                    <?= $attachedCount > 0 ? 'disabled title="Cannot delete: items are currently assigned to this category."' : '' ?>
                                                >
                                                    Delete
                                                </button>
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

    <!-- EDIT CATEGORY MODAL -->
    <div class="modal-overlay" id="editModalOverlay" onclick="if(event.target === this) closeEditModal();">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                <h3 style="font-family: 'Playfair Display', Georgia, serif; margin: 0; font-size: 19px; color: #18241d;">
                    Edit Category
                </h3>
                <button type="button" onclick="closeEditModal();" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #71717a; line-height: 1;">
                    &times;
                </button>
            </div>

            <form action="admin_categories.php" method="POST">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" id="edit_category_id" name="category_id" value="">

                <div class="form-group-modern" style="margin-bottom: 14px;">
                    <label for="edit_category_name" class="form-label-modern">
                        Category Name <span class="req">*</span>
                    </label>
                    <input type="text" id="edit_category_name" name="category_name" class="form-input-modern" required>
                </div>

                <div class="form-group-modern" style="margin-bottom: 14px;">
                    <label for="edit_category_type" class="form-label-modern">
                        Category Type <span class="req">*</span>
                    </label>
                    <select id="edit_category_type" name="category_type" class="form-select-modern" required>
                        <option value="seed">🌾 Produce / Seed / Crop</option>
                        <option value="equipment">🚜 Machinery / Equipment / Tool</option>
                    </select>
                </div>

                <div class="form-group-modern" style="margin-bottom: 20px;">
                    <label for="edit_description" class="form-label-modern">
                        Description <span class="opt">(Optional)</span>
                    </label>
                    <textarea id="edit_description" name="description" class="form-textarea-modern" rows="3"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeEditModal();" class="btn-review-action" style="background: #f4efe6; color: #18241d; border: 1px solid #dcd7cb;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-create-category">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Interactive Filtering Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtns = document.querySelectorAll('.filter-pill-btn');
            const rows = document.querySelectorAll('.cat-row');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const selectedType = this.getAttribute('data-type');
                    rows.forEach(row => {
                        const rowType = row.getAttribute('data-type');
                        if (selectedType === 'all' || rowType === selectedType) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
        });

        function openEditModal(id, name, type, desc) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_type').value = type;
            document.getElementById('edit_description').value = desc;
            document.getElementById('editModalOverlay').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModalOverlay').classList.remove('active');
        }
    </script>
</body>
</html>
