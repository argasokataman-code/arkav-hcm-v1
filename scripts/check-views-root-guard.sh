#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="backend/resources/views"

if [[ ! -d "${ROOT_DIR}" ]]; then
  echo "check-views-root-guard: ${ROOT_DIR} not found; skipping."
  exit 0
fi

fail=0

while IFS= read -r -d '' file; do
  rel="${file#./}"

  if [[ "${rel}" =~ \.bak$ || "${rel}" =~ \.old$ || "${rel}" =~ \.orig$ ]]; then
    echo "ERROR: backup/temp file is not allowed in views root: ${rel}"
    fail=1
    continue
  fi

  non_empty_lines="$(sed '/^[[:space:]]*$/d' "${file}" | wc -l | tr -d ' ')"
  if [[ "${non_empty_lines}" -gt 3 ]]; then
    echo "ERROR: root blade must be a thin wrapper (<=3 non-empty lines): ${rel}"
    fail=1
    continue
  fi

  # Root wrapper must be a direct include for backward compatibility mapping.
  if ! sed '/^[[:space:]]*$/d' "${file}" | grep -Eq "^@include\('[^']+'\)$"; then
    echo "ERROR: root blade must be exactly one @include('...') wrapper: ${rel}"
    fail=1
  fi
done < <(find "${ROOT_DIR}" -maxdepth 1 -type f -name '*.blade.php' -print0)

if [[ "${fail}" -ne 0 ]]; then
  echo
  echo "check-views-root-guard: FAILED"
  exit 1
fi

echo "check-views-root-guard: OK"
exit 0
