# Database Schema Snapshot

> **Tanggal snapshot:** 2026-05-01  
> **Total tabel:** 125  
> **Sumber:** Rekonstruksi dari 208 file migrasi Laravel  
> **Urutan:** Alfabetis  

---

## Daftar Tabel

- [asset_assignments](#asset-assignments)
- [asset_attachments](#asset-attachments)
- [asset_categories](#asset-categories)
- [asset_logs](#asset-logs)
- [assets](#assets)
- [attendance_records](#attendance-records)
- [audit_logs](#audit-logs)
- [auth_tokens](#auth-tokens)
- [cache](#cache)
- [cache_locks](#cache-locks)
- [companies](#companies)
- [company_settings](#company-settings)
- [company_users](#company-users)
- [custom_domains](#custom-domains)
- [dashboard_metrics](#dashboard-metrics)
- [departments](#departments)
- [designations](#designations)
- [domain_verification_logs](#domain-verification-logs)
- [domains](#domains)
- [employee_assignments](#employee-assignments)
- [employee_bank_accounts](#employee-bank-accounts)
- [employee_benefits](#employee-benefits)
- [employee_compensations](#employee-compensations)
- [employee_contracts](#employee-contracts)
- [employee_educations](#employee-educations)
- [employee_emergency_contacts](#employee-emergency-contacts)
- [employee_employment_history](#employee-employment-history)
- [employee_experiences](#employee-experiences)
- [employee_leave_balances](#employee-leave-balances)
- [employee_profiles](#employee-profiles)
- [employee_tax_profiles](#employee-tax-profiles)
- [export_reconciliation_evidences](#export-reconciliation-evidences)
- [failed_jobs](#failed-jobs)
- [hcm_billing_tax_policies](#hcm-billing-tax-policies)
- [hcm_employee_payroll_item_assignments](#hcm-employee-payroll-item-assignments)
- [hcm_employee_work_arrangements](#hcm-employee-work-arrangements)
- [hcm_leave_custom_policies](#hcm-leave-custom-policies)
- [hcm_leave_type_settings](#hcm-leave-type-settings)
- [hcm_manual_activities](#hcm-manual-activities)
- [hcm_overtime_types](#hcm-overtime-types)
- [hcm_payroll_items](#hcm-payroll-items)
- [hcm_payroll_lines](#hcm-payroll-lines)
- [hcm_payroll_periods](#hcm-payroll-periods)
- [hcm_payroll_runs](#hcm-payroll-runs)
- [hcm_payroll_work_profiles](#hcm-payroll-work-profiles)
- [hcm_permissions](#hcm-permissions)
- [hcm_promotions](#hcm-promotions)
- [hcm_resignations](#hcm-resignations)
- [hcm_role_permissions](#hcm-role-permissions)
- [hcm_roles](#hcm-roles)
- [hcm_salary_component_categories](#hcm-salary-component-categories)
- [hcm_salary_components](#hcm-salary-components)
- [hcm_schedule_rosters](#hcm-schedule-rosters)
- [hcm_schedule_timings](#hcm-schedule-timings)
- [hcm_shifts](#hcm-shifts)
- [hcm_smart_planner_settings](#hcm-smart-planner-settings)
- [hcm_subscription_change_requests](#hcm-subscription-change-requests)
- [hcm_tax_governance_anomalies](#hcm-tax-governance-anomalies)
- [hcm_tax_governance_policies](#hcm-tax-governance-policies)
- [hcm_tax_governance_policy_events](#hcm-tax-governance-policy-events)
- [hcm_tax_governance_projections](#hcm-tax-governance-projections)
- [hcm_terminations](#hcm-terminations)
- [hcm_thr_batch_lines](#hcm-thr-batch-lines)
- [hcm_thr_batches](#hcm-thr-batches)
- [hcm_thr_disbursements](#hcm-thr-disbursements)
- [hcm_thr_yearly_settings](#hcm-thr-yearly-settings)
- [hcm_trainers](#hcm-trainers)
- [hcm_training_participants](#hcm-training-participants)
- [hcm_training_types](#hcm-training-types)
- [hcm_trainings](#hcm-trainings)
- [hcm_user_role_audits](#hcm-user-role-audits)
- [hcm_user_roles](#hcm-user-roles)
- [holiday_calendars](#holiday-calendars)
- [holidays](#holidays)
- [invoice_email_logs](#invoice-email-logs)
- [invoices](#invoices)
- [job_batches](#job-batches)
- [jobs](#jobs)
- [leave_approval_workflow_steps](#leave-approval-workflow-steps)
- [leave_approval_workflows](#leave-approval-workflows)
- [leave_approvals](#leave-approvals)
- [leave_blackout_dates](#leave-blackout-dates)
- [leave_ledger](#leave-ledger)
- [leave_policies](#leave-policies)
- [leave_policy_assignments](#leave-policy-assignments)
- [leave_request_attachments](#leave-request-attachments)
- [leave_request_audits](#leave-request-audits)
- [leave_request_breakdowns](#leave-request-breakdowns)
- [leave_requests](#leave-requests)
- [leave_types](#leave-types)
- [notification_deliveries](#notification-deliveries)
- [notification_preferences](#notification-preferences)
- [notifications](#notifications)
- [overtime_requests](#overtime-requests)
- [package_addons](#package-addons)
- [package_features](#package-features)
- [packages](#packages)
- [password_reset_tokens](#password-reset-tokens)
- [payments](#payments)
- [performance_goal_types](#performance-goal-types)
- [performance_goals](#performance-goals)
- [performance_reviews](#performance-reviews)
- [platform_monthly_financial_summaries](#platform-monthly-financial-summaries)
- [platform_revenue_transactions](#platform-revenue-transactions)
- [policies](#policies)
- [purchase_transactions](#purchase-transactions)
- [report_data_blocks](#report-data-blocks)
- [report_exports](#report-exports)
- [report_filters](#report-filters)
- [report_snapshots](#report-snapshots)
- [sessions](#sessions)
- [settings](#settings)
- [subscriptions](#subscriptions)
- [teams](#teams)
- [ticket_assignment_histories](#ticket-assignment-histories)
- [ticket_attachments](#ticket-attachments)
- [ticket_categories](#ticket-categories)
- [ticket_comments](#ticket-comments)
- [tickets](#tickets)
- [transactions](#transactions)
- [users](#users)
- [wilayah_districts](#wilayah-districts)
- [wilayah_provinces](#wilayah-provinces)
- [wilayah_regencies](#wilayah-regencies)
- [wilayah_villages](#wilayah-villages)

---

## asset_assignments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| asset_id | bigint unsigned | yes | null |  |
| employee_id | bigint unsigned | yes | null |  |
| assigned_date | timestamp | no | - |  |
| returned_date | timestamp | yes | null |  |
| condition_at_assign | varchar(30) | no | - |  |
| condition_at_return | varchar(30) | yes | null |  |
| active_token | varchar(32) | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## asset_attachments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| asset_id | bigint unsigned | yes | null |  |
| file_path | varchar(500) | no | - |  |
| file_type | varchar(120) | no | - |  |
| disk | varchar(40) | no | public |  |
| original_name | varchar(255) | yes | null |  |
| size_bytes | bigint unsigned | no | 0 |  |
| uploaded_by | bigint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## asset_categories

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| code | varchar(80) | no | - |  |
| name | varchar(150) | no | - |  |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## asset_logs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| asset_id | bigint unsigned | yes | null |  |
| action | enum(created,assigned,returned,updated,maintenance,issue_reported,retired) | no | - |  |
| reference_id | varchar(120) | yes | null |  |
| description | text | no | - |  |
| performed_by | bigint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## assets

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| asset_category_id | bigint unsigned | yes | null |  |
| asset_code | varchar(120) | no | - |  |
| name | varchar(150) | no | - |  |
| brand | varchar(120) | yes | null |  |
| model | varchar(120) | yes | null |  |
| serial_number | varchar(150) | yes | null |  |
| purchase_date | date | no | - |  |
| purchase_price | decimal(15,2) | no | 0 |  |
| condition | enum(good,damaged,lost) | no | good |  |
| status | enum(available,assigned,maintenance,retired) | no | available |  |
| location | varchar(255) | yes | null |  |
| notes | text | yes | null |  |
| warranty_start_date | date | yes | null |  |
| warranty_end_date | date | yes | null |  |
| deleted_at | timestamp | yes | null | soft delete |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## attendance_records

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → (inferred) |
| work_date | date | no | - |  |
| status | varchar(32) | no | present |  |
| check_in_at | timestamp | yes | null |  |
| check_out_at | timestamp | yes | null |  |
| break_minutes | smallint unsigned | no | 0 |  |
| late_minutes | smallint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| correction_status | varchar(20) | no | none |  |
| correction_reason | text | yes | null |  |
| correction_requested_at | timestamp | yes | null |  |
| corrected_by_user_id | bigint unsigned | yes | - | FK → users |
| corrected_at | timestamp | yes | null |  |
| break_started_at | timestamp | yes | null |  |
| check_in_latitude | decimal(10,7) | yes | null |  |
| check_in_longitude | decimal(10,7) | yes | null |  |
| check_out_latitude | decimal(10,7) | yes | null |  |
| check_out_longitude | decimal(10,7) | yes | null |  |
| check_in_location_name | varchar(255) | yes | null |  |
| check_in_location_address | text | yes | null |  |
| check_out_location_name | varchar(255) | yes | null |  |
| check_out_location_address | text | yes | null |  |
| check_in_location_source | enum(gps,manual,pending) | no | gps |  |
| check_out_location_source | enum(gps,manual,pending) | no | gps |  |
| selfie_path | varchar(255) | yes | null |  |
| selfie_encrypted_hash | varchar(255) | yes | null |  |

## audit_logs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| super_admin_id | bigint unsigned | no | - |  |
| action | varchar(255) | no | - |  |
| target_type | varchar(255) | no | - |  |
| target_id | bigint unsigned | yes | null |  |
| details | json | yes | null |  |
| ip_address | varchar(255) | yes | null |  |
| user_agent | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## auth_tokens

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → (inferred) |
| token_hash | varchar(64) | no | - | unique |
| expires_at | timestamp | yes | null |  |
| revoked_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## cache

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| key | varchar(255) | no | - |  |
| value | mediumtext | no | - |  |
| expiration | int | no | - |  |

## cache_locks

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| key | varchar(255) | no | - |  |
| owner | varchar(255) | no | - |  |
| expiration | int | no | - |  |

## companies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(120) | no | - | unique |
| name | varchar(200) | no | - |  |
| legal_name | varchar(255) | yes | null |  |
| status | varchar(50) | no | active |  |
| owner_user_id | bigint unsigned | yes | - | FK → users |
| timezone | varchar(64) | no | UTC |  |
| currency | varchar(3) | no | IDR |  |
| country_code | varchar(2) | no | ID |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## company_settings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | no | - | FK → companies |
| key | varchar(150) | no | - |  |
| value | text | yes | null |  |
| type | varchar(50) | no | string |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## company_users

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | no | - | FK → companies |
| user_id | bigint unsigned | no | - | FK → users |
| role | varchar(50) | no | member |  |
| status | varchar(50) | no | active |  |
| joined_at | timestamp | yes | null |  |
| invited_by_user_id | bigint unsigned | yes | - | FK → users |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## custom_domains

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | no | - | FK → companies |
| domain | varchar(255) | no | - | unique |
| status | enum(pending,verified,failed,inactive) | no | pending |  |
| verification_token | varchar(255) | no | - | unique |
| verified_at | datetime | yes | null |  |
| verification_failed_at | datetime | yes | null |  |
| verification_method | varchar(255) | no | dns |  |
| verification_record | varchar(255) | yes | null |  |
| verification_response | text | yes | null |  |
| verification_attempts | int | no | 0 |  |
| last_verification_attempt_at | datetime | yes | null |  |
| active_from | datetime | yes | null |  |
| active_until | datetime | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| deleted_at | timestamp | yes | null | soft delete |

## dashboard_metrics

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| metric_date | date | no | - |  |
| metric_key | varchar(255) | no | - |  |
| metric_value | decimal(8,2) | no | - |  |
| metric_metadata | json | yes | null |  |
| calculated_at | timestamp | yes | null |  |
| next_calculation_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## departments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(50) | no | - | unique |
| name | varchar(150) | no | - |  |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| deleted_at | timestamp | yes | null | soft delete |

## designations

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| department_id | bigint unsigned | yes | - | FK → departments |
| code | varchar(50) | no | - | unique |
| name | varchar(150) | no | - |  |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## domain_verification_logs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| domain_id | bigint unsigned | no | - | FK → custom_domains |
| status | enum(pending,verified,failed) | no | pending |  |
| verification_method | varchar(255) | no | - |  |
| details | text | yes | null |  |
| attempted_at | datetime | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## domains

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| domain_name | varchar(255) | no | - | unique |
| company_id | bigint unsigned | yes | null |  |
| verification_type | enum(dns,file) | no | - |  |
| status | enum(pending,verified,failed) | no | pending |  |
| verification_token | varchar(255) | yes | null |  |
| verification_data | json | yes | null |  |
| verified_at | timestamp | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_assignments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| department_id | bigint unsigned | yes | - | FK → departments |
| designation_id | bigint unsigned | yes | - | FK → designations |
| manager_user_id | bigint unsigned | yes | - | FK → users |
| is_primary | tinyint(1) | no | true |  |
| start_date | date | no | - |  |
| end_date | date | yes | null |  |
| team_name | varchar(100) | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| team_id | bigint unsigned | yes | - | FK → teams |

## employee_bank_accounts

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| bank_name | varchar(150) | yes | null |  |
| account_number | varchar(100) | yes | null |  |
| account_holder_name | varchar(150) | yes | null |  |
| bank_ifsc_code | varchar(100) | yes | null |  |
| bank_branch | varchar(150) | yes | null |  |
| is_primary | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_benefits

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| bpjs_kesehatan_no | varchar(100) | yes | null |  |
| bpjs_ketenagakerjaan_no | varchar(100) | yes | null |  |
| effective_date | date | no | - |  |
| end_date | date | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_compensations

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| salary_type | varchar(50) | no | monthly |  |
| base_salary | decimal(15,2) | no | 0 |  |
| fixed_allowance | decimal(15,2) | no | 0 |  |
| currency | varchar(10) | no | IDR |  |
| effective_date | date | no | - |  |
| end_date | date | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_contracts

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| contract_type | varchar(50) | no | permanent |  |
| start_date | date | yes | null |  |
| end_date | date | yes | null |  |
| status | varchar(50) | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_educations

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| institution | varchar(150) | yes | null |  |
| degree | varchar(100) | yes | null |  |
| field_of_study | varchar(150) | yes | null |  |
| start_year | smallint unsigned | yes | null |  |
| end_year | smallint unsigned | yes | null |  |
| notes | text | yes | null |  |
| sort_order | int unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_emergency_contacts

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| name | varchar(150) | no | - |  |
| relationship | varchar(100) | yes | null |  |
| phone | varchar(50) | yes | null |  |
| email | varchar(150) | yes | null |  |
| sort_order | int unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_employment_history

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| employment_status | varchar(50) | no | active |  |
| employee_type | varchar(50) | yes | null |  |
| start_date | date | no | - |  |
| end_date | date | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| probation_end_date | date | yes | null |  |

## employee_experiences

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| company | varchar(150) | yes | null |  |
| position | varchar(150) | yes | null |  |
| start_date | date | yes | null |  |
| end_date | date | yes | null |  |
| description | text | yes | null |  |
| sort_order | int unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_leave_balances

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| employee_id | bigint unsigned | no | - | FK → users |
| leave_type_id | bigint unsigned | no | - | FK → leave_types |
| year | smallint unsigned | no | - |  |
| balance | decimal(10,2) | no | 0 |  |
| used | decimal(10,2) | no | 0 |  |
| expired | decimal(10,2) | no | 0 |  |
| carried_forward | decimal(10,2) | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## employee_profiles

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| team | varchar(100) | yes | null |  |
| designation | varchar(150) | yes | null |  |
| phone | varchar(50) | yes | null |  |
| address | text | yes | null |  |
| bio | text | yes | null |  |
| bank_name | varchar(150) | yes | null |  |
| bank_account_no | varchar(100) | yes | null |  |
| bank_ifsc_code | varchar(100) | yes | null |  |
| bank_branch | varchar(150) | yes | null |  |
| emergency_contacts | json | yes | null |  |
| education_items | json | yes | null |  |
| experience_items | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| employment_status | varchar(20) | no | active |  |
| base_salary | decimal(15,2) | no | 0 |  |
| fixed_allowance | decimal(15,2) | no | 0 |  |
| manager_user_id | bigint unsigned | no | - | FK |
| department_id | bigint unsigned | no | - | FK |
| designation_id | bigint unsigned | no | - | FK |
| hire_date | date | yes | null |  |
| contract_type | varchar(32) | no | permanent |  |
| contract_start_date | date | yes | null |  |
| contract_end_date | date | yes | null |  |
| profile_photo_path | varchar(255) | yes | null |  |
| address_detail | text | yes | null |  |
| nik_encrypted | text | yes | null |  |
| uuid | uuid | yes | - | unique |
| province_id | bigint unsigned | yes | - | FK → wilayah_provinces |
| regency_id | bigint unsigned | yes | - | FK → wilayah_regencies |
| district_id | bigint unsigned | yes | - | FK → wilayah_districts |
| village_id | bigint unsigned | yes | - | FK → wilayah_villages |
| team_id | bigint unsigned | no | - | FK |

## employee_tax_profiles

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| employee_id | bigint unsigned | no | - | FK → employee_profiles |
| npwp | varchar(100) | yes | null |  |
| tax_status | varchar(50) | yes | null |  |
| ptkp_status | varchar(50) | yes | null |  |
| effective_date | date | no | - |  |
| end_date | date | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## export_reconciliation_evidences

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| feature_key | varchar(80) | no | - |  |
| action_key | varchar(80) | no | - |  |
| scope_ref | varchar(120) | no | - |  |
| exported_by_user_id | bigint unsigned | yes | null |  |
| exported_at | timestamp | yes | null |  |
| file_format | varchar(10) | no | - |  |
| file_path | varchar(500) | no | - |  |
| row_count | int unsigned | no | 0 |  |
| filter_payload | json | yes | null |  |
| dataset_checksum | varchar(64) | yes | null |  |
| expires_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## failed_jobs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | varchar(255) | no | - | unique |
| connection | text | no | - |  |
| queue | text | no | - |  |
| payload | longtext | no | - |  |
| exception | longtext | no | - |  |
| failed_at | timestamp | no | - | useCurrent |

## hcm_billing_tax_policies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | uuid | no | - | PK |
| company_id | bigint unsigned | no | - |  |
| billing_month | varchar(7) | no | - |  |
| billing_cycle_type | varchar(16) | no | - |  |
| tax_rate_percentage | decimal(5,2) | no | - |  |
| base_calculation_method | varchar(64) | no | invoice_amount_due |  |
| effective_from | date | no | - |  |
| effective_to | date | yes | null |  |
| status | varchar(16) | no | active |  |
| notes | text | yes | null |  |
| created_by_user_id | bigint unsigned | yes | null |  |
| updated_by_user_id | bigint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_employee_payroll_item_assignments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| user_id | bigint unsigned | no | - |  |
| hcm_payroll_item_id | bigint unsigned | no | - |  |
| amount | decimal(15,2) | no | 0 |  |
| is_active | tinyint(1) | no | true |  |
| effective_start_date | date | yes | null |  |
| effective_end_date | date | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_employee_work_arrangements

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| user_id | bigint unsigned | no | - |  |
| hcm_payroll_work_profile_id | bigint unsigned | yes | null |  |
| arrangement_mode | varchar(30) | no | office_hour |  |
| default_day_type | varchar(40) | yes | null |  |
| weekly_work_days | tinyint unsigned | yes | null |  |
| effective_from | date | no | - |  |
| effective_to | date | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_leave_custom_policies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| leave_type_code | varchar(64) | no | - |  |
| name | varchar(200) | no | - |  |
| days | decimal(8,2) | no | - |  |
| assignee_user_ids | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| leave_type_id | bigint unsigned | yes | null |  |
| leave_policy_id | bigint unsigned | yes | null |  |

## hcm_leave_type_settings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(64) | no | - | unique |
| name | varchar(150) | no | - |  |
| is_enabled | tinyint(1) | no | true |  |
| days | decimal(8,2) | yes | null |  |
| carry_forward | tinyint(1) | no | false |  |
| max_carry_days | smallint unsigned | yes | null |  |
| earned_leave | tinyint(1) | no | false |  |
| sort_order | tinyint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| leave_type_id | bigint unsigned | yes | null |  |

## hcm_manual_activities

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | no | - |  |
| title | varchar(255) | no | - |  |
| activity_kind | varchar(50) | no | task |  |
| status | varchar(50) | no | planned |  |
| due_date | date | yes | null |  |
| created_by_user_id | bigint unsigned | yes | null |  |
| updated_by_user_id | bigint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_overtime_types

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(64) | no | - | unique |
| name | varchar(255) | no | - |  |
| description | varchar(500) | yes | null |  |
| payment_multiplier | decimal(8,2) | no | 1.00 |  |
| is_active | tinyint(1) | no | true |  |
| sort_order | smallint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_payroll_items

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| hcm_salary_component_id | bigint unsigned | yes | - | FK → hcm_salary_components |
| code | varchar(64) | yes | null |  |
| name | varchar(200) | no | - |  |
| kind | varchar(32) | no | - |  |
| category | varchar(64) | no | - |  |
| notes | text | yes | null |  |
| sort_order | smallint unsigned | no | 0 |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_payroll_lines

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| hcm_payroll_run_id | bigint unsigned | no | - | FK → hcm_payroll_runs |
| user_id | bigint unsigned | no | - | FK → users |
| hcm_salary_component_id | bigint unsigned | yes | - | FK → hcm_salary_components |
| component_code | varchar(64) | yes | null |  |
| component_name | varchar(200) | yes | null |  |
| kind | varchar(32) | no | - |  |
| category | varchar(64) | yes | null |  |
| amount | decimal(15,2) | no | - |  |
| sort_order | smallint unsigned | no | 0 |  |
| meta | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_payroll_periods

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| period_year | smallint unsigned | no | - |  |
| period_month | tinyint unsigned | no | - |  |
| status | varchar(24) | no | open |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## hcm_payroll_runs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| hcm_payroll_period_id | bigint unsigned | no | - | FK → hcm_payroll_periods |
| status | varchar(24) | no | draft |  |
| calculated_at | timestamp | yes | null |  |
| finalized_at | timestamp | yes | null |  |
| finalized_by_user_id | bigint unsigned | yes | - | FK → users |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| purpose | varchar(32) | no | monthly |  |
| voided_at | timestamp | yes | null |  |
| voided_by_user_id | bigint unsigned | yes | null |  |
| voided_by_user_uuid | uuid | yes | - |  |
| meta | json | yes | null |  |
| hcm_tax_governance_policy_id | bigint unsigned | yes | null |  |
| hcm_tax_governance_policy_version | smallint unsigned | yes | null |  |

## hcm_payroll_work_profiles

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| code | varchar(80) | no | - |  |
| name | varchar(120) | no | - |  |
| arrangement_mode | varchar(30) | no | office_hour |  |
| default_day_type | varchar(40) | no | workday |  |
| weekly_work_days | tinyint unsigned | no | 5 |  |
| is_default | tinyint(1) | no | false |  |
| meta | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_permissions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(120) | no | - | unique |
| module | varchar(80) | no | - |  |
| resource | varchar(80) | no | - |  |
| action | varchar(80) | no | - |  |
| name | varchar(150) | no | - |  |
| description | varchar(2000) | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_promotions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| department | varchar(150) | yes | null |  |
| designation_from | varchar(150) | yes | null |  |
| designation_to | varchar(150) | yes | null |  |
| promotion_date | date | no | - |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## hcm_resignations

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| department | varchar(150) | yes | null |  |
| reason | text | no | - |  |
| notice_date | date | no | - |  |
| resignation_date | date | no | - |  |
| status | varchar(32) | no | pending |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| deleted_at | timestamp | yes | null | soft delete |
| company_id | bigint unsigned | yes | null |  |

## hcm_role_permissions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| role_id | bigint unsigned | no | - | FK → hcm_roles |
| permission_id | bigint unsigned | no | - | FK → hcm_permissions |
| created_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## hcm_roles

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | - | FK → companies |
| code | varchar(80) | no | - |  |
| name | varchar(150) | no | - |  |
| description | varchar(2000) | yes | null |  |
| status | varchar(30) | no | active |  |
| is_system | tinyint(1) | no | false |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_salary_component_categories

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | yes | - | unique |
| kind | varchar(32) | no | - |  |
| code | varchar(64) | no | - |  |
| name | varchar(150) | no | - |  |
| description | varchar(500) | yes | null |  |
| is_system | tinyint(1) | no | false |  |
| is_active | tinyint(1) | no | true |  |
| sort_order | smallint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_salary_components

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(64) | no | - | unique |
| name | varchar(200) | no | - |  |
| description | text | yes | null |  |
| kind | varchar(32) | no | - |  |
| category | varchar(64) | no | - |  |
| legal_basis | varchar(500) | yes | null |  |
| legal_notes | text | yes | null |  |
| include_bpjs_health_wage_base | tinyint(1) | no | false |  |
| include_bpjs_tk_wage_base | tinyint(1) | no | false |  |
| include_thr_calculation_base | tinyint(1) | no | false |  |
| include_pph21_ter_gross | tinyint(1) | no | false |  |
| include_pph21_annual_reconciliation | tinyint(1) | no | false |  |
| subject_overtime_regulation | tinyint(1) | no | false |  |
| affects_net_pay | tinyint(1) | no | true |  |
| employer_cost_line | tinyint(1) | no | false |  |
| is_system_locked | tinyint(1) | no | false |  |
| sort_order | smallint unsigned | no | 0 |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| default_percent | decimal(8,4) | yes | null |  |
| percent_basis | varchar(64) | yes | null |  |
| tax_treatment_code | varchar(50) | yes | null |  |

## hcm_schedule_rosters

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| user_id | bigint unsigned | no | - |  |
| work_date | date | no | - |  |
| hcm_shift_id | bigint unsigned | yes | null |  |
| start_time | time | yes | null |  |
| end_time | time | yes | null |  |
| cross_day | tinyint(1) | no | false |  |
| roster_status | varchar(20) | no | working |  |
| source | varchar(20) | no | planner |  |
| published_by_user_id | bigint unsigned | yes | null |  |
| published_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_schedule_timings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → (inferred) |
| start_time | time | no | - |  |
| end_time | time | no | - |  |
| source | varchar(20) | no | auto |  |
| updated_by_user_id | bigint unsigned | yes | - | FK → users |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| hcm_shift_id | bigint unsigned | yes | - | FK → hcm_shifts |

## hcm_shifts

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(64) | no | - | unique |
| name | varchar(255) | no | - |  |
| start_time | time | no | - |  |
| end_time | time | no | - |  |
| description | varchar(500) | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| sort_order | smallint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| shift_type | varchar(20) | yes | null |  |

## hcm_smart_planner_settings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| default_rules | json | yes | null |  |
| forbidden_transitions | json | yes | null |  |
| created_by_user_id | bigint unsigned | yes | null |  |
| updated_by_user_id | bigint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_uuid | uuid | yes | - |  |
| created_by_user_uuid | uuid | yes | - |  |
| updated_by_user_uuid | uuid | yes | - |  |

## hcm_subscription_change_requests

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | uuid | no | - | PK |
| company_uuid | uuid | no | - |  |
| user_uuid | uuid | no | - |  |
| current_subscription_uuid | uuid | yes | - |  |
| from_package_uuid | uuid | yes | - |  |
| to_package_uuid | uuid | yes | - |  |
| action | varchar(20) | no | - |  |
| status | varchar(20) | no | pending |  |
| preview | json | yes | null |  |
| notes | varchar(500) | yes | null |  |
| effective_at | timestamp | yes | null |  |
| decided_at | timestamp | yes | null |  |
| decided_by_user_uuid | uuid | yes | - |  |
| applied_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_tax_governance_anomalies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | uuid | no | - | PK |
| company_id | bigint unsigned | no | - |  |
| severity | enum(info,warning,critical) | no | - |  |
| affected_policy_id | uuid | yes | - |  |
| affected_employee_id | bigint unsigned | yes | null |  |
| description | text | no | - |  |
| evidence_snapshot | json | yes | null |  |
| detected_at | timestamp | no | - |  |
| resolved_at | timestamp | yes | null |  |
| resolution_note | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| acknowledged_by_user_id | bigint unsigned | yes | null |  |
| acknowledged_at | timestamp | yes | null |  |

## hcm_tax_governance_policies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | no | - | unique |
| company_id | bigint unsigned | no | - |  |
| policy_code | varchar(100) | no | - |  |
| name | varchar(255) | no | - |  |
| status | varchar(32) | no | draft |  |
| effective_start_date | date | no | - |  |
| effective_end_date | date | yes | null |  |
| rules | json | no | - |  |
| rate_schedules | json | no | - |  |
| version | int unsigned | no | 1 |  |
| created_by_user_id | bigint unsigned | yes | - | FK → users |
| submitted_by_user_id | bigint unsigned | yes | - | FK → users |
| submitted_at | timestamp | yes | null |  |
| approved_by_user_id | bigint unsigned | yes | - | FK → users |
| approved_at | timestamp | yes | null |  |
| published_by_user_id | bigint unsigned | yes | - | FK → users |
| published_at | timestamp | yes | null |  |
| last_note | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| draft_fingerprint | varchar(120) | yes | null |  |

## hcm_tax_governance_policy_events

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | no | - | unique |
| company_id | bigint unsigned | no | - |  |
| hcm_tax_governance_policy_id | bigint unsigned | no | - |  |
| event_type | varchar(64) | no | - |  |
| actor_user_id | bigint unsigned | yes | - | FK → users |
| before_state | json | yes | null |  |
| after_state | json | yes | null |  |
| note | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_tax_governance_projections

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | uuid | no | - | PK |
| company_id | bigint unsigned | no | - |  |
| policy_uuid | uuid | yes | - |  |
| status | enum(draft,submitted,approved,published,superseded,void) | no | - |  |
| version | int | no | 0 |  |
| effective_date | date | yes | null |  |
| end_date | date | yes | null |  |
| last_actor_user_id | bigint unsigned | yes | null |  |
| last_actor_action | enum(created,submitted,approved,published,superseded,voided) | yes | null |  |
| last_actor_timestamp | timestamp | yes | null |  |
| policy_complexity_score | int | no | 0 |  |
| anomaly_flags | json | yes | null |  |
| tenant_risk_level | enum(green,yellow,red) | no | green |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_terminations

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| department | varchar(150) | yes | null |  |
| termination_type | varchar(150) | no | - |  |
| reason | text | no | - |  |
| notice_date | date | no | - |  |
| termination_date | date | no | - |  |
| status | varchar(32) | no | pending |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| deleted_at | timestamp | yes | null | soft delete |
| company_id | bigint unsigned | yes | null |  |
| settlement_payroll_period | varchar(7) | yes | null |  |
| asset_return_notes | text | yes | null |  |
| clearance_notes | text | yes | null |  |
| settlement_payroll_period_id | bigint unsigned | yes | null |  |
| settlement_breakdown | json | yes | null |  |
| clearance_items | json | yes | null |  |
| termination_reason_code | varchar(64) | yes | null |  |
| legal_basis_code | varchar(64) | yes | null |  |
| policy_profile_key | varchar(64) | yes | null |  |
| policy_formula_version | varchar(32) | yes | null |  |
| workflow_stage | varchar(64) | yes | null |  |
| workflow_reviewed_by_user_id | bigint unsigned | yes | null |  |
| workflow_reviewed_at | timestamp | yes | null |  |
| workflow_approved_by_user_id | bigint unsigned | yes | null |  |
| workflow_approved_at | timestamp | yes | null |  |
| workflow_finalized_by_user_id | bigint unsigned | yes | null |  |
| workflow_finalized_at | timestamp | yes | null |  |
| non_asset_checklist | json | yes | null |  |

## hcm_thr_batch_lines

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| hcm_thr_batch_id | bigint unsigned | no | - | FK → hcm_thr_batches |
| user_id | bigint unsigned | no | - | FK → users |
| full_name | varchar(200) | no | - |  |
| employee_no | varchar(32) | yes | null |  |
| join_date_used | date | no | - |  |
| base_salary | decimal(15,2) | no | 0 |  |
| fixed_allowance | decimal(15,2) | no | 0 |  |
| reference_wage | decimal(15,2) | no | 0 |  |
| months_of_service | smallint unsigned | no | 0 |  |
| multiplier | decimal(12,6) | no | 0 |  |
| thr_gross | decimal(15,2) | no | 0 |  |
| row_status | varchar(24) | no | - |  |
| eligible | tinyint(1) | no | false |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| payment_status | varchar(24) | no | unpaid |  |
| payment_failure_reason | text | yes | null |  |
| payment_gateway_ref | varchar(128) | yes | null |  |
| paid_at | timestamp | yes | null |  |
| slip_storage_path | varchar(512) | yes | null |  |
| slip_generated_at | timestamp | yes | null |  |
| slip_notify_sent_at | timestamp | yes | null |  |
| last_disbursement_id | bigint unsigned | yes | - | FK |
| thr_slip_public_no | varchar(48) | yes | null |  |

## hcm_thr_batches

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| calendar_year | smallint unsigned | no | - |  |
| hcm_thr_yearly_setting_id | bigint unsigned | yes | - | FK → hcm_thr_yearly_settings |
| cutoff_date | date | no | - |  |
| grand_total_eligible | decimal(15,2) | no | 0 |  |
| eligible_line_count | int unsigned | no | 0 |  |
| total_line_count | int unsigned | no | 0 |  |
| status | varchar(24) | no | draft |  |
| assigned_at | timestamp | yes | null |  |
| assigned_by_user_id | bigint unsigned | yes | - | FK → users |
| hcm_payroll_period_id | bigint unsigned | yes | - | FK → hcm_payroll_periods |
| hcm_payroll_run_id | bigint unsigned | yes | - | FK → hcm_payroll_runs |
| generated_by_user_id | bigint unsigned | yes | - | FK → users |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_thr_disbursements

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| hcm_thr_batch_id | bigint unsigned | no | - | FK → hcm_thr_batches |
| status | varchar(24) | no | processing |  |
| driver | varchar(32) | no | stub |  |
| meta | json | yes | null |  |
| initiated_by_user_id | bigint unsigned | yes | - | FK → users |
| completed_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_thr_yearly_settings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| calendar_year | smallint unsigned | no | - |  |
| eid_date | date | no | - |  |
| payment_date | date | yes | null |  |
| calculation_cutoff_date | date | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_trainers

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| name | varchar(200) | no | - |  |
| email | varchar(200) | yes | null |  |
| phone | varchar(50) | yes | null |  |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## hcm_training_participants

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| training_id | bigint unsigned | no | - | FK → hcm_trainings |
| user_id | bigint unsigned | no | - | FK → users |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## hcm_training_types

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| name | varchar(200) | no | - | unique |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## hcm_trainings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| training_type_id | bigint unsigned | yes | - | FK → hcm_training_types |
| trainer_name | varchar(200) | yes | null |  |
| start_date | date | yes | null |  |
| end_date | date | yes | null |  |
| description | text | yes | null |  |
| cost_cents | int unsigned | no | 0 |  |
| status | varchar(24) | no | active |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |
| trainer_id | bigint unsigned | no | - | FK |

## hcm_user_role_audits

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | - | FK → companies |
| actor_user_id | bigint unsigned | yes | - | FK → users |
| target_user_id | bigint unsigned | no | - | FK → users |
| role_id | bigint unsigned | yes | - | FK → hcm_roles |
| action | varchar(80) | no | - |  |
| notes | text | yes | null |  |
| metadata | json | yes | null |  |
| created_at | timestamp | no | - | useCurrent |

## hcm_user_roles

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| company_id | bigint unsigned | no | - | FK → companies |
| role_id | bigint unsigned | no | - | FK → hcm_roles |
| assigned_by_user_id | bigint unsigned | yes | - | FK → users |
| status | varchar(30) | no | active |  |
| effective_from | date | yes | null |  |
| effective_until | date | yes | null |  |
| revoked_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## holiday_calendars

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| date | date | no | - |  |
| name | varchar(200) | no | - |  |
| is_national | tinyint(1) | no | false |  |
| is_joint_leave | tinyint(1) | no | false |  |
| deduct_from_leave | tinyint(1) | no | false |  |
| source | varchar(20) | no | manual |  |
| last_synced_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| holiday_id | bigint unsigned | yes | null |  |

## holidays

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| title | varchar(200) | no | - |  |
| holiday_date | date | no | - |  |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| source | varchar(20) | no | manual |  |
| last_synced_at | timestamp | yes | null |  |

## invoice_email_logs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | yes | - | unique |
| invoice_id | bigint unsigned | no | - | FK → invoices |
| invoice_uuid | uuid | yes | - |  |
| to_email | varchar(255) | no | - |  |
| status | enum(sent,failed) | no | - |  |
| provider_message_id | varchar(191) | yes | null |  |
| error_message | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| event_key | varchar(191) | yes | null |  |

## invoices

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| invoice_number | varchar(255) | no | - | unique |
| company_id | bigint unsigned | no | - | FK → companies |
| purchase_transaction_id | bigint unsigned | yes | - | FK → purchase_transactions |
| issue_date | date | no | - |  |
| due_date | date | no | - |  |
| amount_due | decimal(12,2) | no | - |  |
| is_paid | tinyint(1) | no | false |  |
| paid_date | datetime | yes | null |  |
| pdf_path | varchar(255) | yes | null |  |
| status | enum(draft,sent,viewed,paid,expired) | no | draft |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| subscription_id | bigint unsigned | no | - | FK |
| billing_tax_rate_snapshot | decimal(5,2) | yes | null |  |

## job_batches

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | varchar(255) | no | - |  |
| name | varchar(255) | no | - |  |
| total_jobs | int | no | - |  |
| pending_jobs | int | no | - |  |
| failed_jobs | int | no | - |  |
| failed_job_ids | longtext | no | - |  |
| options | mediumtext | yes | null |  |
| cancelled_at | int | yes | null |  |
| created_at | int | no | - |  |
| finished_at | int | yes | null |  |

## jobs

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| queue | varchar(255) | no | - |  |
| payload | longtext | no | - |  |
| attempts | tinyint unsigned | no | - |  |
| reserved_at | int unsigned | yes | null |  |
| available_at | int unsigned | no | - |  |
| created_at | int unsigned | no | - |  |

## leave_approval_workflow_steps

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| workflow_id | bigint unsigned | no | - | FK → leave_approval_workflows |
| level | tinyint unsigned | no | - |  |
| approver_scope | varchar(30) | no | - |  |
| approver_user_id | bigint unsigned | yes | - | FK → users |
| designation_id | bigint unsigned | yes | - | FK → designations |
| requires_all_approvers | tinyint(1) | no | true |  |
| sla_hours | smallint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_approval_workflows

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| leave_type_id | bigint unsigned | yes | - | FK → leave_types |
| name | varchar(200) | no | - |  |
| min_days | decimal(8,2) | no | 0 |  |
| max_days | decimal(8,2) | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| effective_from | date | no | - |  |
| effective_to | date | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_approvals

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| leave_request_id | bigint unsigned | no | - | FK → leave_requests |
| approver_id | bigint unsigned | no | - | FK → users |
| level | tinyint unsigned | no | 1 |  |
| status | varchar(20) | no | pending |  |
| acted_at | timestamp | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_blackout_dates

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| leave_type_id | bigint unsigned | yes | - | FK → leave_types |
| name | varchar(200) | no | - |  |
| rule_type | varchar(30) | no | hard_block |  |
| start_date | date | no | - |  |
| end_date | date | no | - |  |
| max_people_per_day | smallint unsigned | yes | null |  |
| reason | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_ledger

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| employee_id | bigint unsigned | no | - | FK → users |
| leave_type_id | bigint unsigned | no | - | FK → leave_types |
| policy_id | bigint unsigned | yes | - | FK → leave_policies |
| transaction_type | varchar(40) | no | - |  |
| amount | decimal(10,2) | no | - |  |
| balance_after | decimal(10,2) | yes | null |  |
| reference_type | varchar(50) | yes | null |  |
| reference_id | varchar(100) | yes | null |  |
| occurred_on | date | no | - |  |
| notes | text | yes | null |  |
| created_by | bigint unsigned | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_policies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| leave_type_id | bigint unsigned | no | - | FK → leave_types |
| name | varchar(200) | no | - |  |
| days_per_year | decimal(8,2) | no | 0 |  |
| min_service_months | smallint unsigned | no | 0 |  |
| is_prorated | tinyint(1) | no | false |  |
| carry_forward | tinyint(1) | no | false |  |
| max_carry_days | smallint unsigned | yes | null |  |
| expire_after_days | smallint unsigned | yes | null |  |
| is_earned_leave | tinyint(1) | no | false |  |
| allow_negative_balance | tinyint(1) | no | false |  |
| effective_from | date | no | - |  |
| effective_to | date | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_policy_assignments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| policy_id | bigint unsigned | no | - | FK → leave_policies |
| employee_id | bigint unsigned | no | - | FK → users |
| effective_date | date | no | - |  |
| end_date | date | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_request_attachments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| leave_request_id | bigint unsigned | no | - | FK → leave_requests |
| uploaded_by | bigint unsigned | yes | - | FK → users |
| document_type | varchar(40) | no | supporting_document |  |
| file_name | varchar(255) | no | - |  |
| file_path | varchar(500) | no | - |  |
| mime_type | varchar(120) | yes | null |  |
| file_size_bytes | bigint unsigned | yes | null |  |
| is_required | tinyint(1) | no | false |  |
| notes | text | yes | null |  |
| verified_by | bigint unsigned | yes | - | FK → users |
| verified_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_request_audits

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| leave_request_id | bigint unsigned | no | - | FK → leave_requests |
| actor_user_id | bigint unsigned | yes | - | FK → users |
| action | varchar(50) | no | - |  |
| from_status | varchar(20) | yes | null |  |
| to_status | varchar(20) | yes | null |  |
| changes | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## leave_request_breakdowns

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| leave_request_id | bigint unsigned | no | - | FK → leave_requests |
| leave_date | date | no | - |  |
| unit_type | varchar(20) | no | full_day |  |
| session | varchar(20) | yes | null |  |
| minutes | smallint unsigned | yes | null |  |
| is_working_day | tinyint(1) | no | true |  |
| is_holiday | tinyint(1) | no | false |  |
| holiday_name | varchar(200) | yes | null |  |
| deducted_days | decimal(6,2) | no | 0 |  |
| meta | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| holiday_calendar_id | bigint unsigned | no | - |  |

## leave_requests

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| leave_type | varchar(100) | no | - |  |
| date_from | date | no | - |  |
| date_to | date | no | - |  |
| days | decimal(5,1) | no | 1 |  |
| status | varchar(20) | no | pending |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| company_id | bigint unsigned | yes | null |  |

## leave_types

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| code | varchar(64) | no | - | unique |
| name | varchar(150) | no | - |  |
| is_paid | tinyint(1) | no | true |  |
| requires_approval | tinyint(1) | no | true |  |
| requires_attachment | tinyint(1) | no | false |  |
| deduct_from_balance | tinyint(1) | no | true |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## notification_deliveries

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | no | - | unique |
| event_key | varchar(191) | no | - |  |
| channel | enum(database,mail,sms,webhook) | no | database |  |
| status | varchar(32) | no | queued |  |
| notification_uuid | varchar(64) | yes | null |  |
| recipient | varchar(191) | yes | null |  |
| company_uuid | uuid | yes | - |  |
| attempt_count | int unsigned | no | 1 |  |
| last_error | text | yes | null |  |
| metadata | json | yes | null |  |
| sent_at | timestamp | yes | null |  |
| failed_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## notification_preferences

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | no | - | unique |
| user_id | bigint unsigned | no | - |  |
| event_key | varchar(191) | no | - |  |
| channel | enum(database,mail,sms,webhook) | no | database |  |
| enabled | tinyint(1) | no | true |  |
| digest_mode | enum(instant,daily,weekly) | no | instant |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| user_uuid | uuid | yes | - |  |

## notifications

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | uuid | no | - | PK |
| type | varchar(255) | no | - |  |
| data | text | no | - |  |
| read_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| user_uuid | uuid | yes | - |  |
| company_uuid | uuid | yes | - |  |

## overtime_requests

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → users |
| work_date | date | no | - |  |
| minutes | smallint unsigned | no | - |  |
| project_name | varchar(200) | yes | null |  |
| status | varchar(20) | no | pending |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| hcm_overtime_type_id | bigint unsigned | yes | - | FK → hcm_overtime_types |
| request_type | varchar(40) | no | employee_request |  |
| policy_note | varchar(500) | yes | null |  |
| approved_by_user_id | bigint unsigned | yes | - | FK → users |
| approved_at | timestamp | yes | null |  |
| hcm_salary_component_id | bigint unsigned | no | - | FK |
| company_id | bigint unsigned | yes | null |  |
| day_type | varchar(40) | yes | null |  |
| weekly_work_days | tinyint unsigned | yes | null |  |

## package_addons

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(255) | no | - | unique |
| name | varchar(255) | no | - |  |
| description | varchar(255) | yes | null |  |
| price_per_unit | decimal(12,2) | no | - |  |
| unit_name | varchar(255) | no | - |  |
| status | enum(active,inactive) | no | active |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## package_features

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| package_id | bigint unsigned | yes | - | FK → packages |
| feature_code | varchar(255) | no | - |  |
| feature_name | varchar(255) | no | - |  |
| limit | int | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| package_uuid | uuid | yes | - |  |

## packages

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(255) | no | - | unique |
| name | varchar(255) | no | - |  |
| description | varchar(255) | yes | null |  |
| monthly_price | decimal(12,2) | no | 0 |  |
| yearly_price | decimal(12,2) | no | 0 |  |
| billing_unit | enum(user,company,flat) | no | flat |  |
| status | enum(active,inactive,archived) | no | active |  |
| color | varchar(7) | no | #007bff |  |
| sort_order | int | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| is_global_admin_only | tinyint(1) | no | false |  |

## password_reset_tokens

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| email | varchar(255) | no | - |  |
| token | varchar(255) | no | - |  |
| created_at | timestamp | yes | null |  |

## payments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | no | - | FK → companies |
| subscription_id | bigint unsigned | yes | - | FK → subscriptions |
| purchase_transaction_id | bigint unsigned | yes | - | FK → purchase_transactions |
| invoice_id | bigint unsigned | yes | - | FK → invoices |
| amount | decimal(12,2) | no | - |  |
| currency | varchar(255) | no | IDR |  |
| status | enum(pending,completed,failed,disputed) | no | pending |  |
| payment_method | enum(bank_transfer,credit_card,e_wallet,cash,check) | yes | null |  |
| gateway | varchar(255) | yes | null |  |
| gateway_reference | varchar(255) | yes | null |  |
| paid_at | datetime | yes | null |  |
| verified_at | datetime | yes | null |  |
| notes | text | yes | null |  |
| metadata | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## performance_goal_types

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| name | varchar(120) | no | - |  |
| description | text | yes | null |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## performance_goals

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| goal_type_id | bigint unsigned | yes | - | FK → performance_goal_types |
| user_id | bigint unsigned | no | - | FK → users |
| manager_user_id | bigint unsigned | yes | - | FK → users |
| subject | varchar(200) | no | - |  |
| target_achievement | varchar(255) | yes | null |  |
| start_date | date | yes | null |  |
| end_date | date | yes | null |  |
| description | text | yes | null |  |
| status | enum(active,inactive,completed) | no | active |  |
| progress_percent | tinyint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## performance_reviews

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| company_id | bigint unsigned | yes | null |  |

## platform_monthly_financial_summaries

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| report_year | smallint unsigned | no | - |  |
| report_month | smallint unsigned | no | - |  |
| gross_revenue | decimal(18,2) | no | 0 |  |
| cleared_revenue | decimal(18,2) | no | 0 |  |
| uncleared_revenue | decimal(18,2) | no | 0 |  |
| disputed_revenue | decimal(18,2) | no | 0 |  |
| reversed_revenue | decimal(18,2) | no | 0 |  |
| tax_amount | decimal(18,2) | no | 0 |  |
| net_revenue | decimal(18,2) | no | 0 |  |
| report_status | varchar(16) | no | open |  |
| locked_at | timestamp | yes | null |  |
| locked_by_user_id | bigint unsigned | yes | null |  |
| lock_token | varchar(64) | yes | null |  |
| missing_tax_codes | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| tenant_billing_snapshots | json | yes | null |  |
| tax_snapshots_locked_at | timestamp | yes | null |  |

## platform_revenue_transactions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| uuid | uuid | no | - | unique |
| company_id | bigint unsigned | no | - |  |
| source_event_type | varchar(64) | no | - |  |
| source_entity_type | varchar(64) | yes | null |  |
| source_entity_id | bigint unsigned | yes | null |  |
| source_entity_uuid | uuid | yes | - |  |
| transaction_type | varchar(32) | no | - |  |
| amount | decimal(15,2) | no | 0 |  |
| tax_amount | decimal(15,2) | no | 0 |  |
| net_amount | decimal(15,2) | no | 0 |  |
| currency | char(3) | no | IDR |  |
| status | varchar(24) | no | posted |  |
| clearing_status | varchar(24) | no | uncleared |  |
| clearing_date | date | yes | null |  |
| dispute_reason | varchar(255) | yes | null |  |
| idempotency_key | varchar(191) | yes | null |  |
| occurred_at | timestamp | yes | null |  |
| metadata | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## policies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| department_id | bigint unsigned | yes | - | FK → departments |
| name | varchar(150) | no | - |  |
| description | text | no | - |  |
| effective_date | date | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| attachment_path | varchar(512) | yes | null |  |
| company_id | bigint unsigned | yes | - | FK → companies |
| deleted_at | timestamp | yes | null | soft delete |

## purchase_transactions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| transaction_code | varchar(255) | no | - | unique |
| company_id | bigint unsigned | no | - | FK → companies |
| subscription_id | bigint unsigned | yes | - | FK → subscriptions |
| transaction_type | enum(subscription,addon,refund,credit,manual) | no | subscription |  |
| description | varchar(255) | yes | null |  |
| amount | decimal(12,2) | no | - |  |
| tax_amount | decimal(12,2) | no | 0 |  |
| discount_amount | decimal(12,2) | no | 0 |  |
| total_amount | decimal(12,2) | no | - |  |
| billing_period_start | date | yes | null |  |
| billing_period_end | date | yes | null |  |
| due_date | datetime | yes | null |  |
| paid_at | datetime | yes | null |  |
| payment_method | enum(bank_transfer,credit_card,e_wallet,cash) | yes | null |  |
| payment_reference | varchar(255) | yes | null |  |
| status | enum(draft,issued,sent,paid,overdue,cancelled) | no | draft |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| package_addon_id | bigint unsigned | yes | null |  |

## report_data_blocks

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| snapshot_id | bigint unsigned | yes | null |  |
| module | varchar(80) | no | - |  |
| data_key | varchar(120) | no | - |  |
| data_value | json | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## report_exports

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| snapshot_id | bigint unsigned | yes | null |  |
| file_type | varchar(30) | no | - |  |
| file_url | varchar(500) | no | - |  |
| generated_at | timestamp | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## report_filters

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| snapshot_id | bigint unsigned | yes | null |  |
| filter_key | varchar(120) | no | - |  |
| filter_value | json | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## report_snapshots

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | yes | null |  |
| report_type | varchar(80) | no | - |  |
| period_start | date | yes | null |  |
| period_end | date | yes | null |  |
| generated_at | timestamp | yes | null |  |
| generated_by_user_id | bigint unsigned | yes | null |  |
| status | varchar(30) | no | pending |  |
| meta | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## sessions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | varchar(255) | no | - |  |
| user_id | bigint unsigned | yes | - | FK |
| ip_address | varchar(45) | yes | null |  |
| user_agent | text | yes | null |  |
| payload | longtext | no | - |  |
| last_activity | int | no | - |  |
| user_uuid | uuid | yes | - |  |

## settings

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| key | varchar(255) | no | - | unique |
| value | text | yes | null |  |
| group | varchar(255) | no | general |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## subscriptions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| company_id | bigint unsigned | no | - | FK → companies |
| plan_code | varchar(120) | no | starter |  |
| status | varchar(50) | no | trial |  |
| starts_at | timestamp | yes | null |  |
| ends_at | timestamp | yes | null |  |
| trial_ends_at | timestamp | yes | null |  |
| auto_renew | tinyint(1) | no | true |  |
| metadata | json | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| package_id | bigint unsigned | yes | - | FK → packages |
| billing_cycle | varchar(255) | no | monthly |  |
| amount | decimal(12,2) | no | 0 |  |
| terminated_at | timestamp | yes | null |  |
| termination_reason | text | yes | null |  |
| suspended_at | timestamp | yes | null |  |
| suspension_reason | text | yes | null |  |

## teams

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| department_id | bigint unsigned | yes | - | FK → departments |
| name | varchar(100) | no | - |  |
| is_active | tinyint(1) | no | true |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| team_lead_id | bigint unsigned | no | - |  |

## ticket_assignment_histories

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| ticket_id | bigint unsigned | no | - | FK → tickets |
| actor_user_id | bigint unsigned | no | - | FK → users |
| from_assignee_user_id | bigint unsigned | yes | - | FK → users |
| to_assignee_user_id | bigint unsigned | yes | - | FK → users |
| note | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## ticket_attachments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| ticket_id | bigint unsigned | no | - | FK → tickets |
| user_id | bigint unsigned | no | - | FK → (inferred) |
| disk | varchar(40) | no | public |  |
| path | varchar(500) | no | - |  |
| original_name | varchar(255) | no | - |  |
| mime_type | varchar(120) | yes | null |  |
| size_bytes | bigint unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## ticket_categories

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| name | varchar(120) | no | - | unique |
| is_active | tinyint(1) | no | true |  |
| sort_order | int unsigned | no | 0 |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## ticket_comments

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| ticket_id | bigint unsigned | no | - | FK → tickets |
| user_id | bigint unsigned | no | - | FK → (inferred) |
| body | text | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## tickets

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| user_id | bigint unsigned | no | - | FK → (inferred) |
| code | varchar(40) | no | - | unique |
| subject | varchar(255) | no | - |  |
| description | text | no | - |  |
| category | varchar(120) | yes | null |  |
| priority | enum(low,medium,high,urgent) | no | medium |  |
| status | enum(open,in_progress,resolved,closed) | no | open |  |
| sla_due_at | datetime | yes | null |  |
| assignee_user_id | bigint unsigned | yes | - | FK → users |
| resolver_user_id | bigint unsigned | yes | - | FK → users |
| resolved_at | datetime | yes | null |  |
| closed_at | datetime | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| deleted_at | timestamp | yes | null | soft delete |
| company_id | bigint unsigned | yes | null |  |
| category_id | bigint unsigned | no | - | FK |

## transactions

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| subscription_id | bigint unsigned | yes | null |  |
| invoice_number | varchar(255) | no | - | unique |
| amount | decimal(12,2) | no | - |  |
| status | enum(pending,completed,failed,refunded) | no | pending |  |
| payment_method | enum(credit_card,bank_transfer,e_wallet,other) | no | - |  |
| payment_gateway | varchar(255) | yes | null |  |
| transaction_id | varchar(255) | yes | null |  |
| notes | text | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## users

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| name | varchar(255) | no | - |  |
| email | varchar(255) | no | - | unique |
| email_verified_at | timestamp | yes | null |  |
| password | varchar(255) | no | - |  |
| remember_token | varchar(100) | yes | null |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
| uuid | uuid | yes | - | unique |
| company_id | bigint unsigned | yes | null |  |
| is_super_admin | tinyint(1) | no | - |  |

## wilayah_districts

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| regency_id | bigint unsigned | no | - | FK → wilayah_regencies |
| code | varchar(20) | no | - | unique |
| name | varchar(255) | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## wilayah_provinces

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| code | varchar(20) | no | - | unique |
| name | varchar(255) | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## wilayah_regencies

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| province_id | bigint unsigned | no | - | FK → wilayah_provinces |
| code | varchar(20) | no | - | unique |
| name | varchar(255) | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |

## wilayah_villages

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | no | AUTO_INCREMENT | PK |
| district_id | bigint unsigned | no | - | FK → wilayah_districts |
| code | varchar(30) | no | - | unique |
| name | varchar(255) | no | - |  |
| created_at | timestamp | yes | null |  |
| updated_at | timestamp | yes | null |  |
