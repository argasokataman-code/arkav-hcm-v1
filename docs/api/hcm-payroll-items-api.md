# HCM — Payroll items (katalog)

Prefix: `/v1/hcm` · middleware **`api.token`** · envelope `{ success, data?, error? }`.

## Tenant context

- Semua endpoint di-scope ke `activeCompany`; `applyTenantScope` mengikat `company_id` atau fallback ke template global (`company_id IS NULL`).
- **Global Super Admin bypass:** user dengan `users.is_super_admin = 1` melewati filter `company_id` sehingga melihat seluruh payroll item + assignment lintas tenant.

## Tujuan

Sumber utama **admin** untuk mendefinisikan baris yang dipakai konteks payroll (katalog `hcm_payroll_items`): **kustom** atau **taut ke** baris existing `hcm_salary_components` (seed). Halaman web: **`/payroll`**.

Halaman **Master komponen gaji** tetap aktif di **`/salary-component-master`** untuk CRUD seed `hcm_salary_components`. API **`/salary-components`** dipakai baik oleh halaman master tersebut maupun oleh `/payroll` saat admin menautkan payroll item ke master aktif.

## RBAC

| Endpoint | Siapa |
|----------|--------|
| `GET/POST/PUT/DELETE /payroll-items` (+ `PUT/DELETE …/{id}`), `GET /payroll-items/export` | **HCM Admin** saja (`403` `AUTH_FORBIDDEN`) |

## `GET /payroll-items`

Query opsional:

- `kind` — `addition` | `deduction` — filter baris (halaman `/payroll` memakai `addition`, `/payroll-deduction` memakai `deduction`).

**200** `data`:

- `payrollItems[]` — `id`, `salaryComponentId`, `linkedToMaster`, `code`, `name`, `kind`, `category`, `notes`, `sortOrder`, `isActive`, `masterDefaultPercent`, `masterPercentBasis` (jika tertaut master).

**200** `meta`:

- `linkedSalaryComponentIds[]` — daftar `hcm_salary_components.id` yang sudah dipakai suatu payroll item (untuk UI dropdown taut, tetap akurat walau list difilter `kind`).

Catatan sinkronisasi: untuk item `linkedToMaster=true`, field `code`/`name`/`kind`/`category` akan selalu mengikuti master `hcm_salary_components` terbaru.

## `GET /payroll-items/export`

Export katalog payroll items.

Query opsional:

- `kind` — `addition` | `deduction`
- `format` — `csv` (default) | `xlsx`

Response stream file dengan kolom audit katalog (ID, master link, code, name, kind, category, notes, sort, active, default percent).

## `POST /payroll-items`

Dua mode (salah satu):

**A) Taut ke master** — body wajib berisi `salaryComponentId` (integer, exists `hcm_salary_components.id`). Field `name`/`kind`/`category`/`code` diisi server dari master. Opsional: `notes`, `sortOrder`, `isActive`.

- Jika komponen sudah punya payroll item → **422** `PAYROLL_ITEM_LINK_TAKEN`.
- Jika komponen tidak aktif (`is_active=false`) → **422** `PAYROLL_ITEM_MASTER_INACTIVE`.

**B) Item kustom** — tanpa `salaryComponentId` (atau tidak mengirim field tersebut): wajib `name`, `kind` (`addition`|`deduction`), `category` (harus cocok dengan `kind`, sama set kategori dengan `HcmSalaryComponent`). Opsional: `code` (regex `^[a-z0-9_-]+$`, unik jika diisi), `notes`, `sortOrder`, `isActive`.

**201** `{ success: true, data: { id } }`

## `PUT /payroll-items/{id}`

- **Item tertaut master:** umumnya hanya `notes`, `sortOrder`, `isActive`.
- **Lepas tautan:** kirim `salaryComponentId: null` **plus** `name`, `kind`, `category` (wajib), `code` (opsional, unik), serta opsional `notes`, `sortOrder`, `isActive`.
- **Item kustom:** dapat mengubah `name`, `code`, `kind`, `category`, `notes`, `sortOrder`, `isActive`. Untuk **menaut** ke master: kirim `salaryComponentId` (integer); nama/jenis/kategori/kode disalin dari master (satu master hanya satu item).

**422** jika tautan master bentrok (`PAYROLL_ITEM_LINK_TAKEN`) atau kombinasi `kind`/`category` tidak valid.

## `DELETE /payroll-items/{id}`

Menghapus baris `hcm_payroll_items` saja (tidak menghapus `hcm_salary_components`).

## Web

- Route **`/payroll`** — middleware **`hcm.web.admin`**, JS `payroll-items-data.js` + partial `hcm/partials/payroll-item-modals.blade.php`.
- Route **`/salary-component-master`** — middleware **`hcm.web.admin`**, JS `salary-component-master-data.js` untuk CRUD master komponen gaji.
- Dropdown taut master memakai **`GET /v1/hcm/salary-components`** (hanya master yang belum punya payroll item lain).

## Tes

`HcmPayrollItemApiTest`.
