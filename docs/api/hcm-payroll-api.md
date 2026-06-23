# HCM — Payroll period & run (Phase 1)

Prefix: `/v1/hcm` · middleware **`api.token`** · envelope `{ success, data?, error? }`.

Tenant context:
- Endpoint payroll membaca `activeCompany` dari middleware tenant context.
- Header opsional: `X-Company-Id` atau `X-Company-Code`.
- Jika company tidak dimiliki user maka request ditolak `403` dengan `error.code = TENANT_FORBIDDEN`.
- Scope ini berlaku untuk endpoint payroll period/run, payroll items, THR settings/batch, dan PKWT compensation preview/posting.
- Untuk payroll run, alur finalize/disburse/reset juga mempertahankan tenant scope pada fetch period/run lanjutan di dalam transaksi.
- **Global Super Admin bypass:** user dengan `users.is_super_admin = 1` melewati scope `company_id` pada payroll period/run/item queries di controller API. Service-level calculators (THR, PKWT, monthly payslip) tetap per-tenant scoped karena kalkulasinya memang per-company.

## Ringkasan

Fondasi **actual payroll** per kalender bulan: **periode** (`hcm_payroll_periods`), **run** (`hcm_payroll_runs` — status `draft` / `finalized`, kolom **`purpose`**: `monthly` untuk gaji rutin, `thr` untuk THR massal, `pkwt_compensation` untuk kompensasi PKWT off-cycle), **baris slip** (`hcm_payroll_lines`).

Phase 1.1 (April 2026) — **hitung draft** dari profil karyawan + komponen periode berjalan:

- **`base_salary`** → selalu satu baris addition **upah pokok** per karyawan eligible (nilai ≥ 0), agar karyawan **active/probation** tidak “hilang” dari run bila gaji pokok 0.
- **Allowance tetap** → tidak lagi dibaca dari kolom kompensasi legacy; allowance yang ikut payroll monthly harus datang dari assignment payroll item/governance yang aktif.
- **Lembur approved** dalam periode ikut diakumulasi sebagai addition payroll bulanan.
- **Potongan karyawan** dasar (mis. BPJS & **PPh21 bulanan berbasis lookup TER A/B/C** sesuai status pajak) ikut dibentuk sebagai deduction lines.
- Slip bulanan mandiri sudah tersedia sebagai **JSON summary** (`GET /payroll/my-slip`) dan **PDF download** (`GET /payroll/my-slip-pdf`) setelah run periode berstatus **`finalized`**, termasuk summary overtime eksplisit (`overtime.amountTotal`, `totals.overtimeTotal`).
- Listing admin payslip (`GET /payroll/admin-slips`) kini menyertakan helper `emailDelivery` per baris (`status`, `label`, `canResend`, `attemptedAt`, `lastError`) berbasis log `notification_deliveries`, sehingga admin bisa cepat mendeteksi email gagal dan melakukan resend.
- Admin kini punya **Monthly Report** terpusat via **`GET /payroll/monthly-report`** dan **`GET /payroll/monthly-report/export`** untuk melihat gabungan run `monthly`, `thr`, dan `pkwt_compensation` per karyawan-periode, lengkap dengan export CSV/XLSX yang siap dipakai sebagai laporan operasional termasuk kolom overtime eksplisit.
- UI `/payslip` dapat menemukan periode slip terbaru milik user lewat **`GET /payroll/my-slip-latest-period`** untuk fallback awal jika bulan berjalan belum memiliki run final.
- Preview **kompensasi PKWT** bulanan untuk admin tersedia via **`GET /payroll/pkwt-compensations`**, dapat diposting menjadi draft payroll via **`POST /payroll/pkwt-compensations/post-payroll`**, lalu ditandai **paid** secara manual lewat endpoint generic **`POST /payroll-runs/{id}/disburse`** seperti flow off-cycle lain.
- Export reconciliation payroll kini distandardkan untuk flow `payroll_run/disburse`, `thr_batch/disburse|post_payroll`, dan `pkwt_compensation/post_payroll` dengan schema payment-ready yang sama: `payroll_type`, `reference_period`, `reference_id`, `employee_id`, `employee_name`, `bank_name`, `account_number`, `account_holder_name`, `bank_branch`, `gross_total`, `overtime_total`, `deductions_total`, `transfer_amount`, `bank_data_status`, `dataset_checksum`.
- Admin payroll kini dapat membuat **assignment payroll item per karyawan** via endpoint `payroll-item-assignments`; assignment aktif otomatis dimasukkan ke draft payroll bulanan sesuai tanggal efektif.
- Nominal negatif dari DB diperlakukan sebagai **0**.
- Halaman payroll bulanan kini **auto-load periode aktif**, mendukung **select-all / subset** penandaan pembayaran manual, dan draft periodik direfresh scheduler **00:00 WIB** selama periodenya masih `open`.
- Policy bulanan tenant kini dapat dikonfigurasi via **`GET/PUT /payroll/settings`** untuk `paydayDay`, `cutoffOffsetDays`, `payrollTimezone`, `disburseBeforePaydayAllowed`, dan `paydayHolidayStrategy`.
- Draft run `purpose=monthly` kini menyimpan **`policySnapshot`** agar resolved payday/cutoff yang dipakai saat draft dibuat tetap audit-friendly walau setting tenant berubah sesudahnya.

**Belum** di-cover penuh: posting GL, tabel audit terpisah, dan aturan pajak/payroll lanjutan.

## RBAC

| Endpoint | Siapa |
|----------|--------|
| `GET /payroll-periods`, `GET /payroll-periods/{id}`, `GET /payroll-periods/active` | **HCM Admin** dengan `payroll.view` |
| `POST /payroll-periods` | **HCM Admin** dengan `payroll.manage` |
| `POST /payroll-periods/{id}/calculate-draft` | **HCM Admin** dengan `payroll.run` |
| `GET /payroll/settings`, `PUT /payroll/settings`, `GET /payroll/settings/history` | **HCM Admin** saja (`settings.manage`) — konfigurasi payday/cutoff payroll bulanan tenant aktif, plus audit trail governance |
| `GET /payroll-runs/history`, `GET /payroll-runs/{id}`, `GET /payroll/admin-run-slips`, `GET /payroll/admin-slips`, `GET /payroll/monthly-report`, `GET /payroll/monthly-report/export` | **HCM Admin** dengan `payroll.view` |
| `POST /payroll-runs/{id}/finalize`, `POST /payroll-runs/{id}/void` | **HCM Admin** dengan `payroll.finalize` |
| `POST /payroll-runs/{id}/mock-hosted-checkout`, `POST /payroll-runs/{id}/mock-hosted-checkout/confirm`, `POST /payroll-runs/{id}/disburse`, `POST /payroll-runs/{id}/reset-payments` | **HCM Admin** dengan `payroll.disburse` — dua endpoint `mock-hosted-checkout*` saat ini tetap terdaftar tetapi diblokir dengan respons export-only |
| `GET /payroll/my-slip-latest-period` | **Semua user terautentikasi** — cari periode terbaru yang punya run payroll `finalized` untuk user pemanggil |
| `GET /payroll/my-slip` | **Semua user terautentikasi** — ringkasan slip gaji milik sendiri untuk periode query (`earnings`, `deductions`, `overtime`, `totals`, `downloadUrl`) jika ada run **`finalized`** |
| `GET /payroll/my-slip-pdf` | **Semua user terautentikasi** — unduh PDF slip gaji milik sendiri untuk periode query; `404` bila belum ada run final |
| `GET /payroll/my-slip-lines` | **Semua user terautentikasi** — hanya baris **`user_id` = pemanggil**; data slip hanya jika ada run **`finalized`** untuk periode tersebut; baris **digabung** dari run **`purpose` `monthly`**, **`thr`**, dan **`pkwt_compensation`** (jika ada pada bulan yang sama) |
| `GET /payroll/my-thr-slip` | **Semua user terautentikasi** — JSON slip THR milik sendiri (baris batch yang sudah punya PDF); `data.history` untuk pemilih tahun |
| `GET /payroll/pkwt-compensations`, `POST /payroll/pkwt-calculate` | **HCM Admin** dengan `payroll.view` |
| `POST /payroll/pkwt-compensations/post-payroll` | **HCM Admin** dengan `payroll.pkwt.manage` — generate/rebuild draft payroll kompensasi PKWT |
| `GET /payroll/thr-batch/lines/{line}/slip` | **HCM admin** semua baris; **karyawan** hanya jika **`line` milik pemanggil** (`403` jika bukan) |
| `POST /payroll/thr-calculate` | **HCM Admin** saja — estimasi THR bruto (Permenaker 6/2016, pro rata); **bukan** slip final dan **tanpa** PPh 21 TER |
| `GET /payroll/thr-settings`, `GET /payroll/thr-batch` | **HCM Admin** dengan `payroll.view` |
| `PUT /payroll/thr-settings/{calendarYear}`, `POST /payroll/thr-batch/generate`, `POST /payroll/thr-batch/post-payroll`, `POST /payroll/thr-batch/send-slip` | **HCM Admin** dengan `payroll.thr.manage` |
| `POST /payroll/thr-batch/disburse` | **HCM Admin** dengan `payroll.disburse` — batch THR: catat pembayaran manual eksternal |
| `GET /payroll-item-assignments` | **HCM Admin** dengan `payroll.view` |
| `POST /payroll-item-assignments`, `PUT /payroll-item-assignments/{id}`, `DELETE /payroll-item-assignments/{id}` | **HCM Admin** dengan `payroll.manage` — assignment payroll item custom per karyawan |

## Endpoints

### `GET /payroll-periods`

Daftar periode (maks. 100 terbaru), urut tahun & bulan menurun.

**Response 200** `data[]`: `id`, `periodYear`, `periodMonth`, `status`, `createdAt`, `updatedAt`.

### `POST /payroll-periods`

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `periodYear` | ya | integer 2000–2100 |
| `periodMonth` | ya | integer 1–12 |

**422** `PAYROLL_PERIOD_EXISTS` jika kombinasi tahun+bulan sudah ada.

**201** `data`: objek periode baru (`status` awal `open`).

### `GET /payroll-periods/{id}`

**200** `data`: periode + `latestRun` (ringkas: `id`, `payrollPeriodId`, `status`, `calculatedAt`, `finalizedAt`, `finalizedByUserId`, `policySnapshot`, `lateArrivalBuffer`) atau `null`.

`policySnapshot` hanya muncul untuk run `purpose=monthly` dan berisi salinan policy tenant saat draft dibuat: `paydayDay`, `cutoffOffsetDays`, `payrollTimezone`, `disburseBeforePaydayAllowed`, `paydayHolidayStrategy`, `resolvedPaydayDate`, `resolvedCutoffDate`, `draftDataAsOfDate`.

### `POST /payroll-periods/{id}/calculate-draft`

Membangun ulang run `draft` payroll untuk periode menggunakan source of truth tenant terbaru.

- Endpoint ini memerlukan permission **`payroll.run`**.
- Jika sudah ada run `draft` bulanan pada periode yang sama, draft lama **dibersihkan lalu dibangun ulang** agar operator mendapat snapshot payroll terbaru.
- Jika belum ada draft, endpoint membuat run `draft` baru dan menyisipkan baris per karyawan `active` / `probation` yang punya profil (lihat ringkasan engine di atas).

**422** `PAYROLL_PERIOD_FINALIZED` jika periode sudah memiliki run `finalized` dan environment bukan `local` / `development` / `testing`.

Pada environment `local`, `development`, atau `testing`, finalized monthly run lama akan di-void otomatis dan metadata pembayaran line dibersihkan agar draft bisa direbuild untuk kebutuhan QA/dev.

**200** `data`: `run` (ringkas), `lineCount`, `employeeCount`, `anomalies`, `reusedExistingDraft`.

Run ringkas kini juga dapat memuat `policySnapshot` untuk draft `monthly`, sehingga UI dan audit dapat melihat resolved payday/cutoff yang dipakai saat draft tersebut dihitung.

Run ringkas juga memuat `lateArrivalBuffer` (jika tersedia) sebagai backlog pasca-cutoff baseline. Struktur ini berisi metadata capture (`capturedAt`, `asOfDate`, rentang periode, mode migrasi) dan ringkasan source (`overtimeRequests`, `payrollItemAssignments`) dengan `totalCount` + contoh entries terbatas.

`anomalies` saat ini memuat:

- `missingTaxProfileUserCount`
- `missingTaxProfileUserIds[]`

Digunakan untuk audit cepat user yang perhitungan PPh21-nya masih fallback tax status (`TK0`) karena profil pajak belum lengkap.

### `GET /payroll-periods/active`

Mengembalikan periode payroll aktif (`status=open`) terbaru. Jika belum ada periode open, endpoint akan mencari periode bulan berjalan yang sudah ada (termasuk status `posted`) untuk mencegah duplikasi period; jika tetap tidak ada, baru membuat periode bulan berjalan otomatis.

**200** `data`: shape periode + `latestRun` (jika ada).

### `GET /payroll/settings`

Mengambil konfigurasi payroll bulanan tenant aktif. Endpoint ini dipakai halaman payroll run untuk menampilkan form policy cutoff/payday dan preview resolved date untuk periode aktif.

**200** `data`:

- `paydayDay` — integer 1–31
- `cutoffOffsetDays` — integer 0–15
- `payrollTimezone` — timezone IANA, default `Asia/Jakarta`
- `disburseBeforePaydayAllowed` — boolean guard untuk operasi pembayaran lebih awal
- `paydayHolidayStrategy` — strategi resolve payday saat jatuh di non-working day: `previous_working_day` (default), `next_working_day`, atau `exact_calendar_day`

Jika setting tenant belum pernah disimpan, endpoint mengembalikan default runtime: payday `28`, cutoff `3`, timezone `Asia/Jakarta`, `disburseBeforePaydayAllowed = false`, dan `paydayHolidayStrategy = previous_working_day`.

### `PUT /payroll/settings`

Menyimpan konfigurasi payroll bulanan tenant aktif.

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `paydayDay` | tidak | integer 1–31 |
| `cutoffOffsetDays` | tidak | integer 0–15 |
| `payrollTimezone` | tidak | timezone IANA valid |
| `disburseBeforePaydayAllowed` | tidak | boolean |
| `paydayHolidayStrategy` | tidak | enum: `previous_working_day` \| `next_working_day` \| `exact_calendar_day` |

**200** `data`: shape yang sama dengan `GET /payroll/settings`.

**Governance & Audit Trail**

Setiap update ke `/payroll/settings` secara otomatis:
- Menciptakan **snapshot** dari seluruh konfigurasi payroll yang berlaku (disimpan dalam `payroll_settings_snapshots`)
- Mencatat setiap perubahan dalam **audit log** yang immutable (`payroll_settings_audit_log`), termasuk user yang melakukan perubahan, nilai lama/baru, waktu, dan IP address
- Snapshot menyimpan relasi tenant dan aktor perubahan dengan FK integrity constraints (`company_uuid` → `companies.uuid`, `user_uuid` → `users.uuid`).

Snapshot berguna untuk audit trail governance: menunjukkan state lengkap config pada waktu perubahan terakhir. Audit log memungkinkan traceability "siapa mengubah apa kapan".

**Internal Schema Note** (May 2026): `payroll_settings_snapshots` table migrated from numeric IDs to UUID foreign keys for multi-tenant isolation. API contract unchanged; snapshot storage now uses `company_uuid` and `user_uuid` columns with proper FK constraints. Migration is backward-compatible; endpoint behavior identical.

**400** `TENANT_REQUIRED` jika active company context tidak tersedia.

### `GET /payroll/settings/history`

Mengambil **history/audit trail** dari semua perubahan konfigurasi payroll tenant aktif (settings governance).

Query opsional:

- `limit` (default 50, max 200) — jumlah log per query
- `offset` (default 0) — pagination offset

**200** `data`:

```json
{
  "logs": [
    {
      "id": 123,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "changedAt": "2026-05-09T15:30:00Z",
      "changedByUserId": 5,
      "changedByUserName": "HR Admin",
      "action": "update",
      "settingKey": "payroll.monthly.payday_day",
      "oldValue": "28",
      "newValue": "25",
      "ipAddress": "192.168.1.100"
    }
  ],
  "total": 15,
  "limit": 50,
  "offset": 0
}
```

**Fields**

- `id` — record ID (immutable)
- `uuid` — UUID untuk referensi eksternal
- `changedAt` — ISO 8601 timestamp kapan perubahan terjadi
- `changedByUserId`, `changedByUserName` — user yang melakukan perubahan (atau null jika user sudah dihapus)
- `action` — tipe action (`update` default, dapat diperluas ke `restore` di masa depan)
- `settingKey` — key setting yang berubah (e.g. `payroll.monthly.payday_day`)
- `oldValue` — nilai sebelumnya (string, atau null untuk new settings)
- `newValue` — nilai baru (string, atau null untuk deletion)
- `ipAddress` — IP address dari request (opsional, untuk forensics)

**Permissions**

- HCM Admin saja (`settings.manage`) dapat melihat history tenant aktif
- Setiap tenant hanya melihat history sendiri (tenant isolation)

**400** `TENANT_REQUIRED` jika active company context tidak tersedia.

### `GET /payroll/settings

Daftar run payroll untuk keperluan **History Monthly Payroll**.

Query opsional:

- `periodYear` (2000–2100)
- `periodMonth` (1–12)
- `status` (`draft` | `finalized` | `void`)
- `purpose` (`monthly` | `thr` | `pkwt_compensation`)
- `paymentStatus` (`unpaid` | `partial` | `paid`)
- `page`, `perPage`

**200** `data[]`: ringkasan run (`serializeRun`) + `auditTrail[]`.

Ringkasan run `monthly` juga memuat `policySnapshot` bila tersedia.

Field audit yang kini relevan untuk run `void`:
- `voidedAt`
- `voidedByUserId`
- `voidedByUserName`

`auditTrail[]` dapat memuat event `calculated`, `finalized`, `voided`, dan `disbursed`.

**200** `meta.pagination`: `page`, `perPage`, `total`, `totalPages`.

### `GET /payroll-runs/{id}`

**200** `data`: `run` (detail + `period` jika termuat), `lines[]` semua karyawan — urut `userId`, `sortOrder`.

Untuk run `purpose=monthly`, objek `run` kini dapat memuat `policySnapshot` agar halaman admin/history dan audit trail dapat menjelaskan payday/cutoff yang berlaku pada saat draft tersebut dibangun.

Objek `run` juga dapat memuat `lateArrivalBuffer` untuk menampilkan backlog aktivitas post-cutoff yang sengaja tidak masuk perhitungan run periode berjalan.

Untuk run yang sudah di-void, payload `run` juga memuat `voidedAt`, `voidedByUserId`, dan `voidedByUserName`; `auditTrail[]` akan berisi event `voided` dengan waktu dan actor bila metadata tersedia.

Tambahan konteks UI payroll run:
- `specialRecipients.thrUserIds[]` — user dalam periode yang sama yang punya run `purpose=thr` dengan net pay positif.
- `specialRecipients.compensationUserIds[]` — user dalam periode yang sama yang punya run `purpose=pkwt_compensation` dengan net pay positif.

Elemen `lines[]`: `id`, `userId`, `userName`, `salaryComponentId`, `componentCode`, `componentName`, `kind`, `category`, `amount`, `sortOrder`, `paymentStatus`, `paidAt`, `gatewayReference`, `meta`.

### `POST /payroll-runs/{id}/finalize`

Menyetel run menjadi `finalized`, `finalized_at`, `finalized_by_user_id` = user admin pemanggil. Periode induk (`hcm_payroll_periods.status`) di-set ke **`posted`**.

**422**

- `PAYROLL_RUN_NOT_DRAFT` jika run bukan `draft`.
- `PAYROLL_FINALIZED_EXISTS` jika periode sudah punya run `finalized` lain dengan **`purpose` yang sama** (gaji bulanan dan THR boleh sama-sama final dalam satu periode jika `purpose` berbeda).
- `PAYROLL_RUN_EMPTY` jika run tidak memiliki satupun baris (mis. tidak ada karyawan active/probation di draft).
- `PAYROLL_TAX_PROFILE_INCOMPLETE` jika masih ada line PPh21 dengan `missingTaxProfile=true` (source of truth status pajak hanya dari modul profil pajak karyawan).

### `POST /payroll-runs/{id}/void`

Menyetel run `finalized` menjadi `void` selama payroll tersebut **belum pernah dibayar**. Jika sesudah void tidak ada lagi run `finalized` lain di periode yang sama, status periode dikembalikan dari `posted` ke `open`. Saat aksi berhasil, sistem juga menyimpan metadata `voidedAt`, `voidedByUserId`, dan `voidedByUserName` untuk history/audit trail.

Identifier saat ini masih menerima **numeric legacy** pada runtime controller (`{id}` integer), walau kontrak OpenAPI repo tetap memakai path parameter UUID generik.

**422**

- `PAYROLL_RUN_NOT_FINALIZED` jika run belum `finalized`.
- `PAYROLL_RUN_ALREADY_PAID` jika ada line pada run yang sudah berstatus `paid`.

### `POST /payroll-runs/{id}/disburse`

Mencatat payroll bulanan sebagai **sudah dibayar di luar aplikasi** untuk subset **`userIds[]`** yang dicentang pada halaman run. Jika run masih `draft`, endpoint ini akan **otomatis finalize + post period** lebih dulu, lalu menandai karyawan terpilih sebagai **`paid`** secara idempotent.

Flow runtime aktif sekarang bersifat **export-only**:
1. operator membuat evidence lewat export reconciliation,
2. operator mengunduh file XLSX,
3. settlement dana dilakukan di luar aplikasi,
4. endpoint ini dipakai untuk menandai pembayaran manual sebagai selesai.

Endpoint `POST /payroll-runs/{id}/mock-hosted-checkout` dan `POST /payroll-runs/{id}/mock-hosted-checkout/confirm` tetap ada untuk kompatibilitas route, tetapi server saat ini mengembalikan **410** `PAYROLL_GATEWAY_DISABLED_EXPORT_ONLY`.

Karyawan hanya dianggap **eligible** jika total net pay periodenya **`> 0`** (hanya komponen yang memengaruhi net pay). User dengan THP `<= 0` otomatis dikeluarkan dari target penandaan pembayaran.

Untuk run `purpose=monthly`, endpoint juga menegakkan payday policy dari `policySnapshot` run. Jika `disburseBeforePaydayAllowed=false`, request sebelum `resolvedPaydayDate` akan ditolak server dengan policy error eksplisit. Jika snapshot belum ada pada run lama, controller fallback ke setting tenant payroll bulanan saat ini.

Kontrak MVP saat ini adalah **hard-block murni tanpa override inline**. Jika tenant membutuhkan exception operasional, admin harus memastikan policy tenant yang disetujui sudah dipakai oleh snapshot run yang aktif: recalculate untuk run `draft`, atau `void` lalu hitung ulang untuk run `finalized` yang belum `paid`.

Karyawan yang sudah berstatus `paid` dilewati secara otomatis dan dikembalikan di `skippedAlreadyPaidUserIds`. Jika semua karyawan terpilih sudah paid, endpoint tetap mengembalikan **200** dengan `gatewayReference` yang sudah ada (no-op idempotent). Nilai `gatewayReference` kini dipakai sebagai **manual completion reference** untuk kompatibilitas payload. Perlindungan race condition ditangani oleh `lockForUpdate()` pada transaksi DB.

Untuk run `monthly` yang memiliki `lateArrivalBuffer` dan sudah selesai dibayar untuk seluruh user **eligible** (net pay `> 0`), endpoint akan menjalankan auto-migration GAP-OPS-01:
- memastikan periode payroll bulan berikutnya tersedia,
- me-queue metadata migrasi pada source run,
- rebuild draft bulan berikutnya,
- menandai source buffer sebagai `migrated`.

Migrasi ini menyiapkan carryover overtime post-cutoff ke draft periode berikutnya tanpa memasukkannya ke run periode source.

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `userIds` | kondisional | array int `users.id`; wajib jika `applyAll` tidak dikirim/false |
| `applyAll` | kondisional | boolean; kirim `true` untuk menandai seluruh karyawan **eligible** di run |

**200** `data`:
- `run` — ringkasan run dengan `paymentStatus`, `paidEmployeeCount`, `employeeCount`, `paidAt`, `gatewayReference`
- `selectedUserIds[]`
- `ineligibleUserIds[]` — user yang ada di run tapi THP `<= 0`, sehingga tidak diproses pembayaran
- `skippedAlreadyPaidUserIds[]`
- `gatewayReference`
- `completionMode = manual_external`
- `payment { status, employeeCount, paidEmployeeCount, paidUserIds, paidAt }`
- `lateArrivalMigration` (nullable) — ringkasan hasil auto-migration post-cutoff jika dieksekusi (`targetPeriodId`, `targetPeriodYear`, `targetPeriodMonth`, `targetRunId`)

**422**
- `PAYROLL_DISBURSE_NO_EMPLOYEES` jika tidak ada karyawan eligible yang bisa diproses
- `PAYROLL_DISBURSE_BEFORE_PAYDAY_FORBIDDEN` jika run `monthly` dibayar sebelum `resolvedPaydayDate` saat policy tenant melarang early disburse
- `PAYROLL_RUN_EMPTY` jika draft belum memiliki baris
- `PAYROLL_FINALIZED_EXISTS` jika periode sudah punya run finalized lain dengan purpose yang sama
- `PAYROLL_TAX_PROFILE_INCOMPLETE` jika masih ada line PPh21 dengan `missingTaxProfile=true`
- `PAYROLL_GATEWAY_DISABLED_EXPORT_ONLY` jika client lama masih mencoba flow hosted checkout payroll

### `POST /payroll-runs/{id}/mock-hosted-checkout`

Endpoint kompatibilitas yang sekarang **dinonaktifkan** untuk flow payroll export-only.

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `userIds` | kondisional | array int `users.id`; wajib jika `applyAll` tidak dikirim/false |
| `applyAll` | kondisional | boolean; kirim `true` untuk seluruh user eligible |

**410** `PAYROLL_GATEWAY_DISABLED_EXPORT_ONLY`

### `POST /payroll-runs/{id}/mock-hosted-checkout/confirm`

Endpoint kompatibilitas yang sekarang **dinonaktifkan** untuk flow payroll export-only.

**410** `PAYROLL_GATEWAY_DISABLED_EXPORT_ONLY`

### Export reconciliation payroll (`POST /v1/reconciliation/exports`)

Untuk request dengan `featureKey/actionKey` berikut:

- `payroll_run/disburse`
- `thr_batch/disburse`
- `thr_batch/post_payroll`
- `pkwt_compensation/post_payroll`

server akan meng-generate file reconciliation berbentuk **summary pembayaran per karyawan** dengan schema seragam berikut:

- `payroll_type`
- `reference_period`
- `reference_id`
- `employee_id`
- `employee_name`
- `bank_name`
- `account_number`
- `account_holder_name`
- `bank_branch`
- `gross_total`
- `overtime_total`
- `deductions_total`
- `transfer_amount`
- `bank_data_status`
- `dataset_checksum`

`payroll_type` bernilai `monthly`, `thr`, atau `pkwt_compensation`. `reference_id` memakai prefix runtime (`run:{id}` untuk payroll run / PKWT payroll run, `thr_batch:{id}` untuk batch THR). Nilai `gross_total` / `overtime_total` / `deductions_total` / `transfer_amount` hanya menghitung line yang berdampak ke take-home pay (`affects_net_pay=true` pada master `hcm_salary_components`; fallback `true` jika line belum tertaut komponen). Pada payroll monthly, `overtime_total` memisahkan subtotal komponen `upah_lembur`; pada THR dan PKWT compensation nilainya saat ini `0`. Format ini sengaja diseragamkan agar file yang sama tetap sah sebagai evidence export sekaligus cukup operasional untuk admin mengeksekusi pembayaran manual tenant tanpa kolom teknis kosong yang membingungkan.

### `POST /payroll/thr-calculate`

Estimasi **THR bruto** untuk QA / payroll (bukan posting slip, tanpa pajak).

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `joinDate` | ya | `date` — tanggal mulai bekerja |
| `cutoffDate` | ya | `date` — acuan perusahaan (mis. H-1 Lebaran), ≥ `joinDate` |
| `baseMonthlySalary` | ya | numeric ≥ 0 — gaji pokok bulanan |
| `fixedMonthlyAllowance` | tidak | numeric ≥ 0, default 0 — tunjangan **tetap** bulanan (masuk upah acuan) |

**Logika ringkas:** upah acuan = `baseMonthlySalary` + `fixedMonthlyAllowance`. `monthsOfService` = bulan penuh antara kedua tanggal (jika tanggal cut-off belum mencapai tanggal yang sama dengan tanggal bergabung, bulan berjalan tidak dihitung penuh). Jika `monthsOfService` &lt; 1 → THR 0 (`status` `not_eligible`). Jika ≥ 12 → THR = upah acuan (`full`). Jika 1–11 → THR = `(monthsOfService/12) ×` upah acuan (`pro_rata`). Jika `cutoffDate` &lt; `joinDate` → `invalid_dates`, THR 0.

**200** `data`: `eligible`, `status`, `monthsOfService`, `multiplier`, `referenceMonthlyWage`, `thrGross`, `joinDate`, `cutoffDate`, `notes[]`, `regulationReference`.

### `GET /payroll/thr-settings`

**200** `data.settings[]`: `calendarYear`, `eidDate`, `paymentDate`, `calculationCutoffDate`, `notes`, `updatedAt` — maks. 25 tahun terbaru.

### `PUT /payroll/thr-settings/{calendarYear}`

Path: `calendarYear` integer 2000–2100.

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `eidDate` | ya | `date` — tanggal hari raya (referensi kalender kerja) |
| `paymentDate` | tidak | `date` nullable — tanggal transfer **THR** (biasanya tidak sama dengan tanggal payroll bulanan; sering H-7–10) |
| `calculationCutoffDate` | tidak | `date` nullable — cut-off masa kerja untuk pro rata (mis. H-1) |
| `notes` | tidak | string max 2000 |

**200** `data`: satu baris pengaturan (sama shape seperti elemen `settings[]`).

### `GET /payroll/thr-batch?calendarYear=`

Query wajib: `calendarYear` (2000–2100).

**200** `data`:

- `batch`: objek ringkas batch **draft** terbaru untuk tahun itu, atau batch **`assigned`** terakhir, atau `null`.
- `lines[]`: baris karyawan untuk batch tersebut (kosong jika `batch` null).

Shape `batch`: `id`, `calendarYear`, `cutoffDate`, `grandTotalEligible`, `eligibleLineCount`, `totalLineCount`, `status` (`draft` \| `assigned`), `assignedAt`, `payrollPeriodId`, `payrollRunId`, serta jika draft: **`canPostToPayroll`** (boolean — semua baris eligible THR&gt;0 sudah `paid` dan siap `post-payroll`).

Shape elemen `lines[]`: field perhitungan seperti sebelumnya, ditambah **`bankName`** / **`bankAccountNo`** (dari profil karyawan saat respons dibentuk; `null` jika kosong), **`paymentStatus`** (`unpaid` \| `pending` \| `paid` \| `failed`), `paymentFailureReason`, `paymentGatewayRef`, `paidAt`, **`hasSlip`**, `slipGeneratedAt`, `slipNotifySentAt`.

### `POST /payroll/thr-batch/generate`

Membangun ulang **satu** batch **draft** untuk `calendarYear`: menghapus draft lama tahun yang sama, menghitung satu baris per karyawan **active/probation** yang punya profil. Upah acuan dan pro rata mengikuti **`POST /payroll/thr-calculate`** (cut-off dari **`calculationCutoffDate`** pengaturan tahun tersebut).

**422** `THR_SETUP_CUTOFF_REQUIRED` jika pengaturan tahun tidak ada atau **`calculationCutoffDate`** kosong.

**422** `THR_YEAR_ALREADY_ASSIGNED` jika untuk tahun itu sudah ada batch berstatus **`assigned`** (tidak bisa generate ulang lewat API ini).

**200** `data`: `batch`, `lines[]` (sama seperti `GET /payroll/thr-batch`).

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `calendarYear` | ya | integer 2000–2100 |

### `POST /payroll/thr-batch/disburse`

Mencatat **pembayaran manual eksternal** untuk **`userIds`** tercentang. Batch **`draft`**. Baris sudah **`paid`** dilewati. Baris yang dipilih harus **eligible** dan **`thrGross` &gt; 0**.

Pada flow runtime aktif, evidence export reconciliation untuk THR menggunakan file **XLSX** sebelum operator melanjutkan proses tandai bayar atau post payroll.

**200** `data`: `disbursementId` (nullable jika semua terpilih sudah paid), `skippedAlreadyPaidUserIds`, `lines[]` terbaru, `batch` (dengan `canPostToPayroll`).

**422**: `THR_DISBURSE_NO_EMPLOYEES`, `THR_DISBURSE_LINE_NOT_PAYABLE`, `THR_BATCH_NOT_DRAFT`, `THR_ASSIGN_USER_NOT_IN_BATCH`, dll.

### `POST /payroll/thr-batch/post-payroll`

Membuat run **`purpose` `thr`** **final** untuk **semua** baris batch yang eligible dan THR&gt;0 — dengan syarat **semuanya** sudah **`paymentStatus` = `paid`**. Periode dari **`paymentDate`** pengaturan tahun. Batch → **`assigned`**.

**Body JSON**: `batchId` (wajib).

**422**: `THR_POST_UNPAID_PAYABLE_LINES`, `THR_PAYMENT_DATE_REQUIRED`, `THR_SALARY_COMPONENT_MISSING`, `THR_PAYROLL_FINALIZED_EXISTS`, `THR_BATCH_NOT_DRAFT`, `THR_ASSIGN_NO_POSITIVE_LINES`.

**200** `data`: `payrollPeriodId`, `periodYear`, `periodMonth`, `payrollRunId`.

### `GET /payroll/thr-batch/lines/{line}/slip`

**200**: body **PDF** (`application/pdf`) — preview slip THR untuk satu `hcm_thr_batch_lines.id`.

**403**: bukan admin dan bukan pemilik baris (`user_id`).

**404**: `THR_SLIP_NOT_FOUND` / `THR_SLIP_FILE_MISSING`.

### `GET /payroll/my-thr-slip`

Query opsional: `calendarYear` (2000–2100) — memilih slip tahun tersebut; tanpa query, dipilih slip terbaru (tahun kalender tertinggi).

**200** `data`:

- `line` / `batch`: sama bentuknya seperti baris di `thr-batch` (`serializeBatchLine` + `serializeBatch`), atau **`null`** jika belum ada slip PDF untuk user. Pada `line` disertakan **`thrSlipPublicNo`** (kode unik tersimpan di DB, tanpa `#`, mis. `THR-2026-01J9K2…` / migrasi lama `THR-2026-42`), **`slipNumber`** = `#` + `thrSlipPublicNo` untuk tampilan, **`calendarYear`** baris, serta **`bankName`** / **`bankAccountNo`** dari profil (jika ada).
- `history`: `{ lineId, calendarYear }[]` untuk semua slip yang pernah dihasilkan (urut tahun menurun di UI).

Tidak ada halaman web khusus slip THR; klien memakai respons JSON ini (mis. aplikasi mobile) atau mengunduh PDF lewat **`GET /payroll/thr-batch/lines/{line}/slip`** untuk baris milik user.

### `GET /payroll-item-assignments?userId=&kind=&isActive=`

List assignment payroll item per karyawan.

Query:

- `userId` (wajib, `users.uuid`)
- `kind` (opsional: `addition|deduction`)
- `isActive` (opsional boolean)

**200** `data.assignments[]`:

- `id`, `userId`, `payrollItemId`, `amount`, `isActive`
- `effectiveStartDate`, `effectiveEndDate`, `notes`
- `payrollItem { id, code, name, kind, category, linkedToMaster, salaryComponentId, masterDefaultPercent, masterPercentBasis }`

### `POST /payroll-item-assignments`

Membuat assignment payroll item untuk satu karyawan.

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `userId` | ya | UUID `users.uuid` |
| `payrollItemId` | ya | integer `hcm_payroll_items.id` (harus aktif dan berada pada tenant yang sama) |
| `amount` | ya | numeric `0.01`–`999999999999.99` |
| `isActive` | tidak | boolean, default `true` |
| `effectiveStartDate` | tidak | `date` |
| `effectiveEndDate` | tidak | `date`, `>= effectiveStartDate` |
| `notes` | tidak | string max 5000 |

**201** `data`: objek assignment.

**422**:

- `PAYROLL_ITEM_NOT_FOUND` jika payroll item tidak aktif / di luar tenant aktif.
- `PAYROLL_ITEM_ASSIGNMENT_EXISTS` jika kombinasi karyawan + payroll item sudah pernah di-assign.

### `PUT /payroll-item-assignments/{id}`

Update parsial assignment (`amount`, `isActive`, periode efektif, `notes`).

**Body JSON**: kirim field yang diubah saja.

**200** `data`: objek assignment terbaru.

### `DELETE /payroll-item-assignments/{id}`

Hapus assignment payroll item karyawan.

**200** `data`: `{ id }`.

### Dampak ke draft payroll bulanan

`POST /payroll-periods/{id}/calculate-draft` akan menyertakan assignment dengan syarat:

- assignment `is_active = true`
- payroll item terkait `is_active = true`
- tanggal efektif cocok dengan akhir bulan periode (`effective_start_date <= asOf` dan `effective_end_date >= asOf`, jika terisi)

Setiap assignment menghasilkan baris tambahan payroll line (`source = employee_payroll_item_assignments`) dengan `component_code` dan `kind/category` dari payroll item.

### `POST /payroll/thr-batch/send-slip`

Mencatat waktu kirim notifikasi slip (**`slip_notify_sent_at`**) untuk baris yang sudah punya PDF.

**Body JSON**: `batchId`, `lineIds[]` (semua harus milik batch dan punya `slip_storage_path`).

**422**: `THR_SEND_SLIP_NO_LINES`, `THR_SEND_SLIP_INVALID_LINES`, `THR_SEND_SLIP_NO_PDF`.

### Reset QA THR (artisan, setara “clear” pembayaran/posting)

- `php artisan hcm:reset-thr-test-data` — default: hapus run payroll `purpose=thr`, disbursement, reset kolom bayar/slip di `hcm_thr_batch_lines`, kembalikan batch ke `draft` (baris & `thr_slip_public_no` tetap).
- `--year=YYYY` — batasi ke satu tahun kalender.
- `--full` — hapus batch + baris (+ opsi `--keep-settings` untuk pengaturan tahunan).
- `--fresh-slip-numbers` — **tanpa** `--full`: generate ulang **`thr_slip_public_no`** per baris (ULID baru); dipakai jika QA butuh identitas slip baru tanpa generate batch dari nol.

Skrip SQL MySQL manual (disarankan tambahkan `WHERE` pada batch/baris jika tidak ingin menyentuh semua tahun): `docs/features/payroll-runs/THR_RESET_MANUAL.sql`.

### `GET /payroll/my-slip?periodYear=&periodMonth=`

Query wajib: `periodYear`, `periodMonth` (sama aturan seperti POST periode).

**200** `data`:

- `period`, `run`, `runs[]`, `employee`
- `earnings[]` dan `deductions[]` hasil agregasi dari run final **`monthly`** dan, bila ada, **`thr`** pada bulan yang sama untuk user pemanggil
- `overtime`: `amountTotal`, `lineCount`
- `totals`: `earningsTotal`, `deductionsTotal`, `overtimeTotal`, `netPay`
- `slipNumber` format `SLIP-YYYY-MM-USERID`
- `downloadUrl` ke endpoint PDF jika slip tersedia; `null` bila belum ada run final

Jika periode tidak ada atau belum ada run `finalized`, endpoint tetap **200** dengan `run = null` dan total `0`.

### `GET /payroll/my-slip-latest-period`

Mengembalikan periode payroll terbaru yang memiliki run `finalized` dan memuat baris slip untuk user pemanggil.

**200** `data`:

- `period`: `{ id, periodYear, periodMonth, status }` atau `null` jika user belum memiliki slip final sama sekali.
- `run`: ringkasan run (`id`, `purpose`, `status`, `finalizedAt`) yang menjadi sumber periode terbaru, atau `null`.

Endpoint ini dipakai sebagai fallback UX pada initial load halaman `/payslip` agar user otomatis diarahkan ke periode slip terakhir yang tersedia.

### `GET /payroll/monthly-report?periodYear=&periodMonth=`

**HCM Admin only.** Mengembalikan laporan payroll bulanan detail yang menggabungkan run final `monthly`, `thr`, dan `pkwt_compensation` per kombinasi periode-karyawan.

Query opsional:

- `periodYear` — integer 2000–2100
- `periodMonth` — integer 1–12

**200** `data`:

- `rows[]`:
  - `rowKey`
  - `periodYear`, `periodMonth`
  - `userId`, `employeeName`, `email`
  - `designation`, `team`
  - `bankName`, `accountNumber`, `bankBranch`
  - `paymentStatus` — agregasi `paid|partial|unpaid` lintas purpose pada row yang sama
  - `breakdown.monthly|thr|pkwt_compensation` (jika purpose tersebut ada): `runId`, `paymentStatus`, `earningsTotal`, `deductionsTotal`, `overtime.amountTotal`, `netPay`, `earnings[]`, `deductions[]`
  - `overtime.amountTotal`, `overtime.lineCount`
  - `totals.earningsTotal`, `totals.deductionsTotal`, `totals.overtimeTotal`, `totals.netPay`
- `summary`:
  - `totalRows`
  - `totalEmployees`
  - `totalPeriods`
  - `totalNetPay`
  - `totalOvertimePay`
  - `totalsByPurpose.monthly`
  - `totalsByPurpose.thr`
  - `totalsByPurpose.pkwt_compensation`

Endpoint ini dipakai oleh halaman admin `/monthly-report` untuk satu layar gabungan payroll reguler, THR, dan kompensasi PKWT.

### `GET /payroll/monthly-report/export?periodYear=&periodMonth=&format=`

**HCM Admin only.** Mengunduh laporan Monthly Report dalam format tabular.

Query:

- `periodYear` — opsional
- `periodMonth` — opsional
- `format` — opsional, `csv|xlsx`, default `xlsx`

Header export:

- `period`
- `employee_id`
- `employee_name`
- `email`
- `designation`
- `team`
- `bank_name`
- `account_number`
- `bank_branch`
- `payment_status`
- `monthly_run_id`, `monthly_gross`, `monthly_overtime`, `monthly_deductions`, `monthly_net`
- `thr_run_id`, `thr_gross`, `thr_overtime`, `thr_deductions`, `thr_net`
- `pkwt_run_id`, `pkwt_gross`, `pkwt_overtime`, `pkwt_deductions`, `pkwt_net`
- `total_gross`, `total_overtime`, `total_deductions`, `total_net`

Setiap baris tetap satu employee per periode, sehingga admin dapat memakai file ini sebagai laporan detail lintas run tanpa kehilangan breakdown per purpose.

### `GET /payroll/my-slip-pdf?periodYear=&periodMonth=`

Menghasilkan **PDF** slip gaji bulanan milik sendiri untuk periode yang diminta.

**200**: body **PDF** (`application/pdf`).

**404**: `PAYROLL_SLIP_NOT_FOUND` jika belum ada slip final untuk periode tersebut.

### `GET /payroll/pkwt-compensations?periodYear=&periodMonth=`

**HCM Admin only.** Mengembalikan daftar karyawan **PKWT** yang `contract_end_date` jatuh pada bulan terpilih.

**200** `data`:

- `preview.period`: `{ periodYear, periodMonth }`
- `preview.summary`: `totalEmployees`, `eligibleEmployees`, `grandTotal`
- `preview.lines[]`: `userId`, `employeeNo`, `fullName`, `designation`, `contractStartDate`, `contractEndDate`, `baseSalary`, `fixedAllowance`, `referenceMonthlyWage`, `monthsOfService`, `multiplier`, `compensationAmount`
- `fixedAllowance` pada preview PKWT dipertahankan untuk backward compatibility payload, tetapi runtime saat ini selalu `0` karena tunjangan tetap operasional hanya dikelola lewat allowance governance.
- `run`: `null` atau ringkasan payroll run PKWT saat ini: `id`, `purpose`, `status`, `finalizedAt`, `period`, `payment { status, employeeCount, paidEmployeeCount, paidUserIds[], paidAt, gatewayReference }` (`gatewayReference` dipakai sebagai manual completion reference untuk kompatibilitas)

Endpoint ini dipakai untuk preview sekaligus membaca status payroll kompensasi PKWT aktif pada periode tersebut.

### `POST /payroll/pkwt-compensations/post-payroll`

**HCM Admin only.** Membuat atau membangun ulang draft payroll **standalone** dengan `purpose = pkwt_compensation` untuk periode terpilih.

Pada flow runtime aktif, evidence export reconciliation untuk PKWT compensation menggunakan file **CSV/XLSX** dengan schema payment-ready yang sama seperti monthly payroll dan THR sebelum draft diposting ke payroll.

Flow setelah endpoint ini:

- draft dibuat/rebuild dari preview eligible bulan itu,
- pembayaran dicatat manual lewat **`POST /payroll-runs/{id}/disburse`** setelah settlement eksternal,
- hasil final ikut muncul di `my-slip-lines`, admin slips, dan agregasi payslip bulanan pada periode yang sama.

**Body JSON**:

- `periodYear` (wajib)
- `periodMonth` (wajib)

**200** `data`:

- `period`: `{ id, periodYear, periodMonth, status }`
- `run`: ringkasan run PKWT saat ini: `id`, `purpose`, `status`, `finalizedAt`, `period`, `payment`
- `preview`: snapshot preview PKWT bulan tersebut

**422**:

- `PKWT_COMPENSATION_EMPTY` jika tidak ada karyawan eligible pada periode itu
- `PKWT_COMPENSATION_COMPONENT_MISSING` jika komponen master `kompensasi_pkwt` belum tersedia/aktif
- `PKWT_COMPENSATION_FINALIZED_EXISTS` jika periode yang sama sudah punya run PKWT `finalized`

### `POST /payroll/pkwt-calculate`

**HCM Admin only.** Kalkulator cepat satu kontrak PKWT.

**Body JSON**:

- `contractStartDate` (wajib)
- `contractEndDate` (wajib)
- `baseMonthlySalary` (wajib)
- `fixedMonthlyAllowance` (opsional)

**200** `data`: `eligible`, `status`, `monthsOfService`, `multiplier`, `referenceMonthlyWage`, `compensationAmount`, `regulationReference`.

### `GET /payroll/my-slip-lines?periodYear=&periodMonth=`

Query wajib: `periodYear`, `periodMonth` (sama aturan seperti POST periode).

**200** `data`:

- Jika periode tidak ada: `period` `null`, `run` `null`, `lines` `[]`.
- Jika periode ada tapi belum ada run `finalized`: `period` terisi, `run` `null`, `lines` `[]`.
- Jika ada run `finalized`: `period`, `run` (ringkas — prioritas tampilan **`monthly`**, lalu **`thr`**, lalu **`pkwt_compensation`**), `lines[]` gabungan baris dari run final **`monthly`**, **`thr`**, dan **`pkwt_compensation`** untuk bulan tersebut, hanya untuk user pemanggil. Respons dapat menyertakan `runs[]` untuk detail tiap run.

## Kode error domain

| Kode | HTTP | Konteks |
|------|------|---------|
| `PAYROLL_PERIOD_EXISTS` | 422 | Duplikat tahun/bulan |
| `PAYROLL_PERIOD_FINALIZED` | 422 | `calculate-draft` saat sudah ada final |
| `PAYROLL_RUN_NOT_DRAFT` | 422 | `finalize` pada run non-draft |
| `PAYROLL_FINALIZED_EXISTS` | 422 | Dua final **`purpose` sama** dalam satu periode |
| `PAYROLL_RUN_EMPTY` | 422 | Finalize run tanpa baris |
| `PAYROLL_TAX_PROFILE_INCOMPLETE` | 422 | Finalize/disburse ditolak karena ada karyawan fallback PPh21 (`missingTaxProfile=true`) |
| `PKWT_COMPENSATION_EMPTY` | 422 | Tidak ada kompensasi PKWT eligible untuk diposting |
| `PKWT_COMPENSATION_COMPONENT_MISSING` | 422 | Komponen master `kompensasi_pkwt` belum tersedia/aktif |
| `PKWT_COMPENSATION_FINALIZED_EXISTS` | 422 | Periode sudah punya run final `purpose=pkwt_compensation` |
| `THR_SETUP_CUTOFF_REQUIRED` | 422 | Generate batch tanpa cut-off di pengaturan tahun |
| `THR_YEAR_ALREADY_ASSIGNED` | 422 | Tahun sudah pernah assign batch |
| `THR_BATCH_NOT_DRAFT` | 422 | Disburse/post pada batch non-draft |
| `THR_PAYMENT_DATE_REQUIRED` | 422 | Post tanpa `paymentDate` di pengaturan |
| `THR_SALARY_COMPONENT_MISSING` | 422 | Komponen `thr` tidak aktif |
| `THR_ASSIGN_USER_NOT_IN_BATCH` | 422 | `userIds` disburse tidak semua ada di batch |
| `THR_ASSIGN_NO_POSITIVE_LINES` | 422 | Tidak ada THR positif untuk diposting |
| `THR_PAYROLL_FINALIZED_EXISTS` | 422 | Run THR final sudah ada untuk periode bayar |
| `THR_DISBURSE_NO_EMPLOYEES` | 422 | Tandai bayar tanpa `userIds` |
| `THR_DISBURSE_LINE_NOT_PAYABLE` | 422 | Baris tidak eligible / THR nihil |
| `THR_POST_UNPAID_PAYABLE_LINES` | 422 | Masih ada eligible THR&gt;0 yang belum `paid` |
| `THR_SEND_SLIP_*` | 422 | Bulk kirim slip — validasi batch/line/PDF |
| `THR_CUTOFF_DATE_INVALID` | 422 | Tanggal cut-off lebih awal dari tersimpan |
| `PAYROLL_RUN_FOREIGN_USERS` | 422 | Finalize ditolak: ada user_id tidak terdaftar di perusahaan aktif |
| `EXPORT_RECON_REQUIRED` | 422 | Aksi ditolak: export reconciliation evidence wajib diunduh dulu |
| `EXPORT_RECON_EXPIRED` | 422 | Evidence sudah kedaluwarsa, export ulang |
| `EXPORT_RECON_SCOPE_MISMATCH` | 422 | Scope evidence tidak cocok dengan konteks aksi |
| `EXPORT_RECON_STALE_DATA` | 422 | Data sudah berubah sejak export, export ulang |

## Tes

`HcmPayrollApiTest` — admin vs karyawan, draft → finalize → `my-slip`, `my-slip-pdf`, dan `my-slip-lines` (termasuk overtime + deductions).

`HcmPayrollPkwtApiTest` — RBAC admin-only + preview PKWT + post ke payroll standalone + disburse + muncul di `my-slip-lines`.

`HcmPayrollThrApiTest` — RBAC, validasi, pro rata / penuh / tidak eligible, batch generate → disburse → slip PDF → post-payroll + `my-slip-lines` dengan `purpose` **thr**.
