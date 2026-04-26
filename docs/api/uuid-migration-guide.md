# UUID Migration Guide - Tax Governance

## Scope

This guide covers identifier transition for tax governance policy endpoints:

- GET /v1/hcm/tax-governance/policies/{policyRef}
- PATCH /v1/hcm/tax-governance/policies/{policyRef}
- POST /v1/hcm/tax-governance/policies/{policyRef}/submit
- POST /v1/hcm/tax-governance/policies/{policyRef}/approve
- POST /v1/hcm/tax-governance/policies/{policyRef}/publish
- GET /v1/hcm/tax-governance/policies/{policyRef}/events

`policyRef` accepts UUID and temporary numeric legacy fallback during migration window.

## Current Runtime Behavior

1. UUID references are first-class and recommended.
2. Numeric legacy references are accepted for backward compatibility.
3. Numeric usage emits deprecation signals:
   - `Deprecation: true`
   - `Sunset: 2026-07-26T00:00:00Z`
   - `Warning: 299 - "Numeric policy identifier is deprecated. Use UUID."`
4. Numeric usage is logged for telemetry (`tax_governance.numeric_policy_reference_used`).
5. API responses expose UUID-centric identifiers (`policy_uuid`, `event_uuid`).

## Migration Timeline

1. Phase 8 Start: UUID + numeric fallback active with telemetry.
2. Pre-Sunset Window: clients migrate all policy path references to UUID.
3. Sunset Date: 2026-07-26T00:00:00Z.
4. Post-Sunset Target: disable numeric fallback and return 404 for numeric path IDs.

## Client Migration Checklist

1. Replace all policy path references with `data.uuid`.
2. Stop using numeric policy ID from internal persistence snapshots.
3. Add automated checks to reject numeric IDs in API callers.
4. Validate deprecation headers are absent in normal UUID traffic.

## Testing Checklist

1. UUID path should succeed with no deprecation header.
2. Numeric path should still succeed before sunset and include deprecation headers.
3. Event history payload should use `policy_uuid` and `event_uuid`.
4. Telemetry logs should include endpoint path and actor context.

## Operational Monitoring

Track these metrics daily until sunset:

1. Numeric policy path request count.
2. Unique clients still using numeric references.
3. Endpoint distribution of numeric usage.
4. Migration progress to zero numeric usage before cutoff.
