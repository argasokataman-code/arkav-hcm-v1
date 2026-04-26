# Reporting

## Ringkasan

Feature Reporting dipakai untuk membaca report operasional live sekaligus membekukan hasil tertentu sebagai snapshot immutable untuk audit, export, dan pembandingan point-in-time. Reporting di sistem ini menjadi lapisan baca lintas modul yang harus tetap tenant-safe walau sebagian halaman lama masih memakai legacy API.

## Akses

- HCM Admin / HR Admin: generate snapshot, membuka report live/archive, dan export snapshot.
- Admin operasional lama: masih membuka halaman report existing yang memakai legacy report API.
- Karyawan non-admin: tidak boleh generate/list/detail/export snapshot admin-only.

## UI Aktif

- Hub utama: `/reports`.
- Halaman report yang sudah mendukung `Live Data` vs `Archive Snapshot`: attendance, payslip, employee, dan leave report.
- Halaman report lama seperti invoice/payment/user/daily/project/task masih memakai surface existing.

## Documentation Structure

- [README.md](README.md) — business overview, flow, role check, existing vs target, dan gap transparency
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — technical summary untuk snapshot engine, legacy report API, export, dan tenant wiring
- [E2E-TESTING.md](E2E-TESTING.md) — skenario manual UI E2E admin/non-admin
- [tracker.md](tracker.md) — snapshot status terbaru, evidence, dan open gaps

## Ringkasan Bisnis

Feature **Reporting** dipakai untuk dua kebutuhan bisnis yang berjalan bersamaan:

1. memberi user admin halaman report operasional yang bisa dibaca langsung dari data runtime aktif;
2. membekukan hasil report tertentu menjadi **snapshot immutable** agar bisa dibandingkan, diaudit, diexport, dan dibuka ulang tanpa tergantung perubahan data live.

Karena itu reporting di sistem ini tidak boleh dibaca hanya sebagai “halaman chart”. Reporting menjadi lapisan baca lintas modul yang harus menjaga tiga hal sekaligus:

- angka yang dibuka admin harus sesuai tenant aktif;
- archive snapshot tidak boleh bocor lintas company;
- halaman report lama yang masih memakai API legacy harus tetap berjalan tanpa membuka celah override tenant.

## Lifecycle Dan Keputusan Bisnis

- Snapshot immutable dipakai saat bukti point-in-time dibutuhkan.
- Legacy report API tetap dipertahankan selama migrasi penuh belum selesai, tetapi tenant override tidak boleh bocor.
- Satu feature reporting saat ini memang menjalankan dua surface sekaligus: snapshot HCM baru dan legacy reporting lama.

## Aktor & Role

| Aktor | Peran bisnis | Akses existing |
|------|---------------|----------------|
| HCM Admin / HR Admin | Generate snapshot, membuka report live/archive, export snapshot | Full access ke `/reports` dan endpoint snapshot HCM |
| Admin operasional lama | Membuka halaman report lama seperti invoice/payment/user/daily/project/task report | Tetap memakai halaman existing + legacy `/v1/saas/reports/*` |
| Karyawan non-admin | Tidak boleh generate/list/detail/export snapshot admin-only | Ditolak di backend (`403`) dan diarahkan menjauh dari halaman admin-only |

## Flow Bisnis End-to-End

### Flow utama

1. Admin membuka halaman `/reports` sebagai hub reporting.
2. Admin memilih jenis report yang ingin dibaca, misalnya attendance, payroll, employee, leave, atau finance.
3. Jika butuh data current-state, admin membuka halaman report target dalam mode `Live Data`.
4. Jika butuh bukti point-in-time, admin menekan `Generate` dari hub untuk membuat snapshot berdasarkan periode tertentu.
5. Sistem menyimpan metadata snapshot, filter, dan data block JSON yang mewakili keadaan data pada saat generate.
6. Admin kembali membuka halaman report target dan memilih mode `Archive Snapshot` untuk membaca snapshot yang sudah dibekukan.
7. Jika perlu dibagikan keluar layar operasional, admin men-trigger export snapshot ke `csv`, `excel`, atau `pdf`.

### Exception / skenario negatif

- Jika tenant context aktif tidak ada, snapshot API menolak request.
- Jika non-admin mencoba generate snapshot, backend mengembalikan `403 AUTH_FORBIDDEN`.
- Jika snapshot milik company lain diminta dengan id yang diketahui, backend mengembalikan `404 SNAPSHOT_NOT_FOUND`.
- Jika snapshot belum `completed`, export ditolak `422 SNAPSHOT_NOT_READY`.
- Jika halaman attendance report dibuka dalam mode `Archive Snapshot` dengan snapshot non-attendance atau status belum `completed`, UI sekarang menolak merender data misleading dan menampilkan pesan error eksplisit.
- Jika halaman employee report atau leave report dibuka dalam mode `Archive Snapshot` dengan snapshot yang belum `completed`, UI sekarang menghentikan render archive dan menampilkan pesan yang eksplisit.
- Jika halaman payslip report dibuka dengan Snapshot ID yang bukan `payroll` atau status snapshot belum `completed`, UI menolak memuat slip archive dan menampilkan error yang jelas.
- Jika flow HCM mengirim `X-Company-Id` tetapi query legacy report API mencoba override `company_id` ke company lain, backend mengembalikan `403 TENANT_SCOPE_MISMATCH`.
- Jika backend mengirim error envelope valid, Reports Hub sekarang menampilkan pesan error aktual ke user, bukan error generik yang kabur.

## Integrasi

- Attendance Shift Schedule: attendance report dan snapshot attendance membaca data kehadiran, shift, dan timesheet tenant aktif. Lihat `docs/features/attendance-shift-schedule/README.md`.
- Leave And Holidays: leave report dan evaluasi absence memerlukan konteks leave request dan holiday calendar. Lihat `docs/features/leave-and-holidays/README.md`.
- Payroll Runs dan Employee Salary: payslip/payroll report memakai hasil payroll period, salary structure, dan komponen payroll yang aktif. Lihat `docs/features/payroll-runs/README.md` dan `docs/features/employee-salary/README.md`.
- Export Reconciliation: snapshot/report export dapat menjadi evidence operasional untuk kontrol sebelum action finansial sensitif. Lihat `docs/features/export-reconciliation/README.md`.
- Super Admin Dashboard dan Trial/Billing Dashboard: report revenue, aging, churn, dan subscription analytics beririsan dengan dashboard global/billing. Lihat `docs/features/super-admin-dashboard/README.md` dan `docs/features/trial-billing-dashboard/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Snapshot Audit 2026-04-20

- **Sudah diperbaiki**: legacy report API (`/v1/saas/reports/*`) sekarang terkunci ke `X-Company-Id` bila request datang dari HCM flow tenant-scoped.
- **Sudah diperbaiki**: backend menolak override `company_id` yang berbeda dari tenant aktif dengan `403 TENANT_SCOPE_MISMATCH`.
- **Sudah diperbaiki**: Reports Hub merender pesan backend error envelope yang aktual untuk negative flow generate/load/export.
- **Sudah diverifikasi**: snapshot detail dan export tetap terisolasi per company dan tidak bocor lintas tenant.
- **Sudah diverifikasi**: snapshot detail/export menerima UUID atau numeric legacy fallback pada path identifier.
- **Sudah dirapikan**: source-of-truth docs sekarang mencakup snapshot HCM dan legacy report API yang masih dipakai halaman report lama.
- **Sudah diperketat**: manual snapshot selector pada attendance, employee, leave, dan payslip report tidak lagi merender archive yang salah tipe atau belum `completed`.

## Role & Permission Cross-check

### Halaman aktif

| Surface | Existing target role | Catatan |
|--------|-----------------------|---------|
| `/reports` | HCM Admin only | Hub snapshot/generate/export |
| `/attendance-report` | HCM Admin only | Mendukung `Live` vs `Archive Snapshot` |
| `/payslip-report` | HCM Admin only | Mendukung `Live` vs `Archive Snapshot` |
| `/employee-report` | HCM Admin only | Mendukung `Live` vs `Archive Snapshot` |
| `/leave-report` | HCM Admin only | Mendukung `Live` vs `Archive Snapshot` |
| `/invoice-report`, `/payment-report`, `/expenses-report`, `/user-report`, `/daily-report` | Admin flow existing | Masih memakai legacy report/data source yang sudah ada |

### Endpoint API existing

| Endpoint | Fungsi | Existing role behavior |
|----------|--------|------------------------|
| `POST /v1/hcm/reports/snapshots` | generate snapshot | Admin only, tenant-scoped |
| `GET /v1/hcm/reports/snapshots` | list snapshot | Admin only, tenant-scoped |
| `GET /v1/hcm/reports/snapshots/{id}` | detail snapshot | Admin only, tenant-scoped, UUID + numeric fallback |
| `POST /v1/hcm/reports/snapshots/{id}/export` | export snapshot | Admin only, tenant-scoped, UUID + numeric fallback |
| `GET /v1/saas/reports/revenue` | revenue report legacy | Admin only; bila ada `X-Company-Id`, scope terkunci ke tenant aktif |
| `GET /v1/saas/reports/aging` | aging report legacy | Admin only; bila ada `X-Company-Id`, scope terkunci ke tenant aktif |
| `GET /v1/saas/reports/churn` | churn report legacy | Admin only; bila ada `X-Company-Id`, scope terkunci ke tenant aktif |

## Existing Vs Target

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah ada

- snapshot immutable sudah aktif dengan model metadata, filter, export, dan data blocks terpisah;
- Reports Hub sudah bisa generate, refresh list, view detail singkat, dan trigger export;
- mode `Live` vs `Archive Snapshot` sudah aktif di attendance, payslip, employee, dan leave report;
- snapshot detail/export sudah menerima UUID atau numeric legacy fallback;
- legacy report API tetap hidup untuk report page lama, tetapi sekarang aman terhadap override tenant bila dipanggil dari HCM flow;
- frontend reporting sudah mengirim bearer auth + tenant context dan menampilkan pesan error backend yang relevan.

### Gap yang masih terbuka

- belum semua halaman report lama dimigrasikan ke snapshot/HCM-native contract; beberapa masih mengandalkan API legacy atau data source modul lama;
- belum ada browser/history page yang business-friendly untuk menelusuri export dan snapshot tanpa masuk ke table utama hub;
- export payload masih generic flatten dan belum dibentuk khusus per kebutuhan BI downstream.

### Keputusan kompromi sementara

- sistem mempertahankan dua surface reporting sekaligus: snapshot HCM baru dan legacy report API lama, selama migration penuh belum selesai;
- source-of-truth docs mengikuti runtime aktif, bukan memaksa narasi bahwa semua report sudah snapshot-native;
- tenant hardening dipasang tanpa mematahkan consumer global lama yang memang tidak membawa `X-Company-Id`.

## UI Existing

- Hub utama memakai halaman `/reports` dengan kartu report + tombol `View` dan `Generate`.
- Modal generate snapshot memakai `#generate_modal`.
- Snapshot history ditampilkan di tabel `Recent Snapshots`.
- Halaman attendance, payslip, employee, dan leave report sudah punya source selector `Live Data` vs `Archive Snapshot`.
- Report page lama seperti invoice/payment/user/daily/project/task masih mengikuti template existing dan diisi oleh `reports-api-sync.js` atau JS page terkait.
- Negative flow pada hub sekarang menampilkan alert Bootstrap dengan pesan backend yang nyata.

## Status

- Status implementation: **in progress**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: hardening multi-tenant utama untuk reporting sudah tertutup, tetapi migrasi penuh halaman report lama ke contract snapshot/HCM-native belum selesai.

## API & Technical References

- API contract: [docs/api/hcm-reporting-api.md](../../api/hcm-reporting-api.md)
- OpenAPI: [docs/api/openapi.yaml](../../api/openapi.yaml)
- Technical summary: [IMPLEMENTATION.md](IMPLEMENTATION.md)

## Test & Evidence

- Backend snapshot integration: `backend/tests/Feature/ReportSnapshotApiTest.php`
- Backend legacy report integration: `backend/tests/Feature/ReportControllerTest.php`
- Frontend Reports Hub wiring: `backend/tests/ui/reports-hub.wiring.test.js`
- Frontend legacy report sync wiring: `backend/tests/ui/reports-api-sync.wiring.test.js`
- Negative scenario yang sudah diverifikasi:
	- non-admin tidak bisa generate snapshot;
	- snapshot company lain tidak bisa dibaca atau diexport;
	- snapshot detail bisa dibuka via UUID;
	- legacy revenue report tidak bisa mengoverride `company_id` bila tenant header aktif;
	- hub menampilkan pesan backend error envelope pada failure flow.
