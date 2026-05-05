# SPT Masa PPh 21 - Implementation Plan

Dokumen ini memecah delivery ke 3 fase implementasi:

1. Fase 1 - Frontend shell dan UX flow
2. Fase 2 - Backend UUID-first + snapshot data
3. Fase 3 - Integrasi end-to-end + build gate

## Phase 1 - Build FE

## Tujuan

Menyiapkan experience operasional agar user payroll/admin bisa generate, review, dan submit SPT tanpa edit manual nilai.

## Deliverables

- Halaman list `/spt-masa-pph21` (Blade view di `backend/resources/views/spt-masa-pph21/index.blade.php`)
- Halaman detail `/spt-masa-pph21/{sptUuid}` (Blade view + JS wiring)
- Web routes terdaftar di `backend/routes/web/` di balik middleware `hcm.web.admin`
- Status badge: `Draft`, `Ready`, `Submitted`
- Aksi UI: Generate, Regenerate (konfirmasi), Tandai Ready, Submit, Export CSV
- Filter list minimal: periode, status, pencarian
- JS module di `frontend/resources/js/spt-masa/` (atau `frontend/resources/ts/spt-masa/` jika dipilih TS) lengkap dengan unit Vitest happy-path render + action.

## UI states wajib

- loading state
- empty state
- success state
- error state berbasis error envelope backend (`{ success:false, error:{ code, message }}`)
- disabled state untuk tombol berdasarkan status machine + version optimistic lock

## Guard FE

- Tombol mutasi disembunyikan/disabled bila role tidak sesuai.
- Keputusan akhir tetap backend authorization (UI tidak dipercaya).

## Phase 2 - Build BE (UUID-only)

## Prinsip

- Semua endpoint public memakai UUID.
- Tidak ada fallback numeric ID untuk module ini.
- Data SPT disimpan sebagai snapshot immutable per generate cycle.

## Data model target

Konvensi repo: PK numeric `id` (BIGINT auto-increment) + kolom `uuid` UNIQUE INDEX sebagai public identifier. FK internal pakai numeric id; route binding pakai UUID. Tabel pakai prefix `hcm_*` plural.

### Table: `hcm_spt_masa_headers`

- `id` BIGINT PK
- `uuid` CHAR(36) UNIQUE - public identifier
- `company_id` BIGINT FK ke `companies.id` (tenant scope)
- `periode` CHAR(7) - format `YYYY-MM`
- `status` ENUM(`draft`,`ready`,`submitted`) default `draft`
- `total_bruto` DECIMAL(18,2) default 0
- `total_pph21` DECIMAL(18,2) default 0
- `total_karyawan` INT default 0
- `version` INT default 1 - optimistic lock
- `generation_key` VARCHAR(80) NULL - idempotency key dari client (unik per company + periode jika diisi)
- `generated_at` DATETIME
- `submitted_at` DATETIME NULL
- `notes` TEXT NULL
- `created_by_user_id` BIGINT FK ke `users.id`
- `submitted_by_user_id` BIGINT FK ke `users.id` NULL
- timestamps + soft delete (untuk audit regenerate)

Constraint:

- UNIQUE partial: `(company_id, periode)` WHERE `status IN ('draft','ready')` (MySQL: implementasi via expression index atau cek logic transactional di service layer + unique active flag column)
- INDEX (`company_id`, `periode`)
- INDEX (`company_id`, `status`)

### Table: `hcm_spt_masa_details`

- `id` BIGINT PK
- `uuid` CHAR(36) UNIQUE
- `hcm_spt_masa_header_id` BIGINT FK -> `hcm_spt_masa_headers.id` ON DELETE CASCADE
- `hcm_spt_masa_header_uuid` CHAR(36) - denormalized untuk audit (mengikuti pola `HcmPayrollLine`)
- `user_id` BIGINT FK -> `users.id` NULL (NULL untuk non-pegawai)
- `user_uuid` CHAR(36) NULL - denormalized
- `nama` VARCHAR(255)
- `npwp` VARCHAR(32) NULL
- `nik` VARCHAR(32) NULL
- `employment_type` ENUM(`permanent`,`contract`,`intern`,`non_employee`)
- `kategori_spt` ENUM(`pegawai_tetap`,`tidak_tetap`,`non_pegawai`)
- `bruto` DECIMAL(18,2) default 0
- `pph21` DECIMAL(18,2) default 0
- `bukti_potong_type` VARCHAR(40) NULL
- timestamps

Constraint:

- INDEX (`hcm_spt_masa_header_id`)
- INDEX (`company_id`, `user_id`) jika `company_id` di-denormalize (opsional untuk reporting)

### Optional Fase Lanjutan: `hcm_bukti_potong`

Untuk input non-pegawai/A1 manual:

- `id`, `uuid`, `company_id`, `user_id` NULL, `jenis` ENUM(`A1`,`non_pegawai`), `periode`, `bruto`, `pph21`, timestamps.

Tidak masuk MVP; hanya dicatat sebagai placeholder.

## Endpoint & service layer target

- Routes file: `backend/routes/api/spt-masa.php` (loaded di `RouteServiceProvider`).
- Middleware: `prefix('v1/hcm/spt-masa')->middleware(['api.token','tenant.context'])`.
- Controller: `App\Http\Controllers\Api\HcmSptMasaController` (mengikuti naming `HcmTaxGovernanceController`).
- Form Requests: `StoreSptMasaGenerateRequest`, `SubmitSptMasaRequest`, `RegenerateSptMasaRequest`.
- Resource: `SptMasaHeaderResource`, `SptMasaDetailResource`.
- Services:
  - `App\Support\SptMasaGenerationService` - membaca `hcm_payroll_lines` final per periode, menyusun snapshot.
  - `App\Support\SptMasaValidationService` - re-validate header vs lines + NPWP (reuse helper tax governance).
  - `App\Support\SptMasaExportService` - render CSV format DJP-style.
- Permission codes: `tax.spt.view`, `tax.spt.manage` (didaftarkan ke matrix `docs/planning/active-hcm-templates-and-permissions.md`).

Target endpoint:

- `GET /v1/hcm/spt-masa/headers`
- `POST /v1/hcm/spt-masa/headers` - generate (body: `periode`, `generationKey?`)
- `GET /v1/hcm/spt-masa/headers/{sptRef}`
- `POST /v1/hcm/spt-masa/headers/{sptRef}/regenerate` - body: `version`
- `POST /v1/hcm/spt-masa/headers/{sptRef}/mark-ready` - body: `version`
- `POST /v1/hcm/spt-masa/headers/{sptRef}/submit` - body: `version`, `notes?`
- `GET /v1/hcm/spt-masa/headers/{sptRef}/export.csv`

Semua endpoint menerima dan mengembalikan UUID via field `sptRef`/`uuid`. Numeric id tidak dipublish.

## Validation rules backend

Generate:

- Minimal satu `hcm_payroll_runs.status = finalized` purpose `monthly` di periode terkait (`hcm_payroll_periods.code = YYYY-MM` atau filter `period_id`).
- Tidak boleh ada header aktif (`draft|ready`) lain untuk pasangan `(company_id, periode)`. Jika ada, kembalikan `SPT_HEADER_DUPLICATE` (kecuali request adalah regenerate).
- Source data wajib dari payroll lines final.
- `generationKey` (jika dikirim) idempotent: panggilan ulang dengan key yang sama mengembalikan header existing tanpa menggandakan.

Mark-ready:

- `status = draft` dan `version` cocok.
- Total detail konsisten dengan header (`SPT_TOTAL_MISMATCH` jika tidak).
- Tidak ada detail dengan field wajib kosong.

Submit:

- `status = ready` dan `version` cocok.
- `total_pph21` header == sum payroll deduction `pph21_*` periode (toleransi pembulatan `decimal:2`).
- `total_bruto` header == sum payroll addition taxable PPh21.
- NPWP semua detail valid (15-16 digit setelah strip `.`/`-`).

Regenerate:

- `status IN (draft, ready)` dan `version` cocok.
- Audit event tercatat (siapa, kapan, alasan optional).
- Header existing diperbarui in-place; detail di-truncate dan di-rebuild dalam satu transaksi.

Concurrency:

- Setiap mutating ops increment `version` di tail transaksi. Jika request membawa `version` yang lebih kecil, kembalikan `SPT_VERSION_CONFLICT`.

## Mapping rules

`employment_type -> kategori_spt`:

- `permanent -> pegawai_tetap`
- `contract -> tidak_tetap`
- `intern -> tidak_tetap`
- `non_employee -> non_pegawai`

## Snapshot strategy

- Saat generate/regenerate, detail disimpan sebagai salinan nilai final payroll lines + PPh21 saat itu.
- Perubahan data live setelah generate tidak boleh mengubah snapshot lama.
- Header menyimpan `generated_at` dan `version`; detail di-rebuild atomically saat regenerate.

## Sumber agregasi resmi

Generator wajib memakai sumber existing repo:

- Payroll runs: `HcmPayrollRun::where('status','finalized')->where('purpose','monthly')->where('period_id', $periodId)`.
- Payroll lines: `HcmPayrollLine` di-filter via run id.
- Bruto = `sum(amount)` lines `kind = addition` dengan `category` di-prefix `pph21_taxable_`.
- PPh21 = `sum(amount)` lines `kind = deduction` dengan `category` di-prefix `pph21_`.
- Identitas karyawan: ambil snapshot dari `users` + `EmployeeProfile` saat generate (nama, npwp, nik, contract_type).
- Mapping `contract_type -> employment_type`:
  - `permanent|pkwtt -> permanent`
  - `contract|pkwt -> contract`
  - lainnya -> ditolak (MVP).

Aturan ini membuat generator tidak perlu tabel turunan baru.

## CSV Export Format (DJP-style minimal)

Header CSV (urut, comma-separated, UTF-8 BOM):

1. `npwp`
2. `nik`
3. `nama`
4. `kategori_spt`
5. `bukti_potong_type`
6. `bruto`
7. `pph21`

Format angka: tanpa pemisah ribuan, dua desimal (`123456.00`). Periode dan tenant tercantum di nama file: `spt-masa_{company_uuid}_{periode}.csv`. Kebijakan kolom dapat diperluas tanpa breaking jika kolom baru dibubuhkan di akhir.

## Phase 3 - Integrasi dan Build

## Integrasi antar modul

Sumber data:

- payroll results final/locked
- pph21 results final
- employee profile + NPWP

Integrasi target:

- payroll-run status lock sebagai gate generate
- tax governance dipakai sebagai referensi validasi kualitas NPWP/PTKP (tanpa hitung ulang pajak)
- reporting/export reconciliation bisa memakai CSV sebagai evidence operasional

## Test plan minimal

Backend (PHPUnit):

- happy path: generate -> mark-ready -> submit
- generate ditolak jika tidak ada run `finalized` di periode (`SPT_PAYROLL_NOT_FINAL`)
- generate idempotent dengan `generationKey` sama
- duplikasi header aktif ditolak (`SPT_HEADER_DUPLICATE`)
- submit ditolak jika data wajib kosong (`SPT_DETAIL_INCOMPLETE`)
- submit ditolak jika total mismatch (`SPT_TOTAL_MISMATCH`)
- version conflict (`SPT_VERSION_CONFLICT`)
- transition invalid (`SPT_INVALID_TRANSITION`)
- tenant isolation: tenant A tidak melihat SPT tenant B
- 401 tanpa token, 403 tanpa permission

Frontend (Vitest):

- status badge mapping
- tombol disabled sesuai status
- error envelope handling

Manual E2E (per role HR Admin):

1. finalize payroll periode X
2. generate SPT periode X
3. review detail
4. tandai ready
5. submit
6. export CSV dan verifikasi kolom

## Build & gate

Sebelum merge ke main:

1. `bash scripts/local-test-gate.sh`
2. validasi docs feature + tracker update
3. update OpenAPI dan jalankan `bash scripts/check-api-docs-sync.sh`
4. update `docs/planning/active-hcm-templates-and-permissions.md` untuk permission baru
5. `bash scripts/check-shared-hosting-artifact-sync.sh` saat siap deploy

Jika kontrak API runtime berubah, sinkronkan:

- `docs/api/openapi.yaml`
- `docs/api/hcm-spt-masa-api.md` (file baru) atau bagian relevan di `docs/api/hcm-tax-governance-api.md`

## Risiko implementasi

- mismatch data jika generate dipanggil sebelum semua run periode `finalized`
- NPWP invalid yang menahan submit
- duplikasi header jika idempotency / version lock tidak dijaga ketat
- drift antara format CSV dan kebutuhan operasional pelaporan
- `intern`/`non_employee` belum punya backing schema di profile -> generator MVP harus eksplisit menolak

## Mitigasi

- hard-guard `hcm_payroll_runs.status = finalized`
- transactional generate + version lock
- pre-submit validation report di service `SptMasaValidationService`
- versi format CSV dikunci dan ditest snapshot-based
- klasifikasi `intern`/`non_employee` ditunda fase lanjutan + dicatat di tracker
