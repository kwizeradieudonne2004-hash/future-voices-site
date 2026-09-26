<?php
require_once __DIR__ . '/../config.php';

/**
 * Records a payment attempt and asks the provider to charge the phone.
 * Returns the payments.id so the caller (and the eventual webhook) can
 * track it. In this mock, "asking the provider" is skipped — real code
 * goes where MOCK_MODE is checked below.
 */
function request_payment(string $purpose, int $referenceId, string $phone, int $amountRwf, string $provider = 'mtn_momo'): int {
    $stmt = db()->prepare(
        "INSERT INTO payments (purpose, reference_id, phone, amount_rwf, provider, status)
         VALUES (?, ?, ?, ?, ?, 'initiated')"
    );
    $stmt->execute([$purpose, $referenceId, $phone, $amountRwf, $provider]);
    $paymentId = (int) db()->lastInsertId();

    $mockMode = getenv('FVSS_PAYMENTS_MODE') !== 'live';
    if ($mockMode) {
        return $paymentId; // front end simulates the wait, then calls confirm_payment() directly for demos
    }

    // --- Real integration goes here -------------------------------------
    // MTN MoMo Collections API: POST /collection/v1_0/requesttopay
    //   Needs FVSS_MOMO_API_KEY, FVSS_MOMO_SUBSCRIPTION_KEY env vars,
    //   and a callback URL pointing at payment_callback.php.
    // Airtel Money: POST /merchant/v1/payments/
    //   Needs FVSS_AIRTEL_CLIENT_ID / FVSS_AIRTEL_CLIENT_SECRET.
    // On success, store the provider's transaction id:
    //   UPDATE payments SET provider_transaction_id = ? WHERE id = ?
    // ----------------------------------------------------------------------

    return $paymentId;
}

/**
 * Called once the provider confirms money was received (their webhook
 * hitting payment_callback.php, or the mock flow in a demo). Marks the
 * payment and the underlying booking/enrollment as confirmed.
 */
function confirm_payment(int $paymentId, ?string $providerTransactionId = null): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment || $payment['status'] !== 'initiated') {
            $pdo->rollBack();
            return;
        }

        $pdo->prepare("UPDATE payments SET status = 'confirmed', confirmed_at = NOW(), provider_transaction_id = ? WHERE id = ?")
            ->execute([$providerTransactionId, $paymentId]);

        $table = $payment['purpose'] === 'booking' ? 'bookings' : 'enrollments';
        $pdo->prepare("UPDATE $table SET status = 'confirmed' WHERE id = ?")
            ->execute([$payment['reference_id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
