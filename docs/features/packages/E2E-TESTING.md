# Packages Module - E2E UI Testing

## Tujuan

Memastikan alur UI Packages berjalan end-to-end dari sisi Super User (admin) dan company user, termasuk validasi akses, filter/search, dan operasi CRUD.

## Environment

- App URL: `http://localhost:8000`
- Admin user: role HCM admin
- Company user: role non-admin
- API token endpoint aktif: `GET /api-token`

## Skenario 1 - Admin Can Manage Packages

1. Login sebagai admin.
2. Buka `/saas/packages`.
3. Verifikasi tabel packages muncul.
4. Klik Add Package.
5. Isi form:
   - Name: `Starter Plus`
   - Price: `199000`
   - Billing cycle: `Monthly`
   - Status: active
   - Pilih minimal 2 feature chips
6. Submit.
7. Expected:
   - Toast sukses muncul.
   - Data package baru muncul di list.
   - Badge status `active`.

## Skenario 2 - Admin Search and Filter

1. Di halaman Packages admin, ketik keyword unik (misal `Starter`).
2. Tunggu debounce selesai.
3. Expected:
   - List terfilter sesuai keyword.
   - Tidak ada reload/error berulang.
4. Ubah status filter ke `Inactive`.
5. Expected:
   - Hanya package inactive yang tampil.
6. Klik Reset.
7. Expected:
   - Status kembali `All`.
   - Search input kosong.
   - List kembali default.

## Skenario 3 - Admin Edit Package

1. Klik icon edit pada package existing.
2. Ubah nama/deskripsi dan ubah pilihan fitur.
3. Jangan ubah field price/cycle bila hanya ingin update metadata.
4. Submit.
5. Expected:
   - Toast update sukses.
   - Data terbaru tampil di list.
   - Feature modal menampilkan fitur terbaru.
   - Harga tahunan existing tidak ikut berubah diam-diam bila input harga/cycle tidak disentuh.

## Skenario 4 - Admin Delete Package

1. Klik icon delete pada satu package.
2. Expected:
   - Dialog konfirmasi Arcav muncul.
3. Confirm delete.
4. Expected:
   - Toast delete sukses.
   - Data hilang dari list.

Negative checks:
- Jika komponen konfirmasi tidak tersedia, user mendapat error toast dan delete dibatalkan.
- Jika package masih punya subscription history, delete ditolak dengan error bisnis `PACKAGE_IN_USE` dan package tetap ada di list.

## Skenario 4b - Admin Can Manage Add-ons

1. Scroll ke section Package Add-ons.
2. Klik Add Add-on.
3. Isi form:
   - Code: `extra_users`
   - Name: `Extra Users`
   - Price per Unit: `25000`
   - Unit Name: `user / month`
   - Status: active
4. Submit.
5. Expected:
   - Toast sukses muncul.
   - Add-on baru muncul di list.
6. Klik edit pada add-on.
7. Ubah nama dan status.
8. Submit.
9. Expected:
   - Data di list berubah sesuai update.
10. Klik delete pada add-on.
11. Confirm delete.
12. Expected:
   - Add-on hilang dari list.

## Skenario 5 - Company User Access Behavior

1. Login sebagai company user.
2. Buka `/saas/packages`.
3. Verifikasi list package tetap bisa dilihat (read/list behavior).
4. Coba create/edit/delete package.
5. Expected:
   - Request mutasi ditolak dengan `403 ADMIN_REQUIRED`.
   - UI menampilkan pesan error.

## Skenario 6 - Session and Stability

1. Reload halaman beberapa kali.
2. Buka DevTools network.
3. Expected:
   - Tidak terjadi double request konstan dari init ganda.
   - Pagination dan filter tetap berfungsi normal.

## UI Regression Checklist

- Add-on list dapat di-manage dari section tersendiri tanpa mematahkan package flow.

## API Assertions (Optional)

- `GET /v1/saas/packages?status=all&search=starter`
- `POST /v1/saas/packages` (admin => 201)
- `POST /v1/saas/packages` (company => 403)
- `DELETE /v1/saas/packages/{packageUuid}` (company => 403)
- `DELETE /v1/saas/packages/{packageUuid}` (admin, package masih dipakai subscription => 422 `PACKAGE_IN_USE`)

## Exit Criteria

Testing dinyatakan lulus bila:
- Semua skenario admin pass.
- Pembatasan mutasi non-admin terverifikasi.
- Tidak ada error JS kritis di console.
- UI filter/search/delete konfirmasi berjalan stabil.
