<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: equipment.php');
    exit;
}

$equipmentId = (int)($_POST['equipment_id'] ?? 0);
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$pickupLocation = trim($_POST['pickup_location'] ?? '');
$dropoffLocation = trim($_POST['dropoff_location'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');
$buyerBank = trim($_POST['buyer_bank_name'] ?? '');
$buyerAccountName = trim($_POST['buyer_account_name'] ?? '');
$buyerAccountNumber = trim($_POST['buyer_account_number'] ?? '');
$transactionRef = trim($_POST['transaction_ref'] ?? '');

$allowedMethods = ['cash', 'gcash', 'maya', 'bank_transfer'];
if (!in_array($paymentMethod, $allowedMethods)) {
    $paymentMethod = 'cash';
}

if ($equipmentId <= 0 || empty($startDate) || empty($endDate) || empty($pickupLocation) || empty($dropoffLocation)) {
    $_SESSION['booking_error'] = 'Please fill out all required rental fields.';
    header("Location: equipment_details.php?id=$equipmentId");
    exit;
}

// Handle Payment Proof Receipt Upload
$paymentProofPath = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['payment_proof']['tmp_name'];
    $origName = basename($_FILES['payment_proof']['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    if (in_array($ext, $allowed)) {
        $filename = 'pay_booking_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $destPath = __DIR__ . '/uploads/payments/' . $filename;
        if (move_uploaded_file($tmpName, $destPath)) {
            $paymentProofPath = 'uploads/payments/' . $filename;
        }
    }
}

// Fetch equipment and verify
$sql = "SELECT equipment_id, user_id AS owner_id, equipment_name, rate_type, rate_price, availability, status FROM equipment WHERE equipment_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $equipmentId);
$stmt->execute();
$equip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$equip || $equip['status'] !== 'active' || $equip['availability'] !== 'available') {
    $_SESSION['booking_error'] = 'This equipment is currently unavailable for rental.';
    header("Location: equipment_details.php?id=$equipmentId");
    exit;
}

$ownerId = (int)$equip['owner_id'];
if ($ownerId === $userId) {
    $_SESSION['booking_error'] = 'You cannot book your own equipment listing.';
    header("Location: equipment_details.php?id=$equipmentId");
    exit;
}

// Calculate days or hours and total (Return date must be valid and not before today)
$todayTimestamp = strtotime(date('Y-m-d'));
$t1 = strtotime($startDate);
$t2 = !empty($endDate) ? strtotime($endDate) : $t1;

$isHourly = in_array(strtolower($equip['rate_type'] ?? ''), ['hourly', 'hour']);
$rentalHours = null;

if ($isHourly) {
    $rentalHours = max(1, (int)($_POST['rental_hours'] ?? 1));
    if ($t1 < $todayTimestamp) {
        $_SESSION['booking_error'] = 'Invalid start date: Start date cannot be in the past.';
        header("Location: equipment_details.php?id=$equipmentId");
        exit;
    }
    // For hourly, set end_date corresponding to hours (if >= 24 hours spans days)
    $extraDays = (int)floor($rentalHours / 24);
    $endDate = date('Y-m-d', strtotime("$startDate +$extraDays days"));

    $rate = (float)$equip['rate_price'];
    $rentalSubtotal = $rentalHours * $rate;
    $securityDeposit = round($rentalSubtotal * 0.20, 2);
    $totalAmount = $rentalSubtotal + $securityDeposit;
    $scheduleText = "$startDate ($rentalHours " . ($rentalHours === 1 ? 'hour' : 'hours') . ")";
} else {
    if ($t1 < $todayTimestamp) {
        $_SESSION['booking_error'] = 'Invalid start date: Start date cannot be in the past.';
        header("Location: equipment_details.php?id=$equipmentId");
        exit;
    }

    if ($t2 <= $t1) {
        $_SESSION['booking_error'] = 'Invalid return date: The return date must be at least the next day following the start date.';
        header("Location: equipment_details.php?id=$equipmentId");
        exit;
    }

    $days = max(1, (int)round(($t2 - $t1) / 86400));
    $rate = (float)$equip['rate_price'];
    $rentalSubtotal = $days * $rate;
    $securityDeposit = round($rentalSubtotal * 0.20, 2);
    $totalAmount = $rentalSubtotal + $securityDeposit;
    $scheduleText = "from $startDate to $endDate ($days " . ($days === 1 ? 'day' : 'days') . ")";
}

$conn->begin_transaction();

try {
    // 1. Insert Booking (Includes rental_hours and 20% Refundable Security Deposit)
    $bSql = "INSERT INTO bookings (equipment_id, renter_id, owner_id, pickup_location, dropoff_location, booking_date, start_date, end_date, rental_hours, total_amount, security_deposit, status) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'pending')";
    $bStmt = $conn->prepare($bSql);
    $bStmt->bind_param('iiissssidd', $equipmentId, $userId, $ownerId, $pickupLocation, $dropoffLocation, $startDate, $endDate, $rentalHours, $totalAmount, $securityDeposit);
    $bStmt->execute();
    $bookingId = $conn->insert_id;
    $bStmt->close();

    // 2. Insert Payment
    $paymentStatus = ($paymentMethod === 'cash') ? 'pending' : 'paid';
    $paidAt = ($paymentMethod === 'cash') ? null : date('Y-m-d H:i:s');
    
    $pSql = "INSERT INTO payments (user_id, booking_id, payment_method, buyer_bank_name, buyer_account_name, buyer_account_number, amount, payment_status, transaction_ref, payment_proof, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $pStmt = $conn->prepare($pSql);
    $pStmt->bind_param('iissssdsdss', $userId, $bookingId, $paymentMethod, $buyerBank, $buyerAccountName, $buyerAccountNumber, $totalAmount, $paymentStatus, $transactionRef, $paymentProofPath, $paidAt);
    $pStmt->execute();
    $pStmt->close();

    // 3. Notify Owner
    $ownerTitle = "New Rental Booking #$bookingId";
    $ownerMsg = "A user has requested to rent your " . $equip['equipment_name'] . " $scheduleText. Total fee: ₱" . number_format($totalAmount, 2) . " (Includes ₱" . number_format($securityDeposit, 2) . " 20% security deposit for damage/loss). Please review the request.";
    $n1 = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
    $n1->bind_param('issi', $ownerId, $ownerTitle, $ownerMsg, $bookingId);
    $n1->execute();
    $n1->close();

    // 4. Notify Renter
    $renterTitle = "Rental Request #$bookingId Submitted";
    $renterMsg = "Your booking request for " . $equip['equipment_name'] . " ($scheduleText) has been submitted. Total amount: ₱" . number_format($totalAmount, 2) . " (Includes ₱" . number_format($securityDeposit, 2) . " 20% refundable damage deposit).";
    $n2 = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
    $n2->bind_param('issi', $userId, $renterTitle, $renterMsg, $bookingId);
    $n2->execute();
    $n2->close();

    $conn->commit();

    // 5. Send Gmail / Email Notifications
    require_once 'mailer.php';
    @sendBookingCreatedEmails($bookingId, $conn);

    header("Location: booking_details.php?id=$bookingId&booked=1");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['booking_error'] = 'Booking error: ' . $e->getMessage();
    header("Location: equipment_details.php?id=$equipmentId");
    exit;
}
