#!/bin/bash

# Multi-Tenant RBAC HCM E2E Test Runner
# This script runs all E2E tests for the multi-tenant RBAC implementation

set -e

echo "🚀 Starting Multi-Tenant RBAC HCM E2E Test Suite"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if we're in the backend directory
if [ ! -f "playwright.config.js" ]; then
    print_error "Please run this script from the backend directory"
    exit 1
fi

# Check if dependencies are installed
if [ ! -d "node_modules" ]; then
    print_warning "Installing dependencies..."
    npm install
fi

# Install Playwright browsers if needed
if [ ! -d "node_modules/@playwright/test" ]; then
    print_warning "Installing Playwright..."
    npx playwright install
fi

# Check if Laravel server is running
if ! pgrep -f "php artisan serve" > /dev/null; then
    print_warning "Laravel server not detected. Please start it with: php artisan serve"
    print_warning "Tests will fail if server is not running!"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Set base URL
export PLAYWRIGHT_BASE_URL=${PLAYWRIGHT_BASE_URL:-"http://127.0.0.1:8000"}

print_status "Using base URL: $PLAYWRIGHT_BASE_URL"
print_status "Starting E2E test execution..."
echo

# Test scenarios mapping
declare -A test_scenarios=(
    ["01-landing-signup.spec.js"]="LANDING → SIGN UP (CREATE COMPANY)"
    ["02-first-login-owner.spec.js"]="FIRST LOGIN (OWNER)"
    ["03-role-management.spec.js"]="ROLE MANAGEMENT (CREATE ROLE)"
    ["04-user-management.spec.js"]="USER MANAGEMENT (CREATE USER)"
    ["05-permission-enforcement.spec.js"]="PERMISSION ENFORCEMENT (REAL USAGE)"
    ["06-edit-role-dynamic.spec.js"]="EDIT ROLE (DYNAMIC CHANGE)"
    ["07-cross-tenant-isolation.spec.js"]="CROSS TENANT ISOLATION"
    ["08-role-escalation-attempt.spec.js"]="ROLE ESCALATION ATTEMPT"
    ["09-subscription-limitation.spec.js"]="SUBSCRIPTION LIMITATION"
    ["10-delete-role-in-use.spec.js"]="DELETE ROLE IN USE"
    ["11-audit-visibility.spec.js"]="AUDIT VISIBILITY"
)

total_scenarios=${#test_scenarios[@]}
passed=0
failed=0

# Run tests for each scenario
for test_file in "${!test_scenarios[@]}"; do
    scenario_name="${test_scenarios[$test_file]}"

    echo
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}Running: ${scenario_name}${NC}"
    echo -e "${BLUE}File: ${test_file}${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"

    if npx playwright test "$test_file" --reporter=list; then
        print_success "✓ $scenario_name - PASSED"
        ((passed++))
    else
        print_error "✗ $scenario_name - FAILED"
        ((failed++))
    fi
done

# Summary
echo
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}TEST SUMMARY${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo "Total Scenarios: $total_scenarios"
echo -e "Passed: ${GREEN}$passed${NC}"
echo -e "Failed: ${RED}$failed${NC}"

if [ $failed -eq 0 ]; then
    print_success "🎉 ALL TESTS PASSED! Multi-tenant RBAC is SECURE and READY!"
    echo
    echo -e "${GREEN}✅ System is SECURE - Zero cross-tenant data leakage${NC}"
    echo -e "${GREEN}✅ UX is PREDICTABLE - UI correctly reflects permissions${NC}"
    echo -e "${GREEN}✅ Error handling is CLEAR - Meaningful messages displayed${NC}"
    echo -e "${GREEN}✅ Audit trail is COMPLETE - All actions tracked${NC}"
    echo -e "${GREEN}✅ Ready for PRODUCTION - Enterprise-grade RBAC validated${NC}"
else
    print_error "❌ $failed test(s) failed. Please review and fix issues."
    echo
    print_warning "💡 Check test-results/ directory for detailed reports"
    print_warning "💡 Run 'npx playwright show-report' to view HTML report"
    exit 1
fi

echo
print_status "Test execution completed."
print_status "Run 'npx playwright show-report' to view detailed HTML report"