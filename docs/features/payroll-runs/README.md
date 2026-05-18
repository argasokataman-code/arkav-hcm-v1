# Payroll — periode & run

## Ringkasan

Fitur payroll run menutup jarak antara data kompensasi karyawan, katalog payroll item, dan hasil slip gaji final. Di sini sistem membentuk periode payroll, menghitung draft run, menampilkan baris slip minimal, mengunci finalisasi, dan mencatat penyelesaian pembayaran bulanan secara manual setelah gate export reconciliation terpenuhi.

Untuk roadmap penuh payroll, alur target tetap mengacu ke `docs/planning/payroll-lifecycle.md` dengan tahapan pre-payroll, actual, dan post-payroll. Implementasi runtime yang aktif saat ini berfokus pada inti actual payroll: hitung draft, finalisasi, histori run, self-service slip lines, dan batch mark-paid manual yang dikontrol evidence export.

## Akses

- Web: `/payroll-run`, `/payroll-run-history`, dan flow payroll terkait hanya tersedia lewat middleware `hcm.web.admin`.
- API: endpoint `payroll-periods`, `payroll-runs`, `payroll/send-slips`, dan THR batch hanya dapat dipanggil HCM Admin, kecuali endpoint self-service seperti `my-slip-lines` atau `my-thr-slip` yang memang ditujukan untuk employee.
- Permission praktis: guard server tetap menjadi sumber kebenaran; tombol frontend hanya mengikuti status yang diberikan backend. Mutasi inti kini dipisah tegas: `calculate-draft` memakai `payroll.run`, assignment payroll item memakai `payroll.manage`, THR setup/post/send memakai `payroll.thr.manage`, PKWT post payroll memakai `payroll.pkwt.manage`, dan aksi bayar memakai `payroll.disburse`.

## UI Aktif

- Halaman aktif: `/payroll-run` untuk run periode aktif, `/payroll-run-history` untuk histori run, dan `/monthly-report` untuk laporan gabungan monthly + THR + PKWT.
- `/payroll-run` dikunci ke periode aktif: tahun readonly dan bulan disabled agar admin tidak salah mengeksekusi periode historis dari layar operasional utama.
- Halaman `/payslip` sudah menjadi surface self-service employee untuk slip bulanan yang menggabungkan run `monthly`, `thr`, dan `pkwt_compensation` pada bulan kalender yang sama, dengan summary overtime eksplisit di payload, UI, PDF, dan email.
- Slip THR karyawan belum punya halaman web khusus; employee memakai `GET /payroll/my-thr-slip` dan unduhan PDF `GET /payroll/thr-batch/lines/{line}/slip`.

## Flow Bisnis End-to-End

1. HCM Admin membuka `/payroll-run` untuk periode payroll aktif.
2. Admin menjalankan Calculate Draft. Halaman tidak menghitung otomatis saat dibuka.
3. Jika draft sudah ada dan statusnya masih `draft`, tombol Calculate tetap boleh dipakai untuk rebuild draft menggunakan source of truth terbaru; runtime aktif tidak lagi mengandalkan reuse draft stale.
4. Setelah draft tersedia, admin meninjau baris run dan hanya bisa melanjutkan export reconciliation ketika status tetap `draft`.
5. Admin menjalankan Export Reconciliation untuk action `payroll_run` dengan `actionKey=disburse`, lalu wajib mengunduh file sampai sukses. File yang dihasilkan berbentuk summary per karyawan dengan bank/rekening dan nominal transfer agar bisa dipakai untuk pembayaran manual tenant.
6. Sesudah unduhan evidence berhasil, baru tombol tandai dibayar manual dapat digunakan untuk batch pembayaran.
7. Jika draft sudah difinalisasi tetapi ternyata masih perlu koreksi setup, admin dapat melakukan `void` selama belum ada line yang berstatus `paid`, lalu menghitung draft ulang pada periode aktif yang sama.
8. Setelah disburse atau reset pembayaran dev, admin harus export dan unduh lagi sebelum batch bayar berikutnya, supaya evidence tidak dipakai berulang tanpa jejak baru.
9. Jika run sudah finalized, void, atau posted, histori dan audit trail dipantau dari `/payroll-run-history`.

## Lifecycle Dan Keputusan Bisnis

- Active period: layar operasional utama selalu menempel ke periode payroll aktif.
- Draft: setiap karyawan `active` atau `probation` minimal mendapatkan baris upah pokok, walau nilainya bisa 0; tunjangan tetap hanya dibentuk jika nominalnya lebih dari 0.
- Finalize: ditolak dengan `PAYROLL_RUN_EMPTY` jika run tidak memiliki baris eligible.
- Posted: setelah finalize berhasil, periode induk berubah menjadi `posted`.
- Void: hanya boleh untuk run `finalized` yang belum memiliki line `paid`; bila tidak ada finalized run lain di periode yang sama maka periode dibuka kembali agar draft bisa dihitung ulang.
- Disburse: endpoint tetap bernama `disburse`, tetapi runtime aktif memakainya untuk mencatat pembayaran manual eksternal sesudah evidence export reconciliation tersedia dan, bila enforcement diaktifkan, lolos gate server.
- THR run: flow THR mass memakai purpose `thr`, sedangkan payroll reguler memakai purpose `monthly`; employee self-service kemudian melihat gabungan line final monthly dan THR per bulan kalender.
- Monthly Report: admin mempunyai surface laporan detail tersendiri yang menggabungkan run final `monthly`, `thr`, dan `pkwt_compensation` per employee-periode agar review dan export lintas payroll tidak tersebar di tiga halaman berbeda.
- Monthly Report dan admin payslip report kini juga mengangkat overtime sebagai summary eksplisit (`overtime.amountTotal` + `totals.overtimeTotal`), bukan hanya sebagai line item di daftar earnings.

## Integrasi

- Payroll API: `docs/api/hcm-payroll-api.md` mencakup `payroll-periods`, `payroll-runs`, `payroll/my-slip-lines`, `send-slips`, dan THR batch.
- Export reconciliation: gate disbursement terkait dengan evidence export `payroll_run/disburse`, termasuk file download sebagai bukti operator sudah mengambil hasil export. Runtime aktif memakai format payment-ready satu baris per karyawan, bukan campuran line payroll + subtotal teknis.
- Export reconciliation payroll kini juga seragam lintas THR dan PKWT compensation, sehingga admin/operator mendapatkan layout payment-ready yang sama untuk tiga flow manual settlement payroll.
- Salary components dan payroll items: draft payroll bergantung pada data kompensasi dan katalog item yang dibentuk di feature terkait.
- THR: halaman `/payroll-thr` dapat membuat run purpose `thr`, lalu hasilnya ikut tampil pada self-service slip lines sesuai bulan kalender.

## Kontrak API

- Endpoint tambahan aktif saat ini: `GET /payroll-periods/active`, `GET /payroll-runs/history`, dan `GET /payroll-runs/{id}` termasuk `auditTrail`.
- Identifier aktif untuk `userIds` pada disburse/send slips di runtime sekarang menerima numeric `users.id` sebagai kontrak utama yang dipakai frontend, dengan UUID fallback yang tetap didukung server untuk kompatibilitas transisi.
- OpenAPI dan API doc payroll tetap menjadi referensi kontrak utama untuk endpoint payroll yang published.

## Existing Vs Target

- Existing: implementasi aktif saat ini sudah menutup calculate draft, finalize, void finalized run yang belum dibayar, history, self-service slip lines, slip PDF, monthly report admin gabungan, dan gate mark-paid manual berbasis reconciliation export.
- Existing: `/payslip` web sudah memakai runtime aktif `my-slip`, `my-slip-latest-period`, dan `my-slip-pdf` untuk audience employee.
- Existing: payload payroll untuk payslip, admin slips, dan monthly report kini menyertakan summary overtime eksplisit agar UI/export tidak perlu menebak dari line mentah.
- Target: penguatan berikutnya lebih bersifat operasional dan governance, seperti audit append-only terpisah, hardening post-payroll controls lintas integrasi eksternal, dan review kebijakan payroll lintas tenant.

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah aktif

- periode payroll aktif, calculate draft, finalize, history run, audit trail dasar, dan gate pembayaran manual berbasis export reconciliation sudah berjalan;
- run finalized yang belum dibayar sekarang bisa di-void dari UI/API, lalu periode aktif dapat dihitung ulang tanpa membiarkan run paid ikut dibatalkan;
- history payroll sekarang juga menyimpan metadata `voidedAt` / `voidedBy*` sehingga event `voided` punya waktu dan actor yang bisa dibaca dari detail/history API;
- endpoint self-service `my-slip-lines`, `my-slip`, THR slip API, dan surface admin `/monthly-report` sudah menjadi surface aktif setelah finalization, dengan overtime summary eksplisit di payslip, admin slips, dan monthly report;
- identifier `userIds` pada send slips/disburse sudah mengikuti kontrak runtime aktif frontend dengan numeric `users.id` sebagai jalur utama dan UUID fallback tetap didukung server;
- evidence export sekarang harus diunduh ulang setelah penandaan paid/reset dev agar operator tidak memakai evidence lama tanpa jejak baru.

### Gap yang masih terbuka

- model audit append-only yang lebih kaya di luar payload runtime masih backlog bila nantinya dibutuhkan untuk jejak kepatuhan terpisah dari surface operasional;
- post-payroll controls lanjutan untuk reversal/adjustment lintas integrasi eksternal masih perlu diputuskan bila scope bisnis melewati flow void-before-paid yang sekarang aktif;
- boundary dokumentasi monthly run vs THR run vs PKWT compensation tetap perlu dijaga sinkron saat modul payroll berkembang, walau runtime aktifnya sudah tersedia dan kini sudah punya surface gabungan `/monthly-report`.

### Integrasi cuti tanpa gaji & kerja hari libur (opt-in)

Mulai audit H3, `PayrollDraftBuilder` dapat menghasilkan dua jenis line tambahan saat membangun draft:

- `potongan_cuti_unpaid` (deduction): dihitung dari `LeaveRequest` berstatus `approved` dengan `leave_type` bertipe unpaid (`unpaid`, `unpaid_*`, atau mengandung `no_pay`/`tanpa_gaji`) yang overlap dengan periode. Basis hitung = `dailyRate * jumlah hari kerja unpaid` (hari kerja diambil dari `LeaveWorkingDayCalculator`).
- `tunjangan_kerja_libur` (addition): dihitung dari `AttendanceRecord` dengan `check_in_at` terisi pada tanggal yang terdaftar di `holiday_calendars`/`holidays` dalam periode. Basis hitung = `dailyRate * jumlah hari * multiplier` (default `HCM_PAYROLL_HOLIDAY_WORK_MULTIPLIER=2`).

Keduanya dikunci di belakang feature flag `payroll.leave_integration_enabled` (default `false`). Aktivasi dapat dilakukan secara global lewat env `HCM_PAYROLL_LEAVE_INTEGRATION=true`, atau per-tenant lewat `company_settings` (key `payroll.leave_integration_enabled`, value `1`, type `boolean`). Tenant yang belum opt-in tidak mengalami perubahan kontrak payroll.

Komponen auto-provisioned via `resolveOrCreateComponent` di `PayrollDraftBuilder` bila belum ada di master `hcm_salary_components`. Deduction menurunkan `taxableGross`, addition menambah `taxableGross` sehingga kalkulasi PPh21 TER ikut menyesuaikan.

### Keputusan kompromi sementara

- payroll run dianggap siap dipakai untuk lifecycle operasional inti yang saat ini dijual di produk: draft, finalize, void-before-paid, history, self-service slip, PDF, dan mark-paid manual bergate reconciliation;
- review lanjutan yang tersisa diposisikan sebagai hardening governance/compliance, bukan blocker runtime untuk deploy surface payroll yang aktif;
- tracker feature dipakai untuk memisahkan evidence deploy-readiness saat ini dari enhancement audit/governance yang mungkin ditambahkan kemudian.

## Status

- Status implementation: **runtime aktif dengan Monthly Report dan export payment-ready seragam, tetapi evidence manual E2E export/mark-paid per role belum 100% tertutup**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: actual payroll inti sudah aktif end-to-end untuk surface runtime yang dipublikasikan, termasuk payslip web/PDF, overtime roll-up, deduction engine BPJS/PPh21 TER, void-before-paid, history payroll, dan hardening guard permission mutasi payroll. Closure release penuh masih menunggu bukti manual E2E export/mark-paid per role.

## Catatan QA

- Reset THR untuk QA tersedia via `php artisan hcm:reset-thr-test-data` dengan opsi seperti `--year`, `--full`, `--keep-settings`, dan `--fresh-slip-numbers`.
- Alternatif SQL manual tetap tersedia di `THR_RESET_QUERY.sql`, dengan catatan penjelasan dan verifikasi di `THR_RESET_MANUAL.sql`.
