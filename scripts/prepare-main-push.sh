#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

usage() {
  cat <<'EOF'
Usage:
  bash scripts/prepare-main-push.sh --message "<commit message code/docs>" [--skip-tests] [--push]

What this script does (strict order):
  1) Optional local gate (default: run); caches result so pre-push hook skips re-run
  2) Commit code/docs changes first (excluding release/shared-hosting)
  3) Build shared-hosting artifact from latest code commit
  4) Verify artifact sync guard
  5) Commit release/shared-hosting artifact refresh
  6) Push to origin/main if --push flag is set, otherwise print "ready to push"

Notes:
  - --push: enables auto-push after all guards pass (operator-confirmed one-command deploy)
  - Test gate result is cached in .test-gate-passed; pre-push hook skips re-run for same HEAD.
  - Prevents stale artifact metadata by design.
EOF
}

CODE_COMMIT_MSG=""
SKIP_TESTS="false"
AUTO_PUSH="false"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --message)
      CODE_COMMIT_MSG="${2:-}"
      shift 2
      ;;
    --skip-tests)
      SKIP_TESTS="true"
      shift
      ;;
    --push)
      AUTO_PUSH="true"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "[prepare-main-push] unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$CODE_COMMIT_MSG" ]]; then
  echo "[prepare-main-push] --message is required" >&2
  usage
  exit 1
fi

if ! git -C "$ROOT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "[prepare-main-push] not inside a git repository" >&2
  exit 1
fi

if [[ -n "$(git -C "$ROOT_DIR" diff --cached --name-only)" ]]; then
  echo "[prepare-main-push] staged changes detected. Please commit or unstage first." >&2
  echo "[prepare-main-push] this guard keeps commit boundaries deterministic." >&2
  exit 1
fi

echo "[prepare-main-push] step 1/6: local test gate"
if [[ "$SKIP_TESTS" == "false" ]]; then
  CURRENT_HEAD="${CURRENT_HEAD:-$(git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || echo '')}"
  CACHED_HEAD=""
  if [[ -f "$ROOT_DIR/.test-gate-passed" ]]; then
    CACHED_HEAD="$(cat "$ROOT_DIR/.test-gate-passed")"
  fi
  if [[ -n "$CURRENT_HEAD" && "$CURRENT_HEAD" == "$CACHED_HEAD" ]]; then
    echo "[prepare-main-push] test gate cache hit (HEAD=$CURRENT_HEAD) — skipping re-run"
  else
    bash "$ROOT_DIR/scripts/local-test-gate.sh"
    # Cache result so subsequent calls and pre-push hook skip re-running for same commit
    NEW_HEAD="$(git -C "$ROOT_DIR" rev-parse HEAD 2>/dev/null || echo '')"
    if [[ -n "$NEW_HEAD" ]]; then
      echo "$NEW_HEAD" > "$ROOT_DIR/.test-gate-passed"
    fi
  fi
else
  echo "[prepare-main-push] --skip-tests enabled: local test gate skipped"
fi

echo "[prepare-main-push] step 2/6: commit code/docs changes (exclude release/shared-hosting)"
git -C "$ROOT_DIR" add -A
git -C "$ROOT_DIR" restore --staged release/shared-hosting >/dev/null 2>&1 || true

if [[ -n "$(git -C "$ROOT_DIR" diff --cached --name-only)" ]]; then
  git -C "$ROOT_DIR" commit -m "$CODE_COMMIT_MSG"
else
  echo "[prepare-main-push] no code/docs changes to commit (excluding release/shared-hosting)"
fi

echo "[prepare-main-push] step 3/6: build shared-hosting artifact"
bash "$ROOT_DIR/scripts/shared-hosting-package-local.sh"

echo "[prepare-main-push] step 4/6: artifact sync guard"
bash "$ROOT_DIR/scripts/check-shared-hosting-artifact-sync.sh" "$(git -C "$ROOT_DIR" rev-parse HEAD)"

echo "[prepare-main-push] step 5/6: commit artifact refresh"
git -C "$ROOT_DIR" add -A release/shared-hosting
if [[ -n "$(git -C "$ROOT_DIR" diff --cached --name-only)" ]]; then
  git -C "$ROOT_DIR" commit -m "build: refresh shared-hosting artifact"
else
  echo "[prepare-main-push] no artifact change detected in release/shared-hosting"
fi

echo "[prepare-main-push] step 6/6: final guards"
bash "$ROOT_DIR/scripts/check-shared-hosting-artifact-sync.sh" "$(git -C "$ROOT_DIR" rev-parse HEAD)"
bash "$ROOT_DIR/scripts/check-deploy-runtime-guard.sh"

echo ""
if [[ "$AUTO_PUSH" == "true" ]]; then
  echo "[prepare-main-push] --push flag set: pushing to origin/main..."
  git -C "$ROOT_DIR" push origin main
else
  echo "[prepare-main-push] READY TO PUSH"
  echo "[prepare-main-push] this script intentionally does not push."
  echo "[prepare-main-push] run manually after explicit confirmation:"
  echo "  git push origin main"
  echo "  OR add --push flag to run this script with auto-push:"
  echo "  bash scripts/prepare-main-push.sh --message \"...\" --push"
fi
