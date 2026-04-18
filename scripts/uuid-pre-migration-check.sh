#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"

fail() {
  echo "[FAIL] $1"
  exit 1
}

pass() {
  echo "[PASS] $1"
}

info() {
  echo "[INFO] $1"
}

info "UUID pre-migration check started"

if [[ ! -d "${BACKEND_DIR}" ]]; then
  fail "backend directory not found"
fi

if [[ ! -f "${BACKEND_DIR}/artisan" ]]; then
  fail "Laravel artisan file not found in backend"
fi

if [[ ! -f "${BACKEND_DIR}/database/migrations/2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables.php" ]]; then
  fail "final UUID cutover migration file missing"
fi
pass "final UUID cutover migration file exists"

if [[ ! -f "${ROOT_DIR}/docs/sql/uuid-cutover-integrity-check.sql" ]]; then
  fail "integrity SQL file missing"
fi
pass "integrity SQL file exists"

if [[ ! -f "${ROOT_DIR}/docs/features/uuid-migration/PRODUCTION-RUNBOOK.md" ]]; then
  fail "production runbook missing"
fi
pass "production runbook exists"

if command -v php >/dev/null 2>&1; then
  (cd "${BACKEND_DIR}" && php -l database/migrations/2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables.php >/dev/null)
  pass "migration php syntax valid"
else
  info "php not found in PATH, skipped syntax lint"
fi

info "Running focused regression tests"
(cd "${BACKEND_DIR}" && php artisan test --filter=UserHcmAdminGateTest)
pass "UserHcmAdminGateTest"

(cd "${BACKEND_DIR}" && php artisan test --filter=AuthApiTest)
pass "AuthApiTest"

info "UUID pre-migration check completed"
info "Next: execute production runbook at docs/features/uuid-migration/PRODUCTION-RUNBOOK.md"
