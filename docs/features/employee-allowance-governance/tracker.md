# Tracker - Employee Allowance Governance

## Snapshot Status

- Last updated: 2026-05-03
- Overall status: in progress
- Runtime status: dedicated baseline module active (`/employee-allowance-governance*`)

## Scope Snapshot

1. Blueprint business flow sudah didefinisikan.
2. Blueprint implementation baseline sudah diwujudkan untuk policy + assignment + compliance.
3. Baseline default allowance Indonesia sudah berjalan sebagai auto-seed runtime tenant.
4. API contract dedicated allowance governance sudah aktif (reference/policies/assignments/reports).

## Gap Aktif

1. Formula allowance variable (prorate/threshold) belum aktif di runtime baseline.
2. Wiring payroll draft builder ke assignment allowance dedicated belum difinalkan.
3. Hardening permission granuler khusus allowance (`allowance.*`) belum diturunkan ke RBAC matrix runtime.
4. Guard lintas module untuk owner override payroll allowance masih baseline dan belum tenant-configurable.

## Evidence Saat Ini

1. docs/features/employee-allowance-governance/README.md
2. docs/features/employee-allowance-governance/IMPLEMENTATION.md
3. docs/api/allowance-governance-api.md
4. backend/app/Http/Controllers/Api/HcmEmployeeAllowanceGovernanceController.php
5. backend/resources/views/finance/employee-allowance-governance.blade.php
6. frontend/resources/js/employee-allowance-governance-data.js

## Next Milestone

1. Integrasikan assignment allowance ke payroll draft builder.
2. Tambahkan formula engine untuk allowance variable.
3. Tambahkan test coverage payroll integration + negative cases overlap/owner override.
