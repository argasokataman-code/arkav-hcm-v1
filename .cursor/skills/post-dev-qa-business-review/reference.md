# Reference: expanded checks and examples

Use this file when the task is large, high-risk, or the user asks for a formal review.

## Integration checklist (detail)

- **Identifiers**: stable IDs across create/update; no silent remapping of external IDs.
- **Time**: time zones for dates vs datetimes; boundary days (month end, DST).
- **Concurrency**: double-submit, duplicate webhooks, replay safety.
- **Versioning**: breaking API changes vs backward compatibility; feature detection in clients.
- **Observability**: logs/metrics that help diagnose integration failures without leaking PII.

## Example finding (good)

> **Blocking — RBAC**  
> `POST /v1/hcm/example` allows non-admin to set `userId` for another user. Server must reject with 403 unless `EnsuresHcmAdmin` (or documented exception). Evidence: `routes/api.php`, controller method X.

## Example finding (non-blocking)

> **Follow-up — UX**  
> Empty list shows generic "No data" while other HCM pages use the same empty-state pattern. Align with template partial for consistency.

## Tie-break

If product intent is unclear, **state the assumption** and mark the item **Gap (needs product confirmation)** instead of guessing.
