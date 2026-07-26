#!/usr/bin/env sh
set -u

PROJECT_DIR=${VIDEOKUR_PROJECT_DIR:-/var/www/videokur}
UPDATE_DIR="$PROJECT_DIR/data/update"
REQUEST_FILE="$UPDATE_DIR/request.json"
PROCESSING_FILE="$UPDATE_DIR/request.processing.json"
STATUS_FILE="$UPDATE_DIR/status.json"
LOG_FILE=${VIDEOKUR_UPDATE_LOG:-/var/log/videokur-update.log}

mkdir -p "$UPDATE_DIR"

write_status() {
  state="$1"
  message="$2"
  version="$3"
  updated_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  temporary="$STATUS_FILE.tmp.$$"
  printf '{"state":"%s","message":"%s","version":"%s","updated_at":"%s"}\n' \
    "$state" "$message" "$version" "$updated_at" > "$temporary"
  chmod 0644 "$temporary"
  mv "$temporary" "$STATUS_FILE"
}

if [ ! -f "$REQUEST_FILE" ]; then
  exit 0
fi

mv "$REQUEST_FILE" "$PROCESSING_FILE"
current_version=$(git -c safe.directory="$PROJECT_DIR" -C "$PROJECT_DIR" rev-parse HEAD 2>/dev/null || printf 'unknown')
write_status "running" "Güncelleme indiriliyor ve Docker imajı hazırlanıyor." "$current_version"

{
  printf '\n[%s] Web paneli güncellemesi başlatıldı.\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  if "$PROJECT_DIR/deploy/update.sh"; then
    new_version=$(git -c safe.directory="$PROJECT_DIR" -C "$PROJECT_DIR" rev-parse HEAD 2>/dev/null || printf 'unknown')
    write_status "success" "Güncelleme tamamlandı. Uygulama yeni sürümle çalışıyor." "$new_version"
    rm -f "$PROCESSING_FILE"
    printf '[%s] Güncelleme tamamlandı: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$new_version"
  else
    exit_code=$?
    restored_version=$(git -c safe.directory="$PROJECT_DIR" -C "$PROJECT_DIR" rev-parse HEAD 2>/dev/null || printf 'unknown')
    write_status "failed" "Güncelleme başarısız oldu; önceki sürüm çalışmaya devam ediyor." "$restored_version"
    rm -f "$PROCESSING_FILE"
    printf '[%s] Güncelleme başarısız oldu (kod: %s).\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$exit_code"
  fi
} >> "$LOG_FILE" 2>&1

exit 0
