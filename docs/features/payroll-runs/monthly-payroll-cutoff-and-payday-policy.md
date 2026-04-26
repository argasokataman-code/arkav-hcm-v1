# Payroll Bulanan — policy cutoff & payday tenant-level

## Ringkasan

Dokumen ini menjadi acuan implementasi untuk fitur **policy cutoff dan payday payroll bulanan** per tenant. Tujuan utamanya adalah memberi batas kerja payroll yang formal agar draft bulanan, item variabel, dan tanggal gajian berjalan konsisten mengikuti kalender bisnis perusahaan.

Konteks bisnis yang dipakai saat ini mengikuti praktik umum payroll Indonesia:

- payday bulanan biasanya berada di akhir bulan aktif, misalnya tanggal 28;
- cutoff payroll biasanya beberapa hari sebelum payday, misalnya 3 hari kerja/kalender sebelumnya sehingga cutoff jatuh di tanggal 25 pada bulan yang sama;
- item variabel yang masuk setelah cutoff, seperti reimbursement atau input tambahan lain, tidak ikut draft periode berjalan dan masuk ke periode selanjutnya.

## Tujuan Bisnis

- memberi titik cut-off formal sebelum payroll difinalisasi atau dibayar;
- membuat tenant dapat punya kebijakan payroll sendiri tanpa mengganggu tenant lain;
- menurunkan koreksi manual karena perubahan data yang masuk terlalu dekat ke tanggal gajian;
- menjaga audit trail ketika draft direfresh otomatis oleh sistem.

## Asumsi Operasional Production

- fitur ini dipakai tenant nyata yang menjalankan payroll untuk manusia, sehingga setiap keputusan fallback harus bisa dijelaskan ke HR, finance, dan auditor;
- cutoff harus dipahami sebagai **batas akuntansi/operasional** untuk periode payroll berjalan, bukan sekadar pengingat UI;
- payday harus dipahami sebagai **tanggal target pembayaran payroll bulanan**; untuk MVP saat ini, pembayaran sebelum payday diperlakukan sebagai hard-block bila policy tenant melarang early disburse dan tidak ada override inline di endpoint/UI;
- setiap perubahan policy tenant setelah draft pernah terbentuk harus meninggalkan jejak kapan perubahan terjadi dan draft mana yang masih memakai policy lama;
- sistem harus lebih memilih perilaku yang aman dan dapat diaudit daripada otomatisasi yang sulit dijelaskan kembali setelah payroll dibayar.

## Cakupan Fitur

- policy berlaku untuk payroll bulanan purpose `monthly`;
- policy **tidak** otomatis mengganti aturan THR tahunan atau PKWT compensation, tetapi perlu dijadikan referensi agar perilaku antar modul tetap konsisten;
- policy dipakai untuk menentukan tanggal cutoff resolved, payday resolved, dan guard auto-refresh draft pada periode aktif.

## Konfigurasi Yang Disarankan

Policy disimpan **per tenant** di `company_settings`, bukan di attendance schedule atau period table, agar konsisten dengan pola konfigurasi tenant-level yang sudah ada.

Key yang disarankan:

- `payroll.monthly.payday_day`
- `payroll.monthly.cutoff_offset_days`
- `payroll.monthly.payroll_timezone`
- `payroll.monthly.disburse_before_payday_allowed`

Contoh policy:

- `payroll.monthly.payday_day = 28`
- `payroll.monthly.cutoff_offset_days = 3`
- `payroll.monthly.payroll_timezone = Asia/Jakarta`

Hasil resolve untuk periode Maret 2026:

- payday resolved = 2026-03-28
- cutoff resolved = 2026-03-25

Untuk bulan yang jumlah harinya lebih pendek, payday tetap mengikuti hari terakhir bulan bila angka hari tidak tersedia. Contoh:

- payday day 31 pada April menjadi 30 April;
- payday day 31 pada Februari menjadi 28/29 Februari tergantung leap year.

## Flow Bisnis Target

1. HCM Admin mengatur policy payroll tenant di surface settings payroll.
2. Sistem menyimpan policy per tenant dan memakainya saat membangun atau merefresh draft payroll monthly.
3. Untuk periode aktif, sistem me-resolve payday dan cutoff berdasarkan tahun-bulan periode.
4. Calculate Draft hanya memasukkan data yang effective date-nya berada pada atau sebelum cutoff resolved.
5. Item yang masuk setelah cutoff dianggap sebagai input periode berikutnya.
6. Auto-refresh draft oleh scheduler berjalan hanya selama policy masih mengizinkan refresh untuk periode tersebut.
7. Saat draft dibayar, sistem menegakkan hard-block sebelum payday resolved bila `disburseBeforePaydayAllowed=false`; exception operasional mengikuti playbook tenant-level, bukan override inline pada tombol disburse.

## Lifecycle Operasional Yang Disarankan

### Sebelum cutoff

- admin masih dapat melakukan Calculate Draft berulang untuk menarik perubahan data yang valid pada periode berjalan;
- scheduler auto-refresh masih boleh aktif selama run monthly belum finalized dan policy tenant masih mengizinkan refresh otomatis;
- UI perlu menampilkan countdown atau indikator yang jelas bahwa payroll masih berada pada fase “data bisa berubah”.

### Setelah cutoff tetapi sebelum payday

- payroll monthly masuk fase review/freeze: perubahan variabel baru tidak lagi masuk ke draft periode berjalan;
- Calculate Draft manual tetap boleh dijalankan untuk review/check data, simulasi dampak, atau memastikan tidak ada data valid yang tertinggal; tetapi hasil review itu tidak boleh dipakai untuk menjalankan payroll/disburse sebelum tenggat operasional yang sudah ditentukan;
- UI harus menampilkan pesan tegas bahwa item setelah cutoff akan dipindahkan ke periode berikutnya;
- aktivitas baru yang datang setelah cutoff tetap harus tercatat di penampung/history sementara agar tidak hilang, lalu otomatis dibawa ke periode payroll berikutnya setelah seluruh pembayaran periode berjalan selesai;
- untuk MVP saat ini, tidak ada override inline pasca-cutoff; jika tenant perlu policy berbeda untuk periode itu, perubahan dilakukan pada policy tenant lalu draft harus dibangun ulang agar snapshot baru terbentuk.

### Pada payday atau sesudahnya

- disburse payroll boleh dilakukan sesuai policy perusahaan dan snapshot run yang aktif;
- bila pembayaran dilakukan sebelum payday resolved dan `disburseBeforePaydayAllowed=false`, sistem melakukan hard-block murni tanpa override inline;
- histori run harus mampu menjelaskan tanggal cutoff resolved, payday resolved, kapan draft terakhir dihitung, kapan finalize, dan kapan pembayaran dieksekusi.

## Kontrak Operasional Final (MVP Saat Ini)

1. Mode enforcement final untuk payroll monthly adalah **hard-block murni** saat `disburseBeforePaydayAllowed=false` dan tanggal lokal payroll masih sebelum `resolvedPaydayDate`.
2. MVP saat ini **tidak menyediakan override inline** pada endpoint disburse, modal UI, maupun parameter API tambahan.
3. Sumber kebenaran operasional adalah `policySnapshot` pada run monthly. Mengubah `company_settings` setelah draft terbentuk tidak mengubah guard run yang sudah punya snapshot.
4. Jika run masih `draft`, exception operasional dilakukan dengan ubah policy tenant lalu jalankan `Calculate Draft` ulang agar snapshot baru dipakai.
5. Jika run sudah `finalized` tetapi belum `paid`, exception operasional dilakukan dengan `void` run tersebut, ubah policy tenant yang disetujui, lalu `Calculate Draft` ulang, export reconciliation ulang, dan baru disburse.
6. Jika run sudah `paid`, tidak ada replay policy di run yang sama. Koreksi harus memakai proses adjustment/reversal terpisah di luar scope MVP cutoff/payday ini.

## Playbook Exception Minimum

Untuk tenant yang membutuhkan pembayaran lebih awal dari payday policy, MVP saat ini memakai playbook berikut:

1. HR/payroll admin mendapatkan persetujuan operasional sesuai SOP tenant sebelum mengubah policy.
2. Evidence minimum yang harus dicatat di luar sistem saat ini: alasan bisnis, approver, actor yang mengubah policy, nilai policy lama/baru, periode payroll yang terdampak, dan timestamp persetujuan.
3. Jika draft sudah ada, operator wajib memastikan snapshot yang akan dipakai memang berasal dari policy baru melalui recalculate atau void + rebuild, bukan langsung mencoba disburse run lama.
4. Export reconciliation wajib di-generate ulang setelah draft/snapshot baru terbentuk sebelum `Pay via Gateway` dijalankan.
5. Jika tenant membutuhkan approval trail inline dan penyimpanan reason/actor/timestamp di endpoint disburse, itu diperlakukan sebagai enhancement baru di luar MVP saat ini.

## Existing Vs Target

### Existing runtime

- payroll bulanan saat ini baru mengenal periode `period_year` + `period_month` tanpa policy cutoff/payday formal;
- scheduler `payroll_refresh_open_period` merefresh draft open period setiap hari jam 00:00 Asia/Jakarta;
- slip employee dapat menggabungkan run `monthly`, `thr`, dan `pkwt_compensation` pada bulan kalender yang sama;
- THR sudah punya `payment_date` dan `calculation_cutoff_date` sendiri di yearly settings;
- PKWT compensation diposting ke payroll period yang dipilih saat proses posting.

### Target runtime

- payroll monthly punya policy tenant-level yang menjadi sumber kebenaran cutoff/payday;
- draft run menyimpan snapshot policy yang dipakai agar audit trail tidak hilang saat setting berubah;
- scheduler payroll refresh menjadi tenant-aware dan cutoff-aware;
- item variabel bisa dievaluasi terhadap cutoff resolved agar tidak tercampur ke periode yang sudah secara bisnis ditutup.

## Detail Data Yang Wajib Punya Tanggal Acuan

Fitur cutoff hanya aman bila setiap sumber input payroll memiliki tanggal acuan yang jelas. Untuk implementasi production, tiap sumber data harus bisa dijawab dengan pertanyaan: “tanggal mana yang dipakai untuk menentukan masuk periode ini atau periode berikutnya?”

### Sumber yang idealnya memakai effective date

- recurring payroll item assignment dengan rentang `effective_start_date` / `effective_end_date`;
- work arrangement yang mempengaruhi daily rate atau rule payroll;
- status kerja yang berdampak pada eligibility payroll.

### Sumber yang idealnya memakai transaction/event date

- attendance yang mempengaruhi kehadiran, lembur, atau holiday work;
- leave/unpaid leave yang mempengaruhi deduction;
- reimbursement atau variabel sejenis, bila nanti dihubungkan ke engine payroll monthly.

### Prinsip penting

- jangan memakai “tanggal input form” sebagai satu-satunya dasar perioding bila bisnis sebenarnya membutuhkan tanggal efektif kejadian;
- untuk reimbursement/variabel yang dapat diinput terlambat, keputusan final yang lebih aman adalah **kombinasi `effective_date` + `posted_at`**:
  - `effective_date` dipakai untuk menentukan secara bisnis item itu milik periode mana;
  - `posted_at` dipakai untuk audit trail kapan item benar-benar masuk ke sistem, termasuk untuk mendeteksi item yang datang setelah cutoff;
  - bila `effective_date` masih berada pada periode berjalan tetapi `posted_at` sudah lewat cutoff, item tetap dicatat sebagai late-arrival record dan dibawa otomatis ke periode berikutnya sesuai kebijakan freeze operasional yang sudah dikunci.
- bila sumber data belum punya field tanggal yang memadai, modul itu belum layak diikutkan penuh ke cutoff automation tanpa fallback manual yang eksplisit.

## Relasi Tabel Yang Terdampak

### Tabel konfigurasi

- `company_settings`
  - menyimpan policy per `company_id`
  - tidak perlu tabel baru bila pola key-value existing tetap dipakai

### Tabel runtime payroll bulanan

- `hcm_payroll_periods`
  - tetap menjadi anchor tahun-bulan payroll
  - tidak perlu berubah bila snapshot policy disimpan di run metadata
- `hcm_payroll_runs`
  - keputusan final: snapshot resolved policy disimpan di metadata JSON agar fleksibel untuk audit, backward-compatible, dan tidak memaksa penambahan kolom khusus setiap kali struktur snapshot berkembang
- `hcm_payroll_lines`
  - tidak wajib berubah, tetapi line/summary bisa membaca context run snapshot

### Tabel/penampung sementara pasca-cutoff

- dibutuhkan penampung/history sementara untuk aktivitas variabel yang masuk setelah cutoff agar data tidak hilang walau tidak lagi masuk ke draft periode berjalan;
- penampung ini berfungsi sebagai backlog operasional yang tetap bisa diaudit sampai seluruh pembayaran periode berjalan selesai;
- setelah siklus pembayaran bulan berjalan selesai, record yang eligible dipindahkan otomatis ke periode berikutnya dengan jejak asal periode dan timestamp input awal tetap tersimpan.

### Tabel input yang perlu ikut policy cutoff

- `employee_profiles`
- `hcm_employee_payroll_item_assignments`
- `hcm_employee_work_arrangements`
- `attendance_records`
- sumber variabel payroll lain yang nanti dihubungkan ke monthly payroll, termasuk reimbursement bila/ketika sudah dipasang ke engine payroll

### Tabel modul lain yang berelasi secara bisnis

- `hcm_thr_yearly_settings`
- `hcm_thr_batches`
- `hcm_thr_batch_lines`

THR tetap memakai setting tahunannya sendiri. Policy monthly hanya menjadi referensi bisnis agar tidak terjadi konflik pemahaman saat employee menerima monthly + THR + PKWT compensation pada bulan kalender yang sama.

## Dampak Integrasi

### Payroll monthly

- menjadi modul utama yang memakai policy cutoff/payday;
- calculate draft, auto-refresh draft, dan disburse warning/guard membaca policy tenant.

### THR

- tidak memakai policy monthly sebagai sumber tanggal utama;
- tetap memakai `payment_date` dan `calculation_cutoff_date` di THR yearly settings;
- perlu dokumentasi yang jelas bahwa slip bulanan employee tetap bisa menampilkan THR pada bulan yang sama dengan payroll monthly.

### PKWT compensation

- tidak wajib memakai payday monthly sebagai sumber tanggal utama;
- tetap mengikuti periode payroll yang dipilih saat post payroll;
- perlu konsistensi narasi agar admin memahami bahwa kompensasi PKWT bisa hidup berdampingan dengan monthly payroll dalam bulan kalender yang sama.

### Slip employee

- slip bulanan dapat memuat line dari `monthly`, `thr`, dan `pkwt_compensation` sekaligus bila ketiganya finalized pada periode kalender yang sama;
- dokumentasi dan UI perlu menegaskan bahwa ini adalah **rekap penerimaan bulan kalender**, bukan selalu satu batch transfer tunggal.

### Reporting dan audit

- report payroll admin perlu dapat menampilkan cutoff resolved dan payday resolved per run monthly;
- slip employee sebaiknya menampilkan pemisahan source run agar nominal monthly vs THR vs PKWT compensation tidak tercampur secara visual;
- audit trail run perlu memuat event policy change, auto-refresh by system, dan manual recalculate/void untuk rebuild snapshot; event override disburse inline belum ada di MVP saat ini.

## Anomali Yang Harus Dicegah

### Bisnis

- admin menganggap item yang diinput setelah cutoff tetap akan masuk periode berjalan;
- employee mengira slip gabungan berarti selalu satu kali transfer tunggal, padahal source run bisa lebih dari satu;
- perubahan setting payday/cutoff setelah draft dibuat mengubah pemahaman HR tanpa jejak snapshot.
- HR menganggap cutoff adalah tanggal “kunci mutlak semua data”, padahal beberapa sumber data mungkin belum siap memakai effective date yang benar;
- finance mengira ada override inline pada tombol disburse, padahal MVP saat ini memakai hard-block murni dan exception harus lewat rebuild snapshot sesuai playbook;
- admin menganggap hasil auto-refresh harian selalu identik dengan closing payroll final, padahal sistem bisa saja masih menerima perubahan sampai cutoff/finalize.

### UX

- form settings payroll terlalu sederhana sehingga admin tidak paham hubungan antara `payday day` dan `cutoff offset days`;
- UI tidak menunjukkan hasil resolve tanggal aktual untuk bulan aktif, sehingga admin harus menebak sendiri apakah policy menghasilkan cutoff 25 atau 26 untuk bulan tertentu;
- tombol Calculate Draft tetap aktif tanpa konteks setelah cutoff, sehingga user mengira perubahan baru akan ikut periode berjalan;
- halaman payroll run tidak membedakan state “pre-cutoff”, “post-cutoff review”, dan “ready for disburse”, sehingga operator salah langkah;
- slip employee menampilkan total gabungan tanpa breakdown yang cukup jelas, sehingga karyawan mengira ada selisih transfer atau duplikasi pembayaran;
- UI tidak menjelaskan bahwa perubahan policy setelah draft/finalize tidak otomatis mengubah snapshot run yang sudah ada.

### Teknis

- scheduler hanya merefresh satu open period global dan belum loop semua tenant/periode open;
- draft direfresh setelah cutoff terlewati tanpa guard yang jelas;
- policy hanya tersimpan sebagai setting current state, tetapi tidak disnapshot pada run yang sudah pernah dihitung;
- operator mencoba mengubah policy tenant setelah draft terbentuk tetapi tetap memakai run lama, sehingga hasil runtime tidak sesuai ekspektasi karena snapshot belum dibangun ulang.
- modul sumber variabel belum semuanya memiliki field tanggal yang cukup kuat untuk dipakai sebagai dasar cutoff production;
- race condition antara auto-refresh scheduler dan admin manual Calculate Draft/finalize dapat menghasilkan draft yang berubah tanpa ekspektasi user bila belum diberi locking atau messaging yang jelas;
- perubahan setting tenant mendekati cutoff atau payday dapat memunculkan hasil resolve yang berubah mendadak untuk period yang sama bila snapshot belum diterapkan.

## Guardrail UX Yang Disarankan

1. Surface settings payroll harus menampilkan preview resolve untuk bulan aktif dan 1-2 bulan berikutnya.
2. Form settings harus memakai bahasa bisnis, misalnya: “Tanggal gajian bulanan” dan “Cutoff variabel payroll = X hari sebelum tanggal gajian”.
3. Halaman payroll run perlu badge state yang jelas:
  - `Pre-cutoff: data masih bisa berubah`
  - `Post-cutoff: data baru masuk periode berikutnya`
  - `Ready for payday/disburse`
4. Setelah cutoff lewat, tombol Calculate Draft tetap boleh ada hanya bila perilakunya dijelaskan dengan jelas dan, jika perlu, meminta reason.
5. Saat disburse ditolak karena before-payday policy, UI harus menampilkan pesan tegas bahwa MVP saat ini tidak menyediakan override inline dan operator harus memakai policy baru pada draft/snapshot yang dibangun ulang.
6. UI post-cutoff perlu menampilkan bahwa Calculate Draft pada fase ini bersifat review-only dan aktivitas baru setelah cutoff akan masuk antrean/periode berikutnya, bukan masuk draft berjalan.
7. Slip employee dan admin run detail harus menampilkan breakdown per purpose/run, bukan hanya total gabungan.
8. Empty/error states harus spesifik, misalnya menjelaskan bahwa reimbursement setelah cutoff akan dibawa ke periode berikutnya, bukan sekadar “data tidak ditemukan”.

## Rekomendasi Handling

1. Simpan policy tenant-level di `company_settings`.
2. Resolve payday berdasarkan hari kalender bulan aktif; fallback ke last day of month bila hari tidak ada.
3. Simpan snapshot policy resolved pada saat draft dibuat/direfresh di metadata JSON `hcm_payroll_runs`.
4. Ubah scheduler payroll refresh agar loop semua `hcm_payroll_periods` berstatus `open` per tenant, tetapi refresh otomatis harus berhenti saat cutoff lewat untuk mencegah drift payroll berjalan.
5. Kunci mode guard disburse untuk MVP sebagai **hard-block murni** tanpa override inline; exception operasional hanya lewat perubahan policy tenant + rebuild snapshot run.
6. Untuk slip employee, tampilkan breakdown per purpose/run agar monthly vs THR vs PKWT compensation tidak disalahartikan.
7. Untuk reimbursement/variabel terlambat, pakai kombinasi `effective_date` + `posted_at`, lalu sediakan penampung/history sementara untuk item yang masuk setelah cutoff sebelum dibawa otomatis ke periode berikutnya.
8. Jangan aktifkan cutoff automation penuh pada sumber data yang belum memiliki tanggal acuan yang layak audit.
9. Tambahkan state UX yang membedakan pre-cutoff vs post-cutoff agar operator tidak mengandalkan tebakan.

## Dampak Ke Auto Refresh Draft

Runtime saat ini memiliki cron `payroll_refresh_open_period` yang tiap hari merefresh payroll draft untuk open period aktif.

Impact fitur ini:

- job harus resolve policy per tenant sebelum rebuild draft;
- keputusan final: auto-refresh berhenti saat cutoff resolved terlewati agar draft periode berjalan tidak berubah diam-diam setelah fase freeze dimulai;
- job harus menyimpan jejak bahwa rebuild dilakukan oleh sistem dengan policy tertentu;
- job tidak boleh diam-diam memindahkan data setelah cutoff ke draft periode lama.
- job harus aman terhadap multi-tenant dan multi-period open, bukan hanya memilih satu period open paling akhir secara global;
- job harus menghindari rebuild di saat yang terlalu dekat dengan aksi manual admin, atau setidaknya memberi jejak agar perubahan draft bisa dijelaskan;
- aktivitas baru setelah cutoff tetap perlu disimpan di penampung/history sementara agar dapat diaudit dan otomatis dimigrasikan ke periode berikutnya setelah pembayaran bulan berjalan selesai.

Dengan keputusan final saat ini, manual Calculate Draft setelah cutoff tetap boleh untuk kebutuhan review/check data, tetapi bukan untuk membuka kembali siklus payroll berjalan atau membolehkan pembayaran lebih cepat dari tenggat yang sudah dikunci.

## Persyaratan Production Readiness

Sebelum fitur ini dianggap layak produksi, minimal harus ada:

1. snapshot policy resolved pada run monthly;
2. scheduler tenant-aware dan cutoff-aware;
3. UX preview resolve tanggal pada settings payroll;
4. UX state yang jelas pada payroll run setelah cutoff;
5. breakdown purpose/run pada slip atau detail slip;
6. test untuk edge case tanggal pendek, leap year, before-payday reject, dan item setelah cutoff;
7. keputusan final yang terdokumentasi bahwa payday/disburse MVP memakai hard-block murni tanpa override inline.

## Register Gap MVP Production (Wajib Sebelum Go-Live)

Bagian ini adalah register gap eksekusi yang diperlakukan sebagai **MVP wajib** untuk fitur cutoff/payday payroll bulanan. Jika salah satu item berstatus belum selesai, fitur belum boleh diklaim production-complete.

### Gap 1 — Engine payroll monthly belum cutoff-scoped penuh

- Kondisi saat ini: engine draft monthly sudah memakai `policySnapshot.draftDataAsOfDate` dengan default `resolvedCutoffDate` sebagai batas data variabel periode berjalan.
- Implementasi final:
  - builder/query line-item monthly memakai cutoff snapshot untuk assignment eligibility, overtime aggregation, dan leave/holiday event adjustment;
  - transaksi variabel setelah cutoff tidak lagi ikut draft periode berjalan pada source yang sudah di-enforce;
  - payload run tetap membawa snapshot resolved policy agar alasan perioding bisa diaudit.
- Dampak bisnis: closing payroll bulanan sekarang konsisten dengan policy cutoff tenant pada area runtime yang sudah terhubung ke engine payroll.
- Evidence close: `PayrollOvertimeRuleIntegrationTest`, `HcmPayrollPeriodApiTest`, dan `PayrollLeaveHolidayIntegrationTest` lulus; termasuk regresi `test_payroll_overtime_excludes_entries_after_cutoff_snapshot`.
- Status: **CLOSED (BLOCKER MVP, 2026-04-26)**.

### Gap 2 — Guard disburse vs payday belum enforced di endpoint

- Kondisi saat ini: endpoint `POST /v1/hcm/payroll-runs/{id}/disburse` kini memblokir pembayaran `monthly` saat flag `disburseBeforePaydayAllowed=false` dan tanggal lokal payroll masih sebelum `resolvedPaydayDate`.
- Implementasi final:
  - controller membaca `meta.policySnapshot` run jika tersedia;
  - untuk run lama yang belum punya snapshot, controller fallback ke `PayrollMonthlySettingsService::snapshotForPeriod()` tenant aktif;
  - response memakai policy error eksplisit `PAYROLL_DISBURSE_BEFORE_PAYDAY_FORBIDDEN` agar UI menampilkan pesan bisnis tanpa parsing tambahan.
- Dampak bisnis: SOP payday tenant sekarang enforceable di server-side, bukan hanya di form/settings.
- Follow-up tersisa: jika mode override finance dibutuhkan di masa depan, tetap wajib menyimpan reason + actor + timestamp.
- Status: **CLOSED (BLOCKER MVP, 2026-04-26)**.

### Gap 3 — Matrix test cutoff/payday belum lengkap

- Kondisi saat ini: regression matrix inti cutoff/payday sudah mencakup jalur backend dan UI yang paling riskan untuk runtime monthly payroll.
- Implementasi final:
  - backend mengunci resolve snapshot untuk same-month cutoff/payday, short month, dan leap year lewat `HcmPayrollPeriodApiTest`;
  - post-cutoff variable exclusion tetap dijaga oleh `PayrollOvertimeRuleIntegrationTest`;
  - before-payday reject tetap dijaga oleh `HcmPayrollRunApiTest`;
  - UI wiring kini menguji preview state `Pre-cutoff` vs `Post-cutoff` dan error toast saat disburse ditolak policy before-payday.
- Dampak bisnis: baseline regresi untuk kalender pendek dan surface operasional utama sekarang terdeteksi sebelum sampai ke tenant production.
- Evidence close: PHPUnit `HcmPayrollPeriodApiTest` pass (21 tests / 164 assertions), Vitest `tests/ui/payroll-run.wiring.test.js` pass (9 tests), dan local gate hijau setelah perubahan.
- Status: **CLOSED (BLOCKER MVP, 2026-04-26)**.

### Gap 4 — Operasional exception policy belum terkunci

- Kondisi saat ini: kontrak operasional exception untuk cutoff/payday MVP sudah dikunci dan diselaraskan dengan runtime yang aktif.
- Keputusan final:
  - payroll monthly memakai **hard-block murni** untuk before-payday bila `disburseBeforePaydayAllowed=false`;
  - tidak ada override inline pada endpoint disburse atau modal UI;
  - exception tenant-level dilakukan lewat perubahan policy yang disetujui, lalu rebuild snapshot run (`recalculate` untuk draft, atau `void + calculate draft ulang` untuk run finalized yang belum paid).
- Playbook minimum:
  - catat reason bisnis, approver, actor, nilai policy lama/baru, periode/run terdampak, dan timestamp pada SOP internal tenant;
  - regenerate export reconciliation setelah snapshot baru terbentuk sebelum disburse;
  - jangan gunakan run lama yang snapshot-nya masih memuat policy lama.
- Dampak bisnis: interpretasi HR, payroll admin, dan finance sekarang konsisten dengan perilaku server-side yang benar-benar berjalan.
- Status: **CLOSED (BLOCKER MVP, 2026-04-26)**.

## Definisi Selesai (DoD) MVP Cutoff/Payday

Fitur dianggap selesai untuk production bila seluruh kriteria berikut terpenuhi sekaligus:

1. Engine line-item monthly benar-benar cutoff-scoped memakai snapshot run.
2. Endpoint disburse menegakkan payday guard sesuai flag tenant.
3. Matrix test backend + UI untuk cutoff/payday lulus di local gate.
4. OpenAPI + dokumen API + tracker feature sudah sinkron dengan perilaku runtime.
5. Tracker menyatakan seluruh blocker MVP di atas berstatus closed dengan evidence test dan kontrak operasional final.

## Keputusan Final dan Pending Lanjutan

1. **PENDING**: cutoff dihitung dengan hari kalender atau hari kerja masih perlu keputusan final tenant-level.
2. **FINAL (runtime aktif)**: policy payday saat tanggal gajian jatuh pada hari libur nasional / tanggal merah / hari non-kerja tenant kini dipilih per tenant melalui strategy eksplisit:
  - `previous_working_day`: gaji dimajukan ke hari kerja terakhir sebelum libur. Ini umum dipakai untuk perusahaan yang ingin dana sudah diterima sebelum masa libur dimulai.
  - `next_working_day`: gaji tetap mengikuti kalender target, tetapi transfer dieksekusi pada hari kerja pertama setelah libur selesai. Ini umum dipakai bila cash disbursement mengikuti operasional bank/finance di hari kerja.
  - `exact_calendar_day`: tanggal payday tetap dianggap sah walau jatuh di hari libur, dan operasional perusahaan menerima bahwa dana efektif diproses mengikuti mekanisme bank/provider. Opsi ini hanya aman bila provider pembayaran memang mendukung perilaku tersebut.
  - bila tenant punya kebijakan berbeda untuk hari Sabtu/Minggu vs hari libur nasional, sistem perlu memutuskan apakah keduanya memakai strategy yang sama atau dipisah.
  - keputusan ini sebaiknya menjadi policy tenant-level eksplisit, bukan asumsi global, karena praktik perusahaan memang bervariasi.
  - runtime aktif memakai field strategy khusus `payroll.monthly.payday_holiday_strategy`, sehingga resolve `resolvedPaydayDate` dapat diaudit melalui `policySnapshot` run monthly.
3. **FINAL (runtime aktif)**: disburse sebelum payday untuk MVP **benar-benar diblok dan tidak boleh dilanjutkan** bila policy tenant melarang early disburse; tidak ada override inline, tidak ada bypass manual di flow runtime, karena terlalu berbahaya bila payroll dibayar sebelum masa tenggat/penguncian operasional benar-benar aman dari kelalaian HC/finance.
4. **FINAL (kontrak bisnis)**: reimbursement/variabel lain yang diinput setelah cutoff memakai **kombinasi `effective_date` + `posted_at`**. `effective_date` menentukan kepemilikan periode secara bisnis, sedangkan `posted_at` menjaga audit trail keterlambatan input dan dasar pemindahan item pasca-cutoff ke periode berikutnya.
5. **FINAL (runtime aktif)**: snapshot policy disimpan di **metadata JSON pada `hcm_payroll_runs`**, bukan kolom dedicated, agar struktur snapshot fleksibel, audit-friendly, dan lebih aman terhadap perubahan policy di masa depan.
6. **FINAL (kontrak operasional)**: admin **boleh** menjalankan `Calculate Draft` manual setelah cutoff untuk review/check data, tetapi hasilnya tidak boleh dipakai untuk menjalankan payroll lebih awal atau membypass tenggat operasional/payment guard yang sudah dikunci.
7. **FINAL (sebagian runtime aktif)**: auto-refresh **harus berhenti saat cutoff lewat** untuk menghindari drift/salah sinkron pada payroll berjalan. Aktivitas yang masuk setelah cutoff tetap disimpan di penampung/history sementara, lalu otomatis dimigrasikan ke periode berikutnya setelah pembayaran bulan berjalan selesai.

## Gap Implementasi Lanjutan (Pasca Decision Lock)

Bagian ini merinci gap yang tersisa setelah keputusan bisnis dikunci. Gap di bawah ini **bukan blocker MVP CP-01 s.d. CP-04**, tetapi penting untuk menyempurnakan operasional production.

1. **GAP-OPS-01 — Late-arrival buffer & auto-migration engine (CLOSED, 2026-04-26)**
  - Keputusan bisnis point 4 & 7 kini sudah aktif penuh di runtime monthly:
    - draft builder menyimpan backlog post-cutoff pada `hcm_payroll_runs.meta.lateArrivalBuffer`;
    - saat disburse monthly menuntaskan seluruh user eligible, sistem memicu auto-migration engine untuk queue + migrate backlog ke periode berikutnya;
    - draft periode berikutnya direbuild otomatis dan overtime post-cutoff dari source run yang dimigrasi masuk sebagai carryover line yang dapat diaudit.
  - Source yang tercakup pada closure ini:
    - overtime approved dengan `work_date` setelah `draftDataAsOfDate` sampai akhir bulan source period;
    - payroll item assignment post-cutoff tetap tercatat di buffer sebagai evidence backlog (tanpa dimasukkan ke run periode source).
  - Audit trail migrasi:
    - source run menyimpan `lateArrivalBuffer.migration` (`status`, target period, target run, timestamp);
    - response disburse memuat ringkasan `lateArrivalMigration` untuk observabilitas operasional.
  - Evidence close:
    - PHPUnit `HcmPayrollRunApiTest::test_paid_monthly_run_auto_migrates_late_arrival_buffer_into_next_period_draft` pass (21 assertions);
    - regresi terkait tetap hijau (`HcmPayrollRunApiTest`, `HcmPayrollPeriodApiTest`).

2. **GAP-OPS-02 — Guardrail “Calculate Draft review-only” pasca-cutoff (CLOSED, 2026-04-26)**
  - Kontrak operasional point 6 kini sudah dipertegas pada flow runtime UI: mode post-cutoff review-only menonaktifkan jalur export reconciliation untuk payment dan memblok pembukaan modal disburse sebelum tenggat payday sesuai policy snapshot run.
  - Evidence close: update `frontend/resources/ts/payroll-run.ts` (hint + disable action + guard modal/disburse path) dan regresi `backend/tests/ui/payroll-run.wiring.test.js` test `enforces post-cutoff review-only guardrail on export and disburse actions` lulus.

3. **GAP-OPS-03 — Payday holiday strategy tenant-level (CLOSED, 2026-04-26)**
  - Kontrak runtime kini aktif untuk strategy `previous_working_day` / `next_working_day` / `exact_calendar_day`.
  - Key setting `payroll.monthly.payday_holiday_strategy` sudah menjadi bagian API settings payroll bulanan + UI payroll-run.
  - `policySnapshot` run monthly kini menyimpan `paydayHolidayStrategy`, dan resolver payday/cutoff menghormati strategy terhadap weekend + holiday calendar tenant.
  - Evidence close: PHPUnit `HcmPayrollSettingsApiTest` dan `HcmPayrollPeriodApiTest` lulus (termasuk edge case weekend + holiday), Vitest `tests/ui/payroll-run.wiring.test.js` lulus, serta API docs/OpenAPI sinkron.

4. **GAP-OPS-04 — Regression coverage untuk kontrak baru pasca-cutoff (CLOSED, 2026-04-26)**
  - Suite dedicated sekarang sudah tersedia untuk memisahkan evidence post-cutoff backlog + auto-migrasi dari suite payroll umum.
  - Evidence close backend: `PayrollLateArrivalMigrationRegressionTest` menutup kontrak berikut secara eksplisit:
    - overtime post-cutoff tetap tertangkap di `lateArrivalBuffer` dan tidak masuk line periode source;
    - disburse monthly memicu migrasi ke periode berikutnya dan membuat carryover line di draft target period;
    - metadata migrasi (`lateArrivalBuffer.migration`) tetap terlihat pada detail/history run untuk audit operasional.
  - Evidence close UI: wiring test dedicated `payroll-run-late-arrival.wiring.test.js` memastikan payload `lateArrivalMigration` tetap terlihat di feedback operator setelah disburse gateway berhasil.

## Status

- Status: **implemented / MVP blocker contract closed**
- Tujuan dokumen: menjadi acuan runtime dan governance agar keputusan bisnis, risiko teknis, dampak lintas modul, dan playbook operasional tetap konsisten setelah implementasi selesai.