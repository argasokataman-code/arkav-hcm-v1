# Knowledge Base (bantuan dalam aplikasi)

## Dokumentasi

- **README.md** (ini) — gambaran & navigasi
- **IMPLEMENTATION.md** — route, config, uji regresi

## Ringkasan

Halaman web **`/knowledgebase`** menampilkan pusat bantuan statis untuk pengguna yang sudah login. Konten disimpan di **`backend/config/hcm_knowledgebase.php`** (bukan data dummy di Blade). Artikel dirancang **operasional untuk admin/operator** (langkah, checklist, tabel ringkas, rujukan route dan `docs/`). Alur: indeks kategori → daftar artikel (ringkasan + estimasi menit baca) → detail artikel (HTML tepercaya dari config). Setiap kategori dapat punya `description`; setiap artikel dapat punya `reading_minutes`.

URL lama **`/knowledgebase-view?category=...`** dan **`/knowledgebase-details?article=...`** dialihkan ke route baru bila slug dikenal.

## Akses

- Pengguna yang sudah login dapat membuka pusat bantuan ini.
- Kontennya bersifat operasional internal dan tidak menjadi API publik.

## UI Aktif

- `GET /knowledgebase`
- `GET /knowledgebase/category/{slug}`
- `GET /knowledgebase/article/{slug}`

## Flow Bisnis End-to-End

1. User login lalu membuka `/knowledgebase`.
2. User mencari kategori atau artikel yang relevan.
3. Sistem menampilkan daftar artikel dengan ringkasan dan estimasi menit baca.
4. User membuka detail artikel untuk mengikuti SOP atau navigasi ke feature terkait.

## Lifecycle Dan Keputusan Bisnis

- Konten disimpan di config agar tenant-safe dan stabil untuk bantuan operasional internal.
- Route lama tetap dialihkan ke slug route baru agar navigasi existing tidak rusak.
- Knowledgebase difokuskan sebagai hub dokumentasi in-app, bukan knowledge platform publik.

## Integrasi

- Policies: artikel organisasi/SOP dapat merujuk kebijakan internal perusahaan. Lihat `docs/features/policies/README.md`.
- Locations dan Cronjob: knowledgebase memuat panduan administratif seperti sync wilayah dan pengaturan sistem. Lihat `docs/features/locations/README.md` dan `docs/features/cronjob/README.md`.
- Attendance, payroll, tickets, performance, training, packages, subscriptions, dan modul HCM/SaaS lain menjadi rujukan artikel per kategori.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Cepat

| URL | Peran |
|-----|--------|
| `GET /knowledgebase` | Indeks + pencarian `?q=` |
| `GET /knowledgebase/category/{slug}` | Artikel dalam kategori |
| `GET /knowledgebase/article/{slug}` | Isi artikel |

## Peta kategori (cakupan menu)

| Slug kategori | Isi utama |
|---------------|-----------|
| `memulai` | Login, RBAC, troubleshooting API, `/pages`, checklist onboarding admin |
| `dashboard` | `/index`, `/employee-dashboard` |
| `organisasi` | `/employees`, `/employees-grid`, `/employee-details`, departemen, jabatan, policies |
| `cuti-absensi` | Cuti karyawan/admin, pengaturan, libur |
| `absensi` | Punch GPS, jadwal, timesheet |
| `payroll` | Run bulanan, items, kompensasi, slip, THR, rekonsiliasi, payroll lembur & PKWT |
| `pengaturan-sistem` | `/cronjob`, `/users`, `/roles-permissions`, lokalisasi, prefix |
| `pelaporan-detail` | Tutorial langkah demi langkah laporan employee/absensi/slip/cuti |
| `kinerja-goals-training` | Performance, goals, training (SOP bernomor) |
| `wilayah-indonesia` | `/countries`, `/states`, `/cities` |
| `lembur-tiket` | Lembur, payroll overtime, tiket, master kategori, SOP admin |
| `admin-saas` | Paket, langganan, perusahaan, domain, pembelian, invoice & pembayaran |
| `dukungan` | Pointer laporan, promosi/mutasi, export rekonsiliasi API |

## Status

| Area | Status |
|------|--------|
| Web (Blade) | ✅ |
| API publik | — (tidak ada; konten config) |
| Tes | `KnowledgebaseWebTest` (7/7 pass) |
| Multi-tenant isolation | ✅ (config-based, tenant-safe) |
| Negative scenarios | ✅ (covered: search no results, invalid category 404, empty states) |
| UI/UX alignment | ✅ (follows active template patterns) |
| Cross-module integration | ✅ (standalone, no integration issues) |

**Latest:** 2026-04-19 - Knowledgebase audit completed. All wiring tests pass (39/39), web tests pass (7/7), multi-tenant isolation verified (static content prevents tenant leakage). Added negative scenario coverage for search no results, invalid categories, and empty states. UI follows active template patterns with consistent breadcrumb navigation and card layouts.

## Tautan

| Dokumen | Audiens |
|---------|---------|
| [IMPLEMENTATION.md](IMPLEMENTATION.md) | Developer |

## Existing Vs Target

- Existing: knowledgebase web aktif, route lama dialihkan, konten config-based tenant-safe, dan negative scenarios utama sudah ter-cover.
- Target: perluasan artikel dan cross-link yang lebih konsisten ke feature docs saat modul baru ditambahkan.
