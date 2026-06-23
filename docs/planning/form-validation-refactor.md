# Form Validation & UI Compliance — Audit & Plan

## Form Validation

### Global Helper
**File:** `frontend/resources/js/core/arcav-validation.js`
**Fungsi:** `ArcavValidation.validateForm(formEl)`
**Wajib dipanggil di:** Setiap form modal CREATE/EDIT.

```js
if (!ArcavValidation.validateForm(form)) { return; }
```

### CSS Rules (di `frontend/resources/css/style.css`)
```css
.form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545 !important; }
.form-control.is-invalid ~ .invalid-feedback,
.form-select.is-invalid ~ .invalid-feedback { display: block; }
```

### Blade Template
Setiap field `required`/`minlength` WAJIB punya:
```blade
<div class="mb-3">
    <label class="form-label" for="id">Label</label>
    <input id="id" type="text" class="form-control" required>
    <div class="invalid-feedback">Pesan validasi.</div>
</div>
```

### Lint Enforcement
- Pre-commit: `scripts/lint-form-validation.sh` — tolak commit kalo ada inline pattern
- GitHub Actions: `.github/workflows/ui-compliance.yml` — cek compliance di PR

---

## UI Patterns — Global Standards

### ✅ SUDAH STANDAR & WAJIB

| Utility | Lokasi | Wajib? |
|---|---|---|
| `ArcavUi.showToast(msg, type)` | `core/api-client.js` | WAJIB — ganti semua `notify()` inline |
| `ArcavUi.confirmDelete(msg, title)` | `core/api-client.js` | WAJIB — ganti `confirm()`/`alert()` native |
| `ArcavUi.showInfo(title, body)` | `core/api-client.js` | WAJIB untuk info modal |
| `ArcavUi.selectOption(payload)` | `core/api-client.js` | Opsional |
| `ArcavValidation.validateForm(form)` | `core/arcav-validation.js` | WAJIB — form CREATE/EDIT |
| `AuthPermissions.hasPermission()` | `auth-permissions-utils.js` | WAJIB — jangan akses `window.AuthUser` langsung |

### 🔴 GAP — Perlu Dibuat

| Gap | Skrg | Saran |
|---|---|---|
| **Button loading** | 40+ file: `btn.disabled=true; btn.textContent="Menyimpan..."` | `ArcavUi.disableButton(btn, "Menyimpan...")` / `enableButton(btn)` |
| **Format tanggal** | 15+ variasi lokal | `ArcavUi.formatDate(date, format?)` |
| **Format Rupiah** | 4+ file redefinisi | `ArcavUi.formatRupiah(value)` |
| **escapeHtml** | 5+ file redefinisi | `ArcavUi.escapeHtml(str)` |
| **formatApiError** | 4+ file redefinisi | `ArcavUi.formatApiError(data, status)` |

### 🟡 DEAD CODE — Cleanup

| Masalah | Fix |
|---|---|
| `ApiClient.toast()` dipanggil 6 file, **gak pernah didefinisikan** | Delegasi ke `ArcavUi.showToast` atau hapus |
| `ArcavUi.showSuccess()` dipanggil tapi **gak ada methodnya** | Tambah alias: `ArcavUi.showSuccess = function(m){ArcavUi.showToast(m,"success")}` |
| `ArcavUi.toast` (tanpa "show") dipanggil 3 file | Tambah alias atau ganti caller |
| **Empty state** — gak ada template global | Bikin `hcm/partials/hcm-empty-state.blade.php` |

---

## CRUD Form Coverage

### ✅ Lengkap (feedback >= required) — 51 file
`users`, `performance-modals`, `shift-modals`, `holiday-modals`, `leave-settings-modals`, `training-modals`, `trainer-modals`, `promotion-modals`, `resignation-modals`, `termination-modals`, `teams`, `employees`, `schedule-timing`, `departments`, `designations`, dll.

### ⚠️ Sebagian (feedback < required) — 14 file
`crm/companies` (8/12), `assets` (5/13), `performance-modals` (7/9), `subscriptions` (3/8), `packages` (3/6), `shifts` (5/7), `tickets` (2/3), `document-center` (3/4), dll.

### ❌ Kritis (required > 0, feedback = 0) — 9 file
`salary-component-modals` (10 req), `bpjs-governance` (6), `payroll-thr` (5), `pkwt-compensation` (5), `tax-rates` (3), `overtime-modals` (2), `leave-type` (2), `roles-permissions` (2), `employee-salary-compensation` (1).

### 🔵 Non-Required (0 required fields)
39 file — field tanpa `required`, validasi FE tidak aktif.

---

## GitHub Workflow Compliance

**File:** `.github/workflows/ui-compliance.yml`
**Trigger:** PR ke `main`
**Checks:**
1. Form validation lint — inline pattern terlarang di file JS baru
2. `ArcavUi.showToast` — pastikan gak ada `notify()` inline baru
3. `ArcavUi.confirmDelete` — pastikan gak ada `confirm()` native baru

---

## Referensi

- Global helper: `frontend/resources/js/core/arcav-validation.js`
- CSS rules: `frontend/resources/css/style.css` (cari `.form-control.is-invalid`)
- Contoh implementasi: `frontend/resources/js/employees/users-management.js`
- Blade contoh: `backend/resources/views/administration/rbac/users.blade.php`
- Lint script: `scripts/lint-form-validation.sh`
- AGENTS.md: §16 Form Validation Standard
