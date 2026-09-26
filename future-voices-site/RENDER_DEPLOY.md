# Deploying to Render (self-hosted MySQL)

This repo includes a Render Blueprint (`render.yaml`) that creates both
pieces you need in one pass:

- **`future-voices-web`** — a Docker web service running the site (PHP +
  Apache, built from `Dockerfile`)
- **`future-voices-db`** — a private MySQL service (built from
  `mysql/Dockerfile`, official `mysql:8` image + your `schema.sql`
  pre-loaded, on a persistent disk)

Render's own managed database offering is Postgres-only, which is why the
database here is self-hosted as a second service rather than picked from
Render's database list — the Blueprint sets that up for you, no separate
signup or provider needed.

## 1. Push to a Git repo

Render Blueprints deploy from GitHub, GitLab, or Bitbucket. Create a repo
with this folder's contents (including `render.yaml`, `Dockerfile`,
`mysql/`, `docker/`) and push it.

## 2. Deploy the Blueprint

1. In the Render Dashboard: **New → Blueprint**.
2. Connect the repo you just pushed. Render finds `render.yaml`
   automatically and shows both services it's about to create.
3. Click **Apply**. Render builds both Docker images and starts them —
   the database first initializes its disk and loads `schema.sql`
   (seeding the 4 rooms and 9 programs), then the web service starts and
   connects to it using the environment variables the Blueprint wires up
   automatically (`FVSS_DB_HOST`, `FVSS_DB_PORT`, `FVSS_DB_NAME`,
   `FVSS_DB_USER`, `FVSS_DB_PASS` — all pulled from the database service,
   nothing to copy/paste by hand).
4. First deploy takes a few minutes — mostly the MySQL image pulling and
   initializing its disk.

## 3. Get your URL

Once both services show **Live**, `future-voices-web` has a
`*.onrender.com` URL — open it and the site should load with the booking
widget pulling real availability from the database. Add a custom domain
under that service's **Settings → Custom Domains** whenever you're ready.

## 4. Plans and cost

Both services are set to Render's `starter` plan in `render.yaml` — the
database needs a paid plan because it uses a persistent disk (disks
aren't available on free instances), and the web service is set to match
so the whole thing runs on stable, non-sleeping instances. Adjust the
`plan:` values in `render.yaml` if you want a different tier; Render will
pick up the change on the next Blueprint sync.

## 5. Going live with real mobile money

Same as local/any-host deployment — see the "Going live with real mobile
money" section in `README_DEPLOY.md`. The short version: fill in the real
MTN MoMo / Airtel Money calls in `includes/payment.php`, point their
webhook at `https://<your-onrender-url>/payment_callback.php`, remove the
simulated-wait code in `assets/js/app.js`, then set `FVSS_PAYMENTS_MODE`
to `live` on the `future-voices-web` service in the Render dashboard.

## Notes on the self-hosted database

- **Backups**: Render's disk snapshots aren't automatic the way managed
  Postgres backups are. For anything beyond testing, add a scheduled job
  (Render Cron Job) that runs `mysqldump` against `future-voices-db` and
  ships the file somewhere durable (S3-compatible storage, etc.).
- **Scaling**: a disk pins a service to a single instance — don't scale
  `future-voices-db` beyond 1 instance. That's already how the Blueprint
  is set up.
- **Direct access**: the database is a private service, so it isn't
  reachable from outside Render's network — only `future-voices-web` can
  reach it. To run a one-off query, use `psql`/`mysql` from Render's
  **Shell** tab on the `future-voices-db` service.
