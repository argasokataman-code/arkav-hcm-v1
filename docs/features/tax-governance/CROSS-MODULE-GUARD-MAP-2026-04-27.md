# Cross-Module Guard Map (2026-04-27)

## Scope
- Source routes: /v1/hcm/*
- Total routes mapped: 321
- Mapping method:
  - Parse route action (Controller@method)
  - Resolve controller method body
  - Detect explicit guard patterns in method body

Detected explicit guard patterns:
- ensurePermission(
- ensureGlobalHcmAdmin(
- isGlobalHcmAdmin(
- isHcmAdmin(
- authorize(
- can(
- EnsuresHcmAdmin (controller-level marker)

## Global Summary
- Routes mapped to controller methods: 321
- Routes with explicit guard pattern detected in method body: 200
- Routes without detected explicit pattern in method body: 121

Important caveat:
- "No explicit guard pattern detected" does not automatically mean insecure.
- Many endpoints are secured through middleware, service-layer checks, ownership filters, or module-level guard conventions.
- This artifact is an inventory baseline to prioritize test audit, not a vulnerability claim.

## Tax Governance Detail
- tax-governance routes: 20
- tax-governance routes with explicit guard pattern detected: 20

## Module Prefix Summary (prefix | total routes | explicit pattern detected)
- performance | 30 | 19
- payroll | 28 | 23
- tax-governance | 20 | 20
- tickets | 15 | 0
- user-management | 15 | 1
- training | 13 | 9
- attendance | 11 | 1
- employees | 10 | 7
- assets | 9 | 9
- notifications | 9 | 6
- terminations | 9 | 7
- teams | 7 | 0
- billing | 6 | 0
- payroll-runs | 6 | 6
- promotions | 6 | 4
- resignations | 6 | 4
- salary-components | 6 | 6
- settings | 6 | 6
- departments | 5 | 5
- designations | 5 | 5
- email-settings | 5 | 5
- holidays | 5 | 5
- leave-requests | 5 | 0
- leave-settings | 5 | 5
- overtime-requests | 5 | 0
- payroll-items | 5 | 5
- payroll-periods | 5 | 5
- policies | 5 | 5
- asset-categories | 4 | 4
- leave-types | 4 | 4
- overtime-types | 4 | 3
- payroll-item-assignments | 4 | 4
- reports | 4 | 4
- salary-component-categories | 4 | 4
- shifts | 4 | 4
- smart-attendance-shifting | 4 | 0
- subscriptions | 4 | 0
- wilayah | 4 | 0
- activity-manual | 3 | 0
- schedule-timing | 3 | 0
- invoice-settings | 2 | 2
- notification-preferences | 2 | 0
- activity-feed | 1 | 1
- activity-feed-companies | 1 | 1
- company | 1 | 0
- dashboard-summary | 1 | 1
- employee-dashboard-summary | 1 | 0
- employee-leave-balance | 1 | 0
- leave-type-options | 1 | 0
- schedule-rosters | 1 | 0
- timesheets | 1 | 0

## Generated Evidence Files
- /tmp/hcm_routes.json
- /tmp/hcm_guard_map.json

## Next Steps
1. Continue with endpoint-level matrix: endpoint -> expected permission -> existing forbidden test.
2. Prioritize modules with low explicit-check ratio for coverage review.
3. Add missing forbidden-path tests before concluding cross-module audit closure.
