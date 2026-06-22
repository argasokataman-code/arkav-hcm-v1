# Approval Settings — Implementation

---

## 1. Arsitektur

```
┌─────────────────────────────────────────────────────────────┐
│                    Blade View Layer                          │
│  resources/views/settings/approval-settings.blade.php        │
│  resources/views/approval-settings.blade.php (@include)      │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP (fetch API)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    Route Layer                               │
│  routes/api/approval-settings.php  (API)                     │
│  routes/web.php line 1591           (View)                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                 Controller Layer                              │
│  HcmApprovalSettingsController                                │
│  App\Http\Controllers\Api\Settings\                           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                  Service Layer                                │
│  ApprovalConfigService                                       │
│  App\Services\                                               │
└──────┬──────────────────────────────────────┬───────────────┘
       │                                      │
       ▼                                      ▼
┌──────────────┐                    ┌──────────────────┐
│   Models     │                    │  Notifications    │
│ HcmApprovalConfig                 │ LeaveApprovalReq… │
│ HcmApprovalConfigApprover         │ LeaveApproved     │
│              │                    │ LeaveRejected     │
│              │                    │ LeaveNextApprover │
│              │                    │ OvertimeApproval… │
│              │                    └──────────────────┘
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  Database    │
│ hcm_approval_│
│ configs      │
│ hcm_approval_│
│ config_      │
│ approvers    │
└──────────────┘
```

### File Tree

```
backend/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── Settings/
│   │               └── HcmApprovalSettingsController.php    (151 lines)
│   ├── Models/
│   │   ├── HcmApprovalConfig.php
│   │   └── HcmApprovalConfigApprover.php
│   └── Services/
│       └── ApprovalConfigService.php                        (251 lines)
├── database/
│   └── migrations/
│       ├── 2026_05_28_000001_create_hcm_approval_configs_table.php
│       ├── 2026_05_28_000002_create_hcm_approval_config_approvers_table.php
│       ├── 2026_05_28_000002_add_approved_by_to_leave_requests.php
│       └── 2026_05_29_000001_add_approved_by_to_hcm_resignations.php
├── routes/
│   └── api/
│       └── approval-settings.php
├── resources/
│   └── views/
│       ├── approval-settings.blade.php                       (wrapper @include)
│       └── settings/
│           └── approval-settings.blade.php                   (309 lines)
└── tests/
    └── Feature/
        ├── ApprovalConfigServiceTest.php                     (36 tests)
        ├── HcmApprovalSettingsApiTest.php                    (18 tests)
        └── ApprovalSettingsTestPlan.md                       (56 test cases)
```

---

## 2. Database Schema

### 2a. `hcm_approval_configs`

Konfigurasi per company per module — menyimpan pola approval.

```sql
CREATE TABLE hcm_approval_configs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL UNIQUE,
    company_id      BIGINT UNSIGNED NOT NULL,
    module          VARCHAR(50) NOT NULL,    -- 'leave','overtime','resignation','termination','expense','offer'
    approval_mode   ENUM('sequence', 'simultaneous') NOT NULL DEFAULT 'sequence',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    UNIQUE KEY uq_company_module (company_id, module),
    CONSTRAINT fk_approval_config_company
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 2b. `hcm_approval_config_approvers`

Daftar approver per config. `sequence_order` menentukan urutan chain untuk mode sequence.

```sql
CREATE TABLE hcm_approval_config_approvers (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    hcm_approval_config_id  BIGINT UNSIGNED NOT NULL,
    company_id              BIGINT UNSIGNED NOT NULL,
    approver_user_id        BIGINT UNSIGNED NOT NULL,
    approver_user_uuid      CHAR(36) NULL,
    sequence_order          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    CONSTRAINT fk_aca_config
        FOREIGN KEY (hcm_approval_config_id)
        REFERENCES hcm_approval_configs(id) ON DELETE CASCADE,
    CONSTRAINT fk_aca_user
        FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_aca_config_order (hcm_approval_config_id, sequence_order)
);
```

### 2c. Migration Lain (Integrasi)

| Migration | File | Tujuan |
|---|---|---|
| `2026_05_28_000002_add_approved_by_to_leave_requests` | Tambah `approved_by_user_id`, `approved_at` ke `leave_requests` | ANOMALI-4 |
| `2026_05_29_000001_add_approved_by_to_hcm_resignations` | Tambah `approved_by_user_id`, `approved_at` ke `hcm_resignations` | Phase 4 |

### Catatan Schema

- `module` = string enum (6 nilai), bukan FK ke tabel terpisah.
- `approval_mode = 'sequence'` = approver diproses urut berdasarkan `sequence_order`.
- `approval_mode = 'simultaneous'` = semua approver dinotifikasi bersamaan.
- Multi-tenant isolation via `company_id` di setiap tabel.

---

## 3. Service Layer — `ApprovalConfigService`

**File:** `backend/app/Services/ApprovalConfigService.php` (251 lines)

### Method Reference

| Method | Signature | Deskripsi | Query Count |
|---|---|---|---|
| `getConfigForModule` | `(int companyId, string module): ?HcmApprovalConfig` | Ambil config aktif untuk module tertentu | ≤3 |
| `upsertConfig` | `(int companyId, string module, string mode, array approverUserIds): HcmApprovalConfig` | Create/update config + replace approvers | ≤16 |
| `populateLeaveApprovals` | `(LeaveRequest $leaveRequest): array` | Buat baris `LeaveApproval` per configured approver | ≤12 |
| `processApprovalDecision` | `(LeaveRequest $leaveRequest, string $status, User $actor, ?string $notes): array` | Proses approve/reject, advance chain | ≤8 |
| `resolveApproversToNotify` | `(string $module, int $companyId, int $currentLevel = 1): Collection` | Tentukan approver mana yang perlu dinotifikasi | — |
| `getEligibleApprovers` | `(int $companyId, ?string $search = null, int $limit = 20): Collection` | Cari user yang eligible jadi approver | — |

### Detail Method

#### `getConfigForModule($companyId, $module)`
```php
return HcmApprovalConfig::query()
    ->where('company_id', $companyId)
    ->where('module', $module)
    ->where('is_active', true)
    ->with(['approvers.user'])
    ->first();
```

#### `upsertConfig($companyId, $module, $mode, $approverUserIds)`
1. Ambil config existing (atau buat baru).
2. Set `approval_mode`, `is_active = true`.
3. Hapus approvers lama.
4. Insert approvers baru urut `sequence_order`.
5. Return config + approvers fresh.

#### `populateLeaveApprovals($leaveRequest)`
1. Panggil `getConfigForModule(company, 'leave')`.
2. Jika null → return empty (fallback ke legacy flow).
3. Jika mode `sequence` → buat `LeaveApproval` per approver dengan `level = sequence_order`.
4. Jika mode `simultaneous` → buat `LeaveApproval` per approver dengan `level = 1` (semua setara).
5. Hapus `LeaveApproval` stale milik request ini sebelumnya (idempotent).

#### `processApprovalDecision($leaveRequest, $status, $actor, $notes)`
1. Cari `LeaveApproval` untuk actor ini (pending).
2. Jika tidak ditemukan → return fallback (double-approve guard).
3. Update status row → `approved`/`declined`.
4. Jika `declined` → `leave_request.status = declined`, return.
5. Jika mode `sequence`:
   - Cek apakah masih ada level berikutnya.
   - Jika ada → return `next_approvers` (untuk kirim notif).
   - Jika tidak → `leave_request.status = approved`.
6. Jika mode `simultaneous`:
   - Cek apakah semua approver sudah approve.
   - Jika ya → `leave_request.status = approved` (first-to-approve wins).

#### `getEligibleApprovers($companyId, $search, $limit)`
Query `CompanyUser` join `User`, filter active, search by `name`/`email`/`designation`, limit 20.

---

## 4. Controller — `HcmApprovalSettingsController`

**File:** `backend/app/Http/Controllers/Api/Settings/HcmApprovalSettingsController.php` (151 lines)

| Method | Route | Auth | RBAC |
|---|---|---|---|
| `index` | `GET /v1/hcm/approval-settings` | `api.token` | `EnsuresHcmAdmin` |
| `update` | `PUT /v1/hcm/approval-settings/{module}` | `api.token` | `EnsuresHcmAdmin` |
| `eligibleApprovers` | `GET /v1/hcm/approval-settings/eligible-approvers` | `api.token` | `EnsuresHcmAdmin` |

### Route Definition

```php
// routes/api/approval-settings.php
Route::get('/', [HcmApprovalSettingsController::class, 'index']);
Route::put('/{module}', [HcmApprovalSettingsController::class, 'update'])
    ->where('module', '[a-z_]+');
Route::get('/eligible-approvers', [HcmApprovalSettingsController::class, 'eligibleApprovers']);
```

### `index()` — GET all configs
1. Iterate `SUPPORTED_MODULES`.
2. For each: panggil `getConfigForModule()`.
3. Jika null → return default (isActive: false, approvalMode: 'simultaneous', approvers: []).
4. Return `{ success: true, data: { moduleKey: {...}, ... } }`.

### `update()` — PUT upsert
1. Validasi `module` in array `SUPPORTED_MODULES` → 422 `INVALID_MODULE`.
2. Validasi input (`approvalMode` in ['sequence','simultaneous'], `approverUserIds` 1–10, exists:users).
3. **Security check:** Verifikasi semua `approverUserIds` adalah member aktif company → 422 `APPROVER_NOT_IN_COMPANY`.
4. Panggil `upsertConfig()`.
5. Return `{ success: true, data: { ... } }`.

### `eligibleApprovers()` — GET search
1. Ambil `q` dari query params.
2. Panggil `getEligibleApprovers()`.
3. Return `{ success: true, data: [{ id, name, email, designation }, ...] }`.

---

## 5. Integrasi dengan Modul Lain

### 5a. Leave

| Titik Integrasi | File | Mekanisme |
|---|---|---|
| Leave request dibuat (store) | `HcmLeaveRequestController::store()` | Panggil `ApprovalConfigService::populateLeaveApprovals()` |
| Approve/decline decision | `HcmLeaveRequestController::update()` | Panggil `processApprovalDecision()`, kirim notifikasi `LeaveNextApproverNotification` jika ada `next_approvers` |
| Approval table populated | `leave_approvals` | Otomatis dari `populateLeaveApprovals()` |

### 5b. Overtime

| Titik Integrasi | Mekanisme |
|---|---|
| Overtime request dibuat (store) | Panggil `resolveApproversToNotify('overtime')`, kirim `OvertimeApprovalRequestedNotification` |

### 5c. Resignation

| Titik Integrasi | Mekanisme |
|---|---|
| Resignation request dibuat (store) | Kirim `ResignationApprovalRequestedNotification` ke configured approvers |

### 5d. Termination

| Titik Integrasi | Mekanisme |
|---|---|
| Termination dibuat, `workflow_stage = draft_review` | Kirim `TerminationApprovalRequestedNotification` ke configured approvers |

---

## 6. Config & Feature Package

### Supported Modules (Controller constant)

```php
private const SUPPORTED_MODULES = [
    'leave', 'expense', 'offer', 'overtime', 'resignation', 'termination'
];
```

### Feature Code Mapping

| Module | Feature Code | Catalog Source |
|---|---|---|
| leave | `leave_management` | `saas_package_feature_catalog.php` |
| overtime | `overtime` | `saas_package_feature_catalog.php` |
| resignation | `resignation` (via `employee_lifecycle`) | `saas_package_feature_catalog.php` |
| termination | `termination` | `saas_package_feature_catalog.php` |
| expense | — | Future |
| offer | — | Future |

### Route Regex Constraint

```php
Route::put('/{module}', ...)->where('module', '[a-z_]+');
```

Regex `[a-z_]+` lebih ketat dari `SUPPORTED_MODULES` — module name hanya lowercase letters + underscore.

---

## 7. Template Blade

### File: `resources/views/settings/approval-settings.blade.php` (309 lines)

### Auth Headers (JS)

Mengambil token kanonik:
```js
var token = (window.AuthApi && typeof window.AuthApi.getToken === 'function'
        && window.AuthApi.getToken())
    || localStorage.getItem('arcav_access_token')
    || sessionStorage.getItem('arcav_access_token') || '';
var ctx = JSON.parse(localStorage.getItem('arcav_active_tenant') || '{}');
companyId = ctx.companyId ? String(ctx.companyId) : '';
```

### Komponen UI per Module
- Dropdown approver via Select2, source dari `GET /v1/hcm/approval-settings/eligible-approvers?q=`
- Radio button Sequence / Simultaneous
- Daftar approvers terpilih (dengan order untuk sequence)
- Tombol Save → `PUT /v1/hcm/approval-settings/{module}`
- Dynamic visibility berdasarkan feature package

---

## 8. Anomali Ditemukan & Diperbaiki

| ID | Severity | Problem | Fix |
|---|---|---|---|
| ANOMALI-1 | HIGH | `routes/web/settings.php` dead code — route approval-settings di file terpisah tapi tidak pernah di-register | Route ada langsung di `web.php` line 1591. File dead code diabaikan. |
| ANOMALI-2 | MEDIUM | Feature code `leave_approval_flow` tidak terdaftar di package catalog | Ditambahkan ke `saas_package_feature_catalog.php` |
| ANOMALI-3 | HIGH | Tabel `leave_approvals` zero writes — orphan infrastructure | `populateLeaveApprovals()` dipanggil saat leave request dibuat |
| ANOMALI-4 | MEDIUM | `LeaveRequest` tidak punya kolom `approved_by` | Migration tambah `approved_by_user_id`, `approved_at` |
| ANOMALI-5 | CRITICAL | Blade pakai key `hcm_token` / `hcm_active_company_id` yang tidak pernah di-set | Diubah ke `arcav_access_token` + `arcav_active_tenant` (kanonik) |
| ANOMALI-6 | HIGH | `LeaveNextApproverNotification` tidak pernah dikirim di sequence mode | Controller capture return value `processApprovalDecision()` dan kirim notif |
| ANOMALI-7 | HIGH | Cross-company approver injection — validasi hanya cek `exists:users` bukan company membership | Ditambahkan check `CompanyUser::where('company_id', $companyId)->whereIn('user_id', ...)` |

---

## 9. Testing

| Test File | Jumlah Test | Coverage |
|---|---|---|
| `ApprovalConfigServiceTest.php` | 36 | Service unit: CRUD, populate, process, resolve, eligible, query count |
| `HcmApprovalSettingsApiTest.php` | 18 | API feature: auth, RBAC, validation, happy path, tenant isolation |
| **Total** | **54** | Service + API layers |

### Query Count Regression (Service)

| Method | Limit | Actual |
|---|---|---|
| `getConfigForModule` | ≤3 | ~2 |
| `upsertConfig` | ≤16 | ~14 |
| `populateLeaveApprovals` | ≤12 | ~10 |
| `processApprovalDecision` | ≤8 | ~6 |

---

## 10. RBAC & Security

### Middleware Chain

```
Route::prefix('v1/hcm/approval-settings')
    ->middleware(['api.token', 'tenant.context', 'EnsuresHcmAdmin']);
```

### Security Checklist

| Item | Implementasi |
|---|---|
| 401 tanpa token | ✅ `api.token` middleware |
| 403 non-admin | ✅ `EnsuresHcmAdmin` middleware |
| 422 validasi input | ✅ FormRequest validation |
| Multi-tenant isolation | ✅ `company_id` scope di semua query + `APPROVER_NOT_IN_COMPANY` check |
| IDOR protection | ✅ Config selalu discope ke `company_id` dari token |
| XSS via blade | ✅ Data dari API via JSON, bukan inline rendering |
