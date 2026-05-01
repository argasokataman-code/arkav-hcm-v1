#!/bin/bash
# Tax Governance Compatibility Checks (Before Phase 0)
# Purpose: Verify system state is compatible with Phase 0-6 implementation
# Run: bash scripts/check-tax-governance-compatibility.sh

set -e

echo "=========================================="
echo "Tax Governance Compatibility Checks"
echo "=========================================="
echo ""

cd backend

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

CHECKS_PASSED=0
CHECKS_FAILED=0
CHECKS_UNRESOLVED=0

# Check 1: Invoicing Module — Billing tax rate logic
echo "[CHECK 1] Invoicing Module — Billing tax rate (query-time vs snapshot)"
echo "---"
if grep -q "BillingTaxCalculationService" app/Services/BillingTaxCalculationService.php 2>/dev/null; then
    echo "Searching for billing tax calculation pattern..."
    if grep -q "resolvePolicy.*where.*status.*active" app/Services/BillingTaxCalculationService.php 2>/dev/null; then
        echo -e "${YELLOW}⚠️  WARNING: Billing tax might use query-time rate (resolvePolicy pattern found)${NC}"
        echo "   This could break when policy changes after invoice created."
        echo "   ACTION: Review invoices table schema - does it have billing_tax_rate_snapshot column?"
        
        # Check if snapshot column exists
        if php artisan tinker <<< "exit(Schema::hasColumn('invoices', 'billing_tax_rate_snapshot') ? 0 : 1)" >/dev/null 2>&1; then
            echo -e "${GREEN}✓ Snapshot column exists on invoices table${NC}"
            CHECKS_PASSED=$((CHECKS_PASSED + 1))
        else
            echo -e "${RED}✗ Snapshot column MISSING on invoices table (will need A8 migration in Phase 0)${NC}"
            CHECKS_FAILED=$((CHECKS_FAILED + 1))
        fi
    else
        echo -e "${GREEN}✓ BillingTaxCalculationService found${NC}"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    fi
else
    echo -e "${YELLOW}⚠️  BillingTaxCalculationService not found${NC}"
    CHECKS_UNRESOLVED=$((CHECKS_UNRESOLVED + 1))
fi
echo ""

# Check 2: Subscription Module — SubscriptionCreated event
echo "[CHECK 2] Subscription Module — SubscriptionCreated event"
echo "---"
if find app -name "*Subscription*Event*" -type f 2>/dev/null | grep -q .; then
    echo -e "${GREEN}✓ Subscription event(s) found:${NC}"
    find app -name "*Subscription*Event*" -type f 2>/dev/null | sed 's/^/   /'
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo -e "${YELLOW}⚠️  No SubscriptionCreated event found${NC}"
    echo "   ACTION: Phase 1 will CREATE SubscriptionCreated event + listener"
    CHECKS_UNRESOLVED=$((CHECKS_UNRESOLVED + 1))
fi
echo ""

# Check 3: Payroll Module — PayrollFinalized event
echo "[CHECK 3] Payroll Module — PayrollFinalized event"
echo "---"
if grep -r "PayrollFinalized" app/Events app/Models/PayrollRun.php 2>/dev/null | grep -q .; then
    echo -e "${GREEN}✓ PayrollFinalized event found${NC}"
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo -e "${YELLOW}⚠️  No PayrollFinalized event found${NC}"
    echo "   ACTION: Phase 1 will CREATE PayrollFinalized event + listener (or use observer)"
    CHECKS_UNRESOLVED=$((CHECKS_UNRESOLVED + 1))
fi
echo ""

# Check 4: Tax tables schema
echo "[CHECK 4] Database Schema — Tax governance tables"
echo "---"

# Use MySQL directly to check tables
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_DATABASE:-${DB_NAME:-arcav_hcm}}"
DB_USER="${DB_USERNAME:-${DB_USER:-root}}"
DB_PASS="${DB_PASSWORD:-${DB_PASS:-}}"

# Build mysql command
if [ -z "$DB_PASS" ]; then
    MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER $DB_NAME"
else
    MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME"
fi

SCHEMA_OK=1
for table in hcm_tax_governance_policies hcm_tax_governance_policy_events hcm_billing_tax_policies; do
    if $MYSQL_CMD -e "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'" 2>/dev/null | grep -q 1; then
        echo "✓ Table $table exists"
    else
        echo -e "${RED}✗ Table $table MISSING${NC}"
        SCHEMA_OK=0
    fi
done

if [ $SCHEMA_OK -eq 1 ]; then
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    CHECKS_FAILED=$((CHECKS_FAILED + 1))
fi

# Check for FK constraints on tax governance company_id and policy relation
FK_GAPS=0
if $MYSQL_CMD -e "SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hcm_tax_governance_policies' AND COLUMN_NAME='company_id' AND REFERENCED_TABLE_NAME='companies'" 2>/dev/null | grep -q 1; then
    echo "✓ FK exists: hcm_tax_governance_policies.company_id -> companies.id"
else
    echo -e "${YELLOW}⚠️  FK missing: hcm_tax_governance_policies.company_id -> companies.id${NC}"
    FK_GAPS=1
fi

if $MYSQL_CMD -e "SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hcm_tax_governance_policy_events' AND COLUMN_NAME='hcm_tax_governance_policy_id' AND REFERENCED_TABLE_NAME='hcm_tax_governance_policies'" 2>/dev/null | grep -q 1; then
    echo "✓ FK exists: hcm_tax_governance_policy_events.hcm_tax_governance_policy_id -> hcm_tax_governance_policies.id"
else
    echo -e "${YELLOW}⚠️  FK missing: hcm_tax_governance_policy_events.hcm_tax_governance_policy_id -> hcm_tax_governance_policies.id${NC}"
    FK_GAPS=1
fi

if $MYSQL_CMD -e "SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hcm_tax_governance_policy_events' AND COLUMN_NAME='company_id' AND REFERENCED_TABLE_NAME='companies'" 2>/dev/null | grep -q 1; then
    echo "✓ FK exists: hcm_tax_governance_policy_events.company_id -> companies.id"
else
    echo -e "${YELLOW}⚠️  FK missing: hcm_tax_governance_policy_events.company_id -> companies.id${NC}"
    FK_GAPS=1
fi

if $MYSQL_CMD -e "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hcm_billing_tax_policies' AND COLUMN_NAME='company_id'" 2>/dev/null | grep -q 1; then
    echo "✓ hcm_billing_tax_policies uses tenant-scoped company_id"
else
    echo -e "${YELLOW}⚠️  hcm_billing_tax_policies.company_id not found${NC}"
    FK_GAPS=1
fi

if [ $FK_GAPS -eq 0 ]; then
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    CHECKS_UNRESOLVED=$((CHECKS_UNRESOLVED + 1))
fi
echo ""

# Check 5: Permissions
echo "[CHECK 5] RBAC — Tax governance permissions"
echo "---"

# Check if tax permissions exist in database
PERM_COUNT=$($MYSQL_CMD -e "SELECT COUNT(*) FROM hcm_permissions WHERE code LIKE 'tax.%'" 2>/dev/null | tail -1 | tr -d '[:space:]')
if ! [[ "$PERM_COUNT" =~ ^[0-9]+$ ]]; then
    PERM_COUNT=0
fi

if [ "$PERM_COUNT" -gt 0 ]; then
    echo "✓ Tax permissions found ($PERM_COUNT total):"
    $MYSQL_CMD -e "SELECT code FROM hcm_permissions WHERE code LIKE 'tax.%' ORDER BY code" 2>/dev/null | tail -n +2 | sed 's/^/  - /'
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo -e "${YELLOW}⚠️  No tax permissions defined in database${NC}"
    echo "   ACTION: A9 migration needed (10 permissions) in Phase 2"
    CHECKS_UNRESOLVED=$((CHECKS_UNRESOLVED + 1))
fi
echo ""

# Check 6: Event dispatch status
echo "[CHECK 6] Tax Governance Controller — Event dispatch status"
echo "---"

if grep -c "// *TaxGovernancePolicyTransitioned::dispatch" app/Http/Controllers/Api/HcmTaxGovernanceController.php >/dev/null 2>&1; then
    COMMENTED_COUNT=$(grep -c "// *TaxGovernancePolicyTransitioned" app/Http/Controllers/Api/HcmTaxGovernanceController.php)
    echo -e "${RED}✗ Event dispatch COMMENTED ($COMMENTED_COUNT lines)${NC}"
    echo "   ACTION: A2 fix needed (uncomment + create listener) in Phase 1"
    CHECKS_FAILED=$((CHECKS_FAILED + 1))
elif grep -q "TaxGovernancePolicyTransitioned::dispatch" app/Http/Controllers/Api/HcmTaxGovernanceController.php; then
    echo -e "${GREEN}✓ Event dispatch active (uncommented)${NC}"
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo -e "${YELLOW}⚠️  Event dispatch pattern not found${NC}"
    CHECKS_UNRESOLVED=$((CHECKS_UNRESOLVED + 1))
fi
echo ""

# Summary
echo "=========================================="
echo "SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed:${NC}     $CHECKS_PASSED"
echo -e "${RED}Failed:${NC}     $CHECKS_FAILED"
echo -e "${YELLOW}Unresolved:${NC} $CHECKS_UNRESOLVED"
echo ""

if [ $CHECKS_FAILED -gt 0 ]; then
    echo -e "${RED}⚠️  ACTION REQUIRED: Fix $CHECKS_FAILED failed check(s) before Phase 0${NC}"
    exit 1
elif [ $CHECKS_UNRESOLVED -gt 0 ]; then
    echo -e "${YELLOW}ℹ️  $CHECKS_UNRESOLVED check(s) unresolved — these are expected (will be created in Phase 1)${NC}"
    exit 0
else
    echo -e "${GREEN}✓ All checks passed!${NC}"
    exit 0
fi
