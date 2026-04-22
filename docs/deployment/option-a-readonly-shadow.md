# Option A — Read-only shadow deployment

> **Risk to production: ZERO.** The new app sees a clone of the data
> and its own filesystem — nothing it does can affect the live site.
>
> **Recommended for:** the very first run-through, when you want to
> validate that the new UI renders, login works, charts populate,
> without taking on any production risk.

## What you'll get

- New UI on `new.example.com` browsing a **frozen snapshot** of your
  database
- Zero shared writes
- Independent file storage (the new app never reads or writes the
  legacy data dir)
- Disposable: throw away the cloned DB when you're done, no cleanup
  on the production side

## Limitations

- Anything you do in the new UI (close a bug, edit a project) only
  affects the cloned DB, not production
- Crash report download and screenshot extraction won't find the
  legacy `.zip` files because we're not pointing at the legacy
  storage. That's fine for an initial validation pass.

## Steps

### 1. Clone the production database

```bash
# On the box that runs the legacy app
mysqldump --single-transaction --quick crashfix > /tmp/crashfix-snap.sql

mysql -e "CREATE DATABASE crashfix_test
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql crashfix_test < /tmp/crashfix-snap.sql
```

For very large databases (tens of GB), use `--single-transaction`
to keep the dump consistent without locking writers, and pipe
straight into the test schema instead of via a temp file:

```bash
mysqldump --single-transaction --quick crashfix \
  | mysql crashfix_test
```

### 2. Create a dedicated MySQL user for the test schema

```sql
CREATE USER 'crashfix_shadow'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON crashfix_test.* TO 'crashfix_shadow'@'localhost';
FLUSH PRIVILEGES;
```

Yes, `ALL PRIVILEGES` here — the shadow DB is throwaway, so let
the new app exercise its full write paths.

### 3. Deploy the Yii2 app

```bash
git clone https://github.com/nonamegsm/CrashFIX.git /var/www/crashfix-new
cd /var/www/crashfix-new
git checkout yii2-port
composer install --no-dev --optimize-autoloader
chown -R www-data:www-data runtime/ web/assets/
```

### 4. Point the new app at the shadow DB

`/var/www/crashfix-new/config/user_params.ini`:

```ini
[db]
db_connection_string = "mysql:host=127.0.0.1;dbname=crashfix_test"
db_username          = "crashfix_shadow"
db_password          = "change-me"
db_table_prefix      = "tbl_"
```

### 5. Mark the install as already done

```bash
date > /var/www/crashfix-new/config/installed.txt
php yii migrate/mark m250101_000010_seed_mail_status   # see README.md prerequisites
```

### 6. Generate a fresh cookie key

```bash
php -r 'echo bin2hex(random_bytes(16)), "\n";' \
  | xargs -I{} sed -i "s/'cookieValidationKey' => '[^']*'/'cookieValidationKey' => '{}'/" \
       /var/www/crashfix-new/config/web.php
```

### 7. Configure & enable the Apache vhost

See the vhost block in [README.md → Common: Apache vhost for the new
subdomain](README.md#common-apache-vhost-for-the-new-subdomain), then:

```bash
sudo a2ensite crashfix-new
sudo systemctl reload apache2
```

### 8. Browse `https://new.example.com`

Log in with **the same credentials** you use on the legacy app —
the user table came across in the dump and password hashes are
fully compatible (the new `User` model uses the same `md5(salt+pwd)`
algorithm).

## Verification checklist

- [ ] `https://new.example.com/site/login` renders cleanly
- [ ] You can log in as your existing admin user
- [ ] Crash Reports / Bugs / Debug Info menus all load (data may be
      paginated through GridView)
- [ ] The Digest charts (Crash Report Uploads, Bug Dynamics, etc.)
      render with real numbers from your data
- [ ] Project switcher in the top-bar lists your real projects

## Cleanup

When you're done with the shadow:

```bash
mysql -e "DROP DATABASE crashfix_test;"
mysql -e "DROP USER 'crashfix_shadow'@'localhost';"
sudo a2dissite crashfix-new
sudo systemctl reload apache2
rm -rf /var/www/crashfix-new
```

## Next step

When the read-only shadow looks healthy, graduate to
[Option B](option-b-shared-db.md) to test against the *live* DB
(still write-protected, but no need to maintain a clone).
