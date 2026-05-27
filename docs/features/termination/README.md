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

- Runtime mempertahankan lifecycle bisnis utama `pending`, `approved`, `finalized`, dan `cancelled` untuk compatibility existing.
- Di balik lifecycle tersebut, compliance flow sekarang mulai dipisah ke workflow stage: `draft_review`, `legal_review`, `approved_internal`, `finalized_execution`, dan `cancelled`.
- Finalization baru boleh dipakai sebagai snapshot settlement saat preview payroll/asset sudah cukup jelas dan checklist kewajiban wajib yang dikirim sudah selesai.
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
2. Record masuk ke status `pending` dan workflow stage `draft_review` selama diskusi internal, verifikasi alasan, dan validasi tanggal efektif masih berjalan.
3. Saat butuh telaah HR/IR/legal, workflow stage digeser ke `legal_review` tetapi status bisnis tetap `pending` sampai keputusan internal benar-benar disahkan.
4. Setelah keputusan internal disahkan, workflow stage masuk `approved_internal` dan status bisnis turunannya menjadi `approved`.
5. Saat perusahaan siap mengeksekusi settlement akhir, admin membuka mode finalization dan menekan `Refresh from payroll & assets`.
5. Sistem menghitung settlement preview berdasarkan runtime existing:
	- resolve periode payroll aktual terdekat;
	- hitung gaji pokok secara prorata sampai `terminationDate`;
	- tarik komponen payroll lain sebagai reference bila monthly payroll run untuk periode itu sudah tersedia;
	- tambahkan kompensasi PKWT bila kontrak memang due pada bulan tersebut;
	- tampilkan clearance item asset yang masih outstanding.
6. Admin mengecek hasil preview, menambahkan `clearanceNotes`, dan bila perlu melampirkan checklist kewajiban non-asset seperti handover pekerjaan, penutupan akses, atau dokumen legal.
7. Jika checklist mandatory dikirim, semua item tersebut harus selesai sebelum workflow stage dapat disimpan ke `finalized_execution` dan status bisnis turunannya menjadi `finalized`.
8. Jika ada asset yang belum kembali, admin dapat memicu `Mark returned` langsung dari context Termination untuk memperbarui snapshot clearance tanpa pindah ke menu Asset.
9. Record `finalized` menjadi snapshot operasional yang dipakai sebagai bahan eksekusi settlement/payroll final.

### Keputusan / percabangan

- Jika kasus masih diperdebatkan internal, record tetap `pending` dan biasanya berada di `draft_review` atau `legal_review`.
- Jika keputusan terminasi sudah disahkan tetapi settlement belum siap, workflow stage masuk `approved_internal` dan status bisnis menjadi `approved`.
- Jika settlement preview sudah dipetakan dan kewajiban final sudah dicatat, workflow stage masuk `finalized_execution` dan status bisnis menjadi `finalized`.
- Jika kasus dibatalkan, record masuk `cancelled` dan tidak boleh dibawa ke settlement akhir.
- Jika payroll run bulanan belum tersedia, sistem tetap memberi preview dengan policy prorata berdasarkan kompensasi aktif saat ini.
- Jika kontrak PKWT jatuh tempo pada bulan terminasi, sistem menambahkan kompensasi PKWT sebagai komponen settlement.
- Jika asset masih outstanding, finalization tetap bisa disimpan, tetapi snapshot clearance akan menunjukkan item yang belum selesai agar follow-up tetap terlihat.
- Jika checklist non-asset mandatory dikirim tetapi masih open, finalization ditolak agar penyelesaian kewajiban tidak hanya bergantung pada catatan bebas.

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

### Workflow stage compliance

| Workflow stage | Turunan status bisnis | Arti operasional |
|----------------|-----------------------|------------------|
| `draft_review` | `pending` | Draft awal termination masih ditelaah internal |
| `legal_review` | `pending` | Case sedang direview HR/IR/legal sebelum approval internal |
| `approved_internal` | `approved` | Keputusan internal sudah sah, settlement belum dieksekusi |
| `finalized_execution` | `finalized` | Settlement snapshot dan kewajiban final sudah siap eksekusi |
| `cancelled` | `cancelled` | Case dihentikan/dibatalkan |

## E2E Bisnis

### Happy path yang didukung runtime sekarang

1. Admin membuka halaman `/termination`.
2. Admin memilih karyawan, mengisi alasan, notice date, dan termination date.
3. Record disimpan sebagai `pending`.
4. Setelah keputusan internal selesai, admin mengubah status ke `approved`.
5. Menjelang penyelesaian exit, admin mengubah workflow stage ke `finalized_execution` atau tetap memakai pilihan status legacy `finalized`, lalu menekan `Refresh from payroll & assets`.
6. Sistem menampilkan preview settlement dan outstanding clearance.
7. Admin menulis `clearanceNotes`; bila checklist kewajiban non-asset dipakai, semua item mandatory harus selesai.
8. Record menjadi snapshot final yang siap dievaluasi payroll/HR.
9. Bila asset sudah kembali, admin menekan `Mark returned` pada item clearance terkait dan snapshot outstanding berkurang.

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
| `POST /v1/hcm/terminations/{id}/checklist-items` | buat checklist item baru | HCM Admin only |
| `GET /v1/hcm/terminations/{id}/checklist-items` | list checklist items | HCM Admin only |
| `PATCH /v1/hcm/terminations/{id}/checklist-items/{itemId}` | update checklist item | HCM Admin only |
| `PATCH /v1/hcm/terminations/{id}/checklist-items/{itemId}/complete` | mark item completed | HCM Admin only |
| `DELETE /v1/hcm/terminations/{id}/checklist-items/{itemId}` | hapus checklist item (soft delete) | HCM Admin only |

**Cross-check ke matriks HCM aktif:** lihat [docs/planning/active-hcm-templates-and-permissions.md](../../planning/active-hcm-templates-and-permissions.md) baris `/termination` dan `/employee-details`.

## Existing Vs Target

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah ada

- lifecycle `pending | approved | finalized | cancelled` sudah aktif end-to-end;
- workflow stage compliance dasar `draft_review | legal_review | approved_internal | finalized_execution | cancelled` sudah aktif dengan audit actor/timestamp;
- finalization sudah bisa menarik preview settlement dari source runtime;
- settlement sudah menghitung prorata gaji pokok; tunjangan tetap legacy dari salary profile tidak lagi dipakai sebagai source settlement.
- kompensasi PKWT sudah ikut ditambahkan bila due pada bulan termination;
- clearance asset sudah tampil sebagai item terstruktur dan bisa di-return langsung dari feature Termination;
- snapshot final sudah menyimpan periode payroll target, breakdown settlement, dan clearance outstanding.
- snapshot final juga sudah bisa menyimpan checklist kewajiban non-asset bila API mengirimkannya.
- **[Slice A — 2026-05-26]** settlement calculator sekarang menghitung UP (pesangon), UPMK, UPH, dan leave payout per 7 policy profile berdasarkan `terminationReasonCode` + `legalBasisCode`. Evidence snapshot tersimpan immutable di `settlement_evidence_snapshot` (Anomaly #1 guard).
- **[Slice B — 2026-05-26]** workflow stage sekarang divalidasi strict sequential (tidak boleh skip stage). `workflow_history` JSON menyimpan full audit trail setiap transisi stage + actor. `workflow_version` menjadi optimistic lock; concurrent writes dilindungi `DB::transaction + lockForUpdate` (Anomaly #2 guard).
- **[Slice C — 2026-05-26]** 5 endpoint baru untuk manajemen checklist item per record (`POST/GET /checklist-items`, `PATCH/{itemId}`, `PATCH/{itemId}/complete`, `DELETE/{itemId}`). Finalization sekarang juga memblok jika ada mandatory item di tabel `hcm_termination_checklist_items` yang masih `open`.

### Gap yang masih terbuka

- belum ada formula bisnis tambahan seperti severance, leave payout, atau custom compensation policy lain di luar prorata + PKWT;
- UI editor khusus untuk checklist kewajiban non-asset per item belum ada, meski kontrak API dan guard finalization dasarnya sudah tersedia;
- settlement preview belum menggabungkan source lintas-purpose seperti THR atau run khusus lain dalam satu layar final settlement.
- legal taxonomy alasan PHK + dasar hukum sudah ada, tetapi mapping formula hak PHK masih belum lengkap;
- legal audit snapshot sudah mulai menyimpan formula/profile/version dan approval trail, tetapi hash lampiran evidentiary wajib belum immutable.
- **[Pending — Anomaly #8]**: PPh21 deduction untuk settlement belum dihitung via `PayrollTaxCalculationService` — menunggu service tersebut tersedia di codebase.
- **[Pending — Slice B role check]**: role-based stage transition (mis. "non-legal tidak boleh approve `legal_review`") belum diimplementasi — menunggu keputusan role taxonomy dari IR/Legal perusahaan.

### Keputusan kompromi sementara

- sistem memprioritaskan transparansi existing runtime daripada menunggu policy sempurna;
- jika formula bisnis akhir belum lengkap, README tetap menjelaskan perilaku existing dan gap-nya secara eksplisit;
- snapshot final dipakai sebagai basis evaluasi bersama, bukan diklaim sebagai engine settlement final yang sudah sempurna.

## Compliance Regulasi Indonesia (Target Hardening)

Feature Termination ditargetkan menuju proses PHK yang siap audit hubungan industrial di Indonesia. Untuk mencapai itu, implementasi perlu bergerak dari settlement preview operasional ke compliance workflow yang eksplisit.

### Target kontrol minimum

1. Alasan PHK dan dasar hukum tersimpan sebagai kode terstruktur, bukan free text semata.
2. Formula hak akhir dipisah per komponen (mis. pesangon/UPMK/UPH/kompensasi PKWT/komponen internal policy) dengan parameter dan hasil hitung yang bisa diaudit.
3. Finalization mewajibkan dokumen dan approval trail yang relevan (HR, payroll, legal/IR sesuai kebijakan perusahaan).
4. Snapshot final menyimpan metadata legal penting: formula version, actor approval, timestamp, dan referensi lampiran.
5. Seluruh perubahan kontrak API terkait compliance wajib sinkron ke docs API dan tracker.

### Catatan tata kelola

- Baseline ini membantu engineering mematangkan fitur ke arah kepatuhan regulasi Indonesia.
- Validasi terakhir atas interpretasi pasal, formula, dan wording dokumen tetap harus melalui Legal/Industrial Relations perusahaan.

## UI Existing

- List tabel tanpa dummy; modal CRUD `#arcav_termination_modal`; detail `#arcav_termination_detail_modal`.
- `Termination type` memakai input teks + `datalist` saran.
- `Department` otomatis dari team employee.
- Finalization memakai tombol `Refresh from payroll & assets`.
- Clearance item asset pada record existing bisa ditandai returned langsung dari UI.

## Status

- Status implementation: **in progress**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: runtime sudah cukup kuat untuk evaluasi flow bisnis termination secara end-to-end; fokus berikutnya adalah compliance hardening terhadap praktik ketenagakerjaan Indonesia (lihat tracker untuk backlog mandatory).

## API & Technical References

- API contract: [docs/api/hcm-termination-api.md](../../api/hcm-termination-api.md)
- Technical summary: [IMPLEMENTATION.md](IMPLEMENTATION.md)

## Test & Evidence

- Backend regression: `backend/tests/Feature/TerminationApiTest.php`
- Frontend API contract: `backend/tests/ui/termination-api-contract.test.js`
- Frontend wiring: `backend/tests/ui/termination.wiring.test.js`
- Employee detail relation check: `backend/tests/ui/employee-details-training.wiring.test.js`
