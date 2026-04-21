#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
workflow_file="$root_dir/.github/workflows/auto-deploy.yml"
run_file="$root_dir/run.sh"
production_doc="$root_dir/PRODUCTION-SETUP.md"

required_workflow_strings=(
  "mkdir -p /data/code/storage/logs"
  "mkdir -p /data/code/storage/framework/cache/data"
  "mkdir -p /data/code/storage/framework/sessions"
  "mkdir -p /data/code/storage/framework/views"
  "mkdir -p /data/code/storage/app/public"
  "mkdir -p /data/code/storage/app/private"
  "mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public storage/app/private bootstrap/cache"
  "php artisan config:clear"
  "php artisan view:clear"
  "php artisan config:cache"
  "php artisan route:cache"
  "php artisan view:cache"
  "php artisan migrate --force"
)

required_run_strings=(
  "ensure_laravel_runtime_dirs()"
  '"$BACKEND_DIR/storage/framework/views"'
  '"$BACKEND_DIR/bootstrap/cache"'
  "ensure_laravel_runtime_dirs"
)

required_doc_strings=(
  "mkdir -p /data/code/storage/framework/views"
  "php artisan config:clear"
  "php artisan view:clear"
  "php artisan view:cache"
  'host mount akan menimpa isi `storage` bawaan image'
)

assert_contains() {
  local file="$1"
  local expected="$2"

  if ! grep -Fq "$expected" "$file"; then
    echo "ERROR: required deploy guard snippet missing in $file"
    echo "Missing: $expected"
    exit 1
  fi
}

for file in "$workflow_file" "$run_file" "$production_doc"; do
  if [[ ! -f "$file" ]]; then
    echo "ERROR: required file not found: $file"
    exit 1
  fi
done

for expected in "${required_workflow_strings[@]}"; do
  assert_contains "$workflow_file" "$expected"
done

for expected in "${required_run_strings[@]}"; do
  assert_contains "$run_file" "$expected"
done

for expected in "${required_doc_strings[@]}"; do
  assert_contains "$production_doc" "$expected"
done

echo "check-deploy-runtime-guard: OK"