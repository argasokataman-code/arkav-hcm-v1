# Performance (Phase 1)

## Ringkasan

Modul Performance Phase 1 menyediakan alur penilaian sederhana dengan workflow self review oleh employee, manager review oleh atasan, dan final scoring oleh HCM Admin. Modul ini menjadi pusat appraisal formal dan juga berbagi konteks dengan goal tracking, attendance, dan leave saat organisasi membutuhkan gambaran performa yang lebih utuh.

## Akses

- Employee: mengisi dan submit self review milik sendiri saat cycle aktif.
- Manager: melihat review tim dan memberi manager review pada scope bawahan.
- HCM Admin: mengelola indicator template, cycle, create review, final score, dan finalize.

## UI Aktif

- `/performance-indicator` untuk master indikator.
- `/performance-appraisal` untuk cycle dan list review admin.
- `/performance-review` untuk editor review employee/manager/admin.
- JS aktif: `performance-data.js`.

## Flow Bisnis End-to-End

1. HCM Admin menyiapkan template indikator dan appraisal cycle.
2. Admin membuat review untuk employee.
3. Employee mengisi self review saat status masih `draft` dan cycle aktif, lalu submit.
4. Manager mengisi manager review dan menyelesaikan tahap manager.
5. HCM Admin memberi final score dan finalize review.

## Lifecycle Dan Keputusan Bisnis

- Draft → submitted → manager_reviewed → finalized menjadi jalur utama appraisal.
- Manager scope mengikuti `employee_profiles.manager_user_id`.
- Scope `all` hanya untuk admin; employee dan manager hanya boleh melihat scope yang relevan.
- Scoring model hybrid menggabungkan KPI dan behavioral score.
- Review creation sekarang menyimpan `company_id` tenant aktif agar metrik leave/performance tetap tenant-scoped.

## Integrasi

- Goal Tracking: goal tracking melengkapi appraisal dengan sasaran kerja yang dapat dilihat bersama pada konteks performance. Lihat `docs/features/goal-tracking/README.md`.
- Leave & Holidays serta Attendance: metrik frekuensi leave dan absenteeism menjadi input tambahan pada evaluasi tertentu di controller performance. Lihat `docs/features/leave-and-holidays/README.md` dan `docs/features/attendance-shift-schedule/README.md`.
- Employees & Organization: relasi employee-manager dan detail user berasal dari directory employee. Lihat `docs/features/employees-organization/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

### Role & akses

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

### Kontrak identifier aktif

- `POST /v1/hcm/performance/reviews` menerima `cycleId`, `userId`, dan `templateId` dalam format numeric `id` sebagai kontrak aktif UI.
- UUID masih diterima sebagai fallback legacy untuk ketiga field tersebut.
- `userId` target wajib anggota tenant aktif; review baru disimpan dengan `company_id` tenant aktif agar metrik leave/performance tidak bocor lintas company.

## Existing Vs Target

- Existing: workflow appraisal dasar, RBAC per scope, indicator templates, cycles, dan reviews sudah aktif.
- Target: pengayaan analitik performa lintas modul dan visualisasi lebih dalam masih bisa dikembangkan di fase berikutnya.

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

Catatan wiring aktif:
- `performance-data.js` mengirim auth + tenant headers ke semua request `apiRequest()`.
- Page admin `performance-indicator` / `performance-appraisal` akan fallback ke `/performance-review` saat `performance.manage` tidak ada.
- Page review menyembunyikan scope `all` saat user tidak punya permission admin performance.

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
- UI wiring test: `backend/tests/ui/performance.wiring.test.js`

