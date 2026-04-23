#!/bin/bash

# Local Test Gate - Run before every push to main
# Mandatory order: composer → npm ci → npm build → migrate → PHPUnit → Vitest

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$PROJECT_ROOT/backend"

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

# Step 1: Install PHP dependencies
echo "${YELLOW}[1/6]${NC} Installing PHP dependencies..."
if ! composer install --no-dev; then
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

# Step 4: Run migrations
echo "${YELLOW}[4/6]${NC} Running database migrations..."
if ! php artisan migrate --force --env=testing; then
  echo "${RED}✗ Database migration failed${NC}"
  exit 1
fi
echo "${GREEN}✓ Database migrated${NC}"
echo ""

# Step 5: Run PHPUnit tests
echo "${YELLOW}[5/6]${NC} Running PHPUnit tests..."
if ! php artisan test; then
  echo "${RED}✗ PHPUnit tests failed${NC}"
  exit 1
fi
echo "${GREEN}✓ PHPUnit tests passed${NC}"
echo ""

# Step 6: Run Vitest
echo "${YELLOW}[6/6]${NC} Running Vitest..."
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
echo "🚀 Next steps:"
echo "   1. git add ."
echo "   2. git commit -m 'your message'"
echo "   3. git push origin main"
echo "   4. GitHub will automatically deploy artifact to server"
echo ""
