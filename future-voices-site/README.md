# FVSS booking backend

PHP/MySQL, same pattern as your Wellpoint HMS double-booking guard, applied
here to `bookings`.

## Setup
1. `mysql -u root -p < schema.sql`
2. Set env vars (or a `.env` loader of your choice): `FVSS_DB_HOST`,
   `FVSS_DB_NAME`, `FVSS_DB_USER`, `FVSS_DB_PASS`.
3. Point your web server at this folder; each `.php` file at the top level
   is one endpoint.

## How the pieces fit together

- **`schema.sql`** — `bookings` has a generated column `active_hold`
  (1 unless the row is cancelled, else NULL) with a UNIQUE key on
  `(room_id, booking_date, slot, active_hold)`. That single constraint
  *is* the double-booking guard: MySQL itself rejects a second
  pending/confirmed row for the same slot, so the guard can't be
  bypassed by a race condition the way an application-level check-then-
  insert could be.
- **`includes/availability.php`** — `get_slot_status()` powers
  `check_availability.php` (what the front end shows as free/taken).
  `try_hold_slot()` is what `book_room.php` calls to actually reserve a
  slot; it relies on the constraint above and returns `null` if someone
  beat you to it.
- **`includes/payment.php`** — `request_payment()` starts a mobile money
  charge (currently mocked — see below); `confirm_payment()` is what
  turns a `pending_payment` booking or enrollment into `confirmed`. It's
  called from `payment_callback.php` when the real provider's webhook
  fires.
- **`cancel_booking.php`** — enforces the "no later than 2 days before"
  rule server-side (never trust a client-side date check alone).
- **`enroll_course.php`** — same payment flow as bookings, for course
  enrollment fees.

## Going from mock to real mobile money

Right now `request_payment()` records the attempt and returns — no real
API call is made (`FVSS_PAYMENTS_MODE` defaults to mock). To go live:

1. Get merchant credentials from MTN MoMo (Collections API) and/or
   Airtel Money.
2. In `includes/payment.php`, where the comment block says
   `--- Real integration goes here ---`, add the actual HTTP call
   (`requesttopay` for MTN, `/merchant/v1/payments/` for Airtel), store
   `provider_transaction_id`.
3. Point the provider's callback/webhook URL at `payment_callback.php`,
   and add signature verification there before calling `confirm_payment()`
   — right now it trusts any POST, which is fine for local testing only.
4. Set `FVSS_PAYMENTS_MODE=live`.

## Not yet in scope
Auth/sessions, rate limiting, and the admin side (viewing/managing all
bookings) aren't built yet — say if you want those next.
