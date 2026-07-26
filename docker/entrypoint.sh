#!/usr/bin/env sh
set -eu

if [ -n "${TZ:-}" ] && [ -f "/usr/share/zoneinfo/${TZ}" ]; then
  ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime
  printf '%s\n' "$TZ" > /etc/timezone
fi

mkdir -p /app/data/jobs /app/data/.locks /app/data/templates /app/data/social_credentials /app/data/youtube_credentials /app/data/update /app/output /app/logs /app/assets

seed_file() {
  target="$1"
  seed="$2"
  if [ ! -f "$target" ]; then
    if [ -f "$seed" ]; then
      cp "$seed" "$target"
    else
      printf '%s\n' "$3" > "$target"
    fi
  fi
}

seed_file /app/data/production_queue.json /app/data-seed/production_queue.json '{"queue":[],"current_job":null,"settings":{"auto_start_next":true,"max_retries":3,"retry_delay_seconds":60},"stats":{"total_queued":0,"total_processed":0,"total_completed":0,"total_failed":0},"metadata":{"version":"1.0"}}'
seed_file /app/data/social_queue.json /app/data-seed/social_queue.json '{"queue":[],"current_job":null,"metadata":{"version":"1.0"}}'
seed_file /app/data/queues.json /app/data-seed/queues.json '{"queues":[]}'
seed_file /app/data/content_pool.json /app/data-seed/content_pool.json '{"content":[],"metadata":{"version":"1.0","total_items":0}}'
seed_file /app/data/content_sources.json /app/data-seed/content_sources.json '{"sources":[],"metadata":{}}'
seed_file /app/data/scripts.json /app/data-seed/scripts.json '{"scripts":[],"categories":[{"id":"genel","name":"genel","active":true}]}'
seed_file /app/data/scheduler_status.json /app/data-seed/scheduler_status.json '{"production":{"running":true},"social":{"running":true},"content":{"running":true}}'

if [ -d /app/data-seed/templates ]; then
  cp -n /app/data-seed/templates/* /app/data/templates/ 2>/dev/null || true
fi

chown -R www-data:www-data /app/data /app/output /app/logs /app/assets
chmod 770 /app/data /app/output /app/logs /app/assets

exec "$@"
