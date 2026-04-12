# Tickets

Modul tickets sudah di-wire ke API produksi (bukan data dummy template) untuk halaman:
- `/ticket-master` (master kategori, admin)
- `/tickets-admin` (list admin)
- `/tickets-employee` (list employee)
- `/tickets-grid` (grid)
- `/ticket-details/{id}` (detail resource-oriented)

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

## Data model

- `tickets`
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
