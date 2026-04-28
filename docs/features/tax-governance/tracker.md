# Tax Governance & SaaS Financial Platform — Implementation Tracker

**Last Updated:** 2026-04-28 (Platform domain boundary hardening UI+BE + full gate pass)  
**Documentation Status:** Consolidated into 3 files (README.md, IMPLEMENTATION.md, tracker.md)

---

## Project Status Summary

**Overall Completion:** ~70% (Phase 1-4 anomaly stream mostly closed)  
**Total Anomalies Found:** 16 (4 CRITICAL + 6 HIGH + 6 MEDIUM)  
**Status:** Phase 1-4 anomaly fixes completed except AN-012 intentionally deferred; local full gate passes

### What's Done
- ✅ Tenant statutory tax: Employee tax profiles + payroll engine (PPh21 TER calculation)
- ✅ Tax policy CRUD: Lifecycle (draft → submitted → approved → published)
- ✅ Employee import + validation
- ✅ Salary component tax flags
- ✅ RBAC architecture
- ✅ Comprehensive documentation + deep analysis
- ✅ All 16 anomalies identified with code locations + fixes

### What's Missing
- ⚠️ Platform revenue capture: partial hardening still needed (core capture + clearing + idempotency already implemented)
- ⚠️ SaaS Financial Platform model: partial (core transaction + monthly summary tables implemented; full 8-table target not yet complete)
- ⚠️ Governance dashboard: partial (projection sync active; aggregation hardening ongoing)
- ✅ Event dispatch: enabled on policy lifecycle transitions
- ✅ Authorization gap fixed: tenant self-audit enhanced now permission-guarded
- ⚠️ Monthly close: lock implementation exists; production-readiness evidence still incomplete
- ⚠️ Payroll integration: policy snapshot exists; broader payroll integration evidence still incomplete

---

## Critical Issues Summary

### 🔴 CRITICAL (4 issues — Revenue/Data at Risk)

| # | Issue | Impact | Phase | Status |
|----|-------|--------|-------|--------|
| AN-001 | No transaction atomicity | $50-100K loss | 1 | ✅ Fixed (DB::transaction wrapper aktif di 3 capture listeners, 2026-04-27) |
| AN-002 | Race condition on close | $50K missing/mo | 4 | ✅ Fixed (CloseMonthlyFinancialReportJob + Cache::lock + 24h grace + idempotency, 2026-04-27) |
| AN-003 | No payment clearing | $10-50K gap | 1-4 | ✅ Fixed (clearing state machine + nightly job aktif, 2026-04-27) |
| AN-004 | Payroll concurrency | 1-5% wrong tax | 3 | ✅ Fixed (PayrollDraftBuilder sharedLock + policy snapshot on hcm_payroll_runs, 2026-04-27) |

### 🟠 HIGH (6 issues — Functional Gaps)

| # | Issue | Phase | Status |
|----|-------|-------|--------|
| AN-006 | N+1 dashboard query | 4 | ✅ Fixed (dashboard anomaly aggregation optimized) |
| AN-007 | No idempotency key | 1 | ✅ Fixed (idempotency_key unique index + duplicate skip observability aktif, 2026-04-27) |
| AN-008 | Queue backpressure | 1 | ✅ Fixed (QueueBackpressureGuard rolling-window monitor aktif di 3 listeners, 2026-04-27) |
| AN-009 | Missing auth guard | 2 | ✅ Fixed (tenant self-audit enhanced guarded) |
| AN-010 | Event dispatch disabled | 1 | ✅ Fixed (dispatch active in lifecycle endpoints) |
| AN-011 | Hardcoded payroll data | 3 | ✅ Fixed (real DB queries in TaxGovernanceReportingService + controller, 2026-04-27) |

### 🟡 MEDIUM (6 issues — Design Gaps)

| # | Issue | Phase | Status |
|----|-------|-------|--------|
| AN-005 | Missing FK constraints | 0 | ✅ Fixed (FK migration 000700 with SQLite guard; enforced where schema-compatible, 2026-04-27) |
| AN-012 | Domain violation (billing tax) | 0 | 🟡 Deferred (billing tax per-tenant config has value; removing company_id would break existing flow; documented as intentional design) |
| AN-013 | Snapshot mismatch | 1-4 | ✅ Fixed (invoices.billing_tax_rate_snapshot column added, 2026-04-27) |
| AN-014 | Tenant isolation leak | 4 | ✅ Fixed (defensive guards in BillingTaxCalculationService + TaxGovernanceReportingService, 2026-04-27) |
| AN-015 | Payroll/close race | 4 | ✅ Fixed (24h grace period + Cache lock in CloseMonthlyFinancialReportJob, 2026-04-27) |
| AN-016 | Tax code error handling | 4 | ✅ Fixed (warnIfNoActiveTaxCodes() emits warning, does not fail close job, 2026-04-27) |

---

## Phase Implementation Plan (9 Phases Total)

### Execution Sprint Update (2026-04-28)

Completed in current execution:
- Clarified menu boundary labels into explicit Platform Finance domains: Billing & Revenue vs Government Tax & Compliance.
- Refined wording on platform billing and government compliance pages so both screens no longer overlap conceptually.
- Standardized tenant-facing tax menu/page naming (sidebar + tax rates page) to consistent professional business wording.
- Fixed tenant compliance summary mapping so API keys from runtime (`invoice_count`, `paid_invoice_count`, `cleared_revenue_amount`, `tax_amount_due`, `policy_summary`, `created_by`) render correctly in UI.
- Added regression wiring assertion for tax payable display to prevent blank government-tax amount regressions.
- Fixed dashboard wiring so tenant screens no longer call platform policy/report endpoints (prevents cross-domain gate noise).
- Added explicit backend guard checks on platform tax compliance API methods before delegated execution (permission + global-admin), to keep compliance boundary self-documenting in controller flow.
- Standardized Financial Settings cross-links so Billing & Revenue and Government Tax & Compliance are both visible as separate navigation targets.
- Enabled policy transition dispatch in lifecycle endpoints (`store/submit/approve/reject/publish`)
- Fixed projection listener UUID mapping and insert stability for sqlite test/runtime consistency
- Added missing permission guard in enhanced tenant self-audit endpoint
- Hardened tenant isolation checks (`company_id` casting + company existence validation)
- Reworked dashboard anomaly aggregation to avoid N+1 pattern
- Added regression tests for enhanced self-audit permission + cross-tenant denial

Still pending in this sprint:
- Final proof-pack consolidation in tracker for Phase 3-4 evidence checklist
- Cross-module permission enforcement completion in Phase 2

### Detailed Execution Board (Active Sprint)

#### Prerequisite Foundation Scope (Must Exist Before AN-007/AN-003 Closure)

- [x] PRE-01 Create migration `platform_revenue_transactions` (source event refs + status + metadata)
- [x] PRE-02 Create model `PlatformRevenueTransaction` + casts + guarded/fillable policy
- [x] PRE-03 Register event contracts for revenue source ingestion (`SubscriptionCreated`, `PayrollFinalized`, `AddonPurchased` or equivalent)
- [x] PRE-04 Implement capture listeners that write canonical revenue transaction rows
- [x] PRE-05 Add baseline feature tests that prove one source event creates one canonical transaction row

#### Track A — AN-007 Idempotency Key (Duplicate Revenue Prevention)

- [x] AN007-01 Inventory semua entrypoint capture revenue (`subscription`, `payroll_service`, `addon`) + owner function
- [x] AN007-02 Definisikan format key final per source event + uniqueness rule lintas retry queue
- [x] AN007-03 Tentukan storage strategy key (metadata JSON vs dedicated column) + trade-off index
- [x] AN007-04 Implement guard read-before-write untuk skip duplicate event
- [x] AN007-05 Tambahkan logging observability untuk duplicate skipped events
- [x] AN007-06 Tambahkan regression tests untuk redelivery event (double dispatch -> single transaction)
- [x] AN007-07 Jalankan focused PHPUnit suite untuk capture path terkait

Discovery note:
- AN-007 closed untuk baseline runtime saat ini: idempotency key + duplicate skip observability + queued retry policy sudah aktif di tiga capture listener.

#### Track B — AN-003 Clearing Status (Reconciliation Readiness)

- [x] AN003-01 Definisikan state machine `uncleared -> cleared/disputed/reversed` + transition guard
- [x] AN003-02 Tambahkan migration kolom clearing (`clearing_status`, `clearing_date`, `dispute_reason`, source refs)
- [x] AN003-03 Backfill strategy untuk row existing (default state + data safety check)
- [x] AN003-04 Implement nightly clearing job + idempotent rerun behavior
- [x] AN003-05 Integrasikan filter `clearing_status=cleared` ke summary/report aggregation
- [x] AN003-06 Tambahkan regression tests untuk `disputed` dan `reversed` exclusion
- [x] AN003-07 Validasi tidak ada regressions di snapshot compliance response

Discovery note:
- Karena tabel revenue capture dan status clearing belum ada, AN-003 dimulai dari migration baseline + wiring ingestion sebelum nightly clearing job.

Update note (2026-04-27):
- Baseline schema + ingestion layer sudah tersedia (`platform_revenue_transactions`, model, event/listener wiring, idempotency unique key).
- Source reference integrity validator sentral sudah aktif (`RevenueSourceReferenceValidator`) dan dipakai di seluruh capture listener.
- Queue retry policy listener sudah aktif (`ShouldQueue`, `tries=3`, `backoff=[30,120,300]`) dengan failure observability log `tax_governance.revenue_capture_failed`.

#### Track C — Closure and Governance Evidence

- [x] GOV-01 Sync docs feature + API contract bila ada perubahan behavior payload/query (no API contract delta for this listener-only change)
- [x] GOV-02 Jalankan `bash scripts/check-api-docs-sync.sh`
- [x] GOV-03 Jalankan full gate `bash scripts/local-test-gate.sh`
- [x] GOV-04 Update evidence hasil test + risk residual di tracker

Evidence snapshot (2026-04-27):
- Migration applied: `2026_05_03_000500_create_platform_revenue_transactions_table`
- Focused tests pass:
	- `tests/Feature/RevenueSourceReferenceValidatorTest.php` (5 passed)
	- `tests/Feature/TaxGovernanceRevenueCaptureQueuePolicyTest.php` (4 passed)
	- `tests/Feature/TaxGovernanceRevenueCaptureTest.php` (4 passed)
	- `tests/Feature/TaxGovernanceRevenueClearingJobTest.php` (2 passed)
	- `tests/Feature/SubscriptionServiceTest.php`, `tests/Feature/TransactionControllerTest.php`, `tests/Feature/HcmTaxGovernanceApiTest.php` (36 passed)
	- `tests/Feature/HcmTaxGovernancePhase8Phase9Test.php` (5 passed, includes cleared-vs-disputed/reversed aggregation assertion)
- API docs sync guard: `check-api-docs-sync: no changed files`
- Full local gate: PASS (PHPUnit + Vitest)

Residual risks (updated 2026-04-27):
- ~~AN-001: Transaction atomicity wrapper belum aktif~~ → ✅ Resolved: `DB::transaction` wrapper aktif di ketiga capture listeners. Evidence: `TaxGovernanceCaptureAtomicityTest::test_capture_wraps_write_in_transaction_and_commits_on_success`.
- ~~AN-008: Queue backpressure guard belum aktif~~ → ✅ Resolved: `QueueBackpressureGuard` aktif di ketiga listeners dengan rolling 1-minute window (threshold=200/mnt) + observability log `tax_governance.queue_backpressure_alert`. Evidence: `TaxGovernanceCaptureAtomicityTest::test_backpressure_guard_logs_warning_when_threshold_exceeded`.
- Remaining: AN-012 deferred by design decision; Phase 5+ production hardening evidence belum lengkap.

### Phase 0: Schema Foundation (Week 1-2)

**Status:** ⚠️ Partially Complete  
**Deliverables:**
- [ ] 8 new tables created + indexes (full scope)
- [x] Key tax governance FK constraints migration added (schema-compatible subset)
- [ ] Connection pool increased (10 → 50)
- [ ] Database backup + rollback tested
- [x] Migration compatibility verified for current local gate run

**Blockers Fixed:**
- AN-005 (FK constraints)
- AN-012 (Domain violation — billing tax)

**Tests:** 5+ migration tests  
**Evidence:** Migration script + compatibility check log

---

### Phase 1: Revenue Capture + Event System (Week 3-4)

**Status:** ✅ Core Revenue Capture Complete  
**Deliverables:**
- [x] Transaction atomicity wrapper on 3 event listeners (DB::transaction, 2026-04-27)
- [x] Retry queue for failed events (ShouldQueue + tries/backoff, 2026-04-27)
- [x] Idempotency key implementation (unique index + firstOrCreate guard, 2026-04-27)
- [x] Queue monitoring + backpressure handling (QueueBackpressureGuard rolling window, 2026-04-27)
- [x] Clearing status column + reconciliation job (ClearRevenueTransactionsJob, 2026-04-27)
- [x] Event dispatch enabled + projection listener repaired
- [ ] 10 new permissions defined + assigned
- [x] Local test gate passes (PHPUnit + Vitest)

**Target Blockers in Phase 1:**
- AN-001 (Atomicity)
- AN-003 (Clearing status)
- AN-007 (Idempotency)
- AN-008 (Queue backpressure)
- AN-010 (Event dispatch)

**Tests:** 50+ revenue capture + event + queue tests  
**Evidence:** Test coverage report + event audit trail sample

---

### Phase 2: Permissions + Security (Week 5)

**Status:** ⚠️ In Progress  
**Deliverables:**
- [x] Permission guard on tenant self-audit enhanced endpoint
- [x] Tenant isolation validation for tenant report access
- [ ] Cross-module permission enforcement
- [x] Focused regression tests added for permission + cross-tenant denial

**Blockers Fixed:**
- AN-009 (Permission check)
- AN-014 (Tenant isolation)

**Tests:** 15+ permission + isolation tests  
**Evidence:** Permission test matrix + access control audit

---

### Phase 3: Payroll Integration (Week 6)

**Status:** ✅ Core Complete  
**Deliverables:**
- [x] Add policy snapshot column to payroll_runs
- [x] Shared read lock on policy reads during payroll draft rebuild
- [x] Policy version stored in payroll run columns
- [x] Replace hardcoded payroll data with real queries
- [x] 12+ payroll integration tests (focused suite added and passing)

**Blockers Fixed:**
- AN-004 (Payroll concurrency)
- AN-011 (Hardcoded data)

**Tests:** 12+ payroll + locking tests  
**Evidence:** Payroll test coverage + concurrency test results

---

### Phase 4: Monthly Close + Reporting (Week 7-8)

**Status:** ⚠️ In Progress (core anomaly fixes done; non-anomaly reporting hardening still pending)  
**Deliverables:**
- [x] Lock on monthly close (Cache lock)
- [x] Grace period after month-end (24h)
- [x] N+1 dashboard query replaced with grouped aggregation
- [ ] Historical snapshot anchoring (full report layer)
- [x] Tax code error handling
- [x] Monthly close race condition fix
- [x] 18+ reporting tests (global suite includes this coverage; dedicated perf benchmark still pending)

**Blockers Fixed:**
- AN-002 (Close race)
- AN-006 (N+1 query)
- AN-013 (Snapshot consistency)
- AN-015 (Payroll/close interaction)
- AN-016 (Tax code error)

**Tests:** 18+ reporting + dashboard + monthly close tests  
**Evidence:** Performance benchmark report + dashboard response time logs

---

### Phase 5: UI/UX Implementation & User Experience (Week 9-10)

**Status:** ⚠️ In Progress  
**Deliverables:**
- [ ] Dashboard UI (global admin view)
- [ ] Anomaly registry visualization
- [x] Policy event history
- [x] Tenant compliance summary
- [x] 10+ dashboard tests
- [~] Role-based interfaces: Global Admin, Tenant Admin, Finance, HR, Employee
- [ ] Policy management dashboard + editor
- [x] Employee tax profiles + bulk import UI
- [x] Governance audit reports + reconciliation view
- [ ] Tax calculation transparency (employee self-service)
- [ ] Mobile responsive design (all pages)
- [~] Accessibility audit (WCAG 2.1 AA compliance)
- [ ] UX testing with 27+ sessions (5 roles × 3-6 scenarios each)
- [ ] User documentation (help system, tooltips, guides)
- [ ] Design system tokens (colors, typography, components)

**Tests:** 40+ UI + UX + accessibility tests
**Evidence:**
- Full local gate pass after Phase 5 wiring fix: PHPUnit + Vitest green, `166/166` tests passed.
- New Phase 5 wiring tests pass: `backend/tests/ui/tax-governance-phase5.wiring.test.js` (`16/16`).
- Implemented files: `backend/resources/views/tax-rates.blade.php`, `backend/routes/web.php`, `frontend/resources/js/tax-employee-profiles.js`, `frontend/resources/js/tax-tenant-compliance.js`, `backend/resources/views/layout/partials/footer-scripts.blade.php`.
- Remaining evidence pending: formal UX session logs + full WCAG audit report + design token documentation.

---

### Phase 6: Governance Dashboard & Monitoring (Week 11)

**Status:** ⏳ Planning  
**Deliverables:**
- [ ] Dashboard projection listener fixed + projection synced
- [ ] Real-time anomaly registry visualization
- [ ] Policy event history timeline
- [ ] Tenant compliance dashboard + KPI cards
- [ ] Anomaly detection alerts + notifications
- [ ] 15+ dashboard monitoring tests

**Tests:** 15+ monitoring + real-time tests  
**Evidence:** Live dashboard test with 50+ tenants + monitoring alert logs

---

### Phase 7-9: Finalization, Migration, and Launch (Week 12-16)

**Summary:** UUID migration completion, production hardening, rollout validation

---

## Documentation Files (Consolidated into 3)

### 1. **README.md** (Business Overview)
- Executive summary + status
- Revenue streams explanation
- Domain isolation (critical)
- Business flow end-to-end
- Known limitations & scalability ceiling
- Performance benchmarks
- Architecture decisions
- **Size:** ~18KB

### 2. **IMPLEMENTATION.md** (Complete Technical Guide)
- **Part 1:** Executive summary + current status
- **Part 2:** All 16 anomalies detailed (code location + fix + effort)
- **Part 3:** Performance & scalability analysis
- **Part 4:** Cross-module impact matrix
- **Part 5:** Pre-launch critical fixes checklist
- **Part 6:** Phase roadmap (9 phases)
- **Part 7:** UI/UX design system + role flows
- **Part 8:** Risk assessment
- **Part 9:** Failure modes & recovery
- **Size:** ~34KB

### 3. **tracker.md** (This File — Status Tracking)
- Project status summary
- Critical issues tracker
- Phase implementation plan
- Phase evidence checklist
- Completion tracking
- **Size:** ~14KB

### Files to Delete (Merged into above 3)
- ❌ ANOMALIES.md (merged into IMPLEMENTATION.md Part 2)
- ❌ DECISION.md (merged into README.md)
- ❌ DEEP-ANALYSIS-ANOMALIES-SCALABILITY.md (merged into IMPLEMENTATION.md Part 3)
- ❌ PRE-LAUNCH-CRITICAL-FIXES-CHECKLIST.md (merged into IMPLEMENTATION.md Part 5)
- ❌ SAAS-FINANCIAL-PLATFORM.md (reference kept in IMPLEMENTATION.md, can archive)

---

## Evidence Checklist by Phase

### Phase 0 Evidence

- [ ] Migration script created (8 tables, full target)
- [x] FK constraints defined (schema-compatible subset implemented)
- [ ] Connection pool config updated
- [ ] Database backup created
- [x] Migration test passed locally
- [x] Compatibility check log (no conflicts)
- [ ] Schema diagram (visual reference)

### Phase 1 Evidence

- [x] Event listener audit trail (3 files created)
- [x] Retry queue implementation + tests
- [x] Idempotency key code review
- [x] Queue monitoring metrics
- [x] Clearing status job + tests
- [x] Event dispatch uncommented + tests
- [x] Projection synced sample (query results)
- [x] 50+ tests passing log
- [x] Local test gate passes

### Phase 2 Evidence

- [x] Permission enforcement tests (15+ passing)
- [x] Tenant isolation test matrix (5+ scenarios)
- [x] Route guard matrix built: 321 routes mapped, guard type per endpoint identified (2026-04-27)
- [x] 23 flagged admin-only endpoints with missing forbidden-path tests identified (2026-04-27)
- [x] Forbidden-path tests patched: Attendance (smart-attendance, schedule-timing, shifts), Payroll (work-arrangement, work-profiles, payroll-items/export), Tax Governance (dashboard, anomaly-registry, report-export), Billing (invoices), Notifications (delivery-details, export, retry, templates), Email-settings (2026-04-27)
- [x] Forbidden-path tests patched (round 2): Employees (employees/export, departments/export, designations/export, policies/export), Tickets (assignable-users, storeCategory, updateCategory, destroyCategory) (2026-04-27)
- [x] Access control audit (cross-module check): Leave ✅ already covered, Assets ✅ already covered, Training ✅ already covered, Performance ✅ already covered; no gaps remaining (2026-04-27)
- [x] 403 response samples (proof of enforcement)
- [x] Tax governance permission audit artifact created (`docs/features/tax-governance/PERMISSION-AUDIT-2026-04-27.md`)
- [x] Cross-module endpoint inventory artifact created (`docs/features/tax-governance/CROSS-MODULE-ENDPOINT-INVENTORY-2026-04-27.md`)

### Phase 3 Evidence

- [x] Payroll schema migration (policy_id column)
- [x] Shared policy lock implementation + tests
- [x] Hardcoded data replaced (query diff)
- [ ] Concurrency test results (2 parallel builds, dedicated run log)
- [x] 12+ tests passing

### Phase 4 Evidence

- [x] Lock implementation for monthly close
- [x] Dashboard query rewrite (N+1 → GROUP BY)
- [ ] Performance benchmark (before/after)
- [x] Monthly close test results
- [x] 18+ tests passing

---

## Completion Tracking

| Phase | Anomalies Fixed | Tests Added | Status | Target Completion |
|-------|-----------------|-------------|--------|-------------------|
| Phase 0 | AN-005, AN-012 | 5+ | ⚠️ Partial (AN-005 fixed, AN-012 deferred) | Week 1-2 |
| Phase 1 | AN-001, 003, 007, 008, 010 | 50+ | ✅ Core Complete (AN-001/003/007/008/010 fixed) | Week 3-4 |
| Phase 2 | AN-009, 014 | 15+ | ✅ Cross-module forbidden-path audit complete (all modules covered) | Week 5 |
| Phase 3 | AN-004, 011 | 12+ | ✅ Core Complete | Week 6 |
| Phase 4 | AN-002, 006, 013, 015, 016 | 18+ | ⚠️ In Progress (anomaly fixes complete, benchmark/report hardening pending) | Week 7-8 |
| Phase 5 | - (No anomalies) | 40+ | ⚠️ In Progress (employee tax profiles + tenant compliance + wiring tests complete) | Week 9-10 |
| Phase 6 | - | 15+ | ⏳ Planning | Week 11 |
| Phases 7-9 | - | 25+ | ⏳ Planning | Week 12-16 |
| **TOTAL** | **16** | **175+** | **15 anomalies closed/fixed, 1 deferred (AN-012)** | **16 weeks** |

---

## Detailed Execution To-Do Queue (Living Backlog)

Rule:
- Status only uses: `[x] done`, `[ ] pending`.
- A task can be marked done only when evidence artifact or command output exists.
- AN-012 must remain deferred unless explicit architecture decision changes.

### Stream A — Security / Permission / Tenant Isolation (Cross-Module)

- [x] TG-TODO-001 Create tax governance permission audit artifact with executable evidence.
- [x] TG-TODO-002 Build full endpoint inventory for `/v1/hcm/*` grouped by module (employees, attendance, payroll, leave, tax, tickets, training, performance, assets).
- [x] TG-TODO-003 Map each endpoint to middleware guard and controller-level permission check. (Evidence: 321-route guard matrix built, 2026-04-27)
- [x] TG-TODO-004 Produce matrix: endpoint -> required role/permission -> test file coverage. (Evidence: matrix produced with guard type per endpoint — ensureHcmAdmin / ensurePermission / isGlobalHcmAdmin / no-auth-check, 2026-04-27)
- [x] TG-TODO-005 Identify endpoints that are admin-only by business contract but missing explicit forbidden-path test. (Evidence: 23 flagged endpoints identified from matrix, 2026-04-27)
- [x] TG-TODO-006 Add missing forbidden-path tests for Employees module. (Evidence: `test_non_admin_cannot_access_export_endpoints` added to HcmEmployeeApiTest — covers GET employees/export + departments/export + designations/export + policies/export → 403 AUTH_FORBIDDEN; 9 assertions PASS, gate exit 0, 2026-04-27)
- [x] TG-TODO-007 Add missing forbidden-path tests for Attendance module. (Evidence: smart-attendance + schedule-timing + shifts forbidden paths patched; full suite PASS, 2026-04-27)
- [x] TG-TODO-008 Add missing forbidden-path tests for Payroll module. (Evidence: work-arrangement + work-profiles + payroll-items/export forbidden paths patched; HcmPayrollItemApiTest 28 passed, 2026-04-27)
- [x] TG-TODO-009 Add missing forbidden-path tests for Leave module. (Evidence: already covered — LeaveTypeCatalogApiTest `test_non_admin_cannot_manage_leave_types`, LeaveSettingsApiTest `test_non_hcm_admin_cannot_access_leave_settings`, LeaveRequestsApiTest export has no guard; no gap, verified 2026-04-27)
- [x] TG-TODO-010 Add missing forbidden-path tests for Asset module. (Evidence: already covered — HcmAssetApiTest `test_view_only_permission_can_list_assets_but_cannot_mutate_assets_or_categories` covers POST mutation → AUTH_FORBIDDEN; no gap, verified 2026-04-27)
- [x] TG-TODO-011 Add missing forbidden-path tests for Ticket module. (Evidence: `test_non_admin_cannot_access_ticket_admin_only_endpoints` added to TicketApiTest — covers GET assignable-users + POST/PUT/DELETE categories → 403 AUTH_FORBIDDEN; 10 assertions PASS, gate exit 0, 2026-04-27)
- [x] TG-TODO-012 Add missing forbidden-path tests for Training module. (Evidence: already covered — TrainingApiTest `test_trainings_admin_only_crud` + `test_training_type_admin_crud_and_employee_forbidden_mutation`; no gap, verified 2026-04-27)
- [x] TG-TODO-013 Add missing forbidden-path tests for Performance module. (Evidence: already covered — PerformanceApiTest `test_manager_is_forbidden_for_admin_only_endpoints` covers cycles/indicator-templates/reviews; no gap, verified 2026-04-27)
- [x] TG-TODO-014 Add missing forbidden-path tests for Tax Governance module (if uncovered endpoint appears). (Evidence: governance/dashboard + governance/anomalies + reports/tenant-self-audit-export forbidden paths added; HcmTaxGovernanceApiTest 6 passed 65 assertions, 2026-04-27)
- [ ] TG-TODO-015 Verify tenant boundary checks for all endpoints accepting `company_id` query/body.
- [ ] TG-TODO-016 Add regression tests for cross-tenant access denial where missing.
- [ ] TG-TODO-017 Run focused security test batch and archive result log.
- [ ] TG-TODO-018 Update `docs/planning/active-hcm-templates-and-permissions.md` for any discovered mismatch.
- [ ] TG-TODO-019 Update tracker Phase 2 evidence checklist with module-level completion notes.
- [ ] TG-TODO-020 Publish final cross-module access control audit report.

### Stream B — Payroll Integration Hardening Evidence (Phase 3 residual)

- [ ] TG-TODO-021 Create dedicated concurrency test plan (2 parallel payroll draft rebuild jobs).
- [ ] TG-TODO-022 Prepare reproducible dataset for concurrency test (policy + period + lines).
- [ ] TG-TODO-023 Execute parallel run scenario A (same company, same period, same policy).
- [ ] TG-TODO-024 Execute parallel run scenario B (same company, policy publish race window).
- [x] TG-TODO-025 Capture run snapshots (`hcm_tax_governance_policy_id/version` consistency). (Evidence: `HcmPayrollApiTest` now covers effective published policy snapshot on monthly draft run plus future-dated policy exclusion; assertions verify `hcm_tax_governance_policy_id/version`, `meta.taxGovernancePolicy`, and `pph21_ter` source metadata, 2026-04-28)
- [x] TG-TODO-026 Validate no mixed policy assignment in resulting payroll runs. (Evidence: `TaxGovernancePhase3Phase4AnomalyFixTest::test_policy_snapshot_remains_consistent_across_publish_window_between_periods` memastikan snapshot run April tetap policy v1 saat policy v2 dipublish untuk periode Mei; tidak terjadi kontaminasi lintas periode, 2026-04-28)
- [x] TG-TODO-027 Document lock behavior and transaction boundaries in evidence doc. (Evidence: `PayrollDraftBuilder` memilih policy published efektif dengan `sharedLock()` di dalam `DB::transaction`, dan regression test Phase3/4 merekam konsistensi snapshot policy-id/version + meta policy UUID per run, 2026-04-28)
- [ ] TG-TODO-028 Add any missing assertions to `TaxGovernancePhase3Phase4AnomalyFixTest`.
- [x] TG-TODO-029 Re-run focused payroll anomaly suite. (Evidence: `php artisan test tests/Feature/TaxGovernancePhase3Phase4AnomalyFixTest.php` PASS 13 tests; `php artisan test tests/Feature/HcmPayrollApiTest.php` PASS 17 tests; `npx vitest run tests/ui/payroll-run.wiring.test.js` PASS 10 tests, 2026-04-28)
- [ ] TG-TODO-030 Mark Phase 3 evidence item "concurrency test results" complete with artifact link.

### Stream C — Phase 4 Reporting/Close Hardening (post-anomaly)

- [ ] TG-TODO-031 Define benchmark scope for dashboard and monthly close reporting.
- [ ] TG-TODO-032 Capture baseline query timings (before optimization reference, if still available).
- [ ] TG-TODO-033 Capture current query timings for dashboard aggregate endpoints.
- [ ] TG-TODO-034 Capture current close-job duration metrics for one monthly period.
- [ ] TG-TODO-035 Compare baseline vs current and document delta.
- [ ] TG-TODO-036 Validate lock contention behavior for monthly close lock key.
- [ ] TG-TODO-037 Validate retry/requeue behavior when grace period not elapsed.
- [ ] TG-TODO-038 Validate warning path when `platform_expense_tax_codes` empty.
- [ ] TG-TODO-039 Add/refresh tests if benchmark analysis reveals regression risk.
- [ ] TG-TODO-040 Update Phase 4 evidence checklist with benchmark artifact references.

### Stream D — Schema / Ops Readiness (Phase 0 unfinished items)

- [ ] TG-TODO-041 Confirm full target table roadmap against current implemented tables.
- [ ] TG-TODO-042 Document schema-compatible FK subset and intentionally skipped FKs (uuid-vs-id constraints).
- [ ] TG-TODO-043 Prepare migration compatibility report for MySQL/MariaDB and SQLite test runtime.
- [ ] TG-TODO-044 Prepare DB backup rehearsal SOP for pre-production migration.
- [ ] TG-TODO-045 Execute backup rehearsal on staging-like environment and capture duration.
- [ ] TG-TODO-046 Execute rollback rehearsal and capture recovery validation.
- [ ] TG-TODO-047 Tune connection pool proposal (10 -> 50) with expected load assumptions.
- [ ] TG-TODO-048 Validate pool proposal against infra limits and DB config.
- [ ] TG-TODO-049 Create visual schema diagram for implemented tax-governance financial tables.
- [ ] TG-TODO-050 Link schema diagram and migration compatibility outputs in tracker.

### Stream E — API/Docs Sync and Governance Pack

- [ ] TG-TODO-051 Reconcile tracker statuses with IMPLEMENTATION.md anomaly table.
- [ ] TG-TODO-052 Reconcile tracker statuses with README business summary.
- [ ] TG-TODO-053 Ensure deferred AN-012 rationale is identical across all docs.
- [ ] TG-TODO-054 Verify docs/api/openapi.yaml unchanged or updated if any contract changed.
- [ ] TG-TODO-055 Run `bash scripts/check-api-docs-sync.sh` and archive output.
- [ ] TG-TODO-056 Add "evidence bundle" section with command logs and dates.
- [ ] TG-TODO-057 Add unresolved risks section with owner and mitigation ETA.
- [ ] TG-TODO-058 Add dependency map: what blocks Phase 5 launch.
- [ ] TG-TODO-059 Add acceptance criteria per unfinished stream (A-E).
- [ ] TG-TODO-060 Freeze tracker wording to avoid contradictory status phrasing.

### Stream F — Phase 5-9 Planning Breakdown (still genuinely pending)

- [ ] TG-TODO-061 Define Phase 5 backlog per role flow (Global Admin, Tenant Admin, Finance, HR, Employee).
- [ ] TG-TODO-062 Define UI acceptance criteria per role journey.
- [ ] TG-TODO-063 Define accessibility acceptance criteria and audit tooling.
- [ ] TG-TODO-064 Define UX test protocol and evidence template.
- [ ] TG-TODO-065 Define Phase 6 monitoring metrics and alert thresholds.
- [ ] TG-TODO-066 Define anomaly alert routing and escalation policy.
- [ ] TG-TODO-067 Define incident runbook skeleton for governance/reporting failures.
- [ ] TG-TODO-068 Define Phase 7 UUID migration closure plan and data safety checks.
- [ ] TG-TODO-069 Define Phase 8 production hardening checklist with ownership.
- [ ] TG-TODO-070 Define Phase 9 rollout/launch validation and go-no-go criteria.

### Stream G — Release Gate and Closure Discipline

- [x] TG-TODO-071 Ensure all newly added tests are included in local gate execution. (Evidence: all new tests inside Feature/ discovered by PHPUnit auto-loader, 2026-04-27)
- [x] TG-TODO-072 Run full local gate after each major stream completion. (Evidence: rerun local gate pass after Phase 5 wiring fix; PHPUnit + Vitest 166/166, exit 0, 2026-04-27)
- [x] TG-TODO-073 Archive gate outputs (date/time + pass/fail) for audit trail. (Evidence: local-test-gate exit 0, 2026-04-27 ~22:22 WIB)
- [ ] TG-TODO-074 Verify no hidden contract drift before PR finalization.
- [ ] TG-TODO-075 Verify no unresolved migration error in clean environment.
- [ ] TG-TODO-076 Update final completion statement from "anomaly fixed" to "evidence-closed" only after artifacts exist.
- [ ] TG-TODO-077 Prepare final closure note for stakeholder update.
- [ ] TG-TODO-078 Prepare PR summary separated by code change vs governance documentation.
- [ ] TG-TODO-079 Re-run full gate on final branch state before merge.
- [ ] TG-TODO-080 Mark tracker final status only after all required evidence links present.

---

## Financial Impact Summary

### Revenue at Risk (1000 employees)

| Scenario | Amount | Probability | Mitigation |
|----------|--------|-------------|-----------|
| Revenue not captured (AN-001) | $50-100K/mo | HIGH | Phase 1 atomicity |
| Reconciliation gap (AN-003) | $10-50K/mo | HIGH | Phase 1 clearing |
| Report discrepancy (AN-002) | $5-10K/mo | MEDIUM | Phase 4 lock |
| Payroll inconsistency (AN-004) | 1-5% loss | MEDIUM | Phase 3 lock |

**Total at-risk (historical baseline before Phase 1-4 fixes):** $65-165K/month

---

## Rollout Checklist (Pre-Production)

**MUST COMPLETE before Phase 5 (Production Launch):**

- [ ] All anomalies disposition finalized (15 fixed + AN-012 deferred decision approved) + code reviewed
- [ ] All 135+ tests passing locally + GitHub
- [ ] Database backup + migration tested
- [ ] Monitoring + alerting configured
- [ ] Operational runbook created
- [ ] Finance team sign-off
- [ ] Security team sign-off
- [ ] Performance benchmarks approved
- [ ] Documentation reviewed (README + IMPLEMENTATION)
- [ ] Runbook tested with ops team
- [ ] Failure mode tests passed
- [ ] Cross-module integration tests passed

---

## Key Metrics to Track

### Pre-Launch (Phases 0-4)

- Test pass rate (target: 100%)
- Code review completion (target: 2+ per PR)
- Bug discovery rate (trend downward)
- Technical debt (tracked in IMPLEMENTATION.md)

### Production (Phase 5+)

- Revenue transactions/day (baseline: 2000+ at 1000 emp)
- Event queue depth (baseline: <100)
- Dashboard response time (target: <500ms)
- Monthly close duration (target: <5 min)
- Alert false positive rate (target: <5%)

---

## Communication Plan

**Stakeholders:** Product, Engineering, Finance, Security, Ops

**Weekly Updates:**
- Phase progress (completed/pending)
- Blockers + resolution
- Risk status
- Next week's focus

**Pre-Launch (Week 11-12):**
- Full documentation review
- Finance sign-off meeting
- Ops runbook walkthrough
- Go/no-go decision

---

## References

- **Full Technical Details:** [IMPLEMENTATION.md](IMPLEMENTATION.md) (34KB)
- **Business Overview:** [README.md](README.md) (18KB)
- **Architecture Decision:** README.md → Keputusan Produk Final
- **8-Table Schema:** See IMPLEMENTATION.md Part 1
- **Failure Recovery:** IMPLEMENTATION.md Part 9