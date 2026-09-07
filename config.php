<?php
// Database Configuration (Supports Localhost XAMPP & Cloud/Vercel/InfinityFree Environment Variables)
$host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'localhost');
$user = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root');
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : '');
$database = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'agrimart');
$port = (int)(getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306));

$conn = @new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Auto-migration safeguard: ensure all required columns exist
static $schemaChecked = false;
if (!$schemaChecked) {
    $schemaChecked = true;
    // Check users columns
    $uCols = [];
    $uRes = @$conn->query("SHOW COLUMNS FROM users");
    if ($uRes) {
        while ($r = $uRes->fetch_assoc()) { $uCols[] = $r['Field']; }
        if (!in_array('is_verified', $uCols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN id_type VARCHAR(100) NULL, ADD COLUMN id_number VARCHAR(100) NULL, ADD COLUMN id_card_image VARCHAR(255) NULL, ADD COLUMN is_verified ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
        }
    }
    // Check payments columns
    $pCols = [];
    $pRes = @$conn->query("SHOW COLUMNS FROM payments");
    if ($pRes) {
        while ($r = $pRes->fetch_assoc()) { $pCols[] = $r['Field']; }
        if (!in_array('payment_proof', $pCols)) {
            @$conn->query("ALTER TABLE payments ADD COLUMN payment_proof VARCHAR(255) NULL");
        }
        if (!in_array('buyer_bank_name', $pCols)) {
            @$conn->query("ALTER TABLE payments ADD COLUMN buyer_bank_name VARCHAR(100) NULL, ADD COLUMN buyer_account_name VARCHAR(150) NULL, ADD COLUMN buyer_account_number VARCHAR(100) NULL");
        }
    }
}
