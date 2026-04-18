# UUID Migration Steps & Integration Notes

## 1. Migration Steps (Laravel)

Status pengerjaan saat ini:
- Batch 1-21: Done (incremental per 5 tabel/batch, termasuk leave workflow + company settings cluster)
- Tabel tersisa: In progress, lanjut bertahap sesuai dependency parent-child UUID

1. **Branching:**
   - Buat branch baru khusus migrasi UUID.
2. **Update Migration:**
   - Ganti semua `$table->id()` → `$table->uuid('id')->primary()`.
   - Untuk FK: `$table->uuid('xxx_id')->index()` dan constraint ke UUID.
   - Tambahkan kolom UUID baru (nullable) jika data existing.
3. **Data Migration:**
   - Isi UUID untuk semua row existing (gunakan `Str::uuid()`).
   - Update semua FK child ke UUID baru.
   - Setelah semua FK update, jadikan UUID sebagai PK, hapus kolom integer lama.
4. **Model Update:**
   - Fase transisi: pakai trait auto-generator untuk kolom `uuid` (jangan ubah PK integer secara paksa sebelum semua FK siap).
   - Fase final: baru aktifkan UUID sebagai PK utama saat semua FK sudah pindah.
   - Update relasi Eloquent agar pakai UUID.
5. **Seeder & Factory:**
   - Pastikan semua seeder/factory generate UUID, bukan integer.
6. **Codebase Update:**
   - Update semua controller, service, dan query yang akses ID/FK.
   - Audit raw SQL, pastikan tidak ada yang pakai integer ID.
7. **Testing:**
   - Jalankan migration di staging/dev.
   - Smoketest seluruh fitur utama (CRUD, relasi, login, dsb).
   - Audit data orphan, FK error, collision.
8. **Documentation:**
   - Update progress di `uuid-migration-table-list.md` dan `docs/features/uuid-migration/README.md`.

## 2. Integration Notes
- Semua FK di child table harus ikut diubah ke UUID.
- Cek migration constraint tambahan (misal: comprehensive FK constraints).
- Audit Eloquent model, seeder, factory, dan raw SQL.
- Siapkan backup dan rollback plan.
- Tandai status per tabel setiap selesai kloter: `Not started`, `In progress`, `Done`.
- Jika menemukan migration overlap historis, perlakukan sebagai non-blocking selama idempotent guard aktif; hindari menghapus file migration lama yang sudah mungkin tereksekusi di environment lain.
- Cleanup overlap dilakukan via migration lanjutan yang aman (forward-only), bukan rewrite histori migration.
- Jika migration UUID berjalan sebelum migration `Schema::create(...)` parent/child table, gunakan pola 2 tahap:
   - Tahap 1: harden migration lama agar tidak fail (`hasTable`, `hasColumn`, fallback tanpa `after(...)`).
   - Tahap 2: tambahkan migration catch-up setelah table create migration untuk backfill UUID + pasang index/FK UUID yang sempat terlewati.
- Jangan mengandalkan guard saja untuk jangka panjang; guard mencegah crash, tapi catch-up migration memastikan relasi benar-benar terbentuk.

## 4. API Standard Sync (pasca relasi)
- Saat menyesuaikan endpoint setelah perubahan relasi, pertahankan envelope API standar backend:
   - sukses: `{ success: true, data: ... }`
   - gagal: `{ success: false, error: { code, message } }`
- Pada audit 2026-04-18, endpoint settings telah dinormalisasi ke envelope ini untuk konsistensi lintas modul.

## 3. References
- Laravel Docs: [UUIDs & HasUuids](https://laravel.com/docs/10.x/eloquent#uuid-and-ulid-keys)
- Context7: [Laravel UUID Migration Best Practice]

---

**Checklist detail dan status per tabel ada di:**
- `docs/features/uuid-migration/README.md`
- `uuid-migration-table-list.md`

Jika butuh template migration atau contoh implementasi, silakan request.