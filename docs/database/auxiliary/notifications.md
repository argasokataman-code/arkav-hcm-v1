# Auxiliary Tables: Notification Deliveries

## `notification_deliveries`

Event-based notification delivery tracking.

- `id BIGINT UNSIGNED PK`
- `uuid CHAR(36) NOT NULL UNIQUE`
- `event_key VARCHAR(191) NOT NULL` — event trigger (contoh: `email.compose.sent`, `email.inbound.received`)
- `channel ENUM('database','mail','sms','webhook') NOT NULL DEFAULT 'database'`
- `status VARCHAR(32) NOT NULL DEFAULT 'queued'` — `queued`, `sent`, `failed`
- `notification_uuid VARCHAR(64) NULL` (index)
- `recipient VARCHAR(191) NULL` — email/phone/user_id
- `company_uuid CHAR(36) NULL` (index)
- `attempt_count INT UNSIGNED NOT NULL DEFAULT 1`
- `last_error TEXT NULL`
- `metadata JSON NULL`
- `sent_at TIMESTAMP NULL`
- `failed_at TIMESTAMP NULL`
- `created_at`, `updated_at`

Index:
- `KEY notification_deliveries_event_status_idx (event_key, status)`
- `KEY notification_deliveries_channel_status_idx (channel, status)`
- `KEY notification_deliveries_created_status_idx (created_at, status)`

---

## Related Documentation

- **Feature Docs:** `docs/features/notifications/`
- **API:** `docs/api/notifications-api.md`
