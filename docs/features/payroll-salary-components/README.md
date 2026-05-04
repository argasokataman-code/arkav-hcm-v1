# Registri Komponen Gaji (Salary Component Registry)

> **Status arsitektur:** Refactored — Governance-driven, read-only catalog for system components.
> Dokumen ini mencerminkan desain target post-refactor 2026-05.

## Ringkasan

`hcm_salary_components` adalah **registri terpusat** semua komponen gaji yang berlaku di seluruh modul payroll: slip gaji, lembur, THR, BPJS, PPh21, dan kompensasi PKWT. Registri ini bukan form isian bebas — ia terbagi menjadi dua kelompok:

| Kelompok | Siapa yang mendaftarkan | Boleh diedit/dihapus HR Admin? |
|---|---|---|
| **System-locked** (`is_system_locked=1`) | Modul governance secara otomatis saat kebijakan diaktifkan | **Tidak** — dikunci server-side |
| **Tenant-custom** (`is_system_locked=0`) | HR Admin via UI atau API | Ya, dapat dikelola bebas |

Komponen system-locked memiliki kode dan makna semantik yang diandalkan oleh payroll engine (mis. `upah_pokok`, `pph21_ter`, `upah_lembur`). Mengubah atau menghapusnya akan menyebabkan kalkulasi slip gaji rusak.

## Peta Sumber Komponen (source_module)

Setiap komponen kini memiliki kolom `source_module` yang menjelaskan dari mana komponen itu berasal:

| `source_module` | Governance module | Contoh komponen |
|---|---|---|
| `bpjs` | BPJS Governance (`/bpjs-governance`) | iuran_bpjs_kes_pk, iuran_jht_pekerja, premi_jkk_pk |
| `allowance` | Employee Allowance Governance (`/employee-allowance-governance`) | allowance_transport, allowance_meal, allowance_position |
| `pph21` | Tax Governance PPh21 (`/tax-governance`) | pph21_ter, pph21_rekonsiliasi |
| `overtime` | Overtime module | upah_lembur |
| `thr` | THR module | thr |
| `pkwt` | Payroll PKWT Compensation | kompensasi_pkwt |
| `null` | Tenant-custom — dibuat manual HR Admin | reimbursement_pulsa, potongan_serikat_karyawan |

## Alur Bisnis End-to-End

### Skenario A — Tenant baru onboard

1. Saat HR Admin mengaktifkan kebijakan **BPJS Governance** pertama kali, controller memanggil `ensureComponent()` untuk semua 8 komponen BPJS dengan `source_module = 'bpjs'`.
2. Saat HR Admin mengaktifkan kebijakan **Allowance Governance** pertama kali, controller mendaftarkan komponen tunjangan/insentif dengan `source_module = 'allowance'`.
3. Saat HR Admin mengaktifkan kebijakan **Tax Governance (PPh21)**, komponen `pph21_ter` dan `pph21_rekonsiliasi` didaftarkan dengan `source_module = 'pph21'`.
4. Komponen `upah_lembur`, `thr`, `kompensasi_pkwt` didaftarkan oleh modul overtime/THR/PKWT masing-masing.
5. Setelah governance aktif, HR Admin membuka **Registri Komponen Gaji** (`/salary-component-master`) dan melihat katalog komponen governance + tenant-custom.
6. HR Admin dapat menambah komponen tenant-custom (misal: `reimbursement_pulsa`, `potongan_koperasi_xyz`) via tombol tambah.

### Skenario B — Kelola komponen tenant-custom

1. HR Admin membuka `/salary-component-master`.
2. Komponen system-locked ditampilkan dengan badge warna (BPJS, Allowance, PPh21, dll) dan **tombol edit/hapus disembunyikan** untuk komponen ini.
3. Komponen tenant-custom menampilkan tombol edit dan hapus normal.
4. Admin menambah komponen baru → komponen disimpan dengan `is_system_locked = false`, `source_module = null`.
5. Admin mengedit/menghapus komponen tenant-custom → diperbolehkan.

### Skenario C — Payroll item mengacu ke registry

1. Admin membuka `/payroll` untuk membuat payroll item.
2. Dropdown komponen menampilkan daftar dari `GET /v1/hcm/salary-components?isActive=1`.
3. Payroll item menggunakan `hcm_salary_component_id` / `hcm_salary_component_uuid` sebagai FK ke registri.
4. Saat run payroll, engine memakai kode dan flag (mis. `include_pph21_ter_gross`) dari registri untuk kalkulasi.

## Lifecycle Komponen

| Status | Keterangan |
|---|---|
| `is_active = true` | Muncul di dropdown; dapat dipilih untuk payroll item baru |
| `is_active = false` | Disembunyikan dari dropdown; data historis tetap terbaca |
| `is_system_locked = true` | Hanya governance module yang boleh mendaftarkan; HR Admin tidak dapat ubah/hapus |
| `is_system_locked = false` | Dapat dikelola bebas oleh HR Admin |

**Catatan lifecycle:**
- Komponen sistem tidak dapat dihapus via API — controller mengembalikan `403` dengan pesan arah ke governance module.
- Komponen tenant-custom yang sudah dipakai di payroll item aktif sebaiknya hanya di-set `is_active = false`, bukan dihapus, agar histori slip tidak pecah.
- Jika kebijakan governance di-deactivate, komponen yang bersumber dari governance **tidak otomatis dihapus** — hanya `is_active` yang bisa di-set ke false secara manual oleh admin jika memang tidak dipakai.

## Role & Permission

| Aksi | Permission yang diperlukan |
|---|---|
| Lihat daftar komponen | `payroll.view` |
| Tambah/edit komponen tenant-custom | `payroll.manage` |
| Edit/hapus komponen system-locked | **Diblokir server-side — 403** |
| Kelola kategori | `payroll.manage` |

Akses halaman web: route `/salary-component-master` dilindungi oleh middleware `hcm.web.admin`.

## Struktur Data Inti

### `hcm_salary_components`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `code` | varchar(64) UNIQUE | Kode semantik; diperlukan oleh payroll engine |
| `kind` | `addition` / `deduction` | Jenis komponen |
| `category` | varchar(64) | Kategori dari `hcm_salary_component_categories.code` |
| `is_system_locked` | boolean | True = dikunci governance; False = tenant-custom |
| `source_module` | varchar(32) nullable | Modul governance yang mendaftarkan komponen ini |
| `include_pph21_ter_gross` | boolean | Flag dipakai payroll engine untuk PPh21 TER |
| `include_bpjs_health_wage_base` | boolean | Flag dasar upah BPJS Kesehatan |
| `include_bpjs_tk_wage_base` | boolean | Flag dasar upah BPJS TK |
| `include_thr_calculation_base` | boolean | Flag komponen masuk ke basis THR |
| `tax_treatment_code` | varchar(50) | Kode perlakuan pajak |
| `affects_net_pay` | boolean | Apakah komponen mempengaruhi take-home pay |
| `employer_cost_line` | boolean | Hanya informatif (beban perusahaan, tidak ke net pay) |

### `hcm_salary_component_categories`

Katalog 27 kategori sistem. Kategori sistem (`is_system = true`) tidak dapat dihapus via CRUD normal. Tenant dapat menambah kategori custom (`is_system = false`).

## Anomali yang Terdeteksi (per DB snapshot 2026-05)

| # | Anomali | Tabel | Severity | Status |
|---|---|---|---|---|
| 1 | 1 `hcm_payroll_items` dengan `hcm_salary_component_id IS NULL` | `hcm_payroll_items` | LOW | Open — test data, tidak ada FK error |
| 2 | Column `source_module` belum ada | `hcm_salary_components` | MEDIUM | **Fixed oleh migrasi ini** |
| 3 | `is_system_locked` tidak enforce server-side (UI boleh delete) | controller | HIGH | **Fixed oleh refactor ini** |

## Integrasi Antar Modul

| Modul | Cara integrasi | API |
|---|---|---|
| Payroll Items (`/payroll`) | Dropdown komponen via FK | `GET /v1/hcm/salary-components?isActive=1` |
| BPJS Governance | Auto-register komponen BPJS saat policy aktif | Internal `ensureComponent()` |
| Allowance Governance | Auto-register komponen tunjangan saat policy aktif | Internal `ensureComponent()` |
| Tax Governance (PPh21) | Auto-register `pph21_ter`, `pph21_rekonsiliasi` | Internal `ensureComponent()` |
| Overtime | Resolve `upah_lembur` via `resolveForOvertimePay()` | Internal |
| THR | Reference ke komponen `thr` | FK `hcm_salary_component_id` |
| PKWT Compensation | Reference ke `kompensasi_pkwt` | FK |
| Employee Salary | Tidak mengonsumsi master ini langsung; punya `baseSalary` sendiri | — |

## Tab Profil Integrasi Karyawan

Halaman `/salary-component-master` sekarang punya tab kedua **Profil Integrasi Karyawan** untuk audit integrasi employee-level:

1. Menampilkan identitas inti karyawan (nama, email, phone, departemen, jabatan, base salary).
2. Menandai `hasCleanIdentity` + `identityGaps` agar terlihat jelas data profil mana yang belum siap dipakai payroll.
3. Menampilkan ringkasan assignment aktif per karyawan (jumlah allowance assignment, `sourceModuleCounts`, dan daftar `componentCodes`).
4. Menampilkan matrix integrasi lintas modul governance per karyawan:
	- PPh21 Governance (policy tenant aktif + profil pajak employee),
	- BPJS Governance (policy tenant lengkap + membership employee),
	- Allowance Governance (policy allowance aktif + assignment employee),
	- Payroll Assignment aktif.
5. Menampilkan `integrationSummary.gaps[]` agar gap langsung actionable (contoh: `pph21Profile`, `bpjsMembership`, `allowanceAssignment`).
6. Memberikan status integrasi `ready` / `partial` / `missing` berdasarkan kebersihan identitas + seluruh check integrasi lintas modul.

Data tab ini diambil dari endpoint runtime:

- `GET /v1/hcm/salary-components/employee-profiles`
- response berisi `data.rows[]` + `meta.statusSummary` untuk kartu ringkasan di UI.

## Decision Matrix

| Situasi | Tindakan yang Benar |
|---|---|
| Komponen statutory (BPJS, PPh21) perlu ada | Aktifkan governance module terkait — komponen auto-registered |
| Tenant butuh tunjangan kustom | Tambah via UI Registri Komponen Gaji (tenant-custom) |
| Komponen system-locked perlu dikonfigurasi ulang | Ubah melalui governance module yang bersangkutan |
| Komponen lama tidak dipakai | Set `is_active = false` (bukan delete) agar histori slip tetap valid |
| Butuh komponen baru yang belum ada di governance | Tambah sebagai tenant-custom; jika sering dipakai, usulkan ke governance module |

## Kontrak API

- Dokumen utama: `docs/api/hcm-salary-components-api.md`.
- OpenAPI: `docs/api/openapi.yaml` pada tag `Payroll`.
- Identifier aktif untuk route ini saat ini tetap numerik pada path resource, sesuai kontrak runtime yang berjalan.

## Existing Vs Target

- Existing: `/salary-component-master` masih merupakan halaman runtime aktif dan menjadi sumber CRUD master komponen.
- Existing: `/payroll` hanya mengelola katalog payroll item dan linking ke master, bukan pengganti halaman master komponen.
- Target: master komponen tetap menjadi lapisan standardisasi istilah dan legal metadata, sedangkan layar payroll item fokus ke item yang benar-benar dipakai dalam draft/slip.

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah aktif

- `/salary-component-master` tetap menjadi source of truth CRUD untuk master komponen gaji;
- `/salary-component-master` juga menjadi source of truth CRUD untuk master kategori komponen;
- consumer seperti `/payroll`, `/payroll-deduction`, dan overtime sudah memakai master ini sebagai referensi linking atau resolver komponen;
- linking baru hanya memakai master aktif, sehingga layar turunan tidak menempel ke komponen yang sudah di-nonaktifkan;
- kategori tidak lagi dipaksa oleh daftar hardcoded permanen selama tabel master kategori tersedia;
- UI sekarang menegaskan bahwa koreksi komponen untuk payroll reguler harus lewat void + recalculation selama run belum paid, bukan dengan menganggap run paid masih bisa dibatalkan;
- pemisahan tanggung jawab antara master komponen vs payroll item operasional sekarang sudah tertulis jelas di dokumentasi feature.

### Gap yang masih terbuka

- matriks kepatuhan internal yang lebih rinci per komponen masih bisa ditambah bila owner bisnis membutuhkan artefak compliance terpisah dari feature doc runtime;
- review akhir konsultan/klien tetap diperlukan untuk kebijakan payroll tenant, tetapi itu bukan gap implementasi CRUD atau integrasi teknis master component;
- decision matrix harus tetap dijaga sinkron bila katalog komponen inti payroll bertambah di masa depan.

### Keputusan kompromi sementara

- feature doc sekarang menegaskan bahwa master component siap dipakai sebagai source of truth runtime untuk komponen payroll inti dan linking lintas modul;
- compliance sign-off akhir tetap dimiliki owner bisnis/payroll policy, tetapi tidak lagi diposisikan sebagai blocker implementasi atau deploy surface master component;
- tracker feature dipakai untuk membedakan readiness runtime saat ini dari review kebijakan yang sifatnya di luar kode.

## Status

- Status implementation: **ready for deployment**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: master komponen sudah siap dipakai sebagai source of truth runtime payroll, dengan decision matrix eksplisit untuk komponen yang wajib dimasterkan dan batas jelas antara runtime readiness vs sign-off kebijakan payroll.
