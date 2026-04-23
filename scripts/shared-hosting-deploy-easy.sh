#!/usr/bin/env bash

set -euo pipefail

# ------------------------------------------------------------
# Easy deploy script for standard shared hosting.
#
# Goal:
# - No composer/npm on server.
# - Use prebuilt artifact that already contains vendor + public/build.
# - Run only PHP artisan tasks on server.
# ------------------------------------------------------------

print_info() {
  echo "[INFO] $1"
}

print_ok() {
  echo "[OK]   $1"
}

print_err() {
  echo "[ERR]  $1" >&2
}

# Resolve app root as safely as possible.
# Works when script is placed in:
# 1) app root, or
# 2) app_root/scripts/
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -d "$SCRIPT_DIR/backend" ]]; then
  APP_ROOT="$SCRIPT_DIR"
elif [[ -d "$SCRIPT_DIR/../backend" ]]; then
  APP_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
elif [[ -d "$PWD/backend" ]]; then
  APP_ROOT="$PWD"
else
  print_err "Cannot find backend directory."
  print_err "Run this script from app root or place this script in app_root/scripts/."
  exit 1
fi

BACKEND_DIR="$APP_ROOT/backend"

print_info "App root: $APP_ROOT"
print_info "Backend : $BACKEND_DIR"

if [[ ! -f "$BACKEND_DIR/artisan" ]]; then
  print_err "File artisan not found in backend directory."
  exit 1
fi

if [[ ! -f "$BACKEND_DIR/.env" ]]; then
  print_err "Missing backend/.env"
  print_err "Create it first: cp backend/env.txt backend/.env and fill production values."
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  print_err "PHP CLI is not available. Ask hosting support to enable PHP CLI."
  exit 1
fi

php -r 'if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP 8.2+ required. Current: ".PHP_VERSION."\n"); exit(1);}';
print_ok "PHP version is compatible (8.2+)."

if [[ ! -f "$BACKEND_DIR/vendor/autoload.php" ]]; then
  print_err "Missing backend/vendor/autoload.php"
  print_err "Upload a fresh artifact built from local: scripts/shared-hosting-package-local.sh"
  exit 1
fi
print_ok "Vendor package detected."

if [[ ! -f "$BACKEND_DIR/public/build/.vite/manifest.json" ]] && [[ ! -f "$BACKEND_DIR/public/build/manifest.json" ]]; then
  print_err "Missing Vite build output (public/build/.vite/manifest.json)"
  print_err "Upload a fresh artifact built from local: scripts/shared-hosting-package-local.sh"
  exit 1
fi
print_ok "Vite build output detected."

cd "$BACKEND_DIR"

print_info "Preparing Laravel runtime directories..."
mkdir -p \
  storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/app/public \
  storage/app/private \
  bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
print_ok "Runtime directories are ready."

print_info "Running database migration..."
php artisan migrate --force
print_ok "Migration completed."

print_info "Refreshing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_ok "Cache refresh completed."

print_info "Ensuring storage symlink..."
php artisan storage:link || true
print_ok "Storage symlink checked."

echo
echo "Deploy completed successfully."
echo "Document root must point to: $BACKEND_DIR/public"
echo
echo "Recommended cron entries:"
echo "* * * * * cd $BACKEND_DIR && php artisan schedule:run >> /dev/null 2>&1"
echo "* * * * * cd $APP_ROOT && bash scripts/shared-hosting-queue-cron.sh >> /dev/null 2>&1"
