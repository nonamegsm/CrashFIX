# Option B — Shared DB, separate storage

> **Risk to production: LOW.** The new app reads the live database
> through a MySQL user that **physically cannot write**. Its own
> file storage is separate, so any new uploads land outside the
> legacy data tree.
>
> **Recommended for:** day-to-day staging, demos to stakeholders,
> validating that aggregations and search behave correctly against
> live data without maintaining a DB clone.

## What you'll get

- New UI on `new.example.com` browsing the **real, live** database
- All charts and stats populate from real numbers, in real time
- Login with existing credentials (read of `tbl_user` is allowed)
- Sessions persist (DB session writes are explicitly granted)
- New uploads, comment edits etc. **fail silently** at the DB layer
  — UI may show success, but no row is written

## Limitations vs Option C

- Crash report download buttons return 404 — files live under the
  legacy MD5-sharded path that this option doesn't know about
- Same for screenshot view, debug-info download, bug attachment
  download
- That's an acceptable limitation for a UI/data validation pass.
  When you need full file access, move to [Option C](option-c-shared-storage.md).

## Steps

### 1. Create the read-only MySQL user

```sql
-- On the production MySQL server
CREATE USER 'crashfix_readonly'@'localhost' IDENTIFIED BY 'change-me';

-- Browse everything
GRANT SELECT ON crashfix.* TO 'crashfix_readonly'@'localhost';

-- Sessions need to be writable so the user can stay logged in.
-- This is the ONLY write privilege we grant.
GRANT INSERT, UPDATE, DELETE ON crashfix.tbl_YiiSession
   TO 'crashfix_readonly'@'localhost';

FLUSH PRIVILEGES;
```

That single grant set means:

- **`SELECT *`** — the new app can show every row in every table
- **No `INSERT`/`UPDATE`/`DELETE`** on domain tables — bug edits,
  status changes, project tweaks, and uploads all fail at the DB
  layer
- **Sessions** — `tbl_YiiSession` is writable so the cookie-based
  login persists across page loads

### 2. Deploy the Yii2 app

Same as [Option A step 3](option-a-readonly-shadow.md#3-deploy-the-yii2-app):

```bash
git clone https://github.com/nonamegsm/CrashFIX.git /var/www/crashfix-new
cd /var/www/crashfix-new
git checkout yii2-port
composer install --no-dev --optimize-autoloader
chown -R www-data:www-data runtime/ web/assets/
```

### 3. Point the new app at the live DB through the read-only user

`/var/www/crashfix-new/config/user_params.ini`:

```ini
[db]
db_connection_string = "mysql:host=127.0.0.1;dbname=crashfix"
db_username          = "crashfix_readonly"
db_password          = "change-me"
db_table_prefix      = "tbl_"
```

### 4. Give the new app its own file storage area

Add this to `/var/www/crashfix-new/config/web.php` (under
`'components'`):

```php
'storage' => [
    'class'    => 'app\components\Storage',
    'basePath' => '/var/www/crashfix-new/data',   // NOT the legacy data dir!
],
```

Pre-create + chown:

```bash
sudo install -d -m 0775 -o www-data -g www-data \
     /var/www/crashfix-new/data
```

### 5. Mark install + cookie key + vhost

Same as Option A steps 5/6/7:

```bash
date > /var/www/crashfix-new/config/installed.txt
php yii migrate/mark m250101_000010_seed_mail_status

# Cookie key
php -r 'echo bin2hex(random_bytes(16)), "\n";' \
  | xargs -I{} sed -i "s/'cookieValidationKey' => '[^']*'/'cookieValidationKey' => '{}'/" \
       config/web.php

# Apache
sudo a2ensite crashfix-new
sudo systemctl reload apache2
```

### 6. (Recommended) Wire DB-backed sessions explicitly

The shipped `config/web.php` already switches the `session`
component to `DbSession` once `installed.txt` exists, but verify
the Apache user can read `config/`:

```bash
sudo chown -R www-data:www-data /var/www/crashfix-new/config
sudo chmod 0640 /var/www/crashfix-new/config/user_params.ini
```

The session table the legacy app uses (`tbl_YiiSession`) is shared
with both apps. That's fine — Yii2 `DbSession`'s row format
(`id`, `expire`, `data`) is a strict superset of Yii1's.

## Verification checklist

- [ ] `https://new.example.com/` renders the Digest with real data
- [ ] Project switcher lists your **real** projects, not just `demo`
- [ ] Bug grid shows real bugs; clicking one shows real change history
- [ ] Charts (Crash Report Uploads, Bug Dynamics, etc.) match what
      the legacy app reports for the same period
- [ ] Trying to delete a bug returns a Yii2 server error in
      `runtime/logs/app.log` mentioning denied INSERT/DELETE — that's
      the read-only guard working as designed
- [ ] Crash report download links return 404 — expected, addressed
      by Option C

## Caveats specific to read-only DB

- **Login still requires session writes**, so make sure
  `tbl_YiiSession` was granted. Without it the user gets a fresh
  cookie on every page and stays logged out.
- **`PollController::actionRun` writes to `tbl_operation`** and
  drains the mail queue. If you wired this app's poll into the
  daemon (`SITE_POLL_COMMAND`), the daemon will start logging
  permission errors every 30s. Either:
  - leave the daemon polling the legacy app (recommended), **or**
  - grant the readonly user write on `tbl_operation` + `tbl_mail_queue`
- **`User::beforeSave` hashes the password on insert** — irrelevant
  here since you can't insert, but worth knowing if you switch to a
  read-write user later.

## Hardening: defence-in-depth read-only flag

Even with a read-only DB user, you may want the UI to short-circuit
write actions and display a clear "staging mode" badge instead of
opaque DB errors. Add to `config/params.php`:

```php
'readOnlyMode' => true,
```

We haven't shipped a `ReadOnlyFilter` yet, but it's a one-file
addition — open an issue or ask in the repo if you want it.

## Next step

When you need to download historical crash reports, view
screenshots, or read debug-info files through the new UI, move to
[Option C](option-c-shared-storage.md) — it's a 5-minute change on
top of everything you've already configured here.
