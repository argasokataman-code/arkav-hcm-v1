# Tax Governance & SaaS Financial Platform — Complete Implementation Guide

**Last Updated:** 2026-04-27  
**Status:** Architecture designed; Phase 0 (Schema Foundation) pending  
**Completion:** ~45% (tenant statutory tax only; platform revenue 0%)  
**Total Anomalies:** 16 (4 CRITICAL, 6 HIGH, 5 MEDIUM)

---

## PART 1: EXECUTIVE SUMMARY

### Current Implementation Status

**✅ What Works:**
- Tenant statutory tax: Employee tax profiles + payroll engine calculates PPh21 TER
- Tax policy CRUD: Policy lifecycle (draft → submitted → approved → published)
- Permission system: RBAC architecture in place
- Employee tax profile sync via EmployeeSnapshotService

**❌ What's Missing:**
- Platform revenue capture: 0% (no subscription/payroll/addon tax auto-capture)
- SaaS Financial Platform model: 0% (8 tables + 4 automation flows missing)
- Governance dashboard: 10% (tables exist but projection never written — event dispatch disabled)
- Authorization: 1 missing permission check + domain violations
- Event system: Listeners commented out (5 locations)

### Critical Issues by Category

| Category | Count | Severity | Impact |
|----------|-------|----------|--------|
| **Security** | 1 | CRITICAL | Data breach via missing auth |
| **Architecture** | 4 | CRITICAL | Domain violations + missing atomicity |
| **Data Integrity** | 5 | CRITICAL | Missing FK, hardcoded data, orphaned records |
| **Performance** | 3 | HIGH | N+1 queries, connection pool issues |
| **Race Conditions** | 3 | HIGH | Monthly close, payroll, event processing |

**Financial Impact (at 1000 employees):**
- Potential revenue loss: $50-100K/month (if AN-001, AN-003 not fixed)
- Audit discrepancies: $5-10K/month (if AN-002 not fixed)
- Reconciliation gaps: $10-50K/month (if payment clearing not implemented)

---

## PART 2: CRITICAL ANOMALIES (4 CRITICAL + 12 HIGH/MEDIUM)

### 🔴 CRITICAL: AN-001 — No Transaction Atomicity for Revenue Capture

**Location:** Phase 1 — Event listeners (CaptureSubscriptionTax, CapturePayrollServiceTax, CaptureAddonTax)  
**Severity:** CRITICAL (Financial Impact)  
**Impact at 1000 employees:** $50-100K/month revenue loss possible

**Problem:**
When subscription created, listener fires:
1. Read active policy
2. Calculate tax
3. INSERT platform_revenue_transactions

If step 3 fails (DB connection lost, constraint violation), revenue never recorded but subscription already committed.

**Risk Scenario:**
```
Day 1: 50 employees × subscription renewal = 50 SubscriptionCreated events
  → Listener fires 50 times in parallel
  → DB connection pool exhausted (default 10 connections)
  → Some events fail silently or throw exception
  → Subscription committed, but revenue NOT recorded
  → Monthly report shows $0 revenue from 10% of subscriptions
  → Financial discrepancy: $50,000+
```

**Fix Required:**
```php
class CaptureSubscriptionTax
{
    #[WithoutModelEvents]
    #[Transactional]  // Wrap in DB transaction
    public function handle(SubscriptionCreated $event)
    {
        try {
            $policy = Policy::find(...);
            if (!$policy) {
                Log::warning('No policy found');
                return;
            }
            
            DB::transaction(function () use ($event, $policy) {
                $tax = $this->calculate($policy);
                PlatformRevenueTransaction::create([...]);
                $policy->increment('revenue_captures_count');
            });
            
        } catch (\Throwable $e) {
            Log::critical('Revenue capture failed', ['error' => $e->getMessage()]);
            // Queue for retry
            dispatch(new RetryRevenueCapture($event->subscription->id))->delay(minutes: 5);
            throw $e;
        }
    }
}
```

**Validation Checklist:**
- [ ] All 3 listeners wrapped in DB::transaction()
- [ ] Retry queue created + monitored
- [ ] Tests: simulate DB failure mid-transaction, verify rollback
- [ ] Tests: simulate connection pool exhaustion (50 parallel events)
- [ ] Monitoring: alert on revenue_capture failures

**Effort:** High | **Phase:** 1 | **Breaking:** No

---

### 🔴 CRITICAL: AN-002 — Race Condition on Monthly Close Lock

**Location:** Phase 4 — Monthly closing batch job (CloseMonthlyFinancialReport)  
**Severity:** CRITICAL (Financial Report Corruption)  
**Impact at 1000 employees:** $50K/month revenue missing

**Problem:** Two batch jobs run simultaneously, both calculate monthly summary. Only one succeeds; other overwrites with different numbers.

**Failure Scenario:**
```
11:59:45 PM — Job A starts
  → Queries SUM(revenue) = $2.3M
  → Calculates tax = $345K

11:59:52 PM — Job B starts (race condition!)
  → Queries SUM(revenue) = $2.3M (but missing last 10 transactions)
  → Calculates tax = $340K

12:00:15 AM — Job A finishes, writes summary (2.3M revenue, 345K tax)
12:00:20 AM — Job B finishes, OVERWRITES (2.3M revenue, 340K tax)

Result: Report locked with WRONG tax (-$5K discrepancy)
→ Audit finds mismatch, requires amendment
```

**Fix Required:**
```php
public function closeMonthlyReport($year, $month)
{
    // Step 1: Acquire exclusive lock
    $lockKey = "monthly_close_{$year}_{$month}";
    $locked = Cache::lock($lockKey)->block(seconds: 30);
    
    if (!$locked) {
        Log::warning('Could not acquire lock, retrying later');
        dispatch($this)->delay(minutes: 5);
        return false;
    }
    
    try {
        // Step 2: Verify no summary already locked
        $existing = DB::table('platform_monthly_financial_summaries')
            ->where('report_status', 'locked')
            ->where('report_year', $year)
            ->where('report_month', $month)
            ->first();
        
        if ($existing) {
            Log::info('Report already locked');
            return false;
        }
        
        // Step 3: Calculate + lock atomically
        DB::transaction(function () use ($year, $month) {
            $summary = $this->calculateSummary($year, $month);
            $result = DB::table('platform_monthly_financial_summaries')
                ->updateOrCreate(
                    ['report_year' => $year, 'report_month' => $month],
                    [
                        'gross_revenue' => $summary['gross'],
                        'tax_amount' => $summary['tax'],
                        'net_revenue' => $summary['net'],
                        'report_status' => 'locked',
                        'locked_at' => now(),
                    ]
                );
        });
        
    } finally {
        Cache::lock($lockKey)->release();
    }
}
```

**Validation Checklist:**
- [ ] Advisory lock acquired before calculation
- [ ] Lock timeout set to 30s
- [ ] Verify no duplicate locked report (idempotent)
- [ ] Tests: 2 parallel closings, verify only 1 succeeds
- [ ] Monitoring: alert if lock contention detected

**Effort:** High | **Phase:** 4 | **Breaking:** No

---

### 🔴 CRITICAL: AN-003 — No Payment Clearing Status

**Location:** Phase 1-4 — Complete flow (capture → monthly close → reporting)  
**Severity:** CRITICAL (Reconciliation Gap)  
**Impact at 1000 employees:** $10-50K/month reconciliation gap

**Problem:** Revenue transaction created when subscription purchased, but no mechanism to mark as "cleared" or "disputed". Monthly report assumes all revenue is final, even if:
- Subscription cancelled before payment due
- Refunded after payment
- In chargeback dispute

**Risk Scenario:**
```
Month 1: 1000 employees × $100/month = $100K revenue captured
  → All 1000 transactions recorded as 'posted'
  → Monthly summary locked: $100K
  → Finance invoices customers

Month 2: 100 employees cancel (10% churn)
  → Revenue transactions created as 'reversal' (if logic exists)
  → But old revenue NOT marked as reversed
  → Net effect: +100K - 100K = $90K
  
If reversal logic broken/missing:
  → Net effect: +100K (100 cancellations never recorded)
  → Financial report shows $100K, but only $90K collectible
  → $10K discrepancy at audit time
```

**Fix Required:**
```php
// Schema changes
ALTER TABLE platform_revenue_transactions ADD COLUMN (
    clearing_status ENUM('uncleared', 'cleared', 'disputed', 'reversed') DEFAULT 'uncleared',
    clearing_date DATE NULL,
    dispute_reason VARCHAR(255) NULL,
    source_event_type VARCHAR(100),  -- subscription_created, payroll_finalized, addon_purchased
    source_entity_id BIGINT
);

// Monthly report only counts cleared transactions
$clearedRevenue = DB::table('platform_revenue_transactions')
    ->whereBetween('transaction_date', [$month_start, $month_end])
    ->where('status', 'posted')
    ->where('clearing_status', 'cleared')  // ← Only cleared!
    ->sum('gross_amount');

// Payment clearing job (nightly)
class ClearPaymentTransactions
{
    public function handle()
    {
        // Mark transactions as cleared after payment confirmed
        $twoDatagsAgo = now()->subDays(2);
        
        DB::table('platform_revenue_transactions')
            ->where('transaction_date', '<', $twoDatagsAgo)
            ->where('clearing_status', 'uncleared')
            ->update(['clearing_status' => 'cleared', 'clearing_date' => now()]);
    }
}
```

**Validation Checklist:**
- [ ] Clearing_status column added to schema
- [ ] Payment clearing job runs nightly
- [ ] Monthly report only counts 'cleared' transactions
- [ ] Tests: verify reversals reduce monthly total
- [ ] Tests: verify disputed transactions excluded from report

**Effort:** High | **Phase:** 1-4 | **Breaking:** No

---

### 🔴 CRITICAL: AN-004 — Payroll Policy Concurrency (Mixed Tax Rates)

**Location:** Phase 3 — PayrollDraftBuilder.build() method  
**Severity:** CRITICAL (Payroll Calculation Error)  
**Impact at 1000 employees:** 1-5% of employees have wrong tax rate

**Problem:** Two threads build payroll for same company simultaneously. Each reads different policy version, so employees drafted with mixed tax rates.

**Scenario:**
```
09:00 AM — Branch A payroll builder starts
  → Reads policy_v1 (tax_rate = 10%)
  → Calculates 500 employees with 10% tax
  
09:02 AM — Policy changes to v2 (tax_rate = 12%)

09:05 AM — Branch B payroll builder starts
  → Reads policy_v2 (tax_rate = 12%)
  → Calculates 500 employees with 12% tax

Result: Same company has 500 employees with 10% tax, 500 with 12%
→ Audit: "Why different tax rates for same company?"
```

**Fix Required:**
```php
public function build($payroll_run_id)
{
    $payroll_run = PayrollRun::lockForUpdate()->find($payroll_run_id);
    
    // Lock policy too (pessimistic read)
    $policy = HcmTaxGovernancePolicy::where('status', 'published')
        ->where('company_id', $payroll_run->company_id)
        ->sharedLock()  // Hold read lock for duration
        ->first();
    
    if (!$policy) {
        throw new NoPolicyFoundException(...);
    }
    
    // Snapshot policy version
    $payroll_run->hcm_tax_governance_policy_id = $policy->id;
    $payroll_run->hcm_tax_governance_policy_version = $policy->version;
    $payroll_run->save();
    
    // Now safe to build
    foreach ($payroll_run->employees as $emp) {
        $ter = $this->lookupTer($policy->version, $emp->tax_status);
        ...
    }
}
```

**Validation Checklist:**
- [ ] Payroll run locked for duration of calculation
- [ ] Policy snapshot stored atomically with payroll run
- [ ] Tests: 2 parallel builds, verify same policy used
- [ ] Tests: policy change during build, verify no cross-contamination

**Effort:** Medium | **Phase:** 3 | **Breaking:** No

---

### 🔴 CRITICAL: AN-005 — Missing FK Constraints (Data Integrity)

**Location:** Migration files + Schema  
**Severity:** CRITICAL (Data Integrity)  
**Impact:** Orphaned records; referential integrity not enforced

**Problem:** Deleted company leaves behind policies; deleted user leaves behind audit trail without context.

**Fix Required:**
```sql
ALTER TABLE hcm_tax_governance_policies
ADD CONSTRAINT fk_policy_company 
FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE hcm_tax_governance_policy_events
ADD CONSTRAINT fk_event_policy 
FOREIGN KEY (hcm_tax_governance_policy_id) REFERENCES hcm_tax_governance_policies(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_event_company
FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE hcm_billing_tax_policies
ADD CONSTRAINT fk_billing_policy_creator 
FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_billing_policy_updater
FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
```

**Effort:** Low | **Phase:** 0 | **Breaking:** No

---

### 🟠 HIGH: AN-006 — N+1 Query Dashboard (Performance)

**Location:** dashboardSummary() method  
**Impact at 1000 employees:** 20-30 second timeout

**Current (N+1):**
```
1 query: SELECT * FROM projections (50 rows)
50 queries: COUNT(*) per projection
= 51 total queries, ~2-5 seconds
```

**Better (GROUP BY):**
```php
$summary = DB::table('hcm_tax_governance_projections as p')
    ->leftJoin('hcm_tax_governance_anomalies as a', 
        fn($join) => $join->on('p.company_id', '=', 'a.company_id')
                         ->whereNull('a.resolved_at'))
    ->select('p.company_id', 'p.status', DB::raw('COUNT(a.id) as anomaly_count'))
    ->groupBy('p.company_id', 'p.status')
    ->get();
// 1 query, ~100ms
```

**Effort:** Low | **Phase:** 4 | **Breaking:** No

---

### 🟠 HIGH: AN-007 — No Idempotency Key (Duplicate Revenue)

**Location:** Phase 1 — All 3 event listeners  
**Impact:** Duplicate revenue recorded if event re-delivered

**Scenario:**
```
Event fires → Listener creates transaction (ID=1001)
Network glitch: Broker re-delivers event
Event fires AGAIN → Listener creates transaction (ID=1002) DUPLICATE!
```

**Fix:**
```php
class CaptureSubscriptionTax
{
    public function handle(SubscriptionCreated $event)
    {
        $idempotency_key = "sub_{$event->subscription->id}_revenue";
        
        // Check if already processed
        $existing = DB::table('platform_revenue_transactions')
            ->where('metadata->idempotency_key', $idempotency_key)
            ->first();
        
        if ($existing) {
            return;  // Idempotent — silently skip
        }
        
        // Capture with idempotency key
        PlatformRevenueTransaction::create([
            'metadata' => json_encode(['idempotency_key' => $idempotency_key]),
            ...
        ]);
    }
}
```

**Effort:** Medium | **Phase:** 1 | **Breaking:** No

---

### 🟠 HIGH: AN-008 — No Transaction Atomicity on Event Listeners

**Location:** Phase 1 — Event dispatch  
**Impact:** Event queue overflow loses events (500+ events at scale)

**Fix:** Add queue monitoring + backpressure
```php
public function handle(PayrollFinalized $event)
{
    $queue = Queue::connection('redis');
    $queue_size = $queue->size();
    
    if ($queue_size > 1000) {
        Log::critical('Queue backpressure', ['size' => $queue_size]);
        dispatch($event)->delay(minutes: 5);  // Retry later
        return;
    }
    
    // Safe to process
    $this->capturePayrollServiceTax($event);
}
```

**Effort:** Medium | **Phase:** 1 | **Breaking:** No

---

### 🟠 HIGH: AN-009 — Missing Permission Check (Data Breach)

**Location:** HcmTaxGovernanceController::tenantSelfAuditReportEnhanced() line 665  
**Severity:** CRITICAL (OWASP A01)  
**Impact:** Any user can download ANY tenant's audit report

**Current Code:**
```php
public function tenantSelfAuditReportEnhanced(Request $request)
{
    // BUG: NO ensurePermission() check!
    $tenant_id = $this->activeCompanyId($request);
    return response()->json([...]);
}
```

**Fix:**
```php
public function tenantSelfAuditReportEnhanced(Request $request)
{
    $this->ensurePermission($request, 'tax.tenant.report.view');
    $tenant_id = $this->activeCompanyId($request);
    return response()->json([...]);
}
```

**Effort:** Low | **Phase:** 2 | **Breaking:** No

---

### 🟠 HIGH: AN-010 — Event Dispatch Disabled (Projection Stale)

**Location:** HcmTaxGovernanceController lines 665, 750, 810, 880  
**Severity:** CRITICAL (Architectural)  
**Impact:** Dashboard projection never synced; metrics always stale/zero

**Current (Commented):**
```php
public function submit(Request $request)
{
    $policy->status = 'submitted';
    $policy->save();
    
    // TaxGovernancePolicyTransitioned::dispatch(...);  ← COMMENTED!
    
    return response()->json($policy);
}
```

**Fix:** Uncomment + create listener
```php
// Uncomment dispatch
TaxGovernancePolicyTransitioned::dispatch($policy, 'submitted', $request->user());

// Create listener
class UpdateTaxGovernanceProjection
{
    public function handle(TaxGovernancePolicyTransitioned $event)
    {
        HcmTaxGovernanceProjection::updateOrCreate(
            ['policy_uuid' => $event->policy->uuid],
            ['status' => $event->policy->status]
        );
    }
}

// Register in EventServiceProvider
protected $listen = [
    TaxGovernancePolicyTransitioned::class => [
        UpdateTaxGovernanceProjection::class,
    ],
];
```

**Effort:** Medium | **Phase:** 1 | **Breaking:** No

---

### 🟠 HIGH: AN-011 — Hardcoded Payroll Data (Report Accuracy)

**Location:** HcmTaxGovernanceController lines 685-690  
**Impact:** Audit report always shows "all_payroll_runs_covered: true" regardless of reality

**Current (Hardcoded):**
```php
'payroll_runs_in_period' => 0,
'all_payroll_runs_covered' => true,
```

**Fix:** Query actual data
```php
$payroll_runs = DB::table('payroll_runs')
    ->where('company_id', $tenant_id)
    ->whereBetween('period_end', [$period_start, $period_end])
    ->get();

$total_runs = $payroll_runs->count();
$covered_runs = $payroll_runs->filter(fn($r) => !is_null($r->hcm_tax_governance_policy_id))->count();

'payroll_runs_in_period' => $total_runs,
'all_payroll_runs_covered' => $covered_runs === $total_runs,
'coverage_percentage' => $total_runs > 0 ? round($covered_runs / $total_runs * 100, 2) : 100,
```

**Effort:** Medium | **Phase:** 3 | **Breaking:** Requires payroll_runs.policy_id column

---

### 🟠 HIGH: AN-012 — Domain Violation (Billing Tax Scope)

**Location:** hcm_billing_tax_policies schema  
**Impact:** Billing tax can be different per tenant (violates platform uniformity)

**Current (Wrong):**
```sql
CREATE TABLE hcm_billing_tax_policies (
    company_id BIGINT NOT NULL,  -- ← WRONG: Per-tenant
    tax_rate DECIMAL(5,2),
    ...
)
// Allows: company_1 = 5%, company_2 = 3%
```

**Fix (Correct):**
```sql
CREATE TABLE hcm_billing_tax_policies (
    -- NO company_id — platform-global
    tax_rate DECIMAL(5,2),
    status ENUM('active', 'inactive'),
    version INT,
    UNIQUE KEY uk_single_active (status)  -- Only 1 active
)
```

**Effort:** High | **Phase:** 0 | **Breaking:** Yes (requires migration)

---

### 🟡 MEDIUM: AN-013 — Billing Tax Snapshot Not Applied to Recalculations

**Location:** BillingTaxCalculationService  
**Impact:** Historical data mismatch when policy changes

**Problem:** Invoice created with rate 5%, policy changes to 10%, recalculation uses 10% instead of snapshot.

**Fix:** Always use snapshot
```php
$invoices = DB::table('invoices')
    ->whereBetween('invoice_date', [$month_start, $month_end])
    ->get(['id', 'amount', 'billing_tax_rate_snapshot']);

foreach ($invoices as $inv) {
    if (is_null($inv->billing_tax_rate_snapshot)) {
        $rate = $this->getCurrentPolicy()->rate;  // Fallback only
    } else {
        $rate = $inv->billing_tax_rate_snapshot;  // Use snapshot!
    }
    $tax = $inv->amount * $rate / 100;
}
```

**Effort:** Medium | **Phase:** 1-4 | **Breaking:** No

---

### 🟡 MEDIUM: AN-014 — Tenant Isolation Not Validated on Queries

**Location:** Financial reporting endpoints  
**Impact:** Potential data leak if activeCompanyId() returns NULL

**Fix:** Add defensive checks
```php
public function getOwnCharges(Request $request)
{
    $company_id = $this->activeCompanyId($request);
    
    // Defensive: Verify company_id is set
    if (!$company_id) {
        return response()->json(['error' => 'No active company'], 403);
    }
    
    // Defensive: Verify user has access
    if (!$request->user()->hasAccessToCompany($company_id)) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    
    // Defensive: Use model scope (not raw query)
    $charges = PlatformRevenueTransaction::forActiveTenant()
        ->get();
    
    // Defensive: Verify all results belong to active company
    $charges->each(function ($charge) use ($company_id) {
        if ($charge->company_id !== $company_id) {
            throw new SecurityException('Cross-tenant data leak detected!');
        }
    });
}
```

**Effort:** Medium | **Phase:** 4 | **Breaking:** No

---

### 🟡 MEDIUM: AN-015 — Monthly Close Race with Payroll Finalizations

**Location:** Phase 1-4 — Interaction between payroll + monthly close  
**Impact:** $50K revenue missing if payroll finalizes during close

**Problem:** While monthly close calculates revenue (09:00 AM), payroll finalizes (09:05 AM) and creates new transactions not included in sum.

**Fix:** Add grace period
```php
public function closeMonthlyReport($year, $month)
{
    $month_end = Carbon::createFromFormat('Y-m', "$year-$month")->endOfMonth();
    $closing_cutoff = $month_end->addHours(24);  // 24h grace period
    
    if (now() < $closing_cutoff) {
        Log::info('Not yet time for monthly close', ['cutoff' => $closing_cutoff]);
        return false;
    }
    
    // Now query with explicit date range
    $revenue = DB::table('platform_revenue_transactions')
        ->where('transaction_date', '<=', $month_end)
        ->where('status', 'posted')
        ->sum('gross_amount');
}

// Schedule: * 1 * * * (01:00 AM next month)
```

**Effort:** Low | **Phase:** 4 | **Breaking:** No

---

### 🟡 MEDIUM: AN-016 — Missing Tax Code Error Handling

**Location:** Phase 4 — CloseMonthlyFinancialReport  
**Impact:** Report fails or incomplete if tax codes deleted

**Fix:** Add resilience
```php
$activeCodes = DB::table('platform_expense_tax_codes')
    ->where('status', 'active')
    ->get();

if ($activeCodes->isEmpty()) {
    Log::critical('No active tax codes found for monthly close');
    
    // Alert finance team
    Notification::send(
        User::where('role', 'finance_officer')->get(),
        new MonthlyCloseTaxCodeMissing($month)
    );
    
    return false;  // Fail gracefully
}

$missingCodes = array_diff($expectedCodes, $activeCodes->pluck('code')->toArray());
if (!empty($missingCodes)) {
    Log::warning('Some tax codes missing', ['missing' => $missingCodes]);
    $summary->missing_tax_codes = json_encode($missingCodes);
}
```

**Effort:** Low | **Phase:** 4 | **Breaking:** No

---

## PART 3: PERFORMANCE & SCALABILITY ANALYSIS

### Scalability Ceiling

| Scale | Query Performance | Event Queue | Dashboard | Status |
|-------|------------------|-------------|-----------|--------|
| **100 emp** | <100ms ✅ | Clear | <500ms ✅ | **SAFE** |
| **500 emp** | 10-50ms ✅ | Clear | 5s ⚠️ | **SAFE** |
| **1000 emp** | 50-100ms ✅ | Backlog | 20-30s 🔴 | **RISKY** |
| **5000 emp** | 100-500ms ⚠️ | Overflow | 60+ s 💥 | **BROKEN** |
| **10K emp** | 500ms+ 🔴 | Lost events | Timeout 💥 | **CRASHED** |

### Query Bottlenecks

**Bottleneck 1: Monthly Revenue Aggregation**
```sql
SELECT transaction_type, SUM(gross_amount)
FROM platform_revenue_transactions
WHERE transaction_date BETWEEN '2026-04-01' AND '2026-04-30'
  AND status = 'posted'
GROUP BY transaction_type;
```
- **Rows to scan:** ~2K/month (1000 subscriptions + 1000 payroll + 50 add-ons)
- **With index:** 10-20ms ✅
- **Without index:** 500-1000ms ⚠️

**Bottleneck 2: Dashboard Anomaly Count (N+1)**
- **50 tenants:** 51 queries, 1-2 seconds
- **500 tenants:** 501 queries, 20-30 seconds 🔴
- **Better (GROUP BY):** 1 query, 100ms ✅

**Bottleneck 3: Payroll Draft Builder**
- **1000 employees N+1:** 1000 db queries, 1-2 seconds ⚠️
- **1000 employees eager load:** 1 query, 50ms ✅

---

## PART 4: CROSS-MODULE IMPACT

### Module-by-Module Impact Matrix

| Module | Phase | Breaking | Test Count | Status |
|--------|-------|----------|-----------|--------|
| **Payroll** | 3 | No | 12+ | Schema + logic |
| **Subscription** | 1 | No | 10+ | New integration |
| **Invoicing** | 0-1 | ⚠️ | 8+ | **Needs compatibility check** |
| **Add-ons** | 1 | No | 6+ | New integration |
| **RBAC** | 2 | No | 15+ | New permissions |
| **Database** | 0 | No | - | **Backup required** |
| **Events** | 1 | No | 12+ | New listeners |
| **API/OpenAPI** | 4 | No | - | Documentation |
| **Audit** | 1 | No | - | Immutable log |

**Total New Tests Needed:** 63+  
**Modules with Breaking Changes:** 1 (Invoicing)  
**Modules Needing Database Changes:** 1 (Invoicing)

---

## PART 5: PRE-LAUNCH CRITICAL FIXES CHECKLIST

### ⚠️ BEFORE PHASE 0 STARTS

#### Schema & Infrastructure

- [ ] **INDEX-001:** Create (status, transaction_date) index on platform_revenue_transactions
- [ ] **POOL-001:** Increase DB connection pool from 10 → 50 in config/database.php
- [ ] **SCHEMA-001:** Create platform_monthly_financial_summaries with UNIQUE (year, month)
- [ ] **FK-001:** Add all FK constraints (5 ALTER TABLE statements)
- [ ] **BACKUP:** mysqldump production database before Phase 0

#### Code Review

- [ ] All 4 CRITICAL anomalies identified + code reviewed
- [ ] All 8 HIGH anomalies identified + code reviewed
- [ ] 2+ engineers sign-off on each fix

---

### ⚠️ BEFORE PHASE 1 GOES LIVE

#### Revenue Capture Atomicity (AN-001)

- [ ] **ATOMIC-001:** Wrap all 3 event listeners in DB::transaction()
- [ ] **ATOMIC-002:** Add RetryRevenueCapture job + max retries: 3
- [ ] **ATOMIC-003:** Test failure scenarios (DB failure, connection exhaustion)

#### Idempotency Key (AN-007)

- [ ] **IDEM-001:** Add idempotency_key to platform_revenue_transactions.metadata
- [ ] **IDEM-002:** Add idempotency check in all 3 listeners
- [ ] **IDEM-003:** Test duplicate event delivery

#### Queue Backpressure (AN-008)

- [ ] **QUEUE-001:** Add queue size monitoring
- [ ] **QUEUE-002:** Add backpressure handling (delay if > 1000 events)
- [ ] **QUEUE-003:** Test event overflow (5000+ events)

#### Clearing Status (AN-003)

- [ ] **CLEAR-001:** Add clearing_status column to platform_revenue_transactions
- [ ] **CLEAR-002:** Add payment clearing job (nightly)
- [ ] **CLEAR-003:** Monthly report only counts cleared transactions

#### Permission Definitions

- [ ] **PERM-001:** Create 10 new permissions in seeders
- [ ] **PERM-002:** Assign to roles (Tenant Admin, Global Admin)
- [ ] **PERM-003:** Test permission enforcement (15+ scenarios)

#### Event Dispatch Fix (AN-010)

- [ ] **EVENT-001:** Uncomment event dispatch in 4 methods
- [ ] **EVENT-002:** Create UpdateTaxGovernanceProjection listener
- [ ] **EVENT-003:** Test event listener (create policy → verify projection updated)

---

### ⚠️ BEFORE PHASE 2 GOES LIVE

#### Permission Guard on Report (AN-009)

- [ ] **AUTH-001:** Add ensurePermission() to tenantSelfAuditReportEnhanced()
- [ ] **AUTH-002:** Test unauthorized access → 403

#### Tenant Isolation Validation (AN-014)

- [ ] **TENANT-001:** Add defensive checks on financial endpoints
- [ ] **TENANT-002:** Test cross-tenant access attempt → 403
- [ ] **TENANT-003:** Audit all queries for activeCompanyId() filter

---

### ⚠️ BEFORE PHASE 3 GOES LIVE

#### PayrollDraftBuilder Locking (AN-004)

- [ ] **LOCK-001:** Add pessimistic lock on policy read (sharedLock())
- [ ] **LOCK-002:** Add policy snapshot to payroll_runs table
- [ ] **LOCK-003:** Test concurrent policy changes during build

#### Hardcoded Payroll Data Fix (AN-011)

- [ ] **HARDCODE-001:** Replace hardcoded payroll_impact with real queries
- [ ] **HARDCODE-002:** Test audit report accuracy

---

### ⚠️ BEFORE PHASE 4 GOES LIVE

#### Monthly Close Lock (AN-002)

- [ ] **CLOSE-001:** Add advisory lock on monthly close
- [ ] **CLOSE-002:** Add 24h grace period after month-end
- [ ] **CLOSE-003:** Test concurrent close attempts

#### N+1 Dashboard Query Fix (AN-006)

- [ ] **DASHBOARD-001:** Replace N+1 with GROUP BY query
- [ ] **DASHBOARD-002:** Test dashboard performance (<500ms for 50 tenants)

#### Historical Snapshot Consistency (AN-013)

- [ ] **SNAPSHOT-001:** Add billing_tax_rate_snapshot to invoices
- [ ] **SNAPSHOT-002:** Use snapshot in recalculation
- [ ] **SNAPSHOT-003:** Test historical recalculation

#### Missing Tax Codes Error Handling (AN-016)

- [ ] **CODES-001:** Add check for active tax codes
- [ ] **CODES-002:** Test missing codes scenario

---

## PART 6: PHASE ROADMAP

### Phase 0: Schema Foundation (Week 1-2)

**Deliverables:**
- [ ] 8 new tables created + indexed
- [ ] FK constraints added
- [ ] Database migration tested
- [ ] Backup created

**Blocking Issues:**
- Database backup required
- Compatibility check on invoice module

---

### Phase 1: Revenue Capture + Event System (Week 3-4)

**Deliverables:**
- [ ] Transaction atomicity implemented + tested
- [ ] Event listeners created (3 new files)
- [ ] Projection listener created + projection synced
- [ ] 95+ new tests passing
- [ ] Monitoring + alerting configured

**Blocking Issues:**
- AN-001: Atomicity wrapper
- AN-003: Clearing status
- AN-007: Idempotency
- AN-008: Queue monitoring
- AN-010: Event dispatch uncommented

---

### Phase 2: Permissions + Security (Week 5)

**Deliverables:**
- [ ] 10 new permissions defined
- [ ] Role-permission assignment
- [ ] Auth guards on all endpoints
- [ ] Tenant isolation validation

**Blocking Issues:**
- AN-009: Permission check missing
- AN-014: Tenant isolation leak

---

### Phase 3: Payroll Integration (Week 6)

**Deliverables:**
- [ ] Payroll schema changes + migration
- [ ] Policy snapshot implementation
- [ ] Pessimistic locking on policy reads
- [ ] 12+ new payroll tests

**Blocking Issues:**
- AN-004: Payroll concurrency
- AN-011: Hardcoded payroll data

---

### Phase 4: Monthly Close + Reporting (Week 7-8)

**Deliverables:**
- [ ] Monthly close lock implemented
- [ ] N+1 dashboard query fixed
- [ ] Historical snapshot anchoring
- [ ] Comprehensive reporting

**Blocking Issues:**
- AN-002: Race condition on close
- AN-006: N+1 query
- AN-013: Snapshot consistency
- AN-016: Tax code error handling

---

### Phase 5: UI/UX Implementation & User Experience (Week 9-10)

**Objective:** Deliver role-based interfaces with intuitive workflows.

**Deliverables:**
- Policy management dashboard (Global Admin, Tenant Admin)
- Employee tax profile UI (HR, Employee self-service)
- Governance audit reports (Finance, Global Admin)
- Tax calculation transparency, mobile responsive, accessibility (WCAG 2.1 AA)
- UX testing with 5+ user personas

**Blocking Issues:**
- Phases 0-4 must complete + stabilize
- API contracts finalized

---

### Phases 6-9: Launch & Post-Launch (Weeks 11-16)

**Phase 6:** UUID Migration  
**Phase 7:** Multi-Currency Support (optional)  
**Phase 8:** Audit Trail Reporting  
**Phase 9:** Production Go-Live + Monitoring

## PART 7: UI/UX DESIGN SYSTEM & IMPLEMENTATION GUIDE

### Overview: User-Centric Tax Governance

This feature serves 5 distinct user personas with different goals. Each needs a tailored interface that makes complex tax calculations transparent without requiring tax expertise.

**Design Principle:** Progressive Disclosure — Hide complexity, surface only what users need, provide drill-down for details.

---

### Role-Based Interface Architecture

#### 1. GLOBAL ADMIN — System Oversight

**Goals:** Monitor all tenants, detect anomalies, view platform revenue, configure global settings.

**Primary Dashboard:**
- Quick Stats: Policies published, pending, errors; Platform revenue MTD
- Tenant Health Scorecard: Table with status (Active/Warning/Error), policy count, revenue, compliance %
- Interactions: Click tenant row for drill-down; click error badge to see remediation steps

**Components:**
- Alert banners (Red/Yellow/Green/Blue per severity)
- KPI cards with trend arrows
- Sortable/filterable tenant grid
- Context menu per row (View, Edit, Approve)

**UX Patterns:**
- Color-coded status: 🔴 Error | ⚠️ Warning | ✅ OK | 🔵 Info
- Hover status icon → Tooltip explains reason
- Search/filter by tenant name, status, compliance score
- Export to CSV/Excel for audits

---

#### 2. TENANT ADMIN — Policy Management Hub

**Goals:** Create/manage policies (draft → submit → approved → published), monitor employee sync, generate compliance reports.

**Primary Screens:**

**Policy Management View:**
- Tab: Policies | Employees | Reports | Audit Log
- Buttons: [+ New Policy] [Bulk Import] [History]
- Table: Policy name (version), status badge, employee count, actions
- Status legend: ✅ Active | ⏸ Draft | 📝 In Review | 📅 Scheduled | ⚠️ Error

**Policy Editor (Modal/Form):**
- Fields: Policy Name, Version, Effective Date (date picker)
- Tax Rules Builder: Rows of (Income Range | Tax Rate | Cumulative); [+ Add Rule]
- Notes field: Multi-line text
- Buttons: [Save as Draft] [Submit for Review] [Publish]

**Components:**
- Multi-step wizard for first policy (reduce cognitive load)
- Pre-populate from last version (90% reuse)
- Real-time validation: Overlapping ranges, missing rules
- Tooltip help per field with examples
- Warn if unsaved changes + exit
- Success message: "Policy saved" or "Submitted for review"

**UX Patterns:**
- Color-coded badges for status
- Action buttons context-sensitive (Edit if Draft, Publish if Approved only)
- Tax rules inline with calculation preview

---

#### 3. FINANCE OFFICER — Audit & Reconciliation

**Goals:** Generate compliance reports, reconcile revenue vs invoices, export for auditors, detect discrepancies.

**Primary Screens:**

**Reports Dashboard:**
- Generate: [Report Type dropdown] [Period dropdown] [Generate PDF] [Export CSV] [Email]
- Recent Reports: Table with name, period, status (Ready/Generating), actions (View, Send, Download)

**Reconciliation View:**
- Summary: Platform Revenue $2.3M | Invoiced $2.3M | Discrepancy $5K (0.2%)
- Alert: Red if >1% variance, yellow if 0.1-1%, green if <0.1%
- Drill-down: Revenue type table (Subscription, Payroll Service, Add-on, Billing Tax) with Platform vs Finance amounts, match status
- Explanation text: "Add-on tax mismatch: Click row for details"
- Action: "Mark 4 payments as cleared"

**Components:**
- Summary cards with variance indicator
- Date range picker (month/quarter/year)
- Detail table with drill-down to transaction level
- Export button: PDF/CSV/Excel
- Email integration: Send to distribution list

**UX Patterns:**
- Progressive disclosure: Summary first, drill-down for details
- Variance highlighting: Red >1%, yellow 0.1-1%
- Explanation text for every discrepancy
- Action items: Suggest next steps
- Link to transaction list for investigation

---

#### 4. HR MANAGER — Employee Tax Profiles

**Goals:** Manage employee profiles (NPWP, status, tax classification), bulk sync, monitor compliance, generate reports.

**Primary Screens:**

**Employee Tax Directory:**
- Buttons: [+ Add] [Bulk Import] [Sync Status]
- Search: Text field to filter
- Table: ID, Name, Status (✅ Active | ⚠️ Verify | 🔴 Error), NPWP, Annual Tax $
- Status badge: Visual + text
- Bulk actions: Select multiple → Update status, Mark verified, etc.

**Employee Profile Card:**
- Static info: NPWP (masked partially?), Status, Tax Classification, Last Verified date
- Calculated: Gross Income × 12, Non-Taxable × 12, Taxable Income, Annual PPh, Monthly PPh
- Actions: [Edit Profile] [View Full History]

**Components:**
- Search/filter: By name, NPWP, status, tax range
- Bulk import: CSV upload (template provided)
- Status icons: Quick visual (active, verify, error)
- Inline editing: Edit NPWP/status in modal
- Bulk actions: Select multiple rows

**UX Patterns:**
- Default: Show all employees
- NPWP validation: Flag invalid format
- Verification workflow: 1-click to verify
- Compliance view: "85% with valid NPWP"
- Sync indicator: "Last synced 2h ago | [Sync Now]"

---

#### 5. EMPLOYEE — Self-Service Tax Info

**Goals:** View personal tax profile (NPWP, status), understand tax calculation, download payslip, report issues.

**Primary Screens:**

**My Tax Profile Dashboard:**
- Info card: NPWP (✅ verified Oct 2025), Tax Status (Full Month Active), Last Updated date
- Calculation breakdown: Gross $1,500 → Non-taxable allowances -$375 (25%) → Taxable $1,125 → Tax rate 10% → PPh $112.50 → Net $1,387.50
- Visual flow diagram (cascade from left to right)
- Buttons: [Download Payslip PDF] [Questions?]

**Components:**
- Info card: Static personal data
- Calculation breakdown: Step-by-step visual flow
- Monthly summary: Current month card
- Downloadable payslip: PDF export
- FAQ link: Context-sensitive help
- Report issue button: Flag questions/discrepancies

**UX Patterns:**
- Transparency: Show calculation step-by-step (employee educates self)
- Accuracy check: "If this looks wrong, click here"
- Mobile-first: Payslip readable on phone
- Jargon-free: Plain language, not tax terms
- Verification: "Your NPWP verified on Oct 10"

---

### Shared Component Library

**Status Badges:**
- ✅ Active (Green) — Policy active, data current
- ⏸ Draft (Gray) — Work in progress
- 📝 In Review (Blue) — Awaiting approval
- 🔴 Error (Red) — Manual action required
- ⚠️ Warning (Yellow) — Needs attention
- 📅 Scheduled (Purple) — Future-dated

**Alerts:**
- Success (Green): "✅ Policy saved successfully"
- Error (Red): "🔴 Failed: Overlapping tax rates. Fix: Rule 2 and 3 overlap at $250M."
- Warning (Yellow): "⚠️ 3 employees missing NPWP. [Resolve Now]"

**Form Validation:**
- Real-time feedback: "✅ Format valid" or "⚠️ Must be 1st of month"
- Suggest fixes: "Did you include dashes? Copy from NPWP card."

**Data Tables:**
- Sortable columns, filterable rows
- Checkboxes + bulk actions
- Pagination: 10/25/50 rows
- Empty state: Icon + text "No policies yet. [Create one]"
- Loading: Skeleton rows

**Modals:**
- Header: Title + Close (X)
- Body: Form or content
- Footer: Action buttons
- Click backdrop = Close

---

### User Flows (Happy Path & Error Cases)

**Flow 1: Admin Creates & Publishes Policy**
1. Click [+ New Policy] → Wizard Step 1 (Basic Info) → Step 2 (Tax Rules) → Step 3 (Review)
2. [Save as Draft] → Policy saved
3. Click [Submit for Review] → Notification to Global Admin
4. Global Admin reviews & clicks [Approve] → Notification to Tenant Admin
5. Tenant Admin clicks [Publish] → Policy live, employees see new tax rates

**Flow 2: Admin Detects Anomaly**
1. Dashboard shows 🔴 Error on tenant
2. Click error badge → Modal: "3 missing employee profiles"
3. [View Details] → List of employees without NPWP
4. [Notify Tenant] → Notification sent to HR
5. HR updates NPWP via bulk import
6. Error resolved; badge turns ✅ Green

**Flow 3: Employee Understands Tax**
1. Employee logs in; sees calculation breakdown
2. Confused about $112.50 tax → Clicks breakdown card
3. Expansion shows: Gross $1,500 → -$375 allowance → $1,125 taxable → 10% rate → $112.50 tax
4. Employee satisfied; [OK]

**Flow 4: Finance Reconciles Revenue**
1. Click [Generate Report] → Select [April 2026]
2. Report shows: Platform $2.3M | Invoiced $2.3M | Discrepancy $5K 🔴
3. Click discrepancy → Drill-down: Add-on tax mismatch $5K
4. Click [Investigate] → Transaction list filtered
5. Identify: 4 pending payments
6. Mark [Cleared] → Variance resolved, now $0

---

### Accessibility & Inclusive Design (WCAG 2.1 AA)

**Color Accessibility:**
- Don't rely on color alone; use icons + labels
- Example: 🔴 "Error" not just red
- Contrast ratio ≥ 4.5:1 for text

**Keyboard Navigation:**
- Tab through all interactive elements
- Modal focus trap (Tab loops within modal)
- Escape key closes modal

**Screen Reader Support:**
- Alt text on icons: `<img alt="Error icon">`
- Form labels: `<label for="policy_name">Policy Name</label>`
- Table headers: `<th scope="col">Status</th>`
- Live regions: `<div role="alert">` for dynamic updates

**Font & Text:**
- Min font size: 14px
- Line height: 1.5
- Line length: max 80 characters

**Error Messages:**
- Clear, plain language: "NPWP format invalid. Expected: XXX.XXX.XXX.XXX-XXX"
- Suggest fix: "Did you include dashes?"
- Link to help

---

### Responsiveness Requirements

**Desktop (1024px+):** Full layout, 2-3 columns  
**Tablet (768px-1023px):** Stacked, 1-2 columns, 48px buttons  
**Mobile (<768px):** Single column, large touch targets, minimal modals

**Critical Mobile Flows:**
- Employee dashboard: Readable on 375px ✅
- Payslip: Printable from phone ✅
- Policy grid: Horizontally scrollable ✅

---

### UX Testing Plan (Phase 5)

**Test 1: Global Admin — Policy Monitoring (Day 1)**
- Can admin detect anomalies? Scenario: Dashboard error → drill-down → action in 2 clicks
- 3 admins × 2 scenarios = 6 sessions

**Test 2: Tenant Admin — Policy Creation (Day 2)**
- Can HR create policy without tax expertise? Scenario: Create policy from template in 5 mins
- Success: 80%+ complete without help
- 3 HR × 2 policies = 6 sessions

**Test 3: Finance Officer — Reconciliation (Day 2)**
- Can officer detect & resolve discrepancies? Scenario: $5K variance → drill-down → mark cleared
- Success: Resolve in <5 mins
- 2 finance × 2 scenarios = 4 sessions

**Test 4: Employee — Self-Service (Day 3)**
- Does employee understand tax calculation? Scenario: View payslip → explain $112.50 tax
- Success: Employee verbalize calculation without help
- 5 employees × 1 scenario = 5 sessions

**Test 5: Accessibility (Day 3)**
- Screen reader navigation (NVDA, VoiceOver)
- Scenarios: Tab policy table, read badges, submit form
- 2 testers × 3 flows = 6 sessions

**Total:** 27 sessions | Target: 5+ users per role × 2-3 scenarios

---

### Design System Tokens

**Colors:**
- Primary Blue: #0066CC (Buttons, links)
- Success Green: #00AA44 (Checkmarks)
- Error Red: #CC0000 (Errors)
- Warning Yellow: #FFAA00 (Warnings)
- Neutral Gray: #666666 (Borders)

**Typography:**
- H1: 28px bold
- H2: 20px bold
- Body: 14px regular
- Small: 12px regular

**Spacing:**
- xs: 4px | md: 8px | lg: 16px | xl: 32px

**Components:**
- Button (Primary): Blue bg, white text, 8px rounded
- Input: 12px border, 8px padding, 4px rounded
- Badge: 6px padding, 12px rounded
- Card: 8px shadow, 16px padding

---

### User Documentation (Built-in Help)

**Help System:**
1. Contextual tooltips: Hover icon (?) → explain field
2. FAQ modal: [Help] → searchable FAQs
3. Wizard guides: First-time policy → step-by-step
4. Video tutorials: 2-3 min per major flow
5. Email support: [Contact Support] → automatic ticket

**Docs Pages:**
- Understanding NPWP
- How is PPh21 calculated?
- What's a tax policy?
- Troubleshooting: Missing employees

---

### Performance Expectations

**Page Load:**
- Dashboard: <1s (cached)
- Policy list: <2s
- Report generation: <5s
- Payslip PDF: <3s

**Interaction:**
- Button click → 100-200ms visual feedback
- Search filter → <500ms
- Modal open → 150-300ms animation

---

## PART 8: RISK ASSESSMENT & MITIGATION

### Financial Risk (1000 employees)

| Risk | Severity | Probability | Impact | Mitigation |
|------|----------|------------|--------|-----------|
| Revenue loss (AN-001) | CRITICAL | HIGH | $50-100K/mo | Phase 1 atomicity |
| Reconciliation gap (AN-003) | CRITICAL | HIGH | $10-50K/mo | Phase 1 clearing status |
| Report corruption (AN-002) | CRITICAL | MEDIUM | $5-10K/mo | Phase 4 lock |
| Payroll inconsistency (AN-004) | CRITICAL | MEDIUM | 1-5% wrong tax | Phase 3 lock |

### Operational Risk

| Risk | Severity | Probability | Mitigation |
|------|----------|------------|-----------|
| Dashboard timeout | HIGH | HIGH | Phase 4 fix N+1 query |
| Event queue overflow | HIGH | HIGH | Phase 1 monitoring |
| Data isolation breach | CRITICAL | MEDIUM | Phase 2 permission checks |
| Orphaned records | MEDIUM | LOW | Phase 0 FK constraints |

### Recommended Pre-Launch Discipline

**Before Production:**
1. All 16 anomalies documented + fixes implemented
2. All 95+ tests passing locally + green on GitHub
3. Database backup created + migration tested
4. Monitoring + alerting configured
5. Runbook created for ops team

---

## PART 9: FAILURE MODES & RECOVERY

### Revenue Transactions Disappearing

**Symptom:** Monthly revenue total < expected  
**Root Cause:** Event listeners failed (AN-001 not fixed)  
**Recovery:**
1. Check failed listener logs
2. Query retry queue
3. Manually dispatch failed events
4. Run monthly close again

### Duplicate Revenue Recorded

**Symptom:** Monthly revenue 2x expected for some subscriptions  
**Root Cause:** Event re-delivery (AN-007 not fixed)  
**Recovery:**
1. Identify duplicate transactions
2. Mark one as 'reversed'
3. Update monthly summary
4. Issue amended report

### Dashboard Timeout

**Symptom:** GET /admin/dashboard hangs >30s  
**Root Cause:** N+1 query (AN-006 not fixed)  
**Recovery:**
1. Kill query in DB
2. Restart PHP-FPM
3. Implement GROUP BY hotfix

### Inconsistent Payroll Tax

**Symptom:** Same company, different tax rates for same month  
**Root Cause:** Policy race condition (AN-004 not fixed)  
**Recovery:**
1. Identify affected employees
2. Recalculate payroll items
3. Issue amended payslips
4. Update audit trail

---

## COMPLETION TRACKING

| Phase | Anomalies Fixed | Tests Added | Status |
|-------|-----------------|-------------|--------|
| Phase 0 | 0 | - | Planning |
| Phase 1 | AN-001, 003, 007, 008, 010 | 50+ | In-progress |
| Phase 2 | AN-009, 014 | 15+ | Planning |
| Phase 3 | AN-004, 011 | 12+ | Planning |
| Phase 4 | AN-002, 006, 013, 016, 015 | 18+ | Planning |
| Phase 5 | - (UI/UX) | 40+ | Planning |
| Phase 6 | - (Governance monitoring) | 15+ | Planning |
| Phases 7-9 | - | 25+ | Planning |
| **TOTAL** | **16** | **175+** | **0/9 complete** |

**Target:** All anomalies fixed + all tests passing before Phase 9 (production launch)

---

## KEY DECISIONS LOCKED

**Decision 1: Architecture**
- Three-layer model: Platform Revenue → Platform Tax → Governance Dashboard
- Dual-domain: Platform Revenue + Platform Expense Tax (global) vs Tenant Statutory Tax (per-tenant)

**Decision 2: Data Isolation**
- Billing tax: Platform-singleton (NO company_id)
- Revenue transaction: Company_id FK (per-tenant scoped)
- Dashboard: Read-model projection (cross-tenant observability)

**Decision 3: Event System**
- Immutable audit trail for policy state transitions
- Event-sourced projection for governance dashboard
- Idempotent listeners with retry queue

**Decision 4: Scalability**
- Safe to 1000 employees (with all fixes applied)
- Risky at 5000+ employees (needs query optimization)
- Broken at 10K+ employees (fundamental architecture changes needed)

---

## References

- **Architecture:** See README.md for business flow + domain isolation
- **Tracker:** See tracker.md for phase progress + evidence
- **Deep Analysis:** See PART 3 in this document for performance and scalability scenarios
