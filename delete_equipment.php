<?php
session_start();
require_once 'config.php';

/* =========================================================
   REQUIRE LOGIN
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================================================
   BLOCK ADMIN USER FROM SELLER ENDPOINT
========================================================= */
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* =========================================================
   REQUIRE POST METHOD
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_equipment.php');
    exit;
}

/* =========================================================
   GET EQUIPMENT ID
========================================================= */
$equipmentId = isset($_POST['equipment_id']) ? (int) $_POST['equipment_id'] : 0;

if ($equipmentId <= 0) {
    header('Location: my_equipment.php?error=invalid_equipment');
    exit;
}

/* =========================================================
   VERIFY EQUIPMENT OWNERSHIP (SELLER ONLY)
========================================================= */
$stmt = $conn->prepare("SELECT equipment_id, equipment_name FROM equipment WHERE equipment_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $equipmentId, $userId);
$stmt->execute();
$equip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$equip) {
    header('Location: my_equipment.php?error=not_found');
    exit;
}

/* =========================================================
   REMOVE EQUIPMENT
========================================================= */
// Check if equipment has rental bookings in history
$bCheck = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE equipment_id = ?");
$bCheck->bind_param('i', $equipmentId);
$bCheck->execute();
$bookingCount = (int)($bCheck->get_result()->fetch_assoc()['c'] ?? 0);
$bCheck->close();

if ($bookingCount > 0) {
    // Soft-delete to preserve rental booking history & receipts
    $uStmt = $conn->prepare("UPDATE equipment SET status = 'deleted', availability = 'maintenance' WHERE equipment_id = ? AND user_id = ?");
    $uStmt->bind_param('ii', $equipmentId, $userId);
    $uStmt->execute();
    $uStmt->close();
} else {
    // Hard-delete if no booking history exists
    $dStmt = $conn->prepare("DELETE FROM equipment WHERE equipment_id = ? AND user_id = ?");
    $dStmt->bind_param('ii', $equipmentId, $userId);
    $dStmt->execute();
    $dStmt->close();
}

$conn->close();

header('Location: my_equipment.php?deleted=1');
exit;
