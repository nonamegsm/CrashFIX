#!/usr/bin/env bash
# php -l every .php file under protected/ to catch any syntax breakage
# the patcher might have introduced. Skips the framework itself
# (already proven good upstream) and *.php.bak files.
ROOT="$(cd "$(dirname "$0")" && pwd)"
ERR=0
COUNT=0
while IFS= read -r f; do
    COUNT=$((COUNT+1))
    if ! out=$(/mnt/c/xampp/php/php.exe -l "$f" 2>&1); then
        echo "FAIL: $f"
        echo "$out" | sed 's/^/    /'
        ERR=$((ERR+1))
    fi
done < <(find "$ROOT/protected" -path "$ROOT/protected/framework" -prune -o \
                                -path "$ROOT/protected/framework_old" -prune -o \
                                -name '*.php' -type f -print)
echo
echo "scanned: $COUNT files, errors: $ERR"
exit $ERR
