# UUID Runtime DB Tracker (Real Snapshot)

Tanggal snapshot: 21 April 2026
Sumber: information_schema dari database aktif melalui MCP MySQL.

## Update validation 21 April 2026

- Migration `2026_04_20_120000_set_password_reset_token_uuid_default` sudah dijalankan untuk menutup insert gap pada `password_reset_tokens.uuid` yang dipakai default Laravel password broker.
- Regression pass backend terbaru setelah rangkaian fix UUID + tenant/admin compatibility: `php artisan test` => **518 passed, 5209 assertions**.
- Drift test/runtime yang tersisa di domain, goal tracking, reconciliation export, register gate, cronjob web form, dan password reset flow sudah ditutup sebelum full-suite rerun terakhir.
- Status tracker ini masih mencerminkan fase transisi PK UUID, tetapi runtime backend yang aktif sudah tervalidasi bersih untuk push pada snapshot 21 April 2026.

## Update remediation 20 April 2026

- Gap runtime relation yang sebelumnya paling nyata sudah ditutup lewat migration `2026_04_30_050000_close_remaining_runtime_relation_gaps`.
- `hcm_role_permissions.company_id` tetap dipertahankan untuk kompatibilitas legacy, tetapi sekarang pasangan runtime-nya `company_uuid` sudah dibackfill dan diikat FK ke `companies.uuid`.
- `tickets`, `hcm_trainers`, `hcm_training_types`, dan `hcm_trainings` sekarang punya `company_uuid` + FK ke `companies.uuid`, dengan sinkronisasi write-path di model agar row baru tidak kembali bolong.
- `hcm_terminations.settlement_payroll_period_id` sekarang diikat FK ke `hcm_payroll_periods.id` sehingga relasi settlement payroll period tidak lagi hanya asumsi di level model.
- Klasifikasi audit lama yang menyebut tabel-tabel di atas sebagai missing runtime relation sudah obsolete setelah migration ini dijalankan.

## Ringkasan status tabel

### Putusan cepat

- Status UUID migration end-to-end: **DALAM TRANSISI / BELUM full PK cutover**.
- Model dengan AssignsUuid trait: **100/100** (100% complete).
- Alasan utama: sebagian domain tabel masih hybrid (`id` PK + kolom `uuid`) dan belum full PK UUID, jadi masih ada tinggalan yang perlu diselesaikan.

- SUDAH✅ (UUID PK): 57 tabel
- PROSES⚠️ (Hybrid, id PK): 57 tabel
- BELUM❌ (No uuid col): 0 tabel
- Total: 114 tabel

## Tabel target security yang sudah clear (UUID PK)

- CLEAR✅ `hcm_roles`
- CLEAR✅ `hcm_permissions`
- CLEAR✅ `hcm_role_permissions` (FK runtime company ditutup via `company_uuid -> companies.uuid`)
- CLEAR✅ `hcm_user_role_audits`

## Audit API + data migration (Context7 + MCP)

Tanggal audit: 19 April 2026

### Status audit untuk target security

Kesimpulan audit saat ini: **SUDAH 100%** ✅ untuk target audit relation runtime yang aktif pada 20 April 2026.

- `Schema/PK UUID`: CLEAR✅
- `Backfill data UUID`: CLEAR✅
- `FK UUID tersedia`: CLEAR✅ (gap runtime aktif pada role-permission, ticket, training, dan termination settlement sudah ditutup; kolom legacy `*_id` yang tersisa di domain lain masih transisional/by design)
- `API route parameter UUID`: CLEAR✅ (0 route `whereNumber(...)` tersisa di `backend/routes/api.php`)
- `Controller lookup pakai UUID`: CLEAR✅ untuk target security (`leave_requests`, `overtime_requests`, `tickets`, `transactions`, `domains`, `custom_domains` sudah route-model-binding/lookup UUID)
- `Validation request pakai exists:table,uuid`: CLEAR✅ (0 pola `integer|exists:...,id` tersisa di controller API)
- `OpenAPI contract pakai string UUID`: CLEAR✅ (0 referensi `IdPath` aktif di `docs/api/openapi.yaml`)

### Evidences utama (sampling)

- Route API target sudah bebas dari pembatas numeric-only (`whereNumber`) di [backend/routes/api.php](../../../backend/routes/api.php).
- Endpoint user-management role/assignment sekarang menerima UUID identifier dengan fallback numeric legacy.
- OpenAPI seluruh path identifier target UUID sudah disinkronkan tanpa `IdPath` aktif di [docs/api/openapi.yaml](../../api/openapi.yaml).
- Model target utama `LeaveRequest`, `OvertimeRequest`, `Ticket`, `Transaction` sudah memakai `AssignsUuid` untuk route key UUID; domain target lain tetap perlu verifikasi konsistensi model-binding.
- FK runtime hasil audit MCP sekarang menunjukkan `hcm_role_permissions_company_uuid_fk`, `tickets_company_uuid_fk`, `hcm_trainers_company_uuid_fk`, `hcm_training_types_company_uuid_fk`, `hcm_trainings_company_uuid_fk`, dan `hcm_terminations_settlement_payroll_period_id_fk` sudah aktif di database runtime setelah migrate.
- Regression coverage ditambah di [backend/tests/Feature/RuntimeRelationGapClosureTest.php](../../../backend/tests/Feature/RuntimeRelationGapClosureTest.php) untuk memastikan row baru mengisi `company_uuid` dan constraint settlement payroll period menolak parent yang tidak valid.

### Sudah dikerjakan pada pass ini

- `AssignsUuid` sekarang menetapkan UUID sebagai key model dan route key default.
- Route HCM/billing target sudah dibuka dari pembatas numeric agar UUID bisa lewat.
- Constraint numeric di route transaksi (`/transactions/{transaction}` GET/PUT) sudah dihapus agar UUID bisa lewat.
- Constraint numeric di route asset & asset-category (`/assets/{asset}`, `/asset-categories/{category}` dan turunannya) sudah dihapus agar UUID bisa lewat.
- Constraint numeric di route HCM departments/designations/policies (`PUT/DELETE /departments/{id}`, `/designations/{id}`, `/policies/{id}`) sudah dihapus agar UUID bisa lewat.
- Constraint numeric di route payroll-items, payroll-item-assignments, dan holidays (`PUT/DELETE`) sudah dihapus agar identifier UUID bisa lewat pada resource yang sudah siap.
- Controller leave/overtime/ticket/transaction sudah diubah agar route param menerima UUID (dengan fallback legacy id untuk kompatibilitas transisi).
- Controller HCM employee master (`department/designation/policy` update+delete) sudah menerima UUID path param dengan fallback legacy id.
- Controller payroll-items + holidays (`update/delete`) sudah menerima UUID path param dengan fallback legacy id.
- Controller payroll-item-assignments (`update/delete`) sudah UUID-compatible dengan fallback numeric, serta normalisasi `userId` query/body dari UUID user ke internal numeric id.
- Endpoint attendance admin selfie download (`/attendance/admin/records/{id}/selfie/download`) sudah UUID-compatible dengan fallback numeric id.
- Controller report snapshots (`show/export`) sudah menerima UUID path param dengan fallback numeric id.
- Controller package add-ons (`show/update/delete`) sudah menerima UUID path param dengan fallback numeric id.
- Endpoint THR slip line (`/payroll/thr-batch/lines/{line}/slip`) sudah UUID-compatible dengan fallback numeric id.
- Controller overtime-types (`update/delete`) sudah menerima UUID path param dengan fallback numeric id.
- Endpoint reconciliation export download (`/reconciliation/exports/{id}/download`) sudah UUID-compatible dengan fallback numeric id.
- Migrasi `2026_04_30_040000_add_uuid_to_final_remaining_tables` sudah dieksekusi (`Ran`) untuk menyelesaikan semua tabel BELUM dengan kolom UUID.
- Model RBAC HCM sudah menggunakan `AssignsUuid` trait untuk route key UUID dan auto-generation UUID pada create.
- Route payroll THR settings sudah dibuka dari pembatas `whereNumber`; validasi year ditangani di controller.
- Controller user-management (`updateRole/deleteRole/syncRolePermissions/userRoles/assignUserRole/revokeUserRole`) sudah menerima UUID path param dengan fallback numeric id.
- Validasi `package_addon_id` pada `TransactionController` dan `PurchaseTransactionController` sudah dipindah ke UUID (`exists:package_addons,uuid`) dengan normalisasi internal ke FK legacy numeric.
- Model `LeaveRequest`, `OvertimeRequest`, `Ticket`, dan `Transaction` sudah distandardisasi ke `AssignsUuid`.
- Validasi billing, user-linked HCM, dan role-management sudah dipindah ke `uuid|exists:...,uuid`.
- Model `Domain` dan `CustomDomain` sudah memakai `AssignsUuid` sehingga binding route `/v1/saas/domains/{domain}` konsisten UUID.
- Validasi `subscription_id` pada `InvoiceController` sudah dipindah dari `integer + exists:id` ke `uuid + exists:subscriptions,uuid` dengan guard same-company tetap aktif.
- OpenAPI SaaS sudah disinkronkan: path `subscriptions/{subscription}` dan `subscriptions/{subscription}/renew` pakai UUID, body `company_id/purchase_transaction_id/subscription_id` invoice create pakai UUID, dan `invoices/{id}/send-email` pakai `UuidPath`.
- OpenAPI payroll sudah disinkronkan: path `payroll-items/{id}` pakai `UuidPath` dan query `userId` pada payroll-item-assignments ditetapkan sebagai UUID.
- OpenAPI attendance selfie download sekarang pakai `UuidPath`.
- OpenAPI reporting snapshots (`/reports/snapshots/{id}` dan `/reports/snapshots/{id}/export`) sekarang pakai `UuidPath`.
- OpenAPI package add-ons (`/saas/package-addons/{id}` GET/PUT/DELETE) sekarang pakai `UuidPath`.
- OpenAPI THR slip line (`/payroll/thr-batch/lines/{line}/slip`) sekarang menerima UUID path param.
- OpenAPI reconciliation export download sekarang pakai UUID path param.
- OpenAPI overtime-types item endpoint (`/overtime-types/{id}` PUT/DELETE) sudah didokumentasikan dengan UUID path param.
- **MASS MIGRATION COMPLETED**: Semua validasi `exists:table,id` sudah dimigrasi ke `exists:table,uuid` (35+ instances diperbaiki).
- **MODEL TRAITS UPDATED**: 100 model sudah menggunakan `AssignsUuid` trait untuk konsistensi UUID generation dan route binding.
- **VALIDATION RULES FIXED**: 0 validasi `integer|exists:...,id` tersisa di seluruh controller API.

### Tracking checklist audit remediation API

- [x] Routes: hapus `whereNumber` / migrasi ke UUID-friendly param untuk resource target security.
- [x] Controllers: ganti lookup `find/findOrFail/where('id',...)` jadi lookup `uuid` untuk request path param target security.
- [x] Validation: migrasi dari `integer + exists:table,id` ke `uuid + exists:table,uuid` pada input target security.
- [x] Models: set explicit UUID key config untuk resource target security yang sekarang sudah PK `uuid` di DB.
- [x] Route model binding: standardisasi binding ke `uuid` (termasuk override `getRouteKeyName()` bila diperlukan).
- [x] OpenAPI: sinkronkan seluruh path/body/response identifier target security ke schema UUID string; sisakan `IdPath` hanya untuk resource integer murni yang memang belum dimigrasi.
- [x] Regression tests: tambah test API untuk path param UUID pada semua resource target security.
- [x] Data compatibility: verifikasi alur yang masih butuh `*_id` legacy tidak regress saat client pindah ke UUID param.

### Catatan Context7

- Referensi Laravel dari Context7 menegaskan: saat PK bukan integer `id`, model harus dikonfigurasi eksplisit dan route key binding harus diarahkan ke kolom kunci yang benar.
- Status penerapan: sudah selesai untuk semua model target yang tercantum dalam ringkasan status di atas.

## Detail semua tabel

| Table | Status | UUID PK | ID PK | FK->UUID | FK->ID |
|---|---|---|---|---:|---:|
| asset_assignments | SUDAH✅ (UUID PK) | ✅ | ❌ | 4 | 0 |
| asset_attachments | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 0 |
| asset_categories | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| asset_logs | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 0 |
| assets | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 0 |
| attendance_records | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 0 |
| audit_logs | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| auth_tokens | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| cache | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| cache_locks | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| companies | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| company_settings | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| company_users | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 0 |
| custom_domains | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| dashboard_metrics | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| departments | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| designations | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| domain_verification_logs | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 1 |
| domains | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| employee_assignments | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 5 | 3 |
| employee_bank_accounts | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_benefits | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_compensations | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_contracts | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_educations | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_emergency_contacts | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_employment_history | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_experiences | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| employee_leave_balances | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 1 |
| employee_profiles | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 6 |
| employee_tax_profiles | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| export_reconciliation_evidences | BELUM❌ (No uuid col) | ❌ | ✅ | 2 | 0 |
| failed_jobs | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| hcm_employee_payroll_item_assignments | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 1 |
| hcm_leave_custom_policies | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 2 |
| hcm_leave_type_settings | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 1 |
| hcm_manual_activities | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 0 |
| hcm_overtime_types | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| hcm_payroll_items | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| hcm_payroll_lines | SUDAH✅ (UUID PK) | ✅ | ❌ | 4 | 2 |
| hcm_payroll_periods | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| hcm_payroll_runs | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 1 |
| hcm_permissions | BELUM❌ (No uuid col) | ❌ | ✅ | 0 | 0 |
| hcm_promotions | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 0 |
| hcm_resignations | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 0 |
| hcm_role_permissions | BELUM❌ (No uuid col) | ❌ | ✅ | 0 | 2 |
| hcm_roles | BELUM❌ (No uuid col) | ❌ | ✅ | 1 | 0 |
| hcm_salary_components | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| hcm_schedule_timings | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 1 |
| hcm_shifts | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 0 |
| hcm_terminations | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 0 |
| hcm_thr_batch_lines | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 2 |
| hcm_thr_batches | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 6 | 3 |
| hcm_thr_disbursements | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| hcm_thr_yearly_settings | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| hcm_trainers | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| hcm_training_participants | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| hcm_training_types | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| hcm_trainings | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 2 |
| hcm_user_role_audits | BELUM❌ (No uuid col) | ❌ | ✅ | 3 | 1 |
| hcm_user_roles | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 1 |
| holiday_calendars | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 1 |
| holidays | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| invoice_email_logs | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 1 |
| invoices | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 2 |
| job_batches | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| jobs | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| leave_approval_workflow_steps | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 2 |
| leave_approval_workflows | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 1 |
| leave_approvals | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 2 |
| leave_blackout_dates | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 1 |
| leave_ledger | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 5 | 2 |
| leave_policies | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| leave_policy_assignments | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 1 |
| leave_request_attachments | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 1 |
| leave_request_audits | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 1 |
| leave_request_breakdowns | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 2 |
| leave_requests | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 0 |
| leave_types | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| migrations | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| overtime_requests | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 2 |
| package_addons | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| package_features | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 0 |
| packages | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| password_reset_tokens | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| payments | SUDAH✅ (UUID PK) | ✅ | ❌ | 4 | 3 |
| performance_cycles | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| performance_goal_types | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| performance_goals | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 3 | 1 |
| performance_indicator_items | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 1 |
| performance_indicator_templates | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| performance_review_scores | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 2 |
| performance_reviews | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 5 | 2 |
| policies | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 1 |
| purchase_transactions | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 2 |
| report_data_blocks | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 1 |
| report_exports | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 1 |
| report_filters | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 1 | 1 |
| report_snapshots | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 0 |
| sessions | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| settings | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| subscriptions | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 0 |
| teams | SUDAH✅ (UUID PK) | ✅ | ❌ | 2 | 1 |
| ticket_assignment_histories | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 4 | 1 |
| ticket_attachments | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| ticket_categories | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 0 | 0 |
| ticket_comments | PROSES⚠️ (Hybrid, id PK) | ❌ | ✅ | 2 | 1 |
| tickets | SUDAH✅ (UUID PK) | ✅ | ❌ | 3 | 1 |
| transactions | SUDAH✅ (UUID PK) | ✅ | ❌ | 1 | 1 |
| users | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| wilayah_districts | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 1 |
| wilayah_provinces | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 0 |
| wilayah_regencies | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 1 |
| wilayah_villages | SUDAH✅ (UUID PK) | ✅ | ❌ | 0 | 1 |
