# Termination Module Enrichment Plan

**Status**: Planning  
**Last Updated**: 2026-05-26  
**Prepared by**: Technical Team  
**Target Completion**: 2026-06 Q1  
**Anomaly Analysis**: ✅ 8 anomalies identified & mitigated (Section 9.2)

---

## Executive Summary

Termination module saat ini sudah memiliki foundation yang solid (lifecycle, workflow stage, settlement preview, clearance asset). Namun, masih ada **3 gap prioritas tinggi** yang perlu diisi untuk membuat feature lebih lengkap dan compliant dengan regulasi PHK Indonesia.

**Planning ini memisahkan pekerjaan menjadi 3 slice independen yang bisa dikerjakan parallel, dengan total effort ~850 baris code dan 280 baris test.**

---

## 1. Current State Analysis

### ✅ What's Already Implemented

| Component | Status | Notes |
|-----------|--------|-------|
| CRUD Termination | ✅ Done | Create/read/update/delete record |
| Lifecycle (pending, approved, finalized, cancelled) | ✅ Done | Status management |
| Workflow stage (draft_review, legal_review, approved_internal, finalized_execution) | ✅ Done | Stage tracking |
| Settlement preview | ✅ Done | Auto-calculate from payroll period |
| Prorata calculation | ✅ Done | Gaji pokok + tunjangan tetap |
| PKWT compensation | ✅ Done | Added if contract due on termination month |
| Clearance asset items | ✅ Done | List outstanding assets |
| Asset return action | ✅ Done | Mark asset returned from context |
| Taxonomy codes | ✅ Done | `terminationReasonCode`, `legalBasisCode` |
| Policy profile mapping | ✅ Done | Map reason + basis to policy profile |
| Non-asset checklist (persistence) | ⚠️ Partial | Data model + guard exist, no endpoint/UI for management |

### 🚧 What's Missing (Gap Analysis)

| Gap | Severity | Impact | Current State |
|-----|----------|--------|---------------|
| **Severance calculation** | 🔴 Critical | Settlement incomplete without severance per UU Ketenagakerjaan | None |
| **Leave payout calculation** | 🔴 Critical | Required for final settlement | None |
| **Workflow stage validation** | 🔴 Critical | No enforcement of state transition rules | Stage exists but no validation |
| **Approval trail enforcement** | 🔴 Critical | Cannot audit who approved at each stage | No actor/timestamp enforcement |
| **Checklist item endpoints** | 🟠 High | Cannot manage checklist from API | Only batch field in main record |
| **Immutable legal snapshot** | 🟡 Medium | No audit-lock after finalization | Fields are mutable |
| **Leave balance check** | 🟡 Medium | Cannot verify leave remaining before settlement | No integration |

---

## 2. Complete Impact Analysis

### 2.0 Files Impacted by All Slices

**Files that will definitely change:**
1. **Controller**: `backend/app/Http/Controllers/Api/Termination/HcmTerminationController.php`
   - `update()` — Add settlement calculation logic
   - `settlementPreview()` — Call calculator service
   - `settlementPreviewByUser()` — Call calculator service

2. **Model**: `backend/app/Models/HcmTermination.php`
   - Add new fields (if needed)
   - Add relationships to related models

3. **Routes**: `backend/routes/api/termination.php`
   - May add new endpoints for checklist management (Slice C)
   - Existing routes remain but enhanced

4. **Tests**: 
   - `backend/tests/Feature/TerminationApiTest.php` — Add new test cases
   - `backend/tests/ui/termination-api-contract.test.js` — Update contract expectations
   - `backend/tests/ui/termination.wiring.test.js` — Update UI wiring tests

5. **API Documentation**:
   - `docs/api/hcm-termination-api.md` — Document new fields and endpoints
   - `docs/api/openapi.yaml` — Update OpenAPI spec

6. **Feature Documentation**:
   - `docs/features/termination/README.md` — Update with new flow
   - `docs/features/termination/IMPLEMENTATION.md` — Update technical details
   - `docs/features/termination/tracker.md` — Update status and evidence

### 2.1 Slice A Impact (Severance + Leave Payout)

**New Files to Create:**
```
backend/app/Services/TerminationSettlementCalculationService.php  (280 LOC)
backend/app/DataClasses/TerminationSettlementBreakdown.php         (45 LOC)
backend/config/termination-policy-profiles.php                    (50 LOC)
```

**Files to Modify:**
| File | Changes | Line Est |
|------|---------|----------|
| `HcmTerminationController.php` | Add service injection, call calculator in `update()` + preview methods | +60 LOC |
| `HcmTermination.php` | Add fillable fields for settlement breakdown | +10 LOC |
| `TerminationApiTest.php` | Add 8-10 test cases for calculation scenarios | +150 LOC |
| `termination-api-contract.test.js` | Update response expectations | +20 LOC |

**New Database Fields** (Migration: `2026_05_XX_000000_add_settlement_breakdown_to_hcm_terminations.php`):
```sql
ALTER TABLE hcm_terminations ADD COLUMN severance_amount DECIMAL(15,2) NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN service_award_amount DECIMAL(15,2) NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN benefit_substitution DECIMAL(15,2) NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN leave_payout DECIMAL(15,2) NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN prorata_base_salary DECIMAL(15,2) NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN calculation_method VARCHAR(50) DEFAULT 'policy_based';
```

**Routes Affected:**
- `PUT /v1/hcm/terminations/{id}` — Response now includes breakdown
- `GET /v1/hcm/terminations/{id}/settlement-preview` — Uses calculator
- `GET /v1/hcm/terminations/settlement-preview` — Uses calculator
- `GET /v1/hcm/terminations` — List response includes breakdown summary
- `GET /v1/hcm/terminations/{id}` — Detail response includes breakdown
- `POST /v1/hcm/terminations` — Create initializes with calculated breakdown

**Backward Compatibility:**
- ✅ Existing fields remain unchanged
- ✅ New fields are additive (no removal)
- ✅ Existing clients can ignore new breakdown fields
- ✅ No breaking changes to API contract

**Test Scenarios to Add:**
```
1. Calculate severance for redundancy case (PHK alasan pengurangan beban)
2. Calculate severance for misconduct case (PHK alasan kesalahan berat)
3. Calculate with service award (UPMK)
4. Calculate with benefit substitution (UPH)
5. Include PKWT if contract due
6. Calculate leave payout from balance
7. Deduction calculation correct (PPh, BPJS, dll)
8. Net payable = total gross - deduction
9. Handle missing leave balance gracefully
10. Invalid policy profile returns 422
```

### 2.2 Slice B Impact (Workflow Validation + Approval Trail)

**New Files to Create:**
```
backend/app/Services/TerminationWorkflowValidator.php  (120 LOC)
backend/app/DataClasses/WorkflowAuditEvent.php         (30 LOC)
```

**Files to Modify:**
| File | Changes | Line Est |
|------|---------|----------|
| `HcmTerminationController.php` | Add validation + audit logging in `update()` | +80 LOC |
| `HcmTermination.php` | Add workflow history relationship + JSON casting | +15 LOC |
| `TerminationApiTest.php` | Add 6-8 workflow validation test cases | +100 LOC |

**New Database Fields** (Migration: `2026_05_XX_000000_add_workflow_approval_trail.php`):
```sql
ALTER TABLE hcm_terminations ADD COLUMN workflow_history JSON NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN last_reviewed_by UUID NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN last_reviewed_at TIMESTAMP NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN approved_by UUID NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN approved_at TIMESTAMP NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN finalized_by UUID NULLABLE;
ALTER TABLE hcm_terminations ADD COLUMN finalized_at TIMESTAMP NULLABLE;
```

**Routes Affected:**
- `PUT /v1/hcm/terminations/{id}` — Now validates workflow transitions
- `GET /v1/hcm/terminations/{id}` — Response includes workflow history + approval trail

**Backward Compatibility:**
- ✅ Existing workflow_stage field unchanged
- ✅ New fields are informational (audit trail)
- ✅ Invalid transitions now return 422 (breaking but necessary for governance)

**Test Scenarios to Add:**
```
1. Valid transition draft_review → legal_review allowed
2. Invalid transition draft_review → finalized_execution blocked (422)
3. Valid transition legal_review → approved_internal allowed
4. Role-based transition: non-legal cannot approve legal_review
5. Transition to cancelled always allowed with reason
6. Cannot go backward (finalized → draft_review blocked)
7. Workflow history populated on every transition
8. Approval timestamps recorded correctly
```

### 2.3 Slice C Impact (Checklist Item Management)

**New Files to Create:**
```
backend/app/Models/HcmTerminationChecklistItem.php     (50 LOC model + relationships)
backend/app/Http/Requests/ChecklistItemRequest.php    (40 LOC)
backend/app/Http/Requests/CompleteChecklistItemRequest.php (35 LOC)
backend/app/Http/Resources/ChecklistItemResource.php  (45 LOC)
```

**Files to Modify:**
| File | Changes | Line Est |
|------|---------|----------|
| `HcmTerminationController.php` | Add 5 new methods for checklist CRUD | +150 LOC |
| `HcmTermination.php` | Add `hasMany` relationship to checklist items | +5 LOC |
| `TerminationApiTest.php` | Add 8-10 checklist-specific test cases | +140 LOC |
| `termination.wiring.test.js` | Add checklist item creation/completion flows | +40 LOC |

**New Database Table** (Migration: `2026_05_XX_000000_create_hcm_termination_checklist_items_table.php`):
```sql
CREATE TABLE hcm_termination_checklist_items (
  id UUID PRIMARY KEY,
  termination_id BIGINT NOT NULL,
  label VARCHAR(255) NOT NULL,
  description TEXT NULLABLE,
  owner_name VARCHAR(100) NULLABLE,
  due_date DATE NOT NULL,
  mandatory BOOLEAN DEFAULT false,
  status ENUM('open', 'completed', 'skipped') DEFAULT 'open',
  completed_by UUID NULLABLE,
  completed_at TIMESTAMP NULLABLE,
  completion_evidence TEXT NULLABLE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP NULLABLE,   -- Anomaly #3: Soft delete, bukan hard delete
  -- Anomaly #3: RESTRICT (bukan CASCADE) — cegah delete termination yang masih punya items
  FOREIGN KEY (termination_id) REFERENCES hcm_terminations(id) ON DELETE RESTRICT,
  INDEX idx_termination_id (termination_id),
  INDEX idx_status (status)
);
```

> ⚠️ **Anomaly #3 (Cascade Delete Prevention)**: FK constraint menggunakan `ON DELETE RESTRICT`. Termination dengan checklist items yang masih ada tidak dapat dihapus langsung. Wajib hapus items dulu, atau termination sudah dalam status `cancelled`/`pending` sebelum boleh dihapus. Termination dengan status `approved` atau `finalized` **tidak boleh dihapus sama sekali** (hard block di controller).

**New Routes** (All under `/v1/hcm/terminations/{terminationId}/checklist-items`):
```
POST /v1/hcm/terminations/{id}/checklist-items
  - Create new checklist item
  - Request: {label, ownerName, dueDate, mandatory, description}
  - Response: 201 Created with full item

GET /v1/hcm/terminations/{id}/checklist-items
  - List all checklist items
  - Response: array of items + progress meta

PATCH /v1/hcm/terminations/{id}/checklist-items/{itemId}
  - Update item (label, dueDate, owner, etc)
  - Response: updated item

PATCH /v1/hcm/terminations/{id}/checklist-items/{itemId}/complete
  - Mark item as completed
  - Request: {completionEvidence}
  - Response: item with completed_at + completed_by

DELETE /v1/hcm/terminations/{id}/checklist-items/{itemId}
  - Remove item
  - Response: 200 success
```

**Routes to Modify:**
- `PUT /v1/hcm/terminations/{id}` — Finalization blocked if mandatory items open

**Backward Compatibility:**
- ✅ Existing `nonAssetChecklist` field in main record still works
- ✅ New endpoints are additive (no removal)
- ✅ Main termination update still accepts batch checklist (legacy)
- ⚠️ Finalization validation now stricter (mandatory → required completion)

**Test Scenarios to Add:**
```
1. Create new checklist item
2. List checklist items with progress meta
3. Update item (label, dueDate)
4. Mark item as completed with evidence
5. Delete checklist item
6. Cannot finalize if mandatory items open (422)
7. Can finalize if all mandatory items completed
8. Cannot complete non-existent item (404)
9. Role authorization: admin only
10. Checklist items cascade delete when termination deleted
```

---

## 3. Gap Priority & Sequencing

### Recommended Execution Order

```
Phase 1 (Foundation)       Phase 2 (Governance)      Phase 3 (Usability)
┌──────────────────┐      ┌─────────────────┐       ┌──────────────────┐
│ Slice A:         │      │ Slice B:        │       │ Slice C:         │
│ Severance +      │  →   │ Workflow        │   →   │ Checklist        │
│ Leave Payout     │      │ Validation      │       │ Item Mgmt        │
│ 350 LOC          │      │ 200 LOC         │       │ 280 LOC          │
└──────────────────┘      └─────────────────┘       └──────────────────┘

Effort: ~830 LOC code + 280 LOC test
Timeline: 3-4 weeks (parallel possible on backend)
```

---

## 4. Slice A: Severance + Leave Payout Calculator

### 4.1 Business Context

**Regulatory Basis** (Indonesia)
- UU Ketenagakerjaan No. 13 Tahun 2003
- PP No. 35 Tahun 2021 (Kompensasi PKWT)
- Komponen hak minimal saat PHK:
  - **Pesangon**: dasar perhitungan gaji
  - **UPMK** (Uang Penghargaan Masa Kerja): bonus masa kerja
  - **UPH** (Uang Penggantian Hak): bonus jaminan sosial
  - **Kompensasi PKWT**: jika ada kontrak yang belum berakhir
  - **Cuti**: payout sisa cuti yang belum diambil
  - **Pesangon + UPMK + UPH**: structure berbeda per alasan PHK

**Current Limitation**
- Settlement sekarang hanya: prorata gaji + tunjangan tetap + PKWT
- Tidak ada severance/pesangon calculation
- Tidak ada leave payout
- Tidak memisahkan komponen hak per kategori kasus

### 4.2 Technical Design

#### 4.2.1 New Service: `TerminationSettlementCalculationService`

**Location**: `backend/app/Services/TerminationSettlementCalculationService.php`

**Input**:
```php
/**
 * Calculate termination settlement breakdown
 * 
 * @param string $employeeUuid
 * @param Carbon $terminationDate
 * @param string $terminationReasonCode
 * @param string $legalBasisCode
 * @param array $options ['includeLeaveBalance' => true, 'taxProfile' => null]
 * @return TerminationSettlementBreakdown
 */
```

**Output Data Class**: `TerminationSettlementBreakdown`
```php
class TerminationSettlementBreakdown {
    public ?float $severanceAmount;        // Pesangon
    public ?float $serviceAwardAmount;    // UPMK
    public ?float $benefitSubstitution;   // UPH
    public ?float $leavePayout;           // Sisa cuti
    public ?float $prorataBaseSalary;     // Gaji pokok prorata
    public ?float $pkwtCompensation;      // Kompensasi PKWT (existing)
    public ?float $totalGross;
    public ?float $deduction;
    public ?float $netPayable;
    public array $breakdown;              // Detailed per component
    public string $calculationMethod;     // 'policy_based' | 'manual_override'
    public array $evidenceSnapshot;       // Formula version, period used, etc
}
```

#### 4.2.2 Policy Engine: `TerminationPolicyProfile`

**Config Location**: `backend/config/termination-policy-profiles.php`

```php
return [
    'PHK_CAUSE_EMPLOYEE_MISCONDUCT' => [
        'severance' => ['method' => 'base_salary_months', 'months' => 1],
        'serviceAward' => ['method' => 'disabled'],
        'benefitSubstitution' => ['method' => 'disabled'],
    ],
    'PHK_REASON_REDUNDANCY' => [
        'severance' => ['method' => 'base_salary_months', 'months' => 2],
        'serviceAward' => ['method' => 'service_months_divided', 'divisor' => 12],
        'benefitSubstitution' => ['method' => 'mandatory'],
    ],
    // ... more profiles per legal basis
];
```

#### 4.2.3 Algorithm: Severance Calculation

**Case: Redundancy (PHK alasan pengurangan beban)**
```
Severance = Monthly Base Salary × 2 (per UU)
Service Award = (Total Service Months / 12) × Monthly Base Salary
Benefit Substitution = Mandatory component per regulation

ProrataBaseSalary = Monthly Base Salary × (Working Days / Total Days in Month)
Leave Payout = Leave Balance × (Daily Rate)

Total Gross = Severance + ServiceAward + BenefitSubstitution + 
              ProrataBaseSalary + LeavePayout + PKWT (if applicable)
```

**Case: Employee Misconduct (PHK alasan kesalahan berat)**
```
Severance = 1 × Monthly Base Salary (reduced)
Service Award = 0 (waived)
Benefit Substitution = 0 (waived)

ProrataBaseSalary = Monthly Base Salary × (Working Days / Total Days in Month)
Leave Payout = Leave Balance × (Daily Rate)

Total Gross = Severance + ProrataBaseSalary + LeavePayout
```

#### 4.2.4 Leave Balance Integration (with Graceful Fallback — Anomaly #4)

**Dependency**: `EmployeeLeaveBalance` model + `LeaveService`

**⚠️ Wajib: Jangan silent fallback ke zero** (anomaly #4 — Leave Balance Service Failure).

```php
// In TerminationSettlementCalculationService::calculateLeavePayout()
// SALAH — jangan lakukan ini:
// $leavePayout = $this->leaveService->getActiveBalance($uuid) ?? 0.0;

// BENAR — explicit failure handling:
try {
    $leaveBalance = $this->leaveService->getActiveBalance($employeeUuid);
    $dailyRate = $employee->monthly_base_salary / 22; // Standard working days
    $leavePayout = $leaveBalance * $dailyRate;
    $leaveBalanceAvailable = true;
} catch (LeaveServiceUnavailableException | ServiceTimeoutException $e) {
    $leavePayout = null;       // NULL = belum dikalkulasi, BUKAN zero
    $leaveBalanceAvailable = false;
    Log::warning('TerminationSettlement: leave balance unavailable', [
        'employee_uuid' => $employeeUuid,
        'error' => $e->getMessage(),
    ]);
}

return new TerminationSettlementBreakdown(
    leavePayout: $leavePayout,
    leaveBalanceAvailable: $leaveBalanceAvailable,
    // ... other fields
);
```

**Finalization guard** (controller level):
```php
// Blok finalization jika leave_payout NULL tanpa konfirmasi eksplisit:
if ($breakdown->leavePayout === null && !$request->boolean('manualLeavePayoutConfirmed')) {
    return response()->json([
        'error' => 'LEAVE_BALANCE_PENDING',
        'message' => 'Komponen cuti belum dikalkulasi karena service tidak tersedia. Coba lagi atau isi manualLeavePayoutOverride dengan nilai (bisa 0 jika karyawan tidak memiliki sisa cuti).',
    ], 422);
}
```

#### 4.2.5 Evidence Snapshot — Anti Staleness (Anomaly #1)

**Wajib**: Setiap kalkulasi settlement harus menyimpan snapshot data yang digunakan, bukan hanya angka hasil.

```php
// Simpan dalam settlement_breakdown.evidence_snapshot:
$breakdown->evidenceSnapshot = [
    'hire_date_used'          => $employee->hire_date_at->toDateString(),
    'base_salary_used'        => $employee->monthly_base_salary,
    'monthly_allowance_used'  => $employee->monthly_allowance,
    'service_months_used'     => $serviceMonths,
    'leave_balance_used'      => $leaveBalance,
    'payroll_period_used'     => $payrollPeriod->id ?? null,
    'snapshot_at'             => now()->toISOString(),
    'formula_version'         => self::POLICY_FORMULA_VERSION,
];
```

**Pre-finalization drift check** (di controller, saat transisi ke `finalized_execution`):
```php
$snap = $termination->settlement_breakdown['evidence_snapshot'] ?? null;
if ($snap) {
    $currentEmployee = /* re-fetch fresh */;
    $hireDateDrift  = $snap['hire_date_used'] !== $currentEmployee->hire_date_at->toDateString();
    $salaryDrift    = abs($snap['base_salary_used'] - $currentEmployee->monthly_base_salary) > 0.01;

    if ($hireDateDrift || $salaryDrift) {
        return response()->json([
            'error'   => 'SETTLEMENT_DATA_STALE',
            'message' => 'Data karyawan berubah sejak kalkulasi terakhir. Re-kalkulasi settlement sebelum finalisasi.',
            'drifted_fields' => array_filter([
                'hire_date'   => $hireDateDrift,
                'base_salary' => $salaryDrift,
            ]),
        ], 422);
    }
}
```

#### 4.2.6 Tax Calculation — Reuse Payroll Service (Anomaly #8)

**Wajib**: Deduction PPh21 harus menggunakan service yang sama dengan payroll, bukan custom formula.

```php
// JANGAN buat formula sendiri:
// $pph21 = $grossAmount * 0.15; // BERBAHAYA — hasil beda dengan payroll

// BENAR — inject dan gunakan service yang sama:
public function __construct(
    private readonly LeaveService $leaveService,
    private readonly PayrollTaxCalculationService $taxService, // sama persis dengan payroll
) {}

// Dalam kalkulasi:
$deduction = $this->taxService->calculateForSettlement(
    grossAmount: $totalGross,
    taxProfile: $employee->tax_profile,
    settlementDate: $terminationDate,
);
```

#### 4.2.7 Multi-Tenant Guard (Anomaly #6)

**Wajib**: Setiap query employee/payroll/leave data di dalam service harus scoped ke company.

```php
// SALAH:
$employee = User::find($employeeUuid); // Tidak ada company scope

// BENAR:
$employee = User::where('company_id', $this->companyId)->where('uuid', $employeeUuid)->firstOrFail();
// Otomatis 404 jika employee bukan milik company ini
```

**Endpoint**: `PUT /v1/hcm/terminations/{id}`

**New Fields in Request**:
```json
{
  "terminationReasonCode": "PHK_REDUNDANCY",
  "legalBasisCode": "UU13_2003_ARTICLE_151",
  "workflowStage": "finalized_execution",
  "settleWithCalculatedBreakdown": true,
  "manualOverrideBreakdown": null
}
```

**New Fields in Response** (detail/list):
```json
{
  "id": 123,
  "userId": "uuid",
  "terminationDate": "2026-06-30",
  "status": "finalized",
  "workflowStage": "finalized_execution",
  "settlement": {
    "breakdown": {
      "severanceAmount": 5000000,
      "serviceAwardAmount": 2500000,
      "benefitSubstitution": 1500000,
      "leavePayout": 750000,
      "prorataBaseSalary": 1500000,
      "pkwtCompensation": 0,
      "totalGross": 11250000,
      "deduction": 1687500,
      "netPayable": 9562500
    },
    "calculationMethod": "policy_based",
    "formulaVersion": "2026-05-01",
    "policyProfileKey": "PHK_REDUNDANCY"
  }
}
```

### 4.3 Implementation Checklist

- [ ] Create `TerminationSettlementCalculationService`
- [ ] Create `TerminationSettlementBreakdown` data class
- [ ] Create `config/termination-policy-profiles.php`
- [ ] Add leave balance lookup integration
- [ ] Update `HcmTerminationController::updateTermination()` to call calculator
- [ ] Update database schema (add settlement breakdown fields if not exist)
- [ ] Update API response structure in transformer
- [ ] Add calculator to settlement preview endpoint
- [ ] Update `docs/api/hcm-termination-api.md`
- [ ] Update `docs/api/openapi.yaml`

### 4.4 Testing Strategy

**Unit Tests** (`tests/Unit/Services/TerminationSettlementCalculationServiceTest.php`)
```
✓ Calculate severance for redundancy case
✓ Calculate severance for misconduct case
✓ Calculate service award correctly
✓ Include PKWT if contract due
✓ Handle missing leave balance gracefully
✓ Validate policy profile exists
✓ Throw exception for unknown reason code
✓ Deduction calculation correct
✓ Net payable calculation correct
```

**Feature Tests** (`tests/Feature/TerminationApiTest.php` additions)
```
✓ PUT /terminations/{id} with settleWithCalculatedBreakdown=true
✓ Settlement breakdown present in response
✓ Formula version persisted
✓ Manual override can replace calculated breakdown
✓ List terminations shows summary breakdown
✓ Detail terminations shows full breakdown
```

**Regression** (existing tests must still pass)
```
php artisan test tests/Feature/TerminationApiTest.php
npm run test:ui -- tests/ui/termination-api-contract.test.js
```

### 4.5 Effort Estimate

| Component | LOC | Notes |
|-----------|-----|-------|
| TerminationSettlementCalculationService | 280 | Core calculation logic |
| TerminationSettlementBreakdown DTO | 45 | Data structure |
| Config/policy-profiles | 50 | Policy definitions |
| API endpoint updates | 60 | Response transformation |
| Database schema (if needed) | 20 | Migration for new fields |
| Tests | 150 | Unit + feature test cases |
| **Total** | **605** | ~3-4 days solo or 2 days pair |

---

## 5. Slice B: Workflow Stage Validation + Approval Trail

### 4.1 Business Context

**Current Problem**
- Admin dapat langsung jump dari `draft_review` → `finalized_execution` tanpa governance
- Tidak ada audit trail siapa approve di tiap stage
- Tidak ada enforcement bahwa legal review sudah dikerjakan sebelum approval

**Compliance Requirement**
- Setiap transisi stage harus tercatat: siapa, kapan, catatan
- Beberapa stage require approval dari role tertentu (e.g., legal review dari IR staff)
- Tidak boleh skip mandatory stage

### 4.2 Technical Design

#### 5.2.1 State Machine Rules

**Valid Transitions**:
```
pending → draft_review (auto, saat create)
draft_review → legal_review (manual, by HR/admin)
legal_review → approved_internal (manual, by legal/IR + HR approval)
approved_internal → finalized_execution (manual, by payroll/HR)
any_stage → cancelled (manual, by admin)

Invalid transitions blocked with 422:
- draft_review → finalized_execution (skip legal_review)
- legal_review → pending (backward not allowed)
- finalized_execution → draft_review (backward not allowed)
```

#### 5.2.2 Approval Trail Data Structure

**Database Fields** (add to `hcm_terminations`):
```php
// In migration: 2026_05_XX_000000_add_workflow_approval_trail.php
Schema::table('hcm_terminations', function (Blueprint $table) {
    $table->json('workflow_history')->nullable(); // [{stage, actor_id, actor_name, timestamp, note}, ...]
    $table->uuid('last_reviewed_by')->nullable();
    $table->timestamp('last_reviewed_at')->nullable();
    $table->uuid('approved_by')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->uuid('finalized_by')->nullable();
    $table->timestamp('finalized_at')->nullable();
    // Anomaly #2: Optimistic lock — mencegah concurrent stage update corrupt JSON
    $table->unsignedInteger('workflow_version')->default(0);
});
```

**Audit Event Structure**:
```php
class WorkflowAuditEvent {
    public string $stage;              // draft_review, legal_review, etc
    public string $action;             // 'moved_to', 'approved', 'rejected', 'cancelled'
    public string $actorId;            // User UUID
    public string $actorName;          // User name for readability
    public string $actorRole;          // 'hr_admin', 'legal_ir', 'payroll_admin'
    public Carbon $timestamp;
    public ?string $note;              // Free text reason/comment
    public ?string $previousStage;     // Where we came from
}
```

#### 5.2.3 Concurrent Update Protection (Anomaly #2)

**Wajib**: Setiap workflow stage update harus menggunakan optimistic lock + DB transaction untuk mencegah concurrent writes mengcorrupt `workflow_history` JSON.

```php
// In HcmTerminationController::update() — workflow stage change:
DB::transaction(function () use ($termination, $request) {
    // 1. Pessimistic lock — satu writer pada satu waktu
    $termination = HcmTermination::where('company_id', $activeCompanyId)
        ->where('id', $termination->id)
        ->lockForUpdate()
        ->firstOrFail();

    // 2. Optimistic lock — tolak jika sudah berubah sejak GET
    if ($termination->workflow_version !== (int) $request->input('workflowVersion')) {
        abort(409, 'WORKFLOW_CONFLICT: Termination sudah diubah oleh pengguna lain. Refresh dan coba lagi.');
    }

    // 3. Append audit event ke history (bukan overwrite)
    $history   = $termination->workflow_history ?? [];
    $history[] = WorkflowAuditEvent::make($request->user(), $newStage, $previousStage, $request->input('workflowNote'));
    $termination->workflow_history  = $history;
    $termination->workflow_version += 1;   // Increment versi
    $termination->workflow_stage    = $newStage;
    $termination->save();
});
```

**Request wajib sertakan `workflowVersion`** (dari respons GET sebelumnya):
```json
{
  "workflowStage": "legal_review",
  "workflowVersion": 3,
  "workflowNote": "Konsultasi dengan legal sudah selesai"
}
```

**Response include updated version**:
```json
{
  "workflowStage": "legal_review",
  "workflowVersion": 4,
  "workflowHistory": [...]
}
```

#### 5.2.4 Transition Validator

**New Class**: `TerminationWorkflowValidator`

```php
class TerminationWorkflowValidator {
    /**
     * Check if transition is allowed
     */
    public function isTransitionAllowed(
        string $currentStage,
        string $targetStage,
        User $actor,
        ?string $skipReason = null
    ): TransitionResult {
        // Rule: draft_review → legal_review hanya jika HR admin
        // Rule: legal_review → approved_internal hanya jika legal/IR
        // Rule: Tidak boleh skip stage (e.g., draft → finalized)
        // Rule: Cancelled dapat dari any stage dengan reason
    }

    /**
     * Get available next stages
     */
    public function getAvailableTransitions(string $currentStage): array {
        // Return [draft_review → legal_review, cancelled]
    }
}
```

#### 5.2.4 API Contract Changes

**Endpoint**: `PUT /v1/hcm/terminations/{id}`

**New Fields in Request**:
```json
{
  "workflowStage": "legal_review",
  "workflowAction": "move_to_stage",  // or "approve", "reject", "cancel"
  "workflowNote": "Konsultasi dengan legal sudah selesai, persiapan final approve"
}
```

**New Fields in Response** (detail):
```json
{
  "id": 123,
  "workflowStage": "legal_review",
  "workflowHistory": [
    {
      "stage": "draft_review",
      "action": "created",
      "actorId": "uuid1",
      "actorName": "Budi Santoso",
      "actorRole": "hr_admin",
      "timestamp": "2026-05-26T10:00:00Z",
      "note": null,
      "previousStage": null
    },
    {
      "stage": "legal_review",
      "action": "moved_to_stage",
      "actorId": "uuid2",
      "actorName": "Hendra Wijaya",
      "actorRole": "hr_admin",
      "timestamp": "2026-05-26T14:30:00Z",
      "note": "Siap untuk legal review",
      "previousStage": "draft_review"
    }
  ],
  "lastReviewedBy": "Hendra Wijaya",
  "lastReviewedAt": "2026-05-26T14:30:00Z",
  "approvedBy": null,
  "approvedAt": null,
  "finalizedBy": null,
  "finalizedAt": null
}
```

### 5.3 Implementation Checklist

- [ ] Create `TerminationWorkflowValidator` class
- [ ] Create migration for workflow history fields
- [ ] Create `WorkflowAuditEvent` data class
- [ ] Add transition validation to `HcmTerminationController::updateTermination()`
- [ ] Add workflow history logging on every update
- [ ] Add response formatter to include workflow history
- [ ] Add validation: mandatory stage cannot be skipped
- [ ] Add role-based stage transition checks
- [ ] Update `docs/api/hcm-termination-api.md` with workflow rules
- [ ] Update `docs/api/openapi.yaml`

### 5.4 Testing Strategy

**Unit Tests** (`tests/Unit/TerminationWorkflowValidatorTest.php`)
```
✓ Valid transition draft_review → legal_review allowed
✓ Invalid transition draft_review → finalized_execution blocked (422)
✓ Valid transition legal_review → approved_internal allowed if legal approved
✓ Role check: non-legal cannot move to legal_review
✓ Transition to cancelled always allowed
✓ Cannot go backward (finalized → draft_review blocked)
```

**Feature Tests** (`tests/Feature/TerminationApiTest.php` additions)
```
✓ PUT with workflow stage change creates audit entry
✓ Workflow history populated correctly
✓ Invalid transition returns 422 with reason
✓ Approval timestamps recorded
✓ Multiple transitions maintain history chain
✓ Cancelled transition records reason
```

### 5.5 Effort Estimate

| Component | LOC | Notes |
|-----------|-----|-------|
| TerminationWorkflowValidator | 120 | State machine + transition rules |
| Migration (workflow history) | 25 | Database schema change |
| WorkflowAuditEvent DTO | 30 | Data structure |
| Controller update | 80 | Validation + logging integration |
| Response transformer | 40 | Format workflow history |
| Tests | 100 | Unit + feature cases |
| **Total** | **395** | ~2-3 days solo |

---

## 6. Slice C: Checklist Item Management Endpoints

### 6.1 Business Context

**Current Problem**
- Non-asset checklist (handover pekerjaan, akses sistem, dokumen legal) ada di data model tapi tidak bisa di-manage from API
- Hanya bisa set saat create/update termination record (batch update, tidak user-friendly)
- Tidak ada endpoint untuk mark item completed

**User Workflow Expected**
1. HR buat termination record
2. HR view checklist items di modal
3. HR add/remove checklist items sesuai kebutuhan
4. Departemen/tim terkait mark item done (bisa async)
5. Sistem block finalization jika item mandatory masih open

### 6.2 Technical Design

#### 6.2.1 New Endpoints

**Create Item**:
```
POST /v1/hcm/terminations/{terminationId}/checklist-items

Request:
{
  "label": "Return company laptop",
  "ownerName": "IT Department",
  "dueDate": "2026-06-15",
  "mandatory": true,
  "description": "Ensure all equipment is accounted for"
}

Response:
{
  "id": "uuid",
  "terminationId": 123,
  "label": "Return company laptop",
  "ownerName": "IT Department",
  "dueDate": "2026-06-15",
  "mandatory": true,
  "status": "open",  // open | completed | skipped
  "completedBy": null,
  "completedAt": null,
  "completionEvidence": null,
  "createdAt": "2026-05-26T10:00:00Z"
}
```

**Complete Item**:
```
PATCH /v1/hcm/terminations/{terminationId}/checklist-items/{itemId}/complete

Request:
{
  "completionEvidence": "Equipment returned and logged in IT system"
}

Response:
{
  "id": "uuid",
  "label": "Return company laptop",
  "status": "completed",
  "completedBy": "Hendra Wijaya",
  "completedAt": "2026-05-26T14:00:00Z",
  "completionEvidence": "Equipment returned and logged in IT system"
}
```

**Update Item**:
```
PATCH /v1/hcm/terminations/{terminationId}/checklist-items/{itemId}

Request:
{
  "label": "Return company laptop and phone",
  "dueDate": "2026-06-20"
}
```

**Delete Item**:
```
DELETE /v1/hcm/terminations/{terminationId}/checklist-items/{itemId}
```

**List Items**:
```
GET /v1/hcm/terminations/{terminationId}/checklist-items

Response:
{
  "data": [
    {
      "id": "uuid1",
      "label": "Return company laptop",
      "status": "open",
      "mandatory": true,
      ...
    },
    {
      "id": "uuid2",
      "label": "Handover projects",
      "status": "completed",
      "mandatory": true,
      ...
    }
  ],
  "meta": {
    "totalItems": 5,
    "mandatoryItems": 4,
    "completedItems": 1,
    "completionProgress": 25
  }
}
```

#### 6.2.2 Data Model (Database)

**New Table**: `hcm_termination_checklist_items`

```php
Schema::create('hcm_termination_checklist_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->unsignedBigInteger('termination_id');
    // Anomaly #3: RESTRICT — cegah orphan data, tapi juga cegah silent cascade delete
    $table->foreign('termination_id')
        ->references('id')
        ->on('hcm_terminations')
        ->onDelete('restrict');  // Bukan CASCADE

    $table->string('label');
    $table->text('description')->nullable();
    $table->string('owner_name')->nullable();
    $table->date('due_date');
    $table->boolean('mandatory')->default(false);

    $table->enum('status', ['open', 'completed', 'skipped'])->default('open');
    $table->uuid('completed_by')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->text('completion_evidence')->nullable();

    $table->timestamps();
    $table->softDeletes(); // Anomaly #3: Soft delete — audit trail tidak hilang
    $table->index('termination_id');
    $table->index('status');
});
```

#### 6.2.3 Finalized Termination Delete Guard (Anomaly #3)

**Wajib**: Controller `delete()` wajib block penghapusan record yang sudah di-approve atau finalized.

```php
public function delete(Request $request, int $id): JsonResponse
{
    $termination = HcmTermination::where('company_id', $activeCompanyId)->findOrFail($id);

    // Anomaly #3: Hard block — approved/finalized tidak boleh dihapus
    if (in_array($termination->status, ['approved', 'finalized'])) {
        return response()->json([
            'error'   => 'DELETION_FORBIDDEN',
            'message' => 'Termination dengan status approved atau finalized tidak dapat dihapus. Gunakan status cancelled untuk membatalkan.',
        ], 403);
    }

    $termination->delete(); // SoftDeletes — data tidak benar-benar hilang
    return response()->json(['success' => true]);
}
```

#### 6.2.4 Multi-Tenant Scope for Checklist Items (Anomaly #6)

**Wajib**: Checklist item selalu diakses melalui termination yang sudah discope ke company. Jangan query `HcmTerminationChecklistItem` langsung tanpa validasi termination ownership.

```php
// Benar — Scope melalui termination yang sudah diverifikasi company:
$termination = HcmTermination::where('company_id', $activeCompanyId)->findOrFail($terminationId);
$item = $termination->checklistItems()->findOrFail($itemId);

// Salah — Langsung query item tanpa verifikasi company:
// $item = HcmTerminationChecklistItem::find($itemId); // JANGAN
```

#### 6.2.5 Validation Rules

```php
// In ChecklistItemRequest
public function rules() {
    return [
        'label' => 'required|string|max:255',
        'ownerName' => 'nullable|string|max:100',
        'dueDate' => 'required|date|after_or_equal:today',
        'mandatory' => 'boolean',
        'description' => 'nullable|string|max:1000',
    ];
}

// In CompleteChecklistItemRequest
public function rules() {
    return [
        'completionEvidence' => 'required|string|max:500',
    ];
}
```

#### 6.2.4 Finalization Guard

**In `HcmTerminationController::updateTermination()`**:

```php
// Before allowing finalized_execution status
if ($targetStage === 'finalized_execution') {
    $mandatoryOpen = $termination->checklistItems()
        ->where('mandatory', true)
        ->whereNotIn('status', ['completed', 'skipped'])
        ->count();
    
    if ($mandatoryOpen > 0) {
        return response()->json([
            'error' => 'Cannot finalize: ' . $mandatoryOpen . ' mandatory checklist items still open',
            'openItems' => $termination->checklistItems()
                ->where('mandatory', true)
                ->whereNotIn('status', ['completed', 'skipped'])
                ->pluck('label')
        ], 422);
    }
}
```

### 6.3 Implementation Checklist

- [ ] Create `ChecklistItemRequest` form request class
- [ ] Create `CompleteChecklistItemRequest` form request class
- [ ] Create migration for `hcm_termination_checklist_items` table
- [ ] Create `HcmTerminationChecklistItem` model
- [ ] Add controller methods for CRUD + complete action
- [ ] Add routes under `/v1/hcm/terminations/{id}/checklist-items/*`
- [ ] Add validation in finalization guard
- [ ] Create resource/transformer for API responses
- [ ] Update main termination response to include checklist items
- [ ] Update `docs/api/hcm-termination-api.md`
- [ ] Update `docs/api/openapi.yaml`

### 6.4 Testing Strategy

**Unit Tests** (`tests/Unit/Models/HcmTerminationChecklistItemTest.php`)
```
✓ Model relationships work
✓ Validation rules enforced
✓ Status transitions valid
```

**Feature Tests** (`tests/Feature/TerminationChecklistApiTest.php`)
```
✓ POST create checklist item
✓ PATCH update checklist item
✓ DELETE remove checklist item
✓ PATCH complete item with evidence
✓ GET list checklist items with progress meta
✓ Cannot finalize if mandatory items open (422)
✓ Can finalize if mandatory items completed
✓ Cannot complete non-existent item (404)
✓ Role authorization: admin only
```

**Regression** (existing termination tests must still pass)
```
php artisan test tests/Feature/TerminationApiTest.php
```

### 6.5 Effort Estimate

| Component | LOC | Notes |
|-----------|-----|-------|
| ChecklistItemRequest validation | 40 | Form request classes |
| HcmTerminationChecklistItem model | 50 | Model + relationships |
| Controller methods (CRUD + complete) | 120 | 5 endpoints |
| Migration | 30 | Table schema |
| Resource/transformer | 45 | API response formatting |
| Finalization guard logic | 30 | Validation on update |
| Tests | 140 | Unit + feature cases |
| **Total** | **455** | ~3 days solo or 2 days pair |

---

## 7. Implementation Timeline & Dependencies

### Phase 1: Foundation (Slice A - Severance/Leave Payout)
**Duration**: Week 1-2  
**Team**: 1-2 backend engineers  
**Blockers**: None - can start immediately  
**Output**:
- Calculation service tested and verified
- Settlement breakdown stored in DB
- API response includes breakdown
- Integration tests pass
- Docs updated

**Success Criteria**:
- ✅ All calculation scenarios tested (redundancy, misconduct, etc)
- ✅ Leave balance integration works
- ✅ 150+ LOC tests all pass
- ✅ Settlement preview shows severance/leave payout

---

### Phase 2: Governance (Slice B - Workflow Validation)
**Duration**: Week 2-3  
**Team**: 1 backend engineer  
**Blockers**: None - independent from Slice A  
**Dependencies**: Can be done parallel with Slice A  
**Output**:
- State machine enforced
- Approval trail recorded
- API returns workflow history
- Transition validation working

**Success Criteria**:
- ✅ Cannot skip mandatory stage
- ✅ Workflow history populated on every change
- ✅ Invalid transitions return 422
- ✅ Audit trail complete and accurate

---

### Phase 3: Usability (Slice C - Checklist Management)
**Duration**: Week 3-4  
**Team**: 1 backend engineer  
**Blockers**: None - can run parallel  
**Output**:
- Checklist CRUD endpoints working
- Item completion tracked with evidence
- Finalization guard enforced
- Integration with termination flow complete

**Success Criteria**:
- ✅ Can create/update/delete checklist items
- ✅ Can mark items complete with evidence
- ✅ Finalization blocks if mandatory items open
- ✅ All checklist scenarios tested

---

## 8. Definition of Done (Per Slice)

### For Each Slice

- [ ] Code written and reviewed
- [ ] All tests passing (PHPUnit + Vitest)
- [ ] API contract documented in OpenAPI spec
- [ ] `docs/api/hcm-termination-api.md` updated with new endpoints/fields
- [ ] `docs/api/openapi.yaml` synchronized
- [ ] No breaking changes to existing API (backward compatible)
- [ ] Migration tested on fresh + existing DB
- [ ] Local test gate passes: `bash scripts/local-test-gate.sh`
- [ ] Artifact built and verified
- [ ] No regressions in related features (employee, payroll, asset)

### Anomaly Prevention Gates (Mandatory — No Slice is "Done" Without These)

| Gate | Slice | Test Wajib | Status |
|------|-------|-----------|--------|
| Evidence snapshot stored in settlement_breakdown | A | `evidence_snapshot_stored_in_settlement_breakdown` | ❌ Belum |
| Pre-finalization drift check blocks on salary/hire_date change | A | `finalize_blocked_when_employee_salary_changed_after_calculation` | ❌ Belum |
| Leave balance NULL (not zero) when service fails | A | `settlement_flags_leave_unavailable_when_service_fails` | ❌ Belum |
| Finalization blocked if leave_payout is NULL tanpa konfirmasi | A | `finalization_blocked_if_leave_payout_is_null` | ❌ Belum |
| Tax calculation reuses PayrollTaxCalculationService | A | `settlement_tax_uses_same_service_as_payroll` | ❌ Belum |
| Multi-tenant: cannot access other company termination | A/B/C | `cannot_access_termination_from_different_company_returns_404` | ❌ Belum |
| Concurrent workflow update returns 409 Conflict | B | `concurrent_stage_update_returns_409_conflict` | ❌ Belum |
| Workflow version increments setiap stage change | B | `workflow_version_increments_on_every_stage_change` | ❌ Belum |
| Cannot delete approved termination | C | `cannot_delete_approved_termination_returns_403` | ❌ Belum |
| Cannot delete finalized termination | C | `cannot_delete_finalized_termination_returns_403` | ❌ Belum |
| Checklist item soft-deleted (masih queryable) | C | `soft_deleted_checklist_items_still_queryable` | ❌ Belum |
| Checklist item FK is RESTRICT (bukan CASCADE) | C | Migration integrity test | ❌ Belum |

> ⚠️ **Total: 12 anomaly gates. Semua wajib PASS sebelum tiap slice di-merge.**

### Integration Testing

- [ ] Slice A → Slice B: Settlement breakdown carries through approval trail
- [ ] Slice B → Slice C: Cannot finalize if checklist items open AND approved
- [ ] All three slices: End-to-end termination flow with all new features
- [ ] **All 12 anomaly gates pass** (added per risk analysis Section 9.2)

---

## 9. Risk Assessment & Mitigation

### 9.1 Implementation Risks

| Risk | Severity | Mitigation |
|------|----------|-----------|
| Severance formula complexity | Medium | Start with 2 main cases (redundancy, misconduct), expand later |
| Leave balance not available | Medium | Graceful fallback + flag `leave_balance_unavailable=true`; admin can manual override |
| Workflow state conflicts | Medium | DB transaction + pessimistic lock + optimistic version field |
| Backward compatibility break | High | Keep existing fields immutable; add new fields additive only |
| Test coverage incomplete | High | Require >80% code coverage; add negative test cases |
| Database migration performance | Low | Test on staging with large dataset; optimize if needed |

### 9.2 Anomaly Prevention Design (wajib diimplementasi bersamaan Slice A/B/C)

Analisis mendalam terhadap pola integrasi antar modul mengidentifikasi **8 potential anomalies** yang harus diantisipasi dalam design. Setiap anomali wajib dimitigasi SEBELUM implementasi Slice terkait dimulai.

---

#### Anomaly #1 — Data Staleness: Settlement Based on Stale Employee Data 🔴 CRITICAL

**Scenario**:
- Settlement dikalkulasi T=10:00 (berdasarkan `hire_date=2020-01-01`, `base_salary=8jt`)
- Data employee diubah T=11:00 (`hire_date=2021-01-01` karena input error correction)
- Settlement breakdown yang tersimpan = **stale, inaccurate**
- Finalization di-approve dengan angka yang salah

**Root Cause**: Settlement calculation menggunakan live employee data, tapi hasilnya disimpan sebagai snapshot tanpa merecord dari mana data itu berasal.

**Mitigation** (baked into Slice A design):
1. **Employee data snapshot** — Saat settlement dikalkulasi, simpan `evidence_snapshot` di `settlement_breakdown` field:
   ```json
   {
     "evidence_snapshot": {
       "hire_date_used": "2020-01-01",
       "base_salary_used": 8000000,
       "monthly_allowance_used": 1500000,
       "snapshot_at": "2026-05-26T10:00:00Z",
       "formula_version": "2026.04.id.v1"
     }
   }
   ```
2. **Pre-finalization drift check** — Saat transisi ke `finalized_execution`, re-fetch employee data dan compare dengan snapshot. Jika ada selisih pada `hire_date` atau `base_salary`, return 422:
   ```
   "Kritical data berubah sejak kalkulasi terakhir. Silakan re-kalkulasi settlement sebelum finalisasi."
   ```
3. **Lock critical fields** — Setelah `finalized_execution`, field `settlement_breakdown` tidak dapat diubah (guard di controller).

**Test Case to Add**:
- `finalize_blocked_when_employee_salary_changed_after_calculation`
- `finalize_blocked_when_hire_date_changed_after_calculation`
- `evidence_snapshot_stored_in_settlement_breakdown`

---

#### Anomaly #2 — Workflow Concurrency: Concurrent Stage Updates Corrupt JSON 🟠 HIGH

**Scenario**:
- Admin A: GET termination → status `draft_review`, sends PUT → `legal_review`
- Admin B (milliseconds later): GET termination → status still `draft_review`, sends PUT → `approved_internal`
- Both writes land → `workflow_history` JSON corrupt, stage = `approved_internal` (skipped legal_review)

**Root Cause**: Tanpa optimistic lock, dua concurrent PUT bisa overwrite state yang sama.

**Mitigation** (baked into Slice B design):
1. **Optimistic lock via `workflow_version` field** — Add `workflow_version INT DEFAULT 0` ke tabel. Setiap update increment versi:
   ```php
   // Request wajib sertakan versi saat GET
   { "workflowStage": "legal_review", "workflowVersion": 3 }
   
   // Controller: Validate version match
   if ($termination->workflow_version !== $request->workflowVersion) {
       return response()->json(['error' => 'WORKFLOW_CONFLICT', 'message' => 'Termination sudah diubah oleh pengguna lain. Refresh dan coba lagi.'], 409);
   }
   
   // Update + increment versi dalam satu transaction
   DB::transaction(function() use ($termination) {
       $termination->lockForUpdate(); // Pessimistic lock
       $termination->workflow_version++;
       $termination->save();
   });
   ```
2. **DB-level transaction** — Setiap workflow transition wrapped dalam `DB::transaction()`.
3. **409 Conflict response** — Return HTTP 409 (not 422) when version mismatch, dengan message yang actionable.

**New DB Column** (add to Slice B migration):
```sql
ADD COLUMN workflow_version INT NOT NULL DEFAULT 0
```

**Test Case to Add**:
- `concurrent_stage_update_returns_409_conflict`
- `workflow_version_increments_on_every_stage_change`

---

#### Anomaly #3 — Cascade Delete: Finalized Termination Audit Trail Destroyed 🟠 HIGH

**Scenario**:
- Termination sudah finalized, checklist items semua completed, payment done
- Admin (by mistake) calls DELETE `/v1/hcm/terminations/{id}`
- Cascade delete: semua checklist items terhapus
- Audit trail untuk compliance = **gone forever**

**Root Cause**: Kurangnya guard pada delete finalized records + cascade delete pada checklist items FK.

**Mitigation** (baked into Slice C design):
1. **Hard block delete on finalized/approved records**:
   ```php
   public function delete(Request $request, int $id): JsonResponse
   {
       if (in_array($termination->status, ['approved', 'finalized'])) {
           return response()->json([
               'error' => 'DELETION_FORBIDDEN',
               'message' => 'Termination yang sudah di-approve atau finalized tidak dapat dihapus. Gunakan status cancelled jika perlu dibatalkan.',
           ], 403);
       }
       // Soft delete only
       $termination->delete(); // Uses SoftDeletes trait (already exists)
   }
   ```
2. **Checklist items: NO cascade delete on FK** — Gunakan `ON DELETE RESTRICT` bukan `CASCADE`:
   ```php
   // In migration:
   $table->foreign('termination_id')
       ->references('id')
       ->on('hcm_terminations')
       ->onDelete('restrict'); // Tidak allow delete termination jika masih ada items
   ```
3. **Soft delete untuk checklist items** — Add `SoftDeletes` trait ke `HcmTerminationChecklistItem`.

**Test Case to Add**:
- `cannot_delete_approved_termination_returns_403`
- `cannot_delete_finalized_termination_returns_403`
- `soft_deleted_checklist_items_still_queryable`

---

#### Anomaly #4 — Leave Balance Service Failure: Zero Payout Silently 🟠 HIGH

**Scenario**:
- Employee berhak atas 12 hari cuti (senilai ~2jt)
- Saat kalkulasi settlement, Leave Service timeout / unavailable
- Fallback diam-diam ke `leave_payout = 0`
- Settlement finalized → Employee tidak mendapat cuti yang menjadi haknya

**Root Cause**: Service unavailability tanpa notification ke admin.

**Mitigation** (baked into Slice A design):
1. **Explicit flag, bukan silent fallback**:
   ```php
   // Bukan:
   $leavePayout = $this->leaveService->getBalance($uuid) ?? 0.0; // BERBAHAYA
   
   // Yang benar:
   try {
       $leaveBalance = $this->leaveService->getActiveBalance($uuid);
       $leavePayout = $leaveBalance * $dailyRate;
       $leaveBalanceAvailable = true;
   } catch (LeaveServiceUnavailableException $e) {
       $leavePayout = null; // NULL = belum dikalkulasi, bukan zero
       $leaveBalanceAvailable = false;
   }
   ```
2. **Return 422 jika leave tidak tersedia dan settlement diminta final**:
   ```json
   {
     "warning": "LEAVE_BALANCE_UNAVAILABLE",
     "message": "Saldo cuti tidak dapat diambil saat ini. Settlement telah dikalkulasi tanpa komponen cuti. Silakan re-kalkulasi atau isi manual sebelum finalisasi.",
     "settlement": { "leavePayout": null, "leaveBalanceAvailable": false }
   }
   ```
3. **Block finalization** jika `leave_payout IS NULL` tanpa konfirmasi eksplisit admin:
   ```
   "Komponen cuti belum dikalkulasi. Konfirm dengan set manualLeavePayoutOverride=0 jika karyawan tidak memiliki sisa cuti."
   ```
4. **Admin manual override**: Allow `manualLeavePayoutOverride` field di request untuk kasus di mana admin yakin balance = 0.

**Test Case to Add**:
- `settlement_flags_leave_unavailable_when_service_fails`
- `finalization_blocked_if_leave_payout_is_null`
- `admin_can_override_leave_payout_with_manual_value`

---

#### Anomaly #5 — Settlement Batch Duplicate: Double-Click Generates Two Batches 🟠 HIGH

**Scenario (Phase 4, tapi design harus antisipasinya dari sekarang)**:
- Admin klik "Generate Settlement Batch" untuk May 2026
- Network slow → Admin klik lagi
- Dua batch terbuat: `batch_001` dan `batch_002` untuk terminations yang sama
- Karyawan dibayar dua kali

**Root Cause**: Tidak ada idempotency guard pada batch generation.

**Mitigation** (baked into Phase 4 design):
1. **Unique constraint**:
   ```sql
   ADD UNIQUE INDEX uq_company_batch_date_purpose (company_id, batch_date, purpose);
   ```
2. **Pre-check before generate**:
   ```php
   $existingBatch = HcmSettlementBatch::where('company_id', $companyId)
       ->where('batch_date', $batchDate)
       ->where('purpose', 'settlement_termination')
       ->whereNotIn('status', ['void'])
       ->first();
   
   if ($existingBatch) {
       return response()->json(['error' => 'BATCH_ALREADY_EXISTS', 'existing_batch_id' => $existingBatch->id], 409);
   }
   ```
3. **Check termination not already in another batch** — Sebelum menambah line ke batch, verify `termination.settlement_batch_id IS NULL`.

**Test Case to Add (Phase 4)**:
- `duplicate_batch_generation_returns_409`
- `termination_cannot_be_included_in_two_batches`

---

#### Anomaly #6 — Multi-Tenant Isolation: Settlement Touches Wrong Company 🔴 CRITICAL

**Scenario**:
- Request: `X-Company-Id: 1`, user maliciously passes `terminationId` dari Company 2
- Controller tidak check `company_id` match → Settlement batch Company 1 includes employee Company 2
- Data leak + financial error

**Root Cause**: Missing atau inconsistent `company_id` scope check pada nested resources.

**Mitigation** (baked into ALL Slices):
1. **Every query scoped by company_id** — Pattern wajib:
   ```php
   // BENAR:
   $termination = HcmTermination::where('company_id', $activeCompanyId)
       ->where('id', $id)
       ->firstOrFail(); // Otomatis 404 jika beda company
   
   // SALAH (jangan):
   $termination = HcmTermination::findOrFail($id); // No company scope!
   ```
2. **Checklist items scope via termination** — Verify termination belongs to company before checklist CRUD:
   ```php
   $termination = HcmTermination::where('company_id', $activeCompanyId)->findOrFail($terminationId);
   $item = $termination->checklistItems()->findOrFail($itemId); // Scoped via relationship
   ```
3. **Settlement batch scoped** (Phase 4):
   ```php
   HcmSettlementBatch::where('company_id', $activeCompanyId)->...
   ```
4. **Integration test**: Create terminations di 2 company berbeda, verify tidak cross-contaminate.

**Test Case to Add**:
- `cannot_access_termination_from_different_company_returns_404`
- `checklist_item_scoped_to_correct_company`
- `settlement_preview_only_fetches_own_company_data`

---

#### Anomaly #7 — Asset Clearance Desync: Settlement Shows Stale Asset Status 🟡 MEDIUM

**Scenario**:
- Settlement preview fetched: 3 assets outstanding
- Employee returns 2 assets 30 minutes later
- Settlement finalized: still shows 3 outstanding
- Report inaccurate

**Root Cause**: Asset status dalam clearance snapshot tidak di-refresh sebelum finalization.

**Mitigation** (baked into Slice B/C design — finalization guard):
1. **Force re-fetch asset status saat finalization** — Jangan gunakan cached `clearance_items` JSON dari termination record:
   ```php
   // In finalization guard (saat transisi ke finalized_execution):
   $currentAssets = $this->assetService->getOutstandingByEmployee($termination->user_id);
   $termination->clearance_items = $currentAssets; // Refresh snapshot
   ```
2. **Flag asset refresh timestamp** dalam response agar UI bisa show "Last refreshed: 5 minutes ago".

---

#### Anomaly #8 — Tax Calculation Mismatch: Duplicate Logic Diverges 🟡 MEDIUM

**Scenario**:
- Slice A menghitung deduction PPh21 menggunakan custom formula
- Payroll modul menghitung PPh21 menggunakan `PayrollTaxCalculationService` yang sudah proven
- Dua hasil berbeda → karyawan komplain tax tidak sesuai slip gaji

**Root Cause**: Kode pajak di-duplicate (tidak reuse service yang sama).

**Mitigation** (baked into Slice A design):
1. **WAJIB reuse `PayrollTaxCalculationService`** (atau class equivalent) untuk semua tax calculation:
   ```php
   // Bukan buat formula sendiri:
   // $pph21 = $grossAmount * 0.15; // JANGAN
   
   // Inject dan gunakan service yang sama dengan payroll:
   private readonly PayrollTaxCalculationService $taxService;
   
   $deduction = $this->taxService->calculateForSettlement($grossAmount, $taxProfile, $terminationDate);
   ```
2. **Contract test**: Verify bahwa tax calculation untuk amount yang sama menghasilkan angka yang sama antara settlement dan payroll run.

**Test Case to Add**:
- `settlement_tax_uses_same_service_as_payroll`
- `settlement_tax_matches_payroll_tax_for_same_gross`

---

### 9.3 Anomaly Status Tracker

| # | Anomaly | Severity | Mitigated In | Test Case Added |
|---|---------|----------|-------------|-----------------|
| 1 | Data staleness / stale snapshot | 🔴 Critical | Slice A design | ✅ 3 cases |
| 2 | Concurrent workflow corruption | 🟠 High | Slice B design | ✅ 2 cases |
| 3 | Cascade delete on finalized | 🟠 High | Slice C design | ✅ 3 cases |
| 4 | Silent zero leave payout on failure | 🟠 High | Slice A design | ✅ 3 cases |
| 5 | Duplicate settlement batch | 🟠 High | Phase 4 design | ✅ 2 cases |
| 6 | Multi-tenant company isolation breach | 🔴 Critical | All Slices | ✅ 3 cases |
| 7 | Asset clearance status desync | 🟡 Medium | Slice B/C design | ✅ 1 case |
| 8 | Tax calculation duplicate divergence | 🟡 Medium | Slice A design | ✅ 2 cases |

**Total anomaly test cases baked in: 19 additional test scenarios**

> ⚠️ **Gate Mandatory**: Semua mitigasi anomali di atas wajib diimplementasi sebelum merging ke main. Tidak ada slice yang dianggap "done" jika anomaly mitigasinya belum ada test coverage.

---

## 10. Rollout Strategy

### Pre-Production Validation

1. **Code Review**: Full stack review on each slice before merge
2. **Local Testing**: All tests pass on developer machine
3. **Staging Deployment**: Deploy to staging, run smoke tests
4. **Manual UAT**: Test happy paths + edge cases manually
5. **Performance Check**: Verify no N+1 queries, response time acceptable

### Production Rollout

1. **Gradual**: Ship one slice at a time
2. **Monitoring**: Watch error rates, API response times
3. **Rollback Plan**: If critical issue found, revert single slice
4. **Communication**: Notify HR/Payroll team of new features

### Slice Order for Rollout

1. **Slice A first**: Foundation for correct settlement (most critical)
2. **Slice B second**: Governance (important for compliance)
3. **Slice C third**: Usability (nice-to-have, non-blocking)

---

## 11. Documentation Deliverables

### For Each Slice

- [ ] API endpoint documentation in `docs/api/hcm-termination-api.md`
- [ ] OpenAPI spec in `docs/api/openapi.yaml`
- [ ] Request/response examples
- [ ] Error codes and handling
- [ ] Business logic explanation
- [ ] Database schema changes

### Feature Documentation

- [ ] Update `docs/features/termination/README.md` with new flow
- [ ] Update lifecycle/status table
- [ ] Add new sections: "Settlement Calculation", "Workflow Stages", "Checklist Management"
- [ ] Update "Existing vs Target" section to mark as ✅ complete
- [ ] Add implementation evidence in `docs/features/termination/tracker.md`

---

## 12. Success Metrics

### Code Quality
- ✅ >80% test coverage
- ✅ Zero technical debt items flagged by static analysis
- ✅ All tests pass consistently (no flaky tests)
- ✅ Code review approved by 2+ reviewers

### Functional Correctness
- ✅ Severance calculation matches formula for all test cases
- ✅ Workflow state machine enforces all rules correctly
- ✅ Checklist items persist and retrieve accurately
- ✅ Settlement finalization blocked when required

### User Experience
- ✅ API response time <500ms for termination endpoints
- ✅ No 500 errors in production
- ✅ Error messages clear and actionable
- ✅ HR team can complete full workflow in <5 min

### Compliance
- ✅ Settlement calculation aligns with UU Ketenagakerjaan requirements
- ✅ Audit trail complete and immutable
- ✅ Role-based access enforced
- ✅ No data leakage between companies (multi-tenant)

---

## 13. Appendix: Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                   Termination Module (v2)                    │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ CRUD Termination Records (existing)                   │  │
│  │ - Create/update/delete                                │  │
│  │ - List/detail endpoints                               │  │
│  └───────────────────────────────────────────────────────┘  │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ Slice A: Settlement Calculation Service (NEW)       │    │
│  │ ┌─────────────────────────────────────────────────┐ │    │
│  │ │ • Severance calculator                          │ │    │
│  │ │ • Leave payout calculator                       │ │    │
│  │ │ • Policy profile mapping                        │ │    │
│  │ │ → Stores breakdown in settlement field          │ │    │
│  │ └─────────────────────────────────────────────────┘ │    │
│  └─────────────────────────────────────────────────────┘    │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ Slice B: Workflow Stage Validation (NEW)            │    │
│  │ ┌─────────────────────────────────────────────────┐ │    │
│  │ │ • State machine enforcement                     │ │    │
│  │ │ • Transition validation                         │ │    │
│  │ │ • Approval trail recording                      │ │    │
│  │ │ → Blocks invalid transitions                    │ │    │
│  │ └─────────────────────────────────────────────────┘ │    │
│  └─────────────────────────────────────────────────────┘    │
│                           ↓                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ Slice C: Checklist Item Management (NEW)            │    │
│  │ ┌─────────────────────────────────────────────────┐ │    │
│  │ │ • CRUD endpoints for items                      │ │    │
│  │ │ • Mark completion with evidence                 │ │    │
│  │ │ • Blocks finalization if mandatory open        │ │    │
│  │ │ → Tracks non-asset obligations                 │ │    │
│  │ └─────────────────────────────────────────────────┘ │    │
│  └─────────────────────────────────────────────────────┘    │
│                           ↓                                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ Finalization Guard (Enhanced)                        │  │
│  │ - Validates settlement breakdown exists              │  │
│  │ - Validates workflow at approved_internal+ stage    │  │
│  │ - Validates all mandatory checklist items complete  │  │
│  │ → Creates finalized snapshot for payroll/clearance  │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 15. Data Dependency & External Integration

### 15.1 Complete Data Flow Diagram

**Settlement Calculation Flow:**
```
Termination Record Created/Updated
    ↓
HcmTerminationController::store() / update()
    ├─ Fetch: GET /v1/hcm/employees/{uuid}
    │   └─ Basic: id, name, email, hire_date, company_id, status
    ├─ Fetch: GET /v1/hcm/employees/{uuid}/salary
    │   └─ Salary: base_salary, allowances, tax_profile
    ├─ Fetch: GET /v1/hcm/payroll-periods/?company_id=&date={termDate}
    │   └─ Period: period_year, period_month, working_days
    ├─ Fetch: Leave Service (internal)
    │   └─ Leave: remaining_balance, by_type
    ├─ Fetch: Company Settings
    │   └─ Config: termination_policy, tax_method, payroll_config
    └─ Call: TerminationSettlementCalculationService
        ├─ Input: employeeUuid, terminationDate, reason, basis
        ├─ Calculate: severance, UPMK, UPH, leave_payout, deductions
        └─ Output: TerminationSettlementBreakdown
            ├─ Persist: hcm_terminations.settlement_breakdown fields
            └─ Return: API response with breakdown
```

**Checklist & Asset Integration:**
```
Settlement Finalization
    ├─ Validate: All mandatory checklist items completed
    │   └─ Query: hcm_termination_checklist_items WHERE mandatory=1
    ├─ Fetch: Outstanding assets
    │   └─ From: Asset Service (via clearance_items)
    └─ Lock: Record as finalized
        └─ Status: finalized → Ready for settlement batch
```

### 15.2 External API Dependencies (Read-Only)

| Module | Endpoint | Used For | Field Needs |
|--------|----------|----------|------------|
| **Employees** | `GET /v1/hcm/employees/{uuid}` | Employee validation, hire date | `id, uuid, name, email, hire_date_at` |
| **Employees** | `GET /v1/hcm/employees/{uuid}/salary` | Base salary, components | `monthly_base, allowances, tax_profile` |
| **Payroll** | `GET /v1/hcm/payroll-periods` | Reference period lookup | `period_year, period_month, working_days` |
| **Payroll** | `GET /v1/hcm/salary-components` | Component codes/types | `code, type, description` |
| **Leave** | Leave Service (internal) | Leave balance calculation | `remaining_balance, leave_type` |
| **Asset** | Asset Service (existing) | Clearance items | `assignment_id, asset_id, status` |
| **Company** | Company Settings | Policy config | `termination_policy, tax_method` |

### 15.3 Data Handoff: Termination → Settlement Batch

**After Termination Finalized:**
```
finalized termination record
    ├─ settlement_breakdown ✅ (calculated)
    ├─ workflow_stage = 'finalized_execution' ✅ (approved)
    ├─ non_asset_checklist ✅ (all items completed)
    └─ Ready for Settlement Payroll Batch

    ↓

Settlement Batch Generator
    ├─ Query: hcm_terminations WHERE status='finalized'
    ├─ For each: 
    │   ├─ Fetch: user.bank_account / user.preferred_payment_method
    │   ├─ Use: settlement_breakdown.net_payable
    │   ├─ Create: hcm_settlement_batch_lines
    │   └─ Status: 'pending'
    └─ Batch Status: 'draft'

    ↓

Settlement Export Reconciliation
    ├─ Generate: payment-ready CSV (similar to payroll export)
    │   └─ Columns: employee_id, name, bank_account, amount, reference
    ├─ Create: evidence record (Export Reconciliation)
    └─ Require: Admin download confirmation

    ↓

Disburse (Mark Paid)
    ├─ Prerequisite: Evidence download confirmed
    ├─ Update: hcm_settlement_batch_lines.status = 'paid'
    └─ All lines paid → Batch can post to payroll

    ↓

Post-Payroll
    ├─ Create: Payroll Run (purpose='settlement')
    ├─ Status: 'finalized' → 'posted'
    ├─ GL Posted: Settlement amounts to GL
    └─ Employee accessible: Settlement slip via API/PDF
```

---

## 16. Settlement Payroll Batch Module (NEW)

### 16.1 Purpose & Scope

**Module Name**: `payroll-settlement-batch` (or `/payroll-settlement`)

**Purpose**: Create standalone settlement payment batches from finalized terminations, following same pattern as THR payroll but optimized for single/multiple employee exits.

**Not In Scope (Slice A/B/C)**:
- This is a SEPARATE module that uses Slice A's settlement calculation
- Scope: Generate batch → Disburse → Post payroll
- Time: Will be implemented AFTER Slice A/B/C are complete (potential Phase 4)

### 16.2 Technical Design

#### 16.2.1 New Database Tables

**Table: `hcm_settlement_batch`**
```sql
CREATE TABLE hcm_settlement_batch (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    
    batch_date DATE NOT NULL,
    description VARCHAR(255),
    
    status ENUM('draft', 'calculated', 'exported', 'paid', 'posted', 'void') DEFAULT 'draft',
    purpose VARCHAR(50) DEFAULT 'settlement_termination',
    
    total_amount DECIMAL(15,2),
    total_lines INT,
    
    exported_by UUID NULLABLE,
    exported_at TIMESTAMP NULLABLE,
    
    paid_by UUID NULLABLE,
    paid_at TIMESTAMP NULLABLE,
    
    posted_by UUID NULLABLE,
    posted_at TIMESTAMP NULLABLE,
    
    created_by UUID,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id),
    INDEX idx_company_id (company_id),
    INDEX idx_status (status)
);
```

**Table: `hcm_settlement_batch_lines`**
```sql
CREATE TABLE hcm_settlement_batch_lines (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    batch_id BIGINT NOT NULL,
    termination_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    user_uuid UUID,
    
    employee_name VARCHAR(255),
    employee_email VARCHAR(255),
    
    bank_account VARCHAR(100),
    bank_name VARCHAR(100),
    
    gross_amount DECIMAL(15,2),
    deduction_amount DECIMAL(15,2),
    net_amount DECIMAL(15,2),
    
    status ENUM('pending', 'paid', 'skipped', 'failed') DEFAULT 'pending',
    
    line_notes TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (batch_id) REFERENCES hcm_settlement_batch(id) ON DELETE CASCADE,
    FOREIGN KEY (termination_id) REFERENCES hcm_terminations(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_batch_id (batch_id),
    INDEX idx_status (status)
);
```

#### 16.2.2 New Endpoints (Settlement Batch)

```
GET    /v1/hcm/payroll/settlement-batch
       - List all batches
       - Filter: status, date_from, date_to

POST   /v1/hcm/payroll/settlement-batch/generate
       - Generate new batch from finalized terminations
       - Input: {finalization_date, description}
       - Creates lines from terminations with status='finalized'
       - Status: 'draft' → 'calculated'

GET    /v1/hcm/payroll/settlement-batch/{id}
       - Detail batch + all lines

POST   /v1/hcm/payroll/settlement-batch/{id}/export-reconciliation
       - Trigger export reconciliation (like payroll run)
       - Prerequisite: status='calculated'
       - Creates: Evidence + payment-ready CSV

POST   /v1/hcm/payroll/settlement-batch/{id}/disburse
       - Mark lines as paid
       - Prerequisite: Evidence downloaded
       - Input: {lines: [line_ids], notes}
       - Updates: line.status='paid'

POST   /v1/hcm/payroll/settlement-batch/{id}/post-payroll
       - Create settlement run in payroll system
       - Prerequisite: All lines 'paid' OR 'skipped'
       - Creates: payroll_run (purpose='settlement_termination')
       - Status: batch='posted'

GET    /v1/hcm/payroll/settlement-batch/lines/{line_id}/slip
       - Get settlement slip PDF for single line

POST   /v1/hcm/payroll/settlement-batch/{id}/void
       - Cancel batch if not yet posted
       - Status: any → 'void'
```

#### 16.2.3 API Response Example

```json
{
  "id": 1,
  "batchDate": "2026-05-26",
  "description": "May 2026 terminations settlement",
  "status": "posted",
  "purpose": "settlement_termination",
  "totalAmount": 50000000,
  "totalLines": 3,
  "lines": [
    {
      "id": 101,
      "terminationId": 5,
      "employeeName": "Budi Santoso",
      "employeeEmail": "budi@company.com",
      "bankAccount": "1234567890",
      "bankName": "BCA",
      "grossAmount": 15000000,
      "deductionAmount": 2250000,
      "netAmount": 12750000,
      "status": "paid"
    }
  ],
  "meta": {
    "paidLines": 3,
    "pendingLines": 0,
    "totalPaid": 50000000
  },
  "audit": {
    "exportedBy": "Hendra Wijaya",
    "exportedAt": "2026-05-26T14:30:00Z",
    "paidBy": "Hendra Wijaya",
    "paidAt": "2026-05-26T15:00:00Z",
    "postedBy": "Payroll System",
    "postedAt": "2026-05-26T16:00:00Z"
  }
}
```

#### 16.2.4 Integration with Payroll System

**Settlement Run** (in `payroll_runs` table):
```
purpose: 'settlement_termination'
period_id: Settlement batch date (create virtual period if needed)
status: 'finalized' / 'posted'
lines: Pre-calculated from settlement_batch_lines
```

**Settlement Slip** (reuse `payslip` structure):
```
type: 'settlement'
employee: Employee data
period: Settlement batch date
breakdown:
  - severance
  - service_award
  - benefit_substitution
  - leave_payout
  - PKWT
  - deductions (PPh, BPJS)
netPayable: Final amount
```

### 16.3 Implementation Notes

**Why Separate Module:**
- THR pattern already proven
- Keeps settlement independent from monthly payroll
- Clear audit trail per batch
- Flexible timing (can run anytime)
- Reusable for future multi-employee settlements

**When to Build:**
- Phase 4 (after Slice A/B/C done)
- Depends on: Slice A (settlement calculation)
- Does NOT block: Slice A/B/C implementation

**Integration Points:**
- Consumes: `termination.settlement_breakdown` (from Slice A)
- Uses: Export Reconciliation infrastructure (existing)
- Creates: Payroll Run (existing)
- Generates: Settlement slip (reuse payslip template)

---

## 17. Payroll Settlement Payment Decision

### 17.1 Chosen Approach: Settlement Payroll Batch (NEW Module)

**Decision**: Termination settlement payment is a SEPARATE module from monthly payroll, following THR pattern.

**Timing**: Settlement can be paid **immediately** after termination finalized (same day or next business day).

**Flow**:
```
Termination Finalized (settlement_breakdown calculated ✅)
    ↓
Settlement Batch Generate (admin action)
    ↓
Export Reconciliation (evidence confirmation)
    ↓
Disburse (mark paid)
    ↓
Post-Payroll (create settlement run)
    ↓
Settlement Slip generated + sent to employee
```

**Advantage**:
- ✅ Fast settlement payment (not delayed to month-end)
- ✅ Clear audit trail (separate batch record)
- ✅ Flexible (can run on any date)
- ✅ Proven pattern (reuse THR architecture)
- ✅ No monthly payroll disruption

**Constraints**:
- Settlement batch must use only finalized terminations
- All mandatory checklist items must be completed
- Settlement breakdown must be calculated (Slice A)

### 17.2 Implementation Timing

**Phase 1-3**: Slice A/B/C (Termination enrichment)
- Severance calculation ✅
- Workflow validation ✅
- Checklist management ✅

**Phase 4**: Settlement Payroll Batch Module
- Batch generation
- Disburse + post-payroll
- Settlement slip
- Export reconciliation

**Phase 4 NOT blocker for Phase 1-3**: Can be planned separately after Slice A/B/C complete.

---

## 18. Data Dependency Summary Table

| Data Source | API Endpoint | Used By | Criticality |
|-------------|----------|---------|-------------|
| **Employee** | `GET /v1/hcm/employees/{uuid}` | Termination validation, hire date calc | 🔴 Critical |
| **Salary** | `GET /v1/hcm/employees/{uuid}/salary` | Base salary, allowances | 🔴 Critical |
| **Payroll Period** | `GET /v1/hcm/payroll-periods` | Reference period for settlement | 🟠 High |
| **Leave Balance** | Internal Leave Service | Leave payout calculation | 🟠 High |
| **Assets** | Asset Service | Clearance items in finalization | 🟡 Medium |
| **Company Settings** | Company Config | Policy profile, tax method | 🟠 High |
| **Salary Components** | `GET /v1/hcm/salary-components` | Component code mapping | 🟡 Medium |
| **User Bank Account** | Employee profile | Settlement batch payment details | 🟠 High (Phase 4) |

---

## 14. Next Steps

**Immediate Actions**:
1. ✅ Finalize planning document (100% complete)
2. Get stakeholder sign-off on slices + payment approach
3. Assign engineer(s) to Slice A/B/C
4. Create GitHub issues with task breakdown
5. Schedule Phase 1 kick-off meeting

**Phase 1 Starting Point (Week 1)**:
- **Slice A**: Start TerminationSettlementCalculationService
- **Slice B**: Design state machine rules in parallel
- **Slice C**: Design checklist item endpoints in parallel
- Ensure all 3 start together for maximum progress

**Phase 4 (Post Phase 1-3)**:
- Plan Settlement Payroll Batch module
- Design batch API endpoints
- Reuse THR batch patterns

---

**Document Version**: 2.0  
**Last Modified**: 2026-05-26  
**Status**: ✅ 100% Complete - Ready for Implementation

### What's Included:
- ✅ Current state analysis
- ✅ Complete impact analysis (files, routes, DB)
- ✅ Data dependency diagram
- ✅ 3 Implementation slices (A/B/C)
- ✅ Settlement Payroll Batch module design
- ✅ Payment flow decision + timing
- ✅ Integration architecture
- ✅ Timeline & dependencies
- ✅ Testing strategy
- ✅ Risk assessment + **8 anomaly mitigations baked into design**
- ✅ 12 anomaly prevention gates di Definition of Done
- ✅ Success metrics
- ✅ Documentation deliverables
