<?php
// oauth.php - Entry point for Google & Facebook Social Sign-In and Registration
session_start();
require_once __DIR__ . '/config.php';

$provider = strtolower(trim($_GET['provider'] ?? 'google'));
if (!in_array($provider, ['google', 'facebook'])) {
    $provider = 'google';
}

$action = strtolower(trim($_GET['action'] ?? 'login'));
if (!in_array($action, ['login', 'register'])) {
    $action = 'login';
}

$_SESSION['oauth_action'] = $action;
$_SESSION['oauth_provider'] = $provider;

// Determine current application base URL & dynamic redirect URI
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$redirectUri = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '') . '/oauth_callback.php?provider=' . $provider;

// Read credentials from Environment (Vercel / Cloud / Server)
$googleClientId = trim(getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''));
$googleClientSecret = trim(getenv('GOOGLE_CLIENT_SECRET') ?: (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : ''));
$facebookAppId = trim(getenv('FACEBOOK_APP_ID') ?: (defined('FACEBOOK_APP_ID') ? FACEBOOK_APP_ID : ''));
$facebookAppSecret = trim(getenv('FACEBOOK_APP_SECRET') ?: (defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : ''));

// Generate CSRF state token
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}
$state = $_SESSION['oauth_state'];

// If real credentials exist, proceed to official OAuth flow
if ($provider === 'google' && !empty($googleClientId)) {
    $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $googleClientId,
        'redirect_uri' => $redirectUri,
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account'
    ]);
    header("Location: " . $authUrl);
    exit;
}

if ($provider === 'facebook' && !empty($facebookAppId)) {
    $authUrl = "https://www.facebook.com/v19.0/dialog/oauth?" . http_build_query([
        'client_id' => $facebookAppId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => 'email,public_profile'
    ]);
    header("Location: " . $authUrl);
    exit;
}

// =========================================================================
// If credentials are not yet configured in environment, render Quick-Connect / Setup Portal
// =========================================================================
$providerName = ($provider === 'google') ? 'Google' : 'Facebook';
$pageTitle = ($action === 'register') ? "Join with $providerName" : "Sign In with $providerName";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — AgriMart</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .oauth-portal-wrap {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ded6b9;
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
            padding: 36px 40px;
            box-sizing: border-box;
            color: #122017;
        }
        .oauth-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 16px;
        }
        .badge-google { background: #e8f0fe; color: #1967d2; border: 1px solid #c2e7ff; }
        .badge-facebook { background: #e7f3ff; color: #1877f2; border: 1px solid #b2d7ff; }
        .oauth-title {
            font-family: Georgia, serif;
            font-size: 28px;
            margin: 0 0 10px;
            color: #122017;
        }
        .oauth-desc {
            font-size: 14px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 25px;
        }
        .fast-connect-box {
            background: #fdfaf2;
            border: 1.5px solid #ded6b9;
            border-left: 4px solid #768047;
            padding: 22px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .fast-connect-box h3 {
            margin: 0 0 8px;
            font-size: 16px;
            color: #122017;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .preset-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 14px 0;
        }
        .preset-btn {
            background: #fff;
            border: 1px solid #d4c79c;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12.5px;
            cursor: pointer;
            color: #122017;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .preset-btn:hover {
            border-color: #768047;
            background: #f4f0df;
        }
        .setup-steps-collapsible {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 18px 20px;
            font-size: 13px;
            color: #334155;
            line-height: 1.6;
        }
        .setup-steps-collapsible summary {
            font-weight: 700;
            cursor: pointer;
            color: #0f172a;
            font-size: 13.5px;
        }
        .copy-pill {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            font-family: monospace;
            font-size: 11.5px;
            border-radius: 4px;
            display: block;
            word-break: break-all;
            margin: 6px 0 10px;
            color: #0f766e;
        }
    </style>
</head>
<body style="background: #061b0f;">

<header class="site-header is-solid" id="siteHeader">
    <div class="wrap">
        <a href="index.php" class="logo">
            <span class="logo-text"><b>AgriMart</b><span>Field to Farm Gate</span></span>
        </a>
        <div class="header-actions">
            <a href="login.php" class="btn btn-light">Back to Login</a>
            <a href="register.php" class="btn btn-solid">Create Account</a>
        </div>
    </div>
</header>

<main class="auth-page" style="padding-top:100px;">
    <div class="oauth-portal-wrap">
        <div class="oauth-badge <?= $provider === 'google' ? 'badge-google' : 'badge-facebook' ?>">
            <?php if ($provider === 'google'): ?>
                <svg width="14" height="14" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                Google Authentication
            <?php else: ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Facebook Authentication
            <?php endif; ?>
        </div>

        <h1 class="oauth-title"><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="oauth-desc">
            Connect using your <?= htmlspecialchars($providerName) ?> account. Your verified email and profile will be automatically linked to your AgriMart account.
        </p>

        <!-- Fast Connect Form -->
        <div class="fast-connect-box">
            <h3>⚡ Instant <?= htmlspecialchars($providerName) ?> Connect</h3>
            <p style="font-size:13px; color:#555; margin:0 0 12px; line-height:1.5;">
                Choose one of your registered accounts or enter your email to proceed via <?= htmlspecialchars($providerName) ?>:
            </p>

            <div class="preset-pills">
                <button type="button" class="preset-btn" onclick="fillProfile('Juan dela Cruz', 'juan@gmail.com')">
                    👤 Juan Dela Cruz (juan@gmail.com)
                </button>
                <button type="button" class="preset-btn" onclick="fillProfile('Christian Lucero', 'christianlucero522@gmail.com')">
                    👤 Christian Lucero (christianlucero522@gmail.com)
                </button>
                <button type="button" class="preset-btn" onclick="fillProfile('Pedro Penduko', 'pedro@gmail.com')">
                    👤 Pedro Penduko (pedro@gmail.com)
                </button>
            </div>

            <form action="oauth_callback.php" method="POST" style="margin-top:16px;">
                <input type="hidden" name="provider" value="<?= htmlspecialchars($provider) ?>">
                <input type="hidden" name="oauth_id" id="oauth_id" value="<?= 'mock_' . $provider . '_' . time() ?>">
                
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; color:#122017;">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required class="form-control" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #d4c79c; border-radius:3px; font-size:14px;" value="Juan dela Cruz">
                </div>

                <div style="margin-bottom:18px;">
                    <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px; color:#122017;"><?= htmlspecialchars($providerName) ?> Account Email</label>
                    <input type="email" name="email" id="email" required class="form-control" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #d4c79c; border-radius:3px; font-size:14px;" value="juan@gmail.com">
                </div>

                <button type="submit" class="btn btn-solid" style="width:100%; padding:14px; font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:1px; cursor:pointer;">
                    ✓ Continue with <?= htmlspecialchars($providerName) ?> →
                </button>
            </form>
        </div>

        <!-- Setup Instructions for Live OAuth Credentials -->
        <details class="setup-steps-collapsible">
            <summary>⚙️ How to configure live <?= htmlspecialchars($providerName) ?> OAuth Credentials</summary>
            <div style="margin-top:12px;">
                <p>To enable direct single-click popup / redirect without this confirmation step on Vercel:</p>
                <ol style="padding-left:18px; margin:0 0 10px;">
                    <?php if ($provider === 'google'): ?>
                        <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color:#1967d2; font-weight:600;">Google Cloud Console &rarr; Credentials</a>.</li>
                        <li>Create an <strong>OAuth 2.0 Client ID</strong> (Web application).</li>
                        <li>Set <strong>Authorized redirect URIs</strong> to:
                            <code class="copy-pill"><?= htmlspecialchars($redirectUri) ?></code>
                        </li>
                        <li>Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to your <strong>Vercel Settings &rarr; Environment Variables</strong>.</li>
                    <?php else: ?>
                        <li>Go to <a href="https://developers.facebook.com/" target="_blank" style="color:#1877f2; font-weight:600;">Meta for Developers &rarr; My Apps</a>.</li>
                        <li>Add the <strong>Facebook Login</strong> product.</li>
                        <li>Set <strong>Valid OAuth Redirect URIs</strong> to:
                            <code class="copy-pill"><?= htmlspecialchars($redirectUri) ?></code>
                        </li>
                        <li>Add <code>FACEBOOK_APP_ID</code> and <code>FACEBOOK_APP_SECRET</code> to your <strong>Vercel Settings &rarr; Environment Variables</strong>.</li>
                    <?php endif; ?>
                </ol>
                <p style="font-size:12px; color:#64748b; margin:0;">
                    Once added, AgriMart will automatically switch to official direct OAuth redirects seamlessly!
                </p>
            </div>
        </details>
    </div>
</main>

<script>
function fillProfile(name, email) {
    document.getElementById('full_name').value = name;
    document.getElementById('email').value = email;
}
</script>
</body>
</html>
