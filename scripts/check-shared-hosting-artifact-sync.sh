#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_SHA="${1:-}"

if [[ -z "$TARGET_SHA" ]]; then
  TARGET_SHA="$(git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || true)"
fi

if [[ -z "$TARGET_SHA" ]]; then
  echo "[artifact-sync] ERROR: unable to resolve target commit SHA" >&2
  exit 1
fi

ARTIFACT="$(ls -t "$ROOT_DIR"/release/shared-hosting/shared-hosting-artifact-*.tar.gz 2>/dev/null | head -1 || true)"
if [[ -z "$ARTIFACT" ]]; then
  echo "[artifact-sync] ERROR: no artifact found under release/shared-hosting/" >&2
  exit 1
fi

extract_metadata() {
  local key="$1"
  local value
  value="$(tar -xOf "$ARTIFACT" RELEASE-METADATA.txt 2>/dev/null | sed -n "s/^${key}=//p" | head -1 || true)"
  if [[ -n "$value" ]]; then
    echo "$value"
    return 0
  fi

  value="$(tar -xOf "$ARTIFACT" ./RELEASE-METADATA.txt 2>/dev/null | sed -n "s/^${key}=//p" | head -1 || true)"
  echo "$value"
}

ARTIFACT_GIT_HEAD="$(extract_metadata git_head)"
ARTIFACT_NAME="$(extract_metadata artifact_name)"

if [[ -z "$ARTIFACT_GIT_HEAD" ]]; then
  echo "[artifact-sync] ERROR: RELEASE-METADATA.txt missing git_head in artifact: $ARTIFACT" >&2
  exit 1
fi

if ! git -C "$ROOT_DIR" cat-file -e "${ARTIFACT_GIT_HEAD}^{commit}" 2>/dev/null; then
  echo "[artifact-sync] ERROR: artifact git_head not found in repository history: $ARTIFACT_GIT_HEAD" >&2
  exit 1
fi

if ! git -C "$ROOT_DIR" cat-file -e "${TARGET_SHA}^{commit}" 2>/dev/null; then
  echo "[artifact-sync] ERROR: target SHA is not a valid commit: $TARGET_SHA" >&2
  exit 1
fi

if ! git -C "$ROOT_DIR" merge-base --is-ancestor "$ARTIFACT_GIT_HEAD" "$TARGET_SHA"; then
  echo "[artifact-sync] ERROR: artifact git_head is not an ancestor of target commit" >&2
  echo "[artifact-sync] artifact git_head: $ARTIFACT_GIT_HEAD" >&2
  echo "[artifact-sync] target commit    : $TARGET_SHA" >&2
  exit 1
fi

CHANGED_SINCE_ARTIFACT="$({
  git -C "$ROOT_DIR" diff --name-only "$ARTIFACT_GIT_HEAD..$TARGET_SHA" -- backend || true
  git -C "$ROOT_DIR" diff --name-only "$ARTIFACT_GIT_HEAD..$TARGET_SHA" -- docs/api/openapi.yaml || true
  git -C "$ROOT_DIR" diff --name-only "$ARTIFACT_GIT_HEAD..$TARGET_SHA" -- scripts/shared-hosting-deploy.sh || true
  git -C "$ROOT_DIR" diff --name-only "$ARTIFACT_GIT_HEAD..$TARGET_SHA" -- scripts/shared-hosting-deploy-easy.sh || true
  git -C "$ROOT_DIR" diff --name-only "$ARTIFACT_GIT_HEAD..$TARGET_SHA" -- scripts/shared-hosting-queue-cron.sh || true
} | sort -u | sed '/^$/d')"

if [[ -n "$CHANGED_SINCE_ARTIFACT" ]]; then
  echo "[artifact-sync] ERROR: deploy artifact is stale for target commit $TARGET_SHA" >&2
  echo "[artifact-sync] latest artifact : ${ARTIFACT_NAME:-$(basename "$ARTIFACT")}" >&2
  echo "[artifact-sync] artifact git_head: $ARTIFACT_GIT_HEAD" >&2
  echo "[artifact-sync] changed files after artifact build:" >&2
  echo "$CHANGED_SINCE_ARTIFACT" >&2
  echo "" >&2
  echo "[artifact-sync] Fix required:" >&2
  echo "  1) bash scripts/local-test-gate.sh" >&2
  echo "  2) bash scripts/shared-hosting-package-local.sh" >&2
  echo "  3) git add release/shared-hosting" >&2
  echo "  4) git commit -m \"build: refresh shared-hosting artifact\"" >&2
  echo "  5) git push origin main" >&2
  exit 1
fi

echo "[artifact-sync] OK: artifact is aligned with target commit"
echo "[artifact-sync] artifact: ${ARTIFACT_NAME:-$(basename "$ARTIFACT")}" 
echo "[artifact-sync] git_head : $ARTIFACT_GIT_HEAD"
echo "[artifact-sync] target   : $TARGET_SHA"
