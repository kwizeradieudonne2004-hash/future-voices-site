<?php
require_once __DIR__ . '/config.php';

$in = json_input();
$bookingId = (int) ($in['booking_id'] ?? 0);

if (!$bookingId) {
    json_out(['error' => 'booking_id is required'], 400);
}

$stmt = db()->prepare("SELECT booking_date, status FROM bookings WHERE id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    json_out(['error' => 'No such booking'], 404);
}
if ($booking['status'] === 'cancelled') {
    json_out(['error' => 'Already cancelled'], 409);
}

$daysUntil = (strtotime($booking['booking_date']) - strtotime(date('Y-m-d'))) / 86400;
if ($daysUntil < 2) {
    json_out(['error' => "Can't cancel within 2 days of the booking date"], 403);
}

db()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
json_out(['ok' => true]);
