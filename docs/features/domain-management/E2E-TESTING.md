# Domain Management Module - E2E UI Testing

## Objective

Memastikan alur UI Domain Management berjalan end-to-end dari sisi Super User/HCM Admin, termasuk CRUD domain dan verifikasi manual.

## Environment

- App URL: `http://localhost:8000`
- User admin: HCM admin
- User non-admin: company user
- API token endpoint aktif: `GET /api-token`

## Scenario 1 - Admin Open Domain Page

1. Login sebagai admin.
2. Buka `/saas/domains`.
3. Expected:
   - Tabel domains muncul.
   - Tombol `Add Domain` tersedia.
   - Tidak ada error JS kritis di console.

## Scenario 2 - Admin Create Domain

1. Klik `Add Domain`.
2. Isi form:
   - Company: pilih company valid
   - Domain: `hr-demo.example.com`
   - Verification Type: DNS
   - Notes opsional
3. Submit.
4. Expected:
   - Toast sukses muncul.
   - Domain baru tampil di list.
   - Status awal `pending`.
   - Domain tersimpan dalam lowercase walau input user memakai uppercase/whitespace.

## Scenario 3 - Admin View Verification Details

1. Pada domain `pending`, klik tombol verification details.
2. Expected:
   - Modal instructions tampil.
   - Isi step sesuai verification type (`dns` atau `file`).
   - Token verifikasi tampil.

## Scenario 4 - Admin Verify Domain

1. Pada domain `pending`, klik `Verify`.
2. Expected:
   - Request verify berhasil.
   - Status domain berubah ke `verified`.
   - `verifiedAt` terisi pada data API.

## Scenario 5 - Admin Edit Domain

1. Klik icon edit pada domain existing.
2. Ubah notes atau verification type.
3. Submit.
4. Expected:
   - Update sukses.
   - Data terbaru tampil di list.

## Scenario 6 - Admin Delete Domain

1. Klik icon delete pada 1 domain test.
2. Confirm delete.
3. Expected:
   - Toast delete sukses.
   - Row domain hilang dari list.

## Scenario 7 - Non-Admin Restriction

1. Login sebagai non-admin.
2. Akses endpoint mutasi domain (create/update/delete/verify).
3. Expected:
   - API menolak dengan `403 ADMIN_REQUIRED`.

## Scenario 8 - Negative: invalid host format

1. Klik `Add Domain`.
2. Isi domain dengan `https://bad.example.com/path`.
3. Expected:
   - FE menolak submit dan menampilkan error bahwa domain harus berupa host/domain valid tanpa protocol atau path.
4. Paksa request API langsung dengan payload invalid yang sama.
5. Expected:
   - API return `422` pada `domain_name`.

## Scenario 9 - Negative: verify domain non-pending

1. Pilih domain dengan status `verified`.
2. Trigger `POST /v1/saas/domains/{domain}/verify` lewat API/testing tool.
3. Expected:
   - Response tetap `200 success`.
   - Status existing tidak berubah ke nilai lain.

## Scenario 10 - Multi-company filter consistency

1. Pastikan ada domain dari minimal dua company berbeda.
2. Ganti filter company pada halaman domain.
3. Expected:
   - List hanya menampilkan row untuk `companies.id` yang dipilih.
   - Edit modal pada row hasil filter tetap bisa submit karena frontend mengirim `company.uuid`, bukan numeric id.

## UI Regression Checklist

- Table responsive di desktop/mobile.
- Status badge tampil konsisten (`pending|verified|failed`).
- Modal Add/Edit/Verification bisa dibuka-tutup stabil.
- Pagination bekerja saat data lebih dari 1 halaman.
- Select company di modal create/edit tersambung ke UUID company write contract backend.
- Toast error menampilkan pesan validation Laravel pertama saat backend menolak payload.

## Exit Criteria

Testing dinyatakan pass bila:
- Semua skenario admin pass.
- Restriksi non-admin terverifikasi.
- Tidak ada error JS kritis.
- CRUD + verify flow stabil.
