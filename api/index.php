<?php
// AgriMart Vercel Serverless Entrypoint & Gateway Router
chdir(__DIR__ . '/..');

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($rawUri, PHP_URL_PATH);
$uri = '/' . ltrim($uri, '/');

// Support requests prefixed with /api/ by stripping the prefix if the root file exists
if (strpos($uri, '/api/') === 0) {
    $sub = substr($uri, 4); // strip '/api'
    if (file_exists(__DIR__ . '/..' . $sub) || file_exists(__DIR__ . '/..' . $sub . '.php')) {
        $uri = $sub;
    }
}

// 1. Root and index path handling
if ($uri === '/' || $uri === '' || $uri === '/index' || $uri === '/index.php' || $uri === '/api' || $uri === '/api/') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/..' . $uri;

// 2. Direct static file handling (CSS, JS, SVG, Images, Fonts) with proper MIME headers
if (file_exists($target) && !is_dir($target)) {
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'webp'  => 'image/webp',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'json'  => 'application/json',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf'
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($target);
        exit;
    }
    if ($ext === 'php') {
        require $target;
        exit;
    }
    readfile($target);
    exit;
}

// 3. Clean Extensionless PHP URL (e.g. /products -> /products.php)
if (file_exists($target . '.php')) {
    require $target . '.php';
    exit;
}

// 4. Default fallback to index.php
require __DIR__ . '/../index.php';
