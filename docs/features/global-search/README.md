# Global HRMS Search

## Ringkasan Bisnis
Global search pada header/sidebar dipakai untuk mempercepat akses halaman HRMS tanpa harus membuka menu satu per satu. User dapat memakai shortcut `Ctrl + /` untuk fokus ke kolom search.

## Alur End-to-End
1. User menekan `Ctrl + /` atau klik kolom `Search in HRMS`.
2. Sistem menjalankan quick search dengan debounce ke `GET /v1/hcm/search`.
3. Dropdown menampilkan hasil cepat (route, label, deskripsi singkat).
4. Saat menekan Enter, sistem membuka panel hasil penuh (lebih banyak item) untuk query yang sama.
5. User klik item hasil untuk berpindah ke halaman target.

## Otorisasi & Scope
- API search memakai user context aktif (bearer token + tenant context).
- Item global-only (SaaS/settings lintas tenant) hanya muncul untuk global admin.
- Item employee self-service tetap tersedia untuk user non-admin.
- Item selain self-service difilter dengan permission context tenant aktif.
- Guard final tetap di backend route middleware masing-masing halaman.

## Kontrak API
- Endpoint: `GET /v1/hcm/search`
- Query:
  - `q` (required, string, min 1, max 120)
  - `limit` (optional, int, 1..50, default 8)
- Response envelope: `{ success, data: { query, total, limit, items[] } }`

## Existing vs Target
- Existing: input search hanya elemen UI statis tanpa event handler.
- Target: shortcut + quick result + panel hasil penuh + filter RBAC server-side.

## Bukti Integrasi
- Blade input diberi atribut `data-hcm-global-search-input`.
- Script runtime: `build/js/global-search-data.js`.
- Route API baru: `GET /v1/hcm/search`.
- Dokumen API sinkron: `docs/api/openapi.yaml`, `docs/api/hcm-dashboard-api.md`.
