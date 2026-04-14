# Recovery Vault - Implementation

## Architecture Goal

Recovery Vault menyimpan event perubahan data ke database khusus yang append-only, lalu memproses snapshot/restore dari event itu dengan kontrol akses super ketat.

Prinsip utama:

- utama tetap database aplikasi,
- audit/recovery DB hanya menerima event yang sudah divalidasi,
- semua event immutable,
- restore hanya lewat workflow service, bukan edit manual tabel audit.

---

## Suggested Components

### 1. App-side event emitter

Berada di backend utama dan bertugas menangkap CRUD penting dari service/controller/model layer.

Tugasnya:

- menyiapkan payload before/after,
- menambahkan actor context,
- menambahkan company scope,
- menambahkan request metadata,
- mengirim event ke Recovery Vault API atau outbox queue.

### 2. Recovery Vault API

Service internal yang hanya menerima request dari backend terotorisasi.

Fungsi:

- ingest audit event,
- generate snapshot,
- list snapshot,
- restore dari snapshot terverifikasi,
- manage retention archive.

### 3. Recovery Vault database

Database terpisah dari DB operasional.

Karakteristik:

- append-only table pattern,
- foreign key ringan sesuai kebutuhan,
- partition per bulan untuk event log,
- hard delete hanya oleh job retention,
- semua akses dibatasi ke service account.

### 4. Archive storage

Opsional, tetapi direkomendasikan untuk umur data lebih dari 90 hari.

Bisa berupa:

- compressed SQL dump,
- parquet/json archive,
- object storage terenkripsi.

---

## Data Model Proposal

### `audit_events`

Mencatat satu aksi CRUD atau aksi domain lain yang penting.

Kolom yang disarankan:

- `id`
- `event_uuid`
- `company_id`
- `actor_user_id`
- `actor_role`
- `entity_type`
- `entity_id`
- `action` (`created|updated|deleted|restored`)
- `before_payload` JSON
- `after_payload` JSON
- `request_id`
- `route`
- `ip_address`
- `user_agent`
- `source_service`
- `created_at`

### `audit_snapshots`

Mewakili point-in-time snapshot untuk restore cepat.

Kolom yang disarankan:

- `id`
- `snapshot_uuid`
- `company_id`
- `scope`
- `source_event_id`
- `snapshot_state`
- `checksum`
- `status` (`pending|completed|failed|archived`)
- `created_by`
- `created_at`

### `audit_restores`

Mencatat setiap restore request.

Kolom yang disarankan:

- `id`
- `restore_uuid`
- `snapshot_id`
- `requested_by`
- `requested_at`
- `approved_by`
- `approved_at`
- `status`
- `result_summary`

### `audit_retention_jobs`

Melacak job pruning/archiving.

---

## Concrete Schema Baseline

Baseline ini dipakai sebagai starting point implementasi migration pertama.

### `recovery_audit_events`

- `id` BIGINT UNSIGNED PK
- `event_uuid` CHAR(36) UNIQUE
- `idempotency_key` CHAR(36)
- `source_service` VARCHAR(100)
- `occurred_at` TIMESTAMP
- `company_id` BIGINT UNSIGNED NULL
- `actor_user_id` BIGINT UNSIGNED NULL
- `actor_role` VARCHAR(64) NULL
- `entity_type` VARCHAR(120)
- `entity_id` VARCHAR(120)
- `action` ENUM('created','updated','deleted','restored')
- `before_payload` JSON NULL
- `after_payload` JSON NULL
- `request_id` CHAR(36) NULL
- `route` VARCHAR(255) NULL
- `ip_address` VARCHAR(64) NULL
- `user_agent` VARCHAR(255) NULL
- `tags` JSON NULL
- `sensitivity` ENUM('normal','sensitive','restricted') DEFAULT 'normal'
- `created_at` TIMESTAMP

Index minimum:

- (`company_id`, `created_at`)
- (`entity_type`, `entity_id`, `created_at`)
- (`action`, `created_at`)
- (`source_service`, `idempotency_key`) UNIQUE

### `recovery_snapshots`

- `id` BIGINT UNSIGNED PK
- `snapshot_uuid` CHAR(36) UNIQUE
- `company_id` BIGINT UNSIGNED NULL
- `scope` ENUM('global','company','entity')
- `scope_ref` VARCHAR(255) NULL
- `from_event_id` BIGINT UNSIGNED NULL
- `to_event_id` BIGINT UNSIGNED NULL
- `snapshot_state` JSON
- `checksum_sha256` CHAR(64)
- `status` ENUM('pending','completed','failed','archived')
- `created_by` VARCHAR(120)
- `created_at` TIMESTAMP
- `completed_at` TIMESTAMP NULL

### `recovery_restore_jobs`

- `id` BIGINT UNSIGNED PK
- `restore_uuid` CHAR(36) UNIQUE
- `snapshot_uuid` CHAR(36)
- `target_mode` ENUM('dry_run','staging_clone','production_controlled')
- `target_ref` VARCHAR(255)
- `requested_by_user_id` BIGINT UNSIGNED NULL
- `approved_by_user_id` BIGINT UNSIGNED NULL
- `status` ENUM('pending','approved','running','completed','failed','cancelled')
- `result_summary` JSON NULL
- `requested_at` TIMESTAMP
- `approved_at` TIMESTAMP NULL
- `finished_at` TIMESTAMP NULL

### `recovery_retention_jobs`

- `id` BIGINT UNSIGNED PK
- `job_uuid` CHAR(36) UNIQUE
- `executed_at` TIMESTAMP
- `cutoff_at` TIMESTAMP
- `archived_count` INT UNSIGNED DEFAULT 0
- `purged_count` INT UNSIGNED DEFAULT 0
- `status` ENUM('running','completed','failed')
- `error_message` TEXT NULL

---

## Environment Variables (proposed)

Contoh naming agar implementasi service dan app emitter konsisten:

- `RECOVERY_VAULT_ENABLED=true`
- `RECOVERY_VAULT_INTERNAL_BASE_URL=http://recovery-vault.internal`
- `RECOVERY_VAULT_SERVICE_TOKEN=...`
- `RECOVERY_VAULT_HMAC_SECRET=...`
- `RECOVERY_VAULT_TIMEOUT_SECONDS=5`
- `RECOVERY_VAULT_RETENTION_DAYS=90`
- `RECOVERY_VAULT_ARCHIVE_ENABLED=true`
- `RECOVERY_VAULT_ARCHIVE_DISK=s3`
- `RECOVERY_VAULT_ARCHIVE_PREFIX=recovery-vault/`

---

## Scheduler and Jobs

Rekomendasi baseline scheduler:

- every 5 minutes: flush outbox event -> Recovery Vault API
- every day 00:15: create daily company snapshots
- every day 02:00: retention prune hot events older than 90 days
- every week Sunday 03:00: disaster restore rehearsal to staging clone

Job minimal:

- `RecoveryEmitOutboxJob`
- `RecoveryCreateSnapshotJob`
- `RecoveryRunRetentionJob`
- `RecoveryExecuteRestoreJob`
- `RecoveryVerifyChecksumJob`

---

## Restore Runbook (minimum)

Runbook ini wajib sebelum endpoint restore dibuka ke super admin.

1. Lock perubahan data pada scope target (maintenance guard terbatas).
2. Buat pre-restore snapshot dari target saat ini.
3. Jalankan restore dry-run + checksum verify.
4. Jalankan restore actual ke target clone.
5. Validasi record count domain kritis.
6. Validasi login super admin + company context.
7. Promote hasil restore sesuai SOP.
8. Catat incident timeline + restore UUID.

---

## Priority Domain Matrix

Urutan implementasi emitter event agar cepat memberi nilai:

1. Identity and access:
	- users
	- company_users
2. Organization core:
	- companies
	- departments
	- designations
	- employee_profiles
3. SaaS billing:
	- subscriptions
	- purchase_transactions
4. Platform config:
	- settings

Setiap domain baru masuk harus punya:

- entity mapping di emitter,
- masking rule untuk field sensitif,
- uji restore minimal 1 skenario.

---

## Event Flow

### Create / Update / Delete

1. Request masuk ke backend utama.
2. Domain logic mengeksekusi perubahan di DB operasional.
3. Backend membangun audit payload.
4. Payload dikirim ke Recovery Vault API melalui service token.
5. API memverifikasi signature, role, company scope, dan idempotency key.
6. Event disimpan sebagai immutable record.

### Snapshot generation

1. Job terjadwal mengambil rentang event atau data domain tertentu.
2. Service menyusun snapshot state.
3. Snapshot divalidasi checksum-nya.
4. Hasilnya disimpan ke `audit_snapshots` dan archive storage bila perlu.

### Restore

1. Super admin mengajukan restore dari snapshot tertentu.
2. Request harus masuk ke approval gate.
3. Service menyiapkan restore plan di environment aman atau staging target.
4. Setelah validasi, restore dipromosikan ke database tujuan.
5. Semua langkah dicatat ke `audit_restores`.

---

## Security Model

### Access control

- Endpoint Recovery Vault tidak boleh public.
- Hanya backend service account yang bisa menulis event.
- Read dan restore hanya untuk super admin atau automation yang diotorisasi.

### Authentication

Rekomendasi berlapis:

- mTLS untuk service-to-service,
- short-lived signed token,
- HMAC signature per payload,
- request idempotency key,
- IP allowlist untuk jaringan internal.

### Authorization

- Super admin only untuk restore.
- Admin biasa boleh lihat ringkasan terbatas jika diperlukan.
- Non-admin tidak punya akses langsung ke log mentah.

### Data protection

- payload sensitif bisa di-mask sebelum disimpan,
- checksum untuk integritas,
- append-only policy,
- audit akses ke audit DB itu sendiri.

---

## Retention Policy

Rekomendasi awal:

- hot retention: 90 hari,
- snapshot bulanan: 12 bulan,
- archive lama: object storage terenkripsi atau cold storage,
- purge job berjalan terjadwal dan tercatat.

Aturan paling aman:

- data event lebih tua dari 90 hari tidak dihapus tanpa ada snapshot/archive pengganti,
- restore terbaik selalu dari snapshot terdekat, bukan replay raw event satu per satu kecuali investigasi.

---

## Restore Strategy

Ada dua mode restore:

### 1. Granular restore

Dipakai untuk 1 entity atau 1 company tertentu.

Contoh:

- satu user hilang,
- satu departemen salah hapus,
- satu transaksi perlu rollback.

### 2. Disaster restore

Dipakai saat DB utama rusak besar.

Alur yang disarankan:

- spin up database target baru,
- replay snapshot terdekat,
- verifikasi checksum dan record counts,
- smoke test critical login/company data,
- baru promote ke production.

---

## Suggested API Surface

Ini masih proposal, belum kontrak final.

### Internal ingest

- `POST /v1/internal/recovery/events`
- `POST /v1/internal/recovery/snapshots`
- `POST /v1/internal/recovery/restores`

### Admin view

- `GET /v1/saas/recovery-vault/snapshots`
- `GET /v1/saas/recovery-vault/snapshots/{id}`
- `POST /v1/saas/recovery-vault/snapshots/{id}/restore`

### Health / ops

- `GET /internal/recovery/health`

Semua route ini harus dibatasi ketat dan jangan dibuka ke browser publik.

---

## Implementation Phasing

### Phase 1

- audit_events table
- internal event ingest API
- service token auth
- retention job 90 hari
- super admin read-only audit list

### Phase 2

- snapshot engine
- restore approval flow
- archive export
- dashboard summary

### Phase 3

- entity-level restore
- company-level restore
- checksum verification UI
- automated disaster rehearsal

---

## What This Does Not Replace

Recovery Vault bukan pengganti:

- full MySQL backup,
- filesystem backup,
- database snapshot level infra,
- atau DR plan yang memulihkan seluruh environment.

Fungsinya adalah menambah observability dan kemampuan restore terarah.

---

## Open Questions

- Apakah event ingester akan pakai queue outbox atau direct API call?
- Apakah snapshot disimpan sebagai JSON per entity atau normalized per block?
- Apakah restore harus selalu approval dua langkah?
- Apakah semua tabel HCM dan SaaS masuk, atau hanya domain kritis dulu?

---

## Recommendation

Mulai dari domain kritis:

- users,
- companies,
- company_users,
- employee_profiles,
- departments,
- designations,
- subscriptions,
- purchase_transactions,
- settings.

Itu memberi perlindungan paling besar dengan kompleksitas yang masih masuk akal.