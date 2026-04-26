# Termination Implementation

## Scope Teknis Saat Ini

Feature Termination saat ini mencakup:

- CRUD Termination admin-only;
- ownership-based read untuk employee pada endpoint detail/per-user list;
- snapshot settlement final di tabel `hcm_terminations`;
- preview settlement runtime dari payroll period terdekat;
- policy prorata termination untuk gaji pokok dan tunjangan tetap;
- enrichment kompensasi PKWT bila kontrak due pada bulan termination;
- clearance item asset dari assignment aktif yang belum return;
- action return asset langsung dari context Termination.

## Surface Utama

### Web / UI

- `/termination`
- `/employee-details` (section relation Termination)

### API

- `GET /v1/hcm/terminations`
- `POST /v1/hcm/terminations`
- `GET /v1/hcm/terminations/{id}`
- `PUT /v1/hcm/terminations/{id}`
- `DELETE /v1/hcm/terminations/{id}`
- `GET /v1/hcm/terminations/users/{userId}/terminations`
- `GET /v1/hcm/terminations/settlement-preview`
- `GET /v1/hcm/terminations/{id}/settlement-preview`
- `POST /v1/hcm/terminations/{id}/clearance-items/{assignmentId}/return`

## File Runtime Penting

- `backend/app/Http/Controllers/Api/HcmTerminationController.php`
- `backend/app/Models/HcmTermination.php`
- `backend/routes/api.php`
- `frontend/resources/js/termination-data.js`
- `backend/resources/views/hcm/partials/termination-modals.blade.php`
- `backend/resources/views/hcm/partials/termination-detail-modal.blade.php`

## Data Snapshot Yang Disimpan

- termination reason code terstruktur (`termination_reason_code`)
- legal basis code terstruktur (`legal_basis_code`)
- policy profile key (`policy_profile_key`)
- policy formula version (`policy_formula_version`)
- workflow stage (`workflow_stage`)
- workflow reviewed/approved/finalized actor + timestamp metadata
- non-asset obligation checklist (`non_asset_checklist`)
- settlement payroll period label + linked period id
- final salary / allowance / deduction / net
- breakdown settlement terstruktur
- clearance notes
- asset return notes
- clearance items terstruktur

## Rule Runtime Penting

- payload `userId` create/update memakai UUID user;
- record/path termination masih numeric legacy id;
- payload create/update menerima `terminationReasonCode` + `legalBasisCode` (opsional) dari taxonomy legal yang tervalidasi server;
- payload create/update menerima `workflowStage` (opsional) dan server akan menurunkan `status` kompatibilitas dari stage tersebut;
- payload create/update menerima `nonAssetChecklist[]` untuk kewajiban non-asset; item mandatory harus `completed` sebelum finalization berhasil;
- `finalized` minimal mewajibkan `clearanceNotes`;
- preview settlement tidak menunggu payroll monthly finalized: sistem tetap bisa fallback ke policy prorata existing;
- action clearance return memakai lifecycle asset existing via `AssetService::returnAsset`.

## Validasi & Regression

- backend: `php artisan test tests/Feature/TerminationApiTest.php`
- frontend: `npm run test:ui -- tests/ui/termination-api-contract.test.js tests/ui/termination.wiring.test.js tests/ui/employee-details-training.wiring.test.js`

## Catatan Anomali yang Disengaja Terbuka

- severance / leave payout / custom compensation policy belum menjadi bagian formula runtime;
- checklist non-asset masih belum punya approval step per item;
- agregasi lintas-purpose seperti THR belum dijadikan preview final multi-source.