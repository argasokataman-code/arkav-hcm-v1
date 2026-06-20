# Database Schema Documentation

Dokumentasi lengkap schema database MySQL untuk ARCAV HCM.

## 📁 Structure

Schema dibagi per domain untuk kemudahan navigasi dan maintenance:

### Core Standards
- **[00-standards.md](00-standards.md)** — General standards, conventions, tenantization waves, migration order

### Identity & Auth
- **[identity/users-auth.md](identity/users-auth.md)** — `users`, `auth_tokens`, legacy roles/permissions

### Core HCM
- **[core-hcm/organization.md](core-hcm/organization.md)** — `departments`, `designations`, `teams`, `policies`
- **[core-hcm/employee-profiles.md](core-hcm/employee-profiles.md)** — `employee_profiles` + normalized history tables (employment, assignments, compensations, contracts, bank accounts, tax profiles, benefits, emergency contacts, educations, experiences)

### Payroll
- **[payroll/salary-components.md](payroll/salary-components.md)** — `hcm_salary_components`
- **[payroll/payroll-periods.md](payroll/payroll-periods.md)** — `hcm_payroll_periods`, `hcm_payroll_runs`, `hcm_payroll_lines`
- **[payroll/thr.md](payroll/thr.md)** — `hcm_thr_yearly_settings`, `hcm_thr_batches`, `hcm_thr_batch_lines`
- **[payroll/payroll-items.md](payroll/payroll-items.md)** — `hcm_payroll_items`

### Leave & Attendance
- **[leave-attendance/leave-management.md](leave-attendance/leave-management.md)** — `leave_types`, `leave_policies`, `leave_policy_assignments`, `employee_leave_balances`, `leave_ledger`, `leave_requests`, `leave_approvals`, `holiday_calendars`, future tables (workflows, blackouts, breakdowns, attachments, audits)
- **[leave-attendance/attendance.md](leave-attendance/attendance.md)** — `attendance_records`, `hcm_shifts`, `hcm_schedule_timings`
- **[leave-attendance/overtime.md](leave-attendance/overtime.md)** — `hcm_overtime_types`, `overtime_requests`

### User Management (RBAC)
- **[user-management/rbac.md](user-management/rbac.md)** — `hcm_roles`, `hcm_permissions`, `hcm_role_permissions`, `hcm_user_roles`, `hcm_user_role_audits`

### Governance
- **[governance/export-audit.md](governance/export-audit.md)** — `export_reconciliation_evidences`, `export_audit_logs`
- **[governance/data-privacy.md](governance/data-privacy.md)** — `erasure_requests`, `employee_biometric_consents`
- **[governance/approval-workflows.md](governance/approval-workflows.md)** — `hcm_approval_configs`, `hcm_approval_config_approvers`
- **[governance/tax-bpjs.md](governance/tax-bpjs.md)** — Tax governance, BPJS governance tables

### SaaS Platform
- **[saas-platform/companies-subscriptions.md](saas-platform/companies-subscriptions.md)** — `companies`, `company_users`, `subscriptions`, `payments`, `company_settings`
- **[saas-platform/packages.md](saas-platform/packages.md)** — `packages`, `package_features`, `package_addons`, `package_addon_assignments`
- **[saas-platform/subscription-events.md](saas-platform/subscription-events.md)** — `subscription_events`, `hcm_subscription_change_requests`
- **[saas-platform/invoices.md](saas-platform/invoices.md)** — `invoices`, `invoice_email_logs`

### Auxiliary Tables
- **[auxiliary/notifications.md](auxiliary/notifications.md)** — `notification_deliveries`
- **[auxiliary/wilayah.md](auxiliary/wilayah.md)** — `wilayah_provinces`, `wilayah_regencies`, `wilayah_districts`
- **[auxiliary/ai-chat.md](auxiliary/ai-chat.md)** — `ai_chat_logs`
- **[auxiliary/feature-classifications.md](auxiliary/feature-classifications.md)** — `feature_classifications`
- **[auxiliary/performance-training-lifecycle.md](auxiliary/performance-training-lifecycle.md)** — Performance, training, promotion, resignation, termination tables

---

## 🔍 Quick Reference

### By Feature Area
- **Authentication:** `identity/users-auth.md`
- **Employee Data:** `core-hcm/employee-profiles.md`, `core-hcm/organization.md`
- **Payroll:** `payroll/` folder (4 files)
- **Leave/Attendance:** `leave-attendance/` folder (3 files)
- **RBAC:** `user-management/rbac.md`
- **Compliance:** `governance/` folder (4 files)
- **SaaS/Billing:** `saas-platform/` folder (4 files)

### By Migration Priority
1. **Foundation:** `00-standards.md` → migration order
2. **Identity:** `identity/users-auth.md`
3. **Core HCM:** `core-hcm/` folder
4. **Payroll/Leave/Attendance:** respective folders
5. **Governance/SaaS:** respective folders

---

## 📚 Related Documentation

- **Feature Docs:** `docs/features/` — business flow per feature
- **API Specs:** `docs/api/` — endpoint contracts
- **Planning:** `docs/planning/` — implementation status, RBAC matrix
- **Migrations:** `backend/database/migrations/` — actual Laravel migration files

---

## 🔄 Maintenance

Saat menambah/mengubah schema:
1. Update file modular yang relevan di folder ini
2. Update `docs/features/<feature>/IMPLEMENTATION.md` jika menyentuh business logic
3. Update `docs/api/<feature>-api.md` jika endpoint berubah
4. Run `php artisan migrate` untuk verifikasi
5. Update `docs/planning/implementation-status.md` jika fitur baru

---

**Last Updated:** 2026-06-20 (Modularization from monolith `mysql-database-specification.md`)
