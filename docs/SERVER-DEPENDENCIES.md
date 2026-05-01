# Server dependencies and DEB install commands

These commands target the `php8-compat` branch of the legacy Yii1 CrashFix web app.
They assume Debian / Ubuntu and PHP 8.1+.

## Install OS packages

Apache + mod_php:

```bash
sudo apt-get update
sudo apt-get install -y \
  apache2 \
  libapache2-mod-php8.2 \
  mariadb-client \
  unzip \
  curl \
  ca-certificates \
  php8.2-cli \
  php8.2-mysql \
  php8.2-xml \
  php8.2-mbstring \
  php8.2-curl \
  php8.2-gd \
  php8.2-zip \
  php8.2-intl

sudo a2enmod rewrite
sudo systemctl reload apache2
```

Replace `8.2` with your installed PHP series (`8.1`, `8.3`, etc.) if needed.

nginx + PHP-FPM alternative:

```bash
sudo apt-get update
sudo apt-get install -y \
  nginx \
  php8.2-fpm \
  mariadb-client \
  unzip \
  curl \
  ca-certificates \
  php8.2-cli \
  php8.2-mysql \
  php8.2-xml \
  php8.2-mbstring \
  php8.2-curl \
  php8.2-gd \
  php8.2-zip \
  php8.2-intl

sudo systemctl enable --now nginx php8.2-fpm
```

## Install the generated DEB package

Copy the package from `dist/` to the server, then install it:

```bash
sudo apt-get update
sudo apt-get install -y ./crashfix-yii1-php8-compat_1.0.10_all.deb
```

If you use plain `dpkg`, repair missing dependencies afterwards:

```bash
sudo dpkg -i crashfix-yii1-php8-compat_1.0.10_all.deb
sudo apt-get -f install
```

The package installs the web app under:

```text
/usr/share/crashfix-yii1
```

Writable app storage is prepared under:

```text
/var/lib/crashfix/runtime
/var/lib/crashfix/data
```

and linked into:

```text
/usr/share/crashfix-yii1/protected/runtime
/usr/share/crashfix-yii1/protected/data
```

## Apache vhost example

```apache
<VirtualHost *:80>
    ServerName crashfix.example.com
    DocumentRoot /usr/share/crashfix-yii1

    <Directory /usr/share/crashfix-yii1>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/crashfix.error.log
    CustomLog ${APACHE_LOG_DIR}/crashfix.access.log combined
</VirtualHost>
```

Enable it:

```bash
sudo a2ensite crashfix
sudo systemctl reload apache2
```

## Application configuration

The installer / deployment still needs database settings in:

```text
/usr/share/crashfix-yii1/protected/config/user_params.ini
```

If you want the web installer to write that file, make the config directory writable by the web-server user during installation:

```bash
sudo chown -R www-data:www-data /usr/share/crashfix-yii1/protected/config
```

After configuration, lock it back down:

```bash
sudo chown -R root:root /usr/share/crashfix-yii1/protected/config
sudo chmod -R go-w /usr/share/crashfix-yii1/protected/config
```

## CrashFix daemon

The web app expects the CrashFix daemon (`crashfixd`) to be installed separately and reachable using the configured daemon host/port (commonly `127.0.0.1:50`). This DEB packages the PHP web UI only; it does not rebuild or replace `crashfixd`.
