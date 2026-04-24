#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="$ROOT_DIR/release/shared-hosting"
KEEP_COUNT="${1:-${SHARED_HOSTING_ARTIFACT_KEEP_COUNT:-5}}"

if ! [[ "$KEEP_COUNT" =~ ^[0-9]+$ ]]; then
  echo "[artifact-prune] ERROR: keep count must be a positive integer" >&2
  exit 1
fi

if [[ "$KEEP_COUNT" -lt 1 ]]; then
  echo "[artifact-prune] ERROR: keep count must be >= 1" >&2
  exit 1
fi

if [[ ! -d "$OUTPUT_DIR" ]]; then
  echo "[artifact-prune] INFO: output directory not found, nothing to prune"
  exit 0
fi

artifacts=()
while IFS= read -r artifact; do
  artifacts+=("$artifact")
done < <(ls -1t "$OUTPUT_DIR"/shared-hosting-artifact-*.tar.gz 2>/dev/null || true)

if [[ "${#artifacts[@]}" -le "$KEEP_COUNT" ]]; then
  echo "[artifact-prune] INFO: artifacts=${#artifacts[@]}, keep=$KEEP_COUNT, nothing to prune"
  exit 0
fi

echo "[artifact-prune] INFO: pruning old artifacts (keep latest $KEEP_COUNT)"
for artifact in "${artifacts[@]:$KEEP_COUNT}"; do
  echo "[artifact-prune] remove $(basename "$artifact")"
  rm -f "$artifact"
done

echo "[artifact-prune] INFO: done"
echo "[artifact-prune] NOTE: stage retention changes with: git add -A release/shared-hosting"
