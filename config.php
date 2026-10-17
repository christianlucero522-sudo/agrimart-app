<?php
// Database Configuration (Supports Localhost XAMPP & Cloud/Vercel/Clever Cloud/Railway/Aiven/TiDB)

// 1. Support URL format (Clever Cloud MYSQL_ADDON_URI, DATABASE_URL or MYSQL_URL)
$dbUrl = getenv('MYSQL_ADDON_URI') ?: (getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: (getenv('JAWSDB_URL') ?: (getenv('CLEARDB_DATABASE_URL') ?: ''))));

if (!empty($dbUrl)) {
    $parsed = parse_url($dbUrl);
    $host = $parsed['host'] ?? '127.0.0.1';
    $user = $parsed['user'] ?? 'root';
    $password = $parsed['pass'] ?? '';
    $database = ltrim($parsed['path'] ?? 'agrimart', '/');
    $port = (int)($parsed['port'] ?? 3306);
} else {
    // 2. Support standard and Clever Cloud individual environment variables
    $host = getenv('MYSQL_ADDON_HOST') ?: (getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1')));
    $user = getenv('MYSQL_ADDON_USER') ?: (getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: (getenv('MYSQLUSER') ?: 'root')));
    $password = getenv('MYSQL_ADDON_PASSWORD') !== false ? getenv('MYSQL_ADDON_PASSWORD') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '')));
    $database = getenv('MYSQL_ADDON_DB') ?: (getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: (getenv('MYSQLDATABASE') ?: (getenv('DB_DATABASE') ?: 'agrimart'))));
    $port = (int)(getenv('MYSQL_ADDON_PORT') ?: (getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: (getenv('MYSQLPORT') ?: 3306))));
}

$ssl = getenv('DB_SSL') ?: (getenv('MYSQL_SSL') ?: false);

mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

if ($ssl) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $flags = MYSQLI_CLIENT_SSL;
} else {
    $flags = 0;
}

$connected = @$conn->real_connect($host, $user, $password, $database, $port, NULL, $flags);

if (!$connected) {
    // Try fallback to localhost if 127.0.0.1 was specified
    if ($host === '127.0.0.1' || $host === 'localhost') {
        $connected = @$conn->real_connect('localhost', $user, $password, $database, $port);
    }
}

if (!$connected) {
    $connError = $conn->connect_error ?: 'Connection refused / host unreachable';
    $isVercel = (getenv('VERCEL') || strpos($_SERVER['HTTP_HOST'] ?? '', 'vercel.app') !== false);
    
    // If on Vercel or cloud without configured database, render a friendly setup guide
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AgriMart — Database Connection Error</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f4f0df; color: #162018; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 25px; }
            .setup-card { background: #fff; border: 1px solid #ded6b9; border-radius: 8px; max-width: 680px; width: 100%; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
            .badge { display: inline-block; background: #fee2e2; color: #991b1b; padding: 6px 12px; font-size: 11px; font-weight: 700; border-radius: 4px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; }
            h1 { font-family: Georgia, serif; font-size: 28px; color: #122017; margin-bottom: 12px; }
            p { font-size: 14px; line-height: 1.6; color: #555; margin-bottom: 18px; }
            .error-box { background: #fff5f5; border: 1px solid #fed7d7; border-left: 4px solid #e53e3e; padding: 12px 16px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #c53030; margin-bottom: 25px; word-break: break-all; }
            .steps-container { background: #faf8f0; border: 1px solid #e9e3cb; border-radius: 6px; padding: 20px; margin-bottom: 25px; }
            .steps-container h3 { font-size: 15px; font-weight: 700; color: #122017; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .step-item { display: flex; gap: 12px; margin-bottom: 14px; font-size: 13.5px; }
            .step-num { width: 22px; height: 22px; background: #2f4329; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; margin-top: 2px; }
            .env-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
            .env-table th, .env-table td { padding: 8px 10px; border: 1px solid #d8d0b7; text-align: left; }
            .env-table th { background: #ede7d3; font-weight: 700; color: #333; }
            .env-table code { background: #fff; padding: 2px 6px; border-radius: 3px; font-weight: 600; color: #2f4329; border: 1px solid #ddd; }
            .action-btn { display: inline-block; background: #2f4329; color: #fff; text-decoration: none; padding: 12px 24px; font-size: 13px; font-weight: 600; border-radius: 4px; border: none; cursor: pointer; }
            .action-btn:hover { background: #1c2b18; }
        </style>
    </head>
    <body>
        <div class="setup-card">
        <?php if (!$isVercel): ?>
            <span class="badge">Local MySQL Stopped</span>
            <h1>🌾 Start XAMPP MySQL Service</h1>
            <p>
                AgriMart could not connect to your local MySQL database (<code>127.0.0.1:3306</code>). The MySQL module in your XAMPP Control Panel is currently stopped.
            </p>

            <div class="error-box">
                <strong>Connection Error:</strong> <?= htmlspecialchars($connError) ?> (Target: <?= htmlspecialchars($host . ':' . $port . ' / ' . $database) ?>)
            </div>

            <div class="steps-container">
                <h3>🛠️ How to fix on Localhost:</h3>
                <div class="step-item">
                    <div class="step-num">1</div>
                    <div>Open the <strong>XAMPP Control Panel</strong> on your computer.</div>
                </div>
                <div class="step-item">
                    <div class="step-num">2</div>
                    <div>Click the <strong>Start</strong> button next to <strong>MySQL</strong>.</div>
                </div>
                <div class="step-item">
                    <div class="step-num">3</div>
                    <div>Refresh this page once MySQL turns green with port 3306.</div>
                </div>
            </div>

            <div>
                <a href="javascript:location.reload()" class="action-btn">🔄 Retry Connection</a>
            </div>
        <?php else: ?>
            <span class="badge">Cloud Database Configuration Required</span>
            <h1>🌾 Connect AgriMart to Cloud MySQL</h1>
            <p>
                Your AgriMart application is deployed and running on Vercel. Because Vercel functions are serverless cloud containers, they cannot connect to local <code>127.0.0.1</code> and need a cloud MySQL database.
            </p>

            <div class="error-box">
                <strong>Connection Status:</strong> <?= htmlspecialchars($connError) ?> (Target: <?= htmlspecialchars($host . ':' . $port . ' / ' . $database) ?>)
            </div>

            <div class="steps-container">
                <h3>🚀 Quick 3-Step Setup:</h3>
                
                <div class="step-item">
                    <div class="step-num">1</div>
                    <div>
                        <strong>Get a Free Cloud MySQL Database:</strong><br>
                        Create a free database on <a href="https://aiven.io/" target="_blank" style="color:#2f4329; font-weight:600;">Aiven.io</a>, <a href="https://tidbcloud.com/" target="_blank" style="color:#2f4329; font-weight:600;">TiDB Cloud</a>, <a href="https://railway.app/" target="_blank" style="color:#2f4329; font-weight:600;">Railway.app</a>, or <a href="https://www.clever-cloud.com/" target="_blank" style="color:#2f4329; font-weight:600;">Clever Cloud</a>.
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-num">2</div>
                    <div>
                        <strong>Import Database Schema:</strong><br>
                        Import the included SQL database dump file <code>database/agrimart.sql</code> into your cloud MySQL database.
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-num">3</div>
                    <div>
                        <strong>Set Vercel Environment Variables:</strong><br>
                        In your <strong>Vercel Project Dashboard &rarr; Settings &rarr; Environment Variables</strong>, add:
                        
                        <table class="env-table">
                            <thead>
                                <tr>
                                    <th>Variable Name</th>
                                    <th>Description / Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>DB_HOST</code></td>
                                    <td>Cloud database host (e.g. <code>mysql-xxx.aivencloud.com</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>DB_USER</code></td>
                                    <td>Database username (e.g. <code>avnadmin</code> or <code>root</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>DB_PASSWORD</code></td>
                                    <td>Database user password</td>
                                </tr>
                                <tr>
                                    <td><code>DB_NAME</code></td>
                                    <td>Database name (e.g. <code>defaultdb</code> or <code>agrimart</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>DB_PORT</code></td>
                                    <td>Database port (e.g. <code>3306</code> or cloud port)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <span style="font-size:12px; color:#888;">After adding environment variables, trigger a <strong>Redeploy</strong> in Vercel.</span>
                <a href="https://vercel.com/dashboard" target="_blank" class="action-btn">Open Vercel Dashboard &rarr;</a>
            </div>
        <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$conn->set_charset("utf8mb4");

// Auto-migration safeguard: ensure all required columns exist across tables
static $schemaChecked = false;
if (!$schemaChecked) {
    $schemaChecked = true;

    // Check products columns
    $pCols = [];
    $pRes = @$conn->query("SHOW COLUMNS FROM products");
    if ($pRes) {
        while ($r = $pRes->fetch_assoc()) { $pCols[] = $r['Field']; }
        if (!in_array('location_city', $pCols)) {
            @$conn->query("ALTER TABLE products ADD COLUMN location_city VARCHAR(100) NULL DEFAULT NULL");
        }
        if (!in_array('location_province', $pCols)) {
            @$conn->query("ALTER TABLE products ADD COLUMN location_province VARCHAR(100) NULL DEFAULT NULL");
        }
    }

    // Check equipment columns
    $eCols = [];
    $eRes = @$conn->query("SHOW COLUMNS FROM equipment");
    if ($eRes) {
        while ($r = $eRes->fetch_assoc()) { $eCols[] = $r['Field']; }
        if (!in_array('location_city', $eCols)) {
            @$conn->query("ALTER TABLE equipment ADD COLUMN location_city VARCHAR(100) NULL DEFAULT NULL");
        }
        if (!in_array('location_province', $eCols)) {
            @$conn->query("ALTER TABLE equipment ADD COLUMN location_province VARCHAR(100) NULL DEFAULT NULL");
        }
    }

    // Check orders columns
    $oCols = [];
    $oRes = @$conn->query("SHOW COLUMNS FROM orders");
    if ($oRes) {
        while ($r = $oRes->fetch_assoc()) { $oCols[] = $r['Field']; }
        if (!in_array('cancellation_reason', $oCols)) {
            @$conn->query("ALTER TABLE orders ADD COLUMN cancellation_reason TEXT NULL DEFAULT NULL");
        }
        if (!in_array('cancelled_by', $oCols)) {
            @$conn->query("ALTER TABLE orders ADD COLUMN cancelled_by INT NULL DEFAULT NULL");
        }
    }

    // Check categories columns
    $cCols = [];
    $cRes = @$conn->query("SHOW COLUMNS FROM categories");
    if ($cRes) {
        while ($r = $cRes->fetch_assoc()) { $cCols[] = $r['Field']; }
        if (!in_array('rental_rate_type', $cCols)) {
            @$conn->query("ALTER TABLE categories ADD COLUMN rental_rate_type ENUM('daily', 'hourly') NOT NULL DEFAULT 'daily' AFTER category_type");
            @$conn->query("UPDATE categories SET rental_rate_type = 'hourly' WHERE category_type = 'equipment' AND (LOWER(category_name) LIKE '%pump%' OR LOWER(category_name) LIKE '%irrigation%' OR LOWER(category_name) LIKE '%sprayer%')");
        }
    }
}

require_once __DIR__ . '/sms_helper.php';
require_once __DIR__ . '/image_helper.php';

/**
 * Automatically synchronize user verification and account records between local XAMPP and Clever Cloud.
 * This guarantees that when a user verifies on mobile via Vercel, localhost immediately reflects the verified status.
 */
function syncUserVerificationWithCloud($email, $conn) {
    if (empty($email) || !$conn) return;

    $dbHost = $conn->host_info ?? '';
    $isLocal = (strpos($dbHost, '127.0.0.1') !== false || strpos($dbHost, 'localhost') !== false);
    if (!$isLocal) return;

    try {
        $cloudConn = @new mysqli('blpcpqtohlaqhpgy6nte-mysql.services.clever-cloud.com', 'ui0fiyieoo7h6gn8', 'yVyYoZcZjbLv0XlWzhYd', 'blpcpqtohlaqhpgy6nte', 3306);
        if ($cloudConn && !$cloudConn->connect_error) {
            $stmt = $cloudConn->prepare("SELECT user_id, full_name, email, phone, password, role, is_verified, face_verified, status, email_verified, id_type, id_number, id_card_image, face_image FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $cUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($cUser) {
                    $lStmt = $conn->prepare("SELECT user_id, email_verified, is_verified, face_verified FROM users WHERE email = ? LIMIT 1");
                    $lStmt->bind_param('s', $email);
                    $lStmt->execute();
                    $lUser = $lStmt->get_result()->fetch_assoc();
                    $lStmt->close();

                    if ($lUser) {
                        // User exists locally: sync verification state and KYC documents from cloud
                        $up = $conn->prepare("UPDATE users SET 
                            email_verified = GREATEST(email_verified, ?),
                            is_verified = CASE WHEN ? = 'verified' THEN 'verified' WHEN ? = 'pending' AND is_verified != 'verified' THEN 'pending' ELSE is_verified END,
                            face_verified = CASE WHEN ? = 'verified' THEN 'verified' WHEN ? = 'pending' AND face_verified != 'verified' THEN 'pending' ELSE face_verified END,
                            id_type = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE id_type END,
                            id_number = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE id_number END,
                            id_card_image = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE id_card_image END,
                            face_image = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE face_image END
                            WHERE user_id = ?");
                        if ($up) {
                            $emailVer = (int)$cUser['email_verified'];
                            $idVer = $cUser['is_verified'] ?? 'pending';
                            $faceVer = $cUser['face_verified'] ?? 'pending';
                            $idType = $cUser['id_type'];
                            $idNum = $cUser['id_number'];
                            $idImg = $cUser['id_card_image'];
                            $faceImg = $cUser['face_image'];
                            $lUid = (int)$lUser['user_id'];

                            $up->bind_param('isssssssssssssi', 
                                $emailVer,
                                $idVer, $idVer,
                                $faceVer, $faceVer,
                                $idType, $idType, $idType,
                                $idNum, $idNum, $idNum,
                                $idImg, $idImg, $idImg,
                                $faceImg, $faceImg, $faceImg,
                                $lUid
                            );
                            $up->execute();
                            $up->close();
                        }
                    } else {
                        // User was registered on Vercel: replicate user locally so they can log in seamlessly on localhost
                        $ins = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, is_verified, face_verified, status, email_verified, id_type, id_number, id_card_image, face_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($ins) {
                            $ins->bind_param('ssssssssissss', $cUser['full_name'], $cUser['email'], $cUser['phone'], $cUser['password'], $cUser['role'], $cUser['is_verified'], $cUser['face_verified'], $cUser['status'], $cUser['email_verified'], $cUser['id_type'], $cUser['id_number'], $cUser['id_card_image'], $cUser['face_image']);
                            $ins->execute();
                            $ins->close();
                        }
                    }
                }
            }
            $cloudConn->close();
        }
    } catch (\Throwable $t) {}
}

