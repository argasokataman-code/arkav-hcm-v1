# HCM — Employee Allowance Governance API

Prefix: `/v1/hcm/allowance-governance`  
Middleware: `api.token` + `tenant.context` + server-side RBAC (`ChecksPermissions`)

Status dokumen: `active runtime baseline (policy + assignment + compliance)`.

## Prinsip Kontrak

1. Domain allowance umum dipisah dari domain BPJS dan PPh21.
2. Semua endpoint memakai envelope standar `{ success, data?, error? }`.
3. Scope tenant wajib lewat `X-Company-Id`.
4. Scope payroll allowance **mengecualikan role `owner`** secara default.
5. Runtime tidak lagi menyuntik baseline policy allowance statis; semua policy allowance dibuat eksplisit oleh tenant/admin.

## RBAC Minimum

| Endpoint | Method | Permission minimum |
|---|---|---|
| `/reference` | GET | `payroll.view` |
| `/policies` | GET | `payroll.view` |
| `/policies/history` | GET | `payroll.view` |
| `/policies` | POST | `payroll.manage` |
| `/policies/{policyRef}` | PATCH | `payroll.manage` |
| `/policies/{policyRef}/activate` | POST | `payroll.manage` |
| `/assignments` | GET | `payroll.view` |
| `/assignments` | POST | `payroll.manage` |
| `/assignments/{assignmentRef}` | PATCH | `payroll.manage` |
| `/reports/compliance` | GET | `payroll.view` |
| `/reports/compliance/export` | GET | `payroll.view` |

## Identifier Contract

1. `{policyRef}`: UUID policy (utama), numeric ID fallback legacy.
2. `{assignmentRef}`: UUID assignment (utama), numeric ID fallback legacy.

## Endpoint Contract

### `GET /v1/hcm/allowance-governance/reference`

Response `200`:
1. `policyStatuses[]`
2. `assignmentStatuses[]`
3. `amountTypes[]`
4. `frequencies[]`

### `GET /v1/hcm/allowance-governance/policies`

Query opsional:
1. `active_only` (boolean)
2. `as_of` (date)

Response `200`:
1. `data.items[]`
2. `data.meta.total`
3. `data.meta.as_of`

Catatan:
1. Endpoint ini murni membaca policy tenant yang sudah ada; tidak lagi membuat seed baseline runtime.

### `GET /v1/hcm/allowance-governance/policies/history`

Query opsional:
1. `limit` (1-200)

Response `200`:
1. `data.items[]`
2. `data.meta.total`
3. `data.meta.limit`

### `POST /v1/hcm/allowance-governance/policies`

Body minimum:
1. `code`
2. `name`
3. `effectiveStartDate`

Body opsional:
1. `isTaxable`
2. `isMandatory`
3. `defaultAmount`
4. `effectiveEndDate`
5. `status` (`draft|active|superseded|archived`)
6. `isActive`
7. `notes`

Response:
1. `201` success create
2. `422` validation error

### `PATCH /v1/hcm/allowance-governance/policies/{policyRef}`

Body partial update diperbolehkan untuk field policy mutable.

Response:
1. `200` success update
2. `404` `ALLOWANCE_POLICY_NOT_FOUND`
3. `422` validation error

### `POST /v1/hcm/allowance-governance/policies/{policyRef}/activate`

Body opsional:
1. `effectiveStartDate`
2. `notes`

Response:
1. `200` policy menjadi `active`
2. `404` `ALLOWANCE_POLICY_NOT_FOUND`

### `GET /v1/hcm/allowance-governance/assignments`

Query opsional:
1. `search`
2. `policyRef`
3. `status`
4. `as_of`
5. `page`
6. `perPage`

Response `200`:
1. `data.items[]`
2. `data.meta.page`
3. `data.meta.perPage`
4. `data.meta.total`
5. `data.meta.as_of`

Catatan:
1. `data.items[]` berasal dari assignment payroll item aktif dengan kategori `fixed_allowance`.
2. Mulai Mei 2026, governance **tidak lagi** menampilkan source tunjangan dari `employee_compensations`; semua allowance operasional harus masuk lewat assignment governance/payroll item.

### `POST /v1/hcm/allowance-governance/assignments`

Body minimum:
1. `policyRef`
2. `userId`
3. `effectiveStartDate`

Body opsional:
1. `amountOverride`
2. `effectiveEndDate`
3. `status` (`draft|active|suspended|ended`)
4. `isActive`
5. `notes`

Error khusus:
1. `ALLOWANCE_POLICY_NOT_FOUND`
2. `ALLOWANCE_EMPLOYEE_NOT_FOUND`
3. `422` overlap assignment aktif pada user + policy + periode

### `PATCH /v1/hcm/allowance-governance/assignments/{assignmentRef}`

Body partial update diperbolehkan untuk field assignment mutable.

Response:
1. `200` success update
2. `404` `ALLOWANCE_ASSIGNMENT_NOT_FOUND`
3. `422` validation error/overlap

### `GET /v1/hcm/allowance-governance/reports/compliance`

Response `200`:
1. `data.reportingPeriod`
2. `data.activePolicyCount`
3. `data.mandatoryPolicyCount`
4. `data.employeeScopeCount`
5. `data.score`
6. `data.checks[]`

Checklist baseline saat ini:
1. `default_baseline_seeded`
2. `mandatory_assignment_coverage`
3. `assignment_overlap_guard`

Pada `mandatory_assignment_coverage`, evidence menyertakan `nonCompliantEmployees[]` agar actionable per karyawan.

Catatan compliance saat ini:
1. Karyawan dianggap comply bila memiliki minimal satu tunjangan aktif.
2. Sumber tunjangan aktif hanya dihitung dari assignment payroll item kategori `fixed_allowance` yang aktif pada tanggal evaluasi.

### `GET /v1/hcm/allowance-governance/reports/compliance/export`

Response `200`:
1. JSON attachment (`Content-Disposition: attachment`)
2. Payload mengikuti struktur `/reports/compliance`

## Error Contract Minimum

1. `401` Unauthorized
2. `403` Forbidden (`AUTH_FORBIDDEN`)
3. `404` Not Found (`ALLOWANCE_POLICY_NOT_FOUND`, `ALLOWANCE_ASSIGNMENT_NOT_FOUND`, `ALLOWANCE_EMPLOYEE_NOT_FOUND`)
4. `422` Validation Error
