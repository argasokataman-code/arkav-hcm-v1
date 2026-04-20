#!/usr/bin/env bash
set -euo pipefail

# Fail if backend API surface changes without docs/api updates.
#
# Usage:
#   bash scripts/check-api-docs-sync.sh [base_ref]
#   bash scripts/check-api-docs-sync.sh --staged
#
# base_ref:
#   - Optional. Defaults to "origin/main" if present, else "main", else "HEAD~1".
#
# --staged:
#   - Checks only staged files (for pre-commit hooks).

mode="range"
base_ref="${1:-}"

if [[ "${base_ref}" == "--staged" ]]; then
  mode="staged"
  base_ref=""
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "check-api-docs-sync: not a git repo; skipping."
  exit 0
fi

if [[ "${mode}" == "staged" ]]; then
  changed="$(git diff --cached --name-only --diff-filter=ACMR || true)"
  range_desc="staged"
else
  default_base="HEAD~1"
  if [[ -z "${base_ref}" ]]; then
    if git rev-parse --verify -q "origin/main" >/dev/null; then
      base_ref="origin/main"
    elif git rev-parse --verify -q "main" >/dev/null; then
      base_ref="main"
    else
      base_ref="${default_base}"
    fi
  fi

  if ! git rev-parse --verify -q "${base_ref}" >/dev/null; then
    echo "check-api-docs-sync: base ref '${base_ref}' not found; skipping."
    exit 0
  fi

  range="${base_ref}...HEAD"
  changed="$(git diff --name-only "${range}" || true)"
  range_desc="${range}"
fi

if [[ -z "${changed}" ]]; then
  echo "check-api-docs-sync: no changed files."
  exit 0
fi

api_changed_files="$(echo "${changed}" | awk '
  $0 ~ /^backend\/routes\/api\.php$/ { print; next }
  $0 ~ /^backend\/app\/Http\/Controllers\/Api\/.*\.php$/ { print; next }
')"

docs_changed_files="$(echo "${changed}" | awk '
  $0 ~ /^docs\/api\/.*\.(md|yaml|yml)$/ { print; next }
')"

openapi_changed_files="$(echo "${changed}" | awk '
  $0 ~ /^docs\/api\/openapi\.(yaml|yml)$/ { print; next }
')"

feature_docs_changed_files="$(echo "${changed}" | awk '
  $0 ~ /^docs\/api\/.*\.md$/ { print; next }
')"

if [[ -z "${api_changed_files}" ]]; then
  echo "check-api-docs-sync: no backend API surface changes detected."
  exit 0
fi

if [[ -z "${docs_changed_files}" ]]; then
  echo "ERROR: Backend API changed but docs/api not updated."
  echo
  echo "API files changed (${range_desc}):"
  echo "${api_changed_files}" | sed 's/^/  - /'
  echo
  echo "Expected: update at least one of:"
  echo "  - docs/api/<feature>-api.md"
  echo "  - docs/api/api-spec-phase-1.md (index/global)"
  echo "  - docs/api/openapi.yaml (if maintained)"
  echo
  exit 1
fi

if [[ -z "${openapi_changed_files}" ]]; then
  echo "ERROR: Backend API changed but OpenAPI contract was not updated."
  echo
  echo "API files changed (${range_desc}):"
  echo "${api_changed_files}" | sed 's/^/  - /'
  echo
  echo "Required: update docs/api/openapi.yaml"
  echo
  exit 1
fi

if [[ -z "${feature_docs_changed_files}" ]]; then
  echo "ERROR: Backend API changed but Swagger-style feature docs were not updated."
  echo
  echo "API files changed (${range_desc}):"
  echo "${api_changed_files}" | sed 's/^/  - /'
  echo
  echo "Required: update at least one docs/api/<feature>-api.md"
  echo
  exit 1
fi

echo "check-api-docs-sync: OK"
echo
echo "API files changed:"
echo "${api_changed_files}" | sed 's/^/  - /'
echo
echo "OpenAPI files changed:"
echo "${openapi_changed_files}" | sed 's/^/  - /'
echo
echo "Feature API docs changed:"
echo "${feature_docs_changed_files}" | sed 's/^/  - /'
echo
echo "Docs files changed:"
echo "${docs_changed_files}" | sed 's/^/  - /'

