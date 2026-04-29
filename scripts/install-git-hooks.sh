#!/usr/bin/env bash

set -euo pipefail

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "install-git-hooks: not a git repo in current directory."
  exit 1
fi

chmod +x scripts/check-tests-on-change.sh
chmod +x scripts/check-views-root-guard.sh
chmod +x scripts/check-api-docs-sync.sh
chmod +x scripts/lint-next-step-format.sh
chmod +x scripts/prepare-main-push.sh
chmod +x .githooks/pre-commit
chmod +x .githooks/pre-push

git config core.hooksPath .githooks

echo "install-git-hooks: done"
echo "  - core.hooksPath = .githooks"
echo "  - pre-commit will run test/docs + next-step format lint guards"
echo "  - pre-push will block main push unless local gate + artifact sync pass"
echo "  - deploy prep helper available: bash scripts/prepare-main-push.sh --message \"<msg>\""
