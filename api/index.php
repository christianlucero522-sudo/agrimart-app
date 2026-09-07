<?php
// AgriMart Vercel Serverless Entrypoint & Gateway Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize path
if ($uri === '/' || $uri === '' || $uri === '/index') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/..' . $uri;

// If file exists with .php extension
if (file_exists($target . '.php')) {
    require $target . '.php';
    exit;
}

// If direct file exists
if (file_exists($target) && !is_dir($target)) {
    require $target;
    exit;
}

// Default fallback
require __DIR__ . '/../index.php';
