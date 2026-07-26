#!/usr/bin/env sh
set -eu

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$PROJECT_DIR"

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  echo "Docker Engine ve Docker Compose plugin kurulmalı."
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

if grep -q '^APP_PORT=' .env; then
  sed -i 's/^APP_PORT=.*/APP_PORT=80/' .env
else
  printf '\nAPP_PORT=80\n' >> .env
fi
if grep -q '^APP_ENV=' .env; then
  sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
else
  printf 'APP_ENV=production\n' >> .env
fi

native_services="videokur-production videokur-social videokur-content nginx php8.3-fpm"
for service in $native_services; do
  systemctl stop "$service" 2>/dev/null || true
done

if "$PROJECT_DIR/deploy/update.sh"; then
  for service in videokur-production videokur-social videokur-content; do
    systemctl disable "$service" 2>/dev/null || true
  done
  echo "Docker geçişi başarılı."
  exit 0
fi

echo "Docker geçişi başarısız; native servisler geri başlatılıyor."
docker compose down 2>/dev/null || true
for service in php8.3-fpm nginx videokur-production videokur-social videokur-content; do
  systemctl start "$service" 2>/dev/null || true
done
exit 1
