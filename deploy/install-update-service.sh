#!/usr/bin/env sh
set -eu

if [ "$(id -u)" -ne 0 ]; then
  echo "Bu kurulum root yetkisi gerektirir: sudo ./deploy/install-update-service.sh"
  exit 1
fi

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
UNIT_DIR=/etc/systemd/system

install -m 0644 "$PROJECT_DIR/deploy/systemd/videokur-update.service" "$UNIT_DIR/videokur-update.service"
install -m 0644 "$PROJECT_DIR/deploy/systemd/videokur-update.path" "$UNIT_DIR/videokur-update.path"

mkdir -p "$PROJECT_DIR/data/update"
chown www-data:www-data "$PROJECT_DIR/data/update"
chmod 0770 "$PROJECT_DIR/data/update"

systemctl daemon-reload
systemctl enable --now videokur-update.path

echo "VideoKur web güncelleme hizmeti etkin."
