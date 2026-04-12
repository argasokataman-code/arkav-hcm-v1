## Implementation status (2026-04-12)

## Seed data testing leaves (2026-04-12)

Perintah untuk generate data testing cuti yang bersih dan detail lintas schema legacy + foundation:

- php artisan hcm:leave-seed-testing-data --fresh

Perintah tersedia di:

- backend/app/Console/Commands/HcmSeedLeaveTestingDataCommand.php

Cakupan data yang diisi otomatis:

- users + employee_profiles (admin, manager, 2 employee)
- leave_types (Indonesia catalog) + hcm_leave_type_settings (linked by leave_type_id)
- leave_policies (default + custom) + hcm_leave_custom_policies (linked by leave_type_id/leave_policy_id)
- leave_policy_assignments
- employee_leave_balances
- leave_requests (status pending/approved/declined)
- leave_approvals
- leave_ledger (usage/accrual/carry-forward)
- holidays + holiday_calendars

Output verifikasi row count setelah seed contoh terbaru:

- leave_types=9
- leave_policies=11
- leave_policy_assignments=4
- employee_leave_balances=6
- leave_requests=7
- leave_approvals=7
- leave_ledger=5
- hcm_leave_type_settings=9
- hcm_leave_custom_policies=2
- holidays=3
- holiday_calendars=3

## Future development tables (cross-check template gaps)

Berdasarkan cross-check template aktif HCM (`/leaves`, `/leaves-employee`, `/leave-settings`, dashboard approval inbox, dan roadmap report), tabel berikut ditambahkan agar pengembangan lanjutan tidak mentok di struktur lama:

- `leave_approval_workflows`
	- Menyimpan master alur approval per leave type + periode efektif.
	- Kunci utama: `leave_type_id`, `min_days`, `max_days`, `effective_from`, `effective_to`.

- `leave_approval_workflow_steps`
	- Menyimpan step-level approver (`manager`, `department_head`, `hr_admin`, `specific_user`) dan SLA.
	- Mendukung approval multi-level bertahap tanpa ubah tabel request utama.

- `leave_blackout_dates`
	- Menyimpan blackout/rule periode sensitif (peak season, audit period, event operasional).
	- Mode: `hard_block`, `warning_only`, `max_quota` + `max_people_per_day`.

- `leave_request_breakdowns`
	- Rincian harian/jam per request untuk kebutuhan half-day/hourly dan trace kalkulasi hari kerja vs hari libur.
	- Menyimpan `deducted_days` per baris agar report lebih akurat.

- `leave_request_attachments`
	- Penyimpanan metadata file bukti cuti (surat sakit, akta, dll) + status verifikasi HR.
	- Menutup gap requirement attachment di template/policy.

- `leave_request_audits`
	- Audit trail perubahan request (create/update/status/attachment/delete) untuk investigasi dan compliance.

Semua tabel di atas dibuat via migrasi:

- `backend/database/migrations/2026_04_19_010000_create_leave_future_development_tables.php`

## Update integrasi production-ready (2026-04-12)

### A. Leaves UI sekarang aktif (bukan placeholder)

- Filter aktif di halaman `/leaves` dan `/leaves-employee`:
	- `leaveType`, `status`, `dateFrom`, `dateTo`
- Export CSV aktif (mengikuti filter yang dipilih user).
- Date picker modal leave pakai flatpickr:
	- weekend + holiday disabled visual,
	- validasi range + estimasi hari kerja terpotong.

### B. Jalur data leaves ↔ holiday sudah terhubung DB-level

- `holiday_calendars` sekarang punya kolom relasi `holiday_id` ke `holidays.id`.
- Relasi dibentuk via migrasi:
	- `backend/database/migrations/2026_04_19_020000_link_holiday_calendars_to_holidays.php`
- Sync CRUD/sync holiday otomatis menulis `holiday_id` di `holiday_calendars`.
- API leave list (`/v1/hcm/leave-requests`) sekarang mengirim `meta.holidays[].holidayId` sebagai jejak linkage.

Contoh verifikasi DB terbaru:

- `holiday_rows=3`
- `calendar_rows=3`
- `calendar_linked=3`
- `calendar_unlinked=0`

### C. Guard payroll untuk karyawan resign/terminate (anti payroll salah bayar)

- `PayrollDraftBuilder` sekarang mengecualikan user dengan:
	- `hcm_resignations.status = approved` dan `resignation_date <= end_of_period`
	- `hcm_terminations.status = approved` dan `termination_date <= end_of_period`
- Tujuan: user yang sudah efektif resign/terminate tidak ikut draft payroll bulanan.
- Ditutup oleh test regresi:
	- `approved resigned employee is excluded from monthly payroll draft`

### D. Export leaves sekarang server-side

- Endpoint baru: `GET /v1/hcm/leave-requests/export`
- Filter sama dengan list (`scope`, `leaveType`, `status`, `dateFrom`, `dateTo`, `userId`).
- Frontend tidak lagi loop paging untuk export; backend stream CSV langsung.

### E. Monitoring linkage holiday di admin + sinkronisasi API lebih ketat

- Halaman Holidays menampilkan card monitoring linkage:
  - `holidayRows`, `calendarRows`, `linkedRows`, `unlinkedRows`, `manualRows`, `apiRows`
- `GET /v1/hcm/holidays` dan `POST /v1/hcm/holidays/sync-indonesia` sekarang mengembalikan `meta.linkage`.
- Upstream provider sync holiday dipindahkan ke `https://libur.deno.dev/api?year=YYYY` (lebih lengkap untuk kalender Indonesia) dengan fallback otomatis ke `https://date.nager.at/api/v3/PublicHolidays/{year}/ID` jika provider utama gagal.
- Sync API sekarang rekonsiliasi tahunan:
  - data `source=api` yang sudah tidak ada di payload provider akan dibersihkan,
  - data manual perusahaan tetap dipertahankan,
  - linkage ke `holiday_calendars` tetap sinkron, sehingga perhitungan leave otomatis ikut update.
	- monthly accrual,
	- yearly carry-forward,
	- daily expiration.
- Command `hcm:leave-backfill-foundation` untuk backfill otomatis dari `hcm_leave_type_settings` + `hcm_leave_custom_policies` ke foundation (`leave_types`, `leave_policies`, `leave_policy_assignments`) agar transisi tidak perlu setup manual satu-satu.
- Scheduler terpasang di `routes/console.php` untuk menjalankan maintenance otomatis (timezone Asia/Jakarta).
- Integrasi awal ke flow live: perubahan status leave request oleh admin (approve/reverse) sekarang menulis ke ledger foundation secara otomatis.

Catatan fase transisi:

- API existing tidak di-break (tetap kompatibel dengan flow lama).
- Penegakan saldo minus ketat untuk approve request masih ditahan sementara agar transisi dari data legacy tidak mengganggu operasional.
- Validasi lanjut entitlement strict dapat diaktifkan setelah policy assignment + opening balance seluruh karyawan selesai dibackfill.

🧠 RANGKUMAN LENGKAP (TIDAK ADA YANG KELEWAT)
1. MASALAH AWAL SISTEM LO

Struktur awal:

hcm_leave_type_settings
hcm_leave_custom_policies
ada assignee_user_ids (array ❌)
❌ Issue utama:
Campur antara:
leave type
leave policy
leave assignment
Tidak scalable
Tidak compliant penuh dengan aturan Indonesia
Tidak support:
prorate
audit trail
payroll integration
multi policy
2. STRUKTUR YANG BENAR (FOUNDATION)
A. leave_types

Master jenis cuti:

is_paid
requires_approval
requires_attachment
deduct_from_balance
B. leave_policies

Aturan cuti:

days_per_year
min_service_months (Indonesia: 12 bulan)
carry_forward
max_carry_days
expire_after_days
is_prorated
is_earned_leave
C. leave_policy_assignments

Menggantikan assignee_user_ids ❌

Relasi:

employee_id
policy_id
D. employee_leave_balances

Cache saldo:

balance
used
expired
carried_forward
E. leave_requests

Transaksi cuti:

start_date
end_date
total_days
status
approval
3. LEVEL LANJUTAN (ENTERPRISE)
🔥 1. LEAVE LEDGER (WAJIB)

Sumber kebenaran:

+12 (jatah tahunan)
-1  (cuti bersama)
-2  (cuti tahunan)
+6  (carry forward)
-3  (expired)

Fungsi:

audit
debugging
rollback
🔥 2. LEAVE ACCRUAL (BULANAN)
12 hari → 1 hari per bulan
otomatis prorate
🔥 3. POLICY VERSIONING
policy tidak boleh overwrite
pakai:
effective_from
effective_to
🔥 4. APPROVAL FLOW

Multi level:

Manager → HR → Director
🔥 5. HOLIDAY & CUTI BERSAMA (KRUSIAL DI INDONESIA)
skip hari libur saat hitung cuti
cuti bersama:
bisa potong saldo
bisa tidak (configurable)
🔥 6. VALIDATION RULE ENGINE

Contoh:

minimal cuti 1 hari
blackout date (peak season)
🔥 7. HALF DAY / HOURLY LEAVE
support 0.5 hari
optional: jam
🔥 8. MULTI COMPANY SUPPORT
semua tabel wajib ada company_id
🔥 9. PAYROLL INTEGRATION
paid leave → tidak potong gaji
unpaid leave → potong gaji
🔥 10. CRON JOB
harian: expire
bulanan: accrual
tahunan: reset + carry forward
4. KHUSUS INDONESIA (COMPLIANCE)
✔️ Cuti Tahunan
12 hari
setelah 12 bulan kerja
✔️ Cuti Sakit
tidak mengurangi saldo
tetap dibayar
✔️ Cuti Melahirkan
3 bulan (fixed)
bukan dari balance
✔️ Cuti Bersama
configurable:
potong cuti
atau tidak
5. MASALAH KRITIS YANG HARUS DIHINDARI
❌ Array di database (assignee_user_ids)

→ harus relational table

❌ Double deduction

→ cuti + cuti bersama

❌ Race condition

→ saldo minus karena request bersamaan

❌ Backdated leave

→ bisa merusak ledger

6. ARSITEKTUR FINAL

👉 SALDO CUTI =
bukan field statis

TAPI:

hasil kalkulasi dari
ledger + policy + rules + holiday engine

7. MODE UMKM (LITE) - BIAR PENGUSAHA TIDAK TERBEBANI

Tujuan:

tetap compliant Indonesia
biaya implementasi rendah
operasional HR sederhana
fitur bisa tumbuh bertahap saat bisnis naik kelas

A. PRINSIP DESAIN UMKM

default simple dulu, bukan full enterprise dari hari pertama
fitur advanced jadi toggle (on/off)
otomasi boleh semi-manual di tahap awal (dengan audit tetap aman)
tetap gunakan struktur data yang benar (relational + ledger), walau alur dibuat sederhana

B. PAKET FITUR WAJIB (UMKM STARTER)

Wajib:
leave_types
leave_policies
leave_policy_assignments (tanpa array)
leave_requests
leave_ledger (minimal transaksi accrual/usage/adjustment)
holiday_calendars (minimal nasional + cuti bersama)

Opsional di fase awal:
multi-level approval (cukup 1 level dulu)
hourly leave
policy versioning kompleks
multi-company penuh

C. DEFAULT RULE UMKM (REKOMENDASI PRAKTIS)

approval flow: 1 level (atasan langsung)
accrual: bulanan sederhana (1 hari/bulan untuk jatah 12)
carry forward: off by default (bisa on jika dibutuhkan)
negative balance: off (saldo tidak boleh minus)
backdated leave: dibatasi, misal maksimal mundur 7 hari
attachment: wajib hanya untuk leave type tertentu (sakit, duka, dll)
cuti bersama: default configurable per perusahaan (potong saldo atau company-paid)

D. PENGENDALIAN BIAYA BAGI UMKM

hindari implementasi “all features at once”
gunakan 1 policy utama per leave type dulu
gunakan job terjadwal sederhana:
bulanan: accrual
harian: sinkron holiday + validasi request pending
tahunan: reset saldo + carry forward (jika aktif)

E. INTEGRASI PAYROLL VERSI UMKM

aturan minimum:
paid leave -> tidak mengurangi gaji
unpaid leave -> mengurangi gaji pro-rata

opsi formula awal (sederhana):
potongan_harian = gaji_bulanan / jumlah_hari_kerja_bulan_berjalan
potongan = potongan_harian * total_hari_unpaid_leave

F. ROADMAP BERTAHAP (PRAKTIS)

Fase 1 (MVP UMKM):
core table + single approval + ledger minimal + holiday engine + payroll basic

Fase 2 (Growth):
policy versioning, accrual otomatis penuh, carry forward, approval 2 level

Fase 3 (Enterprise):
rule engine lengkap, hourly leave, multi company, advanced analytics

G. GUARDRAIL AGAR UMKM TETAP AMAN

jangan korbankan audit trail (ledger tetap wajib)
jangan pakai array assignment lagi
jangan bypass validasi holiday/weekend
jangan izinkan update saldo manual tanpa catatan ledger

8. CHECKLIST KEPUTUSAN SAAT IMPLEMENTASI

Wajib dijawab sebelum go-live:

apakah perusahaan pakai accrual bulanan atau jatah tahunan langsung?
apakah cuti bersama memotong saldo?
berapa level approval yang dipakai sekarang?
apakah unpaid leave langsung memotong payroll bulan berjalan?
berapa batas backdated leave?

9. OUTPUT YANG WAJIB DIBIKIN AGENT (TAMBAHAN UNTUK UMKM)

Selain deliverables awal, agent wajib memberi:

Mode A: Enterprise recommendation
Mode B: UMKM Lite recommendation
gap analysis biaya/kompleksitas antar mode
rekomendasi mode default berdasarkan jumlah karyawan (misal <= 100 pakai UMKM Lite dulu)

10. CATATAN PENTING

UMKM Lite bukan berarti mengurangi compliance.
Yang disederhanakan adalah alur operasional dan urutan implementasi,
bukan mengorbankan struktur data inti dan audit.

🚀 PROMPT AI AGENT (SIAP COPAS)
Writing

You are a senior HRIS system architect and backend engineer.

Your task is to design and implement a scalable, enterprise-grade Leave Management System for Indonesia, ensuring full compliance with Indonesian labor regulations and best practices.

You MUST provide two implementation modes:
Mode A: Enterprise Full
Mode B: UMKM Lite (cost-efficient, lower operational burden)

For each mode, provide:
scope
trade-offs
estimated operational complexity
recommended rollout phase

🚨 CRITICAL INSTRUCTION (MANDATORY FIRST STEP)

Before starting any development, you MUST:

Analyze the existing database schema and system structure.
Identify all impacted modules, including:
Payroll system
Employee management
Attendance system
Approval workflows
Identify all risks, including:
Data inconsistency
Double deduction of leave
Race conditions (concurrent requests)
Backdated leave issues
Produce a clear impact analysis report before making any changes.

DO NOT proceed to implementation before completing this analysis.

🧠 SYSTEM DESIGN REQUIREMENTS
1. Core Architecture Separation

You MUST separate the following concepts:

Leave Types (master data)
Leave Policies (rules)
Leave Assignments (who gets which policy)
Leave Balances (cached values)
Leave Ledger (source of truth transactions)
2. Required Database Tables
leave_types
code
name
is_paid
requires_approval
requires_attachment
deduct_from_balance
is_active
leave_policies
leave_type_id
name
days_per_year
min_service_months (12 months for annual leave)
is_prorated
carry_forward
max_carry_days
expire_after_days
is_earned_leave
effective_from
effective_to
leave_policy_assignments
policy_id
employee_id
effective_date

(MUST replace any array-based assignment approach)

employee_leave_balances (CACHE ONLY)
employee_id
leave_type_id
year
balance
used
expired
carried_forward
leave_ledger (SOURCE OF TRUTH - CRITICAL)
employee_id
leave_type_id
transaction_type (accrual, usage, adjustment, joint_leave, expire)
amount (+/-)
balance_after (optional but recommended)
reference_id
reference_type
notes
leave_requests
employee_id
leave_type_id
start_date
end_date
total_days
is_half_day
status
approved_by
reason
attachment
holiday_calendars
date
name
is_national
is_joint_leave
deduct_from_leave
leave_approvals
leave_request_id
approver_id
level
status
3. Indonesian Compliance Rules

You MUST enforce:

Annual leave:
Minimum 12 days
Only after 12 months of service
Sick leave:
Paid
Does NOT reduce leave balance
Maternity leave:
3 months fixed
Not deducted from balance
Joint leave (cuti bersama):
Must be configurable:
deduct from leave OR
company-paid
4. Leave Calculation Engine

You MUST implement:

Working day calculation:
Exclude weekends
Exclude holidays from holiday_calendars
Leave balance calculation:
Based on leave_ledger (not static fields)
5. Accrual System
Support monthly accrual (earned leave)
Example:
12 days/year → 1 day/month
6. Critical System Behaviors

You MUST handle:

Prorated leave (mid-year join)
Carry forward with expiration
Leave expiry
Negative balance prevention (configurable)
Backdated leave validation
Multi-level approval workflow
7. Payroll Integration
Paid leave → no salary deduction
Unpaid leave → salary deduction
Configurable per leave type
8. Performance Strategy
Use employee_leave_balances as cache
Use leave_ledger as source of truth
Avoid recalculating from scratch on every request
9. Automation (CRON JOBS)
Daily:
expire leave balances
Monthly:
accrual processing
Yearly:
reset balances
apply carry forward
10. Anti-Patterns to Avoid
DO NOT use array fields for assignments
DO NOT store leave balance as the only source of truth
DO NOT hardcode policies
DO NOT skip audit trail (ledger is mandatory)
🎯 FINAL GOAL

Build a system where:

Leave balance is NOT stored as a static value,

BUT is derived from:

leave_ledger
leave_policies
holiday rules
accrual logic

Deliverables:

Impact analysis report (MANDATORY FIRST)
Database schema (final)
Service logic (step-by-step)
Edge case handling
Example scenarios (real-world cases)

Additional mandatory deliverables:
UMKM Lite default configuration pack
Feature toggle matrix (what is ON/OFF per mode)
Migration path from UMKM Lite -> Enterprise Full

Ensure the system is scalable, auditable, production-ready, and cost-aware for UMKM adoption.

---

## 11) IMPACT ANALYSIS REPORT (MANDATORY FIRST) - KONDISI REPO SAAT INI

Tanggal analisis: 2026-04-12

### 11.1 Current Schema Snapshot (aktual di codebase)

Leave/Holiday saat ini:

- `holidays` (title, holiday_date, description, is_active, source, last_synced_at)
- `leave_requests` (user_id, leave_type [string], date_from, date_to, days, status, notes)
- `hcm_leave_type_settings` (code, name, days, carry_forward, earned_leave)
- `hcm_leave_custom_policies` (leave_type_code, name, days, assignee_user_ids JSON)

Observation kritis:

- `leave_requests.leave_type` masih string bebas (belum FK ke leave type master).
- assignment policy masih pakai `assignee_user_ids` (array JSON, anti-pattern).
- belum ada `leave_ledger` sebagai source of truth.
- belum ada `employee_leave_balances` cache table.
- belum ada `leave_approvals` untuk multi-level approval.
- belum ada kolom request untuk half-day/hourly.
- holiday table belum memisahkan `is_joint_leave` + `deduct_from_leave`.

### 11.2 Impacted Modules (WAJIB terdampak)

1. Leave Settings API/UI
- sekarang baca/tulis `hcm_leave_type_settings` + `hcm_leave_custom_policies`.
- akan berubah ke model: leave types + leave policies + assignments.

2. Leave Requests API/UI
- saat ini request create/update langsung ke `leave_requests` tanpa ledger.
- akan berubah: validasi policy + holiday engine + write ledger + balance cache update.

3. Holiday API/UI
- sudah ada sync nasional, tapi belum punya semantik cuti bersama untuk deduction.

4. Payroll
- saat ini belum konsumsi leave ledger untuk potongan unpaid leave secara eksplisit.
- perlu integrasi ke pipeline payroll calculation.

5. Attendance
- masih terpisah dari leave engine; perlu rule cross-check untuk absent vs approved leave.

6. Employee Management
- `employee_profiles.hire_date` wajib dipakai untuk min_service_months + prorate.

### 11.3 Risk Analysis (sebelum implementasi)

1. Data inconsistency risk
- sumber data tersebar di request + policy JSON assignment.

2. Double deduction risk
- potensi overlap unpaid leave, cuti bersama, dan potongan payroll.

3. Race condition risk
- request paralel hari yang sama bisa mengurangi saldo lebih dari sekali.

4. Backdated mutation risk
- perubahan request lama tanpa ledger immutable merusak histori saldo.

5. Migration risk
- konversi dari string leave type ke FK leave type/policy berisiko orphan data.

### 11.4 Decision Gate (go/no-go)

Implementasi tidak boleh lanjut ke phase coding penuh sebelum:

- mapping data lama -> tabel baru final disetujui.
- strategi backfill ledger disetujui.
- strategy cutover (freeze write / dual-write / phased) disetujui.

---

## 12) FINAL DATABASE SCHEMA (TARGET)

### 12.1 Master

#### `leave_types`
- id
- company_id (nullable for single-company stage, required for multi-company)
- code (unique per company)
- name
- is_paid
- requires_approval
- requires_attachment
- deduct_from_balance
- is_active
- created_at, updated_at

#### `leave_policies`
- id
- company_id
- leave_type_id (FK)
- name
- days_per_year
- min_service_months
- is_prorated
- carry_forward
- max_carry_days
- expire_after_days
- is_earned_leave
- allow_negative_balance
- effective_from
- effective_to (nullable)
- created_at, updated_at

#### `leave_policy_assignments`
- id
- company_id
- policy_id (FK)
- employee_id (FK users.id)
- effective_date
- end_date (nullable)
- created_at, updated_at

### 12.2 Transactional

#### `leave_requests`
- id
- company_id
- employee_id (FK users.id)
- leave_type_id (FK)
- policy_id (FK snapshot policy)
- start_date
- end_date
- total_days
- is_half_day
- half_day_session (first_half/second_half, nullable)
- start_time (nullable, future hourly)
- end_time (nullable, future hourly)
- status (draft/pending/approved/rejected/cancelled)
- reason
- attachment_path
- approved_by
- approved_at
- rejected_by
- rejected_at
- created_at, updated_at

#### `leave_approvals`
- id
- company_id
- leave_request_id (FK)
- approver_id
- level
- status (pending/approved/rejected/skipped)
- acted_at
- notes
- created_at, updated_at

#### `leave_ledger` (SOURCE OF TRUTH)
- id
- company_id
- employee_id
- leave_type_id
- policy_id (nullable snapshot)
- transaction_type (accrual, usage, adjustment, carry_forward, expire, joint_leave, reversal)
- amount (signed decimal)
- balance_after (optional but recommended)
- reference_type (leave_request, scheduler, migration, admin_adjustment, payroll)
- reference_id
- occurred_on
- notes
- created_by
- created_at, updated_at

#### `employee_leave_balances` (CACHE)
- id
- company_id
- employee_id
- leave_type_id
- year
- balance
- used
- expired
- carried_forward
- updated_at
- unique(company_id, employee_id, leave_type_id, year)

### 12.3 Holiday Calendar

#### `holiday_calendars`
- id
- company_id
- date
- name
- is_national
- is_joint_leave
- deduct_from_leave
- source (manual/api)
- last_synced_at
- created_at, updated_at

---

## 13) MIGRATION STRATEGY (NO-DATA-LOSS)

Phase M1 - additive schema:

1. Create new tables (`leave_types`, `leave_policies`, `leave_policy_assignments`, `employee_leave_balances`, `leave_ledger`, `leave_approvals`, `holiday_calendars`).
2. Keep old tables alive (compat mode).

Phase M2 - data backfill:

1. Map `hcm_leave_type_settings` -> `leave_types` + default `leave_policies`.
2. Map `hcm_leave_custom_policies.assignee_user_ids` -> `leave_policy_assignments` rows.
3. Map `holidays` -> `holiday_calendars` (default `is_national=true`, `is_joint_leave=false`, `deduct_from_leave=false` unless configured).
4. Map historical `leave_requests` -> new `leave_requests` and create `leave_ledger` usage rows for approved requests.

Phase M3 - dual-write:

1. API write to new schema.
2. Optional temporary shadow write ke schema lama untuk rollback window.

Phase M4 - cutover:

1. Switch read path ke schema baru.
2. Lock old write path.
3. Deprecate old tables after stabilization.

---

## 14) SERVICE LOGIC (STEP-BY-STEP)

### 14.1 Create Leave Request

1. Validate actor + scope.
2. Resolve active assignment by employee + leave type + date range.
3. Resolve active policy by effective period.
4. Run working-day calculator (exclude weekend + holiday_calendars).
5. Apply half-day/hourly modifier.
6. Validate service tenure (`min_service_months`).
7. Validate balance via ledger-derived cache (`employee_leave_balances`).
8. Validate blackout/backdated/company rules.
9. Start DB transaction + row lock balance cache.
10. Insert leave request.
11. Insert approval steps (single-level UMKM or multi-level enterprise).
12. If auto-approve path applies, insert ledger usage and update cache.

### 14.2 Approve / Reject

1. Verify approver authority by level.
2. Lock request row + lock employee balance row.
3. If approve final:
- post ledger `usage` (negative) when deduct_from_balance=true
- if not deducting (sick/maternity/company-paid) post informational ledger with 0 or tagged type
- update balance cache atomically
4. If reject: no balance change, update status and approval row.

### 14.3 Scheduler Jobs

Daily:
- expire carry-forward that passed expiration window -> ledger `expire`.

Monthly:
- accrual for earned leave -> ledger `accrual`.

Yearly:
- reset + carry forward rules -> ledger `carry_forward` and/or `expire`.

### 14.4 Payroll Integration

Per payroll period:

1. Fetch approved leave requests in period.
2. Separate paid vs unpaid by leave type config.
3. Compute unpaid leave deduction.
4. Write payroll line item (deduction) with reference to leave ledger/request.
5. Prevent double deduction by unique reference lock (period + request_id).

---

## 15) VALIDATION RULE ENGINE (MINIMUM SET)

Rule categories:

1. Temporal
- cannot overlap with approved leave
- cannot request on locked payroll dates (configurable)

2. Eligibility
- min service months
- assignment exists

3. Balance
- no negative balance unless policy allow_negative_balance

4. Operational
- max backdated days
- blackout dates
- attachment required for selected leave types

5. Calendar
- exclude holiday/weekend from counted days
- joint leave deduction based on `deduct_from_leave`

---

## 16) CONCURRENCY & DATA INTEGRITY CONTROLS

Mandatory controls:

1. DB transaction for every approve/reject/cancel with balance impact.
2. `SELECT ... FOR UPDATE` (or equivalent lock) on balance row and request row.
3. Idempotency key for approval action API.
4. Unique reference constraints in ledger for one-time postings.
5. Immutable ledger entries (no hard update/delete in normal flow).

---

## 17) EDGE CASE HANDLING

1. Join mid-year + annual leave policy
- prorate by remaining months.

2. Employee pindah policy di tengah tahun
- close assignment lama, open assignment baru with effective_date.

3. Backdated request setelah payroll posted
- reject by default, or route to correction flow with audit reason.

4. Cuti bersama bersamaan dengan annual leave
- avoid double deduction by holiday engine precedence.

5. Correction / cancellation approved leave
- post reversal ledger entry instead of editing old ledger rows.

---

## 18) UMKM LITE DEFAULT CONFIG PACK

Recommended default:

1. Approval level: 1
2. Earned leave: ON only for annual leave
3. Carry forward: OFF
4. Allow negative balance: OFF
5. Max backdated days: 7
6. Attachment required: sick/maternity only
7. Joint leave deduction: configurable, default OFF

---

## 19) FEATURE TOGGLE MATRIX (MODE A vs MODE B)

| Feature | Mode B UMKM Lite | Mode A Enterprise Full |
|---|---|---|
| Leave ledger | ON (mandatory) | ON (mandatory) |
| Balance cache | ON | ON |
| Multi-level approval | OFF (1 level only) | ON |
| Hourly leave | OFF | ON |
| Policy versioning advanced | BASIC | FULL |
| Backdated correction workflow | BASIC | FULL |
| Payroll leave deduction integration | BASIC | FULL |
| Rule engine custom scripting | OFF | ON |
| Multi-company hard isolation | Optional | ON |

---

## 20) MIGRATION PATH: UMKM LITE -> ENTERPRISE FULL

Step 1: Stabilize ledger + cache for 2-3 payroll cycles.

Step 2: Enable policy versioning and carry-forward with expiry.

Step 3: Enable multi-level approvals and approval SLA.

Step 4: Enable hourly leave and advanced rule engine.

Step 5: Enable multi-company partitioning and analytics.

---

## 21) EXAMPLE SCENARIOS (REAL-WORLD)

### Scenario A: Cuti tahunan karyawan baru 7 bulan

- Request annual leave submitted.
- Engine checks min_service_months=12.
- Result: rejected with rule explanation.

### Scenario B: Cuti sakit 2 hari dengan attachment

- Request approved.
- Deduct_from_balance=false.
- Payroll impact=none (paid leave).
- Ledger logs informational event for audit.

### Scenario C: Unpaid leave 3 hari

- Request approved.
- Payroll line deduction generated in current payroll period.
- No duplicate deduction due to unique reference lock.

### Scenario D: Cuti bersama company-paid

- Holiday engine flags joint leave with deduct_from_leave=false.
- Balance unchanged.
- Attendance treated as approved non-working day.

---

## 22) IMPLEMENTATION BACKLOG (ACTIONABLE)

Sprint 1:

1. Add new schema tables + indexes
2. Build leave type/policy/assignment services
3. Build ledger posting service + cache updater

Sprint 2:

1. Refactor leave request create/approve/reject to new engine
2. Add holiday engine integration for leave day calculation
3. Add scheduler jobs (daily/monthly/yearly)

Sprint 3:

1. Payroll integration for unpaid leave deductions
2. Data migration backfill + reconciliation report
3. Cutover + observability dashboards

Done criteria:

- no negative balance race in concurrent tests
- payroll deduction reconciliation = 100%
- ledger-to-balance consistency checks pass
- Indonesian compliance test cases pass