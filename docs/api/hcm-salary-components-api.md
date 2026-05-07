# HCM — Master komponen gaji (`/v1/hcm/salary-components`)

Master data untuk **nama komponen** pada slip gaji beserta **metadata peraturan Indonesia** (BPJS, THR, PPh 21 TER/rekonsiliasi, lembur, baris beban perusahaan). Field hukum di DB bersifat **informatif** untuk tim HR/payroll; engine agregasi slip penuh belum wajib, tetapi **pengajuan lembur** sudah menaut ke baris komponen upah lembur (`hcm_salary_component_id` pada `overtime_requests` — lihat `docs/api/hcm-overtime-api.md`).

## RBAC

- **Semua endpoint:** `Authorization: Bearer` + middleware `api.token`.
- **Semua verb (termasuk `GET`):** hanya **HCM Admin** (`EnsuresHcmAdmin`). Karyawan non-admin mendapat `403` + `error.code: AUTH_FORBIDDEN`.

## Model data

- `kind`: `addition` (pendapatan) | `deduction` (potongan).
- `category`: kode kategori aktif dari master kategori `hcm_salary_component_categories` sesuai `kind`. Bila tabel master kategori belum ada, runtime fallback ke daftar seed bawaan.
- **Flag perhitungan (boolean):**
  - `includeBpjsHealthWageBase` — masuk pertimbangan dasar upah BPJS Kesehatan (untuk engine mendatang).
  - `includeBpjsTkWageBase` — dasar upah BPJS Ketenagakerjaan.
  - `includeThrCalculationBase` — masuk basis THR (biasanya upah pokok + tunjangan tetap; bukan THR itu sendiri).
  - `includePph21TerGross` — masuk bruto bulanan untuk lapisan TER / PPh 21.
  - `includePph21AnnualReconciliation` — relevan penyesuaian tahunan/Desember.
  - `subjectOvertimeRegulation` — komponen terkait aturan lembur (UU Ketenagakerjaan).
  - `affectsNetPay` — memengaruhi THP pekerja.
  - `employerCostLine` — baris informatif beban perusahaan (JKK, JKM, iuran PK, dll.).
- **Klasifikasi pajak eksplisit:**
  - `taxTreatmentCode` menyimpan klasifikasi audit-safe untuk tenant tax mapping.
  - Nilai aktif: `pph21_taxable_full`, `pph21_taxable_partial`, `non_object`, `deductible`, `pph21_final`, `pph21_separate`, `employer_display_only`.
  - Runtime tetap menurunkan flag legacy PPh 21 dari nilai ini untuk kompatibilitas payroll engine yang belum sepenuhnya dipindah.
- **Persen default (opsional):**
  - `defaultPercent` — angka 0–100 (disimpan empat desimal); `null` jika nilai di slip diisi nominal (bukan rumus % di master).
  - `percentBasis` — dasar perhitungan jika `defaultPercent` terisi: `basic_wage`, `wage_bpjs_health`, `wage_bpjs_tk`, `gross_monthly_ter`, `thr_calculation_base`. **Wajib berpasangan:** keduanya diisi atau keduanya kosong (`null`), selain itu `422`.
- `isSystemLocked`: penanda bahwa komponen didaftarkan oleh governance/system registry dan **memblokir** update/delete langsung dari master registry.
- `sourceModule`: asal registrasi komponen. Nilai umum: `system`, `bpjs`, `allowance`, `pph21`, `overtime`, `thr`, `pkwt`, atau `null` untuk komponen custom tenant.

## Endpoints

### `GET /v1/hcm/salary-components`

- **200** `data[]` — array objek komponen (urut `sort_order`, `name`), camelCase seperti di `HcmSalaryComponentController::serialize`.

### `GET /v1/hcm/salary-components/employee-profiles`

- Endpoint snapshot integrasi employee-level untuk audit UI tab **Profil Integrasi Karyawan** pada halaman Salary Component.
- Path ini tetap numeric/UUID-agnostic (tidak memakai path id); identifier user di payload mengembalikan dua bentuk:
  - `userId` (numeric legacy internal), dan
  - `userUuid` (UUID canonical runtime).
- Query opsional:
  - `page` (default `1`),
  - `perPage` (default `200`, max `500`),
  - `search` (nama/email/phone/designation/team).
- **200** `data.rows[]` berisi:
  - identitas karyawan (`employeeCode`, `fullName`, `email`, `phone`, `departmentName`, `designationName`, `baseSalary`),
  - indikator kebersihan identitas (`hasCleanIdentity`, `identityGaps[]`),
  - ringkasan assignment aktif (`assignmentSummary.totalActiveAssignments`, `allowanceAssignments`, `allowanceGovernanceAssignments`, `sourceModuleCounts`, `componentCodes[]`),
  - matrix integrasi lintas domain (`integrationSummary.checks[]`) untuk:
    - `pph21` (policy tenant + profil pajak employee),
    - `bpjs` (policy tenant + membership employee),
    - `allowance` (policy allowance aktif + assignment governance employee),
    - `payroll` (adanya payroll assignment aktif),
  - daftar gap integrasi (`integrationSummary.gaps[]`),
  - status integrasi (`integrationStatus`: `ready` | `partial` | `missing`).
- `meta.statusSummary` mengembalikan agregat jumlah status (`ready`, `partial`, `missing`) untuk panel ringkasan.

### `GET /v1/hcm/salary-components/{id}`

- **200** `data` — satu objek komponen (sama bentuk serialize dengan elemen list).
- **403** non-admin | **404** id tidak ada.

### `POST /v1/hcm/salary-components`

- Pembuatan komponen manual dari tenant **dinonaktifkan**.
- Endpoint selalu mengembalikan **403** dengan `error.code = MANUAL_COMPONENT_CREATION_DISABLED`.
- Penambahan komponen baru dilakukan lewat modul governance sumber (`sourceModule`) sesuai domain fitur.

### `PUT /v1/hcm/salary-components/{id}`

- Path `id` memakai numeric legacy internal (`whereNumber('id')`), bukan UUID.
- Body selalu payload penuh seperti mutasi master: `code`, `name`, `kind`, `category`, `description`, `legalBasis`, `legalNotes`, `defaultPercent`, `percentBasis`, semua flag boolean (wajib eksplisit), `taxTreatmentCode` (opsional), `isActive`, `sortOrder`.
- Bila `taxTreatmentCode` dikirim, runtime otomatis menyelaraskan flag legacy PPh 21 dan `employerCostLine` sesuai klasifikasi yang dipilih.
- Kategori tetap harus valid terhadap master kategori aktif untuk `kind` terkait.

**200** | **403** | **404** | **422**.

Catatan:
- Jika komponen `isSystemLocked = true`, endpoint mengembalikan `403` dengan `error.code = SYSTEM_LOCKED` dan perubahan harus dilakukan dari modul governance asal (`sourceModule`).

### `PATCH /v1/hcm/salary-components/{id}/tax-flags`

- Path `id` memakai numeric legacy internal (`whereNumber('id')`).
- Body minimal salah satu dari:
  1. `taxTreatmentCode` untuk klasifikasi eksplisit baru.
  2. `includePph21TerGross`.
  3. `includePph21AnnualReconciliation`.
- Jika `taxTreatmentCode` dikirim, response `200` mengembalikan `data` komponen hasil serialize terbaru agar UI bisa langsung me-refresh klasifikasi, payroll effect, dan flag turunannya.
- Jika hanya flag legacy yang dikirim, runtime tetap menghitung ulang `taxTreatmentCode` tersimpan secara deterministik.

**200** | **403** | **404** | **422**.

### `DELETE /v1/hcm/salary-components/{id}`

- Hanya komponen custom tenant (`isSystemLocked = false`) yang dapat dihapus permanen dari registry.
- Komponen governance/system yang locked mengembalikan `403` + `error.code = SYSTEM_LOCKED`.
- Relasi downstream yang memakai foreign key `nullOnDelete` (mis. payroll items, overtime requests, payroll lines) akan otomatis dilepas tanpa mengubah histori slip lama hanya untuk komponen custom yang memang boleh dihapus.

**200** | **403** | **404** | **422**.

### `GET /v1/hcm/salary-component-categories`

- Mengembalikan master kategori dinamis per `kind`, termasuk row seed yang diberi badge sistem di UI.

### `POST /v1/hcm/salary-component-categories`

- Master kategori bersifat global/default dan **read-only** untuk tenant.
- Endpoint selalu **403** dengan `error.code = CATEGORY_MASTER_READ_ONLY`.

### `PUT /v1/hcm/salary-component-categories/{id}`

- Master kategori bersifat global/default dan **read-only** untuk tenant.
- Endpoint selalu **403** dengan `error.code = CATEGORY_MASTER_READ_ONLY`.

### `DELETE /v1/hcm/salary-component-categories/{id}`

- Master kategori bersifat global/default dan **read-only** untuk tenant.
- Endpoint selalu **403** dengan `error.code = CATEGORY_MASTER_READ_ONLY`.

## Seed

Migrasi `2026_04_10_130000_create_hcm_salary_components_table` mengisi komponen acuan (upah pokok, tunjangan tetap contoh, lembur, THR, bonus, natura, iuran BPJS pekerja, PPh 21 TER/rekonsiliasi, baris beban PK, potongan internal).

Migrasi `2026_04_11_120000_add_percent_fields_to_hcm_salary_components_table` menambah kolom persen dan mengisi **nilai ilustratif** pada beberapa baris iuran (BPJS Kes/TK pekerja & PK) — **wajib diverifikasi** terhadap tarif berlaku; disesuaikan lewat UI/API (`PUT`).

## Tes

`HcmSalaryComponentApiTest` — list/detail komponen tetap tersedia untuk admin, create komponen manual ditolak (`MANUAL_COMPONENT_CREATION_DISABLED`), dan seluruh mutasi kategori ditolak (`CATEGORY_MASTER_READ_ONLY`).

## OpenAPI (Swagger UI)

`docs/api/openapi.yaml` — tag **Payroll**: path `/v1/hcm/salary-components`, `/v1/hcm/salary-components/{id}`, `/v1/hcm/salary-component-categories`, dan `/v1/hcm/salary-component-categories/{id}` beserta skema request/response terkait.

---
> **2026-05-07**: Internal bug fix — added missing `resolveTaxTreatmentCodeFromValidated()` private method. No API contract change.
