# Side-by-side Deployment Guide

How to run the new Yii2 CrashFix UI on a separate subdomain
(`new.example.com`) alongside the legacy Yii1 install
(`old.example.com`) **without disrupting production**.

## Why side-by-side?

The Yii2 port is a fresh implementation of the CrashFix web UI
that talks to the same data the legacy Yii1 app does:

- Same `tbl_*` MySQL schema (the [migrations under `migrations/`](../../migrations/)
  match legacy column-for-column).
- Same `crashfixd` daemon (one binary serves both apps; protocol
  unchanged).
- Different on-disk file layout (legacy is MD5-sharded; the new app
  is project-scoped). This is the only thing that needs adapting,
  and only when you choose Option C.

You can therefore stand the new UI up on a subdomain, point it at
the existing database, and start clicking around against real data
**without touching the production app**.

## Three deployment options

| | Option A: Read-only shadow | Option B: Shared DB, separate storage | Option C: Shared DB + Legacy storage |
|---|---|---|---|
| **DB**         | clone of prod | live prod (read-only user) | live prod (read-only user, then read-write) |
| **Storage**    | RO mount or skipped | new app gets its own `data/` | shares legacy `protected/data/` via [`LegacyStorage`](../../components/LegacyStorage.php) |
| **Risk**       | zero | low (no writes possible) | low (still read-only DB) |
| **Effort**     | ~30 min (DB clone takes time) | ~10 min | ~5 min after Option B |
| **Browse old data** | yes | yes | yes |
| **Download historical crash report files** | yes | **no** | yes |
| **Test new uploads** | yes | yes | yes (writes go to legacy paths!) |
| **Recommended for** | first run, validation | day-to-day staging | full-parity preview |

We recommend the progression **A → B → C** so you build confidence
before each escalation.

- 🟢 [`option-a-readonly-shadow.md`](option-a-readonly-shadow.md) -
  clone DB into a `crashfix_test` schema, point the new app at it.
- 🟡 [`option-b-shared-db.md`](option-b-shared-db.md) -
  point the new app at production DB through a read-only MySQL user;
  the new app gets its own `data/` directory for any new uploads.
- 🟠 [`option-c-shared-storage.md`](option-c-shared-storage.md) -
  same as B, plus swap `Storage` for `LegacyStorage` so the new UI
  can read every historical `.zip`, `.pdb` and bug attachment from
  the legacy `protected/data/` tree.

## Prerequisites

- Apache (or nginx) with PHP 7.4+ and `mod_rewrite`
- MySQL/MariaDB the legacy app already uses
- Shell access to the box that runs the legacy app
- A subdomain you can point at this server (DNS A/AAAA record)
- The Yii2 codebase deployed to a fresh path, e.g.
  `/var/www/crashfix-new/`

## Filesystem layout (assumed across all three options)

```
/var/www/
  ├ crashfix-old/                    ← existing Yii1 install (untouched)
  │   └ protected/data/              ← legacy file storage
  │       ├ crashReports/<md5sh>/<md5>.zip
  │       ├ debugInfo/<file>/<guid>/<file>
  │       └ bugAttachments/<md5sh>/<md5>
  │
  └ crashfix-new/                    ← new Yii2 port
      ├ web/                         ← document root
      ├ config/
      ├ migrations/
      ├ components/Storage.php
      └ components/LegacyStorage.php ← only used by Option C
```

## Common: Apache vhost for the new subdomain

This is the same for all three options. Create
`/etc/apache2/sites-available/crashfix-new.conf`:

```apache
<VirtualHost *:80>
    ServerName  new.example.com
    DocumentRoot /var/www/crashfix-new/web

    <Directory /var/www/crashfix-new>
        Require all denied
    </Directory>
    <Directory /var/www/crashfix-new/web>
        Require all granted
        AllowOverride All
        FallbackResource /index.php
    </Directory>

    ErrorLog  /var/log/apache2/crashfix-new.error.log
    CustomLog /var/log/apache2/crashfix-new.access.log combined
</VirtualHost>
```

Enable + reload:

```bash
sudo a2ensite crashfix-new
sudo systemctl reload apache2
```

For nginx the equivalent is `root /var/www/crashfix-new/web;
try_files $uri /index.php?$args;` plus a php-fpm location block.

## Common: bypass the installer

The Yii2 app's [`SiteController::beforeAction`](../../controllers/SiteController.php)
redirects to `/install/index` when `config/installed.txt` is missing.
Since the schema already exists in the legacy DB, you don't need to
run the installer:

```bash
date > /var/www/crashfix-new/config/installed.txt
```

> **DO NOT** run `php yii migrate` against the legacy database. The
> migration history table is empty, so `migrate` would try to
> re-create existing tables and fail. To make `migrate` aware of the
> existing schema without re-running anything:
>
> ```bash
> php yii migrate/mark m250101_000010_seed_mail_status
> ```
>
> This fast-forwards the migration history past every shipped
> migration without executing them.

## Common: cookie validation key

Each install needs its own. Generate a fresh key for the new vhost:

```bash
php -r 'echo bin2hex(random_bytes(16)), "\n";'
```

Paste into `config/web.php` `'cookieValidationKey' => '...'`.

## What about the daemon?

You **don't need a second daemon**. The single `crashfixd` process
already running for the legacy app continues to serve both web
front-ends - it talks to MySQL and the filesystem, not to a
specific webroot. The new app's
[`Daemon` component](../../components/Daemon.php) connects to the
same `127.0.0.1:50` socket.

If you want the new app's daemon-status check to go green, leave
`SITE_POLL_COMMAND` in `crashfixd.conf` pointed at whichever app's
console you trust most:

```conf
# Keep production behaviour (default for legacy installs):
SITE_POLL_COMMAND = php /var/www/crashfix-old/protected/yiic.php poll

# Or once you trust the new app's polling:
SITE_POLL_COMMAND = php /var/www/crashfix-new/yii poll/run
```

See [`commands/PollController.php`](../../commands/PollController.php)
for what the new poll action does.

## Rollback

Every option above leaves the legacy install untouched. To roll back:

1. Disable the new vhost: `sudo a2dissite crashfix-new && sudo systemctl reload apache2`.
2. Drop the read-only MySQL user (Options B/C):
   `DROP USER 'crashfix_readonly'@'localhost';`
3. Delete `/var/www/crashfix-new/` (or just leave it; it does no harm
   when its vhost is disabled).
4. The legacy app continues exactly as before.

## See also

- [MIGRATION_WORKFLOW.md](../../MIGRATION_WORKFLOW.md) — full Yii1→Yii2 porting plan
- [README.md](../../README.md) — project overview
- [components/Storage.php](../../components/Storage.php) — default project-scoped storage component
- [components/LegacyStorage.php](../../components/LegacyStorage.php) — drop-in legacy-path adapter
