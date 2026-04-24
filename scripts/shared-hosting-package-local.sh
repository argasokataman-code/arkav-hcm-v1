#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
OUTPUT_DIR="$ROOT_DIR/release/shared-hosting"
PRUNE_SCRIPT="$ROOT_DIR/scripts/prune-shared-hosting-artifacts.sh"
ARTIFACT_KEEP_COUNT="${SHARED_HOSTING_ARTIFACT_KEEP_COUNT:-5}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
STAGING_DIR="$OUTPUT_DIR/staging-$TIMESTAMP"
ARTIFACT="$OUTPUT_DIR/shared-hosting-artifact-$TIMESTAMP.tar.gz"
GIT_HEAD="$(git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || echo UNKNOWN)"
GIT_BRANCH="$(git -C "$ROOT_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo UNKNOWN)"

if [[ ! -d "$BACKEND_DIR" ]]; then
  echo "[shared-hosting-package-local] backend directory not found: $BACKEND_DIR" >&2
  exit 1
fi

if [[ ! -f "$BACKEND_DIR/artisan" ]]; then
  echo "[shared-hosting-package-local] artisan not found in backend directory" >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "[shared-hosting-package-local] php command is required" >&2
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "[shared-hosting-package-local] composer command is required on local machine" >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "[shared-hosting-package-local] npm command is required on local machine" >&2
  exit 1
fi

php -r 'if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "[shared-hosting-package-local] PHP 8.2+ is required. Current: ".PHP_VERSION."\n"); exit(1);}';

mkdir -p "$OUTPUT_DIR"
rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR/backend"

cd "$BACKEND_DIR"

echo "[shared-hosting-package-local] installing PHP dependencies (production mode)..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [[ ! -f "vendor/autoload.php" ]]; then
  echo "[shared-hosting-package-local] composer install finished but backend/vendor/autoload.php is missing" >&2
  exit 1
fi

echo "[shared-hosting-package-local] installing JS dependencies and building assets..."
if [[ -f "package-lock.json" ]]; then
  npm ci
else
  npm install
fi
npm run build

if [[ ! -f "public/build/.vite/manifest.json" ]] && [[ ! -f "public/build/manifest.json" ]]; then
  echo "[shared-hosting-package-local] vite build finished but backend/public/build/.vite/manifest.json is missing" >&2
  exit 1
fi

echo "[shared-hosting-package-local] preparing Laravel runtime directories..."
mkdir -p \
  storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/app/public \
  storage/app/private \
  bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

cd "$ROOT_DIR"

echo "[shared-hosting-package-local] copying deployable files to staging..."
rsync -a \
  --exclude='.env' \
  --exclude='env.txt' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='playwright-report' \
  --exclude='test-results' \
  --exclude='.playwright' \
  --exclude='coverage' \
  --exclude='.phpunit.cache' \
  --exclude='.phpunit.result.cache' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='storage/app/public/*' \
  --exclude='storage/app/private/*' \
  --exclude='cookies.txt' \
  --exclude='schema.sql' \
  --exclude='schema_for_dbeaver.sql' \
  --exclude='full_backup.sql' \
  --exclude='test-output.log' \
  --exclude='*.bak' \
  --exclude='test-*.js' \
  --exclude='test_*.php' \
  --exclude='debug-*.js' \
  --exclude='repomix-*.xml' \
  --exclude='SUPERADMIN_PERMISSION_AUDIT.js' \
  --exclude='verify-comprehensive-permissions.js' \
  backend/ "$STAGING_DIR/backend/"

if [[ ! -f "$STAGING_DIR/backend/vendor/autoload.php" ]]; then
  echo "[shared-hosting-package-local] staging package is missing backend/vendor/autoload.php" >&2
  exit 1
fi

if [[ ! -f "$STAGING_DIR/backend/public/build/.vite/manifest.json" ]] && [[ ! -f "$STAGING_DIR/backend/public/build/manifest.json" ]]; then
  echo "[shared-hosting-package-local] staging package is missing backend/public/build/.vite/manifest.json" >&2
  exit 1
fi

mkdir -p "$STAGING_DIR/scripts"
cp "$ROOT_DIR/scripts/shared-hosting-deploy.sh" "$STAGING_DIR/scripts/shared-hosting-deploy.sh"
cp "$ROOT_DIR/scripts/shared-hosting-deploy-easy.sh" "$STAGING_DIR/scripts/shared-hosting-deploy-easy.sh"
cp "$ROOT_DIR/scripts/shared-hosting-queue-cron.sh" "$STAGING_DIR/scripts/shared-hosting-queue-cron.sh"

cat > "$STAGING_DIR/RELEASE-METADATA.txt" <<EOF
git_head=$GIT_HEAD
git_branch=$GIT_BRANCH
packaged_at=$TIMESTAMP
artifact_name=$(basename "$ARTIFACT")
EOF

cp "$STAGING_DIR/RELEASE-METADATA.txt" "$STAGING_DIR/backend/RELEASE-METADATA.txt"

# Ship OpenAPI spec for Swagger endpoint on shared-hosting runtime.
if [[ -f "$ROOT_DIR/docs/api/openapi.yaml" ]]; then
  mkdir -p "$STAGING_DIR/docs/api"
  cp "$ROOT_DIR/docs/api/openapi.yaml" "$STAGING_DIR/docs/api/openapi.yaml"
fi

cat > "$STAGING_DIR/DEPLOY-README.txt" << 'EOF'
1) Upload and extract this artifact to your hosting directory.
2) Ensure backend/.env exists on server (do not overwrite it with template).
3) Run: bash scripts/shared-hosting-deploy-easy.sh
4) Configure cron:
   * * * * * cd /path/to/app/backend && php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd /path/to/app && bash scripts/shared-hosting-queue-cron.sh >> /dev/null 2>&1
EOF

chmod +x \
  "$STAGING_DIR/scripts/shared-hosting-deploy.sh" \
  "$STAGING_DIR/scripts/shared-hosting-deploy-easy.sh" \
  "$STAGING_DIR/scripts/shared-hosting-queue-cron.sh"

echo "[shared-hosting-package-local] creating artifact: $ARTIFACT"
tar -C "$STAGING_DIR" -czf "$ARTIFACT" .

rm -rf "$STAGING_DIR"

if [[ -x "$PRUNE_SCRIPT" ]]; then
  echo "[shared-hosting-package-local] pruning old artifacts (keep=$ARTIFACT_KEEP_COUNT)..."
  "$PRUNE_SCRIPT" "$ARTIFACT_KEEP_COUNT"
fi

echo "[shared-hosting-package-local] done"
echo "[shared-hosting-package-local] artifact: $ARTIFACT"
