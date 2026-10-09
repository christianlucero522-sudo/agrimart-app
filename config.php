<?php
// Database Configuration (Supports Localhost XAMPP & Cloud/Vercel/InfinityFree Environment Variables)
$host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: '127.0.0.1');
$user = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root');
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : '');
$database = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'agrimart');
$port = (int)(getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306));

$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
if (!@$conn->real_connect($host, $user, $password, $database, $port)) {
    if (!@$conn->real_connect('localhost', $user, $password, $database, $port)) {
        die("Database connection failed: " . $conn->connect_error);
    }
}

$conn->set_charset("utf8mb4");

require_once __DIR__ . '/sms_helper.php';
