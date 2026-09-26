<?php
require_once __DIR__ . '/includes/availability.php';
require_once __DIR__ . '/includes/payment.php';

$in = json_input();
$roomId = (int) ($in['room_id'] ?? 0);
$date = $in['date'] ?? '';
$slot = $in['slot'] ?? '';
$name = trim($in['name'] ?? '');
$phone = trim($in['phone'] ?? '');

if (!$roomId || !$date || !$slot || !$name || !$phone) {
    json_out(['error' => 'room_id, date, slot, name and phone are all required'], 400);
}

$stmt = db()->prepare("SELECT price_rwf FROM rooms WHERE id = ?");
$stmt->execute([$roomId]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    json_out(['error' => 'No such room'], 404);
}

$bookingId = try_hold_slot($roomId, $date, $slot, $name, $phone);
if ($bookingId === null) {
    // Someone else has this slot — tell them what's actually free right now.
    json_out(['error' => 'That slot was just taken', 'slots' => get_slot_status($roomId, $date)], 409);
}

$paymentId = request_payment('booking', $bookingId, $phone, (int) $room['price_rwf']);

json_out(['booking_id' => $bookingId, 'payment_id' => $paymentId, 'amount_rwf' => (int) $room['price_rwf']]);
