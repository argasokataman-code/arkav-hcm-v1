#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"

HOST="${SMOKE_HOST:-127.0.0.1}"
PORT="${SMOKE_PORT:-8030}"
HEALTH_PATH="${SMOKE_HEALTH_PATH:-/health}"
LOGIN_PATH="${SMOKE_LOGIN_PATH:-/v1/identity/auth/login}"
MAX_ATTEMPTS="${SMOKE_MAX_ATTEMPTS:-20}"
WAIT_SECONDS="${SMOKE_WAIT_SECONDS:-1}"
EMAIL="${SMOKE_EMAIL:-qa.login@example.com}"
PASSWORD="${SMOKE_PASSWORD:-StrongPass1}"
COMPANY_CODE="${SMOKE_COMPANY_CODE:-}"
LOG_FILE="${SMOKE_LOG_FILE:-/tmp/arcav-local-auth-smoke.log}"
BOOTSTRAP_DB="${SMOKE_BOOTSTRAP_DB:-1}"

if [[ ! -d "$BACKEND_DIR" || ! -f "$BACKEND_DIR/artisan" ]]; then
	echo "[local-auth-smoke] backend/artisan not found at $BACKEND_DIR" >&2
	exit 1
fi

cleanup() {
	if [[ -n "${SERVER_PID:-}" ]] && kill -0 "$SERVER_PID" >/dev/null 2>&1; then
		kill "$SERVER_PID" >/dev/null 2>&1 || true
		wait "$SERVER_PID" 2>/dev/null || true
	fi
}

trap cleanup EXIT INT TERM

cd "$BACKEND_DIR"

if [[ "$BOOTSTRAP_DB" == "1" ]]; then
	echo "[local-auth-smoke] preparing local auth fixtures..."
	php artisan migrate --force >/dev/null
	php artisan db:seed --class=DevelopmentSuperUserSeeder --force >/dev/null
fi

php artisan serve --host="$HOST" --port="$PORT" >"$LOG_FILE" 2>&1 &
SERVER_PID=$!

health_url="http://$HOST:$PORT$HEALTH_PATH"
login_url="http://$HOST:$PORT$LOGIN_PATH"

probe_ok=0
for _attempt in $(seq 1 "$MAX_ATTEMPTS"); do
	if ! kill -0 "$SERVER_PID" >/dev/null 2>&1; then
		echo "[local-auth-smoke] artisan serve exited before becoming ready." >&2
		cat "$LOG_FILE" >&2 || true
		exit 1
	fi

	if curl -fsS "$health_url" >/dev/null 2>&1; then
		probe_ok=1
		break
	fi

	sleep "$WAIT_SECONDS"
done

if [[ "$probe_ok" -ne 1 ]]; then
	echo "[local-auth-smoke] health probe failed: $health_url" >&2
	cat "$LOG_FILE" >&2 || true
	exit 1
fi

payload=$(printf '{"email":"%s","password":"%s"}' "$EMAIL" "$PASSWORD")
if [[ -n "$COMPANY_CODE" ]]; then
	payload=$(printf '{"email":"%s","password":"%s","companyCode":"%s"}' "$EMAIL" "$PASSWORD" "$COMPANY_CODE")
fi

response_file="$(mktemp /tmp/arcav-local-auth-response.XXXXXX.json)"
http_code=$(curl -sS -o "$response_file" -w '%{http_code}' \
	-X POST "$login_url" \
	-H 'Content-Type: application/json' \
	-d "$payload")

if [[ "$http_code" != "200" ]]; then
	echo "[local-auth-smoke] login failed with HTTP $http_code" >&2
	cat "$response_file" >&2 || true
	rm -f "$response_file"
	exit 1
fi

if ! grep -q '"success":true' "$response_file"; then
	echo "[local-auth-smoke] login response did not report success=true" >&2
	cat "$response_file" >&2 || true
	rm -f "$response_file"
	exit 1
fi

echo "[local-auth-smoke] health OK: $health_url"
echo "[local-auth-smoke] login OK: $EMAIL"
cat "$response_file"
rm -f "$response_file"