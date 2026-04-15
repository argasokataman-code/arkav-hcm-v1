# Export Reconciliation - Implementation Plan

## Arsitektur Singkat

Fitur ini menambahkan lapisan kontrol di antara:

- halaman transaksi/payroll,
- endpoint aksi berisiko (finalize/disburse/post/mark-paid/verify),
- endpoint export dataset,
- dan tabel audit evidence.

Alur dasar:

1. User menyiapkan filter/periode di UI.
2. User trigger export (CSV/XLSX).
3. Backend menghasilkan file + checksum dataset + metadata filter.
4. Backend menyimpan evidence export.
5. Saat user menekan aksi berisiko, backend memvalidasi apakah evidence export masih valid.
6. Jika valid, aksi lanjut. Jika tidak, action diblokir dengan error eksplisit.

---

## Modul Target Fase 1

### 1) Payroll Run (Monthly)

Action yang digate:

- `POST /v1/hcm/payroll-runs/{id}/finalize`
- `POST /v1/hcm/payroll-runs/{id}/disburse`

Data export minimum:

- periodYear, periodMonth, runId
- employeeId, employeeName
- componentCode, componentName, kind, category
- amount, affectsNetPay
- netPayByEmployee
- paymentStatus, paidAt, gatewayReference

### 2) THR Batch

Action yang digate:

- `POST /v1/hcm/payroll/thr-batch/disburse`
- `POST /v1/hcm/payroll/thr-batch/post-payroll`

Data export minimum:

- calendarYear, batchId
- employeeId, employeeName
- eligibilityStatus
- baseSalary, fixedAllowance, multiplier, thrAmount
- paymentStatus, paidAt, gatewayReference

### 3) PKWT Compensation

Action yang digate:

- `POST /v1/hcm/payroll/pkwt-compensations/post-payroll`
- pay run via disburse payroll run hasil PKWT

Data export minimum:

- periodYear, periodMonth, runId
- employeeId, employeeName
- contractStartDate, contractEndDate
- baseMonthlySalary, fixedMonthlyAllowance
- compensationAmount
- paymentStatus

### 4) Invoices

Action yang digate:

- `PUT /v1/saas/invoices/{invoice}/mark-paid`

Data export minimum:

- invoiceId, invoiceNumber
- companyId, companyName
- issueDate, dueDate
- amountDue, taxAmount, discountAmount, totalAmount
- status

### 5) Payments

Action yang digate:

- `PUT /v1/saas/payments/{payment}/verify`

Data export minimum:

- paymentId
- invoiceId, invoiceNumber
- companyId, companyName
- amount, currency
- paymentMethod, gateway, gatewayReference
- status, paidAt, verifiedAt

---

## Data Model (Proposal)

### Table: export_reconciliation_evidences

Kolom utama:

- id
- feature_key (payroll_run, thr_batch, pkwt_compensation, invoice, payment)
- action_key (finalize, disburse, post_payroll, mark_paid, verify)
- scope_ref (runId, batchId, invoiceId, paymentId)
- exported_by_user_id
- exported_at
- file_format (csv|xlsx)
- file_path
- row_count
- filter_payload (JSON)
- dataset_checksum (sha256)
- expires_at (opsional TTL)
- created_at, updated_at

Index disarankan:

- (feature_key, action_key, scope_ref, exported_at)
- (exported_by_user_id, exported_at)

---

## Gate Validation Rules

Rule minimum agar action boleh diproses:

1. Evidence export ada untuk feature/action/scope yang sama.
2. Evidence belum expired (jika TTL aktif).
3. Filter payload masih cocok dengan konteks action.
4. Dataset checksum masih valid terhadap snapshot saat action (opsional strict mode).
5. User memiliki permission action dan export.

Jika gagal:

- return `422` dengan code spesifik:
  - `EXPORT_RECON_REQUIRED`
  - `EXPORT_RECON_EXPIRED`
  - `EXPORT_RECON_SCOPE_MISMATCH`
  - `EXPORT_RECON_STALE_DATA`

---

## Frontend Behavior

1. Tambahkan tombol Export Reconciliation di halaman target.
2. Saat export berhasil, tampilkan info evidence terbaru:

- timestamp
- user
- format
- jumlah baris

3. Tombol action berisiko menampilkan warning jika belum ada evidence valid.
4. UI tetap bukan source of truth; backend gate tetap wajib.

---

## Security & Compliance

- Endpoint export/gate tetap mengikuti RBAC server-side.
- File export tidak boleh expose data lintas tenant.
- Audit event harus immutable untuk keperluan investigasi.
- Semua error message harus jelas tapi tidak bocorkan data sensitif.

### Role boundary (wajib)

- Reconciliation export dan gate enforcement hanya untuk role admin/operator internal (HCM Admin, Finance Admin, Accounting).
- Customer/subscriber non-admin tidak menjadi aktor export reconciliation dan tidak boleh dipaksa menjalankan export manual.
- Jika ada flow customer yang membutuhkan konsistensi data, validasi dilakukan otomatis di backend tanpa UX langkah export manual.

---

## Rollout Plan

### Phase A (MVP)

- Payroll run finalize/disburse
- Invoice mark-paid
- Payment verify
- Evidence table + basic UI indicator

### Phase B

- THR disburse/post-payroll
- PKWT post-payroll/pay run
- XLSX formatter parity

### Phase C

- Snapshot checksum strict mode
- Report dashboard untuk compliance export coverage

---

## Deliverables

- Endpoint export per scope utama
- Middleware/service gate validation
- Migration evidence table
- Feature tests (happy + forbidden + mismatch)
- OpenAPI update untuk endpoint baru
- Dokumen fitur + runbook QA

---

## Checklist Eksekusi Teknis (Langsung Implementasi)

### Step 1 - Data Layer

1. Buat migration `export_reconciliation_evidences`.
2. Tambahkan indeks komposit untuk pencarian scope/action terbaru.
3. Buat model + repository query helper (`latestValidEvidenceByScope`).

### Step 2 - Service Layer

1. `ReconciliationExportService`:
  - generate dataset dari scope,
  - hitung checksum,
  - simpan file,
  - simpan evidence record.
2. `ReconciliationGateService`:
  - cek evidence eksis,
  - cek expiry,
  - cek scope/filter match,
  - cek stale data (jika strict mode aktif).

### Step 3 - API Layer

1. Implement endpoint:
  - `POST /v1/reconciliation/exports`
  - `GET /v1/reconciliation/exports`
  - `GET /v1/reconciliation/exports/{id}/download`
2. Standarisasi response:
  - success envelope `success/data`
  - error envelope `success/error.code/error.message`

### Step 4 - Gate Integration (Controller Hook)

Urutan prioritas:

1. Monthly payroll
  - `POST /v1/hcm/payroll-runs/{id}/finalize`
  - `POST /v1/hcm/payroll-runs/{id}/disburse`
2. Billing
  - `PUT /v1/saas/invoices/{invoice}/mark-paid`
  - `PUT /v1/saas/payments/{payment}/verify`
3. THR/PKWT
  - `POST /v1/hcm/payroll/thr-batch/disburse`
  - `POST /v1/hcm/payroll/thr-batch/post-payroll`
  - `POST /v1/hcm/payroll/pkwt-compensations/post-payroll`

### Step 5 - Frontend Wiring

1. Tambah tombol export reconciliation per halaman prioritas.
2. Tampilkan state evidence terakhir (time/user/format/rows).
3. Blok aksi berisiko di UI jika evidence invalid (tetap backend sebagai guard final).
4. Petakan error code `EXPORT_RECON_*` ke pesan yang actionable.

### Step 6 - Testing & Verification

1. Feature test backend:
  - success path,
  - forbidden/unauthorized,
  - scope mismatch,
  - stale evidence.
2. Manual E2E:
  - admin flow,
  - non-admin blocked,
  - tenant isolation.

### Step 7 - Documentation Closure

1. Update `docs/api/openapi.yaml`.
2. Update docs fitur terdampak (payroll/billing).
3. Update planning status dan release note internal.
