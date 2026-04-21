#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"

if [[ ! -d "$BACKEND_DIR" ]]; then
  echo "[run.sh] backend directory not found: $BACKEND_DIR" >&2
  exit 1
fi

if [[ ! -f "$BACKEND_DIR/artisan" ]]; then
  echo "[run.sh] artisan not found in backend directory." >&2
  exit 1
fi

if [[ ! -d "$FRONTEND_DIR" ]]; then
  echo "[run.sh] frontend directory not found: $FRONTEND_DIR" >&2
  exit 1
fi

if [[ ! -f "$BACKEND_DIR/.env" ]]; then
  echo "[run.sh] missing backend/.env. Run: cp backend/env.txt backend/.env && cd backend && php artisan key:generate --force" >&2
  exit 1
fi

if [[ ! -d "$BACKEND_DIR/vendor" ]]; then
  echo "[run.sh] missing backend/vendor. Run: cd backend && composer install --ignore-platform-req=php" >&2
  exit 1
fi

if [[ ! -f "$FRONTEND_DIR/server.js" ]]; then
  echo "[run.sh] missing frontend/server.js." >&2
  exit 1
fi

# Run pending migrations (safe - idempotent)
echo "[run.sh] Running pending migrations..."
cd "$BACKEND_DIR"
php artisan migrate --force || echo "[run.sh] Migrations already applied or errored"

# Ensure local/dev bootstrap accounts still exist after local DB resets/imports.
echo "[run.sh] Ensuring development super users are seeded..."
php artisan db:seed --class=DevelopmentSuperUserSeeder --force

if ! php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); exit((int) ! App\Models\User::query()->where("email", config("hcm.admin_email"))->exists());' >/dev/null; then
  echo "[run.sh] configured development super user is missing after seeding." >&2
  exit 1
fi

pids=()

is_port_in_use() {
  local port="$1"
  lsof -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
}

ensure_storage_symlink() {
  local expected_target="$BACKEND_DIR/storage/app/public"
  local current_target=""

  if [[ -L "$BACKEND_DIR/public/storage" ]]; then
    current_target="$(readlink "$BACKEND_DIR/public/storage")"
  fi

  if [[ "$current_target" != "$expected_target" ]]; then
    rm -f "$BACKEND_DIR/public/storage"
    ln -s "$expected_target" "$BACKEND_DIR/public/storage"
    echo "[run.sh] fixed public/storage symlink -> $expected_target"
  fi
}

cleanup() {
  echo
  echo "[run.sh] stopping services..."
  for pid in "${pids[@]:-}"; do
    if kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
    fi
  done
  wait || true
}

wait_for_health() {
  local url="$1"
  local attempts=20
  local i=1
  while [[ $i -le $attempts ]]; do
    if curl -sf "$url" >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
    i=$((i + 1))
  done
  return 1
}

trap cleanup INT TERM EXIT

cd "$BACKEND_DIR"

ensure_storage_symlink

BACKEND_PORT=8007
FRONTEND_PORT=5179

if is_port_in_use "$BACKEND_PORT"; then
  echo "[run.sh] required backend port $BACKEND_PORT is already in use. Stop the existing process first." >&2
  exit 1
fi

if is_port_in_use "$FRONTEND_PORT"; then
  echo "[run.sh] required frontend port $FRONTEND_PORT is already in use. Stop the existing process first." >&2
  exit 1
fi

(
  php -d error_reporting=8191 artisan serve --host=0.0.0.0 --port="$BACKEND_PORT" 2>&1 | sed -u 's/^/[backend] /'
) &
pids+=("$!")

(
  FRONTEND_PORT="$FRONTEND_PORT" BACKEND_HOST=127.0.0.1 BACKEND_PORT="$BACKEND_PORT" \
    node "$FRONTEND_DIR/server.js" 2>&1 | sed -u 's/^/[frontend] /'
) &
pids+=("$!")

echo "[run.sh] backend: http://0.0.0.0:$BACKEND_PORT"
echo "[run.sh] frontend: http://0.0.0.0:$FRONTEND_PORT"
echo "[run.sh] app url: http://0.0.0.0:$FRONTEND_PORT (Node frontend proxy)"
echo "[run.sh] backend url: http://0.0.0.0:$BACKEND_PORT"

if wait_for_health "http://127.0.0.1:$BACKEND_PORT/health"; then
  echo "[run.sh] backend health check: OK"
else
  echo "[run.sh] backend health check: FAILED (GET /health)" >&2
  exit 1
fi
echo "[run.sh] running in foreground. Press Ctrl+C to stop."

wait
