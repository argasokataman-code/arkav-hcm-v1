# Team Legacy Backfill Runbook

Dokumen ini adalah prosedur operasional untuk command:

`php artisan hcm:teams-backfill-legacy`

Tujuan command: mengisi `employee_profiles.team_id` dari legacy string `employee_profiles.team`, lalu menyinkronkan assignment primer di `employee_assignments`.

## 1. Kapan dijalankan

- Sesudah migrasi fitur Team master selesai di tenant.
- Saat ditemukan banyak profil employee dengan `team` terisi tetapi `team_id` masih `NULL`.
- Setelah import legacy yang belum menggunakan `team_id`.

## 2. Opsi command

`php artisan hcm:teams-backfill-legacy [--company-id=<id>] [--team-name="<name>"] [--create-missing] [--dry-run]`

- `--company-id`: batasi hanya satu tenant/company.
- `--team-name`: batasi hanya satu nama team legacy (exact, case-insensitive setelah trim).
- `--create-missing`: otomatis buat baris `teams` jika nama team belum ada.
- `--dry-run`: simulasi, tidak ada write ke database.

## 3. Pre-check wajib

Jalankan query berikut sebelum eksekusi:

```sql
-- jumlah row legacy yang belum punya team_id
SELECT company_id, COUNT(*) AS legacy_rows
FROM employee_profiles
WHERE team_id IS NULL
  AND team IS NOT NULL
  AND TRIM(team) <> ''
GROUP BY company_id
ORDER BY company_id;
```

```sql
-- deteksi nama team legacy yang belum ada di master teams
SELECT ep.company_id, TRIM(ep.team) AS legacy_team, COUNT(*) AS rows_count
FROM employee_profiles ep
LEFT JOIN teams t
  ON t.company_id = ep.company_id
 AND LOWER(t.name) = LOWER(TRIM(ep.team))
WHERE ep.team_id IS NULL
  AND ep.team IS NOT NULL
  AND TRIM(ep.team) <> ''
  AND t.id IS NULL
GROUP BY ep.company_id, TRIM(ep.team)
ORDER BY ep.company_id, rows_count DESC;
```

## 4. Execution flow (mandatory order)

1. Jalankan dry-run global:
   `php artisan hcm:teams-backfill-legacy --dry-run`
2. Jika perlu rollout bertahap, jalankan per tenant:
   `php artisan hcm:teams-backfill-legacy --company-id=<id> --dry-run`
3. Jika ada team legacy yang belum punya master row, pilih salah satu:
   - Siapkan master team manual dulu, lalu ulangi dry-run.
   - Atau pakai `--create-missing` setelah disetujui operator.
4. Eksekusi write untuk scope yang sudah lolos dry-run.
5. Simpan output tabel command sebagai evidence operasi.

Contoh eksekusi produksi bertahap:

```bash
php artisan hcm:teams-backfill-legacy --company-id=12 --dry-run
php artisan hcm:teams-backfill-legacy --company-id=12 --create-missing
```

## 5. Post-check wajib

```sql
-- validasi sisa row legacy null team_id
SELECT company_id, COUNT(*) AS remaining_rows
FROM employee_profiles
WHERE team_id IS NULL
  AND team IS NOT NULL
  AND TRIM(team) <> ''
GROUP BY company_id
ORDER BY company_id;
```

```sql
-- validasi sinkron employee_assignments primer
SELECT ea.employee_id, ep.team_id AS profile_team_id, ea.team_id AS assignment_team_id
FROM employee_assignments ea
JOIN employee_profiles ep ON ep.id = ea.employee_id
WHERE ea.is_primary = 1
  AND (ea.end_date IS NULL OR ea.end_date >= CURRENT_DATE)
  AND COALESCE(ea.team_id, 0) <> COALESCE(ep.team_id, 0)
LIMIT 50;
```

Kriteria pass:

- `remaining_rows` sesuai target scope (ideal: 0 untuk scope yang dieksekusi).
- Query mismatch assignment tidak mengembalikan baris.

## 6. Rollback strategy

Command ini tidak menyediakan rollback otomatis. Gunakan rollback operasional berikut:

1. Ambil backup database sebelum eksekusi write.
2. Jika hasil tidak sesuai:
   - restore dari backup, atau
   - lakukan corrective script terkontrol untuk `employee_profiles.team_id` + `employee_assignments.team_id/team_name` pada scope yang terdampak.
3. Ulangi dry-run dengan scope lebih sempit (`--company-id` atau `--team-name`) sebelum write ulang.

## 7. Catatan keamanan dan kebijakan

- Jalankan dari environment trusted dengan akses tenant context yang benar.
- Hindari run global write tanpa dry-run dan approval operator.
- `--create-missing` hanya dipakai bila naming team legacy sudah tervalidasi.
- Team baru dari `--create-missing` dibuat `is_active = true`; review manual tetap diperlukan setelah eksekusi.
