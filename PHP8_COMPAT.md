# PHP 8 compatibility notes

This branch makes the **legacy Yii1 CrashFix** install runnable on
modern PHP 8.x interpreters (tested on PHP 8.2.4; should also work
through PHP 8.4). Use it when your distribution upgrade has bumped
PHP past 7.4 and re-installing PHP 7.4 from a 3rd-party repo isn't an
option (e.g. Debian 12 → Sury bookworm doesn't ship PHP 7.4).

## What changed

### 1. Yii framework upgraded 1.1.21 → 1.1.32

`protected/framework/` was replaced wholesale with the latest
upstream Yii 1.1 release:

```
Yii 1.1.21  (Mar 2018, PHP 5.x / early 7.x baseline)
        ↓
Yii 1.1.32  (Dec 2025, PHP 8.4 supported)
```

The 1.1.32 framework includes every PHP 8.x compatibility fix that
upstream has accumulated: PHP 8 union/intersection types in action
declarations, deprecation fixes in `CCaptcha` / `MarkdownParser` /
`CLocale`, NULL-safe string handling in dozens of places, and the
HTMLPurifier 4.15 bundle that's PHP 8.1+ compatible. See the [1.1.32
CHANGELOG](https://raw.githubusercontent.com/yiisoft/yii/1.1.32/CHANGELOG)
for the full list.

The Yii team officially designates 1.1.x as **maintenance-only**, so
this is genuinely the last version that's going to be released — but
it works on every PHP from 5.1 through 8.4.

### 2. `#[\AllowDynamicProperties]` added to every app class

PHP 8.2 deprecated dynamic property assignment with a warning that
becomes a fatal error under strict error reporting. Yii1's
`CComponent::__set` machinery — which **every** ActiveRecord, model,
controller, widget, and component uses — relies on dynamic props.

The fix: each class that extends a Yii base class (`CActiveRecord`,
`CFormModel`, `CController`, `CWidget`, `CComponent`, `CPortlet`, …)
now carries the `#[\AllowDynamicProperties]` attribute, which is
PHP's officially blessed opt-out.

Patched files: 54 (24 models, 11 controllers, 8 components, 3
commands, 8 misc — see `php8_patch.ps1`).

### 3. ezcomponents Graph library hand-patched

The bundled ezcomponents bundle (used for the Digest charts and
Crash Report Uploads graph) had two PHP-8-incompatible patterns:

- **`function __set_state(array $properties)`** — PHP 8 requires
  `__set_state` to be static. Fixed in:
  - `Graph/src/structs/step.php`
  - `Graph/src/structs/coordinate.php`
  - `Graph/src/structs/context.php`

- **`function __autoload($className) { ... }`** — `__autoload`
  was deprecated in PHP 7.2 and **removed in PHP 8.0**. Replaced
  with `spl_autoload_register('ezcBase::autoload')` in 9 files
  (`Base/src/ezc_bootstrap.php` is the production-relevant one;
  the rest are documentation samples).

## Files NOT changed

- **No application logic was modified** beyond adding the attribute
  line. URLs, controller methods, model rules, view templates,
  database schema, daemon protocol — all identical to upstream.
- **No vendor source was touched** outside of the four ezcomponents
  fixes above; HTMLPurifier, jQuery, etc. all stay as Yii ships them.

## Tooling shipped on this branch

- `php8_patch.ps1` — applies `#[\AllowDynamicProperties]` to every
  class file under `protected/{models,controllers,components,commands,extensions}`.
  Idempotent — safe to re-run after editing.
- `php8_autoload_patch.ps1` — replaces `__autoload` with
  `spl_autoload_register` across the ezcomponents tree.
- `php_lint.ps1` — runs `php -l` over every `.php` under
  `protected/`, skipping the framework and vendor-test fixtures
  (which have their own pre-existing issues unrelated to CrashFix).
  Returns 0 if clean. Use it before every push.
- `php_lint.sh` — same, callable from WSL or Linux CI.

## Verifying after pulling this branch

On a host with PHP 8.x:

```bash
git pull
php protected/yiic.php migrate --interactive=0   # should succeed
php -r "
  define('YII_DEBUG', true);
  require 'protected/framework/yii.php';
  echo 'Yii ', Yii::getVersion(), ' on PHP ', PHP_VERSION, PHP_EOL;
"
```

Expected:
```
Yii 1.1.32 on PHP 8.2.4
```

Then point your webserver at `index.php` and the legacy UI should
load. If you see a 500, check `protected/runtime/application.log`
for the actual stack trace — most non-Yii issues at this point are
configuration drift (missing PHP extensions, MySQL credentials,
file-permission changes) rather than language version.

## What this branch does NOT do

- **It does not modernize the UI.** The legacy Yii1 templates,
  CSS, and JavaScript are unchanged. For a modern UI on top of the
  same data, see the [Yii2 port branch](../../tree/yii2-port)
  in the sister CrashFIX repo.
- **It does not touch the database schema.** The same `tbl_*`
  tables work as before.
- **It does not address `mysql_*` deprecations** — there aren't any
  in CrashFix; the legacy code uses Yii's PDO wrappers throughout.
- **It does not patch the Yii1 framework's source.** All
  framework-side fixes come from upstream 1.1.32; we don't carry
  custom patches.

## Maintaining

When upstream Yii releases a new 1.1.x version, swap
`protected/framework/` again:

```bash
git clone --depth=1 --branch 1.1.<NN> https://github.com/yiisoft/yii.git /tmp/yii
rm -rf protected/framework
cp -r /tmp/yii/framework protected/framework
git add protected/framework
git commit -m "Yii framework: 1.1.32 -> 1.1.<NN>"
```

Re-run `php_lint.ps1` (or `php_lint.sh`) and you're done. The
`#[\AllowDynamicProperties]` attribute on app classes is independent
of the framework version and stays valid.

## Related

- [Yii 1.1 maintenance announcement](https://www.yiiframework.com/news/710/yii-1-1-31-is-released)
- [PHP 8.2 dynamic-properties deprecation](https://wiki.php.net/rfc/deprecate_dynamic_properties)
- [Yii2 port (sibling repo)](https://github.com/nonamegsm/CrashFIX/tree/yii2-port)
- [CrashRPT.SVC daemon (PHP-version-independent)](https://github.com/nonamegsm/CrashRPT.SVC)
