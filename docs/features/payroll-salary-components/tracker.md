# Registri Komponen Gaji — Tracker

## Snapshot 2026-05-04 — Cross-Module Employee Integration Matrix

- **Status:** Delivered — tab audit employee-level sekarang memeriksa integrasi lintas domain (PPh21, BPJS, Allowance, Payroll assignment), bukan allowance-only.

### Perubahan Utama

1. Endpoint `GET /v1/hcm/salary-components/employee-profiles` menambahkan `integrationSummary.checks[]` untuk modul `pph21`, `bpjs`, `allowance`, `payroll`.
2. Endpoint menambahkan `integrationSummary.gaps[]` untuk alasan gap yang actionable (`pph21Profile`, `bpjsMembership`, `allowanceAssignment`, dst).
3. `assignmentSummary` kini menyertakan `sourceModuleCounts` agar asal assignment aktif dapat diaudit langsung per karyawan.
4. UI tab **Profil Integrasi Karyawan** menampilkan badge kesiapan per modul + daftar gap integrasi per baris employee.

### Evidence

- Controller: `backend/app/Http/Controllers/Api/HcmSalaryComponentController.php` (method `employeeProfiles`).
- UI renderer: `frontend/resources/js/salary-component-master-data.js`.
- Tabel tab profile: `backend/resources/views/finance/salary-component-master.blade.php`.
- Kontrak API sinkron: `docs/api/hcm-salary-components-api.md` + `docs/api/openapi.yaml`.

## Snapshot 2026-05-04 — Employee Integration Profile Tab

- **Status:** Delivered — tab audit employee-level integration sudah aktif di `/salary-component-master`.

### Perubahan Utama

1. API baru `GET /v1/hcm/salary-components/employee-profiles` untuk snapshot integrasi employee.
2. UI tab baru **Profil Integrasi Karyawan** pada halaman Salary Component.
3. Ringkasan status integrasi (`ready/partial/missing`) ditampilkan langsung di header tab.
4. Baris employee menampilkan `identityGaps` + summary assignment allowance governance.

### Evidence

- Route baru ditambahkan di `backend/routes/api/salary-component.php`.
- Controller `HcmSalaryComponentController::employeeProfiles()` mengembalikan `data.rows` + `meta.statusSummary`.
- JS `salary-component-master-data.js` me-render tab baru dan tabel profil integrasi.

## Snapshot 2026-05-01 — Governance-Driven Refactor

- **Status:** Refactor in progress — migrating from fully-mutable CRUD to governance-driven registry
- **Arsitektur target:** System-locked components auto-registered from governance modules; tenant-custom components remain editable

### Perubahan Utama Refactor Ini

1. **Kolom baru `source_module`** di `hcm_salary_components` — menandai origin setiap komponen
2. **Server-side lock** — `update()` dan `destroy()` mengembalikan `403` untuk komponen `is_system_locked=1`
3. **`HcmSalaryComponent::ensureComponent()`** — static helper yang dipanggil governance modules saat aktivasi policy
4. **Auto-register** di 5 governance controllers: BPJS, Allowance, PPh21, Overtime, THR/PKWT
5. **UI refactor** — badge source_module, tombol edit/hapus disembunyikan untuk locked, nama halaman diperbarui
6. **Pembersihan hardcoded runtime** — integrasi registry tidak lagi bergantung pada daftar kode komponen statis; allowance governance tidak lagi auto-seed baseline policy statis saat dibuka

### Evidence DB (snapshot sebelum refactor)

- `hcm_salary_components`: 61 rows — 24 system_locked, 37 tenant
- `hcm_salary_component_categories`: 27 rows — semua system
- Breakdown source governance berdasarkan code pattern:
  - `bpjs`: 8 komponen (`iuran_bpjs_*`, `premi_jk*`)
  - `allowance`: 9 komponen (`tunjangan_*`, `uang_makan_*`)
  - `pph21`: 2 komponen (`pph21_*`)
  - `overtime`: 1 komponen (`upah_lembur`)
  - `thr`: 1 komponen (`thr`)
  - `pkwt`: 1 komponen (`kompensasi_pkwt`)
  - `system`: 2 komponen (`upah_pokok`, `bonus`)
  - tenant-custom: 37 komponen (`reimbursement_*`, `potongan_*`, dll)

### Anomali Ditemukan

| # | Anomali | Severity | Fix |
|---|---|---|---|
| 1 | `source_module` belum ada | MEDIUM | Migrasi tambah kolom + backfill |
| 2 | `is_system_locked` tidak enforce di server | HIGH | Block di controller |
| 3 | 1 payroll item dengan `hcm_salary_component_id IS NULL` | LOW | Test data, tidak breaking |

### Evidence Test Target

- `php artisan test tests/Feature/HcmSalaryComponentApiTest.php`
- `php artisan test tests/Feature/HcmEmployeeAllowanceGovernanceApiTest.php`
- Test tambah: assert 403 pada update/destroy komponen system_locked
- Test tambah: assert policy allowance dibuat eksplisit tanpa runtime seed default

## Snapshot 2026-04-27 (pre-refactor baseline)

- Status: salary component dan category master sekarang sudah murni CRUD; lock delete legacy/hardcoded dihapus dari runtime.
- `HcmSalaryComponent::categoriesForKind()` membaca master kategori dinamis apa adanya saat tabel tersedia.
- `DELETE /v1/hcm/salary-components/{id}` mengizinkan hapus semua komponen termasuk seed/system.
- UI menampilkan aksi hapus untuk semua row.

## Gap Aktif

1. `source_module` enum belum terdefinisi di schema — **target refactor ini**
2. Governance modules (BPJS, Allowance, PPh21) belum memanggil `ensureComponent()` — **target refactor ini**
3. UI tidak membedakan locked vs unlocked components secara visual — **target refactor ini**
4. Matriks kepatuhan internal (sign-off klien) masih perlu dilengkapi oleh owner bisnis — out of scope teknis

- Anggap audit teknis integrasi utama, pemisahan surface, dan readiness deploy master component sudah tertutup.
- Perlakukan sisa pekerjaan sebagai governance/policy maintenance, bukan blocker deploy atau regression CRUD/API inti.