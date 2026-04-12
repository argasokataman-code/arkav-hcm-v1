## Performance (Phase 1) — README

Modul Performance Phase 1 menyediakan alur penilaian sederhana dengan workflow:

- **Employee** mengisi **self review** (draft → submit)
- **Manager** mengisi **manager review** (submitted → manager reviewed)
- **HCM Admin** mengisi **final score** dan **finalize**

### Role & akses (target)

- **Employee (karyawan non-admin)**:
  - Lihat & edit review milik sendiri saat status `draft`
  - Submit review saat cycle **active**
  - Tidak bisa melihat review orang lain
- **Manager**:
  - Lihat review tim (yang `manager_user_id` = user manager)
  - Mengisi skor manager saat status `submitted`
  - Menandai selesai (manager complete) → status `manager_reviewed`
  - Tidak punya akses admin-only (cycles, template, create review)
- **HCM Admin**:
  - CRUD master template indikator dan items
  - CRUD cycle (draft/active/closed) + activate/close
  - Create review untuk employee
  - Final scoring + finalize

Catatan: hubungan employee→manager diambil dari `employee_profiles.manager_user_id`.

### UI (web)

- `GET /performance-indicator` — master template indikator (admin)
  - Aset: `performance-data.js`
  - Modal: template + items
- `GET /performance-appraisal` — cycle + list review (admin)
  - Aset: `performance-data.js`
  - Modal: add/edit cycle, create review
- `GET /performance-review` — isi review (employee/manager/admin)
  - Aset: `performance-data.js`
  - Panel kiri: list review (scope: me/team/all)
  - Panel kanan: editor skor + notes sesuai role
  - Panduan: tombol **Panduan pemakaian** (modal) berisi langkah karyawan/manager/admin

### API (Phase 1)

Semua endpoint di bawah prefix **`/v1/hcm/performance`**.

#### Indicator templates (admin only)

- `GET /indicator-templates`
- `POST /indicator-templates`
- `PUT /indicator-templates/{id}`
- `DELETE /indicator-templates/{id}`
- `GET /indicator-templates/{id}/items`
- `POST /indicator-templates/{id}/items`
- `PUT /indicator-items/{itemId}`
- `DELETE /indicator-items/{itemId}`

#### Cycles (admin only)

- `GET /cycles`
- `POST /cycles`
- `PUT /cycles/{id}`
- `POST /cycles/{id}/activate`
- `POST /cycles/{id}/close`

#### Reviews (mixed RBAC)

- `GET /reviews?scope=me|team|all`  
  - `me`: owner only
  - `team`: manager only (by `manager_user_id`)
  - `all`: admin only
- `POST /reviews` (admin only) — create review dan pre-create score rows
- `GET /reviews/{id}` — owner OR manager OR admin
- `PUT /reviews/{id}` — owner update (draft only)
- `POST /reviews/{id}/submit` — owner submit (cycle active)
- `PUT /reviews/{id}/manager` — manager update (submitted only)
- `POST /reviews/{id}/manager-complete` — manager complete (submitted → manager_reviewed)
- `PUT /reviews/{id}/final` — admin final update (manager_reviewed/finalized)
- `POST /reviews/{id}/finalize` — admin finalize (manager_reviewed → finalized)

### Scoring model (hybrid)

- KPI: input **0–100** per item, dihitung weighted berdasarkan `weight` item KPI.
- Behavioral: rating `rating_scale_min..rating_scale_max` (default 1–5), dikonversi ke 0–100.
- Total hybrid: \( total = 0.7 \times KPI_{avg} + 0.3 \times Behavioral_{avg} \) (dibulatkan 2 desimal).

### Tests

- Feature test: `backend/tests/Feature/PerformanceApiTest.php`

