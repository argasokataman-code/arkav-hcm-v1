#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

SSH_PORT="${STAGING_SSH_PORT:-22}"
SSH_USER="${STAGING_SSH_USER:-}"
SSH_HOST="${STAGING_SSH_HOST:-}"
REMOTE_APP_DIR="${STAGING_APP_DIR:-}"

usage() {
  cat <<'USAGE'
Usage:
  bash scripts/compare-local-staging.sh --user <ssh_user> --host <ssh_host> --app-dir <remote_app_dir> [--port <ssh_port>]

Examples:
  bash scripts/compare-local-staging.sh --user jogn3455 --host jogjatourdrive.com --port 2223 --app-dir /home/jogn3455/public_html/hr.jogjatourdrive.com

Environment variable alternatives:
  STAGING_SSH_USER, STAGING_SSH_HOST, STAGING_SSH_PORT, STAGING_APP_DIR
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --user)
      SSH_USER="$2"
      shift 2
      ;;
    --host)
      SSH_HOST="$2"
      shift 2
      ;;
    --port)
      SSH_PORT="$2"
      shift 2
      ;;
    --app-dir)
      REMOTE_APP_DIR="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$SSH_USER" || -z "$SSH_HOST" || -z "$REMOTE_APP_DIR" ]]; then
  echo "Missing required connection arguments." >&2
  usage
  exit 1
fi

hash_file() {
  local path="$1"
  if [[ ! -f "$path" ]]; then
    echo "MISSING"
    return 0
  fi

  if command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$path" | awk '{print $1}'
    return 0
  fi

  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$path" | awk '{print $1}'
    return 0
  fi

  echo "NO_HASH_TOOL"
}

hash_text() {
  if command -v shasum >/dev/null 2>&1; then
    shasum -a 256 | awk '{print $1}'
    return 0
  fi

  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum | awk '{print $1}'
    return 0
  fi

  echo "NO_HASH_TOOL"
}

LOCAL_TMP="$(mktemp)"
REMOTE_TMP="$(mktemp)"

cleanup() {
  rm -f "$LOCAL_TMP" "$REMOTE_TMP"
}
trap cleanup EXIT

LOCAL_MANIFEST_PATH=""
if [[ -f "$ROOT_DIR/backend/public/build/.vite/manifest.json" ]]; then
  LOCAL_MANIFEST_PATH="$ROOT_DIR/backend/public/build/.vite/manifest.json"
elif [[ -f "$ROOT_DIR/backend/public/build/manifest.json" ]]; then
  LOCAL_MANIFEST_PATH="$ROOT_DIR/backend/public/build/manifest.json"
fi

LOCAL_RELEASE_METADATA_PATH=""
if [[ -f "$ROOT_DIR/backend/RELEASE-METADATA.txt" ]]; then
  LOCAL_RELEASE_METADATA_PATH="$ROOT_DIR/backend/RELEASE-METADATA.txt"
elif [[ -f "$ROOT_DIR/RELEASE-METADATA.txt" ]]; then
  LOCAL_RELEASE_METADATA_PATH="$ROOT_DIR/RELEASE-METADATA.txt"
fi

LOCAL_ROUTE_HASH="N/A"
if [[ -f "$ROOT_DIR/backend/artisan" ]]; then
  LOCAL_ROUTE_HASH="$(cd "$ROOT_DIR/backend" && (php artisan route:list --json 2>/dev/null || true) | hash_text)"
fi

{
  echo "APP_DIR=$ROOT_DIR"
  echo "GIT_HEAD=$(git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || echo N/A)"
  echo "GIT_BRANCH=$(git -C "$ROOT_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo N/A)"
  echo "PHP_VERSION=$(php -v 2>/dev/null | head -n 1 | tr -s ' ')"
  echo "ARTISAN_VERSION=$(cd "$ROOT_DIR/backend" && php artisan --version 2>/dev/null || echo N/A)"
  echo "MIGRATION_FILES=$(find "$ROOT_DIR/backend/database/migrations" -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "OPENAPI_SHA256=$(hash_file "$ROOT_DIR/docs/api/openapi.yaml")"
  echo "SYNC_VIEW_SHA256=$(hash_file "$ROOT_DIR/backend/resources/views/locations/partials/sync-controls.blade.php")"
  echo "WILAYAH_CONTROLLER_SHA256=$(hash_file "$ROOT_DIR/backend/app/Http/Controllers/Api/WilayahLookupController.php")"
  echo "RELEASE_METADATA_SHA256=$(hash_file "$LOCAL_RELEASE_METADATA_PATH")"
  if [[ -n "$LOCAL_RELEASE_METADATA_PATH" ]]; then
    echo "RELEASE_METADATA_GIT_HEAD=$(grep -E '^git_head=' "$LOCAL_RELEASE_METADATA_PATH" | head -n 1 | cut -d'=' -f2-)"
  else
    echo "RELEASE_METADATA_GIT_HEAD=MISSING"
  fi
  if [[ -n "$LOCAL_MANIFEST_PATH" ]]; then
    echo "VITE_MANIFEST_SHA256=$(hash_file "$LOCAL_MANIFEST_PATH")"
  else
    echo "VITE_MANIFEST_SHA256=MISSING"
  fi
  echo "ROUTES_JSON_SHA256=$LOCAL_ROUTE_HASH"
} > "$LOCAL_TMP"

ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "bash -s" -- "$REMOTE_APP_DIR" > "$REMOTE_TMP" <<'REMOTE_SCRIPT'
set -euo pipefail

APP_DIR="$1"
BACKEND_DIR="$APP_DIR/backend"

hash_file() {
  local path="$1"
  if [[ ! -f "$path" ]]; then
    echo "MISSING"
    return 0
  fi

  if command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$path" | awk '{print $1}'
    return 0
  fi

  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$path" | awk '{print $1}'
    return 0
  fi

  echo "NO_HASH_TOOL"
}

hash_text() {
  if command -v shasum >/dev/null 2>&1; then
    shasum -a 256 | awk '{print $1}'
    return 0
  fi

  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum | awk '{print $1}'
    return 0
  fi

  echo "NO_HASH_TOOL"
}

MANIFEST_PATH=""
if [[ -f "$BACKEND_DIR/public/build/.vite/manifest.json" ]]; then
  MANIFEST_PATH="$BACKEND_DIR/public/build/.vite/manifest.json"
elif [[ -f "$BACKEND_DIR/public/build/manifest.json" ]]; then
  MANIFEST_PATH="$BACKEND_DIR/public/build/manifest.json"
fi

RELEASE_METADATA_PATH=""
if [[ -f "$BACKEND_DIR/RELEASE-METADATA.txt" ]]; then
  RELEASE_METADATA_PATH="$BACKEND_DIR/RELEASE-METADATA.txt"
elif [[ -f "$APP_DIR/RELEASE-METADATA.txt" ]]; then
  RELEASE_METADATA_PATH="$APP_DIR/RELEASE-METADATA.txt"
fi

if [[ -d "$APP_DIR/.git" ]]; then
  REMOTE_HEAD="$(git -C "$APP_DIR" rev-parse HEAD 2>/dev/null || echo N/A)"
  REMOTE_BRANCH="$(git -C "$APP_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo N/A)"
elif [[ -d "$BACKEND_DIR/.git" ]]; then
  REMOTE_HEAD="$(git -C "$BACKEND_DIR" rev-parse HEAD 2>/dev/null || echo N/A)"
  REMOTE_BRANCH="$(git -C "$BACKEND_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo N/A)"
else
  REMOTE_HEAD="NO_GIT"
  REMOTE_BRANCH="NO_GIT"
fi

ROUTES_HASH="N/A"
if [[ -f "$BACKEND_DIR/artisan" ]]; then
  ROUTES_HASH="$(cd "$BACKEND_DIR" && (php artisan route:list --json 2>/dev/null || true) | hash_text)"
fi

echo "APP_DIR=$APP_DIR"
echo "GIT_HEAD=$REMOTE_HEAD"
echo "GIT_BRANCH=$REMOTE_BRANCH"
echo "PHP_VERSION=$(php -v 2>/dev/null | head -n 1 | tr -s ' ')"
echo "ARTISAN_VERSION=$(cd "$BACKEND_DIR" && php artisan --version 2>/dev/null || echo N/A)"
echo "MIGRATION_FILES=$(find "$BACKEND_DIR/database/migrations" -type f 2>/dev/null | wc -l | tr -d ' ')"
echo "OPENAPI_SHA256=$(hash_file "$APP_DIR/docs/api/openapi.yaml")"
echo "SYNC_VIEW_SHA256=$(hash_file "$BACKEND_DIR/resources/views/locations/partials/sync-controls.blade.php")"
echo "WILAYAH_CONTROLLER_SHA256=$(hash_file "$BACKEND_DIR/app/Http/Controllers/Api/WilayahLookupController.php")"
echo "RELEASE_METADATA_SHA256=$(hash_file "$RELEASE_METADATA_PATH")"
if [[ -n "$RELEASE_METADATA_PATH" ]]; then
  echo "RELEASE_METADATA_GIT_HEAD=$(grep -E '^git_head=' "$RELEASE_METADATA_PATH" | head -n 1 | cut -d'=' -f2-)"
else
  echo "RELEASE_METADATA_GIT_HEAD=MISSING"
fi
if [[ -n "$MANIFEST_PATH" ]]; then
  echo "VITE_MANIFEST_SHA256=$(hash_file "$MANIFEST_PATH")"
else
  echo "VITE_MANIFEST_SHA256=MISSING"
fi
echo "ROUTES_JSON_SHA256=$ROUTES_HASH"
REMOTE_SCRIPT

read_kv() {
  local file="$1"
  local key="$2"
  grep -E "^${key}=" "$file" | head -n 1 | cut -d'=' -f2-
}

compare_key() {
  local key="$1"
  local local_value remote_value status
  local_value="$(read_kv "$LOCAL_TMP" "$key")"
  remote_value="$(read_kv "$REMOTE_TMP" "$key")"

  if [[ -z "$local_value" ]]; then
    local_value="N/A"
  fi
  if [[ -z "$remote_value" ]]; then
    remote_value="N/A"
  fi

  if [[ "$local_value" == "$remote_value" ]]; then
    status="MATCH"
  else
    status="DIFF"
  fi

  printf "%-28s | %-5s | local=%s | staging=%s\n" "$key" "$status" "$local_value" "$remote_value"
}

echo "=== Local vs Staging Parity Report ==="
echo "local   : $(read_kv "$LOCAL_TMP" "APP_DIR")"
echo "staging : $(read_kv "$REMOTE_TMP" "APP_DIR")"
echo

compare_key "GIT_HEAD"
compare_key "GIT_BRANCH"
compare_key "PHP_VERSION"
compare_key "ARTISAN_VERSION"
compare_key "MIGRATION_FILES"
compare_key "OPENAPI_SHA256"
compare_key "VITE_MANIFEST_SHA256"
compare_key "ROUTES_JSON_SHA256"
compare_key "SYNC_VIEW_SHA256"
compare_key "WILAYAH_CONTROLLER_SHA256"
compare_key "RELEASE_METADATA_SHA256"
compare_key "RELEASE_METADATA_GIT_HEAD"

echo
echo "Tip: Jika GIT_HEAD sama tapi hash file beda, biasanya deploy manual/scp overwrite terjadi di staging." 