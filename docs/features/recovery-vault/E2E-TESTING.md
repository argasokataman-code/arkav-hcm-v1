# Recovery Vault - E2E Testing

Dokumen ini berisi skenario manual untuk memvalidasi konsep Recovery Vault setelah implementasi.

Status saat ini: proposal / not implemented.

---

## Test Roles

- Super Admin
- Backend service account
- Non-admin user

---

## Prerequisites

- Backend service aktif.
- Recovery Vault service aktif di environment test/staging.
- Secret service token tersedia hanya di server.
- Minimal ada 1 company dan 1 super admin.
- Logging sink sudah menyimpan request id dan actor context.

---

## Scenario 1: Internal ingest only

### Goal
Pastikan event CRUD hanya bisa ditulis oleh service account.

### Steps
1. Kirim `POST /v1/internal/recovery/events` tanpa token service.
2. Kirim request dengan token user biasa.
3. Kirim request dengan service token valid.

### Expected
- Tanpa token: `401`.
- Token user biasa: `403`.
- Service token valid: `201` atau `202`.

---

## Scenario 2: CRUD event is captured

### Goal
Pastikan create/update/delete menghasilkan audit event.

### Steps
1. Buat 1 user atau update 1 employee profile.
2. Lakukan delete pada entitas test.
3. Buka audit list internal untuk super admin.

### Expected
- Event tersimpan dengan `before_payload` dan `after_payload`.
- Ada `actor_user_id`, `company_id`, `request_id`, dan `entity_type`.
- Data event immutable.

---

## Scenario 3: Super admin can read snapshots

### Goal
Pastikan hanya super admin yang boleh melihat snapshot.

### Steps
1. Login sebagai non-admin.
2. Coba buka endpoint snapshot admin.
3. Login sebagai super admin.
4. Buka endpoint snapshot admin yang sama.

### Expected
- Non-admin: `403`.
- Super admin: `200`.

---

## Scenario 4: Restore request requires approval

### Goal
Pastikan restore tidak bisa dilakukan sembarang user.

### Steps
1. Pilih snapshot valid.
2. Kirim restore request sebagai non-admin.
3. Kirim restore request sebagai super admin.
4. Cek status restore job.

### Expected
- Non-admin: `403`.
- Super admin: request tercatat.
- Restore job masuk status pending/approved sesuai policy.

---

## Scenario 5: Retention pruning

### Goal
Pastikan data audit lebih tua dari 90 hari diproses sesuai policy.

### Steps
1. Seed event test dengan timestamp lebih tua dari 90 hari.
2. Jalankan retention job.
3. Cek event lama.
4. Cek archive manifest jika dipakai.

### Expected
- Data lama tidak lagi muncul di hot table.
- Snapshot/archive penggantinya tetap ada.
- Job tercatat di audit job table.

---

## Scenario 6: Disaster restore rehearsal

### Goal
Simulasi restore bencana pada environment staging.

### Steps
1. Snapshot database staging.
2. Drop atau kosongkan satu subset data test.
3. Jalankan restore dari Recovery Vault.
4. Verifikasi record count dan login flow.

### Expected
- Data kritis kembali.
- Login super admin kembali normal.
- Tidak ada integrity error pada relasi utama.

---

## Evidence to Record

- Tanggal eksekusi.
- Environment test/staging.
- Role yang dipakai.
- Snapshot ID / restore ID.
- Hasil pass/fail.
- Catatan deviasi.

---

## Minimal Acceptance Criteria

- Internal ingest tidak bisa dipakai dari browser publik.
- Audit event tercatat untuk CRUD kritis.
- Super admin bisa baca snapshot.
- Restore hanya lewat jalur yang diotorisasi.
- Retention 90 hari berjalan sesuai jadwal.

---

## Next Step for QA

Setelah implementasi code, tambahkan test otomatis untuk:

- auth internal ingest,
- forbidden access non-admin,
- snapshot creation,
- retention pruning,
- dan restore approval flow.