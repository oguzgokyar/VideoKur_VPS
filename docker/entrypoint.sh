#!/usr/bin/env sh
set -eu

mkdir -p /app/data/jobs /app/data/.locks /app/output /app/logs

if [ ! -f /app/data/production_queue.json ]; then
  printf '{"queue":[],"current_job":null,"settings":{"auto_start_next":true,"max_retries":3,"retry_delay_seconds":60},"stats":{"total_queued":0,"total_processed":0,"total_completed":0,"total_failed":0},"metadata":{"version":"1.0"}}\n' > /app/data/production_queue.json
fi

if [ ! -f /app/data/social_queue.json ]; then
  printf '{"queue":[],"current_job":null,"metadata":{"version":"1.0"}}\n' > /app/data/social_queue.json
fi

if [ ! -f /app/data/queues.json ]; then
  printf '{"queues":[]}\n' > /app/data/queues.json
fi

if [ ! -f /app/data/content_pool.json ]; then
  printf '{"content":[],"metadata":{"version":"1.0","total_items":0}}\n' > /app/data/content_pool.json
fi

exec "$@"
