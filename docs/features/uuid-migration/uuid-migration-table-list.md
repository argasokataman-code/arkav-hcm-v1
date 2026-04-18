# UUID Migration Batch Tracker

Tanggal pembaruan: 18 April 2026

## Ringkasan

Status global: Core PK/FK cutover complete in local database.

- Rollout kolom UUID: mayoritas selesai.
- Rollout FK UUID tambahan: mayoritas selesai.
- Final cutover PK integer ke UUID: sudah dieksekusi untuk core tables target.

## Legend status

- Done: migration tersedia dan termasuk rollout/backfill/hardening.
- In progress: sudah ada pondasi, masih menunggu penutupan lanjutan.
- Not done: belum ada implementasi final untuk target akhir.

## Tracker per migration

| Migration file | Domain | Status | Catatan |
|---|---|---|---|
| 2026_04_17_200000_add_uuid_to_users_table.php | Core auth | Done | Tambah/backfill uuid users |
| 2026_04_17_210000_add_uuid_to_batch1_tables.php | Core batch 1 | Done | Rollout uuid awal |
| 2026_04_17_220000_add_uuid_foreign_keys_for_batch1_tables.php | Core batch 1 FK | Done | Tambah FK berbasis uuid |
| 2026_04_17_229000_add_uuid_to_packages_table.php | Billing package | Done | Rollout uuid packages (historis overlap) |
| 2026_04_17_230000_add_uuid_fields_for_billing_batch2_tables.php | Billing batch 2 | Done | Tambah uuid fields domain billing |
| 2026_04_17_231000_add_uuid_to_packages_table.php | Billing package | Done | Duplikasi historis yang dijaga idempotent |
| 2026_04_17_240000_add_uuid_to_employee_history_batch1_tables.php | Employee history | Done | Batch 1 employee history |
| 2026_04_17_250000_add_uuid_to_employee_history_batch2_tables.php | Employee history | Done | Batch 2 employee history |
| 2026_04_17_260000_add_uuid_to_hcm_role_permission_batch1_tables.php | HCM RBAC | Done | Batch role-permission awal |
| 2026_04_18_000100_add_uuid_to_ticketing_batch_tables.php | Ticketing | Done | Rollout uuid ticketing |
| 2026_04_18_000200_add_uuid_to_asset_batch_tables.php | Asset | Done | Rollout uuid asset |
| 2026_04_18_000300_add_uuid_to_performance_batch_tables.php | Performance | Done | Rollout uuid performance |
| 2026_04_18_000400_add_uuid_to_leave_foundation_batch_tables.php | Leave foundation | Done | Rollout uuid foundation leave |
| 2026_04_18_000500_add_uuid_to_leave_future_batch_tables.php | Leave future | Done | Rollout uuid leave lanjutan |
| 2026_04_18_000600_add_uuid_to_reporting_and_goal_type_batch_tables.php | Reporting + goal type | Done | Rollout uuid reporting |
| 2026_04_18_000700_add_uuid_to_leave_request_batch_tables.php | Leave request | Done | Rollout uuid request leave |
| 2026_04_18_000800_add_uuid_to_payroll_thr_core_batch_tables.php | Payroll THR core | Done | Rollout uuid payroll core |
| 2026_04_18_000900_add_uuid_to_thr_payroll_assignment_batch_tables.php | Payroll assignment | Done | Rollout uuid assignment |
| 2026_04_18_001000_add_uuid_to_performance_training_batch_tables.php | Performance training | Done | Rollout uuid training |
| 2026_04_18_001100_add_uuid_to_org_domain_auth_batch_tables.php | Org/domain/auth | Done | Rollout uuid domain auth |
| 2026_04_18_001200_add_uuid_to_hr_core_batch_tables.php | HR core | Done | Rollout uuid HR core |
| 2026_04_18_001300_add_uuid_to_billing_support_batch_tables.php | Billing support | Done | Rollout uuid tabel support billing |
| 2026_04_18_001400_add_uuid_to_hcm_rbac_batch_tables.php | HCM RBAC | Done | Batch RBAC lanjutan |
| 2026_04_18_001500_add_uuid_to_reporting_schedule_batch_tables.php | Reporting schedule | Done | Rollout uuid schedule/report |
| 2026_04_18_001600_add_uuid_to_leave_workflow_and_company_settings_batch_tables.php | Leave workflow + company settings | Done | Rollout uuid workflow/settings |
| 2026_04_18_130000_switch_pk_to_uuid_core_tables.php | Final PK cutover | Not done | Masih no-op/checkpoint, belum switch PK/FK |
| 2026_04_24_000000_finalize_uuid_relations_for_billing_core_tables.php | Billing relation recovery | Done | Menutup gap relasi billing akibat urutan migrasi |
| 2026_04_26_130000_fix_missing_uuid_relations_for_billing_parents.php | Billing relation recovery | Done | Fix relasi uuid parent billing yang tertinggal |
| 2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables.php | Final core PK cutover | Done | PK cutover core tables pertama dijalankan sebagai checkpoint awal |
| 2026_04_26_170000_finalize_uuid_full_cutover_core_tables.php | Final core PK/FK cutover | Done | Rebind inbound FK ke uuid dan swap PK core tables |
| 2026_04_26_180000_add_uuid_primary_keys_to_company_users_and_hcm_user_roles.php | Final core PK tail cleanup | Done | Menyelesaikan sisa core tables yang belum punya uuid PK |

## Remaining work checklist

| Item | Status | Notes |
|---|---|---|
| Final desain cutover PK/FK core tables | Done | Urutan drop/rebind FK sudah tervalidasi di database lokal |
| Implement migration cutover non-no-op | Done | Migration final core sudah dijalankan |
| Regression test pasca cutover | Not done | Wajib sebelum rollout produksi |
| Audit model/service/query raw | In progress | Pastikan integer PK bukan identifier utama |
| Sign-off dokumen closure | In progress | Sinkronkan README + STEPS + tracker ini |
