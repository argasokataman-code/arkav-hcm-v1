# Notifications & Alerts Module

## Ringkasan

Modul ini menjadi pusat desain notifikasi lintas domain HCM + SaaS: in-app inbox, email billing/reminder, dan event operasional kritikal (approval, assignment, due/overdue, lifecycle subscription). Fokus dokumen ini adalah menutup gap desain agar implementasi berikutnya konsisten, aman, dan bisa dikerjakan bertahap tanpa vendor berbayar.

## Akses

- Konsumen end-user: karyawan, manager, dan admin tenant menerima notifikasi sesuai role + company scope.
- Konsumen platform: primary super admin code-1 menerima notifikasi approval lintas tenant yang bersifat platform-level.
- Pengaturan template/channel global: admin platform saja.
- Pengaturan preferensi personal: user pemilik akun masing-masing.

## UI Aktif

- Halaman konfigurasi visual `/notification-settings` sudah terhubung ke API runtime preference (`GET/PUT /v1/hcm/notification-preferences`).
- Header dropdown notifikasi sudah runtime (load list + unread count + mark-read + mark-all-read) dari inbox API.
- In-app inbox API backend (list/read/read-all/unread-count) dan API preference backend sudah tersedia; dedicated inbox page full masih pending.
- Baseline observability delivery sudah mulai aktif via `notification_deliveries` + endpoint summary admin.

## Phase 2 Execution Scope (Completed)

- Phase 2 dieksekusi single-track end-to-end untuk **inbox UX runtime + observability dasar**.
- Scope yang sudah selesai:
  - header dropdown notifikasi membaca data runtime (unread count + latest items),
  - aksi mark-as-read dan mark-all-as-read dari UX,
  - fallback aman ketika token/API unavailable,
  - baseline logging status delivery notifikasi agar troubleshooting tidak blind,
  - observability summary endpoint + panel observability di `/notification-settings` (global admin),
  - baseline retry policy untuk producer email aktif,
  - fallback recipient tenant-safe untuk payment reminder (owner -> membership owner/admin aktif).
- Evidence test ada pada tracker feature (`tracker.md`) dan sudah mencakup targeted tests + full local gate.

## Phase 3 Progress (Event Taxonomy Onboarding)

- Onboarding canonical event taxonomy lintas domain sudah berjalan untuk leave, payroll, ticketing, dan performance review.
- Observability dashboard kini memiliki drilldown failed-event detail, manual retry trigger, dan retry audit trail display.
- Event taxonomy matrix sudah dipublikasikan sebagai referensi operasional di `EVENT-TAXONOMY-MATRIX.md`.
- Template catalog stub endpoint admin (`GET /v1/hcm/notifications/templates`) tersedia sebagai fondasi manajemen template.
- Digest mode (`instant|daily|weekly`) tetap ditopang oleh preference runtime API (`GET/PUT /v1/hcm/notification-preferences`).

## Flow Bisnis End-to-End

1. Event bisnis terjadi (contoh: asset assigned, request change package, invoice due soon).
2. Event dipetakan ke rule notifikasi (siapa penerima, channel apa, prioritas apa).
3. Sistem membuat record notifikasi yang bisa diaudit (`notifications` untuk in-app, `invoice_email_logs` untuk email billing).
4. Job queue mengeksekusi delivery per channel (database/mail).
5. User melihat notifikasi di inbox atau email, lalu status dibaca/diakui tercatat untuk audit.
6. Tim operasional memonitor kegagalan delivery dan melakukan retry dari mekanisme yang terkontrol.

## Lifecycle Dan Keputusan Bisnis

- Severity:
  - `critical`: wajib terkirim (contoh: overdue + suspension warning).
  - `important`: wajib masuk inbox, email optional by preference.
  - `informational`: inbox default, bisa di-mute user.
- Delivery policy:
  - In-app database menjadi baseline wajib dan gratis.
  - Email dipakai untuk billing/security/approval yang butuh jejak eksternal.
  - SMS/WA ditunda sampai ada kebutuhan bisnis kuat dan gateway free yang operasional.
- Scope policy:
  - Tenant-scoped event tidak boleh lintas company.
  - Platform-scoped event harus mengikuti rule code-1 untuk action sensitif.

## Integrasi

- Asset Management: `AssetAssignedNotification`, `AssetReturnedNotification` (database channel).
- Subscriptions: `SubscriptionChangeApprovalNeededNotification` + `NotifySubscriptionChangeApproverJob`.
- Identity/Auth: `PasswordResetLinkNotification` (mail channel).
- Billing: `SendInvoiceEmailJob`, `SendPaymentReminder`, `invoice_email_logs`.
- Scheduler: `routes/console.php` (payment reminder, recurring billing, plan-change apply).

## Kontrak API

Draft kontrak API notifikasi ada di [API-CONTRACT.md](API-CONTRACT.md). Kontrak ini disiapkan sebagai target implementasi bertahap; belum semua endpoint tersedia di runtime saat ini.

## Existing Vs Target

- Existing:
  - Channel yang benar-benar hidup saat ini: `database` (asset, subscription approval) dan `mail` (password reset + invoice/reminder).
  - Tabel `notifications` sudah ada, tetapi belum ada inbox API/UX standar untuk consume notifikasi lintas fitur.
  - `invoice_email_logs` sudah dipakai untuk audit send invoice email.
  - Beberapa job notifikasi masih punya gap reliability/recipient source (contoh payment reminder masih mengandalkan field email company yang tidak canonical).
- Target:
  - Unified notification domain (event -> routing -> delivery -> observability) dengan prioritas dan policy yang konsisten.
  - In-app inbox API + preference API + admin template management.
  - Retry + dead-letter + monitoring dasar yang tetap 100% free/open-source.

## Stack Free / Open-Source (Rekomendasi)

- Backend core: Laravel Notifications + Queue + Scheduler (native, free).
- Queue runtime:
  - Minimal: `database` queue driver (tanpa infrastruktur tambahan).
  - Recommended: Redis + Horizon (self-hosted, OSS) untuk observability queue.
- Realtime inbox:
  - Minimal: polling API tiap interval.
  - Recommended: Laravel Reverb (OSS) untuk WebSocket event stream tanpa layanan berbayar.
- Email local/dev: Mailpit (OSS).
- Email prod low-cost/free-start: SMTP self-hosted (Postal/Mailcow) atau SMTP provider dengan free tier, tetap melalui abstraction service internal.

## Dokumentasi

- [IMPLEMENTATION.md](IMPLEMENTATION.md) — desain teknis rinci, data model target, rollout phase.
- [API-CONTRACT.md](API-CONTRACT.md) — draft endpoint notifikasi + preferensi + template.
- [E2E-TESTING.md](E2E-TESTING.md) — skenario QA untuk channel inbox dan email.
- [tracker.md](tracker.md) — snapshot status, gap, dan evidence terbaru.

## Status

Module version: `0.1 (design)`
Status: `In Progress (Phase 0 done, Phase 1 done, Phase 2 complete, Phase 3 items 26-39 complete)`
Last updated: `2026-04-24`
