# Team Management

## ⚠️ Implementation Status & Existing UI Impact

**Status:** ✅ Phase 1 Complete + ✅ Phase 2 Complete + ✅ Phase 3 Complete (Backend Polish) + ✅ Phase 4.5 Access & Ops Hardening  
**Existing UI Changes:** ✅ Documented in [IMPACT-ANALYSIS.md](./IMPACT-ANALYSIS.md)

**Key existing UI modifications during Phase 1:**
- ✅ Sidebar: Add "Teams" menu item (non-breaking addition)
- ✅ Employee Form: Add team dropdown field (optional, backward compatible)
- ✅ All existing flows: NO removal, NO breaking changes

**QA Regression Checks:** See §D.5 in [IMPACT-ANALYSIS.md](./IMPACT-ANALYSIS.md#d5-uiux) for pre-deployment validation checklist.

---

## Ringkasan

Fitur ini mendokumentasikan master entity **Team** sebagai workgroup formal lintas departemen dalam satu tenant/company. Team memungkinkan admin untuk mengelola struktur kerja yang lebih fleksibel: employee dari berbagai departemen dapat digabungkan dalam satu team dengan karakteristik shift/jenis pekerjaan yang sama (misal: Customer Service 24 Jam, Engineering Core, Sales Division 1, Finance Receivables).

Team adalah entitas **independent dari Department dan Designation**, meskipun setiap team memiliki primary department untuk inheritance hierarchy. Manfaat Team:
1. **Cross-departmental workgroup**: mengelompokkan employee dari berbagai dept ke satu team logis
2. **Unified shift/schedule**: assign shift pattern yang sama ke seluruh team member
3. **Team-aware reporting**: analytics dan attendance dapat difilter/digroup by team
4. **Team leadership delegation**: team lead dapat manage hanya anggota team-nya
5. **Dynamic reorganization**: bulk reassign employee tanpa mengubah departemen individual mereka

Dokumen teknis pendamping:
- `tracker.md` untuk snapshot readiness, evidence implementasi, dan gap aktif.
- `USE-CASES.md` untuk detail aktor, matriks hak, dan skenario bisnis per role.
- `BACKFILL-RUNBOOK.md` untuk prosedur operasional command `hcm:teams-backfill-legacy`.
- `docs/features/employees-organization/README.md` untuk employee core yang terintegrasi dengan team.
- `docs/features/attendance-shift-schedule/README.md` untuk integrasi shift + team scheduling.

## Akses & Izin

**Aktor utama:**
- **HCM Admin**: CRUD seluruh teams, assign/reassign employee ke teams, lihat composition per team, bulk reassign action.
- **Team Lead**: view member list team yang dipimpin (wajib `team.lead` + ownership team).
- **Employee**: view diri sendiri dan team-mate (jika di team yang sama), tidak bisa mutasi.
- **Super Admin**: lihat teams lintas tenant (monitoring).

**Guard & Permission:**
- Seluruh mutasi team (create/update/delete/reassign member) hanya boleh HCM Admin dengan guard `hcm.web.admin` dan permission `team.manage` (fallback transisi masih menerima `employee.manage`).
- Assignment employee ke team hanya boleh HCM Admin atau module bulk import, dan target team **harus active** (`TEAM_INACTIVE_NOT_ASSIGNABLE` jika inactive).
- Akses `GET /teams/{id}/members` untuk team lead mensyaratkan dua hal: user adalah `team_lead_id` pada team tersebut **dan** punya permission `team.lead`.
- Team data hanya valid untuk member tenant aktif; cross-tenant team tampil hanya di view super admin.

## UI Aktif

- **Halaman `/teams`** — list, create, edit, delete teams (mirip `/departments` dan `/designations`).
- **Halaman `/teams/{id}/members`** — detail anggota team dengan search, status filter, dan pagination.
- **Form team**: name, primary department (dropdown), team lead (optional select employee), is_active checkbox.
- **List team**: table dengan kolom: name, department, member count (link to filtered employee list), team lead, status, action (edit/delete).
- **Employee form** (`/employee-details`, `/employees` create/edit modal): dropdown **Team** (filtered by active teams, optional) — employee bisa dipilih tidak punya team (null).
- **Team members panel** (future, optional): halaman `/teams/{id}` menampilkan list employee yang assigned, quick stats (count, active/inactive, department breakdown).

## Flow Bisnis End-to-End

### 1. Planning Phase (Admin Setup)
```
1a. HCM Admin buka menu Employees → Teams (atau direct /teams)
1b. Lihat daftar teams yang sudah ada (jika ada)
1c. Pilih "Create Team"
    - Input: Team Name ("Customer Service 24h", "Engineering Core", dst)
    - Input: Primary Department (select dari departments list)
    - Input: Team Lead (optional, select employee)
    - Input: Status (Active/Inactive)
1d. Simpan → Team tersimpan di database, assign company_id tenant aktif
1e. Ulangi untuk setiap team yang direncanakan
```

**Output bisnis:** Struktur team formal, jelas hirarki dan scope.

### 2. Assignment Phase (Org Staffing)
```
2a. HCM Admin buka halaman Employees → list
2b. Create employee baru ATAU edit employee existing
    - Field Team: dropdown teams (optional, bisa null)
    - Pilih "Customer Service 24h" untuk employee di CS
    - Pilih "Engineering Core" untuk engineer
2c. Sistem:
    - Tidak auto-override department (department tetap independen)
    - Employee dapat punya Department=Finance, Team=Finance Receivables
    - Team tidak inherit department otomatis (admin bisa tetap di dept berbeda)
2d. Alternative: Bulk assignment
    - Import employee bulk via /v1/hcm/employees/bulk-upload
    - Kolom 'team' berisi team name atau team id
  - Sistem match dengan team master tenant aktif; jika match ke team inactive maka baris ditolak
```

**Output bisnis:** Employee terassign ke workgroup formal, siap untuk shift/schedule centralized.

### 3. Shift & Schedule Integration
```
3a. HCM Admin buka /schedule-timing
3b. Select scope: "Department" ATAU "Team" (UI dropdown untuk filter scope)
3c. Pilih: Team = "Customer Service 24h"
    - Sistem load seluruh employee dalam team tersebut
3d. Assign Shift → "CS 24-Hour Rotating" ke seluruh team
    - Bulk override shift per team (untuk efficiency)
    - Atau manual per employee (untuk flexibility)
3e. Smart Attendance Planner sekarang generate jadwal dengan konteks team:
    - Filter employee: team = "Customer Service 24h"
    - Generate: jadwal mingguan yang optimize untuk team
    - Preview: kalender dengan team member assignment
```

**Output bisnis:** Team dapat manage shift secara terpusat, planning lebih efisien.

### 4. Reporting & Analytics
```
4a. Dashboard HCM: widget team
    - "Team Composition": bar chart atau table per team
    - "Team Attendance": filter by team → show KPI per team
    - "Team Shift Distribution": pie/stacked chart of shift per team member
4b. Report attendance admin:
    - Filter Team dropdown → "Customer Service 24h"
    - Tampil hanya attendance records team tersebut
4c. Payroll + Team grouping:
    - Export slip per team
    - Aggregate salary by team untuk cost center allocation
```

**Output bisnis:** Clear visibility team performance, cost allocation, compliance audit.

### 5. Reorganization (Dynamic Update)
```
5a. HCM Admin perlu reassign 10 employee dari "CS Team 1" ke "CS Team 2"
5b. Buka /teams atau employees bulk action
5c. Option A: Manual per employee
    - Edit employee → ubah Team field
5d. Option B: Bulk reassign (future)
    - Select 10 employee
    - Action "Reassign to Team" → dropdown CS Team 2
    - Confirm + apply
5e. Sistem track history (optional, bisa defer):
    - Audit log: "user X reassigned Y employee from team A to B on date Z"
    - Employee assignment history tetap terekam
```

**Output bisnis:** Org structure dapat adapt cepat terhadap kebutuhan bisnis.

## Lifecycle Dan Keputusan Bisnis

### Team Entity Scope
- **Team adalah master entity**, setara dengan Department dan Designation.
- **Team dapat cross-departemen**: employee dari berbagai dept dapat di-team yang sama.
- **Primary department opsional**: team dapat punya `department_id` untuk inheritance, atau null untuk truly cross-dept (misal: "Corporate Council" tidak punya dept).
- **Team tetap independen terhadap Designation**: satu team bisa punya banyak designations (misal CS Team: ada CS Associate, CS Lead, CS Manager).

### Employee-Team Relationship
- **Relationship**: satu employee → maksimal 1 team (one-to-many inverse).
- **Team optional**: employee boleh tidak punya team (NULL di field `team_id`).
- **Department NOT changed**: assign employee ke team TIDAK mengubah `department_id` employee. Department tetap terpisah untuk org chart vertikal, Team adalah grouping horizontal.
- **Immutability untuk inherited data**: jika employee department auto-inherit dari team, kemudian team primary_department diubah, employee department tetap (cascade update tidak otomatis).

### Team Lead & Permission Delegation
- **Team lead**: optional field `team_lead_id` (FK ke `users.id`).
- **Team lead capability** (future phase):
  - View member list team-nya
  - Report attendance per team member
  - Suggest discipline/appraisal per member (HCM Admin approve)
  - Cannot mutasi team master, cannot reassign member (HCM Admin approve)
- **Permission role**: `team.lead` atau `team.{team_id}.lead` (detail tergantung RBAC finalization).

### Data Quality & Constraints
- **Team name unique per company**: dua tenant boleh punya team dengan nama sama, tapi dalam 1 company, team name harus unik (UNIQUE constraint `(company_id, name)`).
- **Team status**: active/inactive (soft delete bisa defer ke phase 2).
- **Orphan handling**: delete team diblok jika masih ada member aktif (`TEAM_DELETION_BLOCKED`), sehingga tidak terjadi orphan assignment.
- **Audit trail** (optional phase 2): track create/update/delete team + reason.

### Integration Point: Backward Compatibility
- **Legacy team field**: di EmployeeProfile sudah ada kolom `team` (string, free text).
- **Policy**: 
  - Jika team_id null, system tetap support free-text `team` field untuk legacy (display as "Manual Team" atau "—").
  - Admin dapat migrate legacy `team` string → team_id referensi via data cleanup script.
  - New employee HARUS pilih team dari master (atau null), jangan free text lagi.
- **Reporting**: both `team_id` (structured) dan `team` (legacy string) dapat dipakai, dengan preference ke `team_id`.

### Decision: Master vs Non-master
- **DECIDED: Team IS master entity** (dengan analisis di atas).
- Team mempunya lifecycle (create → active → reassign → inactive/delete) seperti Department dan Designation.
- Team akan exposed via `/v1/hcm/teams` CRUD API.
- Team akan punya dedicated halaman UI `/teams` untuk admin management.

## Integrasi

### Employee & Organization
- **Employee master**: `GET/POST /v1/hcm/employees` dengan field `team_id` (nullable, FK ke `teams.id`).
- **Team list API**: `GET /v1/hcm/teams?active=true` untuk populate team dropdown di employee form.
- **Bulk upload**: kolom `team_id` atau `team_name` dalam template.
- **Reference**: `docs/features/employees-organization/README.md` untuk employee core.

### Attendance & Shift Schedule
- **Smart Attendance Planner**: `POST /v1/hcm/smart-attendance-shifting/generate` dengan `scope` parameter:
  - `scope=all`: generate untuk semua employee tenant.
  - `scope=department:{dept_id}`: generate untuk dept tertentu.
  - `scope=team:{team_id}`: generate untuk team tertentu.
- **Schedule timing**: `/schedule-timing` dapat filter employee by team untuk bulk shift assignment.
- **Reference**: `docs/features/attendance-shift-schedule/README.md` untuk shift/schedule core.

### Reporting & Dashboard
- **Report filtering**: attendance, payroll, training, performance reports dapat filter by team.
- **Dashboard widgets**:
  - Team composition (count, department breakdown, shift distribution).
  - Team attendance KPI (on-time %, absence rate).
  - Team shift coverage (minimal coverage met?).
  - Team cost (aggregate payroll by team).
- **Reference**: `docs/features/reporting/README.md`.

### Performance & Appraisal
- **Performance review scope**: manager dapat review team member, system filter goal/appraisal by team.
- **Team performance**: aggregate KPI per team untuk business unit assessment.
- **Reference**: `docs/features/performance/README.md`.

### Payroll & Compensation
- **Payroll grouping**: payroll run dapat generate slip per team (untuk cost center allocation).
- **Shift allowance**: team member dengan shift assignment dapat receive shift premium.
- **Reference**: `docs/features/payroll-runs/README.md`, `docs/features/payroll-salary-components/README.md`.

## Kontrak API

### Team Endpoints (`/v1/hcm/teams`)

- `GET /v1/hcm/teams`
- `POST /v1/hcm/teams`
- `GET /v1/hcm/teams/{id}`
- `PUT /v1/hcm/teams/{id}`
- `DELETE /v1/hcm/teams/{id}`
- `GET /v1/hcm/teams/{id}/members`
- `POST /v1/hcm/teams/reassign-members`

**List Teams**
```
GET /v1/hcm/teams
Query params:
  - perPage: int (default 20)
  - page: int (default 1)
  - search: string (search by name, optional)
  - status: enum(all|active|inactive), optional

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "company_id": 1,
      "uuid": "uuid-string",
      "name": "Customer Service 24h",
      "department_id": 10,
      "department_name": "Operations",
      "team_lead_id": 123,
      "team_lead_name": "John Doe",
      "is_active": true,
      "member_count": 30,
      "created_at": "2026-01-15T10:00:00Z",
      "updated_at": "2026-04-20T14:30:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 15
  }
}
```

**Create Team**
```
POST /v1/hcm/teams
Body:
{
  "name": "Engineering Core",
  "department_id": 5,
  "team_lead_id": 456,
  "is_active": true
}

Response: { "success": true, "data": { "id": 2, ...full team object... } }
```

**Get Team Detail**
```
GET /v1/hcm/teams/{id}
Response: { "success": true, "data": { ...team object with members count... } }
```

**Update Team**
```
PUT /v1/hcm/teams/{id}
Body: { "name": "...", "department_id": ..., "team_lead_id": ..., "is_active": ... }
Response: { "success": true, "data": { ...updated team... } }
```

**Delete Team**
```
DELETE /v1/hcm/teams/{id}
Response:
  Success: 204 No Content
  Error (has member): 409 TEAM_DELETION_BLOCKED
```

**Team Members**
```
GET /v1/hcm/teams/{id}/members
Query params:
  - perPage: int (default 20)
  - page: int (default 1)
  - search: string (optional)
  - status: enum(all|active|probation|inactive), optional
```

**Bulk Reassign Team Members**
```
POST /v1/hcm/teams/reassign-members
Body:
{
  "employee_ids": [101, 102],
  "source_team_id": 1,
  "target_team_id": 2
}

Notes:
  - target_team_id = null means unassign team
  - source_team_id optional as guard to prevent wrong-scope updates
```

### Employee Integration

**Get/Update Employee with Team**
```
GET /v1/hcm/employees/{id}
PUT /v1/hcm/employees/{id}
Body (for PUT):
{
  "name": "...",
  "team_id": 1,  // nullable FK to teams.id
  ...
}
```

**Bulk Upload Employee Template**
```
GET /v1/hcm/employees/bulk-template
Response: Excel file dengan kolom [..., team_id, ...]
Note: ref sheet 'ref_teams' dengan team master
```

### Source of Truth
- `docs/api/openapi.yaml` — canonical API contract.
- `docs/api/hcm-masterdata-api.md` — feature API detail synchronized with runtime.

## Current Runtime Vs Backlog

### Current Runtime (April 2026)
- **Data model**: `teams` table sudah ada dengan columns: `id`, `uuid`, `company_id`, `department_id`, `name`, `is_active`, `created_at`, `updated_at`.
- **Model & Relation**: `Team` model exists (`app/Models/Team.php`) dengan methods `department()`, `assignments()` (via EmployeeAssignment).
- **Employee integration**: `EmployeeProfile` sudah punya `team` field (string, legacy).
- **Employee assignments**: `employee_assignments` table track employee-team via `team_id` FK, plus `EmployeeAssignment` model.
- **Employee snapshot service**: dapat auto-create team saat import employee dengan team name baru (`EmployeeSnapshotService::resolveTeamId()`).
- **API employee**: `GET/POST/PUT /v1/hcm/employees` support `team` field di payload.
- **Bulk import**: template sudah include team reference sheet (`ref_teams`), dropdown validation untuk team_id di Excel.
- **Foreign key**: safe FK migration sudah run untuk team → department dan employee_assignments → teams.

### Audit Total Integrasi UI/UX (26 April 2026)

| Area | Kondisi Runtime Saat Ini | Evidence | Status Audit | Gap Phase Selanjutnya |
|------|---------------------------|----------|--------------|-----------------------|
| Team Master List (`/teams`) | Kolom team-centric sudah lengkap: Team Name, Department, Members, Team Lead, Status | `backend/resources/views/teams.blade.php` | ✅ Clear | Tidak ada gap kritikal UI untuk list inti team |
| Team Members List (`/teams/{id}/members`) | Menampilkan anggota dalam konteks single-team: Name, Email, NIK, Department, Designation, Status | `backend/resources/views/team-members.blade.php` | ✅ Clear (by context) | Tambah aksi bulk reassign dari halaman ini (UX enhancement) |
| Employee Main List (`/employees`) | Sudah menampilkan kolom Team di tabel utama + Team filter | `backend/resources/views/employees.blade.php` | ✅ Clear | Monitor UX dan data quality lintas tenant |
| Employee Report Table | Renderer report sudah fallback `teamName || team` | `frontend/resources/js/employees-data.js` | ✅ Clear | Pertahankan backward compatibility field |
| Employee Create/Edit Form | Dropdown Team mutation hanya tampilkan team aktif; fallback aman untuk employee legacy yang masih di team inactive | `frontend/resources/js/employees-data.js` + runtime form employees | ✅ Clear | Monitor hanya untuk exceptional tenant policy |
| Bulk Team Mutation UX | API bulk reassign sudah live + mass-action UI sudah enforce source-team guard sebelum submit | `POST /v1/hcm/teams/reassign-members` + `employees.blade.php` | ✅ Clear | Monitoring UX selection lintas halaman |

### Remaining Backlog (Post Phase 3)
- Tidak ada blocker mayor tersisa untuk team management runtime saat ini.
- Monitoring lane tetap aktif untuk kebutuhan tenant-specific custom role mapping.

### Gap & Risk Mitigation
1. **Free-text team field conflict**: write path admin sekarang wajib assignment via `teamId` master (`TEAM_MASTER_SELECTION_REQUIRED` jika hanya kirim free-text `team`). Legacy `team` string tetap dipertahankan untuk read compatibility.
2. **Orphan employee post-team-delete**: mitigasi runtime aktif dengan block delete team ber-member (`TEAM_DELETION_BLOCKED`), jadi orphan assignment tidak terjadi.
3. **Team lead governance rollout**: baseline role default (`TEAM_LEAD`, `MANAGER`) sudah tersedia; tenant lama bisa disinkronkan via command `hcm:sync-team-role-defaults`.
4. **Bulk operation UX safety**: mass-action reassign sekarang enforce source-team guard di UI dan mengirim `source_team_id` ke API.
5. **Inactive team assignment policy**: hard-block sudah aktif lintas create/update/import/reassign (`TEAM_INACTIVE_NOT_ASSIGNABLE`).

## Data Model Ringkas

```
teams (master)
├─ id (PK)
├─ uuid (unique)
├─ company_id (FK companies)
├─ department_id (FK departments, nullable — untuk primary dept reference)
├─ name (varchar, UNIQUE per company_id)
├─ team_lead_id (FK users, nullable)
├─ is_active (boolean)
├─ created_at, updated_at

employee_assignments (assignment history)
├─ id (PK)
├─ employee_id (FK users)
├─ team_id (FK teams, nullable)
├─ department_id (FK departments)
├─ designation_id (FK designations)
├─ start_date, end_date (nullable)
├─ created_at, updated_at

employee_profiles (denormalization)
├─ user_id (PK, FK users)
├─ team_id (FK teams, nullable) — active team assignment
├─ team (varchar, legacy string — backward compat)
├─ [other employee data]

departments, designations (untouched by team feature)
```

## Frontend Flow

- **`/teams` page**: CRUD halaman team, mirip `/departments` dan `/designations`.
  - File: `backend/resources/views/teams.blade.php` (new) — template list + form.
  - JS: `frontend/resources/js/team-master-data.js` (new) — API binding, CRUD action, grid render.
  - JS hook: `hcm-pages-data.js` akan add handler untuk `/teams` path.
  
- **Employee form integration**: `/employees` create/edit modal.
  - JS: `frontend/resources/js/employees-data.js` — add team dropdown field.
  - API call: `GET /v1/hcm/teams?active=true` untuk populate dropdown.
  - Validation: team_id FK check di backend (optional at submission).

- **List team members** (future): `/teams/{id}` show member list, team details, quick stats.

## Aturan Bisnis Penting

1. **Team adalah formal org structure**: admin harus maintain team master, bukan free-text dari employee form.
2. **Team independent dari Department**: employee department dapat berbeda dari team's primary department.
3. **One employee, one team**: satu employee tidak boleh punya multiple teams simultan.
4. **Team lead ownership**: team lead dapat punya visibility hanya ke team-nya (permission gate, phase 2).
5. **Safe delete**: delete team hanya jika tidak ada member aktif, atau cascade set member `team_id = NULL` dengan warning.
6. **Backward compat**: legacy `team` string field tetap read-able, tapi new data prefer `team_id` structured reference.
7. **Company scoping**: team selalu bound ke `company_id` aktif, tidak boleh cross-tenant reference.

## Roadmap Fase Implementasi

| Fase | Fitur | Status | Catatan |
|------|-------|--------|---------|
| **Fase 1** | Core CRUD + `/teams` UI + employee team dropdown | ✅ Complete | MVP selesai |
| **Fase 2** | Team members API/UI + team lead visibility + role matrix sync | ✅ Complete | Members page live |
| **Fase 3** | Bulk reassign API + audit trail + legacy backfill command + performance tuning | ✅ Complete | Polish backend selesai |
| **Fase 4 (UI/UX Integration Hardening)** | Employee list Team column + Team filter + alignment list/report | ✅ Complete | UI parity list/report sudah ditutup |
| **Fase 4 (UI/UX Integration Hardening)** | Bulk reassign mass-action UI (employee/team list) + feedback modal | ✅ Complete | Operasional tidak API-only lagi |
| **Fase 4.5 (Security Hardening)** | Granular RBAC (`team.manage`, `team.lead`) + scope enforcement matrix | ✅ Complete | Runtime + docs + tests aligned |
| **Fase 4.5 (Ops Readiness)** | Backfill operational runbook + rollback playbook | ✅ Complete | Runbook siap eksekusi tenant rollout |
| **Fase 4.6 (Follow-up Rollout)** | Default role template `TEAM_LEAD` + UI mutation guard parity inactive-team | ✅ Complete | Seeder + frontend fallback aman untuk data legacy |
