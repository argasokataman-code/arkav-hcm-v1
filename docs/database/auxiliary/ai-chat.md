# Auxiliary Tables: AI Chat & Logs

## `ai_chat_logs`

Audit log untuk AI Assistant chat interactions.

- `id BIGINT UNSIGNED PK`
- `user_uuid CHAR(36) NOT NULL` (FK `users.uuid`, cascade on delete)
- `user_legacy_id BIGINT UNSIGNED NULL` — legacy integer user ID fallback
- `company_id INT UNSIGNED NULL`
- `session_id CHAR(36) NOT NULL` — session identifier per conversation
- `intent VARCHAR(100) NOT NULL DEFAULT 'unknown'` — intent recognized (contoh: `cuti.request`, `absensi.check`)
- `allowed BOOLEAN NOT NULL DEFAULT 0` — RBAC gate: apakah user boleh akses intent tersebut
- `deny_reason VARCHAR(100) NULL` — reason jika `allowed = 0`
- `source_endpoints JSON NULL` — endpoint backend yang dipanggil AI
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`

Constraint:
- `FOREIGN KEY (user_uuid) REFERENCES users(uuid) ON DELETE CASCADE`

Index:
- `KEY ai_chat_logs_user_uuid_idx (user_uuid)`
- `KEY ai_chat_logs_user_session_idx (user_uuid, session_id)`
- `KEY ai_chat_logs_created_at_idx (created_at)`

---

## Related Documentation

- **Feature Docs:** `docs/features/ai-assistant/`
- **API:** None (internal audit log)
