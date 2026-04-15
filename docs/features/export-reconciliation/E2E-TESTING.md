# Export Reconciliation - E2E Testing & Execution Log

Dokumen untuk mencatat manual UI E2E testing dan hasil eksekusi payment flow dengan export reconciliation.

---

## Role Gate Matrix

| Role | Akses Export | Akses Payment After Export | Blocked at Auth (403) |
|---|---|---|---|
| HCM Admin / Finance Admin | ✅ | ✅ | ❌ |
| Accounting Team | ✅ | ✅ | ❌ |
| Karyawan / Non-Admin | ❌ | ❌ | ✅ (auth, 403) |
| Customer Subscriber (Self-Service) | ❌ | N/A (self-service flow) | ✅ (auth, 403) |

---

## Preconditions for Testing

1. **Environment**:
   - Admin user logged in + valid company context
   - API endpoint `/v1/reconciliation/exports` active
   - Gate service integrated in controller flow
   - Frontend UI export button + indicator deployed (npm build done)

2. **Test Data**:
   - Payroll run in draft status (ready for payment)
   - THR batch with eligible employees
   - PKWT compensation data for period
   - Access to import/create test data via artisan or seeder

3. **Browser**:
   - DevTools Network tab open to inspect requests
   - JavaScript console to check for errors
   - Screenshot capability for evidence logging

---

## Test Execution Log

### Scenario 1: Payroll Disburse - Export Required (PC-01)

**Test ID**: PC-01  
**Date**: 2026-04-15  
**Status**: NOT YET EXECUTED  
**Tester**: [TBD]

**Steps**:

1. Navigate to Payroll Run page
   ```
   URL: http://localhost:8000/hcm/payroll-runs
   ```

2. Load payroll period + active run (status = draft or open)
   ```
   Select period, verify run ID appears
   Expected: run #<id> with employee list loads
   ```

3. **WITHOUT export**, click "Pay via Gateway" button
   ```
   Expected Network Response:
   - 422 Unprocessable Entity
   - Response body: { "error": { "code": "EXPORT_RECON_REQUIRED", "message": "..." } }
   - UI: Orange warning banner shows reconciliation hint
   ```

4. **Verify warning banner** displays actionable message
   ```
   Expected text: "Sebelum lanjut proses, lakukan export reconciliation..."
   ```

5. Click "Export Reconciliation" button
   ```
   Expected Network Request:
   - POST /v1/reconciliation/exports
   - Body:
     {
       "featureKey": "payroll_run",
       "actionKey": "finalize",
       "scopeRef": "<runId>",
       "filterPayload": { /* employee/user list */ },
       "format": "csv"
     }
   ```

6. **Verify export succeeds** (200 response)
   ```
   Response: { "data": { "id": <evidenceId>, "expires_at": "...", "exported_at": "..." } }
   ```

7. **Check evidence indicator** on page
   ```
   Expected: Badge shows "valid" + timestamp "Exported: [date] oleh [user]"
   Location: Below reconciliation hint div
   ```

8. Click "Pay via Gateway" button again
   ```
   Expected: Payment proceeds OR gateway modal opens
   (If success: toast "Pembayaran gateway selesai", PAID status updated)
   ```

**Result**: [ ] **PASS** [ ] **FAIL** [ ] **NOT TESTED**

**Evidence Required**:
- [ ] Screenshot: Step 3 - 422 error with warning banner
- [ ] Screenshot: Step 7 - Evidence indicator "valid"
- [ ] Screenshot: Step 8 - Payment success / gateway initiated
- [ ] Browser console: No errors
- [ ] Network log: POST export + subsequent payment request

---

### Scenario 2: THR Disburse - Export Required (TC-01)

**Test ID**: TC-01  
**Date**: 2026-04-15  
**Status**: NOT YET EXECUTED  
**Tester**: [TBD]

**Steps**:

1. Navigate to THR Batch page
   ```
   URL: http://localhost:8000/hcm/payroll/thr-batch
   ```

2. Verify batch with eligible employees + unpaid status

3. **WITHOUT export**, click "Pay THR" button
   ```
   Expected: 422 EXPORT_RECON_REQUIRED error + warning banner
   ```

4. Click "Export Reconciliation" button
   ```
   Expected Network Request:
   - POST /v1/reconciliation/exports
   - Body: { "featureKey": "thr_batch", "actionKey": "disburse", "scopeRef": "<batchId>", ... }
   ```

5. Verify evidence indicator shows "valid"

6. Click "Pay THR" button
   ```
   Expected: Payment proceeds OR batch disburse modal
   ```

**Result**: [ ] **PASS** [ ] **FAIL** [ ] **NOT TESTED**

**Evidence Required**:
- [ ] Screenshot: Export reconciliation success
- [ ] Screenshot: Evidence indicator visible
- [ ] Screenshot: Payment successful

---

### Scenario 3: Non-Admin Auth Gate (NG-01)

**Test ID**: NG-01  
**Date**: 2026-04-15  
**Status**: NOT YET EXECUTED  
**Tester**: [TBD]

**Steps**:

1. Login as non-admin user (operator/viewer role)
   ```
   User: demo.owner01@example.com
   ```

2. Navigate to Payroll Run page
   ```
   Expected: Either blocked at page level (403 forbidden page)
   OR page loads but payment buttons disabled
   ```

3. Attempt to click Export or Pay button
   ```
   Expected Network Response:
   - 403 Forbidden (auth rejection, NOT reconciliation gate)
   - Error message: "Anda tidak punya akses..."
   - Backend logs confirm: Auth gate fires BEFORE reconciliation gate
   ```

4. Verify non-admin never reaches reconciliation gate validation

**Result**: [ ] **PASS** [ ] **FAIL** [ ] **NOT TESTED**

**Evidence Required**:
- [ ] Screenshot: 403 Forbidden error
- [ ] Browser console: No "reconciliation" related errors (auth error only)
- [ ] Backend log: Auth middleware rejection logged

---

### Scenario 4: Tenant Boundary Isolation (TB-01)

**Test ID**: TB-01  
**Date**: 2026-04-15  
**Status**: NOT YET EXECUTED  
**Tester**: [TBD]

**Steps**:

1. Admin in Company A exports payroll run evidence
   ```
   Evidence created for: company_id=1, feature=payroll_run, run_id=100
   ```

2. Switch context to Company B (or different tenant)
   ```
   Via company selector OR re-login if multi-tenant isolation by login
   ```

3. Attempt to trigger payment on DIFFERENT run/batch in Company B
   ```
   Backend should use Company B's evidence query
   Expected: No evidence found from Company A visible to Company B
   ```

4. If somehow trying to directly use Company A's evidence:
   ```
   Expected Network Response:
   - 422 EXPORT_RECON_SCOPE_MISMATCH
   - Error: "Evidence reconciliation tidak sesuai..."
   ```

**Result**: [ ] **PASS** [ ] **FAIL** [ ] **NOT TESTED**

**Evidence Required**:
- [ ] Screenshot: Company A export evidence created
- [ ] Screenshot: Switch to Company B
- [ ] Screenshot: Payment attempt shows no evidence or mismatch error
- [ ] Database log: Verify scope query includes tenant filter

---

## Master Checklist

| # | Scenario | Status | Pass | Fail | Evidence |
|---|---|---|---|
| 1 | Payroll Disburse (PC-01) | [ ] | [ ] | [ ] [ ] [ ] |
| 2 | THR Disburse (TC-01) | [ ] | [ ] | [ ] [ ] |
| 3 | Non-Admin Guard (NG-01) | [ ] | [ ] | [ ] [ ] |
| 4 | Tenant Boundary (TB-01) | [ ] | [ ] | [ ] |

**Overall E2E Status**: [ ] ALL PASS [ ] PARTIAL PASS [ ] INCOMPLETE

---

## Known Blockers / Issues Found

- [x] Export buttons deployed (2026-04-15)
- [x] Indicator wired (2026-04-15)
- [ ] TBD during execution

---

## Test Execution Notes

- Tests focus on **payment flow** (disburse/pay actions) ONLY
- Full test matrix (mismatch/stale/granular permissions) deferred post-payment-flow-validation
- Non-admin auth gate should be quick (403 before reconciliation logic)
- Screenshots uploaded as evidence per scenario
- Results logged here real-time as tests execute

**Target Completion**: 2026-04-21



Lanjut:

1. Lakukan export reconciliation.
2. Ulangi Finalize.

Expected:

- Finalize berhasil.
- Audit trail menyimpan reference evidence export.

Tambahan role check:

1. Login non-admin/costumer.
2. Pastikan tidak ada CTA export reconciliation pada halaman non-admin.

Expected:

- Tidak ada blokir UX yang meminta customer melakukan export manual.

## Scenario 2 - Payroll Disburse scope mismatch

Langkah:

1. Export untuk run A.
2. Coba disburse run B.

Expected:

- API menolak `EXPORT_RECON_SCOPE_MISMATCH`.
- Tidak ada line payment yang berubah.

## Scenario 3 - Invoice Mark Paid tanpa evidence

Langkah:

1. Pilih invoice status sent/issued.
2. Klik mark paid tanpa export.

Expected:

- Gagal `EXPORT_RECON_REQUIRED`.

Lanjut:

1. Export invoice reconciliation.
2. Mark paid ulang.

Expected:

- Berhasil.
- Evidence terkait tersimpan.

## Scenario 4 - Payment Verify dengan data stale

Langkah:

1. Export payment list.
2. Ubah data payment/invoice dari tab lain.
3. Verify payment dengan evidence lama.

Expected:

- Jika strict mode aktif: gagal `EXPORT_RECON_STALE_DATA`.
- Jika strict mode nonaktif: warning/log tetap tercatat.

## Scenario 5 - RBAC dan tenant boundary

Langkah:

1. Login non-admin.
2. Coba trigger export reconciliation.

Expected:

- Ditolak 403.

Langkah:

1. Login admin tenant A.
2. Akses evidence tenant B.

Expected:

- Ditolak 403/404 sesuai kebijakan.

## Scenario 6 - THR batch disburse/post-payroll gated for admin only

Langkah:

1. Login admin, buka flow THR batch.
2. Jalankan disburse/post-payroll tanpa evidence.

Expected:

- API menolak `EXPORT_RECON_REQUIRED` pada action yang digate.

Lanjut:

1. Buat evidence export untuk batch yang sama.
2. Ulang action disburse/post-payroll.

Expected:

- Action tidak lagi ditolak oleh gate reconciliation.

Role check:

1. Login non-admin/costumer.
2. Pastikan tidak ada permintaan export manual pada flow yang bukan scope admin.

Expected:

- Non-admin tidak diminta export manual; akses tetap mengikuti role endpoint.

## Scenario 7 - PKWT post-payroll gated for admin only

Langkah:

1. Login admin, jalankan post-payroll PKWT tanpa evidence.

Expected:

- API menolak `EXPORT_RECON_REQUIRED`.

Lanjut:

1. Buat evidence export untuk periode PKWT yang sama (`YYYY-MM`).
2. Ulang post-payroll.

Expected:

- Action tidak lagi ditolak oleh gate reconciliation.

## Evidence QA yang harus dikumpulkan

1. Screenshot UI sebelum dan sesudah export.
2. Response payload error code `EXPORT_RECON_*`.
3. Log audit yang menghubungkan export evidence ke action.
4. Catatan pass/fail per skenario.

## Manual Execution Log Template

| Tanggal | Environment | Role | Skenario | Hasil (Pass/Fail) | Bukti (screenshot/log) | Catatan |
|---|---|---|---|---|---|---|
| 2026-04-15 | local | HCM Admin | Scenario 1 | Pass | link/path | - |
| 2026-04-15 | local | Non-admin | Scenario 1 role check | Pass | link/path | Tidak ada CTA export di flow non-admin |

## Exit Criteria

- Semua skenario critical pass.
- Tidak ada bypass action tanpa evidence pada scope fase 1.
- Error message jelas dan bisa ditindaklanjuti user.
- Tidak ada flow customer/non-admin yang dipaksa export manual.
