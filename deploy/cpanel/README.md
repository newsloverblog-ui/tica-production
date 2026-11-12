# Tica on cPanel

This profile targets a single cPanel server without Redis or Supervisor. It
uses MySQL for queues, cache locks and sessions. Redis remains preferable when
the host provides a managed, persistent Redis service.

## Layout

Keep application code outside the public web root:

```text
/home/CPANEL_USER/tica/current       application release
/home/CPANEL_USER/tica/shared/.env   production secrets
/home/CPANEL_USER/tica/shared/storage persistent private files and logs
```

Set the domain document root to `/home/CPANEL_USER/tica/current/public`. Never
copy the entire Laravel project into `public_html`.

## First release

1. Upload/extract the release into `tica/current`.
2. Link `current/.env` to `shared/.env` and `current/storage` to
   `shared/storage`.
3. Merge `tica-production.env.example` into the private `.env`; replace every
   `CHANGE_ME` value using cPanel-managed secrets.
4. Run with the cPanel PHP binary:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
php artisan ops:release-readiness --configuration-only --json
```

Do not continue a production release while the readiness command reports a
critical result.

## Scheduler and workers

Install the three entries from `cron.example`. Confirm the PHP path using
cPanel's **Terminal** (`which php`) or the PHP selector. If `flock` is not
available, ask the host to enable it; overlapping queue workers are unsafe.

After deployment, run `php artisan queue:restart`. Check **Cron Jobs** and both
queue logs after one minute. The readiness gate also expects current worker
heartbeats.

## cPanel operations still requiring real evidence

- Configure encrypted off-site database and private-file backups and perform a
  restore drill.
- Configure external uptime checks for `/health/live` and `/health/ready`, plus
  error tracking and a synthetic checkout exercise.
- Complete the WCAG, privacy/DPA, penetration-test, incident-response, key
  rotation, SMS and payment-provider drills before recording their evidence
  variables.
- Enable CSP only after staging report-only violations have been reviewed.

These attestations must describe real completed work; placeholder values should
never be used to make the readiness command pass.
