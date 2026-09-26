# Deploying Future Voices School & Studio

Everything in this folder is one self-contained site: a static front end
(`index.html` + `assets/`) plus the PHP booking/enrollment/payment backend.
Any PHP 8 + MySQL host works — shared hosting, a VPS, or something like
DigitalOcean App Platform / Render with a PHP buildpack.

## 1. Database

```
mysql -u root -p < schema.sql
```

This creates the `fvss` database, its tables, and seeds the 4 rooms and 9
programs shown on the site (same order the front end expects, so don't
reorder the `INSERT` rows in `schema.sql` without also updating the
`PROGRAMS` array near the bottom of `index.html`).

## 2. Environment variables

Set these on your host (control panel, `.env` loader, or your process
manager — `config.php` reads them with `getenv()`):

| Variable          | Default     | Notes                              |
|-------------------|-------------|-------------------------------------|
| `FVSS_DB_HOST`    | `127.0.0.1` | MySQL host                          |
| `FVSS_DB_NAME`    | `fvss`      |                                      |
| `FVSS_DB_USER`    | `root`      | use a scoped DB user in production  |
| `FVSS_DB_PASS`    | *(empty)*   |                                      |
| `FVSS_PAYMENTS_MODE` | mock (anything other than `live`) | set to `live` once MTN/Airtel credentials are wired up in `includes/payment.php` |

## 3. Upload

Upload the whole folder to your web root (e.g. `public_html/`), keeping the
structure as-is:

```
/index.html
/assets/css/style.css
/assets/js/app.js
/config.php
/check_availability.php
/book_room.php
/cancel_booking.php
/enroll_course.php
/payment_callback.php
/includes/availability.php
/includes/payment.php
/.htaccess
/schema.sql            (keep this out of the public web root if possible)
```

Point your domain at this folder. No build step — `index.html` is served
as-is and calls the `.php` files next to it with relative URLs, so it
works from any subfolder or domain without code changes.

## 4. Web server notes

**Apache**: the included `.htaccess` blocks directory listing, direct
requests to `includes/*.php`, and direct requests to `schema.sql` /
`README_DEPLOY.md`. Requires `mod_rewrite` and `mod_headers` (both are on
by default on almost all Apache hosts).

**Nginx** — add the equivalent to your server block:

```nginx
location ~ ^/includes/ { deny all; }
location ~ ^/(schema\.sql|README_DEPLOY\.md)$ { deny all; }
autoindex off;
```

## 5. Going live with real mobile money

Right now `FVSS_PAYMENTS_MODE` defaults to mock: `book_room.php` and
`enroll_course.php` record the payment attempt, and the front end (see
the `waitThenConfirm()` helper in `assets/js/app.js`) simulates the
customer approving the prompt, then calls `payment_callback.php` itself
to confirm — so you can demo and test the whole flow without a real MoMo
account.

To go live:

1. Get merchant credentials from MTN MoMo (Collections API) and/or
   Airtel Money.
2. In `includes/payment.php`, fill in the `--- Real integration goes
   here ---` block with the actual `requesttopay` (MTN) or
   `/merchant/v1/payments/` (Airtel) call.
3. Point the provider's webhook at `payment_callback.php`, and add
   signature verification there before trusting the payload — right now
   it accepts any POST, which is only safe for local testing.
4. In `assets/js/app.js`, remove the `waitThenConfirm()` simulated delay
   and its call to `payment_callback.php` — once real webhooks are
   confirming payments, the front end should just poll or wait for the
   booking/enrollment status instead of confirming it itself.
5. Set `FVSS_PAYMENTS_MODE=live`.

## Not yet in scope

Auth/sessions, rate limiting, and an admin view for managing bookings
aren't built yet (see the original backend `README.md` — kept below as
`README.md` for reference).
