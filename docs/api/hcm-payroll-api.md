# HCM — Payroll period & run (Phase 1)

Prefix: `/v1/hcm` · middleware **`api.token`** · envelope `{ success, data?, error? }`.

Tenant context:
- Endpoint payroll membaca `activeCompany` dari middleware tenant context.
- Header opsional: `X-Company-Id` atau `X-Company-Code`.
- Jika company tidak dimiliki user maka request ditolak `403` dengan `error.code = TENANT_FORBIDDEN`.
- Scope ini berlaku untuk endpoint payroll period/run, payroll items, THR settings/batch, dan PKWT compensation preview/posting.
- Untuk payroll run, alur finalize/disburse/reset juga mempertahankan tenant scope pada fetch period/run lanjutan di dalam transaksi.

## Ringkasan

Fondasi **actual payroll** per kalender bulan: **periode** (`hcm_payroll_periods`), **run** (`hcm_payroll_runs` — status `draft` / `finalized`, kolom **`purpose`**: `monthly` untuk gaji rutin, `thr` untuk THR massal, `pkwt_compensation` untuk kompensasi PKWT off-cycle), **baris slip** (`hcm_payroll_lines`).

Phase 1.1 (April 2026) — **hitung draft** dari profil karyawan + komponen periode berjalan:

- **`base_salary`** → selalu satu baris addition **upah pokok** per karyawan eligible (nilai ≥ 0), agar karyawan **active/probation** tidak “hilang” dari run bila gaji pokok 0.
- **`fixed_allowance`** → baris addition tambahan hanya jika nominal **> 0** (komponen master kategori `fixed_allowance` pertama yang aktif).
- **Lembur approved** dalam periode ikut diakumulasi sebagai addition payroll bulanan.
- **Potongan karyawan** dasar (mis. BPJS & estimasi **PPh21 bulanan berbasis annualized**) ikut dibentuk sebagai deduction lines.
- Slip bulanan mandiri sudah tersedia sebagai **JSON summary** (`GET /payroll/my-slip`) dan **PDF download** (`GET /payroll/my-slip-pdf`) setelah run periode berstatus **`finalized`**.
- Preview **kompensasi PKWT** bulanan untuk admin tersedia via **`GET /payroll/pkwt-compensations`**, dapat diposting menjadi draft payroll via **`POST /payroll/pkwt-compensations/post-payroll`**, lalu dibayar lewat endpoint generic **`POST /payroll-runs/{id}/disburse`** seperti flow off-cycle lain.
- Nominal negatif dari DB diperlakukan sebagai **0**.
- Halaman payroll bulanan kini **auto-load periode aktif**, mendukung **select-all / subset** pembayaran gateway, dan draft periodik direfresh scheduler **00:00 WIB** selama periodenya masih `open`.

**Belum** di-cover penuh: posting GL, void/reopen run, tabel audit terpisah, dan aturan pajak/payroll lanjutan.

## RBAC

| Endpoint | Siapa |
|----------|--------|
| `GET /payroll-periods/active` | **HCM Admin** saja (`403` `AUTH_FORBIDDEN`) |
| `GET/POST /payroll-periods`, `GET /payroll-periods/{id}`, `POST .../calculate-draft` | **HCM Admin** saja (`403` `AUTH_FORBIDDEN`) |
| `GET /payroll-runs/history`, `GET /payroll-runs/{id}`, `POST /payroll-runs/{id}/finalize`, `POST /payroll-runs/{id}/disburse` | **HCM Admin** saja |
| `GET /payroll/my-slip` | **Semua user terautentikasi** — ringkasan slip gaji bulan berjalan milik sendiri (`earnings`, `deductions`, `totals`, `downloadUrl`) jika ada run **`finalized`** |
| `GET /payroll/my-slip-pdf` | **Semua user terautentikasi** — unduh PDF slip gaji bulan berjalan milik sendiri; `404` bila belum ada run final |
| `GET /payroll/my-slip-lines` | **Semua user terautentikasi** — hanya baris **`user_id` = pemanggil**; data slip hanya jika ada run **`finalized`** untuk periode tersebut; baris **digabung** dari run **`purpose` `monthly`**, **`thr`**, dan **`pkwt_compensation`** (jika ada pada bulan yang sama) |
| `GET /payroll/my-thr-slip` | **Semua user terautentikasi** — JSON slip THR milik sendiri (baris batch yang sudah punya PDF); `data.history` untuk pemilih tahun |
| `GET /payroll/pkwt-compensations`, `POST /payroll/pkwt-compensations/post-payroll`, `POST /payroll/pkwt-calculate` | **HCM Admin** saja — preview daftar karyawan PKWT jatuh tempo, generate/rebuild draft payroll kompensasi PKWT, dan kalkulator estimasi cepat |
| `GET /payroll/thr-batch/lines/{line}/slip` | **HCM admin** semua baris; **karyawan** hanya jika **`line` milik pemanggil** (`403` jika bukan) |
| `POST /payroll/thr-calculate` | **HCM Admin** saja — estimasi THR bruto (Permenaker 6/2016, pro rata); **bukan** slip final dan **tanpa** PPh 21 TER |
| `GET /payroll/thr-settings`, `PUT /payroll/thr-settings/{calendarYear}` | **HCM Admin** saja — pengaturan per tahun: tanggal Lebaran (referensi), tanggal pembayaran THR, cut-off perhitungan pro rata, catatan |
| `GET /payroll/thr-batch`, `POST /payroll/thr-batch/generate`, `POST /payroll/thr-batch/disburse`, `POST /payroll/thr-batch/post-payroll`, `POST /payroll/thr-batch/send-slip` | **HCM Admin** saja — batch THR: gateway disburse → slip PDF → posting run `purpose=thr` |

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

**200** `data`: periode + `latestRun` (ringkas: `id`, `payrollPeriodId`, `status`, `calculatedAt`, `finalizedAt`, `finalizedByUserId`) atau `null`.

### `POST /payroll-periods/{id}/calculate-draft`

Membangun ulang **satu** run `draft` untuk periode:

- Menghapus semua run `draft` lama beserta barisnya di periode ini.
- Membuat run baru `draft`, mengisi `calculated_at`.
- Menyisipkan baris per karyawan `active` / `probation` yang punya profil (lihat ringkasan engine di atas).

**422** `PAYROLL_PERIOD_FINALIZED` jika periode sudah memiliki run `finalized` (recalc tidak diizinkan sampai ada void / periode baru — backlog).

**200** `data`: `run` (ringkas), `lineCount`, `employeeCount`, `anomalies`.

`anomalies` saat ini memuat:

- `missingTaxProfileUserCount`
- `missingTaxProfileUserIds[]`

Digunakan untuk audit cepat user yang perhitungan PPh21-nya masih fallback tax status (`TK0`) karena profil pajak belum lengkap.

### `GET /payroll-periods/active`

Mengembalikan periode payroll aktif (`status=open`) terbaru. Jika belum ada periode open, endpoint akan membuat periode bulan berjalan otomatis.

**200** `data`: shape periode + `latestRun` (jika ada).

### `GET /payroll-runs/history`

Daftar run payroll untuk keperluan **History Monthly Payroll**.

Query opsional:

- `periodYear` (2000–2100)
- `periodMonth` (1–12)
- `status` (`draft` | `finalized` | `void`)
- `purpose` (`monthly` | `thr` | `pkwt_compensation`)
- `paymentStatus` (`unpaid` | `partial` | `paid`)
- `page`, `perPage`

**200** `data[]`: ringkasan run (`serializeRun`) + `auditTrail[]`.

**200** `meta.pagination`: `page`, `perPage`, `total`, `totalPages`.

### `GET /payroll-runs/{id}`

**200** `data`: `run` (detail + `period` jika termuat), `lines[]` semua karyawan — urut `userId`, `sortOrder`.

Elemen `lines[]`: `id`, `userId`, `userName`, `salaryComponentId`, `componentCode`, `componentName`, `kind`, `category`, `amount`, `sortOrder`, `paymentStatus`, `paidAt`, `gatewayReference`, `meta`.

### `POST /payroll-runs/{id}/finalize`

Menyetel run menjadi `finalized`, `finalized_at`, `finalized_by_user_id` = user admin pemanggil. Periode induk (`hcm_payroll_periods.status`) di-set ke **`posted`**.

**422**

- `PAYROLL_RUN_NOT_DRAFT` jika run bukan `draft`.
- `PAYROLL_FINALIZED_EXISTS` jika periode sudah punya run `finalized` lain dengan **`purpose` yang sama** (gaji bulanan dan THR boleh sama-sama final dalam satu periode jika `purpose` berbeda).
- `PAYROLL_RUN_EMPTY` jika run tidak memiliki satupun baris (mis. tidak ada karyawan active/probation di draft).

### `POST /payroll-runs/{id}/disburse`

Eksekusi gateway pembayaran payroll bulanan untuk subset **`userIds[]`** yang dicentang pada halaman run. Jika run masih `draft`, endpoint ini akan **otomatis finalize + post period** lebih dulu, lalu menandai karyawan terpilih sebagai **`paid`** secara idempotent.

Karyawan yang sudah berstatus `paid` dilewati secara otomatis dan dikembalikan di `skippedAlreadyPaidUserIds`. Jika semua karyawan terpilih sudah paid, endpoint tetap mengembalikan **200** dengan `gatewayReference` yang sudah ada (no-op idempotent). Perlindungan race condition ditangani oleh `lockForUpdate()` pada transaksi DB.

**Body JSON**

| Field | Wajib | Aturan |
|-------|--------|--------|
| `userIds` | tidak | array int `users.id`; bila kosong → semua karyawan di run |

**200** `data`:
- `run` — ringkasan run dengan `paymentStatus`, `paidEmployeeCount`, `employeeCount`, `paidAt`, `gatewayReference`
- `selectedUserIds[]`
- `skippedAlreadyPaidUserIds[]`
- `gatewayReference`
- `payment { status, employeeCount, paidEmployeeCount, paidUserIds, paidAt }`

**422**
- `PAYROLL_DISBURSE_NO_EMPLOYEES` jika tidak ada karyawan valid yang dipilih
- `PAYROLL_RUN_EMPTY` jika draft belum memiliki baris
- `PAYROLL_FINALIZED_EXISTS` jika periode sudah punya run finalized lain dengan purpose yang sama

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

Mengeksekusi **payment gateway** (stub / integrasi Xendit-Midtrans nanti) untuk **`userIds`** tercentang. Batch **`draft`**. Baris sudah **`paid`** dilewati (tidak dipanggil ulang ke gateway). Baris yang dipilih harus **eligible** dan **`thrGross` &gt; 0**.

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
- `totals`: `earningsTotal`, `deductionsTotal`, `netPay`
- `slipNumber` format `SLIP-YYYY-MM-USERID`
- `downloadUrl` ke endpoint PDF jika slip tersedia; `null` bila belum ada run final

Jika periode tidak ada atau belum ada run `finalized`, endpoint tetap **200** dengan `run = null` dan total `0`.

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
- `run`: `null` atau ringkasan payroll run PKWT saat ini: `id`, `purpose`, `status`, `finalizedAt`, `period`, `payment { status, employeeCount, paidEmployeeCount, paidUserIds[], paidAt, gatewayReference }`

Endpoint ini dipakai untuk preview sekaligus membaca status payroll kompensasi PKWT aktif pada periode tersebut.

### `POST /payroll/pkwt-compensations/post-payroll`

**HCM Admin only.** Membuat atau membangun ulang draft payroll **standalone** dengan `purpose = pkwt_compensation` untuk periode terpilih.

Flow setelah endpoint ini:

- draft dibuat/rebuild dari preview eligible bulan itu,
- pembayaran dilakukan lewat **`POST /payroll-runs/{id}/disburse`**,
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
| `THR_DISBURSE_NO_EMPLOYEES` | 422 | Disburse tanpa `userIds` |
| `THR_DISBURSE_LINE_NOT_PAYABLE` | 422 | Baris tidak eligible / THR nihil |
| `THR_POST_UNPAID_PAYABLE_LINES` | 422 | Masih ada eligible THR&gt;0 yang belum `paid` |
| `THR_SEND_SLIP_*` | 422 | Bulk kirim slip — validasi batch/line/PDF |

## Tes

`HcmPayrollApiTest` — admin vs karyawan, draft → finalize → `my-slip`, `my-slip-pdf`, dan `my-slip-lines` (termasuk overtime + deductions).

`HcmPayrollPkwtApiTest` — RBAC admin-only + preview PKWT + post ke payroll standalone + disburse + muncul di `my-slip-lines`.

`HcmPayrollThrApiTest` — RBAC, validasi, pro rata / penuh / tidak eligible, batch generate → disburse → slip PDF → post-payroll + `my-slip-lines` dengan `purpose` **thr**.
