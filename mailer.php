<?php
/**
 * AgriMart Automated Gmail & Email Notification Service (mailer.php)
 */

if (!defined('AGRIMART_MAILER_CONFIG')) {
    define('AGRIMART_MAILER_CONFIG', true);

    // =========================================================================
    // GMAIL / SMTP CONFIGURATION
    // =========================================================================
    define('SMTP_ENABLED', true); // Active live Gmail delivery
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587); // 587 for TLS
    define('SMTP_SECURE', 'tls'); // 'tls'
    define('SMTP_USER', 'christianlucero522@gmail.com'); // Configured Sender Gmail
    define('SMTP_PASS', 'kjczooeoxpmcbzeh'); // Configured App Password
    define('MAIL_FROM_NAME', 'AgriMart Notifications');
    define('MAIL_FROM_EMAIL', 'christianlucero522@gmail.com');
}

/**
 * Send an HTML email via Gmail SMTP or fallback
 */
function sendAgriMartEmail($toEmail, $toName, $subject, $headline, $contentHtml, $actionUrl = '', $actionText = 'View in AgriMart') {
    if (empty($toEmail)) return false;

    // Compose HTML template
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { margin:0; padding:0; background:#f4f0df; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color:#162018; }
            .email-container { max-width:600px; margin:30px auto; background:#ffffff; border:1px solid #ded6b9; border-radius:6px; overflow:hidden; }
            .email-header { background:#0d2818; padding:28px 30px; border-bottom:3px solid #d6b95f; color:#f5f0df; }
            .email-brand { font-family:Georgia, serif; font-size:24px; font-weight:bold; color:#f5f0df; margin:0; }
            .email-tagline { font-family:monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#d6b95f; margin-top:4px; }
            .email-body { padding:32px 30px; font-size:14.5px; line-height:1.6; color:#2d382e; }
            .email-headline { font-family:Georgia, serif; font-size:20px; color:#122017; margin:0 0 16px; border-bottom:1px solid #eee8d5; padding-bottom:10px; }
            .email-btn { display:inline-block; background:#768047; color:#ffffff !important; text-decoration:none; padding:12px 24px; font-size:13px; font-weight:bold; border-radius:4px; margin-top:20px; text-transform:uppercase; letter-spacing:1px; font-family:monospace; }
            .email-footer { background:#f9f7f0; padding:20px 30px; font-size:12px; color:#7d7967; text-align:center; border-top:1px solid #eee8d5; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <div class="email-brand">AgriMart</div>
                <div class="email-tagline">Field to Farm Gate • Digital Marketplace</div>
            </div>
            <div class="email-body">
                <h2 class="email-headline">' . htmlspecialchars($headline) . '</h2>
                <div>' . $contentHtml . '</div>
                ' . (!empty($actionUrl) ? '<div style="margin-top:24px;"><a href="' . htmlspecialchars($actionUrl) . '" class="email-btn">' . htmlspecialchars($actionText) . ' →</a></div>' : '') . '
            </div>
            <div class="email-footer">
                <p style="margin:0 0 6px;">You received this automated notification for your AgriMart account (<strong>' . htmlspecialchars($toEmail) . '</strong>).</p>
                <p style="margin:0;">© 2026 AgriMart Philippines. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    // Always log email copy to uploads/mail_logs for inspection/offline testing
    $logDir = __DIR__ . '/uploads/mail_logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    $filename = 'email_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $toEmail) . '.html';
    @file_put_contents($logDir . $filename, $html);

    if (SMTP_ENABLED) {
        return sendViaSocketSmtp($toEmail, $toName, $subject, $html);
    } else {
        // Fallback to PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM_EMAIL . "\r\n";
        $headers .= "X-Mailer: AgriMart Mailer v2.0\r\n";
        return @mail($toEmail, $subject, $html, $headers);
    }
}

/**
 * Socket-based SMTP Client for Gmail (STARTTLS / SSL)
 */
function sendViaSocketSmtp($to, $toName, $subject, $htmlBody) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $secure = SMTP_SECURE;
    $user = SMTP_USER;
    $pass = SMTP_PASS;

    $timeout = 10;
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$socket) return false;

    $read = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $read();
    $send("EHLO localhost");

    if ($secure === 'tls') {
        $send("STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send("EHLO localhost");
    }

    $send("AUTH LOGIN");
    $send(base64_encode($user));
    $authRes = $send(base64_encode($pass));
    if (strpos($authRes, '235') === false) {
        fclose($socket);
        return false;
    }

    $send("MAIL FROM: <$user>");
    $send("RCPT TO: <$to>");
    $send("DATA");

    $mime = "MIME-Version: 1.0\r\n";
    $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mime .= "From: =?UTF-8?B?" . base64_encode(MAIL_FROM_NAME) . "?= <$user>\r\n";
    $mime .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$to>\r\n";
    $mime .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $mime .= "Date: " . date('r') . "\r\n\r\n";
    $mime .= $htmlBody . "\r\n.";

    $send($mime);
    $send("QUIT");
    fclose($socket);
    return true;
}

/**
 * Send Gmail notification when an order is placed
 */
function sendOrderPlacedEmails($orderId, $conn) {
    $orderSql = "
        SELECT o.order_id, o.total_amount, o.shipping_address, o.buyer_id, u.full_name AS buyer_name, u.email AS buyer_email,
               p.payment_method, p.buyer_bank_name, p.buyer_account_name, p.buyer_account_number, p.transaction_ref
        FROM orders o
        INNER JOIN users u ON o.buyer_id = u.user_id
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.order_id = ? LIMIT 1
    ";
    $stmt = $conn->prepare($orderSql);
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) return;

    // Fetch order items and sellers
    $itemsSql = "
        SELECT oi.quantity, oi.price, oi.subtotal, p.product_name, p.unit, s.user_id AS seller_id, s.full_name AS seller_name, s.email AS seller_email, s.phone AS seller_phone
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.product_id
        INNER JOIN users s ON oi.seller_id = s.user_id
        WHERE oi.order_id = ?
    ";
    $iStmt = $conn->prepare($itemsSql);
    $iStmt->bind_param('i', $orderId);
    $iStmt->execute();
    $itemsResult = $iStmt->get_result();

    $itemsList = [];
    $sellerItems = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $itemsList[] = $row;
        $sellerItems[$row['seller_email']][] = $row;
    }
    $iStmt->close();

    // 1. Send confirmation email to Buyer
    $buyerItemsHtml = '<table style="width:100%; border-collapse:collapse; margin-top:12px;">';
    $buyerItemsHtml .= '<tr style="background:#f9f7f0; text-align:left; font-size:12px; text-transform:uppercase; font-family:monospace;"><th style="padding:8px;">Item</th><th style="padding:8px;">Qty</th><th style="padding:8px;">Price</th><th style="padding:8px;">Subtotal</th></tr>';
    foreach ($itemsList as $it) {
        $buyerItemsHtml .= '<tr style="border-bottom:1px solid #eee;">';
        $buyerItemsHtml .= '<td style="padding:8px;">' . htmlspecialchars($it['product_name']) . '</td>';
        $buyerItemsHtml .= '<td style="padding:8px;">' . (int)$it['quantity'] . ' ' . htmlspecialchars($it['unit'] ?? '') . '</td>';
        $buyerItemsHtml .= '<td style="padding:8px;">₱' . number_format((float)$it['price'], 2) . '</td>';
        $buyerItemsHtml .= '<td style="padding:8px;">₱' . number_format((float)$it['subtotal'], 2) . '</td>';
        $buyerItemsHtml .= '</tr>';
    }
    $buyerItemsHtml .= '</table>';

    $buyerBody = '
        <p>Hello <strong>' . htmlspecialchars($order['buyer_name']) . '</strong>,</p>
        <p>Thank you for your order! Your purchase has been received and forwarded to the respective farmer/seller.</p>
        <div style="background:#fdfbf7; border:1px solid #ded6b9; padding:15px; margin:15px 0;">
            <div><strong>Order ID:</strong> #' . (int)$order['order_id'] . '</div>
            <div><strong>Total Amount:</strong> ₱' . number_format((float)$order['total_amount'], 2) . '</div>
            <div><strong>Payment Method:</strong> ' . strtoupper(htmlspecialchars($order['payment_method'] ?? 'CASH')) . '</div>
            <div><strong>Delivery Address:</strong> ' . htmlspecialchars($order['shipping_address']) . '</div>
        </div>
        ' . $buyerItemsHtml . '
    ';

    sendAgriMartEmail(
        $order['buyer_email'],
        $order['buyer_name'],
        "Order #" . $order['order_id'] . " Confirmed — AgriMart",
        "Order #" . $order['order_id'] . " Confirmation",
        $buyerBody,
        "http://localhost/AgriMart/agrimart-frontend/assets/order_details.php?id=" . $order['order_id'],
        "View Order Details"
    );

    // 2. Send notification email to each Seller
    foreach ($sellerItems as $sellerEmail => $sItems) {
        $sName = $sItems[0]['seller_name'] ?? 'Seller';
        $sItemsHtml = '<table style="width:100%; border-collapse:collapse; margin-top:12px;">';
        $sItemsHtml .= '<tr style="background:#f9f7f0; text-align:left; font-size:12px; text-transform:uppercase; font-family:monospace;"><th style="padding:8px;">Crop / Product</th><th style="padding:8px;">Quantity</th><th style="padding:8px;">Subtotal</th></tr>';
        $sellerTotal = 0;
        foreach ($sItems as $sit) {
            $sellerTotal += (float)$sit['subtotal'];
            $sItemsHtml .= '<tr style="border-bottom:1px solid #eee;">';
            $sItemsHtml .= '<td style="padding:8px;">' . htmlspecialchars($sit['product_name']) . '</td>';
            $sItemsHtml .= '<td style="padding:8px;">' . (int)$sit['quantity'] . ' ' . htmlspecialchars($sit['unit'] ?? '') . '</td>';
            $sItemsHtml .= '<td style="padding:8px;">₱' . number_format((float)$sit['subtotal'], 2) . '</td>';
            $sItemsHtml .= '</tr>';
        }
        $sItemsHtml .= '</table>';

        $sellerBody = '
            <p>Hello <strong>' . htmlspecialchars($sName) . '</strong>,</p>
            <p>Great news! A customer has placed a new order for your agricultural produce.</p>
            <div style="background:#fdfbf7; border:1px solid #ded6b9; padding:15px; margin:15px 0;">
                <div><strong>Order ID:</strong> #' . (int)$order['order_id'] . '</div>
                <div><strong>Customer:</strong> ' . htmlspecialchars($order['buyer_name']) . '</div>
                <div><strong>Delivery Address:</strong> ' . htmlspecialchars($order['shipping_address']) . '</div>
                <div><strong>Your Revenue:</strong> ₱' . number_format($sellerTotal, 2) . '</div>
                ' . (!empty($order['buyer_bank_name']) ? '<div><strong>Buyer Payment:</strong> ' . htmlspecialchars($order['buyer_bank_name']) . ' (' . htmlspecialchars($order['buyer_account_name']) . ' - ' . htmlspecialchars($order['buyer_account_number']) . ')</div>' : '') . '
            </div>
            ' . $sItemsHtml . '
        ';

        sendAgriMartEmail(
            $sellerEmail,
            $sName,
            "New Order #" . $order['order_id'] . " Received — AgriMart",
            "New Order Received: #" . $order['order_id'],
            $sellerBody,
            "http://localhost/AgriMart/agrimart-frontend/assets/seller_orders.php",
            "Manage Customer Orders"
        );
    }
}

/**
 * Send Gmail notification when equipment is booked
 */
function sendBookingCreatedEmails($bookingId, $conn) {
    $sql = "
        SELECT b.booking_id, b.start_date, b.end_date, b.pickup_location, b.dropoff_location, b.total_amount, b.security_deposit,
               e.equipment_name,
               renter.user_id AS renter_id, renter.full_name AS renter_name, renter.email AS renter_email, renter.phone AS renter_phone,
               owner.full_name AS owner_name, owner.email AS owner_email, owner.phone AS owner_phone,
               p.payment_method, p.buyer_bank_name, p.buyer_account_name, p.buyer_account_number, p.transaction_ref
        FROM bookings b
        INNER JOIN equipment e ON b.equipment_id = e.equipment_id
        INNER JOIN users renter ON b.renter_id = renter.user_id
        INNER JOIN users owner ON b.owner_id = owner.user_id
        LEFT JOIN payments p ON b.booking_id = p.booking_id
        WHERE b.booking_id = ? LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$b) return;

    $secDeposit = (float)($b['security_deposit'] ?? 0);
    if ($secDeposit <= 0) {
        $secDeposit = round(((float)$b['total_amount'] / 1.20) * 0.20, 2);
    }
    $rentalSubtotal = (float)$b['total_amount'] - $secDeposit;

    // 1. Email to Equipment Owner
    $ownerBody = '
        <p>Hello <strong>' . htmlspecialchars($b['owner_name']) . '</strong>,</p>
        <p>A user has requested to rent your farm machinery <strong>' . htmlspecialchars($b['equipment_name']) . '</strong>.</p>
        <div style="background:#fdfbf7; border:1px solid #ded6b9; padding:15px; margin:15px 0;">
            <div><strong>Booking ID:</strong> #' . (int)$b['booking_id'] . '</div>
            <div><strong>Equipment:</strong> ' . htmlspecialchars($b['equipment_name']) . '</div>
            <div><strong>Rental Period:</strong> ' . htmlspecialchars($b['start_date']) . ' to ' . htmlspecialchars($b['end_date']) . '</div>
            <div><strong>Rental Subtotal:</strong> ₱' . number_format($rentalSubtotal, 2) . '</div>
            <div><strong>🛡️ 20% Security Deposit (Damage/Loss):</strong> ₱' . number_format($secDeposit, 2) . '</div>
            <div style="margin-top:6px; font-size:16px; font-weight:bold; color:#122017;"><strong>Total Booking Fee:</strong> ₱' . number_format((float)$b['total_amount'], 2) . '</div>
            <div><strong>Renter Contact:</strong> ' . htmlspecialchars($b['renter_name']) . ' (' . htmlspecialchars($b['renter_phone'] ?: $b['renter_email']) . ')</div>
            <div><strong>Pickup Location:</strong> ' . htmlspecialchars($b['pickup_location']) . '</div>
        </div>
    ';

    sendAgriMartEmail(
        $b['owner_email'],
        $b['owner_name'],
        "New Rental Request #" . $b['booking_id'] . " for " . $b['equipment_name'] . " — AgriMart",
        "New Machinery Booking Request",
        $ownerBody,
        getAgriMartBaseUrl() . 'rental_requests.php',
        "Review Rental Request"
    );

    // 2. Email to Renter
    $renterBody = '
        <p>Hello <strong>' . htmlspecialchars($b['renter_name']) . '</strong>,</p>
        <p>Your rental booking request for <strong>' . htmlspecialchars($b['equipment_name']) . '</strong> has been submitted to the machine owner.</p>
        <div style="background:#fdfbf7; border:1px solid #ded6b9; padding:15px; margin:15px 0;">
            <div><strong>Booking ID:</strong> #' . (int)$b['booking_id'] . '</div>
            <div><strong>Equipment:</strong> ' . htmlspecialchars($b['equipment_name']) . '</div>
            <div><strong>Rental Dates:</strong> ' . htmlspecialchars($b['start_date']) . ' to ' . htmlspecialchars($b['end_date']) . '</div>
            <div><strong>Rental Subtotal:</strong> ₱' . number_format($rentalSubtotal, 2) . '</div>
            <div><strong>🛡️ 20% Refundable Deposit (Damage/Loss):</strong> ₱' . number_format($secDeposit, 2) . '</div>
            <div style="margin-top:6px; font-size:16px; font-weight:bold; color:#122017;"><strong>Total Fee Due:</strong> ₱' . number_format((float)$b['total_amount'], 2) . '</div>
            <div><strong>Owner Contact:</strong> ' . htmlspecialchars($b['owner_name']) . ' (' . htmlspecialchars($b['owner_phone'] ?: 'N/A') . ')</div>
        </div>
        <p style="font-size:12px; color:#596054;">* The 20% security deposit will be refunded / released upon safe return of the machine in good condition.</p>
    ';

    sendAgriMartEmail(
        $b['renter_email'],
        $b['renter_name'],
        "Booking #" . $b['booking_id'] . " Submitted — AgriMart",
        "Equipment Booking Confirmation",
        $renterBody,
        "http://localhost/AgriMart/agrimart-frontend/assets/booking_details.php?id=" . $b['booking_id'],
        "View Booking Details"
    );
}

/**
 * Send Gmail notification when order status changes
 */
function sendOrderStatusEmail($orderId, $newStatus, $conn) {
    $stmt = $conn->prepare("SELECT o.order_id, o.total_amount, u.full_name, u.email FROM orders o INNER JOIN users u ON o.buyer_id = u.user_id WHERE o.order_id = ? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) return;

    $body = '
        <p>Hello <strong>' . htmlspecialchars($order['full_name']) . '</strong>,</p>
        <p>Your order <strong>#' . (int)$order['order_id'] . '</strong> has been updated to <strong style="text-transform:uppercase; color:#768047;">' . htmlspecialchars($newStatus) . '</strong> by the seller.</p>
        <p>Total amount: ₱' . number_format((float)$order['total_amount'], 2) . '</p>
    ';

    sendAgriMartEmail(
        $order['email'],
        $order['full_name'],
        "Order #" . $order['order_id'] . " Status Update: " . ucfirst($newStatus) . " — AgriMart",
        "Order Update: " . ucfirst($newStatus),
        $body,
        "http://localhost/AgriMart/agrimart-frontend/assets/order_details.php?id=" . $order['order_id'],
        "Track Order"
    );
}

/**
 * Send Gmail notification when ID verification changes
 */
function sendVerificationStatusEmail($userId, $status, $conn) {
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) return;

    if ($status === 'verified') {
        $subject = "Your AgriMart Account is Verified! ✓";
        $headline = "Identity & Farmer ID Verified";
        $body = '<p>Hello <strong>' . htmlspecialchars($user['full_name']) . '</strong>,</p><p>Congratulations! Your submitted identification has been reviewed and approved by AgriMart administrators. You now have full verified status across the marketplace.</p>';
    } else {
        $subject = "AgriMart Identity Verification Update";
        $headline = "Verification Status: " . ucfirst($status);
        $body = '<p>Hello <strong>' . htmlspecialchars($user['full_name']) . '</strong>,</p><p>Your identification status was updated to <strong>' . htmlspecialchars($status) . '</strong>. Please check your profile or contact administrator for further information.</p>';
    }

    sendAgriMartEmail(
        $user['email'],
        $user['full_name'],
        $subject,
        $headline,
        $body,
        getAgriMartBaseUrl() . "profile.php",
        "View Profile"
    );
}

/**
 * Base URL helper for generating full absolute URLs in emails
 */
function getAgriMartBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/AgriMart/agrimart-frontend/assets/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.') $dir = '';
    return rtrim($protocol . $host . $dir, '/') . '/';
}

/**
 * Send Gmail notification with email verification activation link
 */
function sendVerificationEmail($toEmail, $toName, $token) {
    $baseUrl = getAgriMartBaseUrl();
    $verifyUrl = $baseUrl . "verify_email.php?token=" . urlencode($token);

    $subject = "Verify Your Email Address — AgriMart";
    $headline = "Welcome to AgriMart, " . $toName . "!";
    $contentHtml = '
        <p>Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
        <p>Thank you for creating an account on <strong>AgriMart</strong>. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
        <div style="background:#fdfbf7; border:1px solid #ded6b9; padding:16px; margin:20px 0; border-radius:4px;">
            <p style="margin:0 0 8px; font-size:12.5px; color:#596054;">If the button below does not work, copy and paste this verification link into your web browser:</p>
            <a href="' . htmlspecialchars($verifyUrl) . '" style="font-size:13px; color:#768047; word-break:break-all;">' . htmlspecialchars($verifyUrl) . '</a>
        </div>
        <p style="font-size:12px; color:#7d7967; margin-top:20px;">If you did not register for an AgriMart account, you can safely ignore this email.</p>
    ';

    return sendAgriMartEmail(
        $toEmail,
        $toName,
        $subject,
        $headline,
        $contentHtml,
        $verifyUrl,
        "Verify Email Address"
    );
}

