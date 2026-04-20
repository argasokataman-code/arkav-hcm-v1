# Payroll — Master komponen gaji

## Ringkasan

Fitur ini menyimpan katalog master komponen gaji di `hcm_salary_components`, termasuk kode komponen, jenis penambahan/potongan, kategori, default percent, dan catatan legal internal. Master ini dipakai sebagai sumber kebenaran untuk komponen payroll yang perlu distandardkan lintas tenant, terutama saat admin ingin membuat payroll item yang tertaut ke komponen resmi.

Halaman admin yang aktif untuk CRUD master tetap berada di `/salary-component-master`. Halaman `/payroll` dan `/payroll-deduction` tidak menggantikan master ini; keduanya hanya mengonsumsi master komponen sebagai dropdown atau referensi linking.

## Akses

- Web: route `/salary-component-master` berada di middleware `hcm.web.admin`; user terautentikasi yang bukan `User::isHcmAdmin()` diarahkan ke `/employee-dashboard`.
- API: `GET/POST/PUT/DELETE /v1/hcm/salary-components` hanya bisa dipanggil HCM Admin melalui guard `EnsuresHcmAdmin`.
- Permission praktis: sumber kebenaran akses tetap kombinasi middleware web admin dan policy/controller admin, bukan hanya visibilitas menu.

## UI Aktif

- Blade aktif: `salary-component-master.blade.php`.
- JS aktif: `salary-component-master-data.js`, dimuat dari `footer-scripts.blade.php` saat route `salary-component-master`.
- Consumer aktif lain: `/payroll` dan `/payroll-deduction` mengambil daftar master aktif untuk linking dan filter, tetapi tidak menjalankan CRUD master langsung di layar yang sama.

## Flow Bisnis End-to-End

1. HCM Admin membuka `/salary-component-master` untuk melihat daftar komponen aktif dan non-aktif.
2. Admin membuat atau mengubah master komponen untuk memastikan istilah payroll yang dipakai tenant konsisten dengan kebutuhan bisnis dan aturan internal.
3. Saat tenant membangun payroll item di `/payroll`, admin dapat memilih master aktif agar payroll item mewarisi kode/nama/jenis dari master resmi.
4. Jika payroll run bulan aktif perlu dikoreksi setelah finalize tetapi sebelum pembayaran, admin melakukan `void` pada payroll run lalu menghitung draft ulang agar perubahan master komponen dipakai secara eksplisit.
5. Fitur lain seperti overtime dapat menyimpan referensi ke `hcm_salary_component_id` agar komponen yang dipakai pada slip tetap terhubung ke master yang sama.

## Lifecycle Dan Keputusan Bisnis

- Create: dipakai saat tenant membutuhkan komponen resmi baru yang akan dipakai lintas flow payroll.
- Update: dipakai saat metadata komponen perlu dibenahi tanpa membuat item payroll custom baru.
- Active vs inactive: komponen non-aktif tetap bisa dipertahankan untuk histori, tetapi tidak seharusnya muncul lagi di dropdown linking baru.
- Efek perubahan: perubahan master komponen memengaruhi draft berikutnya atau draft yang dihitung ulang; run yang sudah paid tidak boleh di-void hanya demi menyesuaikan metadata komponen.
- Delete: penghapusan harus hati-hati karena master dapat sudah direferensikan payroll item atau flow turunan lain; UI consumer tetap memakai pola konfirmasi sebelum `DELETE` dikirim.

## Decision Matrix

- Jadikan komponen sebagai **master component** bila istilah, kode, kategori, atau metadata persen harus konsisten lintas flow dan tidak boleh dibiarkan berubah per tenant secara bebas.
- Jadikan komponen sebagai **master wajib** untuk komponen sistem seperti upah pokok, tunjangan tetap, overtime pay, BPJS employee deductions, dan `pph21_ter`, karena payroll engine dan flow turunan sudah meresolusikannya dari master aktif.
- Biarkan kebutuhan tetap sebagai **payroll item kustom tenant** bila komponen hanya berlaku lokal pada tenant tertentu dan tidak menjadi sumber aturan persentase/global naming lintas modul.
- Gunakan **master active/inactive** untuk mengontrol komponen mana yang masih boleh dipakai pada linking baru, sambil tetap menjaga histori lama tetap terbaca.

## Compliance Boundary

- Repo saat ini sudah mengimplementasikan komponen deduction engine BPJS employee dan `pph21_ter` pada runtime payroll bulanan.
- Yang masih membutuhkan review konsultan/klien adalah keputusan kebijakan payroll perusahaan, bukan wiring CRUD master component atau integrasi teknis antar modul.
- Karena itu, readiness deploy feature ini ditentukan oleh kestabilan CRUD/integrasi runtime, sedangkan sign-off kepatuhan akhir tetap berada pada owner bisnis/payroll policy.

## Integrasi

- Payroll items: `/payroll` memakai `GET /v1/hcm/salary-components?isActive=1` untuk dropdown linking master aktif.
- Employee salary: `/employee-salary` tetap mengelola `baseSalary` dan `fixedAllowance` per karyawan, bukan master komponen. Detailnya ada di `docs/features/employee-salary/README.md`.
- Overtime: `overtime_requests` menyimpan `hcm_salary_component_id` untuk komponen slip upah lembur, dengan resolver `code = upah_lembur` dan fallback kategori `overtime`.
- Overtime API: `GET /v1/hcm/overtime-requests` dan `POST /v1/hcm/overtime-requests/calculate` mengembalikan tautan ke komponen terkait agar UI overtime dan payroll master tetap sinkron.

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
- consumer seperti `/payroll`, `/payroll-deduction`, dan overtime sudah memakai master ini sebagai referensi linking atau resolver komponen;
- linking baru hanya memakai master aktif, sehingga layar turunan tidak menempel ke komponen yang sudah di-nonaktifkan;
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
