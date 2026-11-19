# Putting TICA on cPanel

The **tica-cpanel.zip** package (GitHub → Actions → "cPanel package" → latest run → Artifacts)
has everything the site needs, ready to upload: no Composer or npm on the server.

## 1. Back up first
cPanel → **phpMyAdmin** → your database → **Export** → Go. Keep the file.

## 2. Check PHP
cPanel → **MultiPHP Manager** → set the domain to **PHP 8.4** (8.5 also works).
Extensions needed (cPanel → Select PHP Version → Extensions): bcmath, ctype, curl, dom,
fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip.

## 3. Upload the files
1. cPanel → **File Manager** → make a folder next to `public_html`, e.g. `tica`.
2. Upload **tica-cpanel.zip** into it and **Extract**.
3. Point the domain at the `public` folder: cPanel → **Domains** → your domain →
   Document Root → `tica/public`.
   (If you can't change it, extract into `public_html` instead: the `.htaccess` in the
   package sends visitors to `public/` for you.)

## 4. Settings file
Copy `deploy/cpanel/env.cpanel.example` to `.env` in the `tica` folder and fill in the
lines marked **FILL IN**. Keep the old `APP_KEY`: saved secrets in the database need it.

## 5. Update the database (one time, keeps all your data)
Your current database is the original Evento one: TICA's 114 newer updates still
need to go in. They add tables and columns and convert some data; nothing is deleted.

**With cPanel Terminal** (cPanel → Terminal):
```
cd ~/tica
php artisan migrate --force
```

If upgrading a site that previously accepted ZIP attachments on support tickets,
move those legacy files into private storage after the database update:
```
php artisan support:secure-attachments --dry-run
php artisan support:secure-attachments
```
The second command exits with an error if a database reference points to a file
that cannot be found, and it does not overwrite that reference.

If the hosting plan has no terminal, ask the host to run the same command from the
application directory. TICA deliberately does not expose database updates over HTTP.

## 6. Folders that must be writable
`storage/` and `bootstrap/cache/` (755 is usually enough on cPanel).

## 7. Scheduled jobs
cPanel → **Cron Jobs** → Once Per Minute:
```
cd ~/tica && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Turn on email, payments and sign-in
Log in as admin → **Integrations**: fill in Email (press "Send me a test email"),
Paystack, SMS, Google sign-in. Each has a Test button.

## The SQL file in database/supabase is not for cPanel
`database/supabase/tica_database_update.sql` is written for Supabase (Postgres) and won't
run in phpMyAdmin. On cPanel, step 5 does the same job, written for MySQL/MariaDB.
