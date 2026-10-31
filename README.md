# Bookworm Buddies

A community site for lending books: share what's on your shelf and borrow what you haven't read. Anyone can browse, and an account is needed to request or share books. Built with plain PHP 8.1+, MySQL 8, Cloudinary and Resend, with no framework or build step.

## Setup

1. Copy `config.example.php` to `config.php` and fill in your database, Cloudinary and Resend details. Add your database's CA certificate as `ca.pem`.
2. Load the tables from `database/schema.sql`, then the indexes from `database/scale.sql`.
3. Optional: fill an empty database with demo content. Logins are written to `demo-accounts.txt`.

   ```bash
   php tools/seed.php
   php tools/seed-covers.php
   ```

4. Start the site:

   ```bash
   php -d upload_max_filesize=10M -d post_max_size=12M -S localhost:8000 -t public
   ```

## Admin

The admin area is at `/admin`, and it doesn't exist until you configure it:

1. Create a password hash (at least 12 characters):

   ```bash
   php tools/hash-password.php
   ```

2. Add your email and the hash to the `admin` block in `config.php`.

## Return reminders

Render's free plan has no scheduled jobs, so an outside service calls a secret link once a day:

1. Set `cron_token` in `config.php` to a long random value, for example from `openssl rand -hex 32`.
2. At a free scheduler such as cron-job.org, add a daily call to `https://your-site/cron.php?token=YOUR_TOKEN`.

The admin dashboard also has a button to send reminders by hand.

## Deploying to Render

The repo includes a `Dockerfile` and `render.yaml` for Render's free plan.

1. Push this repo to GitHub.
2. In Render, choose **New > Blueprint** and connect the repo.
3. Open the new service's **Environment** page and add two **Secret Files**: `config.php` and `ca.pem`. In `config.php`, keep `'ssl_ca' => __DIR__ . '/ca.pem'`.
4. Deploy, then copy the `onrender.com` address and set it as `app_url` in the `config.php` secret file. Save, and redeploy.
5. Check that sign-up emails arrive, and that their links open the live site.

If the database won't connect, check that the Aiven service's allowed IP addresses include Render. On the free plan, a site asleep for 15 minutes takes up to a minute to wake.

## Deploying elsewhere

Point the web server's document root at `public/`, so `config.php` and everything else stay out of reach. Set `app_url` in `config.php` to the site's public address, because email links are built from it. Use a Resend sender on a verified domain. Serve the site over HTTPS, and set `upload_max_filesize` to 10M and `post_max_size` to 12M. The GD extension is required, because photos are compressed to HD before they reach Cloudinary.

## Tests

```bash
php tools/test.php
```

The tests start their own copy of the site on a separate `bookworm_test` database (create it once from `database/schema.sql` and `database/scale.sql`), so nothing they create appears on the real site. By default they send no email and upload no images. Adding `--real-services` calls Resend and Cloudinary for real, which uses your plan's limits, so keep it for rare final checks.

## Layout

| Folder | What's in it |
|---|---|
| `public/` | Pages and assets, the only web-accessible folder |
| `public/actions/` | Form handlers that change data |
| `src/` | Database, login, validation, Cloudinary and view helpers |
| `templates/` | Shared page layout and components |
| `database/` | Table definitions |
| `tools/` | Demo data and tests, command line only |
