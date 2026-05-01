#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
PKG_NAME="crashfix-yii1-php8-compat"
APP_DIR="/usr/share/crashfix-yii1"

VERSION="$(sed -n "s/.*'version'=>'\([^']*\)'.*/\1/p" "${ROOT_DIR}/protected/config/common.php" | head -n 1)"
if [ -z "${VERSION}" ]; then
  VERSION="1.0.0"
fi

ARCH="all"
BUILD_DIR="${TMPDIR:-/tmp}/${PKG_NAME}-deb-build-$$"
PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${VERSION}_${ARCH}"
APP_STAGE="${PKG_DIR}${APP_DIR}"

rm -rf "${BUILD_DIR}"
mkdir -p "${APP_STAGE}" "${PKG_DIR}/DEBIAN" "${DIST_DIR}"
trap 'rm -rf "${BUILD_DIR}"' EXIT

cd "${ROOT_DIR}"

# Package the Yii1 web app. Exclude generated packages/build output,
# runtime data, git metadata, and the Yii2 experiment under new/.
tar \
  --exclude='./.git' \
  --exclude='./build' \
  --exclude='./dist' \
  --exclude='./new' \
  --exclude='./stats' \
  --exclude='./protected/runtime' \
  --exclude='./protected/data' \
  --exclude='./protected/config/user_params.ini' \
  --exclude='./backup.sql' \
  -cf - . | tar -xf - -C "${APP_STAGE}"

cat > "${PKG_DIR}/DEBIAN/control" <<EOF_CONTROL
Package: ${PKG_NAME}
Version: ${VERSION}
Section: web
Priority: optional
Architecture: ${ARCH}
Maintainer: CrashFix Maintainers <admin@example.com>
Depends: apache2 | nginx, php-cli, php-mysql, php-xml, php-mbstring, php-curl, php-gd, php-zip, mariadb-client | default-mysql-client, unzip
Description: CrashFix legacy Yii1 web UI with PHP 8 compatibility fixes
 CrashFix is a web application for collecting and managing crash reports,
 debug symbols, and stack processing metadata. This package installs the
 legacy Yii1 PHP 8 compatible web UI; the crashfixd daemon is packaged and
 deployed separately.
EOF_CONTROL

cat > "${PKG_DIR}/DEBIAN/postinst" <<'EOF_POSTINST'
#!/bin/sh
set -e

APP_DIR="/usr/share/crashfix-yii1"
STATE_DIR="/var/lib/crashfix"
WEB_USER="www-data"
WEB_GROUP="www-data"

mkdir -p "${STATE_DIR}/runtime" "${STATE_DIR}/data"

if getent passwd "${WEB_USER}" >/dev/null 2>&1; then
  chown -R "${WEB_USER}:${WEB_GROUP}" "${STATE_DIR}" || true
fi

for name in runtime data; do
  target="${APP_DIR}/protected/${name}"
  source="${STATE_DIR}/${name}"
  if [ ! -e "${target}" ]; then
    ln -s "${source}" "${target}"
  fi
done

exit 0
EOF_POSTINST

chmod 0755 "${PKG_DIR}/DEBIAN/postinst"
python3 - "${APP_STAGE}" <<'PY_CHMOD'
import os
import sys

root = sys.argv[1]
for dirpath, dirnames, filenames in os.walk(root):
    os.chmod(dirpath, 0o755)
    for filename in filenames:
        os.chmod(os.path.join(dirpath, filename), 0o644)
PY_CHMOD
chmod 0755 "${APP_STAGE}/protected/yiic" || true
chmod 0755 "${APP_STAGE}/php_lint.sh" || true

dpkg-deb --build --root-owner-group "${PKG_DIR}" "${DIST_DIR}/${PKG_NAME}_${VERSION}_${ARCH}.deb"
echo "Built ${DIST_DIR}/${PKG_NAME}_${VERSION}_${ARCH}.deb"
