# Auto-Renewal - Implementation Blueprint (Production-Grade)

## Tujuan

Dokumen ini menjadi desain implementasi final untuk auto-renewal enterprise-grade yang aman, scalable, idempotent, dan audit-friendly.

Dokumen ini juga menjadi konsolidasi dari catatan teknis lama yang sebelumnya tersebar di lampiran terpisah. Jangan buat ulang file appendix baru di folder feature ini kecuali memang diminta eksplisit.

## Mulai Implementasi Dari Mana (Urutan Eksekusi)

Urutan implementasi wajib dimulai dari fondasi data + idempotency sebelum UI monitoring.

### Fase 1 - Fondasi Data dan Kontrak Runtime (Wajib dulu)

1. Tambah tabel idempotency renewal dan event audit immutable.
2. Tambah kolom reason code/message untuk jejak status renewal.
3. Tambah constraint unik renewal per subscription+period.
4. Pastikan migration aman untuk data existing (nullable/default/backfill plan).

Output minimum fase ini:
- renewal period tidak bisa dobel walau job/webhook retry
- status renewal punya reason_code + reason_message yang bisa di-query

### Fase 2 - Renewal Engine dan Lifecycle

1. Refactor orchestrator renewal agar lock + idempotent key dipakai di awal transaksi.
2. Implement retry policy 1h/24h/3d di job worker.
3. Implement lifecycle grace_period -> suspended.
4. Simpan event audit untuk setiap transisi penting.

Output minimum fase ini:
- alur due -> invoice -> payment attempt -> paid/failed berjalan konsisten
- seluruh transisi punya event audit

### Fase 3 - Reconciliation dan Monitoring Global Admin

1. Implement job reconciliation pending invoice/webhook telat.
2. Tambah endpoint monitoring summary/records/anomalies.
3. Implement halaman `/saas/renewal-monitoring` untuk global admin.
4. Tambah metric + alert untuk failure spike, gateway outage, worker crash.

Output minimum fase ini:
- global admin bisa melihat status renewal lintas tenant dengan alasan detail
- anomaly reason dapat dibedakan (gateway down vs feature crash vs duplicate blocked)

### Scope PR Pertama (Direkomendasikan)

Batasi PR pertama hanya untuk Fase 1 agar risk kecil dan review mudah.

Checklist PR pertama:
1. migration + model update untuk idempotency dan reason fields
2. unit test uniqueness/idempotency basic
3. update dokumentasi kontrak field status/reason bila ada perubahan shape

## Arsitektur Backend

### Layering

1. Controller layer
- validasi request
- authz/RBAC
- delegasi ke service layer

2. Service layer
- orchestration renewal
- invoice snapshot
- retry policy
- entitlement transition

3. Repository layer
- query subscription due
- lock row subscription/invoice
- persist event/audit records

4. Job layer
- scheduler-triggered workers
- reconciliation
- notification retry

5. Gateway adapter layer
- Xendit sebagai gateway aktif runtime saat ini
- Adapter layer tetap dijaga agar ekspansi multi-gateway bisa dilakukan terkontrol tanpa memecah alur renewal
- request signing/verification helpers

### Service Classes (Target)

1. SubscriptionRenewalOrchestrator
- find eligible subscription
- run renewal transaction with lock

2. RenewalInvoiceService
- create invoice + invoice_items immutable snapshot

3. PaymentAttemptService
- execute payment attempt
- apply retry policy (1h, 24h, 3d)

4. WebhookIngestionService
- validate signature
- deduplicate callback
- persist raw payload receipt

5. WebhookReconciliationService
- poll pending invoices
- sync gateway status

6. SubscriptionLifecycleService
- transition state (`payment_pending`, `grace_period`, `suspended`, `active`)

7. SubscriptionEventAuditService
- write immutable subscription_events records

8. EntitlementProjectionService
- map subscription -> plan -> feature entitlements -> access flags

## Scheduler/Cron

### Job utama

1. ProcessSubscriptionRenewals
- frequency: every 30 minutes (`everyThirtyMinutes`)
- queue-safe
- retry-safe
- idempotent

2. ReconcilePendingRenewalPayments
- frequency: every 30 minutes
- cek invoice pending lama / webhook telat

3. RenewalReminderDispatcher
- H-7, H-3, H-1

4. SuspensionEscalationJob
- warning sebelum suspend
- suspend setelah grace_ends_at lewat

## Monitoring, Alerting, dan Test Gate

Metric minimum yang wajib ada:
1. `cron_health`
2. `renewal_success_rate`
3. `payment_failure_rate`
4. `duplicate_renewal_detection`
5. `failed_webhook_count`
6. `stuck_invoice_detection`
7. `renewal_anomaly_count`
8. `gateway_health_renewal`

Alert minimum yang wajib ada:
1. cron mati lebih dari 1 jam
2. payment failure spike terhadap baseline 24 jam
3. duplicate invoice atau duplicate renewal terdeteksi
4. third-party gateway down
5. renewal worker crash spike

Negative scenarios minimum yang wajib lolos:
1. duplicate job retry tidak membuat invoice kedua
2. duplicate webhook menjadi no-op
3. gateway timeout tidak meng-extend subscription
4. invoice paid telat tetap tersinkron via reconciliation
5. grace period berakhir tanpa payment memicu suspend tepat sekali

Manual verification minimum untuk fase 3:
1. global admin bisa buka `/saas/renewal-monitoring`
2. non-global admin mendapat 403 atau redirect sesuai guard web
3. summary, records, detail, dan anomalies menampilkan `reason_code` + `reason_message`
4. simulasi anomali gateway down dan worker crash muncul di monitoring

## Idempotency dan Concurrency Protection

### Aturan transaksi

1. Seluruh renewal path wajib DB transaction.
2. Lock subscription row dengan pessimistic lock.
3. Gunakan renewal key unik per period.

Contoh renewal key:
- `sub_<subscription_id>_<YYYY_MM>`

### Constraint wajib

1. Unique constraint invoice renewal per subscription+period.
2. Duplicate webhook harus no-op setelah receipt tercatat processed.
3. Retry worker tidak boleh menciptakan invoice/charge baru untuk period yang sama.

## Payment Flow

1. Scheduler menemukan subscription due.
2. Service validasi eligibility:
- status in (active, payment_pending)
- auto_renew true
- bukan cancelled/expired terminal
3. Lock + create pending invoice snapshot.
4. Create payment attempt record.
5. Trigger charge request gateway.
6. Update invoice/payment state ke pending settlement.
7. Tunggu webhook atau reconciliation.
8. Saat paid: extend subscription dan update entitlement.

## Retry Mechanism

Policy wajib:
1. retry #1: +1 jam
2. retry #2: +24 jam
3. retry #3: +3 hari

Jika masih gagal:
1. status -> grace_period
2. set grace_ends_at dari konfigurasi `hcm.saas.renewal_grace_period_days` (fallback 3 hari)
3. kirim notifikasi grace started

Jika grace lewat tanpa payment:
1. status -> suspended
2. kirim suspension warning + suspended notice

## Webhook Flow

1. Terima callback.
2. Validasi signature/token.
3. Derive provider_webhook_id.
4. Insert ke webhook receipt table dengan unique(provider, webhook_id).
5. Jika duplicate key -> return success no-op.
6. Map payload ke invoice/payment target.
7. Update state via lifecycle service.
8. Tandai receipt processed_at.

## Audit Logging Strategy

### Structured logs

Setiap proses wajib logging JSON dengan field minimum:
- correlation_id
- trace_id
- subscription_id
- invoice_id
- payment_id
- renewal_period_key
- gateway
- action
- result
- error_code (jika gagal)

### Subscription event audit

Event minimum yang wajib tercatat:
- created
- renewed
- payment_failed
- grace_started
- suspended
- resumed
- cancelled
- auto_renew_disabled
- plan_changed
- retry_attempted

## Webhook Reconciliation

Tujuan:
- menangani callback telat
- menangani network failure
- menangani timeout webhook

Flow:
1. Query invoice pending yang melewati SLA tertentu.
2. Poll status gateway by reference.
3. Sinkronkan payment state.
4. Emit event audit reconciliation.

## Plan Snapshot System

Saat membuat invoice renewal, snapshot immutable wajib mencakup:
- plan_name
- feature_snapshot
- quantity
- unit_price_snapshot
- tax_snapshot
- total_snapshot

Larangan:
- jangan render billing historis dari relasi realtime package karena harga/fitur bisa berubah.

## Entitlement System

Model akses yang dipakai:
1. subscription status
2. plan baseline entitlements
3. addon entitlements
4. feature toggles
5. seat-based constraints

Aturan:
- hindari hardcode `if active` tunggal.
- akses harus diproyeksikan dari entitlement layer.

## Security Controls

1. Webhook signature validation wajib.
2. Payment metadata sensitif wajib terenkripsi at-rest.
3. Audit logs immutable.
4. Permission check di backend untuk semua mutasi sensitif.
5. Anti duplicate transaction via unique key + lock + idempotency records.

## Timezone Contract

1. Semua datetime utama disimpan UTC.
2. Frontend melakukan conversion ke timezone user.
3. Renewal scheduler memakai boundary UTC yang konsisten.

## Existing vs Target (Audit Delta)

Existing runtime yang sudah ada:
- recurring renewal job
- webhook signature/token validation
- cache-based dedup webhook
- reminder H-7
- pending invoice activation ke active

Gap utama:
- belum ada DB-backed renewal idempotency key
- belum ada grace_period lifecycle penuh
- belum ada immutable subscription_events dan invoice_items
- belum ada reconciliation job dedicated
- scheduler recurring masih daily
