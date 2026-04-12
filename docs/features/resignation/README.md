# Resignation

## Ringkasan

Menu Resignation menyimpan **riwayat pengajuan resign** employee (notice date, resignation date, alasan, status, catatan).

## Akses (RBAC)

- **Halaman `/resignation`**: HCM Admin. **Server:** middleware `hcm.web.admin` redirect non-admin ke `/employee-dashboard`. **Client:** `resignation-data.js` cek `auth/me` sebelum load list (sama pola `/promotion`).
- **API**: List/create/update/delete `GET/POST/PUT/DELETE /v1/hcm/resignations` — **HCM Admin**; `GET /resignations/{id}` dan `GET /resignations/users/{userId}/resignations` — admin semua user, karyawan hanya **self**.

## UI flow (template-aligned)

- List di tabel; ikon **mata** → modal detail read-only `#arcav_resignation_detail_modal` (`GET /resignations/{id}`).
- Halaman **Employee detail**: section **Resignation** + tombol **Detail** (modal sama; script `resignation-data.js` dimuat di route `employee-details`).
- Add/Edit via modal `#arcav_resignation_modal`.
- **Department**: field disabled, otomatis dari `GET /employees/{id}` (team) saat pilih employee (add); saat edit nilai dari record.
- **Employee**: pada edit, select **disabled** (snapshot tidak berubah tanpa sengaja).
- Delete memakai `window.ArcavUi.confirmDelete` (modal global HCM).
- Error simpan: tampilkan pesan `422` dari envelope API bila ada.

## API

Lihat `docs/api/hcm-resignation-api.md`.

## Validasi penting

- `noticeDate`, `resignationDate` wajib (`YYYY-MM-DD`); `resignationDate` ≥ `noticeDate`.
- `reason` wajib, max 2000; `notes` max 2000; `department` max 150.
- `status`: `pending` | `approved` | `cancelled`.

## Test coverage (minimum)

- Admin: CRUD happy path
- Non-admin: forbidden pada list admin; self show + per-user list sesuai ownership
