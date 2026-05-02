# Document Center

## Ringkasan

**Document Center** adalah modul penyimpanan dan pengelolaan dokumen karyawan per-tenant. HR Admin bisa mengupload dokumen (kontrak, SK, sertifikat, dll.) dan mengontrol apakah dokumen tersebut dapat dilihat langsung oleh karyawan yang bersangkutan atau hanya oleh HR.

Scope Phase 1:
- CRUD **Document Categories** (admin-only mutasi).
- Upload dan pengelolaan **dokumen karyawan** dengan kontrol visibilitas.
- Download dokumen — HR bebas mengunduh semua; karyawan hanya dokumen miliknya sendiri yang berstatus `employee_visible`.
- Semua data di-scope ke active company (multi-tenant).

---

## Akses Per Role

| Role              | Akses                                                                                   |
|-------------------|-----------------------------------------------------------------------------------------|
| HCM Admin         | CRUD categories, upload/edit/delete semua dokumen semua karyawan, download semua        |
| `document_center.manage` | Sama dengan HCM Admin (permission granular)                                    |
| `document_center.view`   | Lihat list dokumen semua karyawan (read-only), download semua                  |
| Karyawan biasa    | Hanya melihat & mengunduh dokumen milik sendiri yang `visibility = employee_visible`    |

---

## Halaman UI

| URL               | Blade View                                      | Deskripsi                                  |
|-------------------|-------------------------------------------------|--------------------------------------------|
| `/document-center` | `backend/resources/views/hrm/document-center.blade.php` | List dokumen + filter + CRUD modal  |

**JS wiring:** `frontend/resources/js/document-center.js` → dikopi ke `backend/public/build/js/document-center.js` via Vite static copy. Dimuat di footer via `@if (Route::is(['document-center']))` di `footer-scripts.blade.php`.

**Admin actions** (Upload Document, Categories button) disembunyikan di HTML dan hanya di-`show` oleh JS setelah identity endpoint dikonfirmasi admin (`/v1/identity/auth/me`).

---

## Flow Bisnis End-to-End

```
[HR Admin]
  1. Buka /document-center
  2. (Optional) Buat Document Category via modal "Categories"
     → Nama category, aktif/nonaktif
  3. Klik "Upload Document"
     → Pilih karyawan, isi title, pilih category, set visibility,
        opsional expiry date, lalu pilih file
     → Submit → file di-store ke storage/app/public/documents/{companyId}/
  4. Dokumen muncul di tabel dengan kolom: Title, Employee, Category,
     File (link download), Visibility badge, Expires, Uploaded By, Aksi
  5. HR bisa Edit metadata (title, category, visibility, expiry)
     atau Delete dokumen (file ikut dihapus dari disk)

[Karyawan]
  1. Buka /document-center
  2. Melihat hanya dokumen miliknya yang visibility = employee_visible
  3. Bisa klik download langsung dari kolom File
  4. Tidak melihat dokumen HR-only dan dokumen karyawan lain
```

---

## Visibilitas Dokumen

| Nilai              | Arti Bisnis                                                            |
|--------------------|------------------------------------------------------------------------|
| `hr_only`          | Hanya HR/Admin yang bisa lihat dan unduh. Karyawan tidak lihat.        |
| `employee_visible` | Karyawan pemilik dokumen bisa lihat dan unduh. HR tetap bisa lihat.    |

---

## Lifecycle Dokumen

```
Draft upload → Tersimpan (aktif) → (Edit metadata) → (Soft deleted)
                                 ↘ Expired (informasi, bukan status DB)
```

- Dokumen tidak punya status enum. "Expired" hanya informatif via kolom `expires_at` — tidak ada auto-archival.
- Hapus dokumen = soft delete di DB + hard delete file dari disk.
- Edit dokumen **tidak** mengganti file — hanya metadata (title, description, category, visibility, expiresAt).

---

## Integrasi

- **Employee & Organization**: employee picker di upload modal memanggil `GET /v1/hcm/employees`; dokumen direlasikan ke `employee_profiles.id`.
- **Package/Feature Gating**: halaman dan API hanya aktif jika paket perusahaan memiliki fitur `employee_document_center`. Dicheck via `hcm.web.feature:employee_document_center` (web) dan `hcm.api.feature:employee_document_center` (API).
- **Sidebar / Nav**: item menu muncul jika `$canViewDocumentCenterMenu` true (kombinasi feature aktif + permission).

---

## Kontrak API

Prefix: `GET|POST|PUT|DELETE /v1/hcm/document-center/*`

Middleware API: `['api.token', 'tenant.context', 'hcm.api.feature:employee_document_center']`

Response envelope: `{ success: true|false, data?: ..., error?: { code, message } }`

### Categories

| Method | Endpoint                                    | Guard                        | Keterangan                                          |
|--------|---------------------------------------------|------------------------------|-----------------------------------------------------|
| GET    | `/v1/hcm/document-center/categories`        | `document_center.view\|manage` | Non-admin hanya lihat `is_active=true`            |
| POST   | `/v1/hcm/document-center/categories`        | `document_center.manage`     | Body: `{ name, description?, isActive? }`           |
| PUT    | `/v1/hcm/document-center/categories/{id}`   | `document_center.manage`     | Body: partial `{ name?, description?, isActive? }`  |
| DELETE | `/v1/hcm/document-center/categories/{id}`   | `document_center.manage`     | Dokumen terkait kehilangan category (set null)      |

**Response category object:**
```json
{
  "id": 1, "uuid": "...", "name": "Kontrak Kerja",
  "description": "", "isActive": true
}
```

### Documents

| Method | Endpoint                                         | Guard                              | Keterangan                                             |
|--------|--------------------------------------------------|------------------------------------|--------------------------------------------------------|
| GET    | `/v1/hcm/document-center/documents`              | `document_center.view\|manage` atau employee self | Query: `employeeProfileId`, `categoryId`, `visibility`, `q`, `perPage` |
| POST   | `/v1/hcm/document-center/documents`              | `document_center.manage`           | `multipart/form-data` dengan field `file`              |
| PUT    | `/v1/hcm/document-center/documents/{id}`         | `document_center.manage`           | JSON body, metadata only (no file re-upload)           |
| DELETE | `/v1/hcm/document-center/documents/{id}`         | `document_center.manage`           | Soft delete DB + hard delete file di disk              |
| GET    | `/v1/hcm/document-center/documents/{id}/download`| Employee self atau HR              | Stream file; karyawan diblock jika bukan miliknya atau `hr_only` |

**Query params `GET /documents` (admin):**
| Param              | Tipe    | Keterangan                          |
|--------------------|---------|-------------------------------------|
| `employeeProfileId`| integer | Filter per karyawan                 |
| `categoryId`       | integer | Filter per category                 |
| `visibility`       | string  | `hr_only` atau `employee_visible`   |
| `q`                | string  | Full-text search title/nama file/deskripsi |
| `perPage`          | integer | Default 20, max 100                 |

**POST body fields (upload, multipart):**
| Field            | Wajib | Keterangan                              |
|------------------|-------|-----------------------------------------|
| `file`           | ✓     | File binary, max 20 MB                  |
| `employeeProfileId` | ✓  | ID employee pemilik dokumen             |
| `title`          | ✓     | Judul dokumen, max 500 char             |
| `description`    |       | Deskripsi, max 5000 char                |
| `categoryId`     |       | ID category                             |
| `visibility`     | ✓     | `hr_only` atau `employee_visible`       |
| `expiresAt`      |       | Tanggal kedaluwarsa (Y-m-d)             |

**Response document object:**
```json
{
  "id": 1,
  "uuid": "...",
  "title": "Kontrak Kerja 2026",
  "description": "",
  "originalName": "kontrak-ali.pdf",
  "mimeType": "application/pdf",
  "sizeBytes": 204800,
  "visibility": "employee_visible",
  "expiresAt": "2027-01-01",
  "createdAt": "2026-05-02T10:00:00+07:00",
  "category": { "id": 1, "name": "Kontrak Kerja" },
  "employee": { "id": 5, "uuid": "...", "fullName": "Ali Basuki" },
  "uploadedBy": { "id": 2, "name": "HR Manager" },
  "downloadUrl": "/v1/hcm/document-center/documents/1/download"
}
```

**Response `GET /documents` (paginated):**
```json
{
  "success": true,
  "data": [ ...document objects... ],
  "meta": { "currentPage": 1, "lastPage": 3, "total": 42, "perPage": 20 }
}
```

---

## Database

### Migrasi

- `backend/database/migrations/2026_05_09_000100_create_hcm_employee_document_center_tables.php`

### Tabel

**`hcm_employee_document_categories`**

| Kolom          | Tipe         | Keterangan                        |
|----------------|--------------|-----------------------------------|
| `id`           | bigint PK    |                                   |
| `uuid`         | char(36)     | Auto-generated via `AssignsUuid`  |
| `company_id`   | bigint FK    | Tenant isolation                  |
| `company_uuid` | char(36)     | Synced di `booted()`              |
| `name`         | varchar(200) | Unique per company                |
| `description`  | text         | Nullable                          |
| `is_active`    | boolean      | Default true                      |

**`hcm_employee_documents`**

| Kolom                  | Tipe                  | Keterangan                                        |
|------------------------|-----------------------|---------------------------------------------------|
| `id`                   | bigint PK             |                                                   |
| `uuid`                 | char(36)              | Auto-generated                                    |
| `company_id`           | bigint FK             | Tenant isolation                                  |
| `company_uuid`         | char(36)              |                                                   |
| `employee_profile_id`  | bigint FK             | Wajib                                             |
| `employee_profile_uuid`| char(36)              |                                                   |
| `category_id`          | bigint FK nullable    | Nullable jika belum dikategorikan                 |
| `category_uuid`        | char(36) nullable     |                                                   |
| `title`                | varchar(500)          |                                                   |
| `description`          | text nullable         |                                                   |
| `file_path`            | varchar(1000)         | Path relatif di disk `public`                     |
| `original_name`        | varchar(500)          | Nama file asli                                    |
| `mime_type`            | varchar(200) nullable |                                                   |
| `size_bytes`           | bigint nullable       |                                                   |
| `disk`                 | varchar(50)           | Default `public`                                  |
| `visibility`           | enum                  | `hr_only` (default) atau `employee_visible`       |
| `expires_at`           | date nullable         |                                                   |
| `uploaded_by`          | bigint FK nullable    | `users.id`                                        |
| `uploaded_by_uuid`     | char(36) nullable     |                                                   |
| `deleted_at`           | timestamp nullable    | Soft delete                                       |

---

## RBAC

Selaras dengan:
- `docs/planning/active-hcm-templates-and-permissions.md`

Permission codes:
| Kode                       | Aksi yang Diizinkan                            |
|----------------------------|------------------------------------------------|
| `document_center.manage`   | Full CRUD categories + documents               |
| `document_center.view`     | Read-only semua dokumen semua karyawan         |
| *(tidak ada permission)*   | Karyawan: self-service — hanya dokumen sendiri yang `employee_visible` |

---

## Existing vs Target

| Aspek                          | Status          | Catatan                                                                 |
|--------------------------------|-----------------|-------------------------------------------------------------------------|
| CRUD categories                | ✅ Aktif         |                                                                         |
| Upload dokumen (multipart)     | ✅ Aktif         |                                                                         |
| Edit metadata dokumen          | ✅ Aktif         | Re-upload file belum didukung — hanya metadata                          |
| Download dengan access control | ✅ Aktif         | Karyawan diblock jika `hr_only` atau bukan miliknya                    |
| Employee self-service view     | ✅ Aktif         |                                                                         |
| Auto-archival saat expired     | ❌ Belum ada     | `expires_at` informatif saja; tidak ada cron/job untuk auto-archive     |
| Notifikasi dokumen baru        | ❌ Belum ada     | Tidak ada email/push notification ke karyawan saat dokumen diupload     |
| Bulk upload                    | ❌ Belum ada     |                                                                         |
| Preview dokumen in-browser     | ❌ Belum ada     | Link download membuka file di tab baru, tanpa preview PDF embedded       |
| Riwayat versi dokumen          | ❌ Belum ada     | Satu dokumen = satu file; versi baru = upload ulang secara manual       |

---

## Negative Scenario Yang Digate

- Request tanpa active company context → `422 TENANT_CONTEXT_REQUIRED`
- Karyawan mencoba mengakses dokumen HR-only → `403 AUTH_FORBIDDEN`
- Karyawan mencoba mengunduh dokumen milik karyawan lain → `403 AUTH_FORBIDDEN`
- User dengan `document_center.view` mencoba mutasi → `403 AUTH_FORBIDDEN`
- Upload file tanpa `employee_document_center` feature aktif di paket → `403` dari middleware `hcm.api.feature`
- Category delete: dokumen yang terkait tidak dihapus, hanya `category_id` di-set null

---

## File & Lokasi Kode

| Komponen                | Path                                                                                 |
|-------------------------|--------------------------------------------------------------------------------------|
| Migration               | `backend/database/migrations/2026_05_09_000100_create_hcm_employee_document_center_tables.php` |
| Model Category          | `backend/app/Models/HcmEmployeeDocumentCategory.php`                                |
| Model Document          | `backend/app/Models/HcmEmployeeDocument.php`                                        |
| Controller              | `backend/app/Http/Controllers/Api/HcmEmployeeDocumentController.php`                |
| API Routes              | `backend/routes/api/document-center.php`                                            |
| Web Route               | `backend/routes/web/document-center.php`                                            |
| Blade (entry)           | `backend/resources/views/document-center.blade.php`                                 |
| Blade (halaman)         | `backend/resources/views/hrm/document-center.blade.php`                             |
| Blade (modals)          | `backend/resources/views/hcm/partials/document-center-modals.blade.php`             |
| Frontend JS (source)    | `frontend/resources/js/document-center.js`                                           |
| Frontend JS (built)     | `backend/public/build/js/document-center.js`                                        |

---

## Status Implementasi

| Komponen                       | Status         |
|--------------------------------|----------------|
| Migration                      | ✅ Dibuat       |
| Model + booted() UUID sync     | ✅ Dibuat       |
| API Controller (full CRUD)     | ✅ Dibuat       |
| API Routes                     | ✅ Terdaftar    |
| Web Route                      | ✅ Terdaftar    |
| Sidebar menu + permission vars | ✅ Ditambahkan  |
| Header nav menu                | ✅ Ditambahkan  |
| Blade view                     | ✅ Dibuat       |
| Modal CRUD                     | ✅ Dibuat       |
| Frontend JS                    | ✅ Dibuat       |
| Build asset                    | ✅ Tersalin ke `public/build/js/` |
| `php artisan migrate`          | ✅ Dijalankan di local dev DB (`arcav_hcm`) |
| Permission di role matrix      | ✅ Di-assign ke role template (`docs/planning/active-hcm-templates-and-permissions.md` + `hcm-permission-scope-reference.md`) |
| PHPUnit tests                  | ✅ Dibuat (`DocumentCenterApiTest`, 13 pass / 121 assertions di `--env=testing`) |
| Vitest UI wiring tests         | ✅ Dibuat (`document-center-api-contract.test.js`, 12 pass) |
