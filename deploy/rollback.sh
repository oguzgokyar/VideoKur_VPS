#!/usr/bin/env sh
set -eu
if [ "$#" -ne 1 ]; then
  echo "Kullanım: sudo ./deploy/rollback.sh <commit-sha>"
  exit 1
fi
PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
commit=$1
cd "$PROJECT_DIR"
if ! docker image inspect "videokur:$commit" >/dev/null 2>&1; then
  echo "Image bulunamadı: videokur:$commit"
  exit 1
fi
git cat-file -e "$commit^{commit}"
git reset --hard "$commit"
APP_VERSION="$commit" docker compose up -d --no-build --remove-orphans
printf '%s\n' "$commit" > .current-version
echo "VideoKur geri alındı: $commit"
