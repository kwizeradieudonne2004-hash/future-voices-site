<?php
require_once __DIR__ . '/../config.php';

const SLOTS = ['09:00-12:00', '12:00-16:00', '16:00-20:00'];

/**
 * Returns each slot for a room+date marked free or taken.
 * "Taken" means a booking row exists that is either confirmed, or
 * still pending_payment and less than 15 minutes old (a payment in
 * progress holds the slot briefly so two people can't both pay for it).
 */
function get_slot_status(int $roomId, string $date): array {
    $stmt = db()->prepare(
        "SELECT slot FROM bookings
         WHERE room_id = ? AND booking_date = ?
           AND (status = 'confirmed'
                OR (status = 'pending_payment' AND created_at > NOW() - INTERVAL 15 MINUTE))"
    );
    $stmt->execute([$roomId, $date]);
    $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $result = [];
    foreach (SLOTS as $slot) {
        $result[] = ['slot' => $slot, 'free' => !in_array($slot, $taken, true)];
    }
    return $result;
}

/**
 * The double-booking guard. Attempts to place a pending_payment hold on a
 * slot. Returns the new booking id, or null if the slot was taken by
 * someone else in the moment between the availability check and this call
 * (a real gap the UI's slot list alone can't close — this is why the
 * insert itself has to be the source of truth).
 *
 * Relies on the UNIQUE KEY on (room_id, booking_date, slot, status) in
 * schema.sql: a second INSERT with status='pending_payment' for the same
 * slot raises a duplicate-key error, which we catch and treat as "taken".
 */
function try_hold_slot(int $roomId, string $date, string $slot, string $name, string $phone): ?int {
    if (!in_array($slot, SLOTS, true)) {
        throw new InvalidArgumentException('Not a real time slot.');
    }
    $stmt = db()->prepare(
        "INSERT INTO bookings (room_id, customer_name, phone, booking_date, slot, status)
         VALUES (?, ?, ?, ?, ?, 'pending_payment')"
    );
    try {
        $stmt->execute([$roomId, $name, $phone, $date, $slot]);
        return (int) db()->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // unique constraint violation
            return null; // someone else has this slot
        }
        throw $e;
    }
}
