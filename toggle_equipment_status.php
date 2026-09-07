<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_equipment.php');
    exit;
}

$equipmentId = (int)($_POST['equipment_id'] ?? 0);
if ($equipmentId <= 0) {
    header('Location: my_equipment.php');
    exit;
}

// Check ownership or admin
$sql = "SELECT equipment_id, status, availability FROM equipment WHERE equipment_id = ? " . ($userRole === 'admin' ? "" : "AND user_id = $userId") . " LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $equipmentId);
$stmt->execute();
$equip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$equip) {
    header('Location: my_equipment.php');
    exit;
}

$newStatus = ($equip['status'] === 'active') ? 'inactive' : 'active';
$up = $conn->prepare("UPDATE equipment SET status = ? WHERE equipment_id = ?");
$up->bind_param('si', $newStatus, $equipmentId);
$up->execute();
$up->close();

header('Location: my_equipment.php?toggled=1');
exit;
