#!/bin/bash

# Local Test Gate - Run before every push to main
# Mandatory order: composer → npm ci → npm build → migrate → PHPUnit → Vitest

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$PROJECT_ROOT/backend"
LOCK_DIR="/tmp/arcav-local-test-gate.lock"

# Prevent concurrent gate runs that can race on shared testing DB.
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
  echo "Another local-test-gate process is running. Wait for it to finish, then retry."
  exit 1
fi

cleanup_lock() {
  rmdir "$LOCK_DIR" 2>/dev/null || true
}
trap cleanup_lock EXIT

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║ 🧪 LOCAL TEST GATE - All tests before push to main             ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

cd "$BACKEND_DIR"

# Step 1: Install PHP dependencies (with dev for testing)
echo "${YELLOW}[1/6]${NC} Installing PHP dependencies..."
if ! composer install; then
  echo "${RED}✗ composer install failed${NC}"
  exit 1
fi
echo "${GREEN}✓ PHP dependencies installed${NC}"
echo ""

# Step 2: Install Node dependencies
echo "${YELLOW}[2/6]${NC} Installing Node dependencies..."
if ! npm ci; then
  echo "${RED}✗ npm ci failed${NC}"
  exit 1
fi
echo "${GREEN}✓ Node dependencies installed${NC}"
echo ""

# Step 3: Build frontend assets
echo "${YELLOW}[3/6]${NC} Building frontend assets..."
if ! npm run build; then
  echo "${RED}✗ npm run build failed${NC}"
  exit 1
fi
echo "${GREEN}✓ Frontend assets built${NC}"
echo ""

# Step 4: Run migrations (fresh to avoid MySQL 1412 table-definition-changed errors after ALTERs)
echo "${YELLOW}[4/6]${NC} Running database migrations..."
if ! php artisan migrate:fresh --force --env=testing; then
  echo "${RED}✗ Database migration failed${NC}"
  exit 1
fi
echo "${GREEN}✓ Database migrated${NC}"
echo ""

# Step 5: Smart planner UUID FK health check
echo "${YELLOW}[5/7]${NC} Checking smart planner UUID FK health..."
if ! bash "$PROJECT_ROOT/scripts/check-smart-planner-fk-health.sh"; then
  echo "${RED}✗ Smart planner FK health check failed${NC}"
  exit 1
fi
echo "${GREEN}✓ Smart planner FK health check passed${NC}"
echo ""

# Step 6: Run PHPUnit tests
echo "${YELLOW}[6/7]${NC} Running PHPUnit tests..."
if ! php artisan test --env=testing; then
  echo "${RED}✗ PHPUnit tests failed${NC}"
  exit 1
fi
echo "${GREEN}✓ PHPUnit tests passed${NC}"
echo ""

# Step 7: Run Vitest
echo "${YELLOW}[7/7]${NC} Running Vitest..."
if ! npx vitest run; then
  echo "${RED}✗ Vitest failed${NC}"
  exit 1
fi
echo "${GREEN}✓ Vitest passed${NC}"
echo ""

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║ ${GREEN}✓ ALL TESTS PASSED${NC} - Ready to push & deploy!          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "🚀 Next steps (strict):"
echo "   1. Commit code/docs first (without release artifact)"
echo "   2. Build artifact from latest code commit"
echo "   3. Commit release/shared-hosting artifact refresh"
echo "   4. Push to main ONLY after explicit operator confirmation"
echo ""
echo "Suggested helper (no auto-push):"
echo "   bash scripts/prepare-main-push.sh --message 'your code/docs commit message'"
echo ""
