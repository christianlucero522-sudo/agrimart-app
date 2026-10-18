<?php
// oauth_callback.php - OAuth Callback and Social Authentication Processor
session_start();
require_once __DIR__ . '/config.php';

$provider = strtolower(trim($_GET['provider'] ?? ($_POST['provider'] ?? 'google')));
if (!in_array($provider, ['google', 'facebook'])) {
    $provider = 'google';
}

$userEmail = '';
$userName = '';
$oauthId = '';
$avatarUrl = '';

// Check if this is a direct POST from Fast Connect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $userEmail = strtolower(trim($_POST['email']));
    $userName = trim($_POST['full_name'] ?? '');
    $oauthId = trim($_POST['oauth_id'] ?? ('mock_' . $provider . '_' . time()));
} else {
    // Real OAuth Code Exchange
    $code = trim($_GET['code'] ?? '');
    $state = trim($_GET['state'] ?? '');

    if (empty($code)) {
        $_SESSION['login_error'] = "Authentication cancelled or failed. Please try again.";
        header("Location: login.php");
        exit;
    }

    // Determine current redirect URI
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $redirectUri = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '') . '/oauth_callback.php?provider=' . $provider;

    if ($provider === 'google') {
        $googleClientId = trim(getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''));
        $googleClientSecret = trim(getenv('GOOGLE_CLIENT_SECRET') ?: (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : ''));

        // Exchange code for token
        $tokenUrl = "https://oauth2.googleapis.com/token";
        $postData = [
            'code' => $code,
            'client_id' => $googleClientId,
            'client_secret' => $googleClientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $tokenRes = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $tokenData = json_decode($tokenRes, true);
        $accessToken = $tokenData['access_token'] ?? '';

        if (empty($accessToken)) {
            $_SESSION['login_error'] = "Failed to obtain access token from Google: " . ($tokenData['error_description'] ?? $curlErr ?: 'Invalid response');
            header("Location: login.php");
            exit;
        }

        // Fetch user profile
        $userinfoUrl = "https://www.googleapis.com/oauth2/v3/userinfo";
        $ch2 = curl_init($userinfoUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        $profileRes = curl_exec($ch2);
        curl_close($ch2);

        $profile = json_decode($profileRes, true);
        $userEmail = strtolower(trim($profile['email'] ?? ''));
        $userName = trim($profile['name'] ?? '');
        $oauthId = trim($profile['sub'] ?? '');
        $avatarUrl = trim($profile['picture'] ?? '');

    } elseif ($provider === 'facebook') {
        $facebookAppId = trim(getenv('FACEBOOK_APP_ID') ?: (defined('FACEBOOK_APP_ID') ? FACEBOOK_APP_ID : ''));
        $facebookAppSecret = trim(getenv('FACEBOOK_APP_SECRET') ?: (defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : ''));

        // Exchange code for token
        $tokenUrl = "https://graph.facebook.com/v19.0/oauth/access_token?" . http_build_query([
            'client_id' => $facebookAppId,
            'client_secret' => $facebookAppSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $tokenRes = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($tokenRes, true);
        $accessToken = $tokenData['access_token'] ?? '';

        if (empty($accessToken)) {
            $_SESSION['login_error'] = "Failed to obtain access token from Facebook.";
            header("Location: login.php");
            exit;
        }

        // Fetch user profile
        $userinfoUrl = "https://graph.facebook.com/v19.0/me?fields=id,name,email,picture.type(large)&access_token=" . urlencode($accessToken);
        $ch2 = curl_init($userinfoUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        $profileRes = curl_exec($ch2);
        curl_close($ch2);

        $profile = json_decode($profileRes, true);
        $userEmail = strtolower(trim($profile['email'] ?? ''));
        $userName = trim($profile['name'] ?? '');
        $oauthId = trim($profile['id'] ?? '');
        $avatarUrl = trim($profile['picture']['data']['url'] ?? '');

        // If Facebook user didn't share email, create pseudo verified email
        if (empty($userEmail) && !empty($oauthId)) {
            $userEmail = 'fb_' . $oauthId . '@facebook.agrimart.ph';
        }
    }
}

// Validation
if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['login_error'] = "Unable to retrieve a valid email address from your social account.";
    header("Location: login.php");
    exit;
}

if (empty($userName)) {
    $parts = explode('@', $userEmail);
    $userName = ucfirst($parts[0]);
}

// Sync with Clever Cloud if applicable
syncUserVerificationWithCloud($userEmail, $conn);

// Check if user already exists
$stmt = $conn->prepare("SELECT user_id, full_name, email, role, status, email_verified FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    die("Database query error: " . $conn->error);
}
$stmt->bind_param('s', $userEmail);
$stmt->execute();
$existingUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$providerTitle = ($provider === 'google') ? 'Google' : 'Facebook';

if ($existingUser) {
    // Check status
    if ($existingUser['status'] === 'banned') {
        $_SESSION['login_error'] = "Your account has been suspended due to marketplace policy violations. Please contact support.";
        header("Location: login.php");
        exit;
    } elseif ($existingUser['status'] !== 'active') {
        $_SESSION['login_error'] = "Your account is currently inactive. Please contact support.";
        header("Location: login.php");
        exit;
    }

    // Link OAuth provider and mark email as verified (Google/Facebook emails are authenticated)
    $upStmt = $conn->prepare("UPDATE users SET email_verified = 1, oauth_provider = ?, oauth_id = ? WHERE user_id = ?");
    if ($upStmt) {
        $upStmt->bind_param('ssi', $provider, $oauthId, $existingUser['user_id']);
        $upStmt->execute();
        $upStmt->close();
    }

    // Log the user in
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$existingUser['user_id'];
    $_SESSION['full_name'] = $existingUser['full_name'];
    $_SESSION['email'] = $existingUser['email'];
    $_SESSION['role'] = $existingUser['role'];
    $_SESSION['login_success'] = "Welcome back, " . htmlspecialchars($existingUser['full_name']) . "! You have successfully signed in with " . $providerTitle . ".";

    if ($existingUser['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
} else {
    // Register brand new user via Social Join!
    $randomPassHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insStmt = $conn->prepare("
        INSERT INTO users (full_name, email, password, role, status, is_verified, face_verified, email_verified, oauth_provider, oauth_id, created_at)
        VALUES (?, ?, ?, 'user', 'active', 'pending', 'pending', 1, ?, ?, NOW())
    ");

    if (!$insStmt) {
        die("Registration error: " . $conn->error);
    }

    $insStmt->bind_param('sssss', $userName, $userEmail, $randomPassHash, $provider, $oauthId);
    $insStmt->execute();
    $newUserId = (int)$insStmt->insert_id;
    $insStmt->close();

    // Create default blank address row
    $addrStmt = $conn->prepare("INSERT INTO addresses (user_id, barangay, created_at) VALUES (?, 'Not specified', NOW())");
    if ($addrStmt) {
        $addrStmt->bind_param('i', $newUserId);
        $addrStmt->execute();
        $addrStmt->close();
    }

    // Log the new user in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['full_name'] = $userName;
    $_SESSION['email'] = $userEmail;
    $_SESSION['role'] = 'user';
    $_SESSION['login_success'] = "Welcome to AgriMart, " . htmlspecialchars($userName) . "! Your account has been created via " . $providerTitle . ".";

    header("Location: index.php");
    exit;
}
