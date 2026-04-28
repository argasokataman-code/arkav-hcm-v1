# Cross-Module Endpoint Inventory (2026-04-27)

## Scope
- Route domain: /v1/hcm/*
- Source: runtime route registry
- Command evidence:
  - php artisan route:list --path=v1/hcm --json > /tmp/hcm_routes.json
  - node parser on /tmp/hcm_routes.json

## Summary
- Total endpoints in scope: 321
- Endpoints with baseline tenant middleware (api.token + tenant.context): 321
- Endpoints with feature gate middleware (hcm.api.feature:*): 91
- Endpoints with throttle middleware: 6

## Module Grouping by URI Prefix
- performance: 30
- payroll: 28
- tax-governance: 20
- tickets: 15
- user-management: 15
- training: 13
- attendance: 11
- employees: 10
- assets: 9
- notifications: 9
- terminations: 9
- teams: 7
- billing: 6
- payroll-runs: 6
- promotions: 6
- resignations: 6
- salary-components: 6
- settings: 6
- departments: 5
- designations: 5
- email-settings: 5
- holidays: 5
- leave-requests: 5
- leave-settings: 5
- overtime-requests: 5
- payroll-items: 5
- payroll-periods: 5
- policies: 5
- asset-categories: 4
- leave-types: 4
- overtime-types: 4
- payroll-item-assignments: 4
- reports: 4
- salary-component-categories: 4
- shifts: 4
- smart-attendance-shifting: 4
- subscriptions: 4
- wilayah: 4
- activity-manual: 3
- schedule-timing: 3
- invoice-settings: 2
- notification-preferences: 2
- activity-feed: 1
- activity-feed-companies: 1
- company: 1
- dashboard-summary: 1
- employee-dashboard-summary: 1
- employee-leave-balance: 1
- leave-type-options: 1
- schedule-rosters: 1
- timesheets: 1

## Guard Baseline Observations
- All /v1/hcm routes are under api.token + tenant.context.
- Feature-gated subdomains currently observed:
  - hcm.api.feature:payroll
  - hcm.api.feature:performance
  - hcm.api.feature:training
- Throttle-protected endpoints observed primarily in notification delivery/compose paths.

## Known Gap (Still Pending)
This inventory is endpoint-level only. It does not yet map:
- endpoint -> controller method permission code
- endpoint -> forbidden-path test coverage
- endpoint -> expected role matrix from planning docs

Those are handled by next tasks in tracker:
- TG-TODO-003
- TG-TODO-004
- TG-TODO-005
