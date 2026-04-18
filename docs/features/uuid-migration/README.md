# UUID Migration Summary & Integration Report

**Tanggal:** 18 April 2026

## 1. Scope & Objective
Migrasi seluruh primary key dan foreign key utama di database dari integer (auto-increment) ke UUID (Universally Unique Identifier) dengan auto-generation dan validasi anti-collision. Semua relasi antar tabel (FK) harus ikut diubah ke UUID agar integritas data tetap terjaga.

## 2. Checklist Tabel & Status

| Table | Status | Foreign Keys | Catatan |
|-------|--------|--------------|---------|
| users | Done | sessions.user_id, employee_profiles.user_id, hcm_user_roles.user_id | Kolom `uuid` aktif dan dibackfill |
| companies | Done | company_users.company_id, hcm_user_roles.company_id | Kolom `uuid` aktif dan dibackfill |
| employee_profiles | Done | user_id, company_id, manager_user_id | FK UUID aktif dan dibackfill |
| hcm_user_roles | Done | user_id, company_id, assigned_by_user_id | FK UUID aktif dan dibackfill |
| sessions | Done | user_id | `user_uuid` aktif dan dibackfill |
| company_users | Done | user_id, company_id, invited_by_user_id | UUID row + FK UUID aktif |
| Batch 2-21 | Done (incremental) | Lihat tracker batch | UUID row + FK UUID sudah dimigrasikan per batch |
| Tabel tersisa | In progress | Lihat tracker batch terbaru | Lanjut batch bertahap sesuai dependency |

- **Status**: Not started = belum migrasi, In progress = sedang migrasi, Done = sudah migrasi & terintegrasi.
- **Foreign Keys**: Daftar FK yang harus ikut diubah ke UUID.
- **Catatan**: Integrasi, risiko, atau notes khusus per tabel.

## 3. Langkah Migrasi Teknis (Best Practice Laravel)

1. **Siapkan branch khusus migrasi UUID.**
2. **Update migration:**
   - Ubah `$table->id()` menjadi `$table->uuid('id')->primary()`.
   - Untuk FK: `$table->uuid('user_id')->index()` lalu tambahkan constraint FK ke UUID.
   - Fase transisi: gunakan kolom `uuid` terpisah dulu + trait auto-generator kolom `uuid` (bukan mengubah PK langsung).
   - Fase final: setelah semua FK pindah ke UUID, baru ubah PK utama ke UUID.
3. **Generate UUID untuk data existing:**
   - Tambahkan kolom UUID baru (nullable), isi UUID untuk semua row, update FK child.
   - Setelah semua FK update, jadikan kolom UUID sebagai PK, hapus kolom integer lama.
4. **Pastikan semua seeder, factory, dan relasi model sudah pakai UUID.**
5. **Update semua query, controller, dan logic yang akses ID/FK.**
6. **Testing:**
   - Jalankan migration di staging/dev, pastikan tidak ada data orphan, FK error, atau collision.
   - Lakukan smoketest pada seluruh fitur utama (CRUD, relasi, login, dsb).
7. **Dokumentasi:**
   - Update file `docs/features/uuid-migration/README.md` dan checklist integrasi.

## 4. Validasi & Integrasi
- Semua FK di tabel child harus ikut diubah ke UUID.
- Cek constraint di migration khusus (misal: add_comprehensive_foreign_key_constraints.php).
- Lakukan audit pada Eloquent model, seeder, dan factory.
- Pastikan tidak ada query raw SQL yang masih pakai integer ID.

## 5. Catatan Risiko
- **Downtime:** Proses migrasi bisa butuh downtime jika tabel besar.
- **Data Integrity:** Wajib backup sebelum migrasi.
- **Rollback:** Siapkan skenario rollback jika ada error.

## 6. Progress Agent
- Kloter migrasi sudah berjalan sampai Batch 21 dengan pola 5 tabel per batch.
- Tracking utama ada di session list batch dan migration files `backend/database/migrations/2026_04_18_*.php`.
- Fokus berikutnya adalah menutup tabel tersisa dan menjaga sinkronisasi model, FK UUID, serta dokumentasi.

## 7. Known Non-Blocking Anomalies
- Ada migration historis yang overlap scope (contoh: `add_uuid_to_packages_table` muncul di dua file timestamp berbeda).
- Ada kloter reporting yang menyentuh tabel serupa pada batch berbeda.
- Kondisi ini saat ini non-blocking karena migration menggunakan guard `Schema::hasColumn(...)` dan helper aman untuk index/FK duplikat.
- Untuk menjaga konsistensi environment yang sudah berjalan, jangan menghapus migration historis yang sudah tercatat di tabel `migrations`; lakukan cleanup hanya lewat migration follow-up yang idempotent.
- Ditemukan mismatch urutan timestamp pada domain billing: migration UUID (`2026_04_17_*`) berjalan lebih awal daripada migration create table billing (`2026_04_21_*` sampai `2026_04_23_*`).
- Recovery sudah ditambahkan via migration forward-only:
   - `2026_04_24_000000_finalize_uuid_relations_for_billing_core_tables.php`
   - `2026_04_26_130000_fix_missing_uuid_relations_for_billing_parents.php`
- Hasil verifikasi runtime: FK UUID billing aktif kembali pada `subscriptions`, `purchase_transactions`, `invoices`, dan `payments`.

## 8. FK Relation Closure (2026-04-18)
- Ditambahkan migration hardening relasi lanjutan: `2026_04_26_140000_finalize_remaining_foreign_key_relations.php`.
- Target relasi yang ditutup mencakup domain: asset, leave, reporting, payroll, domain management, performance, dan transaksi legacy.
- Strategi migrasi:
   - Guard `hasTable` / `hasColumn` sebelum menambah FK.
   - Nullify orphan values untuk kolom nullable sebelum apply FK agar tidak gagal di data historis.
   - Penamaan FK aman terhadap batas panjang identifier MySQL.
- Hasil audit ulang information_schema setelah migration:
   - Gap kolom `*_uuid` tanpa FK tersisa: **1** kolom (`sessions.user_uuid`) — intentional non-FK.
   - Gap kolom `*_id` tanpa FK tersisa: **5** kolom, semuanya intentional/non-relational:
      - `asset_logs.reference_id`
      - `audit_logs.target_id`
      - `leave_ledger.reference_id`
      - `sessions.user_id`
      - `transactions.transaction_id`

---

**Next:** Lanjutkan ke implementasi migration, update model, dan testing sesuai checklist di atas. Jika butuh template migration UUID atau contoh HasUuids, bisa request langsung.
