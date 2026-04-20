# HCM Termination API (Phase 2)

Base path: `/v1/hcm`

## RBAC

- **HCM Admin**: list penuh `GET /terminations`, create/update/delete, serta `GET /terminations/{id}` dan `GET /terminations/users/{userId}/terminations` untuk semua user.
- **Karyawan (non-admin)**: **tidak** boleh `GET /terminations` (list admin), **tidak** boleh mutasi.
- **Karyawan** boleh:
  - `GET /terminations/{id}` hanya jika `termination.user_id === auth.id`
  - `GET /terminations/users/{userId}/terminations` hanya jika `userId === auth.id`

## Data model (ringkas)

- `userId` (required, **UUID user**) — target user wajib anggota aktif company yang sedang aktif.
- `department` (optional, string ≤ 150)
- `terminationType` (required, string ≤ 150) — contoh: Retirement, Layoff, Insubordination
- `reason` (required, string ≤ 2000)
- `noticeDate`, `terminationDate` (required, `YYYY-MM-DD`; `terminationDate` ≥ `noticeDate`)
- `status` (optional, default `pending`): `pending` | `approved` | `finalized` | `cancelled`
- `notes` (optional, string ≤ 2000)
- `settlementPayrollPeriod` (optional, `YYYY-MM`) — override label periode payroll target untuk final settlement. Jika tidak dikirim saat `finalized`, server akan resolve periode payroll aktual terdekat.
- `finalSalaryAmount` (optional, numeric ≥ 0) — jika tidak dikirim saat `finalized`, server akan derive dari preview payroll/kompensasi aktif.
- `finalAllowanceAmount` (optional, numeric ≥ 0) — jika tidak dikirim saat `finalized`, server akan derive dari preview payroll/kompensasi aktif.
- `finalDeductionAmount` (optional, numeric ≥ 0) — jika tidak dikirim saat `finalized`, server akan derive dari preview payroll/kompensasi aktif.
- `assetReturnNotes` (optional, string ≤ 2000)
- `clearanceNotes` (optional, string ≤ 2000)
- `settlementBreakdown` (optional, array) — snapshot breakdown settlement terstruktur.
- `clearanceItems` (optional, array) — snapshot clearance item terstruktur, saat ini terutama asset assignment aktif.

Jika `status = finalized`, runtime saat ini mewajibkan minimum:

- `clearanceNotes`

Saat `status = finalized`, runtime akan otomatis:

- resolve payroll period aktual terdekat dan menyimpan link `payrollPeriodId`;
- menghitung gaji pokok dan tunjangan tetap secara prorata sampai `terminationDate`;
- menambahkan komponen payroll bulanan lain sebagai reference bila payroll run periodenya sudah ada;
- menambahkan kompensasi PKWT bila profile contract memang due pada bulan termination;
- mengambil `clearanceItems` dari asset assignment aktif yang belum return.

Response detail/list sekarang mengembalikan `settlement` object bila snapshot finalization tersedia:

- `payrollPeriod`
- `payrollPeriodId`
- `payrollPeriodStatus`
- `finalSalaryAmount`
- `finalAllowanceAmount`
- `finalDeductionAmount`
- `finalNetAmount` (dihitung server: salary + allowance - deduction)
- `assetReturnNotes`
- `clearanceNotes`
- `breakdown[]`
- `clearanceItems[]`
- `clearanceOutstandingCount`

## Identifier status

- Payload `userId` untuk create/update memakai **UUID user**.
- Row termination endpoint `/{id}` saat ini masih **numeric legacy id**.
- Path `/terminations/users/{userId}/terminations` saat ini masih **numeric legacy user id**.

## Endpoints

### GET `/terminations`

List (**HCM admin only**). Query: `q`, `dateFrom`/`dateTo` (filter `termination_date`), `perPage` 1..100.

### GET `/terminations/settlement-preview`

Preview settlement untuk modal finalization (**HCM admin only**). Query:

- `userId` (**UUID user**, required)
- `terminationDate` (`YYYY-MM-DD`, optional)

Response mengembalikan:

- `resolvedPeriod` — periode payroll aktual terdekat (akan disimpan saat finalization)
- `summary` — salary/allowance/deduction/net
- `source` — `termination_policy_prorated`, `termination_policy_prorated_plus_payroll_reference`, atau `termination_policy_prorated_plus_pkwt`
- `breakdown[]` — komponen granular settlement
- `clearance.items[]` — kewajiban clearance terstruktur (asset aktif yang belum returned)

Endpoint ini dipakai UI untuk tombol `Refresh from payroll & assets` di modal Termination.

### GET `/terminations/{id}`

Detail dengan **numeric legacy id**. **404**: `TERMINATION_NOT_FOUND`. **403**: `AUTH_FORBIDDEN` untuk karyawan bukan pemilik baris.

### GET `/terminations/{id}/settlement-preview`

Preview settlement untuk record Termination yang sudah ada (**HCM admin only**). Identifier tetap **numeric legacy termination id**.

### POST `/terminations/{id}/clearance-items/{assignmentId}/return`

Trigger pengembalian satu clearance item asset langsung dari context Termination (**HCM admin only**). Identifier:

- `id` = **numeric legacy termination id**
- `assignmentId` = **numeric asset assignment id**

Payload opsional:

- `returnedDate` (`YYYY-MM-DD`)
- `conditionAtReturn` (string ≤ 30)
- `notes` (string ≤ 5000)

Server akan memanggil lifecycle asset return, lalu me-refresh snapshot clearance di record Termination sehingga `clearanceOutstandingCount` dan `assetReturnNotes` ikut berubah.

### GET `/terminations/users/{userId}/terminations`

Riwayat per employee (paginated) dengan **numeric legacy user id**. **404** jika user tidak ada.

### POST `/terminations`

Create (**HCM admin only**). `userId` wajib **UUID user** dan server menolak user di luar tenant aktif. Saat `status = finalized`, server akan auto-link ke payroll period aktual dan mengisi snapshot breakdown/clearance bila payload belum mengirim override eksplisit. **201**: `{ "success": true, "data": { "id": … } }`.

### PUT `/terminations/{id}`

Update partial (**HCM admin**). Pasangan tanggal harus tetap valid (422 `VALIDATION_ERROR` jika `terminationDate` < `noticeDate`). Saat record berada di status `finalized`, update akan me-refresh link payroll period dan snapshot settlement/clearance dari source runtime kecuali field override dikirim eksplisit.

### DELETE `/terminations/{id}`

Delete (**HCM admin**). **200**: `{ "success": true }`.
