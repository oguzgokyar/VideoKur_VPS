#!/usr/bin/env sh
set -eu

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
BRANCH=${VIDEOKUR_BRANCH:-main}
BACKUP_DIR=${VIDEOKUR_BACKUP_DIR:-/var/backups/videokur}
LOCK_FILE=${VIDEOKUR_UPDATE_LOCK:-/tmp/videokur-update.lock}

cd "$PROJECT_DIR"

git_repo() {
  git -c safe.directory="$PROJECT_DIR" "$@"
}

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "Başka bir VideoKur güncellemesi çalışıyor."
  exit 1
fi

dirty_code=$(git_repo status --porcelain --untracked-files=no | grep -vE '^.. data/' || true)
if [ -n "$dirty_code" ]; then
  echo "Takip edilen kod dosyalarında yerel değişiklik var; güncelleme durduruldu."
  printf '%s\n' "$dirty_code"
  exit 1
fi

old_commit=$(git_repo rev-parse HEAD)
timestamp=$(date +%Y%m%d-%H%M%S)
mkdir -p "$BACKUP_DIR"
backup="$BACKUP_DIR/videokur-$timestamp.tar.gz"

backup_items=""
for item in data output logs .env; do
  if [ -e "$item" ]; then backup_items="$backup_items $item"; fi
done
if [ -n "$backup_items" ]; then
  # shellcheck disable=SC2086
  tar -czf "$backup" $backup_items
  echo "Yedek: $backup"
fi

# Older releases tracked runtime JSON files. The backup above is authoritative;
# clean only those legacy tracked files so the first fast-forward can proceed.
if git_repo ls-files data | grep -q .; then
  git_repo restore --source=HEAD --worktree -- data
fi

current_image=""
if docker inspect videokur >/dev/null 2>&1; then
  current_image=$(docker inspect --format '{{.Image}}' videokur)
  docker tag "$current_image" "videokur:$old_commit"
fi

git_repo fetch origin "$BRANCH"
target_commit=$(git_repo rev-parse "origin/$BRANCH")
git_repo merge --ff-only "$target_commit"

# A commit that removes formerly tracked runtime JSON files must not remove
# live data. Restore the pre-update runtime snapshot after the code update.
if [ -f "$backup" ]; then
  tar -xzf "$backup" -C "$PROJECT_DIR"
fi

APP_VERSION="$target_commit" docker compose build videokur
APP_VERSION="$target_commit" docker compose up -d --remove-orphans

attempt=0
while [ "$attempt" -lt 30 ]; do
  health=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' videokur 2>/dev/null || true)
  if [ "$health" = "healthy" ]; then
    printf '%s\n' "$target_commit" > .current-version
    if [ "$(id -u)" -eq 0 ] && [ -x "$PROJECT_DIR/deploy/install-update-service.sh" ]; then
      "$PROJECT_DIR/deploy/install-update-service.sh" || echo "Uyarı: Web güncelleme hizmeti kurulamadı."
    fi
    echo "VideoKur güncellendi: $target_commit"
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 2
done

echo "Yeni sürüm health check'i geçemedi; önceki sürüme dönülüyor."
docker compose logs --tail=100 videokur || true
APP_VERSION="$old_commit" docker compose up -d --no-build --remove-orphans || true
git_repo reset --hard "$old_commit"
if [ -f "$backup" ]; then tar -xzf "$backup" -C "$PROJECT_DIR"; fi
exit 1
