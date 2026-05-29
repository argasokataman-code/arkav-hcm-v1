# Approval Settings — Feature Planning

**Status:** PHASE 1–4 COMPLETE — API + UI wired; Leave, Overtime, Resignation, Termination integrated. Phase 5 (Expense/Offer) pending modul baru.
**Tanggal dibuat:** 2026-05-28
**Tanggal diimplementasi:** 2026-05-29
**Prioritas:** Medium — blocker untuk memperkuat Leave & Overtime approval flow

---

## 1. Ringkasan Bisnis

Approval Settings adalah fitur konfigurasi yang memungkinkan HR Admin/Owner mengatur **siapa yang menjadi approver** dan **pola approval** (sequential/simultaneous) untuk setiap jenis request di HCM.

Tanpa fitur ini, approver di setiap modul harus dikonfigurasi secara hardcode atau manual per-request — tidak scalable untuk perusahaan dengan struktur hierarki yang dinamis.

---

## 2. Masalah yang Diselesaikan

| Masalah Saat Ini | Dampak |
|---|---|
| Leave: approver tidak bisa dikonfigurasi via UI — `LeaveApproval` model & tabel ada tapi **tidak pernah dipakai** (zero writes) | HR harus set approver secara manual per-request; audit trail kosong |
| Overtime: `approved_by_user_id` ada di model tapi siapa yang berhak approve tidak terkonfigurasi | Approval bisa bypass atau tidak konsisten |
| ~~Resignation: status `pending/approved` ada tapi tidak ada `approved_by` — siapa yang approve tidak tercatat~~ | ✅ FIXED Phase 4 — kolom `approved_by_user_id` + `approved_at` ditambahkan ke `hcm_resignations`. Controller set value saat status→approved. |
| Termination: approval flow paling lengkap (review → approve → finalize) tapi masih hardcoded ke role admin | Tidak fleksibel untuk delegasi |
| Expense & Offer: belum ada modul, tapi saat dibangun nanti butuh approval framework yang sudah siap | Technical debt kalau tidak dibangun sekarang |

---

## 3. Fitur Runtime yang Akan Menggunakan Approval Settings

### 3a. Langsung Bisa Diintegrasikan (modul sudah ada)

| Modul | Status Approval Saat Ini | Gap |
|---|---|---|
| **Leave** (MVP) | Model `LeaveApproval` + tabel ada, tapi **tidak pernah diisi** (zero writes — lihat ANOMALI-3). `LeaveRequest` juga tidak punya `approved_by` (ANOMALI-4) | Approval chain tidak ada, audit trail siapa yang approve hilang total |
| **Overtime** (add-on) | `OvertimeRequest.approved_by_user_id` ada | Single approver hardcoded, tidak ada sequential/simultaneous chain |
| **Resignation** (add-on) | Status `pending/approved/cancelled` ada | ✅ Fixed — `approved_by_user_id` + `approved_at` ditambahkan (Phase 4) |
| **Termination** (add-on) | Workflow paling lengkap: review → approve → finalize + audit trail | Approver per stage tidak dikonfigurasi via settings, masih role-based admin |

### 3b. Butuh Modul Baru (tidak ada di runtime saat ini)

| Modul | Catatan |
|---|---|
| **Expense** | Tidak ada model, tidak ada migration, tidak ada route/controller — perlu dibangun dari awal |
| **Offer/Recruitment** | Tidak ada di codebase — perlu dibangun dari awal |

**Rekomendasi:** Fokus integrasi ke Leave dan Overtime dulu (modul sudah runtime-ready).

---

## 4. Desain Database

### 4a. Tabel `hcm_approval_configs`

Konfigurasi per company per module — menyimpan pola approval dan daftar approver.

```sql
CREATE TABLE hcm_approval_configs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL UNIQUE,
    company_id      BIGINT UNSIGNED NOT NULL,
    module          VARCHAR(50) NOT NULL,    -- 'leave', 'overtime', 'resignation', 'termination', 'expense', 'offer'
    approval_mode   ENUM('sequence', 'simultaneous') NOT NULL DEFAULT 'sequence',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    UNIQUE KEY uq_company_module (company_id, module),
    CONSTRAINT fk_approval_config_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 4b. Tabel `hcm_approval_config_approvers`

Daftar approver per config (ordered by `sequence_order` untuk mode sequence).

```sql
CREATE TABLE hcm_approval_config_approvers (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                    CHAR(36) NOT NULL UNIQUE,
    hcm_approval_config_id  BIGINT UNSIGNED NOT NULL,
    company_id              BIGINT UNSIGNED NOT NULL,
    approver_user_id        BIGINT UNSIGNED NOT NULL,
    approver_user_uuid      CHAR(36) NULL,
    sequence_order          TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- urutan dalam chain (untuk mode sequence)
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    CONSTRAINT fk_aca_config FOREIGN KEY (hcm_approval_config_id) REFERENCES hcm_approval_configs(id) ON DELETE CASCADE,
    CONSTRAINT fk_aca_user FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_aca_config_order (hcm_approval_config_id, sequence_order)
);
```

### Catatan Schema

- `module` menggunakan string enum terbatas, bukan FK ke tabel lain — sederhana dan extendable.
- `approval_mode = 'sequence'`: approver diproses urut berdasarkan `sequence_order` (chain). Level selanjutnya hanya dinotifikasi setelah level sebelumnya approve.
- `approval_mode = 'simultaneous'`: semua approver dalam config dinotifikasi bersamaan, dan semua harus approve (atau salah satu, tergantung business rule yang ditetapkan saat implementasi).
- Multi-tenant isolation: setiap row terikat `company_id`.

---

## 5. Dynamic Module Visibility — Berdasarkan Paket Aktif

### Jawaban: YA, bisa dan wajib dinamis

Sistem sudah punya mekanisme `Company::hasFeature(string $featureCode)` yang runtime-ready. Artinya Approval Settings page **harus** query fitur aktif company terlebih dulu, lalu hanya render section yang modulnya benar-benar aktif di paket mereka.

### Feature codes yang relevan (dari `saas_package_feature_catalog.php`)

| Section di UI | Feature Code | Tier |
|---|---|---|
| Leave Approval | `leave_management` | MVP (selalu ada) |
| Overtime Approval | `overtime` | Add-on |
| Resignation Approval | `resignation` | Add-on (`employee_lifecycle`) |
| Termination Approval | `termination` | Add-on |
| Expense Approval | *(belum ada)* | Future |
| Offer Approval | *(belum ada)* | Future |

### Cara implementasinya

**Di Controller (saat render view):**
```php
$company = auth()->user()->company; // atau dari tenant context

$activeModules = collect([
    'leave'       => $company->hasFeature('leave_management'),
    'overtime'    => $company->hasFeature('overtime'),
    'resignation' => $company->hasFeature('resignation'),
    'termination' => $company->hasFeature('termination'),
])->filter()->keys()->toArray();

return view('settings.approval-settings', compact('activeModules'));
```

**Di Blade:**
```blade
@if(in_array('leave', $activeModules))
    {{-- Leave Approval section --}}
@endif

@if(in_array('overtime', $activeModules))
    {{-- Overtime Approval section --}}
@endif
```

**Di API endpoint:**
- `GET /v1/hcm/approval-settings` hanya return config untuk module yang `hasFeature()` = true.
- Client tidak perlu tahu tentang package logic — server yang filter.

### Efek samping positif
- Company dengan paket minimal (hanya `leave_management`) hanya lihat satu section "Leave Approval" — tidak ada UI clutter.
- Saat company upgrade package dan add `overtime` → section "Overtime Approval" otomatis muncul tanpa code change.
- Backward compatible 100% karena query `hasFeature()` sudah production-ready.

---

## 6. Notifikasi — Approval Flow

### Jawaban: Infrastruktur sudah ada, event-nya belum dibuat

Sistem notifikasi (`NotificationPayloadFactory`, `HcmNotificationController`, in-app inbox) sudah runtime-ready dan dipakai di banyak domain (payroll, asset, attendance, subscription). Tapi **belum ada notification class khusus untuk approval request/decision**.

### Yang sudah ada (bisa dijadikan referensi pola)

| Class | Event | Siapa penerima |
|---|---|---|
| `LeaveCancelledNotification` | `leave.cancelled` | Employee pemilik request |
| `SubscriptionChangeApprovalNeededNotification` | `subscription.change_approval_needed` | Admin platform |
| `AttendanceCorrectionRequestedNotification` | `attendance.correction.requested` | Admin/approver |

### Yang perlu dibuat untuk approval flow

| Class (baru) | Event Key | Trigger | Penerima |
|---|---|---|---|
| `LeaveApprovalRequestedNotification` | `leave.approval.requested` | Leave request dibuat → status `pending` | Approver level 1 |
| `LeaveApprovedNotification` | `leave.approved` | Approver approve | Employee pengaju |
| `LeaveRejectedNotification` | `leave.rejected` | Approver reject | Employee pengaju |
| `LeaveNextApproverNotification` | `leave.approval.next_level` | Level 1 approve, ada level 2 | Approver level 2 |
| `OvertimeApprovalRequestedNotification` | `overtime.approval.requested` | Overtime request dibuat | Approver |
| `OvertimeApprovedNotification` | `overtime.approved` | Approver approve | Employee pengaju |

### Pola implementasi (mengikuti existing pattern)

```php
// app/Notifications/LeaveApprovalRequestedNotification.php
class LeaveApprovalRequestedNotification extends Notification
{
    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly User $approver,
    ) {}

    public function toDatabase($notifiable): array
    {
        return NotificationPayloadFactory::make('leave.approval.requested', [
            'companyUuid'      => (string) ($this->leaveRequest->company_uuid ?? ''),
            'entityType'       => 'leave',
            'entityUuid'       => (string) ($this->leaveRequest->uuid ?? ''),
            'title'            => 'Leave approval needed',
            'message'          => "Request from {$this->leaveRequest->user?->name}",
            'severity'         => 'important',
            'event'            => 'leave.approval.requested',
            'leaveRequestUuid' => (string) ($this->leaveRequest->uuid ?? ''),
        ]);
    }
}
```

### Kapan notif dikirim

```
Employee submit leave request
    → status = 'pending'
    → ApprovalConfigService::resolveApproversForRequest()
    → $approverLevel1->notify(new LeaveApprovalRequestedNotification($request, $approver))

Approver level 1 approve
    → Jika mode = 'sequence' dan ada level 2:
        → $approverLevel2->notify(new LeaveNextApproverNotification(...))
    → Jika mode = 'sequence' dan ini level terakhir (atau mode = 'simultaneous' dan semua approve):
        → status = 'approved'
        → $employee->notify(new LeaveApprovedNotification(...))

Approver reject (kapanpun)
    → status = 'rejected'
    → $employee->notify(new LeaveRejectedNotification(...))
```

### Catatan
- Notifikasi dikirim via queue (async) menggunakan Laravel Notification + database channel.
- User bisa mute event ini via `PUT /v1/hcm/notification-preferences` (sudah ada di runtime).
- Email channel opsional, tergantung preferensi user — in-app inbox adalah baseline wajib.

---

## 8. Arsitektur Aplikasi

### 5a. Model

```
App\Models\HcmApprovalConfig         → tabel hcm_approval_configs
App\Models\HcmApprovalConfigApprover → tabel hcm_approval_config_approvers
```

### 5b. Controller & Routes

```
GET    /v1/hcm/approval-settings              → list semua config per company
PUT    /v1/hcm/approval-settings/{module}     → upsert config untuk satu module
DELETE /v1/hcm/approval-settings/{module}/approvers/{id}  → hapus satu approver dari chain
```

Route web (view):
```
GET /approval-settings  → settings.approval-settings (sudah ada, tinggal di-wire ke controller)
```

### 5c. Service Layer

`App\Services\ApprovalConfigService`
- `getConfigForModule(company_id, module)` → return config + approvers
- `upsertConfig(company_id, module, mode, approver_ids[])` → atomic update
- `resolveNextApprover(config, current_level)` → untuk dikonsumsi modul leave/overtime

### 5d. Integrasi dengan Leave Module

`LeaveApproval` model sudah punya kolom `level` dan `approver_id`. Yang perlu ditambahkan:
- Saat leave request dibuat (`pending`), panggil `ApprovalConfigService::resolveApproversForRequest()` untuk populate tabel `leave_approvals` otomatis berdasarkan config.
- Jika config belum di-set → fallback ke behaviour saat ini (admin approve manual).

### 5e. Integrasi dengan Overtime Module

`OvertimeRequest` saat ini hanya punya `approved_by_user_id` (single approver).
- Jika config ada → gunakan chain dari `hcm_approval_config_approvers`.
- Jika mode `simultaneous` → semua approver di-notify sekaligus.

---

## 9. API Contract (Draft)

### GET `/v1/hcm/approval-settings`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "module": "leave",
      "approval_mode": "sequence",
      "is_active": true,
      "approvers": [
        { "id": "uuid-1", "sequence_order": 1, "user": { "id": "uuid-a", "name": "Budi Santoso", "role": "Manager" } },
        { "id": "uuid-2", "sequence_order": 2, "user": { "id": "uuid-b", "name": "Siti HR",      "role": "HR Admin" } }
      ]
    },
    {
      "module": "overtime",
      "approval_mode": "simultaneous",
      "is_active": true,
      "approvers": [...]
    }
  ]
}
```

### PUT `/v1/hcm/approval-settings/{module}`

**Request:**
```json
{
  "approval_mode": "sequence",
  "approver_ids": ["user-uuid-1", "user-uuid-2"]
}
```

**Response:**
```json
{ "success": true, "data": { "module": "leave", "approval_mode": "sequence", "approvers": [...] } }
```

**RBAC:** endpoint ini hanya untuk role `admin` (Global HCM Admin atau Company Owner).

---

## 10. UI / Blade Changes

### Perubahan di `settings/approval-settings.blade.php`

Saat ini: static HTML, hardcoded dropdown CEO/Manager/Team Lead.

Target:
1. Dropdown "Approvers" diisi dari API endpoint `GET /v1/hcm/users?role=approver_eligible` (user list perusahaan).
2. Radio button Sequence/Simultaneous tersambung ke `approval_mode`.
3. Tombol Save memanggil `PUT /v1/hcm/approval-settings/{module}`.
4. Support multiple approvers dengan UX drag-to-reorder untuk sequence mode.
5. Bagian "Offer Approval" disembunyikan sampai modul Offer dibangun.

---

## 11. Urutan Implementasi (Prioritas)

### Phase 1 — Foundation (wajib dulu)
1. Migration: buat tabel `hcm_approval_configs` dan `hcm_approval_config_approvers`
2. Model: `HcmApprovalConfig`, `HcmApprovalConfigApprover`
3. Controller + Service: CRUD config
4. API: GET + PUT endpoints
5. Blade: wire dropdown ke user list API + wire Save button ke PUT endpoint

### Phase 2 — Integrasi Leave (nilai tertinggi, modul sudah runtime)
6. Saat `LeaveRequest` dibuat → auto-populate `leave_approvals` dari config
7. Jika config kosong → fallback ke behaviour saat ini
8. UI Leave admin: tampilkan "approver chain" dari config yang aktif

### Phase 3 — Integrasi Overtime ✅ COMPLETE (2026-05-29)
9. ~~Overtime request: gunakan config approver jika ada~~ → **DONE**: `HcmOvertimeRequestController::store()` memanggil `ApprovalConfigService::resolveApproversToNotify('overtime')` saat status `pending`. Notifikasi dikirim via `OvertimeApprovalRequestedNotification`.
10. ~~Support simultaneous notification ke semua approver~~ → **DONE**: simultaneous → semua approver. sequence → level 1 saja.

### Phase 4 — Integrasi Resignation & Termination ✅ COMPLETE (2026-05-29)
11. ~~Tambah `approved_by_user_id` ke `hcm_resignations`~~ → **DONE**: Migration `2026_05_29_000001_add_approved_by_to_hcm_resignations.php` + model fillable/casts updated. `HcmResignationController::update()` set `approved_by_user_id` + `approved_at` saat status→approved. Notifikasi via `ResignationApprovalRequestedNotification` saat store pending.
12. ~~Wire termination workflow stages ke config~~ → **DONE**: `HcmTerminationController::store()` notifikasi `TerminationApprovalRequestedNotification` ke configured approvers saat `workflow_stage = draft_review`. Existing workflow actor tracking (workflow_reviewed_by, workflow_approved_by, workflow_finalized_by) tidak diubah.

### Phase 5 — Expense & Offer (butuh modul baru)
13. Bangun modul Expense dari awal (model, migration, API, UI)
14. Bangun modul Offer dari awal (model, migration, API, UI)
15. Keduanya langsung consume `hcm_approval_configs` dari day 1

---

## 12. Checklist Definition of Done (per Phase)

- [ ] Migration berjalan clean (`php artisan migrate --force`)
- [ ] PHPUnit tests: happy path + 401/403/422 untuk setiap endpoint
- [ ] API response shape `{ success, data?, error? }` konsisten
- [ ] RBAC: endpoint hanya accessible oleh admin/owner
- [ ] OpenAPI `docs/api/openapi.yaml` di-update
- [ ] Blade tersambung ke API (bukan hardcoded lagi)
- [ ] Integrasi dengan modul target (leave/overtime) verified manual

---

## 13. Anomali Ditemukan (wajib diselesaikan sebelum/saat implementasi)

> **Ini adalah temuan dari analisis codebase 2026-05-28. Setiap anomali HARUS diaddress sesuai tindakan yang ditentukan. Jangan skip.**

### ANOMALI-1 — `routes/web/settings.php` adalah dead code [SEVERITY: HIGH] ✅ RESOLVED

**Fakta:** File `backend/routes/web/settings.php` ada dan berisi route `approval-settings`, tapi tidak pernah di-`require` di `web.php` maupun `app.php`. Hanya `routes/web/saas.php` yang di-include. Semua route di file ini tidak pernah terdaftar di Laravel.

**Route yang aktif saat ini (di `web.php` line 1591):**
```php
Route::get('/approval-settings', function () {
    return view('approval-settings');  // → views/approval-settings.blade.php
})->middleware('hcm.web.admin')->name('approval-settings');
```

**Catatan:** `views/approval-settings.blade.php` isinya hanya `@include('settings.approval-settings')` — jadi konten aslinya di `views/settings/approval-settings.blade.php` (ini benar dan fix typo kita sudah ke file yang tepat).

**Resolusi:** Route live ada di `web.php`. `routes/web/settings.php` adalah dead code yang diabaikan. Route baru ditambahkan langsung ke `web.php` sesuai pola yang dipakai route lain di sekitarnya. Dead file tidak dihapus karena diluar scope, tetapi sudah dikonfirmasi tidak menyebabkan konflik.

---

### ANOMALI-2 — Ghost feature code `leave_approval_flow` [SEVERITY: MEDIUM] ✅ RESOLVED

**Fakta:** `backend/config/hcm_feature_permission_contract.php` mendefinisikan:
```php
'leave.approve' => ['any_of' => ['leave_management', 'leave_approval_flow']],
'leave.reject'  => ['any_of' => ['leave_management', 'leave_approval_flow']],
```

Tapi `leave_approval_flow` **tidak terdaftar** di `saas_package_feature_catalog.php` maupun `mvp_feature_codes`. Feature code ini tidak bisa pernah di-assign ke package apapun.

**Resolusi (2026-05-29):** `leave_approval_flow` ditambahkan ke `saas_package_feature_catalog.php` di bawah module `leave` sebagai add-on. Ini menjadikannya addressable via subscription management. Permission contract tetap berisi keduanya — design yang benar untuk Phase 2 di mana non-admin configured approvers akan mendapat feature code ini (tanpa full `leave_management` privileges).

---

### ANOMALI-3 — `leave_approvals` table adalah orphan infrastructure [SEVERITY: HIGH] ✅ RESOLVED

**Fakta:** Migration membuat tabel `leave_approvals` tapi **zero code** yang pernah menulis ke tabel ini.

**Resolusi (2026-05-29):** `ApprovalConfigService::populateLeaveApprovals()` dipanggil saat `LeaveRequest` dibuat — mengisi `leave_approvals` rows per configured approver. `ApprovalConfigService::processApprovalDecision()` dipanggil saat admin approve/decline — mengupdate baris yang relevan dengan `status`, `acted_at`, dan `notes`. Fallback ke legacy flow (notif ke semua tenant admin) jika tidak ada config.

---

### ANOMALI-4 — `LeaveRequest` tidak punya kolom `approved_by` [SEVERITY: MEDIUM] ✅ RESOLVED

**Fakta:** `OvertimeRequest` punya `approved_by_user_id` + `approved_at`. Tapi `LeaveRequest` fillable tidak punya field approved_by.

**Resolusi (2026-05-29):** Migration `2026_05_28_000002_add_approved_by_to_leave_requests.php` menambah `approved_by_user_id` dan `approved_at` ke `leave_requests`. Model `LeaveRequest` fillable dan casts diupdate. Controller mengisi kolom ini saat transisi ke `approved`.

---

### ANOMALI-5 (Baru ditemukan) — Token + Company ID key mismatch di Blade [SEVERITY: CRITICAL] ✅ FIXED 2026-05-29

**Fakta:** `approval-settings.blade.php` menggunakan `localStorage.getItem('hcm_token')` dan `localStorage.getItem('hcm_active_company_id')` — kedua key ini tidak pernah di-set oleh sistem. Key kanonik adalah `arcav_access_token` dan `arcav_active_tenant` (JSON object dengan field `companyId`).

**Dampak:** Semua API call dari halaman approval settings selalu mengirim token kosong → 401 Unauthorized. `X-Company-Id` header selalu kosong → tenant context failure.

**Fix:** `getAuthHeaders()` di Blade diubah mengikuti pola kanonik dari `preferences.blade.php` dan `authentication-settings.blade.php`:
```js
var token = (window.AuthApi && typeof window.AuthApi.getToken === 'function' && window.AuthApi.getToken())
    || localStorage.getItem('arcav_access_token')
    || sessionStorage.getItem('arcav_access_token') || '';
var ctx = JSON.parse(localStorage.getItem('arcav_active_tenant') || '{}');
companyId = ctx.companyId ? String(ctx.companyId) : '';
```

---

### ANOMALI-6 (Baru ditemukan) — `LeaveNextApproverNotification` tidak pernah dikirim [SEVERITY: HIGH] ✅ FIXED 2026-05-29

**Fakta:** `LeaveNextApproverNotification` class sudah dibuat, `processApprovalDecision()` service sudah mengembalikan `next_approvers` collection — tapi return value di controller sama sekali tidak digunakan. Dalam sequence mode, saat Level 1 approver menyetujui, Level 2 tidak pernah dinotifikasi.

**Fix:** Controller kini capture return value dan iterasi `next_approvers` untuk kirim `LeaveNextApproverNotification`:
```php
$approvalDecision = $approvalConfigService->processApprovalDecision(...);
if ($toStatus === 'approved' && $approvalDecision['next_approvers']->isNotEmpty()) {
    foreach ($approvalDecision['next_approvers'] as $nextApprover) {
        $nextApprover->notify(new LeaveNextApproverNotification($r->fresh()));
    }
}
```

---

### ANOMALI-7 (Baru ditemukan) — Cross-company approver injection [SEVERITY: HIGH / SECURITY] ✅ FIXED 2026-05-29

**Fakta:** Endpoint `PUT /v1/hcm/approval-settings/{module}` memvalidasi bahwa `approverUserIds` merupakan user yang ada (`exists:users,id`) tapi **tidak memverifikasi bahwa user tersebut adalah member aktif perusahaan**. Admin bisa memasukkan user dari tenant lain sebagai approver — pelanggaran multi-tenant isolation.

**Fix:** Ditambahkan check setelah validasi di `HcmApprovalSettingsController::update()`:
```php
$memberIds = CompanyUser::query()
    ->where('company_id', $companyId)
    ->where('status', 'active')
    ->whereIn('user_id', $validated['approverUserIds'])
    ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
$outsiders = array_diff(array_map('intval', $validated['approverUserIds']), $memberIds);
if (! empty($outsiders)) { return 422 APPROVER_NOT_IN_COMPANY; }
```

---

## 14. Gap & Asumsi Eksplisit

| Item | Status |
|---|---|
| Apakah "simultaneous" berarti semua harus approve atau cukup satu? | **BELUM DITENTUKAN** — perlu keputusan product owner sebelum implementasi Phase 1 |
| Apakah config berlaku per-department atau per-company? | **Asumsi saat ini: per-company** — jika perlu per-department, schema perlu tambahan kolom `department_id` |
| Notifikasi ke approver (email/in-app) | Infrastruktur sudah ada (`NotificationPayloadFactory`), tinggal tambah class baru — detail di Seksi 6 |
| Apakah approver bisa di-delegate (leave of absence)? | **Out of scope** untuk Phase 1-3 |
| Modul Expense dan Offer | **Belum ada di runtime** — tidak bisa diintegrasikan sampai modulnya dibangun |
| `routes/web/settings.php` — register atau hapus? | **BELUM DIPUTUSKAN** — lihat ANOMALI-1 |
| `leave_approval_flow` feature code — keep atau hapus? | **BELUM DIPUTUSKAN** — lihat ANOMALI-2 |
