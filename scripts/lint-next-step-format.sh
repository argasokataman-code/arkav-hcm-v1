#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_REPORT="$(mktemp)"
TMP_FILES="$(mktemp)"
trap 'rm -f "$TMP_REPORT" "$TMP_FILES"' EXIT

# Internal guide files that should follow the recommendation format rule.
{
  echo "$ROOT_DIR/README.md"
  echo "$ROOT_DIR/AGENTS.md"
  find "$ROOT_DIR/.github/instructions" -type f -name "*.md" 2>/dev/null || true
  find "$ROOT_DIR/docs/planning" -type f -name "*.md" 2>/dev/null || true
} | awk 'NF' | sort -u > "$TMP_FILES"

if [[ ! -s "$TMP_FILES" ]]; then
  echo "lint-next-step-format: no target files found"
  exit 0
fi

EXIT_CODE=0

while IFS= read -r file; do
  [[ -f "$file" ]] || continue

  awk -v f="$file" '
    BEGIN {
      n = 0
    }
    {
      lines[++n] = $0
    }
    END {
      for (i = 1; i <= n; i++) {
        low = tolower(lines[i])

        # Flag lines containing next step / next steps phrase.
        if (low ~ /next step|next steps/) {
          numbered_found = 0

          # Look ahead for numbered list markers.
          for (j = i + 1; j <= n && j <= i + 8; j++) {
            if (lines[j] ~ /^[[:space:]]*[0-9]+\.[[:space:]]+/) {
              numbered_found = 1
              break
            }
          }

          if (numbered_found == 0) {
            printf "%s:%d: potential non-compliant recommendation block\n", f, i
          }
        }
      }
    }
  ' "$file" >> "$TMP_REPORT"
done < "$TMP_FILES"

if [[ -s "$TMP_REPORT" ]]; then
  echo "lint-next-step-format: FAIL"
  cat "$TMP_REPORT"
  echo ""
  echo "Expected: recommendation sections should be followed by numbered list items (1. 2. 3.)."
  EXIT_CODE=1
else
  echo "lint-next-step-format: OK"
fi

exit "$EXIT_CODE"
