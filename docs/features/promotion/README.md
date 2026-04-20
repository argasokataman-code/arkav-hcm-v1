# Promotion

## Ringkasan

Menu Promotion menyimpan **riwayat promosi** employee (department/designation from→to + tanggal).

## Akses

- **Halaman**: HCM Admin — **server:** middleware Laravel `hcm.web.admin` pada `GET /promotion` redirect non-admin ke `/employee-dashboard`; **client:** `promotion-data.js` cek `auth/me`, mengirim header auth + tenant context, lalu memisahkan aksi view/manage sesuai permission sebelum load list.
- **API**: HCM Admin (`/v1/hcm/promotions*`)

## UI Aktif

## UI flow (template-aligned)

- List promotion di tabel; ikon **mata** → modal detail read-only `#arcav_promotion_detail_modal` (`GET /promotions/{id}`).
- Halaman **Employee detail**: section **Promotion** + tombol **Detail** (modal sama; script `promotion-data.js` dimuat juga di route `employee-details`).
- Add/Edit via modal `#arcav_promotion_modal`.
- Department + Designation From: **disabled** (tidak bisa diedit). **Add**: otomatis dari `GET /employees/{id}` (team + designation). **Edit**: nilai tersimpan di record promosi; dropdown **Employee** ikut **disabled** agar snapshot tidak berubah tanpa sengaja.
- Designation To: dropdown master `GET /v1/hcm/designations`; nilai lama yang tidak ada di master ditambahkan sebagai opsi “(di luar master)” agar tidak hilang saat reload dropdown.
- Error simpan: tampilkan pesan validasi Laravel `422` (bukan cuma “Save failed”).
- Delete memakai modal konfirmasi global `#arcav_hcm_confirm_delete` melalui `window.ArcavUi.confirmDelete`.
- Aksi edit/delete hanya tampil saat `promotion.manage` tersedia; halaman employee-detail tetap bisa pakai modal detail tanpa CTA manajemen.

## Flow Bisnis End-to-End

1. Admin membuka halaman promotion.
2. Admin memilih employee target dan sistem menarik snapshot department/designation dari employee aktif.
3. Admin mengisi promotion date, designation tujuan, dan catatan bila perlu.
4. Record promotion disimpan dan dapat dibaca ulang dari halaman promotion maupun employee detail.

## Lifecycle Dan Keputusan Bisnis

- Snapshot from/to dijaga agar histori promosi tidak berubah saat master organization berubah.
- Employee target harus anggota company aktif agar tenant isolation tetap aman.
- Aksi view-only dan manage-action tetap dipisahkan berdasarkan permission.

## Integrasi

- Employees Organization: data team, designation, dan employee membership menjadi sumber snapshot promosi. Lihat `docs/features/employees-organization/README.md`.
- Resignation dan Termination: modul people-lifecycle ini sama-sama bergantung pada validasi `userId` tenant-aware dan employee detail relation surfaces. Lihat `docs/features/resignation/README.md` dan `docs/features/termination/README.md`.
- User Management: permission `promotion.manage` mengikuti fondasi RBAC HCM. Lihat `docs/features/user-management/README.md`.
- Reporting dan Performance: histori promosi dapat menjadi konteks pelaporan people ops dan evaluasi karier. Lihat `docs/features/reporting/README.md` dan `docs/features/performance/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## API

Lihat `docs/api/hcm-promotion-api.md`.

## Validasi penting

- `promotionDate` wajib (`YYYY-MM-DD`)
- `userId` wajib, harus UUID user yang existing, dan user target harus menjadi anggota company aktif
- `notes` max 2000, field text lain max 150

## Test coverage (minimum)

- Admin: CRUD happy path
- Non-admin: forbidden (403)
- Wiring frontend: auth/tenant headers, view-only gating, dan manage-action gating pada `promotion-data.js`

## Existing Vs Target

- Existing: modal CRUD/detail, auth + tenant headers, permission gating, dan tenant-aware `userId` validation sudah aktif.
- Target: dokumentasi test evidence dan snapshot lifecycle business context bisa diperluas setara modul resignation/termination.

