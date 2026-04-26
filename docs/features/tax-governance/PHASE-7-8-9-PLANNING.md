# Phase 7, 8, 9 Implementation Planning

**Date**: 2026-04-26  
**Status**: Planning Complete, Ready for Execution  
**Target**: Audit Evidence Pack → UUID Migration → Billing Tax Finalization

---

## Phase 7: Audit Evidence Pack

### Objective
Menyiapkan artefak audit tenant dan governance platform yang terstruktur dan terunut untuk compliance reporting.

### Scope

#### 7.1 Export Tenant Self-Audit Pack (API + Data)
- **File**: `HcmTaxGovernanceController::tenantSelfAuditReportExport()` (new)
- **Endpoint**: `GET /v1/hcm/tax-governance/reports/tenant-self-audit-export?company_id=uuid&format=json|pdf`
- **Permission**: `tax.tenant.report.export`
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "company_id": "uuid",
      "company_name": "string",
      "report_generated_at": "2026-04-26T10:00:00Z",
      "period": {
        "start": "2026-01-01",
        "end": "2026-03-31"
      },
      "policy_snapshot": {
        "current_version": 3,
        "effective_from": "2026-02-01",
        "effective_to": null,
        "rule_count": 12,
        "last_published_by": "user_id",
        "last_published_at": "2026-02-01T08:00:00Z"
      },
      "change_history": [
        {
          "version": 1,
          "action": "created",
          "actor_user_id": "uuid",
          "actor_name": "John Doe",
          "timestamp": "2026-01-15T14:30:00Z",
          "change_summary": "Initial policy draft"
        },
        {
          "version": 2,
          "action": "published",
          "actor_user_id": "uuid",
          "actor_name": "Jane Smith",
          "timestamp": "2026-02-01T08:00:00Z",
          "change_summary": "Published after approval"
        }
      ],
      "payroll_coverage": {
        "total_payroll_runs": 45,
        "runs_under_current_policy": 40,
        "runs_under_superseded_policy": 5,
        "coverage_percentage": 88.9
      },
      "compliance_checklist": {
        "has_published_policy": true,
        "has_recent_publication": true,
        "all_payroll_runs_covered": false,
        "no_unresolved_anomalies": true,
        "readiness_score": 0.95
      },
      "anomalies_detected": [
        {
          "anomaly_id": "uuid",
          "type": "POLICY_VERSION_CONFLICT",
          "severity": "warning",
          "description": "5 payroll runs processed under superseded policy version 1",
          "detected_at": "2026-04-20T12:00:00Z",
          "resolved": false
        }
      ],
      "audit_trail": [
        {
          "event_type": "policy_created",
          "timestamp": "2026-01-15T14:30:00Z",
          "actor": "John Doe",
          "details": "Policy draft created with 10 rules"
        },
        {
          "event_type": "policy_submitted",
          "timestamp": "2026-01-20T09:15:00Z",
          "actor": "John Doe",
          "submission_note": "Ready for review"
        },
        {
          "event_type": "policy_approved",
          "timestamp": "2026-01-28T16:45:00Z",
          "actor": "Jane Smith",
          "approval_note": "Approved with minor comments"
        },
        {
          "event_type": "policy_published",
          "timestamp": "2026-02-01T08:00:00Z",
          "actor": "Jane Smith",
          "publish_reason": "Effective immediately"
        }
      ]
    }
  }
  ```
- **PDF Export**: Same data structure, formatted as professional PDF report with headers/footers
- **Authorization**: Tenant user can only export own; global admin can export any
- **Validation**: Period dates within last 2 years; company_id must exist

#### 7.2 Centralized Anomaly Registry Enhancement
- **Model**: `HcmTaxGovernanceAnomaly` (already created in Phase 5)
- **Scope**: Already has full structure, add lifecycle endpoints:
  - `PATCH /v1/hcm/tax-governance/anomalies/{anomaly_id}/resolve` - Mark as resolved
  - `POST /v1/hcm/tax-governance/anomalies/{anomaly_id}/acknowledge` - Acknowledge without resolving
- **Fields to Update**:
  - `resolved_at` (timestamp)
  - `resolution_note` (text)
  - `ack_user_id` (user who acknowledged)
  - `ack_at` (acknowledgment timestamp)

#### 7.3 Publication & Change History Evidence
- **Table**: Use existing `hcm_tax_governance_policy_events` (Phase 4)
- **API Endpoint**: `GET /v1/hcm/tax-governance/policies/{policy_id}/events` (new)
- **Response**: Paginated immutable event log with actor, timestamp, before/after state
- **Permission**: `tax.tenant.policy.view` (read-only)
- **Use Case**: Auditor can trace every change end-to-end without reconstructing history

#### 7.4 Report Generation Service
- **Service**: `Services/TaxGovernanceReportingService.php` (new)
- **Methods**:
  - `generateTenantSelfAuditReport($companyId, $periodStart, $periodEnd)` → returns array
  - `generateAnomalySnapshot($companyId)` → returns array
  - `exportToPdf($reportData)` → returns PDF binary
- **Storage**: Cache reports in S3 or filesystem for audit trail
- **Expiry**: Reports immutable once generated, kept for 7 years per audit requirement

### Deliverables (Phase 7)

- ✅ `HcmTaxGovernanceController::tenantSelfAuditReportExport()` - Export endpoint
- ✅ `Services/TaxGovernanceReportingService.php` - Report generation logic
- ✅ Anomaly lifecycle endpoints (resolve, acknowledge)
- ✅ Event history API endpoint
- ✅ PDF export support (using mPDF or similar)
- ✅ Tests: Export authorization, period validation, PDF structure
- ✅ API docs updated for Phase 7 endpoints

### Exit Criteria (Phase 7)

- [ ] Tenant can export self-audit pack (JSON + PDF)
- [ ] Global admin can export any tenant's pack
- [ ] Anomalies can be resolved/acknowledged with audit trail
- [ ] Event history is immutable and accessible
- [ ] All exports pass compliance timestamp + signature checks
- [ ] 10+ automated tests covering all paths

---

## Phase 8: UUID Migration Execution

### Objective
Menutup jalur numeric ID exposure pada domain tax governance dan memastikan UUID-only contract di production.

### Scope

#### 8.1 Current State Audit
- **Task**: Identify all numeric ID references in tax governance
- **Files to Scan**:
  - Frontend components using tax policy IDs
  - API response contracts
  - Database queries with ID selection
  - Internal service calls
- **Action**: Create migration inventory document

#### 8.2 UUID Backfill Strategy
- **Policy Table**: Already uses UUID in Phase 4 (`id` field is UUID)
- **Projection Table**: Uses UUID (`id` field is UUID) - Phase 5
- **Anomaly Table**: Uses UUID (`id` field is UUID) - Phase 5
- **Action**: Verify all foreign keys use UUID

#### 8.3 Internal Bridge Layer (API Compatibility)
- **Scenario**: Deprecated numeric endpoint path `/v1/hcm/tax-governance/policies/{numeric_id}`
- **Strategy**: 
  - Accept both UUID and numeric in URL during transition period
  - Log telemetry for numeric usage
  - Return 400 with deprecation header after cutoff date
- **Implementation**:
  ```php
  Route::get('/policies/{policy}', [HcmTaxGovernanceController::class, 'show'])
      ->name('tax-governance.policy.show')
      ->where('policy', '[0-9a-f\-]{36}|[0-9]+'); // Accept both UUID and numeric

  // In controller:
  public function show(Request $request, $policy)
  {
      if (is_numeric($policy)) {
          Log::warning('Deprecated numeric ID usage', ['id' => $policy]);
          // Map numeric to UUID or return deprecation error
      }
      // Continue with UUID path
  }
  ```

#### 8.4 Telemetry & Monitoring
- **Track**: Numeric ID requests per endpoint
- **Log**: User, timestamp, endpoint, numeric ID used
- **Alert**: If numeric usage > 5% of traffic, delay deprecation
- **Cutoff**: 90 days after UUID-only enforcement in production

#### 8.5 Documentation & Migration Guide
- **Doc**: `docs/api/uuid-migration-guide.md` (new)
- **Contents**:
  - Deprecation timeline
  - Numeric → UUID mapping reference
  - Testing in staging environment
  - Troubleshooting common errors

### Deliverables (Phase 8)

- ✅ UUID backfill verification (all PKs are UUID)
- ✅ Internal bridge layer for numeric → UUID fallback
- ✅ Telemetry logging for numeric ID usage
- ✅ Deprecation warnings in API responses
- ✅ Migration guide documentation
- ✅ Tests: Numeric ID acceptance, telemetry logging, UUID-only enforcement
- ✅ Monitoring dashboard for numeric usage tracking

### Exit Criteria (Phase 8)

- [ ] All policy IDs in database confirmed as UUID
- [ ] Both UUID and numeric endpoints functional in staging
- [ ] Telemetry logging captured for 14 days
- [ ] Deprecation warnings returned in API responses
- [ ] 5+ automated tests covering migration scenarios
- [ ] No numeric IDs exposed in new endpoints

---

## Phase 9: Platform Billing Tax + Tenant Self-Reporting Finalization

### Objective
Menutup domain platform billing tax sekaligus tenant reporting readiness untuk dual-plane architecture completion.

### Scope

#### 9.1 Billing Tax Policy Engine
- **Model**: `HcmBillingTaxPolicy` (new)
- **Fields**:
  - `id` (UUID PK)
  - `company_id` (UUID FK to tenant)
  - `billing_month` (YYYY-MM)
  - `billing_cycle_type` (enum: monthly, yearly, custom)
  - `tax_rate_percentage` (decimal 0.00-100.00)
  - `base_calculation_method` (enum: fixed, percentage_arr, percentage_mgmt_fee)
  - `effective_from` (date)
  - `effective_to` (nullable date)
  - `status` (enum: draft, active, superseded)
  - `created_by_user_id`, `created_at`
  - `updated_by_user_id`, `updated_at`

#### 9.2 Billing Tax Calculation Service
- **Service**: `Services/BillingTaxCalculationService.php` (new)
- **Methods**:
  - `calculateBillingTax($companyId, $billingMonth)` → returns { amount, rate, base, breakdown }
  - `generateBillingTaxInvoice($companyId, $billingMonth)` → returns invoice object
  - `validateBillingTaxPolicy($policyData)` → returns validation result
- **Logic**:
  - Lookup active policy for month
  - Apply rate to calculation base (ARR/MGT fee/fixed)
  - Return line items for finance reporting

#### 9.3 Billing Tax Reporting API
- **Endpoints**:
  - `GET /v1/platform/billing-tax/reports?month=YYYY-MM&company_id=uuid` (Global Admin only)
  - `GET /v1/platform/billing-tax/invoices?month=YYYY-MM` (Global Admin only)
  - `GET /v1/platform/billing-tax/export?format=json|csv&year=2026` (Global Admin only)
- **Permission**: `billing.tax.report.view` (Global Admin)
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "billing_month": "2026-03",
      "report_generated_at": "2026-04-26T10:00:00Z",
      "total_companies": 25,
      "total_tax_amount": 125000.00,
      "companies": [
        {
          "company_id": "uuid",
          "company_name": "PT XYZ",
          "billing_cycle_type": "monthly",
          "tax_rate": 2.5,
          "base_amount": 1000000.00,
          "tax_amount": 25000.00,
          "status": "invoiced"
        }
      ]
    }
  }
  ```

#### 9.4 Tenant Self-Reporting Endpoint
- **Endpoint**: `GET /v1/hcm/tax-governance/reports/tenant-compliance-status` (new)
- **Permission**: `tax.tenant.report.export`
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "company_id": "uuid",
      "company_name": "string",
      "reporting_period": "2026-Q1",
      "compliance_status": {
        "statutory_tax_compliance": {
          "has_active_policy": true,
          "policy_version": 3,
          "last_publication_date": "2026-02-01",
          "payroll_runs_covered": 40,
          "anomalies_unresolved": 1,
          "readiness_score": 0.92
        },
        "billing_tax_compliance": {
          "billing_cycle_active": true,
          "invoices_issued": 3,
          "invoices_paid": 3,
          "amount_outstanding": 0,
          "payment_status": "current"
        },
        "overall_status": "compliant",
        "next_review_date": "2026-05-26"
      },
      "recommended_actions": [
        {
          "priority": "high",
          "action": "Resolve anomaly: POLICY_VERSION_CONFLICT",
          "target_date": "2026-04-30"
        }
      ]
    }
  }
  ```

#### 9.5 Dashboard Integration
- **Update**: `HcmTaxGovernanceController::dashboardSummary()` (from Phase 5)
- **Add**: Billing tax status to summary
- **New Section**: "Billing Tax Health" with invoice count, payment status, amount due

#### 9.6 Tenant Reporting Template
- **Document**: `docs/features/tax-governance/TENANT-REPORTING-TEMPLATE.md` (new)
- **Contents**: 
  - Quarterly/Annual report template structure
  - Required fields for audit compliance
  - Example filled-out report
  - Export as PDF/Excel

### Deliverables (Phase 9)

- ✅ `HcmBillingTaxPolicy` model with migration
- ✅ `Services/BillingTaxCalculationService.php`
- ✅ 2 new billing tax API endpoints
- ✅ Enhanced tenant self-reporting endpoint
- ✅ Dashboard billing tax section
- ✅ Tenant reporting template documentation
- ✅ Tests: Billing calculation, invoice generation, compliance check
- ✅ API docs updated for Phase 9 endpoints

### Exit Criteria (Phase 9)

- [ ] Billing tax policies can be created and managed
- [ ] Billing tax calculations verified against finance requirements
- [ ] Platform can generate cross-tenant billing tax report
- [ ] Tenants can access their compliance status
- [ ] Both statutory + billing tax domains fully operational
- [ ] 10+ automated tests covering all paths
- [ ] All OpenAPI + docs synced
- [ ] Local test gate passes all tests

---

## Implementation Sequence (Execution Order)

### Phase 7 (Days 1-2)
1. Create `tenantSelfAuditReportExport()` endpoint
2. Create `TaxGovernanceReportingService`
3. Add anomaly resolve/acknowledge endpoints
4. Add event history endpoint
5. Implement PDF export
6. Write comprehensive tests

### Phase 8 (Day 3)
1. Audit numeric ID usage
2. Implement bridge layer
3. Add telemetry logging
4. Add deprecation warnings
5. Create migration guide
6. Write bridge layer tests

### Phase 9 (Days 4-5)
1. Create `HcmBillingTaxPolicy` model + migration
2. Create `BillingTaxCalculationService`
3. Create billing tax API endpoints
4. Enhance tenant compliance endpoint
5. Update dashboard with billing section
6. Create reporting template
7. Write comprehensive tests

---

## Testing Strategy (All Phases)

### Unit Tests
- Report generation logic
- Billing calculations
- Validation rules

### Integration Tests
- Export API with authorization checks
- Anomaly resolution workflow
- UUID migration bridge layer
- Billing tax calculation end-to-end

### E2E Tests (Manual)
- Export tenant pack as tenant user (should succeed)
- Export any tenant pack as global admin (should succeed)
- Export with invalid period (should fail)
- Generate billing tax report (should show all tenants)
- UUID + numeric ID fallback in staging

### Regression Tests
- Phase 4 baseline tests (2/2 must still pass)
- Phase 5 endpoint tests (all must pass)
- Phase 6 negative path tests (all must pass)

---

## Success Metrics

| Metric | Phase 7 | Phase 8 | Phase 9 |
|--------|---------|---------|---------|
| API Endpoints | 4 new | 0 (bridge only) | 3 new |
| Database Changes | 0 (schema exists) | 0 (verify UUID) | 1 new table |
| Test Coverage | 10+ | 5+ | 10+ |
| Documentation | 1 new | 1 new | 1 new |
| Local Test Gate | ✅ Pass | ✅ Pass | ✅ Pass |
| Regressions | 0 | 0 | 0 |

---

## Known Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|-----------|
| PDF export library dependency | Medium | Use mPDF (already in Laravel ecosystem) |
| Numeric ID trace-back in audit log | Medium | Implement telemetry capture before deprecation |
| Billing tax calculation wrong | High | 3-way review: FE calc, API calc, Finance check |
| UUID migration breaks old integrations | High | Implement bridge layer with 90-day deprecation window |
| Compliance report structure insufficient | Medium | Pre-approve with compliance officer before Phase 9 GA |

---

## Rollback Strategy

If any phase fails critical tests:
1. Phase 7 Rollback: Disable export endpoints, keep event log
2. Phase 8 Rollback: Keep bridge layer active indefinitely
3. Phase 9 Rollback: Remove billing endpoints, revert dashboard

---

## Communication & Sign-Off

- Product: Approve compliance structure Phase 7
- Finance: Approve billing tax calculation Phase 9
- Compliance: Review audit trail completeness Phase 7
- QA: Sign off on test coverage all phases
- Security: Validate tenant isolation Phase 7-9
