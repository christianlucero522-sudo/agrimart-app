<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'User';
$parts = explode(' ', trim($fullName));
$firstName = $parts[0] ?? 'User';

// Automatically check and dispatch mobile SMS alerts for nearing rentals
@checkAndSendRentalExpiryAlerts($conn);

// Handle AJAX POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $notifId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Return remaining unread count
        $cntRes = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id = $userId AND is_read = 0");
        $unreadCount = (int)($cntRes->fetch_assoc()['c'] ?? 0);
        
        echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
        exit;
    }
    
    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Handle GET: Mark single notification as read via direct link parameter
if (isset($_GET['mark_read_id'])) {
    $notifId = (int)$_GET['mark_read_id'];
    if ($notifId > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notifId, $userId);
        $stmt->execute();
        $stmt->close();
    }
    if (!empty($_GET['redirect'])) {
        header('Location: ' . $_GET['redirect']);
        exit;
    }
    header('Location: notifications.php');
    exit;
}

// Handle GET: Mark All as Read fallback
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId");
    header('Location: notifications.php');
    exit;
}

// Fetch In-App Notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY notification_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) {
        $unreadCount++;
    }
}

// Fetch Mobile SMS Logs for this User
$smsSql = "SELECT * FROM sms_logs WHERE user_id = ? ORDER BY sms_id DESC LIMIT 15";
$smsStmt = $conn->prepare($smsSql);
$smsStmt->bind_param('i', $userId);
$smsStmt->execute();
$smsLogs = $smsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$smsStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications & SMS Alerts — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; background: #f4f0df; color: #162018; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
        .site-header { background: var(--forest-950, #122017) !important; }
        .page-wrap { width: min(850px, 100% - 40px); margin: 0 auto; padding: 120px 0 90px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; flex-wrap: wrap; gap: 20px; border-bottom: 1px solid #ded6b9; padding-bottom: 24px; }
        .page-header h1 { font-family: Georgia, serif; font-size: clamp(28px, 4vw, 42px); margin: 6px 0 0; color: #122017; font-weight: 700; line-height: 1.2; }
        
        /* Action Button */
        .btn-mark-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #122017;
            color: #f5f0df !important;
            border: 1px solid #122017;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(18, 32, 23, 0.15);
        }
        .btn-mark-all:hover {
            background: #233e2d;
            color: #ffffff !important;
            border-color: #233e2d;
            box-shadow: 0 4px 10px rgba(18, 32, 23, 0.25);
            transform: translateY(-1px);
        }
        .btn-mark-all:active {
            transform: translateY(0);
        }
        .btn-mark-all.disabled {
            background: #e5dfcb;
            color: #8c8874 !important;
            border-color: #ded6b9;
            cursor: default;
            box-shadow: none;
            pointer-events: none;
        }

        /* Notification Cards */
        .notif-card {
            background: #ffffff;
            border: 1px solid #ded6b9;
            padding: 20px 24px;
            margin-bottom: 15px;
            border-left: 4px solid #768047;
            border-radius: 4px;
            transition: all 0.25s ease;
            position: relative;
        }
        .notif-card.unread {
            background: #fdfbf3;
            border-left: 4px solid #b29438;
            border-color: #e5dcbe;
            cursor: pointer;
        }
        .notif-card.unread:hover {
            background: #faf5e4;
            box-shadow: 0 4px 12px rgba(178, 148, 56, 0.12);
            border-color: #d6ca9f;
        }
        .notif-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 6px;
        }
        .notif-title {
            font-weight: 700;
            font-size: 16px;
            color: #122017;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .badge-new {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            background: #b29438;
            color: #ffffff;
            padding: 2px 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 3px;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .unread-hint {
            font-size: 11px;
            color: #b29438;
            font-weight: 600;
            opacity: 0.9;
            display: none;
        }
        .notif-card.unread:hover .unread-hint {
            display: inline-block;
        }
        .notif-msg {
            color: #4a5245;
            line-height: 1.6;
            font-size: 14px;
            margin: 0 0 12px;
        }
        .notif-meta {
            font-size: 12px;
            color: #8c8874;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-top: 1px dashed #eee8d5;
            padding-top: 10px;
        }
        .notif-action-link {
            color: #23581c;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 3px;
            background: #edf5ea;
            border: 1px solid #d1e5cc;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .notif-action-link:hover {
            background: #23581c;
            color: #ffffff !important;
            border-color: #23581c;
            text-decoration: none;
        }

        /* SMS Box */
        .sms-box {
            background: #ffffff;
            border: 1px solid #d8d0b7;
            border-left: 4px solid #b29438;
            padding: 18px 22px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .sms-phone {
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
            color: #854d0e;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sms-body {
            font-size: 14px;
            color: #122017;
            line-height: 1.55;
            margin-bottom: 10px;
            background: #fdfaf2;
            padding: 12px 16px;
            border-radius: 4px;
            border: 1px dashed #ded6b9;
        }
        .sms-meta {
            font-size: 12px;
            color: #768047;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Toast notification */
        .toast-popup {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #122017;
            color: #f5f0df;
            padding: 12px 20px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 9999;
        }
        .toast-popup.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

<header class="site-header">
<div class="wrap">
    <a href="index.php" class="logo">
        <span class="logo-text"><b>AgriMart</b><span>Field to Farm Gate</span></span>
    </a>
    <nav class="main-nav">
        <a href="index.php">Home</a>
        <a href="products.php">Products</a>
        <a href="equipment.php">Equipment</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
    <div class="header-actions">
        <span style="color:#fff; font-size:14px; margin-right:10px;">Hi, <strong><?= htmlspecialchars($firstName) ?></strong></span>
        <a href="logout.php" class="btn btn-light" style="background:#fff; color:#122017; font-weight:600; border:none; padding:8px 16px; border-radius:3px;">Logout</a>
    </div>
</div>
</header>

<main class="page-wrap">
    <div class="page-header">
        <div>
            <span class="eyebrow" style="color:#768047; font-family:monospace; text-transform:uppercase; letter-spacing:2px; font-size:12px; font-weight:700;">Alerts & Communication</span>
            <h1>Notifications & Mobile SMS</h1>
        </div>
        <div>
            <button type="button" id="markAllReadBtn" class="btn-mark-all <?= ($unreadCount === 0) ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Mark All as Read</span>
                <span id="unreadCountBadge" style="background:#b29438; color:#fff; font-size:11px; padding:1px 6px; border-radius:10px; margin-left:2px; <?= ($unreadCount === 0) ? 'display:none;' : '' ?>"><?= $unreadCount ?></span>
            </button>
        </div>
    </div>

    <!-- SECTION 1: IN-APP NOTIFICATIONS -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin:0 0 16px;">
        <h2 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin:0;">🔔 In-App Updates</h2>
        <span id="subUnreadText" style="font-size:13px; color:#768047; font-weight:600;">
            <?= $unreadCount > 0 ? "You have $unreadCount unread update" . ($unreadCount > 1 ? 's' : '') : 'All caught up' ?>
        </span>
    </div>

    <?php if (empty($notifications)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:40px; text-align:center; color:#6b6a59; margin-bottom:40px; border-radius:4px;">
            <h3 style="font-family:Georgia,serif; font-size:20px; margin-bottom:8px; color:#122017;">No in-app notifications</h3>
            <p style="margin:0; font-size:14px;">Updates regarding orders, rentals, and payments will appear here.</p>
        </div>
    <?php else: ?>
        <div id="notificationsContainer" style="margin-bottom:45px;">
            <?php foreach ($notifications as $n): ?>
                <?php $isUnread = !(bool)$n['is_read']; ?>
                <div class="notif-card <?= $isUnread ? 'unread' : '' ?>" 
                     data-id="<?= (int)$n['notification_id'] ?>" 
                     data-unread="<?= $isUnread ? '1' : '0' ?>"
                     tabindex="0"
                     role="button"
                     aria-label="<?= htmlspecialchars($n['title']) ?>">
                    
                    <div class="notif-header-row">
                        <div class="notif-title">
                            <?= htmlspecialchars($n['title']) ?>
                            <?php if ($isUnread): ?>
                                <span class="badge-new">New</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isUnread): ?>
                            <span class="unread-hint">Click to mark read</span>
                        <?php endif; ?>
                    </div>

                    <p class="notif-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></p>

                    <div class="notif-meta">
                        <span>🕒 <?= date('M d, Y • h:i A', strtotime($n['created_at'])) ?></span>
                        
                        <?php if ($n['notification_type'] === 'order' && !empty($n['related_id'])): ?>
                            <a href="order_details.php?id=<?= (int)$n['related_id'] ?>" 
                               class="notif-action-link"
                               data-notif-id="<?= (int)$n['notification_id'] ?>">
                                <span>View Order #<?= (int)$n['related_id'] ?></span>
                                <span>→</span>
                            </a>
                        <?php elseif ($n['notification_type'] === 'booking' && !empty($n['related_id'])): ?>
                            <a href="booking_details.php?id=<?= (int)$n['related_id'] ?>" 
                               class="notif-action-link"
                               data-notif-id="<?= (int)$n['notification_id'] ?>">
                                <span>View Booking #<?= (int)$n['related_id'] ?></span>
                                <span>→</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- SECTION 2: MOBILE SMS DISPATCH LOGS -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="font-family:Georgia,serif; font-size:24px; color:#122017; margin:0;">📱 Mobile SMS Notifications Dispatched</h2>
        <span style="font-size:12px; color:#768047; font-weight:600;">Direct to Renter Cellular SIM</span>
    </div>

    <?php if (empty($smsLogs)): ?>
        <div style="background:#fff; border:1px solid #ded6b9; padding:40px; text-align:center; color:#6b6a59; border-radius:4px;">
            <h3 style="font-family:Georgia,serif; font-size:20px; margin-bottom:8px; color:#122017;">No SMS alerts dispatched yet</h3>
            <p style="margin:0; font-size:14px;">Automated rental expiry SMS alerts and booking updates sent to your phone number will be recorded here.</p>
        </div>
    <?php else: ?>
        <div>
            <?php foreach ($smsLogs as $sms): ?>
                <div class="sms-box">
                    <div class="sms-phone">
                        <span>📱 Sent to Mobile:</span>
                        <strong><?= htmlspecialchars($sms['phone_number']) ?></strong>
                    </div>
                    <div class="sms-body">
                        "<?= htmlspecialchars($sms['message']) ?>"
                    </div>
                    <div class="sms-meta">
                        <span style="display:inline-flex; align-items:center; gap:4px;">
                            <span style="width:7px; height:7px; border-radius:50%; background:#23581c; display:inline-block;"></span>
                            Status: <strong><?= htmlspecialchars(strtoupper($sms['status'] ?? 'DELIVERED')) ?></strong>
                        </span>
                        <span>Dispatched: <?= date('M d, Y • h:i A', strtotime($sms['sent_at'])) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Toast Popup Container -->
<div id="toastPopup" class="toast-popup">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
    <span id="toastMessage">Notification marked as read</span>
</div>

<footer class="site-footer">
<div class="wrap">
    <div class="footer-bottom">
        <span>© 2026 AgriMart. All rights reserved.</span>
        <span>Digital Market Platform on Agricultural Products</span>
    </div>
</div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const markAllBtn = document.getElementById('markAllReadBtn');
    const unreadCountBadge = document.getElementById('unreadCountBadge');
    const subUnreadText = document.getElementById('subUnreadText');
    const toast = document.getElementById('toastPopup');
    const toastMessage = document.getElementById('toastMessage');

    function showToast(msg) {
        if (!toast) return;
        toastMessage.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }

    function updateUnreadCounter(count) {
        if (unreadCountBadge) {
            if (count > 0) {
                unreadCountBadge.textContent = count;
                unreadCountBadge.style.display = 'inline-block';
            } else {
                unreadCountBadge.style.display = 'none';
            }
        }
        if (subUnreadText) {
            subUnreadText.textContent = count > 0 
                ? `You have ${count} unread update${count > 1 ? 's' : ''}` 
                : 'All caught up';
        }
        if (markAllBtn) {
            if (count === 0) {
                markAllBtn.classList.add('disabled');
            } else {
                markAllBtn.classList.remove('disabled');
            }
        }
    }

    // Function to mark a single card as read in UI & DB
    function markCardAsRead(card) {
        if (!card || card.dataset.unread !== '1') return;

        const notifId = card.dataset.id;
        
        // Optimistic UI update
        card.classList.remove('unread');
        card.dataset.unread = '0';
        const badge = card.querySelector('.badge-new');
        if (badge) {
            badge.style.opacity = '0';
            badge.style.transform = 'scale(0.8)';
            setTimeout(() => badge.remove(), 250);
        }
        const hint = card.querySelector('.unread-hint');
        if (hint) {
            hint.remove();
        }

        // Count remaining unread in DOM
        const remainingUnread = document.querySelectorAll('.notif-card[data-unread="1"]').length;
        updateUnreadCounter(remainingUnread);

        // Send AJAX request
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notifId);

        fetch('notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && typeof data.unread_count !== 'undefined') {
                updateUnreadCounter(data.unread_count);
            }
        })
        .catch(err => {
            console.error('Error marking notification as read:', err);
        });
    }

    // Attach click handlers to cards
    document.querySelectorAll('.notif-card').forEach(card => {
        // When clicking anywhere on the unread card (except when clicking direct links, which have their own handler)
        card.addEventListener('click', function(e) {
            if (e.target.closest('a')) return; // Allow normal link click
            if (card.dataset.unread === '1') {
                markCardAsRead(card);
                showToast('Marked as read');
            }
        });

        // Also allow Enter or Space key for accessibility
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                if (card.dataset.unread === '1') {
                    e.preventDefault();
                    markCardAsRead(card);
                    showToast('Marked as read');
                }
            }
        });
    });

    // Attach click handlers to internal action links (View Order / View Booking)
    document.querySelectorAll('.notif-action-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const card = link.closest('.notif-card');
            if (card && card.dataset.unread === '1') {
                // Instantly update UI and send beacon / request
                const notifId = link.dataset.notifId || card.dataset.id;
                const formData = new FormData();
                formData.append('action', 'mark_read');
                formData.append('notification_id', notifId);
                
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('notifications.php', formData);
                } else {
                    fetch('notifications.php', {
                        method: 'POST',
                        body: formData,
                        keepalive: true
                    });
                }
            }
        });
    });

    // Handle "Mark All as Read" button
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (markAllBtn.classList.contains('disabled')) return;

            // Optimistic UI update on all cards
            document.querySelectorAll('.notif-card.unread').forEach(card => {
                card.classList.remove('unread');
                card.dataset.unread = '0';
                const badge = card.querySelector('.badge-new');
                if (badge) badge.remove();
                const hint = card.querySelector('.unread-hint');
                if (hint) hint.remove();
            });

            updateUnreadCounter(0);
            showToast('All notifications marked as read');

            // Send AJAX request
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateUnreadCounter(0);
                }
            })
            .catch(err => {
                console.error('Error marking all as read:', err);
                // Fallback to GET navigation if AJAX failed
                window.location.href = 'notifications.php?mark_all_read=1';
            });
        });
    }
});
</script>

</body>
</html>
