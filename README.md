# Bookworm Buddies

A community site for lending books: share what's on your shelf and borrow what you haven't read. Built with plain PHP 8.1+, MySQL 8 and Cloudinary, with no framework or build step.

## Setup

1. Copy `config.example.php` to `config.php` and fill in your database and Cloudinary details. Add your database's CA certificate as `ca.pem`.
2. Load the tables from `database/schema.sql`.
3. Optional: fill an empty database with demo content. Logins are written to `demo-accounts.txt`.

   ```bash
   php tools/seed.php
   php tools/seed-covers.php
   ```

4. Start the site:

   ```bash
   php -d upload_max_filesize=6M -d post_max_size=8M -S localhost:8000 -t public
   ```

## Deploying

Point the web server's document root at `public/`, so `config.php` and everything else stay out of reach. Serve the site over HTTPS, and allow uploads of at least 6 MB (`upload_max_filesize` and `post_max_size`).

## Tests

With the site running:

```bash
php tools/test.php http://localhost:8000
```

The tests use the configured database. They create `zztest_*` accounts and remove them afterwards.

## Layout

| Folder | What's in it |
|---|---|
| `public/` | Pages and assets, the only web-accessible folder |
| `public/actions/` | Form handlers that change data |
| `src/` | Database, login, validation, Cloudinary and view helpers |
| `templates/` | Shared page layout and components |
| `database/` | Table definitions |
| `tools/` | Demo data and tests, command line only |
