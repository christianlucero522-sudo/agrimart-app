<?php
// extend_booking.php - Process Rental Booking Extension
session_start();
require_once 'config.php';
require_once 'sms_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$extensionUnits = (int)($_POST['extension_units'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');
$returnUrl = trim($_POST['return_url'] ?? "booking_details.php?id=$bookingId");

$allowedMethods = ['cash', 'gcash', 'maya', 'bank_transfer'];
if (!in_array($paymentMethod, $allowedMethods)) {
    $paymentMethod = 'cash';
}

if ($bookingId <= 0 || $extensionUnits <= 0) {
    $_SESSION['booking_error'] = 'Invalid extension request: Duration must be at least 1 unit.';
    header("Location: $returnUrl");
    exit;
}

// 1. Fetch booking and equipment
$sql = "
    SELECT 
        b.booking_id,
        b.equipment_id,
        b.renter_id,
        b.owner_id,
        b.start_date,
        b.end_date,
        b.rental_hours,
        b.total_amount,
        b.security_deposit,
        b.status,
        b.created_at,
        b.pickup_location,
        b.dropoff_location,
        e.equipment_name,
        e.brand,
        e.model,
        e.rate_type,
        e.rate_price,
        renter.full_name AS renter_name,
        renter.phone AS renter_phone,
        owner.full_name AS owner_name,
        owner.phone AS owner_phone
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users renter ON b.renter_id = renter.user_id
    INNER JOIN users owner ON b.owner_id = owner.user_id
    WHERE b.booking_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$b) {
    $_SESSION['booking_error'] = 'Rental booking not found.';
    header('Location: bookings.php');
    exit;
}

// Security: User must be the renter
if ((int)$b['renter_id'] !== $userId) {
    $_SESSION['booking_error'] = 'You are not authorized to extend this rental booking.';
    header('Location: bookings.php');
    exit;
}

// Status check: must be confirmed or ongoing
if (!in_array($b['status'], ['confirmed', 'ongoing'])) {
    $_SESSION['booking_error'] = 'Only confirmed or ongoing rentals can be extended.';
    header("Location: $returnUrl");
    exit;
}

$rateType = strtolower($b['rate_type'] ?? 'daily');
$isHourly = ($rateType === 'hourly' || (!empty($b['rental_hours']) && (int)$b['rental_hours'] > 0));
$ratePrice = (float)$b['rate_price'];

$newRentalHours = $b['rental_hours'];
$currentEndDate = $b['end_date'];
$newEndDate = $currentEndDate;
$addedAmount = 0.00;
$durationLabel = '';

if ($isHourly) {
    $extraHours = max(1, $extensionUnits);
    $addedAmount = round($extraHours * $ratePrice, 2);
    $currentHours = (int)($b['rental_hours'] ?: 1);
    $newRentalHours = $currentHours + $extraHours;
    
    // If overdue, base start from today
    $baseStart = (strtotime($b['end_date']) < strtotime(date('Y-m-d'))) ? date('Y-m-d') : $b['start_date'];
    $extraDays = (int)floor($newRentalHours / 24);
    $newEndDate = date('Y-m-d', strtotime("$baseStart +$extraDays days"));
    $durationLabel = "$extraHours " . ($extraHours === 1 ? 'hour' : 'hours');
} else {
    $extraDays = max(1, $extensionUnits);
    $addedAmount = round($extraDays * $ratePrice, 2);
    $newRentalHours = null;

    // If overdue (end_date is in past), extend from today forward so new return date is valid
    $baseDate = (strtotime($currentEndDate) < strtotime(date('Y-m-d'))) ? date('Y-m-d') : $currentEndDate;
    $newEndDate = date('Y-m-d', strtotime("$baseDate +$extraDays days"));
    $durationLabel = "$extraDays " . ($extraDays === 1 ? 'day' : 'days');
}

$newTotalAmount = (float)$b['total_amount'] + $addedAmount;

// Begin database transaction
$conn->begin_transaction();

try {
    // 1. Update Booking record
    $upSql = "
        UPDATE bookings 
        SET end_date = ?, 
            rental_hours = ?, 
            total_amount = ?, 
            late_days = 0, 
            late_penalty = 0.00,
            updated_at = NOW() 
        WHERE booking_id = ?
    ";
    $upStmt = $conn->prepare($upSql);
    $upStmt->bind_param('sidi', $newEndDate, $newRentalHours, $newTotalAmount, $bookingId);
    $upStmt->execute();
    $upStmt->close();

    // 2. Insert Payment record for Extension
    $payStatus = ($paymentMethod === 'cash') ? 'pending' : 'paid';
    $paidAt = ($paymentMethod === 'cash') ? null : date('Y-m-d H:i:s');
    $txRef = 'EXT-' . strtoupper(substr(uniqid(), -6));
    
    $pSql = "
        INSERT INTO payments (
            user_id, 
            booking_id, 
            payment_method, 
            amount, 
            payment_status, 
            transaction_ref, 
            paid_at, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    $pStmt = $conn->prepare($pSql);
    $pStmt->bind_param('iisdsss', $userId, $bookingId, $paymentMethod, $addedAmount, $payStatus, $txRef, $paidAt);
    $pStmt->execute();
    $pStmt->close();

    // 3. Insert In-App Notification for Owner
    $formattedNewEnd = date('F d, Y', strtotime($newEndDate));
    $ownerTitle = "⏰ Rental Extended: Booking #$bookingId";
    $ownerMsg = "Renter " . $b['renter_name'] . " has extended the rental for " . $b['equipment_name'] . " by $durationLabel. New scheduled return date: $formattedNewEnd. Added fee: ₱" . number_format($addedAmount, 2) . " (Payment: " . strtoupper($paymentMethod) . ").";
    $n1 = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id, created_at) VALUES (?, ?, ?, 'booking', ?, NOW())");
    $n1->bind_param('issi', $b['owner_id'], $ownerTitle, $ownerMsg, $bookingId);
    $n1->execute();
    $n1->close();

    // 4. Insert In-App Notification for Renter
    $renterTitle = "✓ Rental Extension Confirmed (Booking #$bookingId)";
    $renterMsg = "Your rental for " . $b['equipment_name'] . " has been successfully extended by $durationLabel! New scheduled return date: $formattedNewEnd. Additional cost: ₱" . number_format($addedAmount, 2) . ".";
    $n2 = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id, created_at) VALUES (?, ?, ?, 'booking', ?, NOW())");
    $n2->bind_param('issi', $userId, $renterTitle, $renterMsg, $bookingId);
    $n2->execute();
    $n2->close();

    // 5. Send Mobile SMS Notifications
    if (!empty($b['renter_phone'])) {
        $renterSms = "AgriMart: Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) has been extended by $durationLabel. New return: $formattedNewEnd. Added amount: ₱" . number_format($addedAmount, 2) . ". Thank you!";
        @sendSmsNotification($conn, $userId, $b['renter_phone'], $renterSms);
    }
    if (!empty($b['owner_phone'])) {
        $ownerSms = "AgriMart Notice: Renter " . $b['renter_name'] . " extended " . $b['equipment_name'] . " (Booking #$bookingId) by $durationLabel. New return: $formattedNewEnd. Added fee: ₱" . number_format($addedAmount, 2) . ".";
        @sendSmsNotification($conn, $b['owner_id'], $b['owner_phone'], $ownerSms);
    }

    $conn->commit();

    // Set flash session
    $_SESSION['booking_success'] = "Rental extended successfully by $durationLabel! New return date: $formattedNewEnd (₱" . number_format($addedAmount, 2) . " added).";
    
    // Redirect cleanly
    $delimiter = (strpos($returnUrl, '?') !== false) ? '&' : '?';
    header("Location: " . $returnUrl . $delimiter . "extended=1&added=" . urlencode(number_format($addedAmount, 2)));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['booking_error'] = 'Failed to extend rental: ' . $e->getMessage();
    header("Location: $returnUrl");
    exit;
}
