#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -d "$SCRIPT_DIR/backend" ]]; then
  ROOT_DIR="$SCRIPT_DIR"
elif [[ -d "$SCRIPT_DIR/../backend" ]]; then
  ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
elif [[ -d "$PWD/backend" ]]; then
  ROOT_DIR="$PWD"
else
  ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
fi

BACKEND_DIR="$ROOT_DIR/backend"
LOCK_FILE="$BACKEND_DIR/storage/framework/cache/data/shared-hosting-queue.lock"

if [[ ! -d "$BACKEND_DIR" ]]; then
  echo "[shared-hosting-queue-cron] backend directory not found: $BACKEND_DIR" >&2
  exit 1
fi

cd "$BACKEND_DIR"

mkdir -p storage/framework/cache/data

QUEUE_CMD=(php artisan queue:work --stop-when-empty --queue=default --tries=3 --timeout=120 --sleep=1 --max-time=50)

if command -v flock >/dev/null 2>&1; then
  flock -n "$LOCK_FILE" "${QUEUE_CMD[@]}"
else
  "${QUEUE_CMD[@]}"
fi