<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/payment.php';

$in = json_input();
$programId = (int) ($in['program_id'] ?? 0);
$name = trim($in['name'] ?? '');
$phone = trim($in['phone'] ?? '');

if (!$programId || !$name || !$phone) {
    json_out(['error' => 'program_id, name and phone are all required'], 400);
}

$stmt = db()->prepare("SELECT enrollment_fee_rwf FROM programs WHERE id = ?");
$stmt->execute([$programId]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$program) {
    json_out(['error' => 'No such program'], 404);
}

$stmt = db()->prepare(
    "INSERT INTO enrollments (program_id, customer_name, phone, status) VALUES (?, ?, ?, 'pending_payment')"
);
$stmt->execute([$programId, $name, $phone]);
$enrollmentId = (int) db()->lastInsertId();

$paymentId = request_payment('enrollment', $enrollmentId, $phone, (int) $program['enrollment_fee_rwf']);

json_out(['enrollment_id' => $enrollmentId, 'payment_id' => $paymentId, 'amount_rwf' => (int) $program['enrollment_fee_rwf']]);
