# Knowledge Base (bantuan dalam aplikasi)

## Dokumentasi

- **README.md** (ini) — gambaran & navigasi
- **IMPLEMENTATION.md** — route, config, uji regresi

## Ringkasan

Halaman web **`/knowledgebase`** menampilkan pusat bantuan statis untuk pengguna yang sudah login. Konten disimpan di **`backend/config/hcm_knowledgebase.php`** (bukan data dummy di Blade). Artikel dirancang **operasional untuk admin/operator** (langkah, checklist, tabel ringkas, rujukan route dan `docs/`). Alur: indeks kategori → daftar artikel (ringkasan + estimasi menit baca) → detail artikel (HTML tepercaya dari config). Setiap kategori dapat punya `description`; setiap artikel dapat punya `reading_minutes`.

URL lama **`/knowledgebase-view?category=...`** dan **`/knowledgebase-details?article=...`** dialihkan ke route baru bila slug dikenal.

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
| Tes | `KnowledgebaseWebTest` |

## Tautan

| Dokumen | Audiens |
|---------|---------|
| [IMPLEMENTATION.md](IMPLEMENTATION.md) | Developer |
