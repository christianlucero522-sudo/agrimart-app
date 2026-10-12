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

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_category') {
        $name = trim($_POST['category_name'] ?? '');
        $type = trim($_POST['category_type'] ?? 'product');
        $desc = trim($_POST['description'] ?? '');

        if ($name === '' || !in_array($type, ['product', 'equipment'])) {
            $error = 'Please provide a valid category name and select type.';
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (category_name, category_type, description) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $name, $type, $desc);
            if ($stmt->execute()) {
                $message = "Category '$name' created successfully.";
            } else {
                $error = "Error adding category: " . $conn->error;
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

// Fetch all categories
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
</head>
<body class="admin-console">

<?php 
$activeNav = 'categories';
include __DIR__ . '/admin_sidebar.php'; 
?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <div class="topbar-subline">Taxonomy & category management</div>
            <h1 class="topbar-heading">Marketplace <i>categories.</i></h1>
        </div>
        <div class="topbar-actions">
            <a href="admin_listings.php" class="btn-review-action">Manage Listings</a>
            <a href="admin_dashboard.php" class="btn-review-action" style="background:#fff; color:#18241d; border:1px solid #dcd7cb;">Back to Overview</a>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div style="background:#e0edd5; border:1px solid #c5ddb4; color:#23581c; padding:15px; margin-bottom:20px; font-weight:500;">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background:#fae6df; border:1px solid #efb7aa; color:#a54129; padding:15px; margin-bottom:20px; font-size:14px;">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="cat-grid">
        <!-- List -->
        <div class="table-panel">
            <h2>Existing Categories (<?= count($categories) ?>)</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Type</th>
                        <th>Attached Items</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($c['category_name']) ?></strong>
                                <?php if (!empty($c['description'])): ?>
                                    <br><small style="color:#777;"><?= htmlspecialchars($c['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= htmlspecialchars($c['category_type']) ?>"><?= htmlspecialchars(ucfirst($c['category_type'])) ?></span></td>
                            <td>
                                <?= $c['category_type'] === 'product' ? (int)$c['product_count'] . ' products' : (int)$c['equipment_count'] . ' machinery' ?>
                            </td>
                            <td>
                                <form action="admin_categories.php" method="POST" onsubmit="return confirm('Delete this category?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>">
                                    <button type="submit" class="btn btn-light" style="padding:4px 8px; font-size:11px; background:#fae6df; color:#a54129; border-color:#efb7aa;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Form -->
        <div class="table-panel">
            <h2>Add New Category</h2>
            <form action="admin_categories.php" method="POST">
                <input type="hidden" name="action" value="add_category">
                
                <div class="form-group">
                    <label for="category_name">Category Name *</label>
                    <input type="text" id="category_name" name="category_name" class="form-control" required placeholder="e.g. Certified Seed Grain">
                </div>

                <div class="form-group">
                    <label for="category_type">Category Type *</label>
                    <select id="category_type" name="category_type" class="form-control" required>
                        <option value="product">Product / Seed / Crop</option>
                        <option value="equipment">Machinery / Equipment / Tool</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Short Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4" placeholder="Brief explanation of items categorized here..."></textarea>
                </div>

                <button type="submit" class="btn btn-solid" style="width:100%; padding:14px; cursor:pointer;">Create Category</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
