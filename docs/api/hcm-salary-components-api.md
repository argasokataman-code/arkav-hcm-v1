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
- **Persen default (opsional):**
  - `defaultPercent` — angka 0–100 (disimpan empat desimal); `null` jika nilai di slip diisi nominal (bukan rumus % di master).
  - `percentBasis` — dasar perhitungan jika `defaultPercent` terisi: `basic_wage`, `wage_bpjs_health`, `wage_bpjs_tk`, `gross_monthly_ter`, `thr_calculation_base`. **Wajib berpasangan:** keduanya diisi atau keduanya kosong (`null`), selain itu `422`.
- `isSystemLocked`: penanda origin seed/legacy untuk kebutuhan observasi UI. Setelah hardening CRUD 2026-04-27, flag ini **tidak lagi memblokir update atau delete**.

## Endpoints

### `GET /v1/hcm/salary-components`

- **200** `data[]` — array objek komponen (urut `sort_order`, `name`), camelCase seperti di `HcmSalaryComponentController::serialize`.

### `GET /v1/hcm/salary-components/{id}`

- **200** `data` — satu objek komponen (sama bentuk serialize dengan elemen list).
- **403** non-admin | **404** id tidak ada.

### `POST /v1/hcm/salary-components`

Body (JSON):

- Wajib: `name`, `kind`, `category`.
- Opsional: `code` (regex `^[a-z0-9_-]+$`, unik), `description`, `legalBasis`, `legalNotes`, `defaultPercent`, `percentBasis`, semua flag boolean, `isActive`, `sortOrder`.
- Baris baru: `isSystemLocked = false`.

**201** `data.id` | **403** | **422** (`code` duplikat, `category` tidak cocok `kind`, dll.).

### `PUT /v1/hcm/salary-components/{id}`

- Body selalu payload penuh seperti mutasi master: `code`, `name`, `kind`, `category`, `description`, `legalBasis`, `legalNotes`, `defaultPercent`, `percentBasis`, semua flag boolean (wajib eksplisit), `isActive`, `sortOrder`.
- Kategori tetap harus valid terhadap master kategori aktif untuk `kind` terkait.

**200** | **403** | **404** | **422**.

### `DELETE /v1/hcm/salary-components/{id}`

- Semua baris dapat dihapus permanen, termasuk komponen seed/legacy dan komponen yang sebelumnya ditandai integrasi/managed.
- Relasi downstream yang memakai foreign key `nullOnDelete` (mis. payroll items, overtime requests, payroll lines) akan otomatis dilepas tanpa mengubah histori slip lama.

**200** | **403** | **404** | **422**.

### `GET /v1/hcm/salary-component-categories`

- Mengembalikan master kategori dinamis per `kind`, termasuk row seed yang diberi badge sistem di UI.

### `POST /v1/hcm/salary-component-categories`

- Wajib: `kind`, `code`, `name`.
- Opsional: `description`, `isActive`, `sortOrder`.

### `PUT /v1/hcm/salary-component-categories/{id}`

- Body: `kind`, `code`, `name`, `description`, `isActive`, `sortOrder`.
- Jika `kind`/`code` berubah, runtime ikut memindahkan komponen yang masih memakai pasangan lama ke pasangan baru.

### `DELETE /v1/hcm/salary-component-categories/{id}`

- Semua kategori dapat dihapus, termasuk kategori seed/legacy bertanda sistem.
- Delete kategori akan ikut menghapus seluruh komponen yang memakai kategori tersebut agar runtime tidak menyisakan referensi kategori yatim.
- Relasi turunan dari komponen yang ikut terhapus tetap aman karena foreign key runtime memakai `nullOnDelete`.

## Seed

Migrasi `2026_04_10_130000_create_hcm_salary_components_table` mengisi komponen acuan (upah pokok, tunjangan tetap contoh, lembur, THR, bonus, natura, iuran BPJS pekerja, PPh 21 TER/rekonsiliasi, baris beban PK, potongan internal).

Migrasi `2026_04_11_120000_add_percent_fields_to_hcm_salary_components_table` menambah kolom persen dan mengisi **nilai ilustratif** pada beberapa baris iuran (BPJS Kes/TK pekerja & PK) — **wajib diverifikasi** terhadap tarif berlaku; disesuaikan lewat UI/API (`PUT`).

## Tes

`HcmSalaryComponentApiTest` — admin CRUD penuh untuk komponen + kategori, non-admin 403 pada list, delete kategori menghapus komponen turunannya.

## OpenAPI (Swagger UI)

`docs/api/openapi.yaml` — tag **Payroll**: path `/v1/hcm/salary-components`, `/v1/hcm/salary-components/{id}`, `/v1/hcm/salary-component-categories`, dan `/v1/hcm/salary-component-categories/{id}` beserta skema request/response terkait.
