<?php
// sms_helper.php - AgriMart SMS Notification & Expiry / Overdue Alert Engine

if (!function_exists('sendSmsNotification')) {
    /**
     * Send an SMS notification to a user's mobile number and log it to database and physical log
     */
    function sendSmsNotification($conn, $userId, $phoneNumber, $message) {
        $cleanPhone = preg_replace('/[^0-9+]/', '', trim((string)$phoneNumber));
        if (empty($cleanPhone)) {
            return false;
        }

        // 1. Insert into database sms_logs table
        $sql = "INSERT INTO sms_logs (user_id, phone_number, message, status, sent_at) VALUES (?, ?, ?, 'delivered', NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iss', $userId, $cleanPhone, $message);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Write to physical SMS logs folder
        $logDir = __DIR__ . '/uploads/sms_logs/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . 'sms_' . date('Y-m-d') . '.log';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: " . $cleanPhone . " (User #" . (int)$userId . ")\n"
                  . "MESSAGE: " . $message . "\n"
                  . "STATUS: DELIVERED (Simulated Carrier Network Gate)\n"
                  . str_repeat('-', 60) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        return true;
    }
}

if (!function_exists('calculateRentalLatePenalty')) {
    /**
     * Calculate 5% daily late penalty based on rental amount and overdue duration
     */
    function calculateRentalLatePenalty($totalAmount, $securityDeposit, $startDate, $endDate, $actualReturnDate = null) {
        $totalAmount = (float)$totalAmount;
        $securityDeposit = (float)$securityDeposit;
        
        if ($securityDeposit <= 0) {
            $securityDeposit = round(($totalAmount / 1.20) * 0.20, 2);
        }
        $baseRental = max(0, $totalAmount - $securityDeposit);
        
        // 5% daily penalty of the base rental
        $dailyPenaltyAmount = round($baseRental * 0.05, 2);
        
        $returnDateStr = $actualReturnDate ? $actualReturnDate : date('Y-m-d');
        $returnTimestamp = strtotime($returnDateStr);
        $endTimestamp = strtotime($endDate);
        
        $daysLate = 0;
        if ($returnTimestamp > $endTimestamp) {
            $daysLate = (int)floor(($returnTimestamp - $endTimestamp) / 86400);
        }
        
        $totalPenalty = round($daysLate * $dailyPenaltyAmount, 2);
        $netDepositRefund = max(0, $securityDeposit - $totalPenalty);
        $excessPenaltyDue = max(0, $totalPenalty - $securityDeposit);
        
        return [
            'base_rental' => $baseRental,
            'security_deposit' => $securityDeposit,
            'is_late' => ($daysLate > 0),
            'days_late' => $daysLate,
            'daily_penalty_rate_percent' => 5,
            'daily_penalty_amount' => $dailyPenaltyAmount,
            'total_penalty' => $totalPenalty,
            'net_deposit_refund' => $netDepositRefund,
            'excess_penalty_due' => $excessPenaltyDue
        ];
    }
}

if (!function_exists('notifyRentalCompleted')) {
    /**
     * Automatically handles rental completion, updates penalties, and dispatches SMS & In-app notifications
     */
    function notifyRentalCompleted($conn, $bookingId, $actualReturnDate = null) {
        $bookingId = (int)$bookingId;
        if ($bookingId <= 0) return false;

        $returnDate = $actualReturnDate ?: date('Y-m-d');

        $sql = "
            SELECT 
                b.booking_id,
                b.renter_id,
                b.owner_id,
                b.equipment_id,
                b.start_date,
                b.end_date,
                b.total_amount,
                b.security_deposit,
                b.status,
                e.equipment_name,
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

        if (!$b) return false;

        $renterId = (int)$b['renter_id'];
        $ownerId = (int)$b['owner_id'];
        $equipId = (int)$b['equipment_id'];
        $renterPhone = trim($b['renter_phone'] ?? '');

        // Calculate penalty if late
        $penaltyInfo = calculateRentalLatePenalty($b['total_amount'], $b['security_deposit'], $b['start_date'], $b['end_date'], $returnDate);
        $daysLate = $penaltyInfo['days_late'];
        $totalPenalty = $penaltyInfo['total_penalty'];

        // Update booking to completed
        $upSql = "UPDATE bookings SET status = 'completed', actual_return_date = ?, late_days = ?, late_penalty = ? WHERE booking_id = ?";
        $uStmt = $conn->prepare($upSql);
        if ($uStmt) {
            $uStmt->bind_param('sidi', $returnDate, $daysLate, $totalPenalty, $bookingId);
            $uStmt->execute();
            $uStmt->close();
        }

        // Make equipment available again
        $conn->query("UPDATE equipment SET availability = 'available' WHERE equipment_id = $equipId");

        // Mark payment as paid/completed
        $conn->query("UPDATE payments SET payment_status = 'paid', paid_at = NOW() WHERE booking_id = $bookingId AND payment_status = 'pending'");

        // 1. Dispatch SMS Notification to Renter
        if (!empty($renterPhone)) {
            if ($daysLate > 0) {
                $smsText = "AgriMart Notice: Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) is completed and marked RETURNED. Late return penalty: ₱" . number_format($totalPenalty, 2) . " ($daysLate day(s) @ 5%/day). Net security deposit refund: ₱" . number_format($penaltyInfo['net_deposit_refund'], 2) . ". Thank you for renting with AgriMart! Please visit your dashboard to rate and review.";
            } else {
                $smsText = "AgriMart Notice: Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) is completed and marked RETURNED on time. Full 20% security deposit (₱" . number_format($penaltyInfo['security_deposit'], 2) . ") has been released. Thank you! Please visit your dashboard to rate and review.";
            }
            sendSmsNotification($conn, $renterId, $renterPhone, $smsText);
        }

        // 2. Dispatch In-App Notification to Renter
        $notifTitle = "✅ Rental Completed: " . $b['equipment_name'];
        if ($daysLate > 0) {
            $notifMsg = "Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) has been marked as returned and completed. A 5% daily late penalty of ₱" . number_format($totalPenalty, 2) . " ($daysLate day(s) overdue) was deducted from your security deposit. Net deposit refund: ₱" . number_format($penaltyInfo['net_deposit_refund'], 2) . ". You can now rate and review this machinery.";
        } else {
            $notifMsg = "Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) has been marked as returned and completed in good standing. Your ₱" . number_format($penaltyInfo['security_deposit'], 2) . " (20%) security deposit has been released. You can now rate and review this machinery.";
        }
        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
        if ($nStmt) {
            $nStmt->bind_param('issi', $renterId, $notifTitle, $notifMsg, $bookingId);
            $nStmt->execute();
            $nStmt->close();
        }

        // 3. Dispatch In-App Notification to Owner
        $ownerTitle = "✅ Machine Returned & Completed: " . $b['equipment_name'];
        $ownerMsg = "Booking #$bookingId for " . $b['equipment_name'] . " has been finalized and marked returned by " . $b['renter_name'] . ". " . ($daysLate > 0 ? "Late penalty applied: ₱" . number_format($totalPenalty, 2) . " ($daysLate day(s) @ 5%/day). " : "") . "The machine is now marked available for new bookings.";
        $oStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
        if ($oStmt) {
            $oStmt->bind_param('issi', $ownerId, $ownerTitle, $ownerMsg, $bookingId);
            $oStmt->execute();
            $oStmt->close();
        }

        return true;
    }
}

if (!function_exists('checkAndSendRentalExpiryAlerts')) {
    /**
     * Scans rentals to automatically send:
     * 1. Expiry reminders (ending today/tomorrow)
     * 2. Overdue alerts with 5% daily late penalty calculation
     */
    function checkAndSendRentalExpiryAlerts($conn) {
        $today = date('Y-m-d');

        // 1. SCAN EXPIRING SOON RENTALS
        $expiringSql = "
            SELECT 
                b.booking_id,
                b.renter_id,
                b.owner_id,
                b.start_date,
                b.end_date,
                b.dropoff_location,
                b.total_amount,
                b.security_deposit,
                b.status,
                e.equipment_name,
                renter.full_name AS renter_name,
                renter.phone AS renter_phone,
                owner.full_name AS owner_name,
                owner.phone AS owner_phone
            FROM bookings b
            INNER JOIN equipment e ON b.equipment_id = e.equipment_id
            INNER JOIN users renter ON b.renter_id = renter.user_id
            INNER JOIN users owner ON b.owner_id = owner.user_id
            WHERE b.status IN ('confirmed', 'ongoing')
              AND b.end_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
              AND b.end_date >= CURDATE()
        ";

        $res = $conn->query($expiringSql);
        if ($res) {
            while ($b = $res->fetch_assoc()) {
                $bookingId = (int)$b['booking_id'];
                $renterId = (int)$b['renter_id'];
                $renterPhone = trim($b['renter_phone'] ?? '');

                if (empty($renterPhone)) continue;

                // Check if expiry reminder sent in the last 20 hours
                $checkSql = "
                    SELECT sms_id 
                    FROM sms_logs 
                    WHERE user_id = ? 
                      AND message LIKE ? 
                      AND sent_at >= DATE_SUB(NOW(), INTERVAL 20 HOUR)
                    LIMIT 1
                ";
                $cStmt = $conn->prepare($checkSql);
                $likeMsg = "%(Booking #$bookingId)%";
                $cStmt->bind_param('is', $renterId, $likeMsg);
                $cStmt->execute();
                $alreadySent = $cStmt->get_result()->num_rows > 0;
                $cStmt->close();

                if (!$alreadySent) {
                    $endDateFormatted = date('F d, Y', strtotime($b['end_date']));
                    $ownerPhone = !empty($b['owner_phone']) ? $b['owner_phone'] : 'No contact provided';
                    $dropoff = !empty($b['dropoff_location']) ? $b['dropoff_location'] : 'designated dropoff location';

                    $smsText = "AgriMart Alert: Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) is scheduled to end on " . $endDateFormatted . ". Please prepare the machine for return at " . $dropoff . " to avoid a 5% daily late penalty. Owner: " . $b['owner_name'] . " (" . $ownerPhone . ").";

                    sendSmsNotification($conn, $renterId, $renterPhone, $smsText);

                    $notifTitle = "⚠️ Rental Ending Soon: " . $b['equipment_name'];
                    $notifMsg = "Your rental period for " . $b['equipment_name'] . " (Booking #$bookingId) expires on " . $endDateFormatted . ". An SMS reminder has been dispatched to your mobile phone (" . $renterPhone . "). Please return the machinery to avoid late penalties (5%/day).";
                    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
                    if ($nStmt) {
                        $nStmt->bind_param('issi', $renterId, $notifTitle, $notifMsg, $bookingId);
                        $nStmt->execute();
                        $nStmt->close();
                    }
                }
            }
        }

        // 2. SCAN OVERDUE RENTALS & APPLY 5% DAILY PENALTY
        $overdueSql = "
            SELECT 
                b.booking_id,
                b.renter_id,
                b.owner_id,
                b.start_date,
                b.end_date,
                b.dropoff_location,
                b.total_amount,
                b.security_deposit,
                b.status,
                e.equipment_name,
                renter.full_name AS renter_name,
                renter.phone AS renter_phone,
                owner.full_name AS owner_name,
                owner.phone AS owner_phone
            FROM bookings b
            INNER JOIN equipment e ON b.equipment_id = e.equipment_id
            INNER JOIN users renter ON b.renter_id = renter.user_id
            INNER JOIN users owner ON b.owner_id = owner.user_id
            WHERE b.status IN ('confirmed', 'ongoing')
              AND b.end_date < CURDATE()
        ";

        $overdueRes = $conn->query($overdueSql);
        if ($overdueRes) {
            while ($b = $overdueRes->fetch_assoc()) {
                $bookingId = (int)$b['booking_id'];
                $renterId = (int)$b['renter_id'];
                $ownerId = (int)$b['owner_id'];
                $renterPhone = trim($b['renter_phone'] ?? '');

                // Calculate current overdue penalty
                $penaltyInfo = calculateRentalLatePenalty($b['total_amount'], $b['security_deposit'], $b['start_date'], $b['end_date']);
                $daysLate = $penaltyInfo['days_late'];
                $totalPenalty = $penaltyInfo['total_penalty'];
                $dailyFee = $penaltyInfo['daily_penalty_amount'];

                // Update booking late penalty in database
                $uStmt = $conn->prepare("UPDATE bookings SET late_days = ?, late_penalty = ? WHERE booking_id = ?");
                if ($uStmt) {
                    $uStmt->bind_param('idi', $daysLate, $totalPenalty, $bookingId);
                    $uStmt->execute();
                    $uStmt->close();
                }

                if (empty($renterPhone)) continue;

                // Check if overdue SMS sent in the last 20 hours
                $checkSql = "
                    SELECT sms_id 
                    FROM sms_logs 
                    WHERE user_id = ? 
                      AND message LIKE ? 
                      AND sent_at >= DATE_SUB(NOW(), INTERVAL 20 HOUR)
                    LIMIT 1
                ";
                $cStmt = $conn->prepare($checkSql);
                $likeMsg = "%(Booking #$bookingId)%";
                $cStmt->bind_param('is', $renterId, $likeMsg);
                $cStmt->execute();
                $alreadySent = $cStmt->get_result()->num_rows > 0;
                $cStmt->close();

                if (!$alreadySent) {
                    $endDateFormatted = date('F d, Y', strtotime($b['end_date']));
                    $ownerPhone = !empty($b['owner_phone']) ? $b['owner_phone'] : 'No contact provided';
                    $dropoff = !empty($b['dropoff_location']) ? $b['dropoff_location'] : 'designated dropoff location';

                    $smsText = "AgriMart OVERDUE ALERT: Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) was due on " . $endDateFormatted . ". You are currently $daysLate day(s) overdue. A 5% daily late penalty of ₱" . number_format($dailyFee, 2) . "/day (Total: ₱" . number_format($totalPenalty, 2) . ") is being deducted from your deposit. Return machine immediately to " . $dropoff . ". Owner: " . $b['owner_name'] . " (" . $ownerPhone . ").";

                    sendSmsNotification($conn, $renterId, $renterPhone, $smsText);

                    // Send in-app notification to Renter
                    $notifTitle = "🚨 OVERDUE: 5% Daily Penalty on " . $b['equipment_name'];
                    $notifMsg = "Your rental for " . $b['equipment_name'] . " (Booking #$bookingId) is $daysLate day(s) past the return date (" . $endDateFormatted . "). A 5% daily late penalty (₱" . number_format($dailyFee, 2) . "/day • Total Accumulated: ₱" . number_format($totalPenalty, 2) . ") is actively being deducted from your 20% security deposit. Please return the equipment to avoid further charges.";
                    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
                    if ($nStmt) {
                        $nStmt->bind_param('issi', $renterId, $notifTitle, $notifMsg, $bookingId);
                        $nStmt->execute();
                        $nStmt->close();
                    }

                    // Send in-app notification to Owner
                    $ownerTitle = "⚠️ Renter Overdue: " . $b['equipment_name'] . " (Booking #$bookingId)";
                    $ownerMsg = "Renter " . $b['renter_name'] . " has not returned " . $b['equipment_name'] . " (Booking #$bookingId), which was due on " . $endDateFormatted . " ($daysLate day(s) late). An accumulated 5% daily penalty of ₱" . number_format($totalPenalty, 2) . " has been recorded.";
                    $oStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_id) VALUES (?, ?, ?, 'booking', ?)");
                    if ($oStmt) {
                        $oStmt->bind_param('issi', $ownerId, $ownerTitle, $ownerMsg, $bookingId);
                        $oStmt->execute();
                        $oStmt->close();
                    }
                }
            }
        }
    }
}
