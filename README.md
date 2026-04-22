# CrashFix

CrashFix is a server for collecting, processing and managing application
crash reports. This repository contains the **Yii 2 port** of the
original Yii 1 CrashFix application; the legacy code base is preserved
under `legacy/` for reference.

The server collects Windows-style minidumps (CrashRpt-compatible),
groups duplicates, links them to bugs, manages debug symbols, and
exposes the data through a project-aware web UI.

## Features

- **Crash report ingestion** &mdash; both authenticated and anonymous
  external upload endpoints
- **Automatic grouping** of duplicate crashes by stack-trace fingerprint
- **Bug tracker** with attachments, comments, status history, and crash
  linking
- **Debug symbol management** per project & version
- **Project & user roles** with per-project permissions on top of Yii's
  RBAC
- **Background daemon** for report processing and symbolication
- **Mail queue** for outbound notifications
- Admin panel for daemon status, license info, user / group management

## Stack

- PHP &ge; 7.4
- [Yii 2 Basic](https://www.yiiframework.com/extension/yiisoft/yii2-app-basic) ~2.0.45
- MySQL 5.7+ / MariaDB 10.2+
- AdminLTE 3 + Bootstrap 5 (mapped to Bootstrap 4 for AdminLTE compat)
- Symfony Mailer
- Codeception for tests

## Directory Layout

```
assets/         asset bundle definitions
commands/       console controllers
components/     app components (Daemon, BatchImporter, WebUser, MiscHelpers)
config/         application configuration
controllers/    web controllers
mail/           e-mail view templates
migrations/     database schema migrations
models/         ActiveRecord models + form models
runtime/        runtime files (logs, cache, uploads) - gitignored
tests/          Codeception test suites
views/          view templates (kebab-case folders)
web/            document root
widgets/        AdminLTE menu / alert widgets
legacy/         original Yii 1 code base, kept for reference
```

## Installation

### 1. Clone & install dependencies

```bash
git clone <repo-url> crashfix
cd crashfix
composer install
```

### 2. Run the web installer

Point a browser at the application URL (e.g. `http://localhost/crashfix/web/`)
and the installer will redirect you to `/install/index`. The wizard walks
through:

1. **Welcome**
2. **Requirements** &mdash; PHP version, extensions, writable paths
3. **Database** &mdash; host, database name, user, password, table prefix
4. **Schema** &mdash; runs all pending migrations from `migrations/`
5. **Admin user** &mdash; creates the first administrator account
6. **Finish** &mdash; writes `config/installed.txt` and unlocks the app

The installer writes `config/user_params.ini` (DB credentials, table
prefix). It is idempotent: re-visiting `/install/index` after install
redirects to the homepage. To force a re-install, delete
`config/installed.txt` or pass `?force=1`.

### 3. (Optional) Run migrations from the CLI

If you prefer the console:

```bash
php yii migrate --interactive=0
```

The migration table defaults to `{{%migration}}` (i.e. honours the
configured table prefix).

## Configuration

Three files in `config/` drive runtime behaviour:

| File | Purpose | Source-controlled? |
|------|---------|--------------------|
| `web.php`         | Web application config (components, URL rules, etc.) | yes |
| `console.php`     | Console application config (migrations, etc.)        | yes |
| `db.php`          | Reads connection details out of `user_params.ini`    | yes |
| `user_params.ini` | DB credentials & table prefix written by installer   | **no** |
| `installed.txt`   | Marker file written when install finishes            | **no** |
| `params.php`      | Free-form parameter dictionary                       | yes |

The default `db.php` falls back to `mysql:host=127.0.0.1;dbname=crashfix`
with username `crashfix` if `user_params.ini` is missing.

## Development

### Tests

Codeception suites live under `tests/`.

```bash
vendor/bin/codecept run unit
vendor/bin/codecept run functional
```

### Daemon

The web app talks to the CrashFix daemon over TCP on `127.0.0.1:50`
through `app\components\Daemon`. Without the daemon running, anything
that calls `Yii::$app->daemon->*` will surface a connection error in the
admin panel; the rest of the UI continues to work.

## Side-by-side deployment with the legacy Yii1 app

If you have an existing Yii1 CrashFix install and want to run the
new UI on a separate subdomain alongside it (sharing the same MySQL
database and optionally the same on-disk file storage), see the
deployment guide:

- [`docs/deployment/README.md`](docs/deployment/README.md) &mdash;
  decision matrix + common prerequisites
- [Option A: Read-only shadow](docs/deployment/option-a-readonly-shadow.md)
- [Option B: Shared DB, separate storage](docs/deployment/option-b-shared-db.md)
- [Option C: Shared DB + Legacy storage adapter](docs/deployment/option-c-shared-storage.md)

The new app's
[`Storage`](components/Storage.php) /
[`LegacyStorage`](components/LegacyStorage.php) component pair lets
you switch between the new project-scoped file layout and the
legacy MD5-sharded layout with a single config change &mdash; no
code modification needed.

## Migration Status (Yii 1 &rarr; Yii 2)

See [`MIGRATION_WORKFLOW.md`](MIGRATION_WORKFLOW.md) for a detailed
breakdown of what has been ported, what is partial, what remains, plus
the team workflow phases.

High-level state:

| Layer        | Status        |
|--------------|---------------|
| Controllers  | ~90% (10/10 controllers, a few actions still partial) |
| Models       | ~95% (ActiveRecord + form models ported)              |
| Views        | ~70% (core CRUD done, several detail partials pending) |
| Components   | core 4/4 done (Daemon, BatchImporter, WebUser, MiscHelpers) |
| Migrations   | full schema available as proper Yii2 migrations        |
| Installer    | new web-based wizard, idempotent                       |

## License

Same license as the upstream CrashFix project. See `legacy/LICENSE` if
present.
