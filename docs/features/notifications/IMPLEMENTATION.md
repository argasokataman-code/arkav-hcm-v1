# Notifications Implementation Blueprint

## Tujuan Teknis

- Menyatukan notifikasi lintas fitur ke arsitektur yang bisa diaudit.
- Menjaga keamanan multi-tenant dan RBAC pada penentuan penerima notifikasi.
- Menyediakan baseline reliabilitas delivery dengan stack free/open-source.

## Inventaris Runtime Saat Ini

1. In-app database notifications:
   - `AssetAssignedNotification`
   - `AssetReturnedNotification`
   - `SubscriptionChangeApprovalNeededNotification`
2. Email notifications:
   - `PasswordResetLinkNotification`
   - `SendInvoiceEmailJob` + `InvoiceMailable`
   - `SendPaymentReminder` + `PaymentReminderMailable`
3. Persistence:
   - `notifications` table tersedia (uuid PK, read_at).
   - `invoice_email_logs` table tersedia untuk audit email invoice.
   - `notification_deliveries` tersedia untuk observability channel (`sent/failed/dropped`).
4. UI:
   - `/notification-settings` sudah menyimpan preferensi runtime via API.
   - Header dropdown notification sudah consume inbox API runtime (latest + unread count + mark read actions).

## Gap Utama

1. Dedicated inbox page full (filter/pagination/mark read batch detail) belum tersedia.
2. Sinkron unread state antar surface (header dropdown vs dedicated inbox page yang akan datang) belum terstandar.
3. Tidak ada template registry standar lintas event.
4. Dashboard observability UI khusus (chart + drilldown) belum tersedia; saat ini baru endpoint summary internal.
5. Event taxonomy belum seragam, sehingga sulit analitik lintas modul.

## Arsitektur Target

## 1) Event Producer Layer

- Sumber event dari service domain (AssetService, Subscription workflow, Billing workflow, HR workflow).
- Semua event masuk dengan struktur canonical:
  - `event_key` (contoh: `asset.assigned`, `billing.invoice.overdue`)
  - `company_uuid` (nullable untuk global)
  - `actor_user_uuid`
  - `entity_type`, `entity_uuid`
  - `severity`
  - `payload`

## 2) Routing & Policy Layer

- Komponen `NotificationRoutingService` menentukan penerima berdasarkan:
  - role template,
  - company scope,
  - rule khusus (contoh code-1 platform gate),
  - preference user.
- Komponen `NotificationPolicyService` memutuskan channel final dan escalation.

## 3) Delivery Layer

- Channel in-app (database) wajib sebagai baseline.
- Channel email untuk event critical/important tertentu.
- Delivery asynchronous via queue job `DispatchNotificationDeliveryJob`.
- Retry policy:
  - `critical`: max 5 retry, exponential backoff.
  - `important`: max 3 retry.
  - `informational`: max 1 retry.

## 4) Observability Layer

- Tabel `notification_deliveries` menyimpan status per channel (`queued`, `sent`, `failed`, `dropped`).
- Event kegagalan terpusat ke `notification_failures` (optional, jika volume tinggi).
- Dashboard minimum:
  - delivery success rate 24h,
  - top failed events,
  - queue latency percentile.

## Data Model Target (Usulan)

1. `notification_templates`
   - `uuid`, `event_key`, `channel`, `title_template`, `body_template`, `is_active`, `version`
2. `notification_preferences`
   - `uuid`, `user_uuid`, `event_key`, `channel`, `enabled`, `digest_mode`
3. `notification_deliveries`
   - `uuid`, `notification_uuid`, `channel`, `recipient`, `status`, `attempt_count`, `last_error`, `sent_at`
4. `notification_events`
   - `uuid`, `event_key`, `company_uuid`, `entity_type`, `entity_uuid`, `severity`, `payload`, `created_at`

Catatan:
- `notifications` bawaan Laravel tetap dipakai sebagai inbox record canonical untuk in-app.
- `notification_events` berperan sebagai audit event-level untuk analitik dan replay.

## API & UX Scope

- Gunakan draft di [API-CONTRACT.md](API-CONTRACT.md).
- UX minimum:
  - badge unread count,
  - dropdown latest notifications,
  - full inbox page dengan filter event/time/read-status,
  - preference page yang benar-benar persist ke backend.

## RBAC Dan Security Rules

- Tenant event hanya ke user dalam `company_uuid` yang sama.
- Platform event sensitif (approval queue lintas tenant) hanya ke primary super admin code-1.
- Endpoint notifikasi wajib auth + scope check server-side, bukan bergantung UI.
- Hindari menyimpan data sensitif plaintext pada payload (gunakan pointer UUID + fetch secure detail saat view).

## Strategi Free/Open-Source

1. Baseline production minimal:
   - Queue `database` + scheduler Laravel.
   - Inbox polling tiap 30-60 detik.
2. Scale-ready (tetap OSS):
   - Redis queue + Horizon.
   - Reverb untuk realtime push notifikasi browser.
3. Email:
   - Development: Mailpit.
   - Production: SMTP relay self-hosted (Postal/Mailcow) atau provider free-tier, dibungkus service internal.

## Roadmap Implementasi Bertahap

## Phase 0 - Standarisasi Event (1 sprint)

- Definisikan `event_key` canonical lintas modul.
- Bungkus notifikasi existing agar gunakan key + payload schema konsisten.
- Output:
  - katalog event v1,
  - migration aman untuk metadata event bila dibutuhkan.

Acceptance criteria:
- 100% notifikasi existing punya `event_key` canonical.
- Tidak ada event lintas tenant pada sample test.

## Phase 1 - Inbox API + Preferences (1-2 sprint)

- Implement endpoint inbox list/detail/mark-read/read-all/unread-count.
- Implement persistence `notification_preferences`.
- Wire halaman `/notification-settings` ke API nyata.

Acceptance criteria:
- User dapat mute event tertentu tanpa mematikan event critical.
- Unread badge konsisten dengan data backend.

## Phase 2 - Delivery Reliability (1 sprint)

- Tambah `notification_deliveries` + retry policy per severity.
- Dashboard observability dasar (route internal/admin).
- DLQ policy dan runbook retry manual.

Execution checklist detail:
1. Wire header dropdown notifikasi agar load unread count + latest notifications dari `/v1/hcm/notifications` dan `/v1/hcm/notifications/unread-count`.
2. Tambahkan aksi mark single read dan mark all read dari dropdown menuju endpoint runtime.
3. Tambahkan fallback UX: jika API gagal, dropdown tidak crash dan tampilkan empty/error state yang jelas.
4. Tambahkan baseline persistence delivery (`notification_deliveries`) untuk status `queued/sent/failed` channel utama.
5. Tambahkan endpoint observability internal minimal untuk ringkasan delivery (`success/failed by window`).
6. Tambahkan test coverage:
   - FE wiring test dropdown notifikasi,
   - backend feature test observability endpoint,
   - regression test mark-read/mark-all dari flow UI contract.
7. Sinkronkan docs API + tracker + evidence test setelah implementasi.

Catatan eksekusi 2026-04-24:
- Checklist phase 2 dijalankan single-track (berurutan end-to-end tanpa pemisahan subphase label), meliputi inbox runtime UX, persistence delivery, summary API, observability panel di settings, retry baseline queue, dan gate validasi penuh.

Acceptance criteria:
- Failure rate terukur, retry tercatat audit trail.
- Tidak ada silent drop untuk critical event.

## Phase 3 - Realtime + Digest (1 sprint)

- Aktifkan Reverb untuk push notifikasi realtime.
- Tambah daily/weekly digest untuk informational events.

Acceptance criteria:
- Latency notifikasi realtime median < 5 detik (in-app).
- Digest menghormati user preference dan timezone.

Catatan progres eksekusi 2026-04-24:
- Canonical event taxonomy lintas domain diperluas untuk leave/payroll/ticketing/performance.
- Observability drilldown modal kini menampilkan detail failed delivery, retry action, dan retry audit trail.
- Template management stub endpoint admin ditambahkan di `GET /v1/hcm/notifications/templates`.
- Digest mode support (`instant|daily|weekly`) tetap dijalankan lewat preference runtime API yang sudah aktif.

## Testing Strategy

- Unit test:
  - routing service,
  - policy service,
  - template rendering.
- Feature test:
  - API inbox dan preference.
  - multi-tenant isolation.
- Integration test:
  - queue delivery + retry + failure logging.
- E2E manual:
  - lihat [E2E-TESTING.md](E2E-TESTING.md).

## Risiko Dan Mitigasi

1. Risiko kebocoran notifikasi lintas tenant.
   - Mitigasi: mandatory `company_uuid` assertion di routing + test matrix lintas company.
2. Risiko queue menumpuk saat puncak event.
   - Mitigasi: pisah queue per severity dan gunakan Horizon alert threshold.
3. Risiko payload berisi data sensitif.
   - Mitigasi: simpan pointer UUID, bukan detail sensitif lengkap.

## Definition Of Done (per phase)

- Kontrak API sinkron dengan `docs/api/openapi.yaml` jika endpoint baru diaktifkan.
- Role/permission matrix di `docs/planning/active-hcm-templates-and-permissions.md` diperbarui.
- Tracker notifikasi diperbarui dengan evidence test dan gap residual.
