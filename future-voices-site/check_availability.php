<?php
require_once __DIR__ . '/includes/availability.php';

$roomId = (int) ($_GET['room_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$roomId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_out(['error' => 'room_id and date (YYYY-MM-DD) are required'], 400);
}

json_out(['room_id' => $roomId, 'date' => $date, 'slots' => get_slot_status($roomId, $date)]);
