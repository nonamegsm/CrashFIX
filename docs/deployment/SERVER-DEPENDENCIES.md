# Server dependencies (Yii2 CrashFix web app)

Commands below target **Debian / Ubuntu** with **PHP 8.1+** (recommended for PHP 8–compatible installs). Adjust package names if you use Remi/Sury or another PHP repo.

## 1. OS packages (Apache + mod_php)

```bash
sudo apt-get update
sudo apt-get install -y \
  apache2 \
  libapache2-mod-php8.2 \
  mariadb-client \
  unzip \
  curl \
  git

sudo apt-get install -y \
  php8.2-cli \
  php8.2-mysql \
  php8.2-xml \
  php8.2-mbstring \
  php8.2-curl \
  php8.2-intl \
  php8.2-gd \
  php8.2-zip \
  php8.2-bcmath

sudo a2enmod rewrite
sudo systemctl reload apache2
```

Replace `8.2` with your distro’s PHP series (`8.1`, `8.3`, …) if needed.

### nginx + PHP-FPM (alternative)

```bash
sudo apt-get install -y nginx php8.2-fpm mariadb-client unzip curl git \
  php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl \
  php8.2-intl php8.2-gd php8.2-zip php8.2-bcmath
sudo systemctl enable --now php8.2-fpm nginx
```

Point `fastcgi_pass` at the matching PHP-FPM socket and set `root` to this app’s `web/` directory.

## 2. Composer (PHP dependency manager)

Install Composer system-wide (official installer):

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version
```

Or use distro package: `sudo apt-get install -y composer` (often an older Composer; prefer the official binary for reproducible builds).

## 3. Application PHP packages (“create vendor”)

From the repository root (same directory as `composer.json`):

**Production (no dev tools, optimized autoloader):**

```bash
cd /var/www/crashfix-new   # example deploy path
composer install --no-dev --optimize-autoloader --no-interaction
```

**Development / CI (includes Codeception, Gii, Debug toolbar):**

```bash
composer install --optimize-autoloader --no-interaction
```

**Validate `composer.json` before deploy:**

```bash
composer validate --no-check-publish
```

## 4. Writable paths

Ensure the web server user can write:

```bash
sudo chown -R www-data:www-data runtime web/assets
sudo chmod -R ug+rwx runtime web/assets
```

(Use `nginx`/`apache` user names per your distro.)

## 5. Database

MySQL 5.7+ or MariaDB 10.2+ (same as legacy CrashFix). Create database and user; credentials go in `config/user_params.ini` (installer) or your deployment secrets.

## 6. CrashFix daemon (`crashfixd`)

The web UI expects the processing daemon on **`127.0.0.1:50`** by default. Install it from your CrashFix/CrashRPT distribution or build; it is **not** installed by Composer. See main `README.md` and `docs/deployment/README.md`.

## 7. Optional: offline / air-gapped bundle

On a machine with network access, vendor can be vendored into the tree:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

Then copy the whole project (including `vendor/`) to the server. Do **not** run `composer install` on the server if `vendor/` is already complete and `composer.lock` is unchanged.

## See also

- [README.md](../../README.md) — installer, migrations, tests  
- [docs/deployment/README.md](README.md) — side-by-side deployment with legacy Yii1  
