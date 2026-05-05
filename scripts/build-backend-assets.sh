#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$PROJECT_ROOT/backend"

if [[ ! -f "$BACKEND_DIR/package.json" ]]; then
  echo "ERROR: backend/package.json not found."
  exit 1
fi

cd "$BACKEND_DIR"

NODE_MAJOR="$(node -p "process.versions.node.split('.')[0]" 2>/dev/null || echo "0")"
if [[ "$NODE_MAJOR" != "20" && "$NODE_MAJOR" != "22" && "$NODE_MAJOR" -lt "24" ]]; then
  echo "WARN: Recommended Node.js version is 20, 22, or >=24 (current: $(node -v 2>/dev/null || echo unknown))."
  echo "WARN: Build may still work, but use an LTS version to avoid random failures."
fi

if [[ ! -x "$BACKEND_DIR/node_modules/.bin/vite" ]]; then
  echo "Installing Node dependencies (npm ci)..."
  npm ci
fi

echo "Running frontend build (vite build)..."
npm run build

echo "Build completed successfully."
