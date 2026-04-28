# Tax Governance Permission Audit (2026-04-27)

## Scope
- Module: Tax Governance API
- Route scope: /v1/hcm/tax-governance/*
- Validation type: server-side permission enforcement + tenant boundary checks
- Evidence source:
  - backend/routes/api.php
  - backend/app/Http/Controllers/Api/HcmTaxGovernanceController.php
  - backend/tests/Feature/HcmTaxGovernanceApiTest.php
  - command: php artisan test tests/Feature/HcmTaxGovernanceApiTest.php

## Endpoint Permission Map (Controller-level)
- index: tax.tenant.policy.view
- store: tax.tenant.policy.draft.manage
- show: tax.tenant.policy.view
- update: tax.tenant.policy.draft.manage
- submit: tax.tenant.policy.draft.manage
- approve: tax.tenant.policy.approve
- reject: tax.tenant.policy.approve
- publish: tax.tenant.policy.publish
- tenantSelfAuditReportExport: tax.tenant.report.export
- dashboardSummary: tax.governance.dashboard.view_all
- anomalyRegistry: tax.governance.anomaly.view_all
- resolveAnomaly: tax.governance.anomaly.manage
- acknowledgeAnomaly: tax.governance.anomaly.manage
- tenantSelfAuditReportEnhanced: tax.tenant.policy.view

## Tenant Isolation Guards
- tenantSelfAuditReportEnhanced:
  - blocks cross-tenant request for non-global admin
  - fallback to active company when company_id omitted
  - rejects request when company context missing
- anomaly management endpoints:
  - require company_id scope checks for non-global admin

## Executable Evidence
Command executed:
- php artisan test tests/Feature/HcmTaxGovernanceApiTest.php

Result:
- PASS (5 tests, 55 assertions)

Covered assertions:
- SoD enforcement in lifecycle approval/publish path
- non-permitted actor gets 403 AUTH_FORBIDDEN
- non-owner tenant access blocked (403/404 based on endpoint semantics)

## Findings
- No open permission bypass found in Tax Governance controller routes tested.
- No open tenant boundary leak found in covered scenarios.

## Residual Gaps (Not Closed by This Audit)
- Cross-module permission audit (Employees, Attendance, Payroll, Leave, Asset, Ticket, Training, Performance) still pending.
- Full route-to-permission matrix synchronization with docs/planning/active-hcm-templates-and-permissions.md still pending for all modules.
- Need one aggregated evidence report proving uniform 403 behavior across all admin-only modules.

## Next Actions
1. Build cross-module endpoint inventory (all /v1/hcm submodules).
2. Map endpoint -> required permission/middleware -> test coverage file.
3. Add missing tests for any admin-only endpoint without forbidden-path test.
4. Update tracker phase-2 evidence with module-by-module completion status.
