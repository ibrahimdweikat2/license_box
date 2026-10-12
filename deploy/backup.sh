#!/usr/bin/env bash
#
# Back up everything LicenseBox cannot recreate: the database, the RSA signing keys, the release
# files and .env. Losing the signing keys breaks every SDK you have shipped, so keep a copy off
# this server: set BACKUP_S3_URI (the instance needs an IAM role allowed to s3:PutObject there).
#
# Run by cron (see /etc/cron.d/<site>) or by hand:  sudo bash deploy/backup.sh
# Restore steps are in DEPLOYMENT.md.

set -Eeuo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/$(basename "$APP_DIR")}"
KEEP_DAYS="${KEEP_DAYS:-14}"
BACKUP_S3_URI="${BACKUP_S3_URI:-}"

[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }

env_value() { grep -E "^$1=" "$APP_DIR/.env" | head -n1 | cut -d= -f2- | tr -d '"' || true; }

stamp=$(date -u +%Y%m%d-%H%M%S)
target="$BACKUP_DIR/$(basename "$APP_DIR")-$stamp.tar.gz"
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

install -d -m 700 "$BACKUP_DIR"

# Dump with the app's own database account (works with MySQL and MariaDB, no root needed).
cat > "$work/my.cnf" <<EOF
[client]
host="$(env_value DB_HOST)"
port="$(env_value DB_PORT)"
user="$(env_value DB_USERNAME)"
password="$(env_value DB_PASSWORD)"
EOF
mysqldump --defaults-extra-file="$work/my.cnf" --single-transaction --quick --no-tablespaces \
    "$(env_value DB_DATABASE)" | gzip > "$work/database.sql.gz"
rm -f "$work/my.cnf"
cp "$APP_DIR/.env" "$work/.env"

# storage/app/private holds keys/ (signing keypair) and updates/ (release ZIP/SQL files).
# A custom LICENSEBOX_KEY_PATH outside it is included as well.
key_path=$(env_value LICENSEBOX_KEY_PATH)
paths=(-C "$APP_DIR" storage/app/private)
if [[ -n $key_path ]]; then paths+=(-C / "${key_path#/}"); fi

tar -czf "$work/files.tar.gz" "${paths[@]}"
tar -czf "$target" -C "$work" database.sql.gz files.tar.gz .env
chmod 600 "$target"
echo "$(date -u +%FT%TZ) backup written: $target ($(du -h "$target" | cut -f1))"

if [[ -n $BACKUP_S3_URI ]]; then
    aws s3 cp --only-show-errors "$target" "${BACKUP_S3_URI%/}/$(basename "$target")"
    echo "$(date -u +%FT%TZ) uploaded to ${BACKUP_S3_URI%/}/"
fi

find "$BACKUP_DIR" -name "$(basename "$APP_DIR")-*.tar.gz" -mtime +"$KEEP_DAYS" -delete
