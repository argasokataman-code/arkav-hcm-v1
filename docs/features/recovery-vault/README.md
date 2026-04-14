# Recovery Vault

Recovery Vault adalah fitur SaaS internal untuk mencatat seluruh perubahan data penting sebagai jejak audit immutable, lalu menyediakan jalur restore yang terkontrol ketika terjadi insiden besar di database utama.

Tujuan fitur ini bukan menggantikan backup penuh database, tetapi memberi lapisan tambahan untuk:

- tracking CRUD lintas fitur secara rapi,
- forensik perubahan data,
- restore granular ketika ada data hilang atau salah update,
- dan retention terukur supaya data lama tidak menumpuk tanpa batas.

---

## Documentation Structure

1. [README.md](README.md)
Ringkasan produk, scope, dan alasan fitur ini diperlukan.

2. [IMPLEMENTATION.md](IMPLEMENTATION.md)
Arsitektur teknis, model data, alur ingest event, restore flow, retention, dan hardening keamanan.

3. [API-CONTRACT.md](API-CONTRACT.md)
Draft kontrak endpoint internal/admin, payload, error code, dan idempotency.

4. [E2E-TESTING.md](E2E-TESTING.md)
Skenario validasi manual untuk service-only access, audit write, snapshot, dan restore.

---

## Problem Statement

Saat database utama kena reset, migrasi destruktif, atau salah operasi, recovery manual jadi mahal dan rawan gagal. Recovery Vault dibuat untuk menutup gap itu dengan dua lapis perlindungan:

- audit log CRUD yang immutable dan mudah dicari,
- snapshot restore yang bisa dipakai untuk bencana besar atau rollback terarah.

---

## Scope

### In scope

- Catat create/update/delete untuk entity penting.
- Simpan before/after payload, actor, company, request metadata, dan correlation id.
- Sediakan endpoint internal untuk ingest event dari service/backend.
- Sediakan snapshot terjadwal dan restore terkontrol.
- Retention hot data 90 hari.
- Akses baca/restore hanya untuk service account dan super admin.

### Out of scope

- Replacing MySQL native binary log.
- End-user UI publik untuk browsing audit.
- Open restore oleh non-admin.
- Rekonstruksi data tanpa sumber truth yang valid.

---

## Recommended Product Name

**Recovery Vault**

Kenapa nama ini cocok:

- terasa SaaS-grade,
- tidak terlalu teknis seperti “binlog service”,
- mencakup audit, snapshot, dan restore,
- lebih mudah dipakai lintas tim ketika bicara incident response.

---

## Primary Users

- Super admin
- Backend/service account
- Ops/engineering team saat investigasi incident

---

## Functional Outcome

Jika fitur ini aktif:

- setiap CRUD penting punya jejak permanen,
- incident bisa ditelusuri per user, per company, per tabel, per request,
- restore bisa dilakukan dari snapshot terdekat,
- dan data audit lama otomatis dibersihkan atau di-archive sesuai retention policy.

---

## Related Existing Modules

- [Reporting System](../reporting/README.md) - snapshot concept yang sudah ada.
- [Super Admin Dashboard](../super-admin-dashboard/README.md) - tempat menampilkan ringkasan audit/recovery.
- [User Management](../user-management/README.md) - contoh audit flow dan permission backend.

---

## Notes

Fitur ini sebaiknya diposisikan sebagai core platform capability untuk SaaS, bukan fitur per modul. Artinya implementasi awal bisa kecil, tetapi kontrak datanya harus stabil sejak awal.