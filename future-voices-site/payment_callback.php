<?php
require_once __DIR__ . '/includes/payment.php';

// Real providers sign their callbacks — verify that signature here before
// trusting the payload (MTN sends an X-Reference-Id header you look up
// against your own request; Airtel signs the body with your secret).
// Without that check, anyone could POST here and "confirm" a free booking.

$in = json_input();
$paymentId = (int) ($in['payment_id'] ?? 0);
$providerTransactionId = $in['transaction_id'] ?? null;

if (!$paymentId) {
    json_out(['error' => 'payment_id is required'], 400);
}

confirm_payment($paymentId, $providerTransactionId);
json_out(['ok' => true]);
