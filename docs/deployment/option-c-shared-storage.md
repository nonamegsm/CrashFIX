# Option C — Shared DB **and** shared file storage

> **Risk to production: STILL LOW** as long as you keep the
> `crashfix_readonly` MySQL user from [Option B](option-b-shared-db.md)
> and the new app stays on read-only DB. The only thing that
> changes is *how the new app finds files on disk*.
>
> **Recommended for:** full feature-parity preview — the new UI
> can now download historical `.zip`s, render screenshots, fetch
> debug symbols, and download bug attachments.

## What you'll get

- Everything from Option B
- **Plus:** every file the legacy app stored under
  `/var/www/crashfix-old/protected/data/` becomes downloadable from
  the new UI
- Crash report screenshots show up correctly in the *Screenshots*
  tab on the report detail page
- Debug-info downloads work
- Bug attachment downloads work

## How it works

The new Yii2 app routes every file read/write through a single
component, [`Storage`](../../components/Storage.php), with these
key methods:

```
crashReportPath(int $projectId, int $reportId): string
crashReportThumbDir(int $projectId, int $reportId): string
crashReportExtractDir(int $projectId, int $reportId): string
debugInfoPath(int $projectId, int $debugInfoId, string $filename): string
bugAttachmentPath(int $projectId, int $attachmentId, string $filename): string
```

Default `Storage` resolves these using a project-scoped layout:
`projects/{id}/crashreports/{report_id}.zip` etc.

[`LegacyStorage`](../../components/LegacyStorage.php) overrides the
same five methods to resolve the **legacy MD5-sharded layout**:
`crashReports/{md5[0:3]}/{md5[3:6]}/{md5}.zip` etc. — looking up
the relevant `md5`/`guid`/`filename` from the database when needed.

Because every controller calls `Yii::$app->storage->...` (never
constructs paths directly), swapping the implementation is a single
config change.

## Steps

### 0. Prerequisite

Complete [Option B](option-b-shared-db.md) first.

### 1. Make the legacy data dir readable by the web user

```bash
# The legacy app runs as www-data (probably). The new app runs as
# www-data too. Make sure the new app can READ the legacy data dir
# but cannot WRITE to it.
sudo chown -R www-data:www-data /var/www/crashfix-old/protected/data
sudo find /var/www/crashfix-old/protected/data -type d -exec chmod 0750 {} \;
sudo find /var/www/crashfix-old/protected/data -type f -exec chmod 0640 {} \;
```

Group `www-data` reads, no group writes — extra defence-in-depth
even though the read-only DB user already prevents the new app
from creating new file rows.

### 2. Switch the new app's `storage` component to `LegacyStorage`

Edit `/var/www/crashfix-new/config/web.php`. Find the `storage`
block from Option B and change it:

```diff
 'storage' => [
-    'class'    => 'app\components\Storage',
-    'basePath' => '/var/www/crashfix-new/data',
+    'class'    => 'app\components\LegacyStorage',
+    'basePath' => '/var/www/crashfix-old/protected/data',
 ],
```

That's it. No other code change.

### 2a. First-time Yii2 setup instead of hand-editing `web.php`

If this server has **not** been marked installed yet (`config/installed.txt`
missing), use the web wizard at `/install/index`. Choose **Existing CrashFix
(Yii1)**, enter the same MySQL database as the legacy app, and set the **legacy
data directory** to the old `protected/data` path. The installer writes
`config/user_params.ini` with a `[storage]` section (`storage_layout`,
`storage_base_path`), and both `config/web.php` and `config/console.php` load
`config/storage.php`, which switches to `LegacyStorage` automatically — no
diff against `web.php` is required.

If the app is **already** installed and you only want to repoint storage,
either re-run the wizard with `?force=1` on the install URLs (development /
staging) or edit `user_params.ini` by hand to match the snippet in the
installer’s on-screen help, then reload PHP.

### 3. Reload PHP-FPM / Apache to pick up the new class

```bash
sudo systemctl reload apache2     # or php-fpm if you use that
```

(The Yii2 class autoloader will pick up `LegacyStorage.php` from
`components/` automatically — no `composer dump-autoload` needed.)

### 4. Verify

Browse to `https://new.example.com/crash-report/index`, click any
report, check:

- [ ] **Files tab** lists archived files; clicking the ZIP link
      starts a real download
- [ ] **Screenshots tab** shows actual thumbnails (or "no
      screenshots" if the report has none)
- [ ] **Threads tab** lists threads; clicking *View stack trace*
      shows real frames
- [ ] **Modules tab** shows loaded modules + their PDB paths
- [ ] Going to a *Debug Info* row and clicking download starts the
      actual `.pdb` download
- [ ] Going to a *Bug* with attachments and clicking the attachment
      link downloads the file

If any of these 404, check `runtime/logs/app.log` for the
"file does not exist on disk" message — that means either the
legacy file genuinely isn't there (corrupted DB row) or the
`md5`/`guid` lookup is failing. The `LegacyStorage` class only
reads `tbl_crashreport.md5`, `tbl_bug_attachment.md5`,
`tbl_debuginfo.{filename,guid,status}` — verify those columns
have the values you expect.

## Where on-demand artefacts get written

`LegacyStorage` is **read-mostly**. Two methods *do* create files,
but deliberately under the new app's `runtime/` so they never
pollute the legacy data tree:

| Method | Writes to | Why |
|---|---|---|
| `crashReportExtractDir(p,r)` | `runtime/legacy-extracts/{r}/` | when a user clicks "Extract this file" we need somewhere to drop the temp |
| `crashReportThumbDir(p,r)` | `runtime/legacy-thumbs/{r}/` | GD-rendered screenshot thumbnails |

Pre-create + chown:

```bash
sudo install -d -m 0775 -o www-data -g www-data \
     /var/www/crashfix-new/runtime/legacy-extracts \
     /var/www/crashfix-new/runtime/legacy-thumbs
```

These directories are safe to delete at any time — they're a
regenerable cache.

## Caveats

- **Crash report uploads and bug attachments** through the new UI
  will land in the legacy MD5-sharded paths. If you're still on the
  read-only MySQL user (recommended at this stage) the DB write
  will fail before any file is written, so this is moot. **If** you
  later switch to a read-write user, write paths from the new app
  will end up in the same place the legacy daemon expects to find
  them — that's intentional, not a bug.
- **Debug info uploads** require a writable
  `protected/data/debugInfo/{filename}/{guid}/` directory. With the
  hardened 0750 perms above, only the legacy app can create files
  there. Same caveat as above: read-only DB → no DB row → no file
  written, so it's fine for read-only mode.
- **Daemon side** is unchanged. The `crashfixd` process keeps using
  the legacy `protected/data/` paths it always did. Both apps,
  daemon, all share the same files.

## Going read-write later

Once you're confident the new UI handles your real data correctly,
you can flip the new app to read-write by replacing its DB user
with `crashfix_rw` (granted full DML on `crashfix.*`). At that
point both apps + daemon are reading and writing the same DB and
the same files concurrently. That's the **production-cutover**
configuration; treat it as a deployment, not a test.

## Reverting

Switch the `storage` block in `config/web.php` back to the
project-scoped default and reload Apache:

```php
'storage' => [
    'class'    => 'app\components\Storage',
    'basePath' => '/var/www/crashfix-new/data',
],
```

Legacy data is untouched.

## See also

- [`components/LegacyStorage.php`](../../components/LegacyStorage.php) —
  source of the adapter
- [`components/Storage.php`](../../components/Storage.php) —
  the base class with the public API contract
- [Option A](option-a-readonly-shadow.md) and
  [Option B](option-b-shared-db.md) — safer first steps
