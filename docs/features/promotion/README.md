# Promotion

## Ringkasan

Menu Promotion menyimpan **riwayat promosi** employee (department/designation from→to + tanggal).

## Akses (RBAC)

- **Halaman**: HCM Admin — **server:** middleware Laravel `hcm.web.admin` pada `GET /promotion` redirect non-admin ke `/employee-dashboard`; **client:** `promotion-data.js` tetap cek `auth/me` sebelum load list.
- **API**: HCM Admin (`/v1/hcm/promotions*`)

## UI flow (template-aligned)

- List promotion di tabel; ikon **mata** → modal detail read-only `#arcav_promotion_detail_modal` (`GET /promotions/{id}`).
- Halaman **Employee detail**: section **Promotion** + tombol **Detail** (modal sama; script `promotion-data.js` dimuat juga di route `employee-details`).
- Add/Edit via modal `#arcav_promotion_modal`.
- Department + Designation From: **disabled** (tidak bisa diedit). **Add**: otomatis dari `GET /employees/{id}` (team + designation). **Edit**: nilai tersimpan di record promosi; dropdown **Employee** ikut **disabled** agar snapshot tidak berubah tanpa sengaja.
- Designation To: dropdown master `GET /v1/hcm/designations`; nilai lama yang tidak ada di master ditambahkan sebagai opsi “(di luar master)” agar tidak hilang saat reload dropdown.
- Error simpan: tampilkan pesan validasi Laravel `422` (bukan cuma “Save failed”).
- Delete memakai modal konfirmasi global `#arcav_hcm_confirm_delete` melalui `window.ArcavUi.confirmDelete`.

## API

Lihat `docs/api/hcm-promotion-api.md`.

## Validasi penting

- `promotionDate` wajib (`YYYY-MM-DD`)
- `userId` wajib dan harus existing
- `notes` max 2000, field text lain max 150

## Test coverage (minimum)

- Admin: CRUD happy path
- Non-admin: forbidden (403)

