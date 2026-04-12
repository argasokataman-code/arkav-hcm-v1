#!/usr/bin/env bash

set -euo pipefail

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "install-git-hooks: not a git repo in current directory."
  exit 1
fi

chmod +x scripts/check-tests-on-change.sh
chmod +x scripts/check-api-docs-sync.sh
chmod +x .githooks/pre-commit

git config core.hooksPath .githooks

echo "install-git-hooks: done"
echo "  - core.hooksPath = .githooks"
echo "  - pre-commit will run test/docs guards"
