#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
workflow_file=""
if [[ -f "$root_dir/.github/workflows/auto-deploy.yml" ]]; then
  workflow_file="$root_dir/.github/workflows/auto-deploy.yml"
elif [[ -f "$root_dir/.github/workflows/shared-hosting-deploy.yml" ]]; then
  workflow_file="$root_dir/.github/workflows/shared-hosting-deploy.yml"
fi
run_file="$root_dir/run.sh"
production_doc="$root_dir/PRODUCTION-SETUP.md"
dockerignore_file="$root_dir/.dockerignore"

required_workflow_strings=()
if [[ "$workflow_file" == *"auto-deploy.yml" ]]; then
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
elif [[ "$workflow_file" == *"shared-hosting-deploy.yml" ]]; then
  required_workflow_strings=(
    "scripts/check-shared-hosting-artifact-sync.sh"
    "bash scripts/shared-hosting-deploy-easy.sh"
  )
fi

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

required_dockerignore_strings=(
  "!docs/"
  "!docs/api/"
  "!docs/api/openapi.yaml"
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

if [[ -z "$workflow_file" ]]; then
  echo "ERROR: required workflow file not found (.github/workflows/auto-deploy.yml or .github/workflows/shared-hosting-deploy.yml)"
  exit 1
fi

for file in "$workflow_file" "$run_file" "$production_doc" "$dockerignore_file"; do
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

for expected in "${required_dockerignore_strings[@]}"; do
  assert_contains "$dockerignore_file" "$expected"
done

echo "check-deploy-runtime-guard: OK"