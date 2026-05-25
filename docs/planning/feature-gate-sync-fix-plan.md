# Feature Gate Sync Fix Plan

**Dibuat:** 2026-05-25  
**Diperbarui:** 2026-05-25 — Sprint 1 ✅ + Sprint 2 ✅ selesai. Semua test pass (1077 PHPUnit, 241 Vitest).  
**Trigger:** Audit menyeluruh menemukan banyak fitur/modul yang tidak sinkron antara catalog, sidebar, web routes, dan API routes — sehingga feature classification via `/packages` UI tidak efektif.

---

## Latar Belakang

Sistem package/subscription punya 3 layer enforcement:
1. **Sidebar gate** — variabel `$hasX` / `$canSeeXMenu` di `sidebar.blade.php` + `header.blade.php`
2. **Web route gate** — middleware `hcm.web.feature:code` di `routes/web.php`
3. **API route gate** — middleware `hcm.api.feature:code` di `routes/api/*.php`

Dan 1 layer definisi:
4. **Feature catalog** — `config/saas_package_feature_catalog.php` (sumber kebenaran UI `/packages`)

Jika salah satu dari 4 layer ini tidak sinkron, maka:
- Fitur bisa diakses meski tidak ada di paket (bypass via URL langsung)
- Fitur tidak bisa di-assign ke paket (tidak ada di catalog)
- Fitur tersembunyi di sidebar tapi masih accessible via direct URL

---

## Metodologi Audit

Dibandingkan: **Catalog** ↔ **Sidebar** ↔ **Web Routes** ↔ **API Routes**

Legend status:
- `✅` = sudah benar
- `❌` = tidak ada / salah
- `⚠️` = partial / perlu perbaikan

---

## Temuan & Rencana Fix

### Blok 1 — Quick Fix (P0): Feature tidak ada di catalog + route tanpa middleware

**Effort:** Kecil, zero regression risk

#### 1.1 `overtime` tidak ada di feature catalog

| Layer | Status | Action |
|---|---|---|
| Catalog | ❌ TIDAK ADA | Tambah ke group `attendance` di catalog |
| Sidebar | ✅ `$canSeeOvertimeMenu` | Sudah fix (2026-05-25) |
| Web routes | ✅ `hcm.web.feature:overtime` | Sudah fix (2026-05-25) |
| API routes | ✅ `hcm.api.feature:overtime` | Sudah fix (2026-05-25) |

**File:** `backend/config/saas_package_feature_catalog.php`  
**Action:** Tambah entry ke group `attendance`:
```php
['code' => 'overtime', 'name' => 'Overtime Management', 'description' => 'Manajemen pengajuan overtime: tipe overtime, request, approval, dan kalkulasi.'],
```

---

#### 1.3 `/performance-review` dan `/goal-tracking` tidak punya middleware apapun

| Layer | Status | Action |
|---|---|---|
| Web route `/performance-review` | ❌ **NO middleware at all** | Tambah `hcm.web.admin` + `hcm.web.feature:performance` |
| Web route `/goal-tracking` | ❌ **NO middleware at all** | Tambah `hcm.web.admin` + `hcm.web.feature:performance` |

**File:** `backend/routes/web.php` (baris ~865 dan ~873)
```php
// BEFORE:
Route::get('/performance-review', function () {
    return view(view: 'performance-review');
})->name('performance-review');

Route::get('/goal-tracking', function () {
    return view(view: 'goal-tracking');
})->name('goal-tracking');

// AFTER:
Route::get('/performance-review', function () {
    return view(view: 'performance-review');
})->middleware(['hcm.web.admin', 'hcm.web.feature:performance'])->name('performance-review');

Route::get('/goal-tracking', function () {
    return view(view: 'goal-tracking');
})->middleware(['hcm.web.admin', 'hcm.web.feature:performance'])->name('goal-tracking');
```

---

#### 1.3 `/payslip` tidak punya middleware apapun

| Layer | Status | Action |
|---|---|---|
| Web route | ❌ **NO middleware at all** | Tambah `hcm.web.feature:payroll` + `hcm.web.admin` |

**File:** `backend/routes/web.php`  
**Action:**
```php
// BEFORE:
Route::get('payslip', function() {
    return view('payslip');
})->name('payslip');

// AFTER:
Route::get('payslip', function() {
    return view('payslip');
})->middleware(['hcm.web.admin', 'hcm.web.feature:payroll'])->name('payslip');
```

---

### Blok 2 — Web Route Feature Gates Missing (P1)

**Konteks:** Route-route ini hanya pakai `hcm.web.admin` — admin bisa akses via URL langsung meski fitur tidak ada di paket mereka.

#### 2.1 Leave Module web routes

**File:** `backend/routes/web.php`

| Route | Gate saat ini | Gate yang ditambah |
|---|---|---|
| `/leaves` | `hcm.web.admin` | + `hcm.web.feature:leave_management` |
| `/leaves-employee` | `hcm.web.employee:leaves` | + `hcm.web.feature:leave_management` |
| `/leave-settings` | `hcm.web.admin` | + `hcm.web.feature:leave_management` |
| `/leave-type` | `hcm.web.admin` | + `hcm.web.feature:leave_management` |
| `/holidays` | `hcm.web.admin` | + `hcm.web.feature:holiday_calendar` |

---

#### 2.2 Attendance Module web routes

**File:** `backend/routes/web.php`

| Route | Gate saat ini | Gate yang ditambah |
|---|---|---|
| `/attendance-admin` | `hcm.web.admin` | + `hcm.web.feature:attendance` |
| `/timesheets` | `hcm.web.admin` | + `hcm.web.feature:attendance` |
| `/schedule-timing` | `hcm.web.admin` | + `hcm.web.feature:attendance` |
| `/shift-master` | `hcm.web.admin` | + `hcm.web.feature:attendance` |

> **Catatan:** Apakah `timesheets/schedule-timing/shift-master` harus ikut `attendance` atau `attendance_shift_scheduling`?  
> Sementara pakai `attendance` karena `attendance_shift_scheduling` belum ada di paket manapun dan belum ada sidebar variable-nya (lihat Blok 4).

---

#### 2.3 Performance Module web routes

**File:** `backend/routes/web.php`

| Route | Gate saat ini | Gate yang ditambah |
|---|---|---|
| `/performance-indicator` | `hcm.web.admin` | + `hcm.web.feature:performance` |
| `/performance-review` | (tidak ada middleware!) | + `hcm.web.admin` + `hcm.web.feature:performance` |
| `/performance-appraisal` | `hcm.web.admin` | + `hcm.web.feature:performance` |
| `/goal-tracking` | (tidak ada middleware!) | + `hcm.web.admin` + `hcm.web.feature:performance` |
| `/goal-type` | `hcm.web.admin` | + `hcm.web.feature:performance` |

---

#### 2.4 Employee Lifecycle web routes

**File:** `backend/routes/web.php`

| Route | Gate saat ini | Gate yang ditambah |
|---|---|---|
| `/promotion` | `hcm.web.admin` (group) | + `hcm.web.feature:employee_lifecycle` |
| `/resignation` | `hcm.web.admin` (group) | + `hcm.web.feature:employee_lifecycle` |
| `/termination` | `hcm.web.admin` (group) | + `hcm.web.feature:employee_lifecycle` |

---

### Blok 3 — API Route Feature Gates Missing (P1)

**Konteks:** API endpoints ini bisa dipanggil langsung oleh tenant yang tidak punya fitur tersebut.

#### 3.1 Employee Lifecycle API

**File:** `backend/routes/api/promotion.php`, `backend/routes/api/resignation.php`, `backend/routes/api/termination.php`

**Action:** Tambah `hcm.api.feature:employee_lifecycle` ke middleware group masing-masing.

```php
// BEFORE:
Route::prefix('v1/hcm/promotions')->middleware(['api.token', 'tenant.context'])->group(...)

// AFTER:
Route::prefix('v1/hcm/promotions')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:employee_lifecycle'])->group(...)
```

Sama untuk `resignations` dan `terminations`.

---

#### 3.2 Tickets API

**File:** `backend/routes/api/ticket.php`

**Action:** Tambah `hcm.api.feature:tickets` ke middleware group.

```php
// BEFORE:
Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(...)

// AFTER:
Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:tickets'])->group(...)
```

---

#### 3.3 Salary Components API

**File:** `backend/routes/api/salary-component.php`

**Action:** Salary components adalah bagian dari payroll — tambah `hcm.api.feature:payroll`.

```php
// BEFORE:
Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(...)

// AFTER:
Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:payroll'])->group(...)
```

> **Alternatif:** Buat feature code `payroll_components` terpisah jika ingin granular (lihat Blok 4).

---

### Blok 4 — Sub-feature Catalog vs Implementation Gap (P2)

**Konteks:** Feature-feature ini ada di catalog tapi tidak bisa di-disable secara individual karena ikut gate parent feature. Jika ditambahkan ke paket, menambahnya tidak ada efek terpisah dari parent-nya.

Ini perlu **analisis bisnis** sebelum implement: apakah sub-feature ini memang harus standalone, atau cukup ikut parent?

#### 4.1 `attendance_shift_scheduling`
- **Catalog:** Ada di grup `attendance`
- **Sidebar:** Timesheets/schedule-timing/shift-master ikut `$canSeeAttendanceMenu` dengan kondisi `$isHcmAdmin`
- **Routes:** Ikut `hcm.web.admin`, tidak ada gate `attendance_shift_scheduling`
- **Opsi A:** Tambah `$canSeeShiftSchedulingMenu = $featureBypass || $hasAttendanceShiftScheduling` + gate di 6 sidebar files + 3 web routes
- **Opsi B:** Merge ke `attendance` (hapus dari catalog sebagai standalone)

#### 4.2 `leave_approval_flow`
- **Catalog:** Ada di grup `leave`
- **Implementasi:** Tidak ada sidebar variable, tidak ada route gate terpisah — semua approval UI ikut `leave_management`
- **Opsi A:** Tambah gate untuk approval flow khusus (butuh analisis halaman/API mana yang termasuk)
- **Opsi B:** Merge ke `leave_management` (hapus dari catalog sebagai standalone)

#### 4.3 `payroll_components`
- **Catalog:** Ada di grup `payroll`
- **Web route:** `salary-component-master` sudah di bawah `hcm.web.feature:payroll` — tidak terpisah
- **API:** `salary-component.php` tidak ada feature gate (Blok 3.3)
- **Opsi A:** Gunakan `payroll_components` sebagai gate terpisah di route dan API
- **Opsi B:** Biarkan ikut `payroll` (hapus dari catalog atau rename jadi sub-label)

#### 4.4 `payroll_thr`
- **Catalog:** Ada di grup `payroll`
- **Web route:** `/payroll-thr` sudah di bawah `hcm.web.feature:payroll` — tidak terpisah
- **Opsi A:** Tambah `hcm.web.feature:payroll_thr` gate terpisah di route `/payroll-thr`
- **Opsi B:** Biarkan ikut `payroll`

#### 4.5 `goal_tracking`
- **Catalog:** Ada di grup `performance`
- **Sidebar:** `/goal-tracking`, `/goal-type` ikut `$canSeePerformanceMenu`
- **Routes:** Tidak ada feature gate (Blok 2.3 akan fix dengan `performance`)
- **Opsi A:** Tambah `$canSeeGoalTrackingMenu` terpisah
- **Opsi B:** Biarkan ikut `performance`

#### 4.6 `performance_goal_tracking`
- **Catalog:** Ada di grup `performance`
- **Implementasi:** Tidak ada referensi implementasi yang berbeda dari `goal_tracking`
- **Action:** Klarifikasi dengan product — apakah ini duplikat `goal_tracking`? Jika ya, hapus dari catalog.

---

### Blok 5 — Temuan Tambahan dari Audit Lanjutan (P1)

Temuan berikut **tidak ada di audit awal** dan ditemukan saat audit lanjutan.

#### 5.1 `ai_assistant` tidak ada di feature catalog

| Layer | Status | Action |
|---|---|---|
| Catalog | ❌ TIDAK ADA | Tambah ke group baru `platform` atau `ai` |
| API gate | ✅ `hcm.api.feature:ai_assistant` dipakai di `dashboard.php` | Sudah ada gate, hanya catalog yang kosong |
| Web routes | N/A | Tidak ada web route terpisah untuk AI |

**File:** `backend/config/saas_package_feature_catalog.php`  
**Action:** Tambah entry (tentukan group-nya — kandidat: `platform` atau group baru `ai`):
```php
['code' => 'ai_assistant', 'name' => 'AI Assistant', 'description' => 'Chat assistant berbasis AI untuk query data HCM.'],
```

> **Impact saat ini:** Endpoint `/v1/hcm/ai/chat` dan `/v1/hcm/ai/intents` memang tergated dengan `hcm.api.feature:ai_assistant`, tapi karena feature code-nya tidak ada di catalog, tidak ada tenant yang bisa di-assign feature ini lewat UI `/packages`. Artinya AI assistant selalu menghasilkan 403 untuk semua tenant.

---

#### 5.2 `spt-masa` dan `tax-governance` routes tidak punya `tax_governance` feature gate

**Web routes** (`routes/web.php`):

| Route | Gate saat ini | Gate yang ditambah |
|---|---|---|
| `/spt-masa-pph21` | `hcm.web.admin` | + `hcm.web.feature:tax_governance` |
| `/spt-masa-pph21/{uuid}` | `hcm.web.admin` | + `hcm.web.feature:tax_governance` |

**API routes**:

| File | Gate saat ini | Gate yang ditambah |
|---|---|---|
| `routes/api/spt-masa.php` | `tenant.context` only | + `hcm.api.feature:tax_governance` |
| `routes/api/tax-governance.php` | `tenant.context` only | + `hcm.api.feature:tax_governance` |

---

### Blok 6 — Grey Area (P3) ✅ RESOLVED

| File | Keputusan | Status |
|---|---|---|
| `routes/api/bpjs-governance.php` | Feature sendiri `bpjs_governance` | ✅ Gate ditambah |
| `routes/api/allowance-governance.php` | Bundled ke `payroll` | ✅ Gate ditambah |
| `routes/api/calendar.php` | Gate `holiday_calendar` | ✅ Gate ditambah |
| `routes/api/reports.php` | Tanpa feature gate (platform analytics) | ✅ Tidak perlu gate |
| `routes/api/notifications.php` | Tanpa feature gate (sudah ada `notifications` feature di catalog, cukup admin-only) | ✅ Tidak perlu gate baru |
| `routes/api/user-management.php` | Platform feature, tidak perlu feature gate | ✅ Tidak perlu gate |

---

## Ringkasan Eksekusi

### Sprint 1 — Blok 1 + 2 + 3 (P0 + P1)

Semua perubahan ini **tidak butuh keputusan product** dan **tidak ada UI/UX change** — hanya tambah middleware.

**Checklist:**

**Blok 1 — Catalog + No-Middleware:**
- [x] **1.1** Tambah `overtime` ke feature catalog ✅
- [x] **1.2** Fix `/performance-review` + `/goal-tracking` — tambah `hcm.web.admin` + `hcm.web.feature:performance` ✅
- [x] **1.3** Fix `/payslip` route — tambah middleware ✅
- [x] **1.4** Tambah `ai_assistant` ke feature catalog ✅
- [x] **1.5** Fix `spt-masa` web routes — tambah `hcm.web.feature:tax_governance` ✅
- [x] **1.6** Fix `routes/api/spt-masa.php` + `routes/api/tax-governance.php` — tambah `hcm.api.feature:tax_governance` ✅

**Blok 2+3 — Missing feature gates:**
- [x] **2.1** Leave web routes — tambah `hcm.web.feature:leave_management` + `holiday_calendar` ✅
- [x] **2.2** Attendance web routes — tambah `hcm.web.feature:attendance` ✅
- [x] **2.3** Performance web routes — tambah `hcm.web.feature:performance` ✅
- [x] **2.4** Lifecycle web routes — tambah `hcm.web.feature:employee_lifecycle` ✅
- [x] **3.1** Lifecycle API routes — tambah `hcm.api.feature:employee_lifecycle` ✅
- [x] **3.2** Ticket API — controller-handled (`SUBSCRIPTION_REQUIRED`), tidak ditambah middleware gate ✅
- [x] **3.3** Salary component API — tambah `hcm.api.feature:payroll` ✅

**Test gate setelah Sprint 1:**
```bash
bash scripts/local-test-gate.sh
```

**Smoke test:**
- Login sebagai sembrani (Starter) → URL langsung `/leaves` harus 403/redirect
- Login sebagai sembrani → URL langsung `/performance-indicator` harus 403/redirect
- Login sebagai tenant dengan `leave_management` → `/leaves` harus accessible

---

### Sprint 2 — Blok 4 + Grey Area (P2 + P3) ✅ SELESAI

**Keputusan product yang diambil (2026-05-25):**

| Feature | Keputusan | Status |
|---|---|---|
| `attendance_shift_scheduling` | **Standalone** — dijual terpisah dari attendance | ✅ Gate diimplementasi |
| `leave_approval_flow` | **Bundled** ke `leave_management` — dihapus dari catalog | ✅ Dihapus dari catalog |
| `payroll_thr` | **Standalone** — THR bisa berdiri sendiri tanpa payroll | ✅ Gate diimplementasi |
| `performance_goal_tracking` | **Duplikat** `goal_tracking` — dihapus dari catalog | ✅ Dihapus dari catalog |
| `bpjs_governance` | **Feature sendiri** terpisah dari `tax_governance` | ✅ Ditambah ke catalog + gate API |
| `calendar` events API | **Gate** dengan `holiday_calendar` | ✅ Gate diimplementasi |
| `reports` API | **Tanpa feature gate** (platform-level analytics) | ✅ Tidak perlu gate |

**Checklist Sprint 2:**
- [x] Hapus `leave_approval_flow` dari catalog ✅
- [x] Hapus `performance_goal_tracking` dari catalog ✅
- [x] Tambah `bpjs_governance` ke catalog + `mvp_feature_codes` ✅
- [x] Web: `/schedule-timing`, `/shift-master` → gate `attendance_shift_scheduling` ✅
- [x] Web: `/payroll-thr` → pindah keluar grup `payroll`, gate `payroll_thr` standalone ✅
- [x] API `attendance.php`: schedule-timing + smart-shifting + shifts → grup `attendance_shift_scheduling` ✅
- [x] API `payroll.php`: THR routes → grup `payroll_thr` ✅
- [x] API `bpjs-governance.php`: gate `bpjs_governance` ✅
- [x] API `calendar.php`: gate `holiday_calendar` ✅
- [x] Test gate: 1077 PHPUnit + 241 Vitest pass ✅

---

## File yang Diubah (Sprint 1 + Sprint 2) ✅

| File | Perubahan |
|---|---|
| `backend/config/saas_package_feature_catalog.php` | +`overtime`, +`ai_assistant`, +`bpjs_governance`; hapus `leave_approval_flow`, `performance_goal_tracking` |
| `backend/routes/web.php` | +20 route feature gates; fix 3 routes tanpa auth; restruktur `payroll-thr` standalone |
| `backend/routes/api/attendance.php` | Split grup: shift/schedule/smart-shifting → `attendance_shift_scheduling` |
| `backend/routes/api/payroll.php` | Split grup: THR routes → `payroll_thr` |
| `backend/routes/api/promotion.php` | +`hcm.api.feature:employee_lifecycle` |
| `backend/routes/api/resignation.php` | +`hcm.api.feature:employee_lifecycle` |
| `backend/routes/api/termination.php` | +`hcm.api.feature:employee_lifecycle` |
| `backend/routes/api/salary-component.php` | +`hcm.api.feature:payroll` |
| `backend/routes/api/spt-masa.php` | +`hcm.api.feature:tax_governance` |
| `backend/routes/api/tax-governance.php` | +`hcm.api.feature:tax_governance` |
| `backend/routes/api/allowance-governance.php` | +`hcm.api.feature:payroll` |
| `backend/routes/api/bpjs-governance.php` | +`hcm.api.feature:bpjs_governance` |
| `backend/routes/api/calendar.php` | +`hcm.api.feature:holiday_calendar` |
| `backend/tests/TestCase.php` | Fix: tambah `user` key ke return `createHcmAdminWithCompany()` |
| `backend/tests/Feature/WebHcmRouteGuardTest.php` | Hapus 3 path yang kini butuh admin auth |
| `backend/tests/Feature/EmployeeScopedWebRoutesTest.php` | Tambah `overtime` ke feature list test |

**Tidak ada perubahan:** Sidebar, UI, JS/frontend — tidak perlu rebuild.

---

## Risiko & Mitigasi

| Risiko | Kemungkinan | Mitigasi |
|---|---|---|
| Tenant existing yang aktif tiba-tiba kena 403 setelah Sprint 1 | Rendah — semua tenant aktif punya paket dengan fitur-fitur ini | Verifikasi `package_features` DB sebelum deploy |
| Route `/performance-review` dan `/goal-tracking` tidak punya middleware → menambah middleware bisa redirect user | Medium | Test smoke dengan user non-admin sebelum deploy |
| `salary-component.php` API di-gate `payroll` tapi dipakai di halaman non-payroll | Rendah — salary component adalah sub-fitur payroll | Check apakah ada UI page yang consume ini tanpa `payroll` context |

---

## Referensi

- Audit sumber: sesi 2026-05-25
- `docs/planning/attendance-correction-flow-fix-plan.md` — pola fix serupa
- `backend/config/saas_package_feature_catalog.php` — sumber kebenaran catalog
- `backend/app/Http/Middleware/EnsureCompanyFeatureForWebPage.php` — implementasi `hcm.web.feature:X`
- `backend/app/Http/Middleware/EnsureHcmApiFeatureAccess.php` (atau equivalent) — implementasi `hcm.api.feature:X`
