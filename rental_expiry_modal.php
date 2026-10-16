<?php
// rental_expiry_modal.php - Pop-up Modal for Rentals Nearing Expiry / One-Hour Reminder & Extension
if (!isset($_SESSION['user_id']) || !isset($conn)) {
    return;
}

$currentUserId = (int)$_SESSION['user_id'];

// Query all active rentals for this user
$modalBookingsSql = "
    SELECT 
        b.booking_id,
        b.equipment_id,
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
        e.image_url,
        e.rate_type,
        e.rate_price,
        u.full_name AS owner_name,
        u.phone AS owner_phone
    FROM bookings b
    INNER JOIN equipment e ON b.equipment_id = e.equipment_id
    INNER JOIN users u ON b.owner_id = u.user_id
    WHERE b.renter_id = ?
      AND b.status IN ('confirmed', 'ongoing')
    ORDER BY b.booking_id DESC
";

$mbStmt = $conn->prepare($modalBookingsSql);
if (!$mbStmt) {
    return;
}
$mbStmt->bind_param('i', $currentUserId);
$mbStmt->execute();
$mbResult = $mbStmt->get_result();

$activeRentalsList = [];
$autoPopupBooking = null;
$currentTime = time();
$forceTestPopup = isset($_GET['test_expire_popup']) || isset($_GET['test_expiry']) || isset($_GET['popup_test']);

while ($row = $mbResult->fetch_assoc()) {
    $rateType = strtolower($row['rate_type'] ?? 'daily');
    $isHourly = ($rateType === 'hourly' || (!empty($row['rental_hours']) && (int)$row['rental_hours'] > 0));
    $hours = !empty($row['rental_hours']) ? (int)$row['rental_hours'] : 0;
    
    // Calculate scheduled end timestamp
    if ($isHourly && $hours > 0) {
        $createdDate = date('Y-m-d', strtotime($row['created_at']));
        if ($createdDate === $row['start_date']) {
            $startTimestamp = strtotime($row['created_at']);
        } else {
            $startTimestamp = strtotime($row['start_date'] . ' 08:00:00');
        }
        $endTimestamp = $startTimestamp + ($hours * 3600);
    } else {
        // Daily rental ends on end_date at 18:00 (6:00 PM)
        $endTimestamp = strtotime($row['end_date'] . ' 18:00:00');
    }

    $diffSeconds = $endTimestamp - $currentTime;
    $diffMinutes = (int)ceil($diffSeconds / 60);

    // Is it about to end in an hour? (Between 0 and 3600 seconds, or within 60 mins)
    $isEndingSoon = ($diffSeconds > -1800 && $diffSeconds <= 3600);

    $bookingPayload = [
        'booking_id' => (int)$row['booking_id'],
        'equipment_id' => (int)$row['equipment_id'],
        'equipment_name' => $row['equipment_name'],
        'brand' => $row['brand'] ?: 'Standard',
        'model' => $row['model'] ?: 'Standard',
        'image_url' => $row['image_url'] ?: 'images/placeholder-equipment.svg',
        'rate_type' => $rateType,
        'rate_price' => (float)$row['rate_price'],
        'is_hourly' => $isHourly,
        'rental_hours' => $hours,
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'end_timestamp' => $endTimestamp,
        'scheduled_end_formatted' => date('M d, Y h:i A', $endTimestamp),
        'diff_seconds' => $diffSeconds,
        'diff_minutes' => $diffMinutes,
        'is_ending_soon' => $isEndingSoon,
        'total_amount' => (float)$row['total_amount'],
        'owner_name' => $row['owner_name'],
        'owner_phone' => $row['owner_phone'] ?: 'No phone provided',
        'dropoff_location' => $row['dropoff_location'] ?: 'Designated Farm Dropoff'
    ];

    $activeRentalsList[] = $bookingPayload;

    // Pick for auto popup if ending soon, or first booking if forced test
    if ($isEndingSoon && $autoPopupBooking === null) {
        $autoPopupBooking = $bookingPayload;
    }
}
$mbStmt->close();

if ($forceTestPopup && $autoPopupBooking === null && !empty($activeRentalsList)) {
    $autoPopupBooking = $activeRentalsList[0];
    $autoPopupBooking['is_ending_soon'] = true;
    $autoPopupBooking['diff_minutes'] = 52;
}

$currentRequestUri = $_SERVER['REQUEST_URI'] ?? 'bookings.php';
?>

<!-- ========================================================
     RENTAL EXPIRY & EXTENSION MODAL DIALOG
======================================================== -->
<style>
.rem-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(18, 32, 23, 0.75);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
    opacity: 0;
    transition: opacity 0.25s ease;
}
.rem-backdrop.show {
    display: flex;
    opacity: 1;
}
.rem-modal {
    background: #ffffff;
    width: 100%;
    max-width: 540px;
    border-radius: 12px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.35);
    overflow: hidden;
    position: relative;
    border: 1px solid #d8d0b7;
    transform: translateY(20px) scale(0.97);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #122017;
}
.rem-backdrop.show .rem-modal {
    transform: translateY(0) scale(1);
}
.rem-header {
    background: linear-gradient(135deg, #1b382b 0%, #122017 100%);
    color: #ffffff;
    padding: 20px 24px;
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
}
.rem-pulse-icon {
    width: 44px;
    height: 44px;
    background: #d97706;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7);
    animation: remPulse 2s infinite;
}
@keyframes remPulse {
    0% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7); }
    70% { box-shadow: 0 0 0 12px rgba(217, 119, 6, 0); }
    100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
}
.rem-header-titles {
    flex: 1;
}
.rem-header-badge {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 3px 8px;
    border-radius: 20px;
    margin-bottom: 4px;
}
.rem-header h3 {
    margin: 0;
    font-family: Georgia, serif;
    font-size: 21px;
    font-weight: 700;
    color: #fff;
    line-height: 1.25;
}
.rem-close-btn {
    position: absolute;
    top: 16px;
    right: 18px;
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    font-size: 22px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.rem-close-btn:hover {
    background: rgba(255,255,255,0.3);
}
.rem-body {
    padding: 24px;
    max-height: calc(85vh - 120px);
    overflow-y: auto;
}
.rem-alert-card {
    background: #fefce8;
    border: 1px solid #fef08a;
    border-left: 4px solid #eab308;
    border-radius: 6px;
    padding: 14px 16px;
    margin-bottom: 18px;
    font-size: 14px;
    color: #713f12;
    line-height: 1.45;
}
.rem-equip-card {
    display: flex;
    gap: 16px;
    background: #f4f0df;
    border: 1px solid #d8d0b7;
    border-radius: 8px;
    padding: 14px;
    align-items: center;
    margin-bottom: 20px;
}
.rem-equip-thumb {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 6px;
    background: #122017;
    flex-shrink: 0;
}
.rem-equip-name {
    font-family: Georgia, serif;
    font-size: 17px;
    font-weight: 700;
    color: #122017;
    margin: 0 0 3px;
}
.rem-equip-meta {
    font-size: 12.5px;
    color: #555;
    margin-bottom: 4px;
}
.rem-badge-timer {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}
.rem-form-group {
    margin-bottom: 16px;
}
.rem-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #122017;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.rem-select, .rem-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d8d0b7;
    border-radius: 6px;
    font-size: 15px;
    color: #122017;
    background: #fff;
    box-sizing: border-box;
}
.rem-pills {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-top: 6px;
}
.rem-pill-btn {
    background: #fff;
    border: 1px solid #d8d0b7;
    padding: 9px 4px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    color: #122017;
    transition: all 0.15s;
}
.rem-pill-btn:hover {
    background: #f4f0df;
    border-color: #b29438;
}
.rem-pill-btn.active {
    background: #1b382b;
    color: #ffffff;
    border-color: #1b382b;
}
.rem-summary-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    padding: 14px 16px;
    margin: 18px 0;
    font-size: 13.5px;
}
.rem-summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 7px;
    color: #166534;
}
.rem-summary-row:last-child {
    margin-bottom: 0;
    padding-top: 7px;
    border-top: 1px dashed #86efac;
    font-size: 15px;
    font-weight: 700;
}
.rem-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 6px;
}
.rem-method-card {
    border: 1px solid #d8d0b7;
    border-radius: 6px;
    padding: 10px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    background: #fff;
    transition: all 0.15s;
}
.rem-method-card input {
    margin: 0;
    cursor: pointer;
}
.rem-method-card:hover {
    border-color: #b29438;
    background: #faf8f2;
}
.rem-footer-actions {
    display: flex;
    gap: 12px;
    margin-top: 22px;
}
.rem-btn-submit {
    flex: 2;
    background: #1b382b;
    color: #ffffff;
    border: none;
    padding: 13px 20px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.2s, transform 0.1s;
}
.rem-btn-submit:hover {
    background: #142a20;
}
.rem-btn-submit:active {
    transform: scale(0.99);
}
.rem-btn-dismiss {
    flex: 1;
    background: #f4f0df;
    color: #555;
    border: 1px solid #d8d0b7;
    padding: 13px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    transition: background 0.2s;
}
.rem-btn-dismiss:hover {
    background: #e7dfc5;
    color: #122017;
}
</style>

<div id="rentalExpiryBackdrop" class="rem-backdrop" onclick="onRemBackdropClick(event)">
    <div class="rem-modal" onclick="event.stopPropagation()">
        <div class="rem-header">
            <div class="rem-pulse-icon">⏰</div>
            <div class="rem-header-titles">
                <span class="rem-header-badge">Action Required</span>
                <h3 id="remModalTitle">Rental Ending in 1 Hour!</h3>
            </div>
            <button type="button" class="rem-close-btn" onclick="closeRentalExpiryModal()" title="Close">&times;</button>
        </div>

        <div class="rem-body">
            <!-- Alert Message -->
            <div class="rem-alert-card">
                <strong>Your rental is about to end in an hour!</strong> Would you like to extend your booking to keep using the equipment without late return penalties?
            </div>

            <!-- Equipment Summary -->
            <div class="rem-equip-card">
                <img id="remEquipImg" src="images/placeholder-equipment.svg" alt="" class="rem-equip-thumb">
                <div style="flex:1;">
                    <div id="remEquipName" class="rem-equip-name">Machinery Name</div>
                    <div id="remEquipMeta" class="rem-equip-meta">Brand: Standard &bull; Booking #0</div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span id="remTimeBadge" class="rem-badge-timer">⏳ Approx. 60 mins remaining</span>
                        <span id="remRateBadge" style="font-size:12px; font-weight:700; color:#1b382b;">₱0.00 / hour</span>
                    </div>
                </div>
            </div>

            <!-- Extension Form -->
            <form id="remExtensionForm" action="extend_booking.php" method="POST">
                <input type="hidden" name="booking_id" id="remBookingId" value="0">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentRequestUri) ?>">
                <input type="hidden" name="extension_units" id="remExtensionUnitsInput" value="1">

                <div class="rem-form-group">
                    <label class="rem-label" id="remDurationLabel">Select Extension Duration:</label>
                    <div id="remPillsContainer" class="rem-pills">
                        <!-- Populated by JS based on hourly vs daily -->
                    </div>
                </div>

                <!-- Dynamic Calculation Summary -->
                <div class="rem-summary-box">
                    <div class="rem-summary-row">
                        <span>Current Scheduled Return:</span>
                        <strong id="remCurrentReturnText">--</strong>
                    </div>
                    <div class="rem-summary-row">
                        <span>New Extended Return:</span>
                        <strong id="remNewReturnText" style="color:#1b382b;">--</strong>
                    </div>
                    <div class="rem-summary-row">
                        <span>Added Rental Fee:</span>
                        <strong id="remAddedFeeText">+₱0.00</strong>
                    </div>
                    <div class="rem-summary-row">
                        <span>Updated Total Amount:</span>
                        <span id="remUpdatedTotalText" style="font-size:16px;">₱0.00</span>
                    </div>
                </div>

                <div class="rem-form-group">
                    <label class="rem-label">Payment Method for Extension:</label>
                    <div class="rem-methods">
                        <label class="rem-method-card">
                            <input type="radio" name="payment_method" value="cash" checked>
                            <span>💵 Cash on Return</span>
                        </label>
                        <label class="rem-method-card">
                            <input type="radio" name="payment_method" value="gcash">
                            <span>📱 GCash</span>
                        </label>
                        <label class="rem-method-card">
                            <input type="radio" name="payment_method" value="maya">
                            <span>💳 Maya</span>
                        </label>
                        <label class="rem-method-card">
                            <input type="radio" name="payment_method" value="bank_transfer">
                            <span>🏦 Bank Transfer</span>
                        </label>
                    </div>
                </div>

                <div class="rem-footer-actions">
                    <button type="submit" class="rem-btn-submit" id="remSubmitBtn">
                        ✓ Confirm & Extend Rental
                    </button>
                    <button type="button" class="rem-btn-dismiss" onclick="dismissRentalExpiryModal()">
                        Dismiss
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Registered active rentals data for this user
const ALL_ACTIVE_RENTALS = <?= json_encode($activeRentalsList) ?>;
const AUTO_POPUP_BOOKING = <?= json_encode($autoPopupBooking) ?>;
let CURRENT_MODAL_BOOKING = null;
let SELECTED_EXTENSION_UNITS = 1;

function openRentalExpiryModal(bookingData) {
    if (!bookingData) return;
    CURRENT_MODAL_BOOKING = bookingData;
    SELECTED_EXTENSION_UNITS = 1;

    document.getElementById('remBookingId').value = bookingData.booking_id;
    document.getElementById('remEquipImg').src = bookingData.image_url || 'images/placeholder-equipment.svg';
    document.getElementById('remEquipName').textContent = bookingData.equipment_name;
    document.getElementById('remEquipMeta').textContent = `Brand: ${bookingData.brand || 'Standard'} • Booking #${bookingData.booking_id}`;
    document.getElementById('remRateBadge').textContent = `₱${parseFloat(bookingData.rate_price).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})} / ${bookingData.is_hourly ? 'hour' : 'day'}`;
    
    // Remaining timer
    let remainingTxt = 'Ending in an hour';
    if (bookingData.diff_minutes > 0 && bookingData.diff_minutes <= 60) {
        remainingTxt = `⏳ Approx. ${bookingData.diff_minutes} min${bookingData.diff_minutes === 1 ? '' : 's'} remaining`;
    } else if (bookingData.diff_minutes <= 0) {
        remainingTxt = `⚠️ Scheduled return is due now!`;
    } else {
        remainingTxt = `⏳ Due on ${bookingData.scheduled_end_formatted}`;
    }
    document.getElementById('remTimeBadge').textContent = remainingTxt;
    document.getElementById('remCurrentReturnText').textContent = bookingData.scheduled_end_formatted;

    // Render Pills
    const pillsContainer = document.getElementById('remPillsContainer');
    pillsContainer.innerHTML = '';
    const unitOptions = bookingData.is_hourly ? [1, 2, 3, 4, 6, 8, 12, 24] : [1, 2, 3, 4, 5, 7, 10, 14];
    const unitLabel = bookingData.is_hourly ? 'Hour' : 'Day';

    unitOptions.forEach((u, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rem-pill-btn' + (idx === 0 ? ' active' : '');
        btn.textContent = `+${u} ${unitLabel}${u > 1 ? 's' : ''}`;
        btn.onclick = () => selectExtensionUnit(u, btn);
        pillsContainer.appendChild(btn);
    });

    document.getElementById('remDurationLabel').textContent = `Select Additional ${unitLabel}s to Extend:`;
    updateExtensionCalculations();

    const backdrop = document.getElementById('rentalExpiryBackdrop');
    backdrop.style.display = 'flex';
    setTimeout(() => backdrop.classList.add('show'), 10);
}

function selectExtensionUnit(units, btnElem) {
    SELECTED_EXTENSION_UNITS = units;
    document.getElementById('remExtensionUnitsInput').value = units;
    
    document.querySelectorAll('.rem-pill-btn').forEach(b => b.classList.remove('active'));
    if (btnElem) btnElem.classList.add('active');

    updateExtensionCalculations();
}

function updateExtensionCalculations() {
    if (!CURRENT_MODAL_BOOKING) return;

    const rate = parseFloat(CURRENT_MODAL_BOOKING.rate_price) || 0;
    const addedAmount = SELECTED_EXTENSION_UNITS * rate;
    const currentTotal = parseFloat(CURRENT_MODAL_BOOKING.total_amount) || 0;
    const newTotal = currentTotal + addedAmount;

    // Calculate new end date/time
    let newEndTimestamp = CURRENT_MODAL_BOOKING.end_timestamp;
    if (CURRENT_MODAL_BOOKING.is_hourly) {
        newEndTimestamp += (SELECTED_EXTENSION_UNITS * 3600);
    } else {
        newEndTimestamp += (SELECTED_EXTENSION_UNITS * 86400);
    }
    const newEndDateObj = new Date(newEndTimestamp * 1000);
    const options = { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true };
    const formattedNewEnd = newEndDateObj.toLocaleString('en-US', options);

    document.getElementById('remNewReturnText').textContent = formattedNewEnd;
    document.getElementById('remAddedFeeText').textContent = `+₱${addedAmount.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    document.getElementById('remUpdatedTotalText').textContent = `₱${newTotal.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    document.getElementById('remSubmitBtn').textContent = `✓ Confirm & Extend Rental (+₱${addedAmount.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})})`;
}

function closeRentalExpiryModal() {
    const backdrop = document.getElementById('rentalExpiryBackdrop');
    backdrop.classList.remove('show');
    setTimeout(() => { backdrop.style.display = 'none'; }, 250);
}

function dismissRentalExpiryModal() {
    if (CURRENT_MODAL_BOOKING) {
        sessionStorage.setItem('agrimart_rental_dismiss_' + CURRENT_MODAL_BOOKING.booking_id, '1');
    }
    closeRentalExpiryModal();
}

function onRemBackdropClick(e) {
    if (e.target.id === 'rentalExpiryBackdrop') {
        closeRentalExpiryModal();
    }
}

// Global helper for opening modal by booking ID anywhere on page
window.openBookingExtension = function(bookingId) {
    const found = ALL_ACTIVE_RENTALS.find(r => r.booking_id == bookingId);
    if (found) {
        openRentalExpiryModal(found);
    } else if (ALL_ACTIVE_RENTALS.length > 0) {
        openRentalExpiryModal(ALL_ACTIVE_RENTALS[0]);
    }
};

// Automatic detection on page load
document.addEventListener('DOMContentLoaded', () => {
    if (AUTO_POPUP_BOOKING) {
        const isDismissed = sessionStorage.getItem('agrimart_rental_dismiss_' + AUTO_POPUP_BOOKING.booking_id);
        const forceTest = <?= $forceTestPopup ? 'true' : 'false' ?>;
        if (!isDismissed || forceTest) {
            // Delay slightly for smooth page entrance
            setTimeout(() => {
                openRentalExpiryModal(AUTO_POPUP_BOOKING);
            }, 600);
        }
    }
});
</script>
<!-- ========================================================
     END RENTAL EXPIRY & EXTENSION MODAL DIALOG
======================================================== -->
