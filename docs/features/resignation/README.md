# Resignation

## Ringkasan

Menu Resignation menyimpan riwayat pengajuan resign employee, termasuk notice date, resignation date, alasan, status, dan catatan. Feature ini dipakai HR untuk melacak proses pengunduran diri secara operasional sebelum masuk ke settlement akhir atau exit handling lain.

## Akses

- HR / HCM Admin: full access ke halaman `/resignation` dan endpoint admin-only.
- Karyawan terkait: hanya bisa melihat record miliknya sendiri melalui endpoint ownership-based.

## UI Aktif

- Halaman utama: `/resignation`.
- Relasi read-only juga muncul pada section resignation di `/employee-details`.

## Integrasi

- Employees Organization: pemilihan employee, department, dan membership company aktif berasal dari data organisasi karyawan. Lihat `docs/features/employees-organization/README.md`.
- Termination: resignation menjadi tahap awal exit lifecycle, sementara termination menangani settlement akhir dan clearance. Lihat `docs/features/termination/README.md`.
- Promotion: domain tetangga ini sudah mengikuti guard tenant-aware yang sama untuk `userId` UUID pada create/update. Lihat `docs/features/promotion/README.md`.
- Reporting dan Knowledgebase: data resignation dan SOP exit process dapat dipakai untuk pelaporan historis dan panduan operasional. Lihat `docs/features/reporting/README.md` dan `docs/features/knowledgebase/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Documentation Structure

- README.md — business overview, flow, role check, existing vs target, gap transparency
- docs/api/hcm-resignation-api.md — active API contract and identifier notes

## Ringkasan Bisnis

Menu Resignation menyimpan **riwayat pengajuan resign** employee (notice date, resignation date, alasan, status, catatan).

Feature ini dipakai HR untuk mencatat bahwa seorang employee sudah menyampaikan niat keluar dan perusahaan perlu melacak status prosesnya secara operasional. Fokus bisnisnya bukan settlement akhir seperti termination, tetapi memastikan ada jejak yang jelas untuk:

- kapan notice diterima;
- kapan tanggal resign efektif berlaku;
- apa alasan yang dicatat perusahaan;
- apakah kasus masih pending, sudah approved, atau dibatalkan.

## Aktor & Role

| Aktor | Peran bisnis | Akses existing |
|------|---------------|----------------|
| HR / HCM Admin | Membuat, mengubah, memantau daftar resignation | Full access ke halaman `/resignation` dan endpoint admin-only |
| Karyawan terkait | Melihat record resignation miliknya sendiri | Tidak punya akses ke halaman admin; hanya self access pada endpoint detail/history tertentu |

## Flow Bisnis End-to-End

### Flow utama

1. HR membuka halaman `/resignation`.
2. HR memilih employee target lalu sistem mengisi department dari data employee aktif.
3. HR mengisi `noticeDate`, `resignationDate`, `reason`, dan catatan bila perlu.
4. Record baru tersimpan dengan status default `pending` bila status tidak diisi eksplisit.
5. Saat keputusan internal sudah jelas, admin dapat mengubah status menjadi `approved` atau `cancelled`.
6. Employee yang bersangkutan dapat melihat record miliknya sendiri lewat endpoint ownership-based.

### Exception / skenario negatif

- Jika karyawan non-admin mencoba membuka list admin, backend mengembalikan `403`.
- Jika employee mencoba melihat resignation milik employee lain, backend mengembalikan `403`.
- Jika `resignationDate` lebih awal dari `noticeDate`, request ditolak `422`.
- Jika admin mengirim `userId` UUID yang valid tetapi user tersebut bukan anggota company aktif, request ditolak `422`.

## Snapshot Audit 2026-04-20

- **Sudah diperbaiki**: create/update sekarang konsisten menerima `userId` sebagai UUID pada body request.
- **Sudah diperbaiki**: backend sekarang menolak `userId` UUID yang valid tetapi **bukan anggota company aktif**, jadi admin tenant A tidak bisa membuat resignation untuk employee tenant B.
- **Sudah diperbaiki**: regression test backend menutup skenario cross-company UUID injection.
- **Sudah diperbaiki**: path detail/delete/history sekarang menerima UUID atau numeric legacy fallback, dan payload/list FE sudah prefer UUID bila tersedia.
- **Sudah diperbaiki**: coverage UI wiring sekarang menutup edit flow, delete failure, render error `422`, dan fallback saat detail employee gagal dimuat.
- **Sudah diperbaiki**: tautan ke `/employee-details` dari modal resignation sekarang prefer UUID employee bila tersedia.
- **Sudah diperbaiki**: modul promotion sudah memakai guard tenant-aware yang sama untuk `userId` UUID pada create/update.

## Role & Permission Cross-check

### Halaman aktif

| Surface | Existing target role | Catatan |
|--------|-----------------------|---------|
| `/resignation` | HCM Admin only | Middleware `hcm.web.admin`; client juga cek `auth/me` sebelum load list |
| `/employee-details` section Resignation | Admin semua user; employee self untuk relasi ownership | Surface read-only memakai modal detail yang sama |

### Endpoint API existing

| Endpoint | View/Create/Mutate | Existing role behavior |
|----------|--------------------|------------------------|
| `GET /v1/hcm/resignations` | list admin | HCM Admin only |
| `POST /v1/hcm/resignations` | create | HCM Admin only |
| `PUT /v1/hcm/resignations/{id}` | update | HCM Admin only |
| `DELETE /v1/hcm/resignations/{id}` | delete | HCM Admin only |
| `GET /v1/hcm/resignations/{id}` | detail | Admin semua record; employee hanya self |
| `GET /v1/hcm/resignations/users/{userId}/resignations` | per-user list | Admin semua user; employee hanya self |

## Existing Vs Target

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah aktif

- list admin resignation sudah tenant-scoped berdasarkan `company_id`;
- create/update memakai `userId` UUID pada body request;
- backend sudah memverifikasi bahwa UUID user target memang anggota company aktif;
- self access untuk detail dan history sudah enforced di backend;
- modal detail dan modal add/edit sudah aktif di `/resignation` dan reuse di `/employee-details`.

### Gap yang masih terbuka

- kontrak identifier belum UUID-only penuh: route tetap dipertahankan backward compatible dengan UUID + numeric legacy fallback selama transisi;
- promotion path parameter masih numeric legacy walaupun body `userId` untuk create/update sudah UUID.

### Keputusan kompromi sementara

- dokumentasi mengikuti kontrak runtime yang benar-benar aktif, bukan memaksa narasi UUID-only yang belum selesai di seluruh route;
- gap identifier campuran dinyatakan eksplisit agar FE/BE tidak diasumsikan sudah final;
- audit resignation mencatat gap lintas modul yang berpotensi memengaruhi user journey admin, tetapi fix domain tetangga tidak dicampur diam-diam ke feature ini.

## UI Existing

- List di tabel; ikon **mata** → modal detail read-only `#arcav_resignation_detail_modal` (`GET /resignations/{id}`).
- Halaman **Employee detail**: section **Resignation** + tombol **Detail** (modal sama; script `resignation-data.js` dimuat di route `employee-details`).
- Add/Edit via modal `#arcav_resignation_modal`.
- Create/update mengirim `userId` sebagai UUID employee; endpoint detail/history menerima UUID maupun numeric legacy fallback pada path.
- **Department**: field disabled, otomatis dari `GET /employees/{id}` (team) saat pilih employee (add); saat edit nilai dari record.
- **Employee**: pada edit, select **disabled** (snapshot tidak berubah tanpa sengaja).
- Delete memakai `window.ArcavUi.confirmDelete` (modal global HCM).
- Error simpan: tampilkan pesan `422` dari envelope API bila ada.

## Validasi Penting

- `noticeDate`, `resignationDate` wajib (`YYYY-MM-DD`); `resignationDate` ≥ `noticeDate`.
- `reason` wajib, max 2000; `notes` max 2000; `department` max 150.
- `status`: `pending` | `approved` | `cancelled`.
- `userId` untuk create/update harus UUID user yang juga menjadi anggota company aktif.

## API & Technical References

Lihat `docs/api/hcm-resignation-api.md`.

## Test & Evidence

- Backend regression: `backend/tests/Feature/ResignationApiTest.php`
- Frontend wiring: `backend/tests/ui/resignation.wiring.test.js`
- Negative scenario yang sudah diverifikasi:
	- karyawan non-admin tidak bisa membuka list admin resignation;
	- karyawan hanya bisa melihat detail resignation miliknya sendiri;
	- admin tidak bisa membuat resignation untuk employee di luar company aktif walaupun mengetahui UUID employee tersebut;
	- `resignationDate` tidak boleh lebih awal dari `noticeDate`;
	- edit flow FE mengirim route identifier UUID bila tersedia;
	- delete failure dan error `422` tampil ke user di wiring FE;
	- create flow tetap lanjut walau autofill detail employee gagal.
