# Quick Reference — Critical Rules

> Fast checklist untuk development harian ARCAV HCM

---

## 🔴 MUST-KNOW (Jangan Lupakan!)

### Sebelum Mulai Coding
- [ ] Baca fitur di `docs/features/<feature>/README.md` atau matriks `docs/planning/active-hcm-templates-and-permissions.md`
- [ ] API sudah documented? Cek/update `docs/api/<feature>-api.md`
- [ ] Task require authentication? Pakai `api.token` middleware
- [ ] Admin-only? Pakai `EnsuresHcmAdmin` middleware

### Backend (Laravel)
- [ ] Endpoint route: prefix `/v1/hcm/`
- [ ] Request validation: FormRequest class (server-side wajib)
- [ ] Response format: `{ success: bool, data?, error? }`
- [ ] RBAC: server check (bukan hanya UI)
- [ ] Test: happy path + 403/401/422

### Frontend (Vue/JS)
- [ ] API client: single pattern (ApiClient class)
- [ ] Modal/Toast: reuse template, tidak custom `alert()`
- [ ] Modal file: `resources/views/hcm/partials/*.blade.php`
- [ ] JS loader: `footer-scripts.blade.php` conditional per-route
- [ ] Build + Copy: `npm run build` → copy ke `backend/public/build/js/`
- [ ] RBAC UI: sembunyikan tombol admin, tapi server enforce 403

### Database
- [ ] Migration namai jelas (cth: `2026_04_11_create_promotions_table`)
- [ ] Model: `$fillable` eksplisit
- [ ] Seeder: untuk test data, bukan dummy hardcode di Blade

### Security (Every Time)
| Sentuh | Wajib |
|---|---|
| **API endpoint** | `api.token` middleware, RBAC check, test 403 |
| **Route web** | Bukan sensitif tanpa auth; tamu → whitelist `arcav_hcm_web_guard.php` |
| **Input/upload** | Server validation, IDOR check, tipe/ukuran limit |
| **Secret** | `.env`, jangan commit password/token |
| **Dependency** | `composer audit` berkala |

---

## 🟡 SEBELUM CLOSE TASK (Mandatory Checklist)

**Security ✓:**
- [ ] Auth middleware applied
- [ ] RBAC checked di server (bukan UI)
- [ ] 401/403/422 responses OK
- [ ] Test written (happy + forbidden)
- [ ] No hardcoded secret

**Dokumentasi ✓:**
- [ ] `docs/planning/implementation-status.md` updated
- [ ] `docs/planning/active-hcm-templates-and-permissions.md` (matriks RBAC) updated if path/API changed
- [ ] `docs/features/<feature>/README.md` created/updated
- [ ] `docs/api/<feature>-api.md` updated if endpoint changed
- [ ] `docs/database/mysql-database-specification.md` updated if schema changed

**OpenAPI ✓:** (if API changed)
- [ ] `docs/api/openapi.yaml` updated
- [ ] Include: tags, summary, security requirement, request/response schema, error codes

---

## 🟢 POLA YANG SUDAH ADA (Gunakan!)

### Template (Jangan Bikin Baru)
```
✓ Card (list layout)
✓ Table (data grid)
✓ Badge (status)
✓ Button (primary/secondary/danger)
✓ Modal (form/confirm/view)
✓ Toast/Alert (feedback)
✓ Breadcrumb (navigation)
✓ Sidebar menu (navigation)
```

### API Response
```json
{ "success": true, "data": { /* ... */ } }
{ "success": false, "error": "message" }
```

### JS Loader
```blade
@if (Route::is('promotion.*'))
    <script src="{{ mix('js/promotion-data.js') }}"></script>
@endif
```

### RBAC Check
```javascript
if (window.hcmAdmin) {
    // Show admin button
}
```

### API Call
```javascript
ApiClient.post('/promotions', data)
    .then(res => {
        if (res.success) {
            window.ArcavUi.toast('OK', 'success');
        }
    });
```

---

## ❌ JANGAN

| Jangan | Gunakan |
|---|---|
| `window.alert()` | `ArcavUi.toast()` / modal |
| Custom modal/drawer | Template Bootstrap modal |
| Dummy data hardcode | API populate / Seeder / kosong placeholder |
| Hardcode secret | `.env` variable |
| Frontend RBAC only | Server enforce 403 |
| `$request->all()` | `$fillable` explicit |
| Multiple response format | `{ success, data, error }` consistent |
| Skip test | Happy path + 403/401/422 |
| Multiple design system | Bootstrap 5 only |

---

## 📂 File Reference

| File | Gunakan Untuk |
|---|---|
| `copilot-instructions.md` (root) | Overview project + rules |
| `backend/copilot-instructions.md` | API pattern + security |
| `frontend/copilot-instructions.md` | UI pattern + no dummy data |
| `docs/planning/active-hcm-templates-and-permissions.md` | Matriks role/path/API |
| `docs/features/<feature>/README.md` | Feature detail |
| `docs/api/<feature>-api.md` | API kontrak |
| `docs/api/openapi.yaml` | Swagger spec |
| `.cursor/rules/<rule>.mdc` | Detail rules (backup reference) |

---

## 🆘 Quick Decision Tree

**Q: Endpoint baru apa yang harus saya buat?**
→ Cek `docs/planning/active-hcm-templates-and-permissions.md` (matriks RBAC)

**Q: Pakai modal apa untuk form?**
→ Cek `resources/views/hcm/partials/` → reuse yang ada, jangan buat custom

**Q: Gimana format response API?**
→ `{ success: true/false, data?: {...}, error?: "message" }`

**Q: Kapan close task?**
→ Setelah: security ✓ + docs semua terdampak ✓ + OpenAPI ✓

**Q: Gimana test RBAC?**
→ Backend: test 403 untuk non-admin → Frontend: button disembunyikan (UX)

**Q: Data dummy di Blade OK?**
→ Tidak. Ganti dengan API, seeder, atau hapus placeholder.

**Q: Mesti custom component UI?**
→ Tanya user dulu. Default: gunakan Bootstrap pola yang ada.

---

**Updated:** April 2026  
**Kept:** `.cursor/rules/` for detailed reference  
**Use:** `copilot-instructions.md` untuk GitHub Copilot Chat
