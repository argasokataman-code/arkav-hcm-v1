# HCM — BPJS Governance API

Prefix: `/v1/hcm/bpjs-governance`  
Middleware: `api.token` + `tenant.context` + server-side RBAC (`ChecksPermissions`)

Status dokumen: `active runtime (standalone BPJS module)`.

## Prinsip Kontrak

1. Domain BPJS dipisah dari Tax Governance (PPh 21) dan tidak bergantung pada master salary component.
2. Semua endpoint menggunakan envelope standar: `{ success, data?, error? }`.
3. Endpoint policy dan membership bersifat tenant-scoped melalui `X-Company-Id`.
4. Identifier path:
  - Policy update menggunakan UUID policy (`/policies/{policyRef}`) dengan fallback numeric ID selama masa transisi
   - Membership update menggunakan numeric user ID (`/employee-membership/{userId}`)
5. Scope compliance/membership BPJS untuk payroll **mengecualikan role `owner`** (owner tenant tidak dihitung sebagai peserta payroll default).

## RBAC Minimum

| Endpoint | Method | Permission minimum |
|---|---|---|
| `/reference` | GET | `payroll.view` |
| `/policies` | GET | `payroll.view` |
| `/policies` | POST | `payroll.manage` (locked, selalu `422 BPJS_POLICY_CREATE_DISABLED`) |
| `/policies/{policyRef}` | PUT | `payroll.manage` |
| `/employee-membership` | GET | `payroll.view` |
| `/employee-membership/{userId}` | PUT | `employee.manage` |
| `/reports` | GET | `payroll.view` |

## Endpoint Contract

### `GET /v1/hcm/bpjs-governance/reference`

Response `200`:
- `programCodes[]`: enum program BPJS (`bpjs_kesehatan`, `jht`, `jp`, `jkk`, `jkm`)
- `contributionParties[]`: enum porsi iuran (`employee`, `employer`)
- `wageBases[]`: enum basis upah (`wage_bpjs_health`, `wage_bpjs_tk`, `fixed_nominal`)
- `regulatoryNotes[]`: catatan regulasi BPJS

### `GET /v1/hcm/bpjs-governance/policies`

Query opsional:
1. `active_only` (boolean)
2. `as_of` (date `YYYY-MM-DD`)

Response `200`:
- `data.items[]`:
  - `id`
  - `programCode`
  - `contributionParty`
  - `ratePercent`
  - `wageBase`
  - `effectiveStartDate`
  - `effectiveEndDate`
  - `legalBasis`
  - `notes`
  - `isActive`
- `data.meta.total`
- `data.meta.as_of`

### `POST /v1/hcm/bpjs-governance/policies`

Status endpoint: **locked untuk tenant runtime**.

Response:
- `422` dengan `error.code = BPJS_POLICY_CREATE_DISABLED`
- `403` jika tanpa `payroll.manage`

### `PUT /v1/hcm/bpjs-governance/policies/{policyRef}`

Path:
1. `policyRef` UUID policy (utama) atau numeric ID legacy

Body partial yang diperbolehkan:
1. `ratePercent`
2. `legalBasis`
3. `notes`
4. `isActive`

Field immutable (akan ditolak `422` jika dikirim):
1. `programCode`
2. `contributionParty`
3. `wageBase`
4. `effectiveStartDate`
5. `effectiveEndDate`

Response:
- `200` success update
- `404` `BPJS_POLICY_NOT_FOUND` jika policy tidak ditemukan di tenant aktif

### `GET /v1/hcm/bpjs-governance/employee-membership`

Query opsional:
1. `search` (string)
2. `page` (int, default 1)
3. `perPage` (int, default 20, max 100)

Response `200`:
- `data.items[]`:
  - `id`
  - `uuid`
  - `fullName`
  - `email`
  - `bpjsKesehatanNo`
  - `bpjsKetenagakerjaanNo`
  - `membershipStatus` (`complete|partial|missing`)
  - `effectiveDate`
- `data.meta.page`
- `data.meta.perPage`
- `data.meta.total`
- `data.meta.complete`

### `PUT /v1/hcm/bpjs-governance/employee-membership/{userId}`

Path:
1. `userId` numeric user id di tenant aktif

Body:
1. `bpjsKesehatanNo` (nullable)
2. `bpjsKetenagakerjaanNo` (nullable)
3. `effectiveDate` (nullable date)

Response `200`:
- `data.userId`
- `data.bpjsKesehatanNo`
- `data.bpjsKetenagakerjaanNo`
- `data.effectiveDate`
- `data.membershipStatus`

Error:
- `404` `EMPLOYEE_NOT_FOUND` jika user tidak aktif pada tenant

### `GET /v1/hcm/bpjs-governance/reports`

Response `200`:
- `data.reportingPeriod`
- `data.policyActiveCount`
- `data.programCoverage`
- `data.rateAudit.items[]`
  - `policyId`
  - `programCode`
  - `contributionParty`
  - `ratePercent`
  - `expectedRateMin`
  - `expectedRateMax`
  - `expectedWageBase`
  - `ratePass`
  - `wageBasePass`
  - `legalBasisPass`
  - `result`
- `data.rateAudit.reviewRequiredCount`
- `data.employeeMembership.totalEmployees`
- `data.employeeMembership.complete`
- `data.employeeMembership.partial`
- `data.employeeMembership.missing`
- `data.employeeMembership.completionRate`
- `data.score`
- `data.checks[]`
  - `code`
  - `label`
  - `pass`
  - `evidence` (objek evidence audit per check)
    - khusus `membership_coverage`: `evidence.nonCompliantEmployees[]` berisi detail karyawan tidak patuh (`userId`, `userUuid`, `fullName`, `email`, `membershipStatus`, `issues[]`)

## Error Contract Minimum

1. `401` Unauthorized (token invalid/expired)
2. `403` Forbidden (`AUTH_FORBIDDEN`)
3. `404` Resource not found (`BPJS_POLICY_NOT_FOUND`, `EMPLOYEE_NOT_FOUND`)
4. `422` Validation error
