#!/usr/bin/env bash

set -euo pipefail

if [[ "${SKIP_TEST_GUARD:-}" == "1" ]]; then
  echo "check-tests-on-change: skipped (SKIP_TEST_GUARD=1)"
  exit 0
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "check-tests-on-change: not a git repo; skipping."
  exit 0
fi

staged_files="$(git diff --cached --name-only --diff-filter=ACMR || true)"

if [[ -z "${staged_files}" ]]; then
  echo "check-tests-on-change: no staged files."
  exit 0
fi

source_changes="$(echo "${staged_files}" | awk '
  $0 ~ /^backend\/app\/.*\.php$/ { print; next }
  $0 ~ /^backend\/routes\/.*\.php$/ { print; next }
  $0 ~ /^frontend\/resources\/js\/.*\.js$/ { print; next }
')"

if [[ -z "${source_changes}" ]]; then
  echo "check-tests-on-change: docs/chore-only changes detected; skipping."
  exit 0
fi

all_test_changes="$(echo "${staged_files}" | awk '
  $0 ~ /^backend\/tests\/.*\.php$/ { print; next }
  $0 ~ /^backend\/tests\/.*\.(js|ts|tsx)$/ { print; next }
  $0 ~ /^frontend\/.*(test|spec)\.(js|ts|tsx)$/ { print; next }
')"

backend_feature_tests="$(echo "${staged_files}" | awk '
  $0 ~ /^backend\/tests\/Feature\/.*\.php$/ { print; next }
')"

api_or_permission_changes="$(echo "${staged_files}" | awk '
  $0 ~ /^backend\/routes\/api\.php$/ { print; next }
  $0 ~ /^backend\/app\/Http\/Controllers\/Api\/.*\.php$/ { print; next }
  $0 ~ /^backend\/app\/Http\/Middleware\/.*\.php$/ { print; next }
  $0 ~ /^backend\/app\/Http\/Requests\/.*\.php$/ { print; next }
  $0 ~ /^backend\/app\/Policies\/.*\.php$/ { print; next }
  $0 ~ /^backend\/config\/arcav_hcm_web_guard\.php$/ { print; next }
  $0 ~ /^backend\/config\/auth\.php$/ { print; next }
')"

if [[ -n "${api_or_permission_changes}" ]]; then
  if [[ -z "${backend_feature_tests}" ]]; then
    echo "ERROR: API/permission-sensitive changes require backend Feature test updates."
    echo
    echo "API/permission-sensitive changes:"
    echo "${api_or_permission_changes}" | sed 's/^/  - /'
    echo
    echo "Please add at least one staged test in:"
    echo "  - backend/tests/Feature/*.php"
    echo
    echo "Bypass once (only if absolutely necessary):"
    echo "  SKIP_TEST_GUARD=1 git commit ..."
    exit 1
  fi

  echo "check-tests-on-change: strict mode OK (API/permission changes covered by Feature tests)"
  echo
  echo "API/permission-sensitive changes:"
  echo "${api_or_permission_changes}" | sed 's/^/  - /'
  echo
  echo "Backend Feature tests changed:"
  echo "${backend_feature_tests}" | sed 's/^/  - /'
  exit 0
fi

if [[ -z "${all_test_changes}" ]]; then
  echo "ERROR: Source changes detected without test changes."
  echo
  echo "Source changes:"
  echo "${source_changes}" | sed 's/^/  - /'
  echo
  echo "Please include at least one relevant test update under:"
  echo "  - backend/tests/"
  echo "  - frontend/**/*.(test|spec).{js,ts,tsx}"
  echo
  echo "Bypass once (only for true non-functional changes):"
  echo "  SKIP_TEST_GUARD=1 git commit ..."
  exit 1
fi

echo "check-tests-on-change: OK"
echo
echo "Source changes:"
echo "${source_changes}" | sed 's/^/  - /'
echo
echo "Test changes:"
echo "${all_test_changes}" | sed 's/^/  - /'
exit 0
