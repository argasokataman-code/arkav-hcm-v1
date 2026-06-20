#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
BACKEND_ENV="${REPO_ROOT}/backend/.env"

if [[ -f "${BACKEND_ENV}" ]]; then
  set -a
  source "${BACKEND_ENV}"
  set +a
fi

export MYSQL_HOST="${DB_HOST:-127.0.0.1}"
export MYSQL_PORT="${DB_PORT:-3306}"
export MYSQL_USER="${DB_USERNAME:-root}"
export MYSQL_PASSWORD="${DB_PASSWORD:-}"
export MYSQL_DATABASE="${DB_DATABASE:-}"
export MYSQL_SSL="${MYSQL_SSL:-false}"

export DB_HOST="${MYSQL_HOST}"
export DB_PORT="${MYSQL_PORT}"
export DB_USER="${MYSQL_USER}"
export DB_PASSWORD="${MYSQL_PASSWORD}"
export DB_DATABASE="${MYSQL_DATABASE}"
export DATABASE_URL="${DATABASE_URL:-mysql://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}}"

if [[ -z "${MYSQL_DATABASE}" ]]; then
  echo "mysql-mcp.sh: DB_DATABASE is empty. Set in backend/.env" >&2
  exit 1
fi

exec npx -y mcp-mysql-server@0.2.0
