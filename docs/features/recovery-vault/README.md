# Recovery Vault

## Ringkasan

Recovery Vault adalah fitur SaaS internal untuk mencatat seluruh perubahan data penting sebagai jejak audit immutable, lalu menyediakan jalur restore yang terkontrol ketika terjadi insiden besar di database utama.

Tujuan fitur ini bukan menggantikan backup penuh database, tetapi memberi lapisan tambahan untuk:

- tracking CRUD lintas fitur secara rapi,
- forensik perubahan data,
- restore granular ketika ada data hilang atau salah update,
- dan retention terukur supaya data lama tidak menumpuk tanpa batas.

## Akses

- Akses baca dan restore dibatasi untuk service account dan super admin.
- End-user publik dan non-admin tidak boleh mengakses flow restore.

## UI Aktif

- Fokus fase ini masih pada capability internal/admin dan service ingest, bukan UI publik penuh.
- Rangkuman audit/recovery idealnya dipantau dari surface admin global.

## Flow Bisnis End-to-End

1. Service/backend mengirim event create/update/delete penting ke Recovery Vault.
2. Sistem menyimpan before/after payload, actor, company, dan metadata request secara immutable.
3. Snapshot terjadwal atau restore terkontrol dapat dipakai saat terjadi insiden data besar.
4. Super admin atau service account melakukan investigasi dan restore sesuai policy yang berlaku.

## Lifecycle Dan Keputusan Bisnis

- Fitur ini adalah platform capability, bukan modul tenant biasa.
- Audit immutable dan restore granular diprioritaskan lebih dulu dibanding UI publik browsing audit.
- Retention hot data 90 hari dipakai agar evidence tetap cukup tanpa membuat penyimpanan tidak terkendali.

## Integrasi

- Reporting: konsep snapshot dan evidence audit beririsan dengan reporting snapshot/archive. Lihat `docs/features/reporting/README.md`.
- Super Admin Dashboard: ringkasan audit/recovery idealnya menjadi bagian dari monitoring platform global. Lihat `docs/features/super-admin-dashboard/README.md`.
- User Management: actor, permission, dan audit akses restore mengikuti fondasi admin/service authorization. Lihat `docs/features/user-management/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

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

## Existing Vs Target

- Existing: dokumentasi, scope, dan rancangan capability audit/restore sudah jelas, tetapi surface ini masih dominan sebagai desain capability internal.
- Target: ingest event, snapshot, restore terkontrol, dan monitoring admin global berjalan konsisten sebagai capability platform.