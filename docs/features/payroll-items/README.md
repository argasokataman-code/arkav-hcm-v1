# Payroll Items (`/payroll`)

## Ringkasan

Fitur Payroll Items mengelola katalog `hcm_payroll_items`, yaitu item payroll yang benar-benar dipakai tenant saat menyusun payroll draft dan slip. Satu item bisa bersifat kustom penuh atau tertaut ke master komponen gaji agar kode, nama, jenis, dan kategorinya mengikuti sumber kebenaran yang lebih resmi.

Halaman ini bukan pengganti CRUD master komponen. Admin tetap mengelola kamus master di `/salary-component-master`, sedangkan `/payroll` fokus ke katalog item payroll operasional yang siap dipakai pada flow penggajian tenant.

## Akses

- Web: route `/payroll` ada di middleware `hcm.web.admin`.
- API: `GET/POST/PUT/DELETE /v1/hcm/payroll-items` dan `GET /v1/hcm/payroll-items/export` hanya untuk HCM Admin.
- Permission praktis: user harus lolos guard admin pada web dan API; menyembunyikan menu saja tidak dianggap cukup.

## UI Aktif

- Blade aktif: `payroll.blade.php`.
- Partial modal: `hcm/partials/payroll-item-modals.blade.php`.
- JS aktif: `frontend/resources/js/payroll-items-data.js` dengan build output di `backend/public/build/js/`.
- Skrip dimuat dari `footer-scripts.blade.php` saat `Route::is(['payroll'])`.

## Flow Bisnis End-to-End

1. HCM Admin membuka `/payroll` untuk melihat semua payroll item tenant.
2. Admin memilih apakah item akan dibuat sebagai item kustom atau item yang tertaut ke master komponen aktif.
3. Jika item tertaut ke master, UI mengambil daftar dari `GET /v1/hcm/salary-components?isActive=1` agar admin tidak menautkan ke komponen non-aktif.
4. Setelah item tersimpan, katalog ini dipakai lagi oleh flow payroll run, assignment, atau perhitungan lain yang membutuhkan item addition/deduction yang konsisten.
5. Jika payroll run bulan aktif sudah difinalisasi tetapi belum dibayar, koreksi item harus diikuti proses `void` pada payroll run sebelum menghitung draft ulang agar histori perubahan tidak rancu.
6. Saat dibutuhkan pelaporan, admin dapat export katalog sesuai filter jenis item.

## Lifecycle Dan Keputusan Bisnis

- Kustom: dipakai bila tenant membutuhkan item payroll yang spesifik tenant dan tidak harus masuk kamus master global.
- Taut ke master: dipakai bila tenant ingin menjaga konsistensi kode dan kategori terhadap komponen resmi.
- Addition vs deduction: dipakai untuk membedakan item penambah penghasilan dan item pengurang.
- Active master only: linking baru hanya boleh ke master aktif agar katalog payroll tidak menempel ke komponen yang sudah dihentikan.
- Efek perubahan: perubahan item payroll memengaruhi draft berikutnya atau draft yang dihitung ulang; run yang sudah paid tidak boleh diubah lewat void/cancel.
- Delete: hapus item payroll tetap perlu konfirmasi karena item bisa sudah dipakai di flow turunan atau assignment.

## Decision Matrix

- Gunakan **payroll item kustom tenant** jika komponen hanya dipakai pada tenant tertentu, tidak perlu menjadi istilah resmi lintas flow, dan cukup direferensikan oleh assignment atau draft payroll tenant itu sendiri.
- Gunakan **payroll item tertaut ke master komponen** jika tenant ingin nama, kode, jenis, dan kategori mengikuti sumber kebenaran yang juga dipakai modul lain seperti overtime atau deduction engine.
- Naikkan kebutuhan dari item kustom menjadi **master component resmi** bila komponen mulai dipakai lintas tenant, dipakai pada lebih dari satu flow payroll, atau mengandung aturan persentase/metadata yang harus konsisten.
- Tetap di level **assignment payroll item per karyawan** bila variasinya hanya pada nominal/effective date per karyawan, bukan pada definisi komponen global.

## Flow Bridge

1. Master component opsional disiapkan di `/salary-component-master` bila tenant butuh sumber istilah resmi.
2. Payroll item dibuat di `/payroll` sebagai katalog operasional yang akan dipakai tenant.
3. Item dapat di-assign ke employee lewat payroll item assignments bila nominalnya perlu berlaku spesifik per karyawan.
4. Payroll draft builder menarik assignment aktif dan katalog item untuk membentuk payroll lines final per periode.
5. Jika setup berubah setelah run `finalized` tetapi sebelum `paid`, admin melakukan `void` lalu `Calculate Draft` ulang agar histori tetap bersih.

## Integrasi

- Salary components: `/payroll` mengambil master aktif dari `/v1/hcm/salary-components`.
- Payroll runs: item di katalog ini menjadi salah satu basis pembentukan line item pada draft/slip payroll.
- Employee salary dan assignment custom: flow kompensasi per karyawan dapat mengarah ke item payroll tertentu tanpa harus mengubah master komponen global.
- Export: `GET /v1/hcm/payroll-items/export` mendukung `csv` dan `xlsx`, mengikuti filter `kind` seperti `addition` atau `deduction`.

## Kontrak API

- Dokumen utama: `docs/api/hcm-payroll-items-api.md`.
- OpenAPI: `docs/api/openapi.yaml` pada area/tag payroll terkait.
- Identifier aktif di resource path saat ini tetap numerik, mengikuti kontrak runtime yang dipakai UI dan backend.

## Existing Vs Target

- Existing: `/payroll` adalah layar katalog payroll item, bukan alias atau redirect dari master komponen.
- Existing: validasi kode item di UI dan backend sudah selaras dengan karakter `a-z`, `0-9`, `_`, dan `-`.
- Target: tenant bisa membedakan dengan jelas item payroll operasional vs master komponen resmi, tanpa duplikasi fungsi antar halaman.

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah aktif

- `/payroll` sudah terpisah jelas dari `/salary-component-master` dan berfungsi sebagai katalog payroll item operasional;
- link ke master komponen aktif sudah dibatasi ke `salary-components?isActive=1`, jadi admin tidak lagi menautkan item baru ke master non-aktif;
- validasi kode item, jenis addition/deduction, dan export format sudah selaras antara UI dan backend;
- UI sekarang menegaskan bahwa perubahan item hanya berlaku untuk draft baru / draft recalculation, dan finalized run yang belum paid harus di-void dulu bila perlu koreksi setup;
- assignment payroll item per karyawan sudah punya permukaan API sendiri dan menjadi jembatan ke flow kompensasi individual.

### Gap yang masih terbuka

- narasi bisnis lintas modul tetap perlu dijaga sinkron jika nanti flow payroll menambah modul baru, tetapi jalur inti item -> assignment -> payroll lines sekarang sudah terdokumentasi di feature ini;
- keputusan pengelompokan item kustom vs master harus tetap dijaga konsisten oleh tim operasional saat tenant menambah komponen baru.

### Keputusan kompromi sementara

- dokumentasi sekarang mengunci payroll item sebagai katalog operasional tenant yang menjembatani master component, assignment, dan payroll lines runtime;
- sisa pekerjaan diposisikan sebagai disiplin operasional menjaga decision matrix tetap konsisten, bukan blocker implementasi atau API runtime.

## Status

- Status implementation: **ready for deployment**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: payroll item sudah siap dipakai sebagai katalog operasional tenant, dengan decision matrix eksplisit untuk memilih item kustom, linked master, atau assignment per karyawan.
