# Tickets

## Ringkasan

Modul tickets sudah memakai API produksi untuk pengelolaan tiket internal tenant, baik dari sisi employee maupun admin. Fitur ini menutup alur create, assign, progress, komentar, attachment, sampai close ticket, dan juga dipakai sebagai target eskalasi dari modul lain seperti asset issue reporting.

## Akses

- Employee: boleh membuat tiket sendiri, melihat tiket sendiri, mengubah atau menghapus tiket sendiri selama belum `closed`, serta menambah komentar/attachment.
- HCM Admin: melihat semua ticket tenant, mengubah status, assign/reassign assignee, mengatur SLA due date, dan menghapus ticket.
- Master kategori ticket adalah admin-only.

## UI Aktif

- `/ticket-master` untuk master kategori.
- `/tickets-admin`, `/tickets-employee`, `/tickets-grid`, dan `/ticket-details/{id}` untuk list/grid/detail.
- JS aktif: `frontend/resources/js/tickets-data.js` dengan sinkron build ke `backend/public/build/js/tickets-data.js`.

## Flow Bisnis End-to-End

1. Employee atau admin membuat ticket baru.
2. Sistem menyimpan ticket ke tenant aktif (`company_id`) agar list/detail tidak bocor saat reporter punya membership di lebih dari satu company.
3. Sistem menyimpan komentar, attachment, dan assignment history sesuai perubahan status/assignee.
3. HCM Admin mengelola SLA, progress, assignment, dan penutupan ticket.
4. Jika issue berasal dari asset management, modul asset mengeskalasi ke ticketing agar tindak lanjut terpusat di workflow ticket.

## Lifecycle Dan Keputusan Bisnis

- Status utama: `open`, `in_progress`, `resolved`, `closed`.
- Closed ticket lock: employee tidak boleh edit/delete/comment/upload attachment lagi saat ticket sudah `closed`; admin tetap bisa reopen atau mengelola dari panel admin bila memang perlu.
- Ownership boundary: employee tidak boleh mengakses atau mengubah tiket milik user lain.
- Tenant boundary: assignable users dan assignee target wajib berasal dari active company yang sama.
- Assignment history wajib terlacak agar perpindahan penanggung jawab tidak hilang.

## Integrasi

- Asset Management: `POST /v1/hcm/assets/{asset}/issue-report` membuat atau memicu alur ticket untuk issue asset. Lihat `docs/features/asset-management/README.md`.
- User/employee context: reporter, assignee, dan permission mengikuti tenant dan identity user aktif. Lihat `docs/features/identity-auth/README.md` dan `docs/features/employees-organization/README.md`.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Ringkasan fungsi

- Employee:
  - create ticket sendiri
  - lihat list/detail ticket sendiri
  - update/delete ticket sendiri jika belum `closed`
  - tambah komentar + attachment di ticket sendiri
- HCM Admin:
  - lihat semua ticket
  - ubah status (`open`, `in_progress`, `resolved`, `closed`)
  - assign/reassign assignee
  - atur SLA due date
  - delete ticket

## Existing Vs Target

- Existing: admin/employee CRUD, comments, attachments, assignment history, dan SLA field sudah aktif di runtime.
- Existing: frontend sudah tidak lagi memakai dummy template.
- Existing: payload FE aktif memakai numeric `categoryId` dan numeric `assigneeUserId`, dan runtime backend sekarang menerima kontrak yang sama seperti docs/API test.
- Existing: asset issue reporting sekarang ikut menulis `company_id` ke ticket baru agar eskalasi asset tetap tenant-safe.
- Target: otomasi eskalasi lintas modul selain asset issue masih bisa diperluas bila dibutuhkan.

## Data model

- `tickets`
  - `company_id` tenant pemilik ticket
  - `user_id` reporter
  - `code` unik
  - `subject`, `description`, `category`
  - `priority`: `low|medium|high|urgent`
  - `status`: `open|in_progress|resolved|closed`
  - `sla_due_at`, `assignee_user_id`, `resolver_user_id`, `resolved_at`, `closed_at`
  - soft deletes
- `ticket_comments`
- `ticket_attachments`
- `ticket_assignment_histories`

## Endpoint

Base: `/v1/hcm/tickets`

- `GET /tickets`
- `POST /tickets`
- `GET /tickets/{id}`
- `PUT /tickets/{id}`
- `DELETE /tickets/{id}`
- `POST /tickets/{id}/comments`
- `POST /tickets/{id}/attachments`
- `GET /tickets/{id}/attachments/{attachmentId}/download`
- `GET /tickets/assignable-users` (admin only)
- `GET /tickets/category-options` (semua auth)
- `GET/POST/PUT/DELETE /tickets/categories` (admin only)

## Catatan implementasi FE

- Shell Blade: `backend/resources/views/tickets*.blade.php`
- Modal create: `backend/resources/views/hcm/partials/ticket-modals.blade.php`
- Wiring JS: `frontend/resources/js/tickets-data.js`
- Sinkron build: `backend/public/build/js/tickets-data.js`

## Test coverage

- `backend/tests/Feature/TicketApiTest.php`:
  - employee own CRUD
  - forbidden cross-ownership
  - admin assign/status management
  - closed ticket lock
  - attachment validation
  - numeric admin assignee contract
  - same-tenant assignee validation

- `backend/tests/Feature/TicketTenantScopeTest.php`:
  - list scoped by active company
  - ticket tidak bocor antar membership reporter multi-company
  - assignable users scoped by active company

Wiring frontend ke API (Vitest):
- `backend/tests/ui/tickets-api-contract.test.js`
- Jalankan: `cd backend && npm run test:ui`
