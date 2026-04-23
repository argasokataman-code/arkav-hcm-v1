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

if [[ ! -d "$BACKEND_DIR" ]]; then
  echo "[shared-hosting-deploy] backend directory not found: $BACKEND_DIR" >&2
  exit 1
fi

cd "$BACKEND_DIR"

if [[ ! -f "artisan" ]]; then
  echo "[shared-hosting-deploy] artisan not found in backend directory" >&2
  exit 1
fi

if [[ ! -f ".env" ]]; then
  echo "[shared-hosting-deploy] missing backend/.env" >&2
  echo "[shared-hosting-deploy] create it first from backend/env.txt and fill production values" >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "[shared-hosting-deploy] php command is required" >&2
  exit 1
fi

php -r 'if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "[shared-hosting-deploy] PHP 8.2+ is required. Current: ".PHP_VERSION."\n"); exit(1);}';

if [[ ! -d "vendor" ]]; then
  echo "[shared-hosting-deploy] missing backend/vendor. Build artifact locally first via scripts/shared-hosting-package-local.sh" >&2
  exit 1
fi

if [[ ! -f "public/build/.vite/manifest.json" ]] && [[ ! -f "public/build/manifest.json" ]]; then
  echo "[shared-hosting-deploy] missing Vite build output at backend/public/build. Build artifact locally first via scripts/shared-hosting-package-local.sh" >&2
  exit 1
fi

echo "[shared-hosting-deploy] preparing Laravel runtime directories..."
mkdir -p \
  storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/app/public \
  storage/app/private \
  bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

echo "[shared-hosting-deploy] applying database migrations..."
php artisan migrate --force

echo "[shared-hosting-deploy] refreshing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[shared-hosting-deploy] ensuring public storage symlink..."
php artisan storage:link || true

echo "[shared-hosting-deploy] completed. Web root must point to backend/public."