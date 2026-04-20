# Termination

## Ringkasan

Menu Termination dipakai ketika perusahaan memutus hubungan kerja karyawan dan perlu memastikan keputusan internal, settlement akhir, serta clearance operasional tercatat rapi. Feature ini adalah exit-management flow yang lebih lanjut daripada resignation karena sudah menyentuh settlement, payroll context, dan asset clearance.

## Akses

- HR / HCM Admin: full access ke halaman `/termination` dan endpoint admin-only.
- Payroll / Finance admin: memakai hasil finalization sebagai input settlement/payroll dalam role admin yang sama.
- Karyawan terkait: hanya bisa melihat riwayat termination miliknya sendiri melalui endpoint ownership-based.

## UI Aktif

- Halaman utama: `/termination`.
- Surface relasi read-only juga muncul pada section termination di `/employee-details`.

## Documentation Structure

- [README.md](README.md) — business overview, flow, lifecycle, role check, gap transparency
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — technical summary for controller, UI, data snapshot, and validation
- [tracker.md](tracker.md) — latest progress snapshot, evidence, and open gaps

## Ringkasan Bisnis

Menu **Termination** dipakai ketika **perusahaan** memutus hubungan kerja karyawan. Fokus bisnisnya bukan sekadar mencatat bahwa hubungan kerja berakhir, tetapi memastikan dua hal berjalan berurutan dan bisa diaudit:

1. keputusan internal perusahaan tercatat jelas;
2. semua hak dan kewajiban akhir dipetakan sebelum settlement akhir dieksekusi.

Feature ini harus dibaca sebagai alur **exit management**. Karena itu, record Termination yang mencapai fase akhir harus membantu tim HR/payroll menjawab pertanyaan bisnis berikut:

- apakah keputusan terminasi sudah final secara internal?
- berapa hak finansial yang masih harus dibayar sampai tanggal efektif terminasi?
- adakah kompensasi khusus yang relevan, misalnya PKWT yang jatuh tempo?
- kewajiban apa yang masih outstanding, terutama pengembalian aset?
- settlement ini akan dibawa ke periode payroll mana?

## Lifecycle Dan Keputusan Bisnis

- `pending`, `approved`, `finalized`, dan `cancelled` adalah lifecycle bisnis utama.
- Finalization baru boleh dipakai sebagai snapshot settlement saat preview payroll/asset sudah cukup jelas.
- Runtime saat ini sengaja memprioritaskan transparansi settlement preview existing meski formula bisnis akhir belum lengkap.

## Aktor & Role

| Aktor | Peran bisnis | Akses existing |
|------|---------------|----------------|
| HR / HCM Admin | Membuat, mengubah, menilai, memfinalkan termination | Full access ke halaman `/termination` dan endpoint admin-only |
| Payroll / Finance admin | Menggunakan hasil finalization sebagai input settlement/payroll | Secara runtime ikut tercakup lewat role HCM Admin |
| Karyawan terkait | Bisa melihat riwayat termination miliknya sendiri dari endpoint ownership-based | Tidak punya akses ke halaman admin `/termination`; hanya self access di endpoint detail/list tertentu |

## Flow Bisnis End-to-End

### Flow utama

1. HR membuat record Termination saat ada keputusan awal bahwa perusahaan akan mengakhiri hubungan kerja karyawan.
2. Record masuk ke status `pending` selama diskusi internal, verifikasi alasan, dan validasi tanggal efektif masih berjalan.
3. Setelah keputusan internal disahkan, status digeser ke `approved`.
4. Saat perusahaan siap mengeksekusi settlement akhir, admin membuka mode finalization dan menekan `Refresh from payroll & assets`.
5. Sistem menghitung settlement preview berdasarkan runtime existing:
	- resolve periode payroll aktual terdekat;
	- hitung gaji pokok dan tunjangan tetap secara prorata sampai `terminationDate`;
	- tarik komponen payroll lain sebagai reference bila monthly payroll run untuk periode itu sudah tersedia;
	- tambahkan kompensasi PKWT bila kontrak memang due pada bulan tersebut;
	- tampilkan clearance item asset yang masih outstanding.
6. Admin mengecek hasil preview, menambahkan `clearanceNotes`, lalu menyimpan status `finalized`.
7. Jika ada asset yang belum kembali, admin dapat memicu `Mark returned` langsung dari context Termination untuk memperbarui snapshot clearance tanpa pindah ke menu Asset.
8. Record `finalized` menjadi snapshot operasional yang dipakai sebagai bahan eksekusi settlement/payroll final.

### Keputusan / percabangan

- Jika kasus masih diperdebatkan internal, record tetap `pending` dan tidak boleh diperlakukan sebagai settlement final.
- Jika keputusan terminasi sudah disahkan tetapi settlement belum siap, record masuk `approved`.
- Jika settlement preview sudah dipetakan dan kewajiban final sudah dicatat, record masuk `finalized`.
- Jika kasus dibatalkan, record masuk `cancelled` dan tidak boleh dibawa ke settlement akhir.
- Jika payroll run bulanan belum tersedia, sistem tetap memberi preview dengan policy prorata berdasarkan kompensasi aktif saat ini.
- Jika kontrak PKWT jatuh tempo pada bulan terminasi, sistem menambahkan kompensasi PKWT sebagai komponen settlement.
- Jika asset masih outstanding, finalization tetap bisa disimpan, tetapi snapshot clearance akan menunjukkan item yang belum selesai agar follow-up tetap terlihat.

## Integrasi

- Employees Organization: pemilihan employee target, department snapshot, dan membership company aktif berasal dari data employee/organization. Lihat `docs/features/employees-organization/README.md`.
- Payroll Runs dan Employee Salary: settlement preview memerlukan payroll period aktif, salary structure, dan komponen penghasilan yang berlaku. Lihat `docs/features/payroll-runs/README.md` dan `docs/features/employee-salary/README.md`.
- Asset Management dan Tickets: clearance asset dan status pengembalian barang mengandalkan data assignment asset serta konteks issue handling. Lihat `docs/features/asset-management/README.md` dan `docs/features/tickets/README.md`.
- Resignation: keduanya berada di exit lifecycle employee, tetapi resignation fokus pada notice/approval sementara termination fokus pada settlement akhir perusahaan. Lihat `docs/features/resignation/README.md`.
- Training, Performance, dan Reporting: data exit employee dapat menjadi konteks evaluasi people-ops dan pelaporan historis. Lihat `docs/features/training/README.md`, `docs/features/performance/README.md`, dan `docs/features/reporting/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Status / Lifecycle

| Status | Arti bisnis | Pemicu masuk | Dampak bisnis | Catatan runtime |
|--------|-------------|--------------|---------------|-----------------|
| `pending` | Kasus masih dibahas internal | Record baru dibuat atau belum ada keputusan final | Belum boleh dianggap dasar settlement akhir | Sudah aktif |
| `approved` | Keputusan terminasi sudah sah secara internal | HR/admin menyetujui keputusan terminasi | Menandakan keputusan final sudah ada, tetapi settlement bisa belum lengkap | Sudah aktif |
| `finalized` | Hak dan kewajiban akhir sudah dipetakan | Admin menyimpan finalization dengan `clearanceNotes` | Menjadi snapshot settlement akhir untuk payroll/clearance | Sudah aktif dengan preview prorata + PKWT + clearance asset |
| `cancelled` | Kasus dibatalkan | Admin membatalkan proses | Tidak boleh diproses sebagai exit settlement | Sudah aktif |

## E2E Bisnis

### Happy path yang didukung runtime sekarang

1. Admin membuka halaman `/termination`.
2. Admin memilih karyawan, mengisi alasan, notice date, dan termination date.
3. Record disimpan sebagai `pending`.
4. Setelah keputusan internal selesai, admin mengubah status ke `approved`.
5. Menjelang penyelesaian exit, admin mengubah status ke `finalized` dan menekan `Refresh from payroll & assets`.
6. Sistem menampilkan preview settlement dan outstanding clearance.
7. Admin menulis `clearanceNotes`, menyimpan, lalu record menjadi snapshot final yang siap dievaluasi payroll/HR.
8. Bila asset sudah kembali, admin menekan `Mark returned` pada item clearance terkait dan snapshot outstanding berkurang.

### Exception / skenario keputusan lain

- Jika termination date lebih awal dari notice date, request ditolak.
- Jika target user bukan anggota aktif company yang sedang aktif, request ditolak.
- Jika asset clearance item tidak milik karyawan termination tersebut, action return ditolak.
- Jika kasus dibatalkan sebelum final, status menjadi `cancelled`.

## Role & Permission Cross-check

### Halaman aktif

| Surface | Existing target role | Catatan |
|--------|-----------------------|---------|
| `/termination` | HCM Admin only | Middleware `hcm.web.admin`; non-admin diarahkan ke `/employee-dashboard` |
| `/employee-details` section Termination | Admin semua user, employee self pada relasi ownership-based | Read-only relation surface |

### Endpoint API existing

| Endpoint | View/Create/Mutate | Existing role behavior |
|----------|--------------------|------------------------|
| `GET /v1/hcm/terminations` | list admin | HCM Admin only |
| `POST /v1/hcm/terminations` | create | HCM Admin only |
| `PUT /v1/hcm/terminations/{id}` | update | HCM Admin only |
| `DELETE /v1/hcm/terminations/{id}` | delete | HCM Admin only |
| `GET /v1/hcm/terminations/{id}` | detail | Admin semua record; employee hanya self |
| `GET /v1/hcm/terminations/users/{userId}/terminations` | per-user list | Admin semua user; employee hanya self |
| `GET /v1/hcm/terminations/settlement-preview` | preview sebelum create | HCM Admin only |
| `GET /v1/hcm/terminations/{id}/settlement-preview` | preview existing record | HCM Admin only |
| `POST /v1/hcm/terminations/{id}/clearance-items/{assignmentId}/return` | return asset dari context Termination | HCM Admin only |

**Cross-check ke matriks HCM aktif:** lihat [docs/planning/active-hcm-templates-and-permissions.md](../../planning/active-hcm-templates-and-permissions.md) baris `/termination` dan `/employee-details`.

## Existing Vs Target

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah ada

- lifecycle `pending | approved | finalized | cancelled` sudah aktif end-to-end;
- finalization sudah bisa menarik preview settlement dari source runtime;
- settlement sudah menghitung prorata gaji pokok dan tunjangan tetap;
- kompensasi PKWT sudah ikut ditambahkan bila due pada bulan termination;
- clearance asset sudah tampil sebagai item terstruktur dan bisa di-return langsung dari feature Termination;
- snapshot final sudah menyimpan periode payroll target, breakdown settlement, dan clearance outstanding.

### Gap yang masih terbuka

- belum ada formula bisnis tambahan seperti severance, leave payout, atau custom compensation policy lain di luar prorata + PKWT;
- belum ada checklist/approval step terstruktur untuk kewajiban non-asset per item;
- settlement preview belum menggabungkan source lintas-purpose seperti THR atau run khusus lain dalam satu layar final settlement.

### Keputusan kompromi sementara

- sistem memprioritaskan transparansi existing runtime daripada menunggu policy sempurna;
- jika formula bisnis akhir belum lengkap, README tetap menjelaskan perilaku existing dan gap-nya secara eksplisit;
- snapshot final dipakai sebagai basis evaluasi bersama, bukan diklaim sebagai engine settlement final yang sudah sempurna.

## UI Existing

- List tabel tanpa dummy; modal CRUD `#arcav_termination_modal`; detail `#arcav_termination_detail_modal`.
- `Termination type` memakai input teks + `datalist` saran.
- `Department` otomatis dari team employee.
- Finalization memakai tombol `Refresh from payroll & assets`.
- Clearance item asset pada record existing bisa ditandai returned langsung dari UI.

## Status

- Status implementation: **in progress**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: runtime sudah cukup kuat untuk evaluasi flow bisnis termination secara end-to-end, tetapi policy settlement dan checklist non-asset masih belum lengkap.

## API & Technical References

- API contract: [docs/api/hcm-termination-api.md](../../api/hcm-termination-api.md)
- Technical summary: [IMPLEMENTATION.md](IMPLEMENTATION.md)

## Test & Evidence

- Backend regression: `backend/tests/Feature/TerminationApiTest.php`
- Frontend API contract: `backend/tests/ui/termination-api-contract.test.js`
- Frontend wiring: `backend/tests/ui/termination.wiring.test.js`
- Employee detail relation check: `backend/tests/ui/employee-details-training.wiring.test.js`
