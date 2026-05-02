# Feature: FAQ (/faq)

## Ringkasan

FAQ sekarang berjalan dengan persistence server-side. Halaman /faq tetap menjadi workspace manajemen FAQ untuk admin tenant, tetapi sumber data sudah dipindah dari localStorage browser ke API backend dengan isolasi tenant dan jejak audit penulis/pengubah.

## UI Aktif

- GET /faq

## API Aktif

- GET /v1/hcm/faqs
- POST /v1/hcm/faqs
- PUT /v1/hcm/faqs/{id}
- DELETE /v1/hcm/faqs/{id}
- POST /v1/hcm/faqs/bulk-delete

Semua endpoint di atas berjalan dengan middleware api.token + tenant.context.

## Flow Bisnis End-to-End

1. Admin tenant membuka /faq.
2. Frontend memanggil GET /v1/hcm/faqs menggunakan tenant context aktif.
3. User melakukan pencarian/filter/sorting pada data hasil API.
4. User menambah FAQ baru lewat modal add, frontend submit ke POST /v1/hcm/faqs.
5. User mengedit FAQ lewat modal edit, frontend submit ke PUT /v1/hcm/faqs/{id}.
6. User menghapus satu FAQ atau banyak FAQ terpilih lewat DELETE atau bulk-delete API.
7. Frontend refresh data dari API setelah aksi mutasi berhasil agar tampilan tetap sinkron lintas sesi.

## Lifecycle Data Dan Arti Bisnis

- Created: FAQ dibuat untuk tenant aktif dan disimpan dengan audit created_by/updated_by.
- Active: FAQ tampil di workspace dan dapat dicari, difilter, diedit, diekspor, serta dipilih untuk bulk delete.
- Updated: setiap perubahan menyimpan updated_by dan updated_at baru di server.
- Deleted: FAQ dihapus permanen dari tenant aktif melalui endpoint delete.
- Exported: user dapat mengekspor snapshot data aktif ke CSV/JSON dari state hasil API terbaru.

Makna bisnis: FAQ kini dapat dibagi lintas browser dalam tenant yang sama dan tidak bergantung pada state lokal perangkat.

## Keputusan Dan Percabangan

- Data source dipindah ke API server-side untuk menghilangkan ketergantungan localStorage.
- Scope data ditetapkan per company_id (tenant aktif) agar tidak ada kebocoran lintas tenant.
- Mutasi FAQ dibatasi hanya untuk HCM admin pada tenant aktif (server-enforced RBAC).
- Jejak audit minimal ditambahkan lewat kolom created_by dan updated_by.

## Arsitektur Implementasi

- View utama: backend/resources/views/content/faq.blade.php
- Modal FAQ: backend/resources/views/components/modals/cms.blade.php
- Script page: frontend/resources/js/faq-data.js
- API controller: backend/app/Http/Controllers/Api/HcmFaqController.php
- Model: backend/app/Models/Faq.php
- API routes: backend/routes/api/faq.php + aggregator backend/routes/api.php
- Migration: backend/database/migrations/2026_05_02_120000_create_faqs_table.php
- Test wiring frontend: backend/tests/ui/faq.wiring.test.js
- Kontrak API detail: docs/api/faq-api.md dan docs/api/openapi.yaml

## Akses Dan Permission

- Route web /faq tetap membutuhkan user terautentikasi.
- Endpoint FAQ API mewajibkan tenant context aktif.
- Endpoint FAQ API mewajibkan user sebagai HCM admin pada tenant aktif.
- User non-admin akan menerima 403 forbidden.

## Existing Vs Target

- Existing sebelum migrasi ini: FAQ berjalan client-only dengan localStorage per browser.
- Existing saat ini: FAQ sudah server-backed, tenant-scoped, dan memiliki audit actor minimal.
- Target berikutnya: tambah soft-delete + restore flow dan audit trail yang lebih detail (event-level history).

## Known Gaps

- Belum ada history perubahan FAQ per field (audit log detail).
- Delete masih hard delete; belum ada trash/restore server-side.
